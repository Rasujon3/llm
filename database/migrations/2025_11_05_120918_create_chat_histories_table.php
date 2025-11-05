<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_histories', function (Blueprint $table) {
            $table->id();

            // 👤 user reference (optional)
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');

            // 💬 user's input (prompt)
            $table->text('prompt')->nullable()->comment('User’s message or voice-converted text');

            // 🤖 model’s or system’s response
            $table->longText('response')->nullable()->comment('ChatGPT or database response');

            // 📦 source (e.g., "chatgpt", "local-db", "system")
            $table->string('source')->default('chatgpt')->comment('Response source type');

            // ⚙️ optional metadata (for debugging / context)
            $table->json('meta')->nullable()->comment('Extra info: temperature, tokens, etc.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_histories');
    }
};
