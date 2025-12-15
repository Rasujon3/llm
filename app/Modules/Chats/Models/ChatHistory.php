<?php

namespace App\Modules\Chats\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    use HasFactory;

    protected $table = 'chat_histories';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'prompt',
        'response',
        'image_base64',
        'source',
        'meta',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'created_at',
        'created_by',
        'updated_by',
        'user_id',
    ];

    // ChatHistory.php
    protected $casts = [
        'meta' => 'array',
    ];
    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}
