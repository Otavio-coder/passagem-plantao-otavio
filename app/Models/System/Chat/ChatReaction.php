<?php

namespace App\Models\System\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatReaction extends Model
{
    protected $table = 'chat_reactions';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\System\User::class, 'user_id');
    }
}
