<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\DB;

/**
 * Pendências de hemoterapia programada nas próximas 48h.
 *
 * Fonte: cpoe_hemoterapia
 * Janela: SYSDATE até SYSDATE + 2 (48h)
 * Urgência: campo ie_urgencia = 'S'
 */
class HemotherapyPendingHandler extends AbstractPendingHandler
{
    private const TIPO_MAP = [
        '0' => 'Hemocomponente',
        '1' => 'Concentrado de Hemácias',
        '2' => 'Concentrado de Plaquetas',
        '3' => 'Plasma Fresco Congelado',
        '4' => 'Crioprecipitado',
        '5' => 'Concentrado de Granulócitos',
    ];

    protected function handlerName(): string { return 'Hemoterapia'; }

    protected function processChunk(array &$results, array $chunk): void
    {
        $rows = DB::connection('tasy')->select("
            SELECT
                nr_atendimento,
                dt_programada  AS dt_evento,
                ie_tipo_hemoterap,
                ie_urgencia
            FROM tasy.cpoe_hemoterapia
            WHERE nr_atendimento IN ({$this->placeholders($chunk)})
                AND dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                AND dt_suspensao IS NULL
        ", $chunk);

        foreach ($rows as $row) {
            if (!isset($results[$row->nr_atendimento])) continue;

            $tipo = self::TIPO_MAP[(string)($row->ie_tipo_hemoterap ?? '')] ?? 'Hemocomponente';

            $results[$row->nr_atendimento]['events'][] = [
                'tipo'                => 'hemoterapia',
                'icone'               => 'hemoterapia.svg',
                'descricao'           => 'Hemoterapia - ' . $tipo,
                'dt_evento'           => $row->dt_evento,
                'dt_evento_formatted' => date('d/m/Y H:i', strtotime($row->dt_evento)),
                'urgente'             => ($row->ie_urgencia ?? 'N') === 'S',
            ];
        }
    }
}
