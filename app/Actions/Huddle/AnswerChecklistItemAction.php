<?php

namespace App\Actions\Huddle;

use App\Enums\Huddle\DayColor;
use App\Enums\Huddle\HuddleChecklistItem;
use App\Models\Huddle\HuddleChecklistAnswer;
use App\Models\Huddle\HuddlePatientDay;

/**
 * Grava (ou atualiza) a resposta de um item do checklist e recalcula a cor do dia.
 *
 * A cor do HuddlePatientDay é sempre derivada do conjunto de respostas
 * (Red se qualquer item estiver Red), nunca definida solta — assim a cor do card
 * reflete fielmente o checklist.
 */
class AnswerChecklistItemAction
{
    public function execute(
        HuddlePatientDay $day,
        HuddleChecklistItem $item,
        string $answer,
        DayColor $signal,
        int $userId,
        ?string $responsible = null,
        ?string $dueAt = null,
        ?string $notes = null,
    ): HuddleChecklistAnswer {
        $record = HuddleChecklistAnswer::updateOrCreate(
            [
                'huddle_patient_day_id' => $day->id,
                'item_code' => $item->value,
            ],
            [
                'answer' => $answer,
                'signal' => $signal,
                'responsible' => $responsible ?: null,
                'due_at' => $dueAt ?: null,
                'notes' => $notes ?: null,
                'answered_by_user_id' => $userId,
            ]
        );

        $day->load('checklistAnswers');
        $day->update([
            'color' => $day->deriveColorFromChecklist(),
            'updated_by_user_id' => $userId,
        ]);

        return $record;
    }
}
