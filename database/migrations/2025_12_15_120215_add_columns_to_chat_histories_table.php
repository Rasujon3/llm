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
        Schema::table('chat_histories', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->constrained('chat_conversations')->onDelete('cascade')->after('user_id');
            $table->longText('image_base64')->nullable()->after('response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_histories', function (Blueprint $table) {
            //
        });
    }
};
