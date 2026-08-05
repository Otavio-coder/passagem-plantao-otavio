<?php

namespace App\Services\Huddle;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Regra de dias de funcionamento do Huddle: a rotina de preenchimento não ocorre
 * aos finais de semana (sábado/domingo) nem em feriados (config/huddle.php).
 */
class HuddleAvailability
{
    public function isAvailable(?CarbonInterface $date = null): bool
    {
        $date ??= Carbon::today();

        return ! $date->isWeekend() && ! $this->isHoliday($date);
    }

    /**
     * Motivo do bloqueio (para exibir na tela), ou null quando disponível.
     */
    public function blockedReason(?CarbonInterface $date = null): ?string
    {
        $date ??= Carbon::today();

        if ($date->isWeekend()) {
            return 'A rotina do Huddle não ocorre aos finais de semana.';
        }

        if ($this->isHoliday($date)) {
            return 'A rotina do Huddle não ocorre em feriados.';
        }

        return null;
    }

    private function isHoliday(CarbonInterface $date): bool
    {
        $holidays = (array) config('huddle.holidays', []);

        $fixed = $date->format('m-d');    // recorrente todo ano
        $full = $date->format('Y-m-d');   // data específica

        return in_array($fixed, $holidays, true) || in_array($full, $holidays, true);
    }
}
