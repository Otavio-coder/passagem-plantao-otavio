<?php

namespace App\Models\Huddle;

use App\Enums\Huddle\RedReason;
use App\Enums\Huddle\RedReasonCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Motivo de "dia vermelho" (red day) associado a um HuddlePatientDay.
 *
 * category e reason_code são enums; auto_derived indica se o motivo foi sugerido
 * automaticamente (pendências/Tasy) ou informado manualmente pelo enfermeiro.
 */
class HuddleRedReason extends Model
{
    protected $table = 'huddle_red_reasons';

    protected $fillable = [
        'huddle_patient_day_id',
        'category',
        'reason_code',
        'auto_derived',
    ];

    protected function casts(): array
    {
        return [
            'category' => RedReasonCategory::class,
            'reason_code' => RedReason::class,
            'auto_derived' => 'boolean',
        ];
    }

    public function patientDay(): BelongsTo
    {
        return $this->belongsTo(HuddlePatientDay::class, 'huddle_patient_day_id');
    }
}
