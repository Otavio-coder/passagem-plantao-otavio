<?php

namespace App\Models\Huddle;

use App\Enums\Huddle\UnitClassification;
use App\Models\System\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Card do Huddle de Segurança (gestão à vista) de um dia de paciente.
 *
 * Agrupa os 4 eixos do preenchimento: ocupação/fluxo, risco clínico/segurança,
 * condições operacionais e classificação de risco da unidade.
 */
class HuddleSafetyAssessment extends Model
{
    protected $table = 'huddle_safety_assessments';

    protected $fillable = [
        'huddle_patient_day_id',
        'expected_discharges',
        'expected_admissions',
        'blocked_beds_isolation',
        'blocked_beds_maintenance',
        'critical_patient_no_bed',
        'critical_medication_failure',
        'adverse_event_24h',
        'physical_chemical_restraint',
        'barrier_breach',
        'pressure_injuries',
        'falls',
        'staff_shortage',
        'critical_exam_delay',
        'unit_classification',
        'justification',
        'immediate_measures',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'critical_patient_no_bed' => 'boolean',
            'critical_medication_failure' => 'boolean',
            'adverse_event_24h' => 'boolean',
            'physical_chemical_restraint' => 'boolean',
            'barrier_breach' => 'boolean',
            'staff_shortage' => 'boolean',
            'critical_exam_delay' => 'boolean',
            'unit_classification' => UnitClassification::class,
        ];
    }

    public function patientDay(): BelongsTo
    {
        return $this->belongsTo(HuddlePatientDay::class, 'huddle_patient_day_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
