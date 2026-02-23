<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Service para buscar eventos pendentes – VERSÃO REESTRUTURADA
 *
 * Retorna por atendimento:
 *   ['events' => [...], 'discharge' => [...|null]]
 *
 * Events: exames, procedimentos, hemoterapia, antibióticos, óbito
 * Discharge: info de alta/alta médica/previsão de alta (vira tooltip no card)
 */
class PatientPendingEventsService
{
    private const CACHE_TTL = 180; // 3 minutos

    /**
     * Busca pendências do setor em batch.
     * Retorna [nr_atendimento => ['events' => [...], 'discharge' => array|null]]
     */
    public function getPendingEventsForSector(int $sectorId): array
    {
        $cacheKey = "sector_pending_fast_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sectorId) {
            $start = microtime(true);

            $rows = DB::connection('tasy')->select("
                SELECT
                    ua.nr_atendimento,
                    ap.cd_pessoa_fisica,
                    pf.dt_obito,
                    ap.dt_alta,
                    ap.dt_alta_medico,
                    ap.dt_previsto_alta,
                    ma2.ds_motivo_alta,
                    COUNT(DISTINCT CASE
                        WHEN pp.dt_baixa IS NULL AND pp.dt_coleta IS NULL
                            AND pp.ie_status_execucao = '10'
                            AND pm.dt_liberacao IS NOT NULL
                            AND pm.dt_suspensao IS NULL
                        THEN pp.nr_seq_proc_interno || ',' || NVL(TO_CHAR(pp.cd_procedimento), '0')
                    END) as prescr_count,
                    COUNT(DISTINCT CASE
                        WHEN hemo.dt_suspensao IS NULL
                            AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                        THEN hemo.nr_sequencia
                    END) as hemo_count
                FROM tasy.unidade_atendimento ua
                INNER JOIN tasy.atendimento_paciente ap ON ua.nr_atendimento = ap.nr_atendimento
                INNER JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
                LEFT JOIN tasy.motivo_alta ma2 ON ap.cd_motivo_alta_medica = ma2.cd_motivo_alta
                LEFT JOIN tasy.prescr_medica pm ON pm.nr_atendimento = ua.nr_atendimento
                    AND pm.dt_liberacao IS NOT NULL
                    AND pm.dt_suspensao IS NULL
                LEFT JOIN tasy.prescr_procedimento pp ON pm.nr_prescricao = pp.nr_prescricao
                    AND pp.ie_status_execucao = '10'
                    AND pp.dt_coleta IS NULL
                    AND pp.dt_baixa IS NULL
                    AND pp.ie_origem_proced <> 4
                LEFT JOIN tasy.cpoe_hemoterapia hemo ON hemo.nr_atendimento = ua.nr_atendimento
                    AND hemo.dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                    AND hemo.dt_suspensao IS NULL
                WHERE ua.cd_setor_atendimento = :sector_id
                    AND ua.ie_situacao = 'A'
                    AND ap.dt_alta IS NULL
                GROUP BY ua.nr_atendimento, ap.cd_pessoa_fisica, pf.dt_obito,
                    ap.dt_alta, ap.dt_alta_medico, ap.dt_previsto_alta, ma2.ds_motivo_alta
            ", ['sector_id' => $sectorId]);

            $queryTime = round((microtime(true) - $start) * 1000, 2);
            Log::info("[PendingEvents] Query principal: {$queryTime}ms - " . count($rows) . " pacientes");

            $results   = [];
            $needsPrescr = [];
            $needsHemo   = [];
            $allNrs      = [];

            foreach ($rows as $row) {
                $nr     = $row->nr_atendimento;
                $events = [];

                // ÓBITO – permanece como evento urgente no card
                if (!empty($row->dt_obito)) {
                    $events[] = [
                        'tipo'               => 'aviso',
                        'subtipo'            => 'obito',
                        'icone'              => 'alert.svg',
                        'descricao'          => 'Óbito registrado',
                        'urgente'            => true,
                        'dt_evento'          => $row->dt_obito,
                        'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_obito)),
                    ];
                }

                // DISCHARGE INFO – passa para tooltip no card (não é evento de pendência)
                $discharge = null;
                if (!empty($row->dt_alta)) {
                    $discharge = [
                        'tipo'               => 'alta',
                        'dt_alta'            => $row->dt_alta,
                        'dt_alta_formatted'  => date('d/m/Y H:i', strtotime($row->dt_alta)),
                        'ds_motivo_alta'     => $row->ds_motivo_alta ?? null,
                    ];
                } elseif (!empty($row->dt_alta_medico)) {
                    $discharge = [
                        'tipo'                       => 'alta_medica',
                        'dt_alta_medico'             => $row->dt_alta_medico,
                        'dt_alta_medico_formatted'   => date('d/m/Y H:i', strtotime($row->dt_alta_medico)),
                        'dt_previsto_alta'           => $row->dt_previsto_alta ?? null,
                        'dt_previsto_alta_formatted' => !empty($row->dt_previsto_alta)
                            ? date('d/m/Y H:i', strtotime($row->dt_previsto_alta)) : null,
                    ];
                } elseif (!empty($row->dt_previsto_alta)) {
                    $discharge = [
                        'tipo'                       => 'previsao_alta',
                        'dt_previsto_alta'           => $row->dt_previsto_alta,
                        'dt_previsto_alta_formatted' => date('d/m/Y H:i', strtotime($row->dt_previsto_alta)),
                    ];
                }

                if ($row->prescr_count > 0) $needsPrescr[] = $nr;
                if ($row->hemo_count > 0)   $needsHemo[]   = $nr;

                $allNrs[]       = $nr;
                $results[$nr]   = ['events' => $events, 'discharge' => $discharge];
            }

            if (!empty($needsPrescr)) {
                $this->addPrescricoesPendentes($results, $needsPrescr);
            }
            if (!empty($needsHemo)) {
                $this->addHemoterapia($results, $needsHemo);
            }
            if (!empty($allNrs)) {
                $this->addAntibioticos($results, $allNrs);
            }

            // Ordena cada lista por dt_evento ascending (mais próximo primeiro; null por último)
            foreach ($results as $nr => &$data) {
                usort($data['events'], function ($a, $b) {
                    $da = $a['dt_evento'] ?? null;
                    $db = $b['dt_evento'] ?? null;
                    // Urgentes primeiro, depois por data
                    if (($a['urgente'] ?? false) !== ($b['urgente'] ?? false)) {
                        return ($a['urgente'] ?? false) ? -1 : 1;
                    }
                    if ($da === null && $db === null) return 0;
                    if ($da === null) return 1;
                    if ($db === null) return -1;
                    return strcmp($da, $db);
                });
            }
            unset($data);

            $totalTime = round((microtime(true) - $start) * 1000, 2);
            Log::info("[PendingEvents] Total: {$totalTime}ms");

            return $results;
        });
    }

    // ─── PRESCRIÇÕES PENDENTES (exames + procedimentos) ──────────────────────

    private function addPrescricoesPendentes(array &$results, array $attendances): void
    {
        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection('tasy')->select("
                SELECT
                    pm.nr_atendimento,
                    pp.dt_prev_execucao       AS dt_evento,
                    pm.dt_prescricao          AS dt_solicitacao,
                    pm.dt_liberacao           AS dt_autorizacao,
                    pp.nr_seq_proc_interno,
                    pp.cd_procedimento,
                    pp.ie_origem_proced,
                    pp.ie_status_execucao,
                    CASE
                        WHEN pp.nr_seq_proc_interno IS NOT NULL
                            THEN tasy.obter_desc_proc_interno(pp.nr_seq_proc_interno)
                        ELSE tasy.obter_descricao_procedimento(pp.cd_procedimento, pp.ie_origem_proced)
                    END AS descricao,
                    CASE
                        WHEN pp.nr_seq_proc_interno IS NOT NULL THEN NULL
                        ELSE tasy.obter_valor_dominio(95, (
                            SELECT cd_tipo_procedimento
                            FROM tasy.procedimento
                            WHERE cd_procedimento = pp.cd_procedimento
                            AND ROWNUM = 1
                        ))
                    END AS ds_subtipo
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp ON pp.nr_prescricao = pm.nr_prescricao
                WHERE pm.nr_atendimento IN ($placeholders)
                    AND pp.ie_status_execucao = '10'
                    AND pp.dt_coleta IS NULL
                    AND pp.dt_baixa IS NULL
                    AND pm.dt_liberacao IS NOT NULL
                    AND pm.dt_suspensao IS NULL
                    AND pp.ie_origem_proced <> 4
                ORDER BY pm.nr_atendimento, pp.dt_prev_execucao
            ", $chunk);

            $statusMap = [
                '10' => 'Aguardando coleta',
                '20' => 'Coletado',
                '30' => 'Em análise',
            ];

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;

                // Interno (nr_seq_proc_interno) = procedimento; externo (cd_procedimento) = exame
                $isInternal = !empty($row->nr_seq_proc_interno);
                $tipo  = $isInternal ? 'procedimento' : 'exame';
                $icone = $isInternal ? 'outpatient-department.svg' : 'tac.svg';

                $tempo = '';
                if ($row->dt_autorizacao) {
                    $diff = time() - strtotime($row->dt_autorizacao);
                    $dias = floor($diff / 86400);
                    $tempo = $dias >= 1
                        ? $dias . ' dia(s) em aberto'
                        : round($diff / 3600) . 'h em aberto';
                }

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => $tipo,
                    'icone'              => $icone,
                    'descricao'          => substr($row->descricao ?? '', 0, 60),
                    'ds_subtipo'         => $row->ds_subtipo ?? null,
                    'dt_evento'          => $row->dt_evento,
                    'dt_evento_formatted'=> $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                    'dt_solicitacao'     => $row->dt_solicitacao ? date('d/m/Y', strtotime($row->dt_solicitacao)) : null,
                    'dt_autorizacao'     => $row->dt_autorizacao ? date('d/m/Y', strtotime($row->dt_autorizacao)) : null,
                    'tempo_pendente'     => $tempo,
                    'status_laudo'       => $statusMap[$row->ie_status_execucao] ?? 'Pendente',
                    'urgente'            => false,
                ];
            }
        }

        Log::info("[PendingEvents] Prescrições: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }

    // ─── HEMOTERAPIA ─────────────────────────────────────────────────────────

    private function addHemoterapia(array &$results, array $attendances): void
    {
        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection('tasy')->select("
                SELECT
                    nr_atendimento,
                    dt_programada AS dt_evento,
                    ie_tipo_hemoterap AS descricao,
                    ie_urgencia
                FROM tasy.cpoe_hemoterapia
                WHERE nr_atendimento IN ($placeholders)
                    AND dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                    AND dt_suspensao IS NULL
            ", $chunk);

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;
                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => 'hemoterapia',
                    'icone'              => 'blood-drop.svg',
                    'descricao'          => $row->descricao ?? 'Hemoterápico',
                    'dt_evento'          => $row->dt_evento,
                    'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_evento)),
                    'urgente'            => ($row->ie_urgencia ?? 'N') === 'S',
                ];
            }
        }

        Log::info("[PendingEvents] Hemoterapia: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }

    // ─── ANTIBIÓTICOS ────────────────────────────────────────────────────────

    private function addAntibioticos(array &$results, array $attendances): void
    {
        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection('tasy')->select("
                SELECT DISTINCT
                    pm.nr_atendimento,
                    INITCAP(m.ds_material)    AS descricao,
                    pt.nr_dia_util,
                    pm.dt_prescricao          AS dt_evento
                FROM tasy.material m
                JOIN tasy.medic_ficha_tecnica mf ON mf.nr_sequencia = (
                    SELECT nr_seq_ficha_tecnica
                    FROM tasy.material
                    WHERE cd_material = m.cd_material_estoque
                )
                JOIN tasy.prescr_material pt ON m.cd_material = pt.cd_material
                JOIN tasy.prescr_medica pm ON pm.nr_prescricao = pt.nr_prescricao
                WHERE mf.ie_antimicrobiano = 'S'
                    AND pm.nr_atendimento IN ($placeholders)
                    AND pm.dt_liberacao IS NOT NULL
                    AND pm.dt_validade_prescr >= SYSDATE
                    AND pm.dt_suspensao IS NULL
                    AND pt.dt_suspensao IS NULL
                    AND pt.nr_dia_util IS NOT NULL
                ORDER BY pm.nr_atendimento, INITCAP(m.ds_material)
            ", $chunk);

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;
                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => 'antibiotico',
                    'icone'              => 'infusion-pump.svg',
                    'descricao'          => $row->descricao ?? 'Antimicrobiano',
                    'ds_subtipo'         => 'Antibiótico',
                    'dt_evento'          => $row->dt_evento,
                    'dt_evento_formatted'=> $row->dt_evento ? date('d/m/Y', strtotime($row->dt_evento)) : null,
                    'ds_complemento'     => $row->nr_dia_util ? $row->nr_dia_util . ' dia(s) de terapia' : null,
                    'urgente'            => false,
                ];
            }
        }

        Log::info("[PendingEvents] Antibióticos: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
