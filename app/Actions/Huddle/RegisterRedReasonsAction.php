<?php

namespace App\Actions\Huddle;

use App\Enums\Huddle\RedReason;
use App\Models\Huddle\HuddlePatientDay;
use InvalidArgumentException;

/**
 * Substitui os motivos de "dia vermelho" de um registro de huddle.
 *
 * Regra metodológica vigente: no máximo 2 motivos por dia (Tabela 3 do estudo).
 * O limite mora aqui — não no schema — para permitir ajuste conforme a definição
 * final da área. Motivos informados manualmente entram como auto_derived = false.
 */
class RegisterRedReasonsAction
{
    public const MAX_REASONS = 2;

    /**
     * @param  RedReason[]  $reasons  Motivos escolhidos (duplicatas são ignoradas)
     *
     * @throws InvalidArgumentException quando excede o limite de motivos por dia
     */
    public function execute(HuddlePatientDay $day, array $reasons, int $userId): HuddlePatientDay
    {
        // Remove duplicatas preservando os enums (chaveado pelo value)
        $unique = [];
        foreach ($reasons as $reason) {
            $unique[$reason->value] = $reason;
        }
        $unique = array_values($unique);

        if (count($unique) > self::MAX_REASONS) {
            throw new InvalidArgumentException(
                'Máximo de '.self::MAX_REASONS.' motivos por dia vermelho.'
            );
        }

        $day->redReasons()->delete();

        foreach ($unique as $reason) {
            $day->redReasons()->create([
                'category' => $reason->category(),
                'reason_code' => $reason,
                'auto_derived' => false,
            ]);
        }

        $day->update(['updated_by_user_id' => $userId]);

        return $day->load('redReasons');
    }
}
