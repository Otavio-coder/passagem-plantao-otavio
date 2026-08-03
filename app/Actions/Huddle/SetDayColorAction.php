<?php

namespace App\Actions\Huddle;

use App\Enums\Huddle\DayColor;
use App\Models\Huddle\HuddlePatientDay;

/**
 * Define a cor do dia (RED/GREEN) de um registro de huddle.
 *
 * A autorização ('conduzir huddle') é responsabilidade do chamador (Livewire/Policy);
 * a Action apenas persiste a decisão e registra quem alterou.
 */
class SetDayColorAction
{
    public function execute(HuddlePatientDay $day, DayColor $color, int $userId): HuddlePatientDay
    {
        $day->update([
            'color' => $color,
            'updated_by_user_id' => $userId,
        ]);

        return $day;
    }
}
