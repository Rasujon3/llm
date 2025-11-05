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
        Schema::create('predefined_commands', function (Blueprint $table) {
            $table->id();

            // 🎤 voice or text command that user might say
            $table->string('command')->nullable()->comment('Exact or pattern of voice/text command');

            // 🗣️ optional alternate language version (e.g., Bangla)
            $table->string('command_bn')->nullable()->comment('Bangla or alternate language command');

            // 📜 short description of what this command does
            $table->string('description')->nullable();

            // 🧠 query or function to execute
            $table->text('action_query')->nullable()->comment('SQL or API endpoint or internal function name');

            // ⚙️ type: query, api, or custom logic
            $table->enum('action_type', ['query', 'api', 'custom'])->default('query');

            // ✅ is active?
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predefined_commands');
    }
};
