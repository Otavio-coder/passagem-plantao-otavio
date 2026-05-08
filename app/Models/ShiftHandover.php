<?php

namespace App\Models;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftHandover extends Model
{
    protected $fillable = [
        'user_id',
        'shift',
        'sector_ids',
        'sector_name',
        'bed_codes',
        'beds_total',
        'beds_visited',
        'started_at',
        'finished_at',
        'duration_seconds',
    ];

    protected $casts = [
        'sector_ids' => 'array',
        'bed_codes' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    public function formattedDuration(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $m = intdiv($this->duration_seconds, 60);
        $s = $this->duration_seconds % 60;

        return $m > 0
            ? "{$m}min {$s}s"
            : "{$s}s";
    }
}
