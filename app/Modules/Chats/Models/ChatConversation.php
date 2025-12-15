<?php

namespace App\Modules\Chats\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    protected $table = 'chat_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'user_id',
    ];
    public function histories()
    {
        return $this->hasMany(ChatHistory::class, 'conversation_id');
    }
}
