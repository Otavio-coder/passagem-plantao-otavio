<?php

namespace App\Actions\Huddle;

use App\Models\Huddle\HuddlePatientDay;

/**
 * Grava os comentários do dia (campo condicional exibido quando o dia é vermelho).
 */
class SaveHuddleCommentsAction
{
    public function execute(HuddlePatientDay $day, ?string $comments, int $userId): HuddlePatientDay
    {
        $day->update([
            'comments' => $comments ?: null,
            'updated_by_user_id' => $userId,
        ]);

        return $day;
    }
}
