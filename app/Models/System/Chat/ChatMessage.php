<?php

namespace App\Models\System\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    public $timestamps = false; // custom created_at/updated_at

    protected $fillable = [
        'nr_atendimento',
        'cd_pessoa_fisica',
        'user_id',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\System\User::class, 'user_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ChatReaction::class, 'message_id');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(ChatMessagePin::class, 'message_id');
    }

    public function activePin(): HasOne
    {
        return $this->hasOne(ChatMessagePin::class, 'message_id')
            ->whereNull('unpinned_at');
    }
}
