<?php

namespace App\Models\System\Chat;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    public $timestamps = false; // custom created_at/updated_at

    protected $fillable = [
        'nr_atendimento',
        'cd_pessoa_fisica',
        'sector_id',
        'sector_name',
        'user_id',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
