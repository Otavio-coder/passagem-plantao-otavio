<?php

namespace App\Models\System\Chat;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessagePin extends Model
{
    protected $table = 'chat_message_pins';

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'nr_atendimento',
        'pinned_by',
        'pinned_at',
        'unpinned_at',
        'unpinned_by',
    ];

    protected $casts = [
        'pinned_at'   => 'datetime',
        'unpinned_at' => 'datetime',
    ];

    /** Active pin = no unpinned_at */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unpinned_at');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function pinnedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\System\User::class, 'pinned_by');
    }

    public function unpinnedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\System\User::class, 'unpinned_by');
    }
}
