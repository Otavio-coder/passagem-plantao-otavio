<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\Contracts\PendingEventHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HemotherapyPendingHandler implements PendingEventHandler
{
    private const TIPO_HEMO_MAP = [
        '0' => 'Hemocomponente',
        '1' => 'Concentrado de Hemácias',
        '2' => 'Concentrado de Plaquetas',
        '3' => 'Plasma Fresco Congelado',
        '4' => 'Crioprecipitado',
        '5' => 'Concentrado de Granulócitos',
    ];

    public function handle(array &$results, array $attendances): void
    {
        if (empty($attendances)) return;

        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection('tasy')->select("
                SELECT
                    nr_atendimento,
                    dt_programada AS dt_evento,
                    ie_tipo_hemoterap,
                    ie_urgencia
                FROM tasy.cpoe_hemoterapia
                WHERE nr_atendimento IN ($placeholders)
                    AND dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                    AND dt_suspensao IS NULL
            ", $chunk);

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;

                $tipoCode  = (string)($row->ie_tipo_hemoterap ?? '');
                $tipoDesc  = self::TIPO_HEMO_MAP[$tipoCode] ?? 'Hemocomponente';

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => 'hemoterapia',
                    'icone'              => 'hemoterapia.svg',
                    'descricao'          => 'Hemoterapia - ' . $tipoDesc,
                    'dt_evento'          => $row->dt_evento,
                    'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_evento)),
                    'urgente'            => ($row->ie_urgencia ?? 'N') === 'S',
                ];
            }
        }

        Log::info("[PendingEvents] Hemoterapia: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
