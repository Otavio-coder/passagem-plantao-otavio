<?php

namespace App\Actions\Huddle;

use App\Enums\Huddle\DayColor;
use App\Models\Huddle\HuddlePatientDay;
use Carbon\Carbon;

/**
 * Garante que existe o registro do dia para um paciente, iniciando em RED.
 *
 * Idempotente: apoiado no unique (nr_atendimento, huddle_date), rodar duas vezes
 * não duplica nem sobrescreve um registro já existente. É a porta de entrada usada
 * tanto pelo modal (ao abrir o paciente) quanto pelo job diário de materialização.
 */
class OpenPatientDayAction
{
    public function execute(int $nrAtendimento, int $sectorId, ?int $userId = null, ?string $date = null): HuddlePatientDay
    {
        $date ??= Carbon::today()->toDateString();

        return HuddlePatientDay::firstOrCreate(
            [
                'nr_atendimento' => $nrAtendimento,
                'huddle_date' => $date,
            ],
            [
                'sector_id' => $sectorId,
                'color' => DayColor::Red,
                'created_by_user_id' => $userId,
            ]
        );
    }
}
