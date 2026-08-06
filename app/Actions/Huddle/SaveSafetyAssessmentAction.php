<?php

namespace App\Actions\Huddle;

use App\Enums\Huddle\UnitClassification;
use App\Models\Huddle\HuddleSafetyAssessment;
use Carbon\Carbon;

/**
 * Grava (ou atualiza) o card do Huddle de Segurança de uma unidade no dia.
 *
 * Um registro por setor/dia. Normaliza os tipos vindos do formulário: contagens
 * (inteiro >= 0 ou nulo), condicionais sim/não (boolean ou nulo), classificação da
 * unidade (enum) e campos de texto. Mantém a auditoria (created_by/updated_by).
 */
class SaveSafetyAssessmentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $sectorId, array $data, int $userId, ?string $date = null): HuddleSafetyAssessment
    {
        $date ??= Carbon::today()->toDateString();

        $assessment = HuddleSafetyAssessment::firstOrNew([
            'sector_id' => $sectorId,
            'huddle_date' => $date,
        ]);

        if (! $assessment->exists) {
            $assessment->created_by_user_id = $userId;
        }

        $assessment->fill([
            // Eixo 1 — contagens
            'expected_discharges' => $this->intOrNull($data['expected_discharges'] ?? null),
            'expected_admissions' => $this->intOrNull($data['expected_admissions'] ?? null),
            'blocked_beds_isolation' => $this->intOrNull($data['blocked_beds_isolation'] ?? null),
            'blocked_beds_maintenance' => $this->intOrNull($data['blocked_beds_maintenance'] ?? null),

            // Eixo 2 — sim/não + contagens
            'critical_patient_no_bed' => $this->boolOrNull($data['critical_patient_no_bed'] ?? null),
            'critical_medication_failure' => $this->boolOrNull($data['critical_medication_failure'] ?? null),
            'adverse_event_24h' => $this->boolOrNull($data['adverse_event_24h'] ?? null),
            'physical_chemical_restraint' => $this->boolOrNull($data['physical_chemical_restraint'] ?? null),
            'barrier_breach' => $this->boolOrNull($data['barrier_breach'] ?? null),
            'pressure_injuries' => $this->intOrNull($data['pressure_injuries'] ?? null),
            'falls' => $this->intOrNull($data['falls'] ?? null),

            // Eixo 3 — sim/não
            'staff_shortage' => $this->boolOrNull($data['staff_shortage'] ?? null),
            'critical_exam_delay' => $this->boolOrNull($data['critical_exam_delay'] ?? null),

            // Eixo 4 — classificação + textos
            'unit_classification' => $this->classificationOrNull($data['unit_classification'] ?? null),
            'justification' => $this->textOrNull($data['justification'] ?? null),
            'immediate_measures' => $this->textOrNull($data['immediate_measures'] ?? null),
        ]);

        $assessment->updated_by_user_id = $userId;
        $assessment->save();

        return $assessment;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function boolOrNull(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function classificationOrNull(mixed $value): ?string
    {
        return UnitClassification::tryFrom((string) $value)?->value;
    }

    private function textOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value !== '' && $value !== null ? (string) $value : null;
    }
}
