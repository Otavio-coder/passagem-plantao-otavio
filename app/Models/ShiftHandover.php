<?php

namespace App\Models;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftHandover extends Model
{
    const STATUS_ACTIVE = 'active';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELED = 'canceled';

    const STATUS_ABANDONED = 'abandoned';

    const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'user_id',
        'status',
        'session_token',
        'shift',
        'sector_ids',
        'sector_name',
        'bed_codes',
        'beds_total',
        'beds_visited',
        'beds_expected',
        'completion_percentage',
        'forced_finish',
        'started_at',
        'last_activity_at',
        'resumed_at',
        'finished_at',
        'canceled_at',
        'abandoned_at',
        'cancel_reason',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'sector_ids' => 'array',
            'bed_codes' => 'array',
            'forced_finish' => 'boolean',
            'completion_percentage' => 'float',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'resumed_at' => 'datetime',
            'finished_at' => 'datetime',
            'canceled_at' => 'datetime',
            'abandoned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForShiftWindow(Builder $query, array $window): Builder
    {
        return $query->whereBetween('started_at', $window);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isAbandoned(): bool
    {
        return $this->status === self::STATUS_ABANDONED;
    }

    /** @deprecated Use isCompleted() */
    public function isFinished(): bool
    {
        return $this->isCompleted();
    }

    public function completionRate(): ?float
    {
        if ($this->beds_expected <= 0) {
            return null;
        }

        return round($this->beds_visited / $this->beds_expected * 100, 2);
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
