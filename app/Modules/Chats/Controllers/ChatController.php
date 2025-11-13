<?php

namespace App\Modules\Chats\Controllers;

use App\Http\Controllers\AppBaseController;
use App\Modules\Chats\Models\ChatHistory;
use App\Modules\Chats\Models\PredefinedCommand;
use App\Modules\Facilities\Repositories\FacilityRepository;
use App\Modules\Facilities\Requests\FacilityRequest;
use App\Modules\Floors\Repositories\FloorRepository;
use App\Modules\Floors\Requests\FloorRequest;
use App\Modules\Ratings\Repositories\RatingRepository;
use App\Modules\Ratings\Requests\RatingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ChatController extends AppBaseController
{
    protected RatingRepository $ratingRepository;

    public function __construct(RatingRepository $ratingRepo)
    {
        $this->ratingRepository = $ratingRepo;
    }

    // Fetch all data
    public function index($user_id)
    {
        $histories = ChatHistory::where('user_id', $user_id)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'prompt', 'response', 'source', 'created_at']);

        if ($histories->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No chat history found for this user.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $histories
        ]);
    }

    public function handleChat(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'prompt' => 'required|string',
        ]);

        $userId = $request->user_id;
        $prompt = strtolower(trim($request->prompt));

        // 🧠 1️⃣ Find best-matching predefined command
        $bestMatch = null;
        $bestScore = 0;

        $commands = PredefinedCommand::where('is_active', true)->get();

        foreach ($commands as $cmd) {
            $score = similar_text($prompt, strtolower($cmd->command), $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $bestMatch = $cmd;
            }
        }

        // 🧩 2️⃣ If similarity ≥ 70%, run saved SQL query
        if ($bestMatch && $bestScore >= 70) {
            return $this->myDbResponse($bestMatch, $bestScore, $userId, $prompt);
        }

        // 🤖 3️⃣ Otherwise, call ChatGPT API
        return $this->chatGPTResponse($bestScore, $userId, $prompt);

    }

    protected function myDbResponse($bestMatch, $bestScore, $userId, $prompt)
    {
        $result = collect(DB::select($bestMatch->action_query))->map(fn($r) => (array) $r);

        $responseText = $result->isEmpty()
            ? "No data found for your request."
            : $result;

        $html = view('components.data-table', ['data' => $result])->render();

        // save history
        ChatHistory::create([
            'user_id' => $userId,
            'prompt' => $prompt,
            'response' => $result->isEmpty() ? $responseText : $html,
            'source' => 'database',
        ]);

        return response()->json([
            'status' => true,
            'source' => 'database',
            'match_score' => round($bestScore, 2),
            'data' => $html
        ]);
    }

    protected function chatGPTResponse($bestScore, $userId, $prompt)
    {
        $history = ChatHistory::where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get(['prompt', 'response']);

        $messages = [];

// আগের কথোপকথন যুক্ত করা
        foreach ($history->reverse() as $item) {
            $messages[] = ['role' => 'user', 'content' => $item->prompt];
            $messages[] = ['role' => 'assistant', 'content' => $item->response];
        }

// এখন নতুন মেসেজ অ্যাড করা
        $messages[] = ['role' => 'user', 'content' => $prompt];
        // 🤖 3️⃣ Otherwise, call ChatGPT API
        $apiKey = env('OPENAI_API_KEY');
        $promptForHtml = "\n\nPlease provide the response in **HTML format**. " .
            "Use proper HTML tags like <p>, <ul>, <li>, <table>, <tr>, <td> if needed. " .
            "Do not include plain text outside HTML.";

        $messages[] = ['role' => 'user', 'content' => $promptForHtml];

        $chatResponse = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 3000,
        ]);

        $content = (bool)$chatResponse->json()['choices'][0]['message']['content'];
        $bodyHtml = $content ? '' : 'No response.';
        if ($content) {
            $responseHtml = $chatResponse->json()['choices'][0]['message']['content'] ?? '';

            // DOMDocument ইনিশিয়ালাইজ
            $doc = new \DOMDocument();

            // Suppress warnings (যেহেতু ChatGPT HTML প্রপারলি formatted নাও হতে পারে)
            libxml_use_internal_errors(true);
            $doc->loadHTML($responseHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // <body> ট্যাগ বের করা
            $body = $doc->getElementsByTagName('body')->item(0);

            $bodyHtml = $body ? $doc->saveHTML($body) : $responseHtml;

            // ✅ এখন $bodyHtml শুধুই <body> content
        }


        // 💾 Save response
        ChatHistory::create([
            'user_id' => $userId,
            'prompt' => $prompt,
//            'response' => $content,
            'response' => $content ? $bodyHtml : 'No response.',
            'source' => 'chatgpt',
        ]);

        // 🚀 Return response
        return response()->json([
            'status' => true,
            'source' => 'chatgpt',
            'match_score' => round($bestScore, 2),
//            'data' => $content,
            'data' => $bodyHtml,
        ]);
    }
}
