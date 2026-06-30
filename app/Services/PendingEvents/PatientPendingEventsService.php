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
 * Consolida em um único cache todas as pendências assistenciais relevantes para a passagem
 * de plantão: exames, procedimentos, hemoterapia, antimicrobianos, quimioterapia, cirurgias
 * agendadas e status de alta.
 *
 * Estrutura de retorno: [nr_atendimento => ['pending_events' => [...], 'discharge' => array|null]]
 *
 * Estratégia de queries:
 * - Uma query principal coleta todos os atendimentos ativos do setor com dados de alta.
 * - Cinco queries em chunk (prescrições, hemoterapia, antimicrobianos, quimioterapia, agenda)
 *   enriquecem os resultados em lote, evitando N queries por paciente.
 * - CHUNK_SIZE = 200: limite empírico seguro para a cláusula IN do Oracle sem atingir
 *   o limite de 1000 elementos ou causar degradação do plano de execução.
 */
class PatientPendingEventsService
{
    use UsesRepositories;

    private const CACHE_TTL = 600; // 10 minutos

    /** Máximo de nr_atendimento por cláusula IN nas queries Oracle. */
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
        $patientEvents = $sectorEvents[$attendanceNumber] ?? ['pending_events' => [], 'discharge' => null];

        $events = is_array($patientEvents['pending_events'] ?? null) ? $patientEvents['pending_events'] : [];
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
        try {
            $_t = hrtime(true);
            $rows = DB::connection('tasy')->select("
                SELECT
                    ua.nr_atendimento,
                    ap.cd_pessoa_fisica,
                    pf.dt_obito,
                    ap.dt_alta,
                    ap.dt_alta_medico,
                    ma2.ds_motivo_alta,
                    apa.dt_previsto_alta AS apa_dt_previsto_alta
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
                WHERE ua.cd_setor_atendimento = :sector_id
                    AND ua.ie_situacao = 'A'
                    AND ap.dt_alta IS NULL
            ", ['sector_id' => $sectorId]);
        } catch (\Throwable $e) {
            Log::error('PatientPendingEventsService: failed base query', [
                'sector_id' => $sectorId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
        Log::info('[PendingEvents] sector='.$sectorId.' query=base ms='.round((hrtime(true) - $_t) / 1e6));

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
            $results[$nr] = ['pending_events' => $events, 'discharge' => $discharge];
        }

        foreach (array_chunk($allNrs, self::CHUNK_SIZE) as $ci => $chunk) {
            $_t = hrtime(true);
            $this->processPrescriptionChunk($results, $chunk);
            Log::info('[PendingEvents] sector='.$sectorId.' query=prescription chunk='.$ci.' ms='.round((hrtime(true) - $_t) / 1e6));
        }

        foreach (array_chunk($allNrs, self::CHUNK_SIZE) as $ci => $chunk) {
            $_t = hrtime(true);
            $this->processHemotherapyChunk($results, $chunk);
            Log::info('[PendingEvents] sector='.$sectorId.' query=hemotherapy chunk='.$ci.' ms='.round((hrtime(true) - $_t) / 1e6));

            $_t = hrtime(true);
            $this->processAntibioticChunk($results, $chunk);
            Log::info('[PendingEvents] sector='.$sectorId.' query=antibiotic chunk='.$ci.' ms='.round((hrtime(true) - $_t) / 1e6));

            $_t = hrtime(true);
            $this->processChemotherapyChunk($results, $chunk);
            Log::info('[PendingEvents] sector='.$sectorId.' query=chemotherapy chunk='.$ci.' ms='.round((hrtime(true) - $_t) / 1e6));

            $_t = hrtime(true);
            $this->processAgendaChunk($results, $chunk);
            Log::info('[PendingEvents] sector='.$sectorId.' query=agenda chunk='.$ci.' ms='.round((hrtime(true) - $_t) / 1e6));
        }

        // Deduplicação prescrição × agenda: o mesmo procedimento pode aparecer tanto em
        // prescr_procedimento (fonte 'prescricao') quanto em agenda_paciente (fonte 'agenda').
        // A entrada da agenda tem dados mais completos (horário real, sala, status atualizado),
        // portanto quando ambas existem para o mesmo nr_seq_proc_interno, a prescrição é removida.
        foreach ($results as &$data) {
            $agendaProcs = [];
            foreach ($data['pending_events'] as $event) {
                if (($event['_fonte'] ?? null) === 'agenda' && ! empty($event['nr_seq_proc_interno'])) {
                    $agendaProcs[$event['nr_seq_proc_interno']] = true;
                }
            }

            if (! empty($agendaProcs)) {
                $data['pending_events'] = array_values(array_filter(
                    $data['pending_events'],
                    fn ($e) => ($e['_fonte'] ?? null) !== 'prescricao'
                        || empty($e['nr_seq_proc_interno'])
                        || ! isset($agendaProcs[$e['nr_seq_proc_interno']])
                ));
            }
        }
        unset($data);

        $now = now()->timestamp;

        // Ordenação por proximidade temporal ao momento atual (não por data cronológica).
        // Eventos urgentes vão primeiro. Entre os demais, prioriza-se quem está mais próximo
        // de "agora" — tanto no futuro quanto no passado — usando distância absoluta em segundos.
        // Isso faz com que um exame com 10 min de atraso apareça antes de um agendado para 3h.
        foreach ($results as &$data) {
            usort($data['pending_events'], function ($a, $b) use ($now) {
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

        $_t = hrtime(true);
        (new ScolaExamStatusService)->enrichEvents($results);
        Log::info('[PendingEvents] sector='.$sectorId.' query=scola_enrich ms='.round((hrtime(true) - $_t) / 1e6));

        // Remove exames sem pendência clínica real — Scola em estado terminal sem baixa administrativa:
        //   - scola_rejected: amostra rejeitada (status N + colheita) — sem laudo possível
        //   - scola_nova_coleta: nova coleta necessária (status N sem colheita) — nova prescrição separada
        //   - scola_data_exportado: resultado exportado para Tasy — resultado disponível
        // is_oculto (HGT/Fisioterapia) NÃO é filtrado aqui — passa para os consumidores;
        // o DataTable de /pendencias oculta por padrão via JS e mostra com "Mostrar ocultos".
        // Nota: "laudo integrado no tasy" (dt_resultado + dt_coleta set) filtrado no SQL da query.
        foreach ($results as &$data) {
            $data['pending_events'] = array_values(array_filter(
                $data['pending_events'],
                fn ($event) => empty($event['scola_rejected'])
                    && empty($event['scola_nova_coleta'])
                    && empty($event['scola_data_exportado'])
            ));
        }
        unset($data);

        foreach ($results as &$data) {
            foreach ($data['pending_events'] as &$event) {
                $event['motivo_pendente'] = PendingEventHelper::motivoPendente($event);
            }
            unset($event);
        }
        unset($data);

        // Remove campos usados exclusivamente durante o processamento server-side.
        // Nenhum consumidor downstream (SbarPatientModal, blade, checklist) lê estes campos.
        foreach ($results as &$data) {
            foreach ($data['pending_events'] as &$event) {
                unset(
                    $event['_fonte'],
                    $event['nr_seq_proc_interno'],
                    $event['nr_sequencia_pp'],
                    $event['dt_resultado'],
                    $event['ds_status_agenda_label'],
                    $event['carater_cirurgia'],
                    $event['tipo_label'],
                    $event['ie_tipo_hemoterap'],
                    $event['ds_procedimento_prescrito'],
                    $event['ds_observacao'],
                    $event['ds_observacao_proc'],
                    $event['ds_horarios'],
                    $event['qt_vol_hemocomp'],
                    $event['via_aplicacao'],
                    $event['ie_via_aplicacao']
                );
            }
            unset($event);
        }
        unset($data);

        return $this->sanitizeUtf8($results);
    }

    // ==================== HANDLERS (collapsed) ====================

    /**
     * Processa prescrições pendentes para um lote de atendimentos. Usa três queries separadas:
     *
     * Query 1 (base): busca procedimentos prescritos ainda pendentes, com todos os joins
     *   necessários para classificação, setor de execução e deduplicação por (prescricao, proc, data).
     *   O NOT EXISTS original foi substituído por LEFT JOIN + IS NULL para melhor plano Oracle.
     *
     * Query 2 (procedimento_paciente): verifica se o procedimento já foi fisicamente executado
     *   (registro em procedimento_paciente) mas sem baixa na prescrição — o Tasy às vezes fica
     *   dessincronizado entre execução e baixa administrativa.
     *
     * newerExameInfo: derivado em PHP dos resultados da Query 1 — agrupa por (nr_atendimento,
     *   nr_seq_exame) e identifica o maior nr_prescricao, eliminando a Query 3 Oracle anterior.
     *
     * Lookups pós-query (PHP): proc_interno_setor, procedimento, valor_dominio, setor_atendimento
     *   são buscados com IN targetado nos IDs retornados pela Query 1, substituindo os dois
     *   subqueries globais (GROUP BY + ROW_NUMBER sobre tabelas inteiras) que causavam lentidão.
     */
    private function processPrescriptionChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);

        $_tDv = hrtime(true);
        $dvMap = $this->loadValorDominioMap();
        Log::info('[PendingEvents] query=valor_dominio ms='.round((hrtime(true) - $_tDv) / 1e6));

        // pisMap e sectorNameMap: tabelas pequenas (~4k e ~1k rows), cache 1h OK.
        $pisMap = $this->loadProcInternSectorMap();
        $sectorNameMap = $this->loadSectorNameMap();

        try {
            $_tQ1 = hrtime(true);
            // Query enxuta: apenas pm + pp + pessoa_fisica.
            // Todos os demais JOINs substituídos por lookup PHP nos maps acima.
            // anti-join result_laboratorio removido — feito em query separada + filtro PHP.
            $rows = DB::connection('tasy')->select("
                SELECT /*+ LEADING(pm pp) USE_NL(pp) */
                    pm.nr_atendimento,
                    pm.nr_prescricao,
                    pp.nr_sequencia                              AS nr_sequencia_pp,
                    pp.nr_seq_exame,
                    pp.nr_seq_proc_interno,
                    pp.cd_procedimento,
                    pp.dt_prev_execucao                         AS dt_evento,
                    pp.dt_coleta,
                    pp.dt_resultado,
                    pp.ie_amostra,
                    pm.dt_prescricao                            AS dt_solicitacao,
                    pm.dt_liberacao                             AS dt_autorizacao,
                    pm.dt_liberacao_medico                      AS dt_liberacao_medico,
                    pm.cd_setor_atendimento                     AS cd_setor_prescricao,
                    pm.cd_estabelecimento,
                    pm.cd_prescritor,
                    pm.nm_usuario                               AS nm_usuario_pm,
                    NVL(pf.nm_pessoa_fisica, pm.nm_usuario)     AS nm_prescritor,
                    pp.ie_status_execucao,
                    pp.ie_urgencia
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp
                    ON pp.nr_prescricao = pm.nr_prescricao
                LEFT JOIN tasy.pessoa_fisica pf
                    ON pf.cd_pessoa_fisica = pm.cd_prescritor
                WHERE pm.nr_atendimento IN ({$p})
                    AND pp.ie_status_execucao NOT IN ('40', 'R', 'C', 'BE')
                    AND pp.dt_baixa        IS NULL
                    AND pp.dt_cancelamento IS NULL
                    AND pp.ie_suspenso     <> 'S'
                    AND pp.ie_status_atend < 35
                    AND pm.dt_liberacao    IS NOT NULL
                    AND pm.dt_suspensao    IS NULL
                    AND (pp.ie_origem_proced <> 4 OR pp.nr_seq_exame IS NOT NULL)
                    AND (pp.dt_resultado IS NULL OR pp.dt_coleta IS NULL)
                    AND (pp.dt_prev_execucao IS NULL OR pp.dt_prev_execucao <= SYSDATE + 1)
                ORDER BY pp.nr_sequencia
            ", $chunk);
            Log::info('[PendingEvents] query=prescription_q1 rows='.count($rows).' ms='.round((hrtime(true) - $_tQ1) / 1e6));
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] processPrescriptionChunk failed', ['error' => $e->getMessage()]);

            return;
        }

        // ── Lookups targeted (batch por IDs presentes nos resultados de q1) ────────────
        // Carrega apenas os IDs que aparecem nos resultados — evita deserializar 80k+50k
        // linhas de tabelas globais por processo PHP.

        // 1. Coleta IDs únicos
        $nrSeqProcInternoIds = [];
        $cdProcIds = [];
        $nrSeqExameIds = [];
        foreach ($rows as $row) {
            if (! empty($row->nr_seq_proc_interno)) {
                $nrSeqProcInternoIds[(string) $row->nr_seq_proc_interno] = true;
            } elseif (! empty($row->cd_procedimento)) {
                $cdProcIds[(string) $row->cd_procedimento] = true;
            }
            if (! empty($row->nr_seq_exame)) {
                $nrSeqExameIds[(string) $row->nr_seq_exame] = true;
            }
        }

        // 2. Query proc_interno (ds_proc_exame, nr_seq_classif, cd_tipo_cirurgia)
        $procInternoMap = [];
        if (! empty($nrSeqProcInternoIds)) {
            $piIds = array_keys($nrSeqProcInternoIds);
            $pPi = implode(',', array_fill(0, count($piIds), '?'));
            $piRows = DB::connection('tasy')->select(
                "SELECT nr_sequencia, ds_proc_exame, nr_seq_classif, cd_tipo_cirurgia FROM tasy.proc_interno WHERE nr_sequencia IN ({$pPi})",
                $piIds
            );
            foreach ($piRows as $r) {
                $procInternoMap[(string) $r->nr_sequencia] = ['ds' => $r->ds_proc_exame, 'classif' => $r->nr_seq_classif, 'cirurgia' => $r->cd_tipo_cirurgia];
            }
        }

        // 3. Query procedimento (ds, cd_tipo, cd_setor) — só para linhas sem proc_interno
        $procedMap = [];
        if (! empty($cdProcIds)) {
            $cpIds = array_keys($cdProcIds);
            $pCp = implode(',', array_fill(0, count($cpIds), '?'));
            $cpRows = DB::connection('tasy')->select(
                "SELECT cd_procedimento, MIN(ds_procedimento) AS ds, MIN(cd_tipo_procedimento) AS cd_tipo, MIN(cd_setor_exclusivo) AS cd_setor FROM tasy.procedimento WHERE cd_procedimento IN ({$pCp}) GROUP BY cd_procedimento",
                $cpIds
            );
            foreach ($cpRows as $r) {
                $procedMap[(string) $r->cd_procedimento] = ['ds' => $r->ds, 'cd_tipo' => $r->cd_tipo, 'cd_setor' => $r->cd_setor];
            }
        }

        // 4. Query exame_laboratorio + grupo (nm_exame, ds_grupo_exame_lab)
        $exameLabMap = [];
        if (! empty($nrSeqExameIds)) {
            $elIds = array_keys($nrSeqExameIds);
            $pEl = implode(',', array_fill(0, count($elIds), '?'));
            $elRows = DB::connection('tasy')->select(
                "SELECT el.nr_seq_exame, el.nm_exame, gel.ds_grupo_exame_lab FROM tasy.exame_laboratorio el LEFT JOIN tasy.grupo_exame_lab gel ON gel.nr_sequencia = el.nr_seq_grupo WHERE el.nr_seq_exame IN ({$pEl})",
                $elIds
            );
            foreach ($elRows as $r) {
                $exameLabMap[(string) $r->nr_seq_exame] = ['nm' => $r->nm_exame, 'grupo' => $r->ds_grupo_exame_lab];
            }
        }

        // 5. Cache de classif e cirurgia — tabelas pequenas (61 e 4 linhas), globais OK
        $procInternoClassifMap = $this->loadProcInternoClassifMap();
        $cihTipoCirurgiaMap = $this->loadCihTipoCirurgiaMap();

        // ── Anti-join result_laboratorio via nr_atendimento (1 query, evita ORA-01795) ──
        // USE_HASH força hash join em vez de NL, estabilizando o plano com Oracle buffer frio.
        $rlCollectedKeys = [];
        try {
            $_tRl = hrtime(true);
            $rlRows = DB::connection('tasy')->select("
                SELECT /*+ USE_HASH(rl pm) */ DISTINCT rl.nr_prescricao, rl.nr_seq_prescricao
                FROM tasy.result_laboratorio rl
                JOIN tasy.prescr_medica pm ON pm.nr_prescricao = rl.nr_prescricao
                WHERE pm.nr_atendimento IN ({$p})
                  AND (rl.dt_coleta IS NOT NULL OR rl.ds_resultado IS NOT NULL)
            ", $chunk);
            Log::info('[PendingEvents] query=prescription_rl rows='.count($rlRows).' ms='.round((hrtime(true) - $_tRl) / 1e6));

            foreach ($rlRows as $rl) {
                $rlCollectedKeys[$rl->nr_prescricao.'|'.$rl->nr_seq_prescricao] = true;
            }
        } catch (\Throwable $e) {
            Log::warning('[PendingEvents] prescription rl failed', ['error' => $e->getMessage()]);
        }

        // ── Resolve campos em PHP + filtra rl ────────────────────────────────────────
        $filteredRows = [];
        foreach ($rows as $row) {
            // Filtro rl
            if (isset($rlCollectedKeys[$row->nr_prescricao.'|'.$row->nr_sequencia_pp])) {
                continue;
            }

            $cdProc = isset($row->cd_procedimento) ? (string) $row->cd_procedimento : null;
            $nrSeqProc = $row->nr_seq_proc_interno ?? null;
            $pisKey = $nrSeqProc !== null ? ($nrSeqProc.'|'.($row->cd_estabelecimento ?? '')) : null;

            $piInfo = $nrSeqProc !== null ? ($procInternoMap[(string) $nrSeqProc] ?? null) : null;
            $elInfo = isset($row->nr_seq_exame) ? ($exameLabMap[(string) $row->nr_seq_exame] ?? null) : null;
            $procedInfo = ($cdProc !== null && $nrSeqProc === null) ? ($procedMap[$cdProc] ?? null) : null;

            $row->descricao = ($elInfo['nm'] ?? null) ?? ($piInfo['ds'] ?? null) ?? ($procedInfo['ds'] ?? null);
            $row->cd_tipo_procedimento = $procedInfo['cd_tipo'] ?? null;
            $row->ds_grupo_lab = ($elInfo['grupo'] ?? null)
                ?? ($piInfo ? ($procInternoClassifMap[(string) ($piInfo['classif'] ?? '')] ?? null) : null)
                ?? ($piInfo ? ($cihTipoCirurgiaMap[(string) ($piInfo['cirurgia'] ?? '')] ?? null) : null);

            $cdSetorExec = null;
            if ($pisKey !== null && isset($pisMap[$pisKey])) {
                $cdSetorExec = (string) $pisMap[$pisKey];
            } elseif ($procedInfo && $procedInfo['cd_setor'] !== null) {
                $cdSetorExec = (string) $procedInfo['cd_setor'];
            } else {
                $cdSetorExec = isset($row->cd_setor_prescricao) ? (string) $row->cd_setor_prescricao : null;
            }
            $row->setor_execucao = $cdSetorExec !== null ? ($sectorNameMap[$cdSetorExec] ?? null) : null;

            $filteredRows[] = $row;
        }
        $rows = $filteredRows;
        unset($filteredRows, $rlCollectedKeys);

        // Dedup PHP — substitui ROW_NUMBER() OVER (PARTITION BY nr_prescricao, nr_seq_proc_interno,
        // dt_prev_execucao ORDER BY nr_sequencia). A query já retorna ordenada por nr_sequencia,
        // então o primeiro registro por chave é o de menor nr_sequencia (equivalente a rn = 1).
        $dedupSeen = [];
        $dedupedRows = [];
        foreach ($rows as $row) {
            $key = $row->nr_prescricao.'|'.($row->nr_seq_proc_interno ?? '').'|'.($row->dt_evento ?? '');
            if (isset($dedupSeen[$key])) {
                continue;
            }
            $dedupSeen[$key] = true;
            $dedupedRows[] = $row;
        }
        unset($rows, $dedupSeen);

        // Deriva newerExameInfo em PHP — substitui Query 3 Oracle.
        $newerExameInfo = [];
        foreach ($dedupedRows as $row) {
            if (empty($row->nr_seq_exame)) {
                continue;
            }
            $existing = $newerExameInfo[$row->nr_atendimento][$row->nr_seq_exame]['nr'] ?? 0;
            if ((int) $row->nr_prescricao > $existing) {
                $dtFormatted = $row->dt_solicitacao ? date('d/m/Y', strtotime($row->dt_solicitacao)) : '?';
                $newerExameInfo[$row->nr_atendimento][$row->nr_seq_exame] = [
                    'nr' => (int) $row->nr_prescricao,
                    'info' => $row->nr_prescricao.' — '.$dtFormatted,
                ];
            }
        }

        // Query 2 – procedimento_paciente para enriquecimento (foi_executado, exame_coletado_nova, proc_realizado_nova)
        $ppByPrescricaoSeq = [];
        $ppMaxByExame = [];
        $ppMaxByProc = [];

        try {
            $_tQ2 = hrtime(true);
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
                  AND ppac.dt_procedimento >= SYSDATE - 7
            ", $chunk);
            Log::info('[PendingEvents] query=prescription_q2_proc_paciente rows='.count($ppRows).' ms='.round((hrtime(true) - $_tQ2) / 1e6));

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
            // apenas enriquecimento — falha silenciosa intencional
        }

        $now = time();

        foreach ($dedupedRows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $isExam = ! empty($row->nr_seq_exame);
            $tempo = $this->calcTempo($row->dt_evento ?? null, $row->dt_autorizacao ?? null, $now);
            $statusLabel = $dvMap[1226][$row->ie_status_execucao ?? ''] ?? null;
            $dsSubtipo = isset($row->cd_tipo_procedimento)
                ? ($dvMap[95][(string) $row->cd_tipo_procedimento] ?? null)
                : null;
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

            $results[$row->nr_atendimento]['pending_events'][] = [
                'tipo' => $tipo,
                'icone' => match ($tipo) {
                    PendingEventTypeClassifier::HEMOTHERAPY => 'hemoterapia.svg',
                    PendingEventTypeClassifier::EXAM => 'outpatient-department.svg',
                    default => 'tac.svg',
                },
                'descricao' => substr($row->descricao ?? '', 0, 80),
                'ds_subtipo' => $dsSubtipo,
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
                'dt_resultado' => ($row->dt_resultado && $row->dt_coleta) ? date('d/m/Y H:i', strtotime($row->dt_resultado)) : null,
                'ie_amostra' => $isExam ? ($row->ie_amostra ?? null) : null,
                'setor_execucao' => $row->setor_execucao ?? null,
                'tempo_pendente' => $tempo,
                'status_laudo' => $statusLabel,
                'ie_status_execucao' => $row->ie_status_execucao ?? null,
                'foi_executado_sem_baixa' => $foiExecutado || $temResultado,
                'exame_coletado_em_prescricao_mais_nova' => $exameColetadoNova,
                'proc_realizado_em_nova_prescricao' => $procRealizadoNova,
                'prescricao_mais_nova_pendente_info' => $prescricaoMaisNova,
                'urgente' => ($row->ie_urgencia ?? 'N') === 'S',
                'nr_seq_proc_interno' => $row->nr_seq_proc_interno ?? null,
                'is_oculto' => in_array((int) ($row->nr_seq_proc_interno ?? 0), [5927, 5970, 1341]),
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
                    sa.ds_setor_atendimento AS setor_execucao,
                    ch.dt_liberacao,
                    ch.dt_liberacao_enf
                FROM tasy.cpoe_hemoterapia ch
                LEFT JOIN tasy.via_aplicacao va
                    ON va.ie_via_aplicacao = ch.ie_via_aplicacao
                   AND va.ie_situacao = 'A'
                LEFT JOIN tasy.setor_atendimento sa
                    ON sa.cd_setor_atendimento = ch.cd_setor_atendimento
                WHERE ch.nr_atendimento IN ({$p})
                  AND ch.dt_programada BETWEEN SYSDATE AND SYSDATE + 1
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

            $hemoStatus = match (true) {
                ! empty($row->dt_liberacao_enf) => 'Liberada',
                ! empty($row->dt_liberacao) => 'Autorizada',
                default => 'Prescrita',
            };

            $dtEvento = $row->dt_evento ?? null;
            $dtTs = $dtEvento ? strtotime($dtEvento) : null;

            $results[$row->nr_atendimento]['pending_events'][] = [
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
                'ds_subtipo' => $tipo,
                'status_laudo' => $hemoStatus,
                'dt_evento' => $dtEvento,
                'dt_evento_formatted' => $dtTs ? date('d/m/Y H:i', $dtTs) : null,
                'dt_autorizacao' => ! empty($row->dt_liberacao) ? date('d/m/Y H:i', strtotime($row->dt_liberacao)) : null,
                'tempo_pendente' => $this->calcTempo($dtEvento, null, time()),
                'setor_execucao' => $row->setor_execucao ?? null,
                'urgente' => ($row->ie_urgencia ?? 'N') === 'S',
                '_fonte' => 'hemoterapia',
            ];
        }
    }

    private function processAntibioticChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);
        $dvMap = $this->loadValorDominioMap();

        // Pré-carrega lista de cd_material antimicrobianos (cache 1h).
        // Substitui o JOIN triplo material→m_stock→medic_ficha_tecnica que causava 19s no setor 6236:
        // Oracle escolhia plano iniciando por cpoe_material (todos os medicamentos dos pacientes),
        // expandia o join chain completo antes de filtrar ie_antimicrobiano='S'.
        // Com IN sobre cd_material pré-computado, Oracle acessa cpoe_material por índice composto
        // (nr_atendimento + cd_material) e já descarta não-antimicrobianos na leitura do índice.
        $antimCodes = $this->loadAntimicrobialMaterialCodes();

        if (empty($antimCodes)) {
            return;
        }

        $pAntim = $this->placeholders($antimCodes);

        try {
            $rows = DB::connection('tasy')->select("
                WITH base AS (
                    SELECT /*+ LEADING(cm pm pmh) */
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
                    JOIN tasy.material m ON m.cd_material = cm.cd_material
                    JOIN tasy.prescr_material pm ON pm.nr_seq_mat_cpoe = cm.nr_sequencia
                    JOIN tasy.prescr_mat_hor pmh
                        ON pmh.nr_prescricao   = pm.nr_prescricao
                       AND pmh.nr_seq_material  = pm.nr_sequencia
                       AND pmh.dt_horario >= TRUNC(SYSDATE)
                       AND pmh.dt_horario <  TRUNC(SYSDATE) + 1
                    LEFT JOIN tasy.prescr_mat_alteracao pma
                        ON pma.nr_seq_horario    = pmh.nr_sequencia
                       AND pma.nr_prescricao     = pmh.nr_prescricao
                       AND pma.nr_seq_prescricao = pm.nr_sequencia
                       AND (pma.ie_alteracao IS NULL OR pma.ie_alteracao NOT IN (5, 12))
                    LEFT JOIN tasy.pessoa_fisica pf ON pf.cd_pessoa_fisica = cm.cd_medico
                    WHERE cm.nr_atendimento IN ({$p})
                      AND cm.cd_material    IN ({$pAntim})
                      AND cm.dt_liberacao   IS NOT NULL
                      AND cm.dt_suspensao   IS NULL
                )
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
                ORDER BY nr_atendimento, dt_horario
            ", array_merge($chunk, $antimCodes));
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

            $results[$row->nr_atendimento]['pending_events'][] = [
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
                'status_laudo' => ! empty($row->ie_alteracao_code)
                    ? trim((string) ($dvMap[1620][(string) $row->ie_alteracao_code] ?? 'Pendente'))
                    : 'Pendente',
                'urgente' => false,
            ];
        }
    }

    private function processChemotherapyChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);
        $dvMap = $this->loadValorDominioMap();

        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.nr_atendimento,
                    aq.dt_agenda            AS dt_evento,
                    aq.ds_local,
                    aq.nm_medico_resp,
                    aq.ds_protocolo_medic,
                    aq.nr_ciclo,
                    aq.ie_status_agenda
                FROM tasy.atendimento_paciente ap
                JOIN tasy.agenda_quimioterapia_pep_v aq
                    ON aq.cd_pessoa_fisica = ap.cd_pessoa_fisica
                WHERE ap.nr_atendimento IN ({$p})
                    AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + 1
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

            $dsStatusAgenda = $dvMap[83][$row->ie_status_agenda ?? ''] ?? null;
            $parts = array_filter([
                ! empty($row->ds_protocolo_medic) ? $row->ds_protocolo_medic : null,
                ! empty($row->nr_ciclo) ? 'Ciclo '.$row->nr_ciclo : null,
                ! empty($dsStatusAgenda) ? $dsStatusAgenda : null,
                ! empty($row->ds_local) ? $row->ds_local : null,
                ! empty($row->nm_medico_resp) ? $row->nm_medico_resp : null,
            ]);

            $results[$row->nr_atendimento]['pending_events'][] = [
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
                'ds_status_agenda_label' => trim((string) ($dsStatusAgenda ?? '')),
                'status_laudo' => trim((string) ($dsStatusAgenda ?? '')),
                'setor_execucao' => ! empty($row->ds_local) ? trim($row->ds_local) : null,
                'tempo_pendente' => $this->calcTempo($row->dt_evento ?? null, null, time()),
                'urgente' => false,
                '_fonte' => 'agenda',
            ];
        }
    }

    private function processAgendaChunk(array &$results, array $chunk): void
    {
        $p = $this->placeholders($chunk);
        $dvMap = $this->loadValorDominioMap();
        $sectorNameMap = $this->loadSectorNameMap();

        try {
            $rows = DB::connection('tasy')->select("
                SELECT /*+ LEADING(atp ap) USE_NL(ap) */
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
                    ap.ds_cirurgia,
                    ap.ie_carater_cirurgia,
                    ap.ie_status_agenda,
                    ap.nr_seq_proc_interno,
                    ap.cd_procedimento,
                    ap.cd_setor_atendimento                  AS cd_setor_agenda,
                    pi.ds_proc_exame,
                    COALESCE(pf_med.nm_pessoa_fisica, pf_user.nm_pessoa_fisica, ap.nm_usuario) AS nm_prescritor
                FROM tasy.agenda_paciente ap
                JOIN tasy.atendimento_paciente atp ON atp.cd_pessoa_fisica = ap.cd_pessoa_fisica
                LEFT JOIN tasy.pessoa_fisica pf_med ON pf_med.cd_pessoa_fisica = ap.cd_medico
                LEFT JOIN tasy.usuario u_ap ON u_ap.nm_usuario = ap.nm_usuario
                LEFT JOIN tasy.pessoa_fisica pf_user ON pf_user.cd_pessoa_fisica = u_ap.cd_pessoa_fisica
                LEFT JOIN tasy.proc_interno pi ON pi.nr_sequencia = ap.nr_seq_proc_interno
                WHERE atp.nr_atendimento IN ({$p})
                    AND ap.dt_agenda >= TRUNC(SYSDATE)
                    AND ap.dt_agenda <= SYSDATE + 1
                    AND ap.ie_status_agenda NOT IN ('C', 'S', 'CR', 'E', 'AD', 'F', 'FI')
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

        // Targeted batch queries — apenas IDs presentes nos resultados (evita 80k rows globais).
        $cdProcAgendaIds = [];
        $nrSeqProcAgendaIds = [];
        foreach ($rows as $row) {
            if (! empty($row->nr_seq_proc_interno)) {
                $nrSeqProcAgendaIds[(string) $row->nr_seq_proc_interno] = true;
            } elseif (! empty($row->cd_procedimento)) {
                $cdProcAgendaIds[(string) $row->cd_procedimento] = true;
            }
        }

        // pis por proc_interno (setor primário)
        $pisAgendaMap = [];
        if (! empty($nrSeqProcAgendaIds)) {
            $piIds = array_keys($nrSeqProcAgendaIds);
            $pPi = implode(',', array_fill(0, count($piIds), '?'));
            $pisRows = DB::connection('tasy')->select(
                "SELECT nr_seq_proc_interno, MIN(cd_setor_atendimento) KEEP (DENSE_RANK FIRST ORDER BY nr_prioridade) AS cd_setor FROM tasy.proc_interno_setor WHERE nr_seq_proc_interno IN ({$pPi}) GROUP BY nr_seq_proc_interno",
                $piIds
            );
            foreach ($pisRows as $r) {
                $pisAgendaMap[(string) $r->nr_seq_proc_interno] = $r->cd_setor;
            }
        }

        // procedimento (descrição + setor exclusivo) — só quando sem proc_interno
        $procedAgendaMap = [];
        if (! empty($cdProcAgendaIds)) {
            $cpIds = array_keys($cdProcAgendaIds);
            $pCp = implode(',', array_fill(0, count($cpIds), '?'));
            $cpRows = DB::connection('tasy')->select(
                "SELECT cd_procedimento, MIN(ds_procedimento) AS ds, MIN(cd_setor_exclusivo) AS cd_setor FROM tasy.procedimento WHERE cd_procedimento IN ({$pCp}) GROUP BY cd_procedimento",
                $cpIds
            );
            foreach ($cpRows as $r) {
                $procedAgendaMap[(string) $r->cd_procedimento] = ['ds' => $r->ds, 'cd_setor' => $r->cd_setor];
            }
        }

        // Resolve descricao_proc e ds_setor_execucao em PHP.
        foreach ($rows as $row) {
            $cdProc = isset($row->cd_procedimento) ? (string) $row->cd_procedimento : null;
            $nrSeqProc = $row->nr_seq_proc_interno ?? null;

            $procedInfo = ($cdProc !== null && $nrSeqProc === null) ? ($procedAgendaMap[$cdProc] ?? null) : null;
            $row->descricao_proc = $row->ds_proc_exame ?? ($procedInfo['ds'] ?? $row->ds_cirurgia ?? null);

            $cdSetorExec = null;
            if ($nrSeqProc !== null && isset($pisAgendaMap[(string) $nrSeqProc])) {
                $cdSetorExec = (string) $pisAgendaMap[(string) $nrSeqProc];
            } elseif ($procedInfo && $procedInfo['cd_setor'] !== null) {
                $cdSetorExec = (string) $procedInfo['cd_setor'];
            } elseif (isset($row->cd_setor_agenda)) {
                $cdSetorExec = (string) $row->cd_setor_agenda;
            }
            $row->ds_setor_execucao = $cdSetorExec !== null ? ($sectorNameMap[$cdSetorExec] ?? null) : null;
        }

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $tipo = $row->tipo ?? 'exame';
            $isSurgery = $tipo === 'cirurgia';
            $isUrgent = $isSurgery && in_array($row->ie_carater_cirurgia ?? '', ['U', 'M'], true);
            $dsCaraterLabel = $dvMap[1016][$row->ie_carater_cirurgia ?? ''] ?? null;
            $dsStatusAgendaLabel = $dvMap[83][$row->ie_status_agenda ?? ''] ?? null;

            $results[$row->nr_atendimento]['pending_events'][] = [
                'tipo' => $tipo,
                'icone' => $isSurgery ? 'general-surgery.svg' : 'outpatient-department.svg',
                'descricao' => substr($row->descricao_proc ?? $row->ds_cirurgia ?? 'Procedimento', 0, 80),
                'ds_subtipo' => $isSurgery
                    ? ('Cirurgia'.($dsCaraterLabel ? ' – '.$dsCaraterLabel : ''))
                    : 'Exame/Procedimento',
                'dt_evento' => $row->dt_evento,
                'dt_evento_formatted' => $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                'ie_status_agenda' => $row->ie_status_agenda ?? null,
                'ds_status_agenda_label' => trim((string) ($dsStatusAgendaLabel ?? '')),
                'status_laudo' => trim((string) ($dsStatusAgendaLabel ?? '')),
                'carater_cirurgia' => $row->ie_carater_cirurgia ?? null,
                'setor_execucao' => $row->ds_setor_execucao ?? null,
                'nr_seq_proc_interno' => $row->nr_seq_proc_interno ?? null,
                'nm_prescritor' => $row->nm_prescritor ?? null,
                'tempo_pendente' => $this->calcTempo($row->dt_evento ?? null, null, time()),
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

    /**
     * Pré-carrega valor_dominio em PHP para eliminar JOINs das queries Oracle.
     * Domínios: 83 (status agenda), 95 (tipo procedimento), 1016 (caráter cirurgia),
     *           1226 (status execução proc), 1620 (alteração dose antibiótico).
     * Um único round-trip Oracle por processo; resultado em cache estático.
     *
     * @return array<int, array<string, string>>
     */
    private function loadValorDominioMap(): array
    {
        static $cache = null;

        if ($cache === null) {
            $rows = DB::connection('tasy')->select(
                'SELECT cd_dominio, vl_dominio, ds_valor_dominio FROM tasy.valor_dominio WHERE cd_dominio IN (83, 95, 1016, 1226, 1620)'
            );
            $cache = [83 => [], 95 => [], 1016 => [], 1226 => [], 1620 => []];
            foreach ($rows as $r) {
                $cache[(int) $r->cd_dominio][(string) $r->vl_dominio] = $r->ds_valor_dominio;
            }
        }

        return $cache;
    }

    /**
     * Cacheia a relação proc_interno → setor primário (ROW_NUMBER por prioridade) por 1h.
     * Chave: "nr_seq_proc_interno|cd_estabelecimento" → cd_setor_atendimento
     *
     * @return array<string, int|null>
     */
    private function loadProcInternSectorMap(): array
    {
        static $static = null;
        if ($static !== null) {
            return $static;
        }

        $static = Cache::remember('tasy_proc_intern_sector_map', 3600, function () {
            $rows = DB::connection('tasy')->select(
                'SELECT nr_seq_proc_interno, cd_estabelecimento, cd_setor_atendimento
                 FROM (
                     SELECT nr_seq_proc_interno, cd_setor_atendimento, cd_estabelecimento,
                            ROW_NUMBER() OVER (
                                PARTITION BY nr_seq_proc_interno, cd_estabelecimento
                                ORDER BY nr_prioridade
                            ) AS rn
                     FROM tasy.proc_interno_setor
                 ) WHERE rn = 1'
            );
            Log::info('[PendingEvents] loadProcInternSectorMap rows='.count($rows));
            $map = [];
            foreach ($rows as $r) {
                $map[$r->nr_seq_proc_interno.'|'.$r->cd_estabelecimento] = $r->cd_setor_atendimento;
            }

            return $map;
        });

        return $static;
    }

    /**
     * Cacheia tasy.setor_atendimento (cd_setor → nome display) por 1h.
     * Usado para resolver setor_execucao em PHP após remover os joins com proced e pis.
     *
     * @return array<string, string>
     */
    private function loadSectorNameMap(): array
    {
        static $static = null;
        if ($static !== null) {
            return $static;
        }

        $static = Cache::remember('tasy_sector_name_map', 3600, function () {
            $rows = DB::connection('tasy')->select(
                'SELECT cd_setor_atendimento, NVL(ds_prescricao, ds_setor_atendimento) AS ds_setor
                 FROM tasy.setor_atendimento'
            );
            Log::info('[PendingEvents] loadSectorNameMap rows='.count($rows));
            $map = [];
            foreach ($rows as $r) {
                $map[(string) $r->cd_setor_atendimento] = $r->ds_setor;
            }

            return $map;
        });

        return $static;
    }

    /** @return array<string, string> */
    private function loadProcInternoClassifMap(): array
    {
        static $static = null;
        if ($static !== null) {
            return $static;
        }

        $static = Cache::remember('tasy_proc_interno_classif_map', 3600, function () {
            $rows = DB::connection('tasy')->select(
                'SELECT nr_sequencia, ds_classificacao FROM tasy.proc_interno_classif'
            );
            Log::info('[PendingEvents] loadProcInternoClassifMap rows='.count($rows));
            $map = [];
            foreach ($rows as $r) {
                $map[(string) $r->nr_sequencia] = $r->ds_classificacao;
            }

            return $map;
        });

        return $static;
    }

    /** @return array<string, string> */
    private function loadCihTipoCirurgiaMap(): array
    {
        static $static = null;
        if ($static !== null) {
            return $static;
        }

        $static = Cache::remember('tasy_cih_tipo_cirurgia_map', 3600, function () {
            $rows = DB::connection('tasy')->select(
                'SELECT cd_tipo_cirurgia, ds_tipo_cirurgia FROM tasy.cih_tipo_cirurgia'
            );
            Log::info('[PendingEvents] loadCihTipoCirurgiaMap rows='.count($rows));
            $map = [];
            foreach ($rows as $r) {
                $map[(string) $r->cd_tipo_cirurgia] = $r->ds_tipo_cirurgia;
            }

            return $map;
        });

        return $static;
    }

    /**
     * Retorna os cd_material de antimicrobianos, cacheados por 1h no Laravel cache.
     *
     * Antes, essa lista era derivada via JOIN triplo (material → material_estoque →
     * medic_ficha_tecnica WHERE ie_antimicrobiano='S') dentro de cada execução do
     * processAntibioticChunk. Oracle escolhia plano ruim para setores com muitas prescrições,
     * causando 19s no setor 6236. Pré-carregar o conjunto fixo de cd_material (~50–300 itens)
     * permite substituir o join chain por um simples IN na cpoe_material.
     *
     * @return string[]
     */
    private function loadAntimicrobialMaterialCodes(): array
    {
        return Cache::remember('tasy_antimicrobial_material_codes', 3600, function () {
            $rows = DB::connection('tasy')->select(
                'SELECT DISTINCT m.cd_material
                 FROM tasy.material m
                 JOIN tasy.material m_stock ON m_stock.cd_material = m.cd_material_estoque
                 JOIN tasy.medic_ficha_tecnica mf ON mf.nr_sequencia = m_stock.nr_seq_ficha_tecnica
                 WHERE mf.ie_antimicrobiano = :flag',
                ['flag' => 'S']
            );
            Log::info('[PendingEvents] loadAntimicrobialMaterialCodes codes='.count($rows));

            return array_map(fn ($r) => $r->cd_material, $rows);
        });
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
            $events[] = [
                'tipo' => 'alta_medica', 'icone' => 'alta.svg',
                'descricao' => 'Alta Médica',
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
                'tone' => (string) (($item['urgente'] ?? false) ? 'danger' : $tone),
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
