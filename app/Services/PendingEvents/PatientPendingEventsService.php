<?php

namespace App\Services\PendingEvents;

use App\Services\Scola\ScolaExamStatusService;
use App\Services\Tasy\TasyFormatter;
use App\Services\UsesRepositories;
use App\Support\PendingEventHelper;
use App\Support\PendingEventTypeClassifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Coordenador de eventos pendentes por setor.
 *
 * Retorna: [nr_atendimento => ['events' => [...], 'discharge' => array|null]]
 */
class PatientPendingEventsService
{
    use UsesRepositories;

    private const CACHE_TTL = 600; // 10 minutos

    private const CHUNK_SIZE = 200;

    public function getPendingEventsForSector(int $sectorId): array
    {
        $cacheKey = "sector_pending_fast_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->fetchEventsForSector($sectorId));
    }

    public function getFreshEventsForSector(int $sectorId): array
    {
        return $this->fetchEventsForSector($sectorId);
    }

    public function getPatientChecklistForAttendance(int $sectorId, int $attendanceNumber): array
    {
        $sectorEvents = $this->getPendingEventsForSector($sectorId);
        $patientEvents = $sectorEvents[$attendanceNumber] ?? ['events' => [], 'discharge' => null];

        $events = is_array($patientEvents['events'] ?? null) ? $patientEvents['events'] : [];
        $discharge = is_array($patientEvents['discharge'] ?? null) ? $patientEvents['discharge'] : null;

        $multidisciplinaryRequests = $this->multidisciplinary()->getDetailedMultidisciplinaryRequests($attendanceNumber);
        $openMultidisciplinaryRequests = array_values(array_filter(
            $multidisciplinaryRequests,
            fn (array $request): bool => ($request['ds_status'] ?? '') === 'Aberto'
        ));

        $exams = $this->buildChecklistSection(
            'Exames pendentes',
            $this->filterEventsByTypes($events, ['exame']),
            'Sem exames pendentes.',
            'blue'
        );

        $procedures = $this->buildChecklistSection(
            'Procedimentos pendentes',
            $this->filterEventsByTypes($events, ['procedimento', 'cirurgia', 'quimioterapia', 'hemoterapia']),
            'Sem procedimentos pendentes.',
            'amber'
        );

        $antibiotics = $this->buildChecklistSection(
            'Tratamentos antimicrobianos pendentes',
            $this->filterEventsByTypes($events, ['antibiotico']),
            'Sem antimicrobianos pendentes.',
            'emerald'
        );

        $multidisciplinary = $this->buildChecklistSection(
            'Avaliações multidisciplinares abertas',
            array_map(
                fn (array $request): array => [
                    'headline' => $request['ds_equipe_destino'] ?? 'Avaliação multidisciplinar',
                    'detail' => trim((string) ($request['ds_motivo_consulta'] ?? '')) ?: null,
                    'meta' => trim(sprintf(
                        '%s%s',
                        ! empty($request['nm_requisitante']) ? 'Solicitante: '.$request['nm_requisitante'] : '',
                        ! empty($request['nm_responsavel_resposta']) ? ($request['nm_requisitante'] ? ' · ' : '').'Resposta: '.$request['nm_responsavel_resposta'] : ''
                    )),
                    'status' => $request['ds_status'] ?? 'Aberto',
                    'tone' => ($request['ds_status'] ?? '') === 'Aberto' ? 'danger' : 'neutral',
                ],
                $openMultidisciplinaryRequests
            ),
            'Sem avaliações multidisciplinares abertas.',
            'violet'
        );

        $dischargeSection = $this->buildDischargeSection($discharge);

        return [
            'counts' => [
                'exams' => count($exams['items']),
                'procedures' => count($procedures['items']),
                'antibiotics' => count($antibiotics['items']),
                'multidisciplinary' => count($multidisciplinary['items']),
                'discharge' => $dischargeSection['has_item'],
            ],
            'sections' => [
                $exams,
                $procedures,
                $antibiotics,
                $multidisciplinary,
                $dischargeSection,
            ],
        ];
    }

    // ==================== FETCHING ====================

    private function fetchEventsForSector(int $sectorId): array
    {
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
                NVL(pf_apa.nm_pessoa_fisica, apa.nm_usuario) AS apa_nm_usuario
            FROM tasy.unidade_atendimento ua
            INNER JOIN tasy.atendimento_paciente ap ON ua.nr_atendimento = ap.nr_atendimento
            INNER JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
            LEFT JOIN tasy.motivo_alta ma2 ON ap.cd_motivo_alta_medica = ma2.cd_motivo_alta
            LEFT JOIN (
                SELECT nr_atendimento, dt_previsto_alta, nm_usuario,
                    ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_registro DESC) AS rn
                FROM tasy.atend_previsao_alta
            ) apa ON apa.nr_atendimento = ua.nr_atendimento AND apa.rn = 1
            LEFT JOIN tasy.usuario u_apa ON u_apa.nm_usuario = apa.nm_usuario
            LEFT JOIN tasy.pessoa_fisica pf_apa ON pf_apa.cd_pessoa_fisica = u_apa.cd_pessoa_fisica
            WHERE ua.cd_setor_atendimento = :sector_id
                AND ua.ie_situacao = 'A'
                AND ap.dt_alta IS NULL
        ", ['sector_id' => $sectorId]);

        $results = [];
        $allNrs = [];

        foreach ($rows as $row) {
            $nr = $row->nr_atendimento;
            $events = [];

            if (! empty($row->dt_obito)) {
                $events[] = [
                    'tipo' => 'aviso',
                    'subtipo' => 'obito',
                    'icone' => 'alert.svg',
                    'descricao' => 'Óbito registrado',
                    'urgente' => true,
                    'dt_evento' => $row->dt_obito,
                    'dt_evento_formatted' => Carbon::parse($row->dt_obito)->format('d/m/Y H:i'),
                ];
            }

            $discharge = $this->buildDischarge($row, $events);
            $allNrs[] = $nr;
            $results[$nr] = ['events' => $events, 'discharge' => $discharge];
        }

        foreach (array_chunk($allNrs, self::CHUNK_SIZE) as $chunk) {
            $this->processPrescriptionChunk($results, $chunk);
        }

        foreach (array_chunk($allNrs, self::CHUNK_SIZE) as $chunk) {
            $this->processHemotherapyChunk($results, $chunk);
            $this->processAntibioticChunk($results, $chunk);
            $this->processChemotherapyChunk($results, $chunk);
            $this->processAgendaChunk($results, $chunk);
        }

        // Deduplica: remove prescrição quando agenda tem o mesmo proc_interno (mais informativo)
        foreach ($results as &$data) {
            $agendaProcs = [];
            foreach ($data['events'] as $event) {
                if (($event['_fonte'] ?? null) === 'agenda' && ! empty($event['nr_seq_proc_interno'])) {
                    $agendaProcs[$event['nr_seq_proc_interno']] = true;
                }
            }

            if (! empty($agendaProcs)) {
                $data['events'] = array_values(array_filter(
                    $data['events'],
                    fn ($e) => ($e['_fonte'] ?? null) !== 'prescricao'
                        || empty($e['nr_seq_proc_interno'])
                        || ! isset($agendaProcs[$e['nr_seq_proc_interno']])
                ));
            }
        }
        unset($data);

        $now = now()->timestamp;

        foreach ($results as &$data) {
            usort($data['events'], function ($a, $b) use ($now) {
                $urgA = $a['urgente'] ?? false;
                $urgB = $b['urgente'] ?? false;
                if ($urgA !== $urgB) {
                    return $urgA ? -1 : 1;
                }

                $da = $a['dt_evento'] ?? null;
                $db = $b['dt_evento'] ?? null;
                if ($da === null && $db === null) {
                    return 0;
                }
                if ($da === null) {
                    return 1;
                }
                if ($db === null) {
                    return -1;
                }

                return abs(Carbon::parse($da)->timestamp - $now) - abs(Carbon::parse($db)->timestamp - $now);
            });
        }
        unset($data);

        (new ScolaExamStatusService)->enrichEvents($results);

        foreach ($results as &$data) {
            foreach ($data['events'] as &$event) {
                $event['motivo_pendente'] = PendingEventHelper::motivoPendente($event);
            }
            unset($event);
        }
        unset($data);

        return $this->sanitizeUtf8($results);
    }

    // ==================== HANDLERS (collapsed) ====================

    private function processPrescriptionChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);

        // Query 1 – main rows; NOT EXISTS replaced with LEFT JOIN / IS NULL pre-aggregation
        try {
            $rows = DB::connection('tasy')->select("
                WITH pp_laudo_agg AS (
                    SELECT nr_prescricao, nr_sequencia_prescricao
                    FROM tasy.procedimento_paciente
                    WHERE nr_atendimento IN ({$p})
                      AND nr_laudo IS NOT NULL
                    GROUP BY nr_prescricao, nr_sequencia_prescricao
                ),
                base AS (
                    SELECT
                        pm.nr_atendimento,
                        pm.nr_prescricao,
                        pp.nr_sequencia                              AS nr_sequencia_pp,
                        pp.nr_seq_exame,
                        pp.nr_seq_proc_interno,
                        pp.dt_prev_execucao                         AS dt_evento,
                        pp.dt_coleta,
                        pp.dt_resultado,
                        pp.ie_amostra,
                        pm.dt_prescricao                            AS dt_solicitacao,
                        pm.dt_liberacao                             AS dt_autorizacao,
                        pm.dt_liberacao_medico                      AS dt_liberacao_medico,
                        NVL(pf.nm_pessoa_fisica, pm.nm_usuario)     AS nm_prescritor,
                        pp.ie_status_execucao,
                        NVL(el.nm_exame, NVL(pi.ds_proc_exame, proced.ds_procedimento)) AS descricao,
                        dv.ds_valor_dominio                         AS ds_subtipo,
                        COALESCE(gel.ds_grupo_exame_lab, pic.ds_classificacao, cih.ds_tipo_cirurgia) AS ds_grupo_lab,
                        vd_stat.ds_valor_dominio AS ds_status_execucao_label,
                        NVL(
                            pis.cd_setor_atendimento,
                            NVL(proced.cd_setor_exclusivo, pm.cd_setor_atendimento)
                        ) AS cd_setor_execucao,
                        ROW_NUMBER() OVER (
                            PARTITION BY pm.nr_prescricao,
                                         pp.nr_seq_proc_interno,
                                         pp.dt_prev_execucao
                            ORDER BY pp.nr_sequencia
                        ) AS rn_proc_dup
                    FROM tasy.prescr_medica pm
                    JOIN tasy.prescr_procedimento pp
                        ON pp.nr_prescricao = pm.nr_prescricao
                    LEFT JOIN tasy.result_laboratorio rl
                        ON  rl.nr_prescricao     = pm.nr_prescricao
                        AND rl.nr_seq_prescricao = pp.nr_sequencia
                        AND rl.dt_coleta         IS NOT NULL
                    LEFT JOIN pp_laudo_agg pla
                        ON  pla.nr_prescricao          = pm.nr_prescricao
                        AND pla.nr_sequencia_prescricao = pp.nr_sequencia
                    LEFT JOIN tasy.pessoa_fisica pf
                        ON pf.cd_pessoa_fisica = pm.cd_prescritor
                    LEFT JOIN tasy.proc_interno pi
                        ON pi.nr_sequencia = pp.nr_seq_proc_interno
                    LEFT JOIN tasy.proc_interno_classif pic
                        ON pic.nr_sequencia = pi.nr_seq_classif
                    LEFT JOIN tasy.cih_tipo_cirurgia cih
                        ON cih.cd_tipo_cirurgia = pi.cd_tipo_cirurgia
                    LEFT JOIN tasy.exame_laboratorio el
                        ON el.nr_seq_exame = pp.nr_seq_exame
                    LEFT JOIN tasy.grupo_exame_lab gel
                        ON gel.nr_sequencia = el.nr_seq_grupo
                    LEFT JOIN (
                        SELECT cd_procedimento,
                               MIN(ds_procedimento)      AS ds_procedimento,
                               MIN(cd_tipo_procedimento) AS cd_tipo_procedimento,
                               MIN(cd_setor_exclusivo)   AS cd_setor_exclusivo
                        FROM tasy.procedimento
                        GROUP BY cd_procedimento
                    ) proced ON proced.cd_procedimento = pp.cd_procedimento
                             AND pp.nr_seq_proc_interno IS NULL
                    LEFT JOIN tasy.valor_dominio dv
                        ON dv.cd_dominio = 95
                       AND dv.vl_dominio = TO_CHAR(proced.cd_tipo_procedimento)
                    LEFT JOIN tasy.valor_dominio vd_stat
                        ON vd_stat.cd_dominio = 1226
                       AND vd_stat.vl_dominio = pp.ie_status_execucao
                    LEFT JOIN (
                        SELECT nr_seq_proc_interno, cd_setor_atendimento, cd_estabelecimento
                        FROM (
                            SELECT nr_seq_proc_interno, cd_setor_atendimento, cd_estabelecimento,
                                   ROW_NUMBER() OVER (
                                       PARTITION BY nr_seq_proc_interno, cd_estabelecimento
                                       ORDER BY nr_prioridade
                                   ) AS rn
                            FROM tasy.proc_interno_setor
                        ) WHERE rn = 1
                    ) pis ON pis.nr_seq_proc_interno = pp.nr_seq_proc_interno
                          AND pis.cd_estabelecimento  = pm.cd_estabelecimento
                    WHERE pm.nr_atendimento IN ({$p})
                        AND pp.ie_status_execucao NOT IN ('40', 'R', 'C', 'BE')
                        AND pp.dt_baixa        IS NULL
                        AND pp.dt_cancelamento IS NULL
                        AND pp.ie_suspenso     <> 'S'
                        AND pp.ie_status_atend < 35
                        AND pm.dt_liberacao    IS NOT NULL
                        AND pm.dt_suspensao    IS NULL
                        AND (pp.ie_origem_proced <> 4 OR pp.nr_seq_exame IS NOT NULL)
                        -- AND (pp.nr_seq_proc_interno IS NULL OR pp.nr_seq_proc_interno NOT IN (5970, 1341, 5927))
                        AND rl.nr_prescricao IS NULL
                        AND pla.nr_prescricao IS NULL
                )
                SELECT
                    b.*,
                    NVL(sa.ds_prescricao, sa.ds_setor_atendimento) AS setor_desc_raw,
                    NULL AS ds_status_laudo
                FROM base b
                LEFT JOIN tasy.setor_atendimento sa
                    ON sa.cd_setor_atendimento = b.cd_setor_execucao
                WHERE b.rn_proc_dup = 1
                ORDER BY b.nr_atendimento, b.dt_evento NULLS LAST
            ", array_merge($chunk, $chunk));
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] processPrescriptionChunk failed', ['error' => $e->getMessage()]);

            return;
        }

        // Query 2 – batch fetch procedimento_paciente for enrichment (foi_executado, exame_coletado_nova, proc_realizado_nova)
        $ppByPrescricaoSeq = [];
        $ppMaxByExame = [];
        $ppMaxByProc = [];

        try {
            $ppRows = DB::connection('tasy')->select("
                SELECT ppac.nr_prescricao,
                       ppac.nr_sequencia_prescricao,
                       ppac.nr_seq_exame,
                       ppac.nr_atendimento,
                       pp_n.nr_seq_proc_interno
                FROM tasy.procedimento_paciente ppac
                LEFT JOIN tasy.prescr_procedimento pp_n
                    ON  pp_n.nr_prescricao = ppac.nr_prescricao
                    AND pp_n.nr_sequencia  = ppac.nr_sequencia_prescricao
                    AND pp_n.nr_seq_proc_interno IS NOT NULL
                WHERE ppac.nr_atendimento IN ({$p})
            ", $chunk);

            foreach ($ppRows as $pp) {
                $ppByPrescricaoSeq[$pp->nr_prescricao][$pp->nr_sequencia_prescricao] = true;

                if (! empty($pp->nr_seq_exame)) {
                    $cur = $ppMaxByExame[$pp->nr_atendimento][$pp->nr_seq_exame] ?? 0;
                    if ((int) $pp->nr_prescricao > $cur) {
                        $ppMaxByExame[$pp->nr_atendimento][$pp->nr_seq_exame] = (int) $pp->nr_prescricao;
                    }
                }

                if (! empty($pp->nr_seq_proc_interno)) {
                    $cur = $ppMaxByProc[$pp->nr_atendimento][$pp->nr_seq_proc_interno] ?? 0;
                    if ((int) $pp->nr_prescricao > $cur) {
                        $ppMaxByProc[$pp->nr_atendimento][$pp->nr_seq_proc_interno] = (int) $pp->nr_prescricao;
                    }
                }
            }
        } catch (\Throwable) {
            // enrichment only – silently skip
        }

        // Query 3 – newest pending prescription per (nr_atendimento, nr_seq_exame)
        $newerExameInfo = [];

        try {
            $newerRows = DB::connection('tasy')->select("
                SELECT pm2.nr_atendimento,
                       pp2.nr_seq_exame,
                       MAX(pm2.nr_prescricao) AS max_nr_prescricao,
                       MAX(pm2.nr_prescricao || ' — ' || TO_CHAR(pm2.dt_prescricao, 'DD/MM/YYYY'))
                           KEEP (DENSE_RANK LAST ORDER BY pm2.nr_prescricao) AS info_prescricao
                FROM tasy.prescr_medica pm2
                JOIN tasy.prescr_procedimento pp2
                    ON pp2.nr_prescricao = pm2.nr_prescricao
                WHERE pm2.nr_atendimento IN ({$p})
                  AND pp2.nr_seq_exame IS NOT NULL
                  AND pm2.dt_liberacao IS NOT NULL
                  AND pm2.dt_suspensao IS NULL
                  AND pp2.ie_status_execucao NOT IN ('40', 'R', 'C', 'BE')
                  AND pp2.dt_baixa        IS NULL
                  AND pp2.dt_cancelamento IS NULL
                  AND pp2.ie_suspenso     <> 'S'
                GROUP BY pm2.nr_atendimento, pp2.nr_seq_exame
            ", $chunk);

            foreach ($newerRows as $nr) {
                $newerExameInfo[$nr->nr_atendimento][$nr->nr_seq_exame] = [
                    'nr' => (int) $nr->max_nr_prescricao,
                    'info' => $nr->info_prescricao,
                ];
            }
        } catch (\Throwable) {
            // enrichment only – silently skip
        }

        $now = time();

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $isExam = ! empty($row->nr_seq_exame);
            $tempo = $this->calcTempo($row->dt_evento ?? null, $row->dt_autorizacao ?? null, $now);
            $statusLabel = $row->ds_status_execucao_label ?? null;
            $tipo = PendingEventTypeClassifier::fromPrescriptionRow($isExam, $row->ds_grupo_lab ?? null);

            $foiExecutado = isset($ppByPrescricaoSeq[$row->nr_prescricao][$row->nr_sequencia_pp]);
            $temResultado = $isExam && ! empty($row->dt_resultado) && ! empty($row->dt_coleta);
            $exameColetadoNova = $isExam
                && ! empty($row->nr_seq_exame)
                && ($ppMaxByExame[$row->nr_atendimento][$row->nr_seq_exame] ?? 0) > (int) $row->nr_prescricao;
            $procRealizadoNova = ! $isExam
                && ! empty($row->nr_seq_proc_interno)
                && ($ppMaxByProc[$row->nr_atendimento][$row->nr_seq_proc_interno] ?? 0) > (int) $row->nr_prescricao;

            $prescricaoMaisNova = null;
            if ($isExam && ! empty($row->nr_seq_exame)) {
                $entry = $newerExameInfo[$row->nr_atendimento][$row->nr_seq_exame] ?? null;
                if ($entry && $entry['nr'] > (int) $row->nr_prescricao) {
                    $prescricaoMaisNova = $entry['info'];
                }
            }

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => $tipo,
                'icone' => match ($tipo) {
                    PendingEventTypeClassifier::HEMOTHERAPY => 'hemoterapia.svg',
                    PendingEventTypeClassifier::EXAM => 'outpatient-department.svg',
                    default => 'tac.svg',
                },
                'descricao' => substr($row->descricao ?? '', 0, 80),
                'ds_subtipo' => $row->ds_subtipo ?? null,
                'ds_grupo_lab' => $row->ds_grupo_lab ?? null,
                'nm_prescritor' => $row->nm_prescritor ?? null,
                'nr_prescricao' => $row->nr_prescricao ?? null,
                'nr_sequencia_pp' => $row->nr_sequencia_pp ?? null,
                'dt_evento' => $row->dt_evento,
                'dt_evento_formatted' => $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                'dt_solicitacao' => $row->dt_solicitacao ? date('d/m/Y H:i', strtotime($row->dt_solicitacao)) : null,
                'dt_autorizacao' => $row->dt_autorizacao ? date('d/m/Y H:i', strtotime($row->dt_autorizacao)) : null,
                'dt_liberacao_medico' => $row->dt_liberacao_medico ? date('d/m/Y H:i', strtotime($row->dt_liberacao_medico)) : null,
                'dt_coleta' => $row->dt_coleta ? date('d/m/Y H:i', strtotime($row->dt_coleta)) : null,
                'dt_resultado' => $row->dt_resultado ? date('d/m/Y H:i', strtotime($row->dt_resultado)) : null,
                'ie_amostra' => $isExam ? ($row->ie_amostra ?? null) : null,
                'setor_execucao' => $row->setor_desc_raw ?? ($row->cd_setor_execucao ?? null),
                'tempo_pendente' => $tempo,
                'status_laudo' => $statusLabel,
                'ie_status_execucao' => $row->ie_status_execucao ?? null,
                'ds_status_laudo' => $row->ds_status_laudo ?? null,
                'foi_executado_sem_baixa' => $foiExecutado || $temResultado,
                'exame_coletado_em_prescricao_mais_nova' => $exameColetadoNova,
                'proc_realizado_em_nova_prescricao' => $procRealizadoNova,
                'prescricao_mais_nova_pendente_info' => $prescricaoMaisNova,
                'urgente' => false,
                'nr_seq_proc_interno' => $row->nr_seq_proc_interno ?? null,
                '_fonte' => 'prescricao',
            ];
        }
    }

    private function processHemotherapyChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);

        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    ch.nr_atendimento,
                    ch.dt_programada AS dt_evento,
                    ch.ie_tipo_hemoterap,
                    ch.ds_procedimento_prescrito,
                    ch.ds_observacao,
                    ch.ds_observacao_proc,
                    ch.ds_horarios,
                    ch.qt_vol_hemocomp,
                    ch.ie_via_aplicacao,
                    va.ds_via_aplicacao AS via_aplicacao,
                    ch.ie_urgencia,
                    sa.ds_setor_atendimento AS setor_execucao
                FROM tasy.cpoe_hemoterapia ch
                LEFT JOIN tasy.via_aplicacao va
                    ON va.ie_via_aplicacao = ch.ie_via_aplicacao
                   AND va.ie_situacao = 'A'
                LEFT JOIN tasy.setor_atendimento sa
                    ON sa.cd_setor_atendimento = ch.cd_setor_atendimento
                WHERE ch.nr_atendimento IN ({$p})
                  AND ch.dt_programada BETWEEN SYSDATE AND SYSDATE + 2
                  AND ch.dt_suspensao IS NULL
            ", $chunk);
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] processHemotherapyChunk failed', ['error' => $e->getMessage()]);

            return;
        }

        $tipoMap = [
            '0' => 'Hemocomponente', '1' => 'Concentrado de Hemácias',
            '2' => 'Concentrado de Plaquetas', '3' => 'Plasma Fresco Congelado',
            '4' => 'Crioprecipitado', '5' => 'Concentrado de Granulócitos',
        ];

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $tipo = $tipoMap[(string) ($row->ie_tipo_hemoterap ?? '')] ?? 'Hemocomponente';

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => 'hemoterapia',
                'icone' => 'hemoterapia.svg',
                'descricao' => PendingEventHelper::hemotherapyDescription([
                    'tipo_label' => $tipo,
                    'ie_tipo_hemoterap' => $row->ie_tipo_hemoterap ?? null,
                    'ds_procedimento_prescrito' => $row->ds_procedimento_prescrito ?? null,
                    'ds_observacao' => $row->ds_observacao ?? null,
                    'ds_observacao_proc' => $row->ds_observacao_proc ?? null,
                    'ds_horarios' => $row->ds_horarios ?? null,
                    'qt_vol_hemocomp' => $row->qt_vol_hemocomp ?? null,
                    'via_aplicacao' => $row->via_aplicacao ?? null,
                    'ie_via_aplicacao' => $row->ie_via_aplicacao ?? null,
                ]),
                'ie_tipo_hemoterap' => $row->ie_tipo_hemoterap ?? null,
                'tipo_label' => $tipo,
                'dt_evento' => $row->dt_evento,
                'dt_evento_formatted' => date('d/m/Y H:i', strtotime($row->dt_evento)),
                'setor_execucao' => $row->setor_execucao ?? null,
                'urgente' => ($row->ie_urgencia ?? 'N') === 'S',
                '_fonte' => 'agenda',
            ];
        }
    }

    private function processAntibioticChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);

        try {
            $rows = DB::connection('tasy')->select("
                WITH base AS (
                    SELECT
                        cm.nr_atendimento,
                        cm.nr_sequencia                                                          AS med_id,
                        pm.nr_prescricao,
                        pm.nr_sequencia                                                          AS nr_sequencia_pp,
                        INITCAP(TRIM(REGEXP_REPLACE(m.ds_material, '\\s*&&\\s*\$', '')))        AS descricao,
                        pmh.dt_horario,
                        cm.qt_dose,
                        cm.cd_unidade_medida                                                     AS cd_unidade_medida_dose,
                        cm.ie_via_aplicacao,
                        cm.nr_dia_util,
                        pma.ie_alteracao,
                        CASE pma.ie_alteracao
                            WHEN 3  THEN 600  WHEN 58 THEN 500  WHEN 8  THEN 400
                            WHEN 38 THEN 300  WHEN 4  THEN 200  WHEN 10 THEN  30
                            WHEN 15 THEN  20  ELSE           1
                        END AS priority,
                        NVL(pf.nm_pessoa_fisica, cm.nm_usuario) AS nm_prescritor
                    FROM tasy.cpoe_material cm
                    JOIN tasy.material m          ON m.cd_material       = cm.cd_material
                    JOIN tasy.material m_stock    ON m_stock.cd_material = m.cd_material_estoque
                    JOIN tasy.medic_ficha_tecnica mf ON mf.nr_sequencia  = m_stock.nr_seq_ficha_tecnica
                    JOIN tasy.prescr_material pm  ON pm.nr_seq_mat_cpoe  = cm.nr_sequencia
                    JOIN tasy.prescr_mat_hor pmh
                        ON pmh.nr_prescricao  = pm.nr_prescricao
                       AND pmh.nr_seq_material = pm.nr_sequencia
                    LEFT JOIN tasy.prescr_mat_alteracao pma
                        ON pma.nr_seq_horario  = pmh.nr_sequencia
                       AND pma.nr_prescricao   = pmh.nr_prescricao
                       AND pma.nr_seq_prescricao = pm.nr_sequencia
                       AND NVL(pma.ie_alteracao, 0) NOT IN (5, 12)
                    LEFT JOIN tasy.pessoa_fisica pf ON pf.cd_pessoa_fisica = cm.cd_medico
                    WHERE cm.nr_atendimento IN ({$p})
                      AND mf.ie_antimicrobiano = 'S'
                      AND cm.dt_liberacao      IS NOT NULL
                      AND cm.dt_suspensao      IS NULL
                      AND TRUNC(pmh.dt_horario) = TRUNC(SYSDATE)
                ),
                grouped AS (
                    SELECT
                        nr_atendimento, med_id, nr_prescricao, nr_sequencia_pp, descricao, dt_horario,
                        qt_dose, cd_unidade_medida_dose, ie_via_aplicacao, nr_dia_util,
                        MAX(priority) AS priority,
                        MAX(ie_alteracao) KEEP (DENSE_RANK LAST ORDER BY priority NULLS FIRST) AS ie_alteracao_code,
                        MAX(nm_prescritor) AS nm_prescritor
                    FROM base
                    GROUP BY nr_atendimento, med_id, nr_prescricao, nr_sequencia_pp, descricao, dt_horario,
                             qt_dose, cd_unidade_medida_dose, ie_via_aplicacao, nr_dia_util
                    HAVING MAX(priority) < 400
                )
                SELECT g.*, vd.ds_valor_dominio AS ds_alteracao_label
                FROM grouped g
                LEFT JOIN tasy.valor_dominio vd
                    ON vd.cd_dominio = 1620
                   AND vd.vl_dominio = TO_CHAR(g.ie_alteracao_code)
                ORDER BY g.nr_atendimento, g.dt_horario
            ", $chunk);
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] processAntibioticChunk failed', ['error' => $e->getMessage()]);

            return;
        }

        $now = time();

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $dose = '';
            if (! empty($row->qt_dose)) {
                $dose = (string) (int) $row->qt_dose;
                if (! empty($row->cd_unidade_medida_dose)) {
                    $dose .= $row->cd_unidade_medida_dose;
                }
            }

            $parts = array_filter([
                $row->nr_dia_util ? 'Dia '.$row->nr_dia_util : null,
                $dose ?: null,
                ! empty($row->ie_via_aplicacao) ? $row->ie_via_aplicacao : null,
            ]);

            $dtTs = $row->dt_horario ? strtotime($row->dt_horario) : null;
            $tempo = '';
            if ($dtTs) {
                $diff = $dtTs - $now;
                $absDiff = abs($diff);
                if ($diff > 0) {
                    $tempo = $absDiff < 3600
                        ? 'em '.(int) round($absDiff / 60).'min'
                        : 'em '.(int) round($absDiff / 3600).'h';
                } else {
                    $tempo = $absDiff < 3600
                        ? (int) round($absDiff / 60).'min em atraso'
                        : (int) round($absDiff / 3600).'h em atraso';
                }
            }

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => 'antibiotico',
                'icone' => 'antimicrobiano.svg',
                'descricao' => substr($row->descricao ?? 'Antimicrobiano', 0, 60),
                'ds_subtipo' => 'Antimicrobiano',
                'ds_complemento' => implode(' · ', $parts),
                'nm_prescritor' => $row->nm_prescritor ?? null,
                'nr_prescricao' => $row->nr_prescricao ?? null,
                'nr_sequencia_pp' => $row->nr_sequencia_pp ?? null,
                'dt_evento' => $row->dt_horario,
                'dt_evento_formatted' => $dtTs ? date('d/m/Y H:i', $dtTs) : null,
                'tempo_pendente' => $tempo,
                'status_laudo' => ! empty($row->ds_alteracao_label)
                    ? trim((string) $row->ds_alteracao_label)
                    : 'Pendente',
                'urgente' => false,
            ];
        }
    }

    private function processChemotherapyChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);

        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.nr_atendimento,
                    aq.dt_agenda            AS dt_evento,
                    aq.ds_local,
                    aq.nm_medico_resp,
                    aq.ds_protocolo_medic,
                    aq.nr_ciclo,
                    aq.ie_status_agenda,
                    vd.ds_valor_dominio     AS ds_status_agenda_label
                FROM tasy.atendimento_paciente ap
                JOIN tasy.agenda_quimioterapia_pep_v aq
                    ON aq.cd_pessoa_fisica = ap.cd_pessoa_fisica
                LEFT JOIN tasy.valor_dominio vd
                    ON vd.cd_dominio = 83
                   AND vd.vl_dominio = aq.ie_status_agenda
                WHERE ap.nr_atendimento IN ({$p})
                    AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + 30
                ORDER BY ap.nr_atendimento, aq.dt_agenda
            ", $chunk);
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] processChemotherapyChunk failed', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $parts = array_filter([
                ! empty($row->ds_protocolo_medic) ? $row->ds_protocolo_medic : null,
                ! empty($row->nr_ciclo) ? 'Ciclo '.$row->nr_ciclo : null,
                ! empty($row->ds_status_agenda_label) ? $row->ds_status_agenda_label : null,
                ! empty($row->ds_local) ? $row->ds_local : null,
                ! empty($row->nm_medico_resp) ? $row->nm_medico_resp : null,
            ]);

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => 'quimioterapia',
                'icone' => 'quimioterapia.svg',
                'descricao' => ! empty($row->ds_protocolo_medic)
                    ? substr($row->ds_protocolo_medic, 0, 60)
                    : 'Quimioterapia',
                'ds_subtipo' => 'Quimioterapia',
                'ds_complemento' => implode(' · ', $parts),
                'nm_prescritor' => $row->nm_medico_resp ?? null,
                'dt_evento' => $row->dt_evento,
                'dt_evento_formatted' => $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                'ie_status_agenda' => $row->ie_status_agenda ?? null,
                'urgente' => false,
                '_fonte' => 'agenda',
            ];
        }
    }

    private function processAgendaChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);

        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    atp.nr_atendimento,
                    CASE
                        WHEN ap.ie_carater_cirurgia IS NOT NULL AND ap.ie_carater_cirurgia <> 'X'
                        THEN 'cirurgia' ELSE 'exame'
                    END                                      AS tipo,
                    CASE
                        WHEN ap.hr_inicio IS NOT NULL
                        THEN TRUNC(ap.dt_agenda) + (ap.hr_inicio - TRUNC(ap.hr_inicio))
                        ELSE ap.dt_agenda
                    END                                      AS dt_evento,
                    ap.hr_inicio,
                    ap.ds_cirurgia,
                    ap.ds_observacao,
                    ap.ie_carater_cirurgia,
                    ap.ie_status_agenda,
                    vd_ag_stat.ds_valor_dominio AS ds_status_agenda_label,
                    vd_ag_car.ds_valor_dominio  AS ds_carater_label,
                    ap.nr_seq_proc_interno,
                    ap.nr_seq_sala,
                    NVL(
                        pis.cd_setor_atendimento,
                        NVL(proced.cd_setor_exclusivo, ap.cd_setor_atendimento)
                    ) AS cd_setor_execucao,
                    NVL(sa_exec.ds_prescricao, sa_exec.ds_setor_atendimento) AS ds_setor_execucao,
                    NVL(pi.cd_tipo_cirurgia,
                        (SELECT MAX(p.cd_tipo_procedimento) FROM tasy.procedimento p
                         WHERE p.cd_procedimento = ap.cd_procedimento
                           AND p.ie_origem_proced = ap.ie_origem_proced)
                    ) AS cd_tipo_cirurgia,
                    COALESCE(pi.ds_proc_exame, proced.ds_procedimento, ap.ds_cirurgia) AS descricao_proc,
                    pi.nr_seq_exame_lab,
                    COALESCE(pf_med.nm_pessoa_fisica, pf_user.nm_pessoa_fisica, ap.nm_usuario) AS nm_prescritor
                FROM tasy.agenda_paciente ap
                JOIN tasy.atendimento_paciente atp ON atp.cd_pessoa_fisica = ap.cd_pessoa_fisica
                LEFT JOIN tasy.pessoa_fisica pf_med ON pf_med.cd_pessoa_fisica = ap.cd_medico
                LEFT JOIN tasy.usuario u_ap ON u_ap.nm_usuario = ap.nm_usuario
                LEFT JOIN tasy.pessoa_fisica pf_user ON pf_user.cd_pessoa_fisica = u_ap.cd_pessoa_fisica
                LEFT JOIN tasy.valor_dominio vd_ag_stat ON vd_ag_stat.cd_dominio = 83 AND vd_ag_stat.vl_dominio = ap.ie_status_agenda
                LEFT JOIN tasy.valor_dominio vd_ag_car  ON vd_ag_car.cd_dominio  = 1016 AND vd_ag_car.vl_dominio  = ap.ie_carater_cirurgia
                LEFT JOIN tasy.proc_interno pi ON pi.nr_sequencia = ap.nr_seq_proc_interno
                LEFT JOIN (
                    SELECT nr_seq_proc_interno, cd_setor_atendimento
                    FROM (
                        SELECT nr_seq_proc_interno, cd_setor_atendimento,
                               ROW_NUMBER() OVER (
                                   PARTITION BY nr_seq_proc_interno
                                   ORDER BY nr_prioridade
                               ) AS rn
                        FROM tasy.proc_interno_setor
                    ) WHERE rn = 1
                ) pis ON pis.nr_seq_proc_interno = ap.nr_seq_proc_interno
                LEFT JOIN (SELECT cd_procedimento, MIN(ds_procedimento) AS ds_procedimento, MIN(cd_setor_exclusivo) AS cd_setor_exclusivo FROM tasy.procedimento GROUP BY cd_procedimento) proced
                    ON proced.cd_procedimento = ap.cd_procedimento AND ap.nr_seq_proc_interno IS NULL
                LEFT JOIN tasy.setor_atendimento sa_exec ON sa_exec.cd_setor_atendimento = NVL(
                    pis.cd_setor_atendimento,
                    NVL(proced.cd_setor_exclusivo, ap.cd_setor_atendimento)
                )
                WHERE atp.nr_atendimento IN ({$p})
                    AND ap.dt_agenda >= TRUNC(SYSDATE)
                    AND ap.dt_agenda <= SYSDATE + 30
                    AND ap.ie_status_agenda NOT IN ('C', 'S', 'CR', 'E', 'AD')
                    AND ap.dt_executada IS NULL
                    AND (
                        (ap.ie_carater_cirurgia IS NOT NULL AND ap.ie_carater_cirurgia <> 'X')
                        OR (ap.ie_carater_cirurgia IS NULL AND (ap.nr_seq_proc_interno IS NOT NULL OR ap.cd_procedimento IS NOT NULL))
                    )
                ORDER BY atp.nr_atendimento, ap.dt_agenda, ap.hr_inicio NULLS LAST
            ", $chunk);
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] processAgendaChunk failed', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $tipo = $row->tipo ?? 'exame';
            $isSurgery = $tipo === 'cirurgia';
            $isUrgent = $isSurgery && in_array($row->ie_carater_cirurgia ?? '', ['U', 'M'], true);

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => $tipo,
                'icone' => $isSurgery ? 'general-surgery.svg' : 'outpatient-department.svg',
                'descricao' => substr($row->descricao_proc ?? $row->ds_cirurgia ?? 'Procedimento', 0, 80),
                'ds_subtipo' => $isSurgery
                    ? ('Cirurgia'.($row->ds_carater_label ? ' – '.$row->ds_carater_label : ''))
                    : 'Exame/Procedimento',
                'dt_evento' => $row->dt_evento,
                'dt_evento_formatted' => $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                'ie_status_agenda' => $row->ie_status_agenda ?? null,
                'setor_execucao' => $row->ds_setor_execucao ?? null,
                'nr_seq_proc_interno' => $row->nr_seq_proc_interno ?? null,
                'nm_prescritor' => $row->nm_prescritor ?? null,
                'urgente' => $isUrgent,
                '_fonte' => 'agenda',
            ];
        }
    }

    // ==================== HELPERS ====================

    private function placeholders(array $chunk): string
    {
        return implode(',', array_fill(0, count($chunk), '?'));
    }

    private function calcTempo(?string $dtEvento, ?string $dtAutorizacao, int $now): string
    {
        if ($dtEvento) {
            $diff = strtotime($dtEvento) - $now;
            if ($diff > 0) {
                return $diff < 3600
                    ? 'em '.(int) round($diff / 60).'min'
                    : ($diff < 86400
                        ? 'em '.(int) round($diff / 3600).'h'
                        : 'em '.(int) floor($diff / 86400).'d');
            }

            $diff = abs($diff);

            return $diff < 3600
                ? (int) round($diff / 60).'min em aberto'
                : ($diff < 86400
                    ? (int) round($diff / 3600).'h em aberto'
                    : (int) floor($diff / 86400).'d em aberto');
        }

        if ($dtAutorizacao) {
            $diff = $now - strtotime($dtAutorizacao);

            return $diff < 3600
                ? (int) round($diff / 60).'min em aberto'
                : ($diff < 86400
                    ? (int) round($diff / 3600).'h em aberto'
                    : (int) floor($diff / 86400).'d em aberto');
        }

        return '';
    }

    private function buildDischarge(object $row, array &$events): ?array
    {
        if (! empty($row->dt_alta)) {
            $events[] = [
                'tipo' => 'alta', 'icone' => 'alta.svg',
                'descricao' => 'Alta Efetivada'.(! empty($row->ds_motivo_alta) ? ' - '.$row->ds_motivo_alta : ''),
                'ds_subtipo' => 'Alta',
                'dt_evento' => $row->dt_alta,
                'dt_evento_formatted' => Carbon::parse($row->dt_alta)->format('d/m/Y H:i'),
                'urgente' => true,
            ];

            return TasyFormatter::buildDischargeInfo(
                (string) $row->dt_alta, null, null,
                ! empty($row->ds_motivo_alta) ? (string) $row->ds_motivo_alta : null
            );
        }

        if (! empty($row->dt_alta_medico)) {
            $descAltaMedica = 'Alta Médica';
            if (! empty($row->apa_dt_previsto_alta)) {
                $descAltaMedica .= ' | Prev. Alta: '.Carbon::parse($row->apa_dt_previsto_alta)->format('d/m/Y H:i');
            }

            $events[] = [
                'tipo' => 'alta_medica', 'icone' => 'alta.svg',
                'descricao' => $descAltaMedica,
                'ds_subtipo' => 'Alta Médica',
                'dt_evento' => $row->dt_alta_medico,
                'dt_evento_formatted' => Carbon::parse($row->dt_alta_medico)->format('d/m/Y H:i'),
                'urgente' => true,
            ];

            return TasyFormatter::buildDischargeInfo(
                null, (string) $row->dt_alta_medico,
                ! empty($row->apa_dt_previsto_alta) ? (string) $row->apa_dt_previsto_alta : null,
                ! empty($row->ds_motivo_alta) ? (string) $row->ds_motivo_alta : null
            );
        }

        if (! empty($row->apa_dt_previsto_alta)) {
            $dtFormatted = Carbon::parse($row->apa_dt_previsto_alta)->format('d/m/Y H:i');

            $events[] = [
                'tipo' => 'previsao_alta', 'icone' => 'alta.svg',
                'descricao' => 'Previsão de Alta: '.$dtFormatted,
                'ds_subtipo' => 'Previsão de Alta',
                'dt_evento' => $row->apa_dt_previsto_alta,
                'dt_evento_formatted' => $dtFormatted,
                'nm_prescritor' => isset($row->apa_nm_usuario) ? trim((string) $row->apa_nm_usuario) : null,
                'urgente' => false,
            ];

            return TasyFormatter::buildDischargeInfo(
                null, null, (string) $row->apa_dt_previsto_alta
            );
        }

        return null;
    }

    private function sanitizeUtf8(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_scrub($value, 'UTF-8');
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->sanitizeUtf8($v), $value);
        }

        return $value;
    }

    private function filterEventsByTypes(array $events, array $types): array
    {
        return array_values(array_filter($events, fn (array $event): bool => in_array((string) ($event['tipo'] ?? ''), $types, true)));
    }

    private function buildChecklistSection(string $title, array $items, string $emptyMessage, string $tone): array
    {
        $mappedItems = array_map(function (array $item) use ($tone): array {
            $detailParts = array_filter([
                $item['ds_subtipo'] ?? null,
                $item['dt_evento_formatted'] ?? null,
                $item['tempo_pendente'] ?? null,
                $item['ds_complemento'] ?? null,
            ]);

            return [
                'headline' => trim((string) ($item['descricao'] ?? 'Sem descrição')),
                'detail' => ! empty($detailParts) ? implode(' · ', $detailParts) : null,
                'meta' => trim((string) ($item['status_laudo'] ?? $item['ie_status_execucao'] ?? $item['ie_status_agenda'] ?? '')) ?: null,
                'urgent' => (bool) ($item['urgente'] ?? false),
                'tone' => (string) (($item['urgent'] ?? false) ? 'danger' : $tone),
            ];
        }, $items);

        return [
            'title' => $title,
            'tone' => $tone,
            'items' => $mappedItems,
            'empty_message' => $emptyMessage,
            'has_items' => ! empty($mappedItems),
        ];
    }

    private function buildDischargeSection(?array $discharge): array
    {
        if (! is_array($discharge) || empty($discharge)) {
            return [
                'title' => 'Alta / previsão de alta', 'tone' => 'green',
                'items' => [], 'empty_message' => 'Sem previsão de alta registrada.',
                'has_item' => false,
            ];
        }

        $title = match ($discharge['tipo'] ?? null) {
            'alta' => 'Alta efetivada', 'alta_medica' => 'Alta médica', default => 'Previsão de alta',
        };

        $mainDate = $discharge['dt_alta_formatted']
            ?? $discharge['dt_alta_medico_formatted']
            ?? $discharge['dt_previsto_alta_formatted']
            ?? null;

        $metaParts = array_filter([
            $mainDate,
            ! empty($discharge['ds_motivo_alta']) ? 'Motivo: '.$discharge['ds_motivo_alta'] : null,
        ]);

        return [
            'title' => 'Alta / previsão de alta', 'tone' => 'green',
            'items' => [[
                'headline' => $title, 'detail' => $mainDate,
                'meta' => ! empty($metaParts) ? implode(' · ', $metaParts) : null,
                'urgent' => true, 'tone' => 'green',
            ]],
            'empty_message' => 'Sem previsão de alta registrada.',
            'has_item' => true,
        ];
    }
}
