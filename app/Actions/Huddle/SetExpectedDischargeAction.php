<?php

namespace App\Actions\Huddle;

use App\Models\Huddle\HuddlePatientDay;

/**
 * Grava a previsão de alta (EDD) e os critérios clínicos de alta (CCD) — o elemento
 * "A" do SAFER bundle. Ambos opcionais: definir apenas a data, apenas os critérios,
 * ou limpar (passando null) é válido.
 */
class SetExpectedDischargeAction
{
    public function execute(
        HuddlePatientDay $day,
        ?string $expectedDischargeDate,
        ?string $clinicalCriteria,
        int $userId
    ): HuddlePatientDay {
        $day->update([
            'expected_discharge_date' => $expectedDischargeDate ?: null,
            'clinical_criteria' => $clinicalCriteria ?: null,
            'updated_by_user_id' => $userId,
        ]);

        return $day;
    }
}
