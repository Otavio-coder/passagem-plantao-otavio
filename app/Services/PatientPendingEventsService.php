<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Service para buscar eventos pendentes – VERSÃO REESTRUTURADA
 *
 * Retorna por atendimento:
 *   ['events' => [...], 'discharge' => [...|null]]
 *
 * Events: exames, procedimentos, hemoterapia, antibióticos, quimioterapia, cirurgias, óbito
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
                    ma2.ds_motivo_alta,
                    apa.dt_previsto_alta AS apa_dt_previsto_alta,
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
                LEFT JOIN (
                    SELECT nr_atendimento, dt_previsto_alta,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_registro DESC) AS rn
                    FROM tasy.atend_previsao_alta
                    WHERE dt_registro >= SYSDATE - 10
                ) apa ON apa.nr_atendimento = ua.nr_atendimento AND apa.rn = 1
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
                    ap.dt_alta, ap.dt_alta_medico, ma2.ds_motivo_alta, apa.dt_previsto_alta
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

                // DISCHARGE INFO – passa para tooltip/inline no card
                // Também adiciona como evento na lista de pendências
                $discharge = null;
                if (!empty($row->dt_alta)) {
                    $discharge = [
                        'tipo'               => 'alta',
                        'dt_alta'            => $row->dt_alta,
                        'dt_alta_formatted'  => date('d/m/Y H:i', strtotime($row->dt_alta)),
                        'ds_motivo_alta'     => $row->ds_motivo_alta ?? null,
                    ];
                    // Adiciona como evento de pendência
                    $events[] = [
                        'tipo'               => 'alta',
                        'icone'              => 'alta.svg',
                        'descricao'          => 'Alta Efetivada' . (!empty($row->ds_motivo_alta) ? ' - ' . $row->ds_motivo_alta : ''),
                        'ds_subtipo'         => 'Alta',
                        'dt_evento'          => $row->dt_alta,
                        'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_alta)),
                        'urgente'            => true,
                    ];
                } elseif (!empty($row->dt_alta_medico)) {
                    $discharge = [
                        'tipo'                       => 'alta_medica',
                        'dt_alta_medico'             => $row->dt_alta_medico,
                        'dt_alta_medico_formatted'   => date('d/m/Y H:i', strtotime($row->dt_alta_medico)),
                        'dt_previsto_alta'           => $row->apa_dt_previsto_alta ?? null,
                        'dt_previsto_alta_formatted' => !empty($row->apa_dt_previsto_alta)
                            ? date('d/m/Y H:i', strtotime($row->apa_dt_previsto_alta)) : null,
                    ];
                    // Adiciona como evento de pendência
                    $descAltaMedica = 'Alta Médica';
                    if (!empty($row->apa_dt_previsto_alta)) {
                        $descAltaMedica .= ' | Prev. Alta: ' . date('d/m/Y', strtotime($row->apa_dt_previsto_alta));
                    }
                    $events[] = [
                        'tipo'               => 'alta_medica',
                        'icone'              => 'alta.svg',
                        'descricao'          => $descAltaMedica,
                        'ds_subtipo'         => 'Alta Médica',
                        'dt_evento'          => $row->dt_alta_medico,
                        'dt_evento_formatted'=> date('d/m/Y H:i', strtotime($row->dt_alta_medico)),
                        'urgente'            => true,
                    ];
                } elseif (!empty($row->apa_dt_previsto_alta)) {
                    $discharge = [
                        'tipo'                       => 'previsao_alta',
                        'dt_previsto_alta'           => $row->apa_dt_previsto_alta,
                        'dt_previsto_alta_formatted' => date('d/m/Y', strtotime($row->apa_dt_previsto_alta)),
                    ];
                    // Adiciona como evento de pendência
                    $events[] = [
                        'tipo'               => 'previsao_alta',
                        'icone'              => 'alta.svg',
                        'descricao'          => 'Previsão de Alta: ' . date('d/m/Y', strtotime($row->apa_dt_previsto_alta)),
                        'ds_subtipo'         => 'Previsão',
                        'dt_evento'          => $row->apa_dt_previsto_alta,
                        'dt_evento_formatted'=> date('d/m/Y', strtotime($row->apa_dt_previsto_alta)),
                        'urgente'            => false,
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
                $this->addQuimioterapia($results, $allNrs);
                $this->addCirurgias($results, $allNrs);
                $this->addAgendaExames($results, $allNrs);
            }

            // Ordena cada lista: urgentes primeiro, depois por proximidade ao momento atual (mais próximo primeiro; null por último)
            $now = time();
            foreach ($results as $nr => &$data) {
                usort($data['events'], function ($a, $b) use ($now) {
                    $da = $a['dt_evento'] ?? null;
                    $db = $b['dt_evento'] ?? null;
                    // Urgentes sempre primeiro
                    if (($a['urgente'] ?? false) !== ($b['urgente'] ?? false)) {
                        return ($a['urgente'] ?? false) ? -1 : 1;
                    }
                    if ($da === null && $db === null) return 0;
                    if ($da === null) return 1;
                    if ($db === null) return -1;
                    // Proximidade ao momento atual (menor distância absoluta primeiro)
                    $distA = abs(strtotime($da) - $now);
                    $distB = abs(strtotime($db) - $now);
                    return $distA - $distB;
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
                    pp.dt_prev_execucao                   AS dt_evento,
                    pm.dt_prescricao                      AS dt_solicitacao,
                    pm.dt_liberacao                       AS dt_autorizacao,
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
                ORDER BY pm.nr_atendimento, pp.dt_prev_execucao NULLS LAST
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

                // Tempo relativo baseado em dt_prev_execucao (quando será executado)
                // ou dt_autorizacao (há quanto tempo está em aberto)
                $tempo = '';
                $now   = time();
                if ($row->dt_evento) {
                    $tsEvento = strtotime($row->dt_evento);
                    $diff = $tsEvento - $now;
                    if ($diff > 0) {
                        // Evento no futuro
                        $horas = round($diff / 3600, 1);
                        $tempo = $diff < 86400
                            ? 'em ' . round($horas) . 'h'
                            : 'em ' . floor($diff / 86400) . 'd';
                    } else {
                        // Prazo já passou
                        $diff = abs($diff);
                        $tempo = $diff < 86400
                            ? round($diff / 3600) . 'h em aberto'
                            : floor($diff / 86400) . 'd em aberto';
                    }
                } elseif ($row->dt_autorizacao) {
                    $diff = $now - strtotime($row->dt_autorizacao);
                    $tempo = $diff < 86400
                        ? round($diff / 3600) . 'h em aberto'
                        : floor($diff / 86400) . 'd em aberto';
                }

                $descricao = substr($row->descricao ?? '', 0, 80);

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => $tipo,
                    'icone'              => $icone,
                    'descricao'          => $descricao,
                    'ds_subtipo'         => $row->ds_subtipo ?? null,
                    'dt_evento'          => $row->dt_evento,
                    'dt_evento_formatted'=> $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                    'dt_solicitacao'     => $row->dt_solicitacao ? date('d/m/Y H:i', strtotime($row->dt_solicitacao)) : null,
                    'dt_autorizacao'     => $row->dt_autorizacao ? date('d/m/Y H:i', strtotime($row->dt_autorizacao)) : null,
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

        // Mapeamento de ie_tipo_hemoterap para descrição legível
        $tipoHemoMap = [
            '0' => 'Hemocomponente',
            '1' => 'Concentrado de Hemácias',
            '2' => 'Concentrado de Plaquetas',
            '3' => 'Plasma Fresco Congelado',
            '4' => 'Crioprecipitado',
            '5' => 'Concentrado de Granulócitos',
        ];

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

                $tipoCode = (string)($row->ie_tipo_hemoterap ?? '');
                $tipoDesc = $tipoHemoMap[$tipoCode] ?? 'Hemocomponente';
                $descricao = 'Hemoterapia - ' . $tipoDesc;

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => 'hemoterapia',
                    'icone'              => 'hemoterapia.svg',
                    'descricao'          => $descricao,
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

            // Usa ROW_NUMBER para pegar apenas o registro mais recente por paciente/medicamento
            $rows = DB::connection('tasy')->select("
                SELECT nr_atendimento, descricao, nr_dia_util, qt_dose,
                       cd_unidade_medida_dose, ie_via_aplicacao, cd_intervalo, dt_inicio_medic
                FROM (
                    SELECT
                        pm.nr_atendimento,
                        INITCAP(TRIM(REGEXP_REPLACE(m.ds_material, '\\s*&&\\s*$', ''))) AS descricao,
                        pt.nr_dia_util,
                        pt.qt_dose,
                        pt.cd_unidade_medida_dose,
                        pt.ie_via_aplicacao,
                        pt.cd_intervalo,
                        pt.dt_inicio_medic,
                        ROW_NUMBER() OVER (
                            PARTITION BY pm.nr_atendimento, m.ds_material
                            ORDER BY pt.nr_dia_util DESC
                        ) AS rn
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
                )
                WHERE rn = 1
                ORDER BY nr_atendimento, descricao
            ", $chunk);

            foreach ($rows as $row) {
                if (!isset($results[$row->nr_atendimento])) continue;

                // Intervalo: extrai número de horas de cd_intervalo (ex: "8ATB" → "8/8h")
                $intervalo = '';
                if (!empty($row->cd_intervalo) && preg_match('/^(\d+)/', $row->cd_intervalo, $m)) {
                    $h = (int)$m[1];
                    $intervalo = $h . '/' . $h . 'h';
                }

                // Dose + unidade
                $dose = '';
                if (!empty($row->qt_dose)) {
                    $dose = (string)(int)$row->qt_dose;
                    if (!empty($row->cd_unidade_medida_dose)) {
                        $dose .= $row->cd_unidade_medida_dose;
                    }
                }

                // Complemento: "Dia X · 1000mg · IV · 8/8h"
                $parts = [];
                if ($row->nr_dia_util) $parts[] = 'Dia ' . $row->nr_dia_util;
                if ($dose)             $parts[] = $dose;
                if (!empty($row->ie_via_aplicacao)) $parts[] = $row->ie_via_aplicacao;
                if ($intervalo)        $parts[] = $intervalo;

                $results[$row->nr_atendimento]['events'][] = [
                    'tipo'               => 'antibiotico',
                    'icone'              => 'antimicrobiano.svg',
                    'descricao'          => substr($row->descricao ?? 'Antimicrobiano', 0, 60),
                    'ds_subtipo'         => 'Antimicrobiano',
                    'dt_evento'          => $row->dt_inicio_medic,
                    'dt_evento_formatted'=> $row->dt_inicio_medic
                        ? date('d/m/Y H:i', strtotime($row->dt_inicio_medic)) : null,
                    'ds_complemento'     => implode(' · ', $parts),
                    'urgente'            => false,
                ];
            }
        }

        Log::info("[PendingEvents] Antibióticos: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }

    // ─── QUIMIOTERAPIA ───────────────────────────────────────────────────────

    private function addQuimioterapia(array &$results, array $attendances): void
    {
        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            try {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                // Busca cd_pessoa_fisica para cada atendimento
                $personRows = DB::connection('tasy')->select("
                    SELECT nr_atendimento, cd_pessoa_fisica
                    FROM tasy.atendimento_paciente
                    WHERE nr_atendimento IN ($placeholders)
                ", $chunk);

                $attendanceToPerson = [];
                foreach ($personRows as $row) {
                    $attendanceToPerson[$row->nr_atendimento] = $row->cd_pessoa_fisica;
                }

                $personIds = array_unique(array_filter(array_values($attendanceToPerson)));
                if (empty($personIds)) continue;

                $personPlaceholders = implode(',', array_fill(0, count($personIds), '?'));

                $rows = DB::connection('tasy')->select("
                    SELECT
                        aq.cd_pessoa_fisica,
                        aq.dt_agenda AS dt_evento,
                        aq.ds_local,
                        aq.nm_medico_resp,
                        aq.ds_protocolo_medic,
                        aq.nr_ciclo
                    FROM tasy.agenda_quimioterapia_pep_v aq
                    WHERE aq.cd_pessoa_fisica IN ($personPlaceholders)
                        AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + 30
                    ORDER BY aq.cd_pessoa_fisica, aq.dt_agenda
                ", $personIds);

                foreach ($rows as $row) {
                    // Encontra o nr_atendimento correspondente ao cd_pessoa_fisica
                    $nr = array_search($row->cd_pessoa_fisica, $attendanceToPerson);
                    if ($nr === false || !isset($results[$nr])) continue;

                    // Monta descrição
                    $descricao = 'Quimioterapia';
                    if (!empty($row->ds_protocolo_medic)) {
                        $descricao .= ' - ' . $row->ds_protocolo_medic;
                    }
                    if (!empty($row->nr_ciclo)) {
                        $descricao .= ' (Ciclo ' . $row->nr_ciclo . ')';
                    }

                    // Monta complemento
                    $parts = [];
                    if (!empty($row->ds_local)) $parts[] = $row->ds_local;
                    if (!empty($row->nm_medico_resp)) $parts[] = $row->nm_medico_resp;

                    $results[$nr]['events'][] = [
                        'tipo'               => 'quimioterapia',
                        'icone'              => 'quimioterapia.svg',
                        'descricao'          => $descricao,
                        'ds_subtipo'         => 'Quimioterapia',
                        'dt_evento'          => $row->dt_evento,
                        'dt_evento_formatted'=> $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                        'ds_complemento'     => implode(' · ', $parts),
                        'local'              => $row->ds_local ?? null,
                        'medico'             => $row->nm_medico_resp ?? null,
                        'ciclo'              => $row->nr_ciclo ?? null,
                        'urgente'            => false,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[PendingEvents] Quimioterapia query failed: ' . $e->getMessage());
                continue;
            }
        }

        Log::info("[PendingEvents] Quimioterapia: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }

    // ─── CIRURGIAS ───────────────────────────────────────────────────────────

    private function addCirurgias(array &$results, array $attendances): void
    {
        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            try {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                $rows = DB::connection('tasy')->select("
                    SELECT
                        ap.nr_atendimento,
                        ap.dt_agenda AS dt_evento,
                        ap.hr_inicio,
                        ap.ds_cirurgia,
                        ap.ie_carater_cirurgia,
                        ap.ds_observacao,
                        ap.nr_seq_proc_interno,
                        ap.cd_procedimento,
                        ap.ie_origem_proced,
                        ap.nr_seq_sala
                    FROM tasy.agenda_paciente ap
                    WHERE ap.nr_atendimento IN ($placeholders)
                        AND ap.dt_agenda >= TRUNC(SYSDATE)
                        AND ap.dt_agenda <= SYSDATE + 30
                        AND ap.ie_carater_cirurgia IS NOT NULL
                        AND ap.ie_carater_cirurgia <> 'X'
                        AND ap.ie_status_agenda NOT IN ('C', 'S')
                        AND ap.dt_executada IS NULL
                    ORDER BY ap.nr_atendimento, ap.dt_agenda, ap.hr_inicio
                ", $chunk);

                foreach ($rows as $row) {
                    if (!isset($results[$row->nr_atendimento])) continue;

                    // Obtém descrição do procedimento
                    $descricao = $row->ds_cirurgia ?? 'Agendas de Cirurgia Recente';
                    if (!empty($row->nr_seq_proc_interno)) {
                        try {
                            $procResult = DB::connection('tasy')->selectOne("
                                SELECT TASY.OBTER_DESC_PROC_INTERNO(:seq) AS descricao FROM dual
                            ", ['seq' => $row->nr_seq_proc_interno]);
                            if ($procResult && !empty($procResult->descricao)) {
                                $descricao = $procResult->descricao;
                            }
                        } catch (\Throwable $e) {
                            // Mantém descrição original em caso de erro
                        }
                    }

                    // Caráter da cirurgia
                    $carater = match($row->ie_carater_cirurgia) {
                        'E' => 'Eletiva',
                        'U' => 'Urgência',
                        'G' => 'Emergência',
                        default => 'Não informado',
                    };

                    // Monta complemento
                    $parts = [];
                    if (!empty($row->ds_cirurgia)) $parts[] = $row->ds_cirurgia;
                    if (!empty($row->nr_seq_sala)) $parts[] = 'Sala: ' . $row->nr_seq_sala;

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo'               => 'cirurgia',
                        'icone'              => 'general-surgery.svg',
                        'descricao'          => $descricao,
                        'ds_subtipo'         => 'Cirurgia ' . $carater,
                        'dt_evento'          => $row->dt_evento,
                        'dt_evento_formatted'=> $row->dt_evento
                            ? date('d/m/Y', strtotime($row->dt_evento)) . (!empty($row->hr_inicio) ? ' ' . date('H:i', strtotime($row->hr_inicio)) : '')
                            : null,
                        'ds_complemento'     => implode(' · ', $parts),
                        'carater'            => $carater,
                        'local'              => $row->ds_cirurgia ?? null,
                        'sala'               => $row->nr_seq_sala ?? null,
                        'observacoes'        => $row->ds_observacao ?? null,
                        'urgente'            => in_array($row->ie_carater_cirurgia, ['U', 'G']), // Urgência ou Emergência
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[PendingEvents] Cirurgias query failed: ' . $e->getMessage());
                continue;
            }
        }

        Log::info("[PendingEvents] Cirurgias: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }

    // ─── AGENDA DE EXAMES / PROCEDIMENTOS (agenda_paciente sem caráter cirúrgico) ──

    private function addAgendaExames(array &$results, array $attendances): void
    {
        $start  = microtime(true);
        $chunks = array_chunk($attendances, 50);

        foreach ($chunks as $chunk) {
            try {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                $rows = DB::connection('tasy')->select("
                    SELECT
                        ap.nr_atendimento,
                        ap.dt_agenda         AS dt_evento,
                        ap.hr_inicio,
                        ap.ds_observacao,
                        ap.nr_seq_proc_interno,
                        ap.cd_procedimento,
                        ap.ie_origem_proced,
                        ap.ie_status_agenda,
                        CASE
                            WHEN ap.nr_seq_proc_interno IS NOT NULL
                                THEN tasy.obter_desc_proc_interno(ap.nr_seq_proc_interno)
                            WHEN ap.cd_procedimento IS NOT NULL
                                THEN tasy.obter_descricao_procedimento(ap.cd_procedimento, ap.ie_origem_proced)
                            ELSE NULL
                        END AS descricao
                    FROM tasy.agenda_paciente ap
                    WHERE ap.nr_atendimento IN ($placeholders)
                        AND ap.dt_agenda >= TRUNC(SYSDATE)
                        AND ap.dt_agenda <= SYSDATE + 30
                        AND ap.ie_carater_cirurgia IS NULL
                        AND ap.ie_status_agenda NOT IN ('C', 'S')
                        AND ap.dt_executada IS NULL
                        AND (ap.nr_seq_proc_interno IS NOT NULL OR ap.cd_procedimento IS NOT NULL)
                    ORDER BY ap.nr_atendimento, ap.dt_agenda, ap.hr_inicio NULLS LAST
                ", $chunk);

                foreach ($rows as $row) {
                    if (!isset($results[$row->nr_atendimento])) continue;

                    $descricao = $row->descricao ?? $row->ds_observacao ?? 'Procedimento Agendado';

                    // Formata data+hora combinando dt_agenda e hr_inicio
                    $dtEvento    = $row->dt_evento;
                    $dtFormatted = null;
                    if ($dtEvento) {
                        $dtFormatted = date('d/m/Y', strtotime($dtEvento));
                        if (!empty($row->hr_inicio)) {
                            $dtFormatted .= ' ' . date('H:i', strtotime($row->hr_inicio));
                        }
                    }

                    $results[$row->nr_atendimento]['events'][] = [
                        'tipo'               => 'exame',
                        'icone'              => 'tac.svg',
                        'descricao'          => substr($descricao, 0, 80),
                        'ds_subtipo'         => 'Agendamento',
                        'dt_evento'          => $dtEvento,
                        'dt_evento_formatted'=> $dtFormatted,
                        'urgente'            => false,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[PendingEvents] AgendaExames query failed: ' . $e->getMessage());
                continue;
            }
        }

        Log::info("[PendingEvents] AgendaExames: " . round((microtime(true) - $start) * 1000, 2) . "ms");
    }
}
