<?php

namespace App\Actions\Huddle;

use App\Models\Huddle\HuddlePatientDay;
use InvalidArgumentException;

/**
 * Define o status do paciente no dia a partir do gate de previsão de alta em 72h:
 * - 'huddle': tem previsão em até 72h → entra no checklist do Huddle.
 * - 'round':  sem previsão em 72h → discutir em Round (sem checklist).
 */
class SetHuddleTriageAction
{
    public const STATUS_HUDDLE = 'huddle';

    public const STATUS_ROUND = 'round';

    public function execute(HuddlePatientDay $day, string $status, int $userId): HuddlePatientDay
    {
        if (! in_array($status, [self::STATUS_HUDDLE, self::STATUS_ROUND], true)) {
            throw new InvalidArgumentException("Status inválido: {$status}");
        }

        $day->update([
            'status' => $status,
            'updated_by_user_id' => $userId,
        ]);

        return $day;
    }
}
