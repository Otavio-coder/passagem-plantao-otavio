<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cirurgias e exames agendados — handler unificado para agenda_paciente.
 *
 * Substitui SurgeryPendingHandler + ExamsPendingHandler, eliminando:
 *   1. Dois round-trips separados na mesma tabela AGENDA_PACIENTE.
 *   2. N+1 Oracle: SurgeryPendingHandler chamava TASY.OBTER_DESC_PROC_INTERNO()
 *      em loop PHP — um round-trip Oracle por cirurgia.
 *   3. Funções Oracle por linha em SELECT (obter_desc_proc_interno, obter_descricao_procedimento)
 *      substituídas por LEFT JOINs estáticos avaliados uma vez pelo otimizador.
 *
 * Diferenciação cirurgia/exame: ie_carater_cirurgia NOT NULL e <> 'X' → cirurgia, caso contrário → exame.
 * Janela: TRUNC(SYSDATE) até SYSDATE + 30 dias.
 * Filtros: status NOT IN ('C','S'), dt_executada IS NULL.
 */
class AgendaPendingHandler extends AbstractPendingHandler
{
    protected function handlerName(): string
    {
        return 'Agenda (cirurgias+exames)';
    }

    protected function processChunk(array &$results, array $chunk): void
    {
        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.nr_atendimento,
                    CASE
                        WHEN ap.ie_carater_cirurgia IS NOT NULL
                         AND ap.ie_carater_cirurgia <> 'X'
                        THEN 'cirurgia'
                        ELSE 'exame'
                    END                                      AS tipo,
                    ap.dt_agenda                             AS dt_evento,
                    ap.hr_inicio,
                    ap.ds_cirurgia,
                    ap.ds_observacao,
                    ap.ie_carater_cirurgia,
                    ap.nr_seq_sala,
                    ap.cd_setor_atendimento,
                    NVL(
                        pi.ds_proc_exame,
                        NVL(proced.ds_procedimento, ap.ds_cirurgia)
                    )                                        AS descricao_proc,
                    pi.nr_seq_exame_lab
                FROM tasy.agenda_paciente ap
                LEFT JOIN tasy.proc_interno pi
                    ON pi.nr_sequencia = ap.nr_seq_proc_interno
                LEFT JOIN (
                    SELECT cd_procedimento, MIN(ds_procedimento) AS ds_procedimento
                    FROM tasy.procedimento
                    GROUP BY cd_procedimento
                ) proced ON proced.cd_procedimento = ap.cd_procedimento
                         AND ap.nr_seq_proc_interno IS NULL
                WHERE ap.nr_atendimento IN ({$this->placeholders($chunk)})
                    AND ap.dt_agenda >= TRUNC(SYSDATE)
                    AND ap.dt_agenda <= SYSDATE + 30
                    AND ap.ie_status_agenda NOT IN ('C', 'S')
                    AND ap.dt_executada IS NULL
                    AND (
                        (ap.ie_carater_cirurgia IS NOT NULL AND ap.ie_carater_cirurgia <> 'X')
                        OR
                        (ap.ie_carater_cirurgia IS NULL
                         AND (ap.nr_seq_proc_interno IS NOT NULL OR ap.cd_procedimento IS NOT NULL))
                    )
                ORDER BY ap.nr_atendimento, ap.dt_agenda, ap.hr_inicio NULLS LAST
            ", $chunk);

            $sectorLabels = [];
            $sectorCodes = array_values(array_unique(array_filter(array_map(
                static fn ($row) => (int) ($row->cd_setor_atendimento ?? 0),
                $rows
            ))));

            if (! empty($sectorCodes)) {
                $sectorRows = DB::connection('tasy')->select(
                    'SELECT cd_setor_atendimento, ds_setor_atendimento FROM tasy.setor_atendimento WHERE cd_setor_atendimento IN ('.implode(',', array_fill(0, count($sectorCodes), '?')).')',
                    $sectorCodes
                );

                foreach ($sectorRows as $sectorRow) {
                    $sectorLabels[(int) $sectorRow->cd_setor_atendimento] = $sectorRow->ds_setor_atendimento;
                }
            }

            foreach ($rows as $row) {
                if (! isset($results[$row->nr_atendimento])) {
                    continue;
                }

                $dtFormatted = null;
                if ($row->dt_evento) {
                    $dtFormatted = date('d/m/Y', strtotime($row->dt_evento));
                    if (! empty($row->hr_inicio)) {
                        $dtFormatted .= ' '.date('H:i', strtotime($row->hr_inicio));
                    }
                }

                if ($row->tipo === 'cirurgia') {
                    $carater = match ($row->ie_carater_cirurgia) {
                        'E' => 'Eletiva',
                        'U' => 'Urgência',
                        'G' => 'Emergência',
                        default => 'Não informado',
                    };

                    $parts = [];
                    if (! empty($row->ds_cirurgia)) {
                        $parts[] = $row->ds_cirurgia;
                    }
                    if (! empty($row->nr_seq_sala)) {
                        $parts[] = 'Sala: '.$row->nr_seq_sala;
                    }

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo' => 'cirurgia',
                        'icone' => 'general-surgery.svg',
                        'descricao' => $row->descricao_proc ?? 'Cirurgia Agendada',
                        'ds_subtipo' => 'Cirurgia '.$carater,
                        'dt_evento' => $row->dt_evento,
                        'dt_evento_formatted' => $dtFormatted,
                        'ds_complemento' => implode(' · ', $parts),
                        'carater' => $carater,
                        'setor_execucao' => $sectorLabels[(int) ($row->cd_setor_atendimento ?? 0)] ?? ($row->cd_setor_atendimento ?? null),
                        'local' => $row->ds_cirurgia ?? null,
                        'sala' => $row->nr_seq_sala ?? null,
                        'observacoes' => $row->ds_observacao ?? null,
                        'urgente' => in_array($row->ie_carater_cirurgia, ['U', 'G']),
                    ];
                } else {
                    $descricao = $row->descricao_proc ?? $row->ds_observacao ?? 'Procedimento Agendado';
                    $tipoAgenda = ! empty($row->nr_seq_exame_lab) ? 'exame' : 'procedimento';

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo' => $tipoAgenda,
                        'icone' => $tipoAgenda === 'exame' ? 'outpatient-department.svg' : 'tac.svg',
                        'descricao' => substr($descricao, 0, 80),
                        'ds_subtipo' => 'Agendamento',
                        'dt_evento' => $row->dt_evento,
                        'dt_evento_formatted' => $dtFormatted,
                        'setor_execucao' => $sectorLabels[(int) ($row->cd_setor_atendimento ?? 0)] ?? ($row->cd_setor_atendimento ?? null),
                        'urgente' => false,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] AgendaHandler query failed', [
                'exception' => $e,
                'attendance_count' => count($chunk),
            ]);
        }
    }
}
