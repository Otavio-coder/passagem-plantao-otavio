<?php

namespace App\Models;

use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackSubmission extends Model
{
    protected $fillable = [
        'sector',
        'shift',
        'rating',
        'difficulty',
        'suggestion',
        'is_anonymous',
        'user_id',
        'user_name_cached',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return 'Anônimo';
        }

        return $this->user_name_cached ?? ($this->user?->name ?? 'Desconhecido');
    }

    public function getShiftLabelAttribute(): string
    {
        return match ($this->shift) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            default => $this->shift,
        };
    }

    public function getRatingLabelAttribute(): string
    {
        return match ($this->rating) {
            'excelente' => 'Excelente',
            'bom' => 'Bom',
            'regular' => 'Regular',
            'ruim' => 'Ruim',
            default => $this->rating,
        };
    }

    public function getRatingColorAttribute(): string
    {
        return match ($this->rating) {
            'excelente' => 'text-emerald-600',
            'bom' => 'text-sky-600',
            'regular' => 'text-amber-500',
            'ruim' => 'text-red-500',
            default => 'text-gray-500',
        };
    }
}
