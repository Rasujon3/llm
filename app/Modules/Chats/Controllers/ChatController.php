<?php

namespace App\Modules\Chats\Controllers;

use App\Http\Controllers\AppBaseController;
use App\Modules\Chats\Models\ChatConversation;
use App\Modules\Chats\Models\ChatHistory;
use App\Modules\Chats\Models\PredefinedCommand;
use App\Modules\Facilities\Repositories\FacilityRepository;
use App\Modules\Facilities\Requests\FacilityRequest;
use App\Modules\Floors\Repositories\FloorRepository;
use App\Modules\Floors\Requests\FloorRequest;
use App\Modules\Ratings\Repositories\RatingRepository;
use App\Modules\Ratings\Requests\RatingRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends AppBaseController
{
    protected RatingRepository $ratingRepository;

    public function __construct(RatingRepository $ratingRepo)
    {
        $this->ratingRepository = $ratingRepo;
    }

    // Fetch all data
    public function conversations(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $data = ChatConversation::where('user_id', $request->user_id)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($data->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No conversations found for this user.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            // Log the error
            Log::error('Error in conversations data: ' , [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);

            return null;
        }
    }
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'conversation_id' => 'required|integer|exists:chat_conversations,id',
        ]);

        try {
            $histories = ChatHistory::where('user_id', $request->user_id)
                ->where('conversation_id', $request->conversation_id)
                ->orderBy('created_at', 'asc')
                ->get();

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
        } catch (Exception $e) {
            // Log the error
            Log::error('Error in storing data: ' , [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);

            return null;
        }
    }

    public function handleChat(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'conversation_id' => 'nullable|integer|exists:chat_conversations,id',
                'prompt' => 'required|string',
                'file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            ]);

            $userId = $request->user_id;
            $prompt = trim($request->prompt);
            $file   = $request->file('file');

            // 🟢 Conversation
            $conversation = null;
            if ($request->conversation_id) {
                $conversation = ChatConversation::where('id', $request->conversation_id)
                    ->where('user_id', $userId)
                    ->firstOrFail();
            } else {
                $conversation = ChatConversation::create([
                    'user_id' => $userId,
                    'title'   => Str::limit($prompt, 40),
                ]);
            }

            // 🔐 Safety check
            if (!$conversation) {
                return response()->json([
                    'status' => false,
                    'message' => 'Conversation not found',
                ], 404);
            }

            // 🤖 3️⃣ Otherwise, call ChatGPT API
            return $this->chatGPTResponse($userId, $conversation, $prompt, $file);
        } catch (Exception $e) {
            // Log the error
            Log::error('Error in storing data: ' , [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong!!!',
                'data' => [],
            ]);
        }
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

    protected function chatGPTResponse($userId, $conversation, $prompt, $file = null)
    {
        // 🔒 Safety check
        if (!is_object($conversation)) {
            throw new \Exception('Conversation must be an object');
        }

        // ===============================
        // 1️⃣ Prepare meta (for DB save)
        // ===============================
        $meta = [
            'has_image' => false,
        ];

        $imageBase64 = null;

        if ($file) {
            $mime = $file->getMimeType();
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $imageBase64 = "data:$mime;base64,$base64";

            $meta = [
                'has_image' => true,
                'image' => [
                    'mime' => $mime,
                    'base64' => $imageBase64,
                ],
            ];
        }

        // ===============================
        // 2️⃣ Load last 5 chat history (memory)
        // ===============================
        $history = ChatHistory::where('conversation_id', $conversation->id)
            ->latest()
            ->get()
            ->reverse();

        $input = [];

        foreach ($history as $item) {
            $input[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $item->prompt],
                ],
            ];

            $input[] = [
                'role' => 'assistant',
                'content' => [
                    ['type' => 'text', 'text' => strip_tags($item->response)],
                ],
            ];
        }

        // ===============================
        // 3️⃣ Current user message
        // ===============================
        $userMessage = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' =>
                        $prompt .
                        "\n\nRules:\n" .
                        "- Respond in Bangla\n" .
                        "- Return ONLY valid HTML body content\n" .
                        "- No <html>, <head>, <body>, no markdown",
                ],
            ],
        ];

        if ($imageBase64) {
            $userMessage['content'][] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageBase64,
                ],
            ];
        }

        $input[] = $userMessage;

        // ===============================
        // 4️⃣ OpenAI Responses API call
        // ===============================
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type'  => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => $input,
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI Error', $response->json());

            return response()->json([
                'status' => false,
                'message' => 'AI response failed',
            ], 500);
        }

        Log::info('OpenAI Response', $response->json());

        // ===============================
        // 5️⃣ Extract HTML body safely
        // ===============================
        $rawHtml = data_get(
            $response->json(),
            'choices.0.message.content',
            'No response'
        );

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($rawHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $doc->getElementsByTagName('body')->item(0);
        $html = $body ? $doc->saveHTML($body) : $rawHtml;

        // ===============================
        // 6️⃣ Save chat history
        // ===============================
        ChatHistory::create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'prompt' => $prompt,
            'response' => $html,
            'image_base64' => $imageBase64,
            'source' => 'chatgpt',
            'meta' => $meta,
        ]);

        // ===============================
        // 7️⃣ Return API response
        // ===============================
        return response()->json([
            'status' => true,
            'conversation_id' => $conversation->id,
            'source' => 'chatgpt',
            'data' => $html,
        ]);
    }
    protected function extractBodyHtml(string $html): string
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $doc->getElementsByTagName('body')->item(0);
        return $body ? $doc->saveHTML($body) : $html;
    }
}
