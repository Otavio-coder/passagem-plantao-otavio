<?php

namespace App\Models;

use App\Models\System\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverAiAnalysis extends Model
{
    protected $fillable = [
        'analysis_type',
        'sector_id',
        'sector_name',
        'period_start',
        'period_end',
        'generated_by',
        'status',
        'total_messages',
        'total_patients',
        'total_professionals',
        'prompt_version',
        'prompt_used',
        'dataset_metadata',
        'analysis_text',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'dataset_metadata' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public static function findCompleted(
        string $type,
        Carbon $start,
        Carbon $end,
        ?int $sectorId = null
    ): ?static {
        return static::where('analysis_type', $type)
            ->where('period_start', $start->toDateString())
            ->where('period_end', $end->toDateString())
            ->when($sectorId !== null, fn ($q) => $q->where('sector_id', $sectorId))
            ->when($sectorId === null && $type === 'general', fn ($q) => $q->whereNull('sector_id'))
            ->where('status', 'completed')
            ->latest('generated_at')
            ->first();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }
}
