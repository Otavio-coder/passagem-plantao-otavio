<?php

namespace App\Repositories\EMR;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Busca rápida de paciente no Tasy e retorna pendências das próximas 24h.
 * Destinado ao recurso beta "Consulta Rápida de Paciente".
 */
class PatientQuickSearchRepository
{
    /**
     * Busca pacientes internados pelo nome.
     * Mínimo 3 caracteres; split por espaço aplica AND em cada palavra.
     *
     * @return Collection<int, object>
     */
    public function searchByName(string $term): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 3) {
            return collect();
        }

        $words = array_values(array_filter(explode(' ', mb_strtoupper($term))));
        if (empty($words)) {
            return collect();
        }

        $conditions = [];
        $bindings = [];
        foreach ($words as $i => $word) {
            $key = "w{$i}";
            $conditions[] = "UPPER(NVL(pf.nm_social, pf.nm_pessoa_fisica)) LIKE :{$key}";
            $bindings[$key] = "%{$word}%";
        }

        $where = implode(' AND ', $conditions);

        try {
            $rows = DB::connection('tasy')->select("
                SELECT *
                FROM (
                    SELECT
                        atp.nr_atendimento,
                        atp.cd_pessoa_fisica,
                        NVL(pf.nm_social, pf.nm_pessoa_fisica)          AS nm_paciente,
                        pf.nr_prontuario,
                        pf.dt_nascimento,
                        ua.cd_unidade_basica                             AS leito,
                        NVL(sa.ds_prescricao, sa.ds_setor_atendimento)  AS setor
                    FROM TASY.ATENDIMENTO_PACIENTE atp
                    JOIN TASY.PESSOA_FISICA pf
                        ON pf.cd_pessoa_fisica = atp.cd_pessoa_fisica
                    JOIN TASY.UNIDADE_ATENDIMENTO ua
                        ON ua.nr_atendimento = atp.nr_atendimento
                    JOIN TASY.SETOR_ATENDIMENTO sa
                        ON sa.cd_setor_atendimento = ua.cd_setor_atendimento
                    WHERE ua.ie_situacao = 'A'
                      AND atp.dt_alta IS NULL
                      AND {$where}
                    ORDER BY NVL(pf.nm_social, pf.nm_pessoa_fisica)
                )
                WHERE ROWNUM <= 10
            ", $bindings);
        } catch (\Exception $e) {
            Log::error('[PatientQuickSearch] searchByName error', ['error' => $e->getMessage()]);

            return collect();
        }

        return collect($rows);
    }

    /**
     * Retorna pendências das próximas ~24h: cirurgias, procedimentos, exames laboratoriais.
     *
     * @return array{surgeries: array, procedures: array, labs: array}
     */
    public function getPending24h(int $nrAtendimento): array
    {
        return [
            'surgeries' => $this->fetchSurgeries($nrAtendimento),
            'procedures' => $this->fetchProcedures($nrAtendimento),
            'labs' => $this->fetchPendingLabs($nrAtendimento),
        ];
    }

    // ── Cirurgias ─────────────────────────────────────────────────────────────

    private function fetchSurgeries(int $nrAtendimento): array
    {
        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.hr_inicio,
                    ap.dt_agenda,
                    NVL(pi.ds_proc_exame, ap.ds_cirurgia)           AS ds_procedimento,
                    sc.ds_sala                                       AS sala,
                    NVL(sa.ds_prescricao, sa.ds_setor_atendimento)  AS local_execucao,
                    (
                        SELECT MAX(vd.ds_valor_dominio)
                        FROM TASY.VALOR_DOMINIO vd
                        WHERE vd.cd_dominio = 83 AND vd.vl_dominio = ap.ie_status_agenda
                    ) AS ds_status,
                    (
                        SELECT MAX(vd.ds_valor_dominio)
                        FROM TASY.VALOR_DOMINIO vd
                        WHERE vd.cd_dominio = 1016 AND vd.vl_dominio = ap.ie_carater_cirurgia
                    ) AS ds_carater
                FROM TASY.AGENDA_PACIENTE ap
                LEFT JOIN TASY.PROC_INTERNO pi
                    ON pi.nr_seq_proc_interno = ap.nr_seq_proc_interno
                LEFT JOIN TASY.SALA_CIRURGIA sc
                    ON sc.nr_sequencia = ap.nr_seq_sala_cir
                LEFT JOIN TASY.SETOR_ATENDIMENTO sa
                    ON sa.cd_setor_atendimento = ap.cd_setor_atendimento
                WHERE ap.nr_atendimento = :nr
                  AND ap.dt_agenda >= TRUNC(SYSDATE)
                  AND ap.dt_agenda  < TRUNC(SYSDATE) + 2
                  AND ap.ie_status_agenda NOT IN ('C','S','CR','E','AD','F','FI')
                  AND ap.dt_executada IS NULL
                  AND ap.ie_carater_cirurgia IS NOT NULL
                  AND ap.ie_carater_cirurgia != 'X'
                ORDER BY ap.hr_inicio NULLS LAST
            ", ['nr' => $nrAtendimento]);

            return array_map([$this, 'formatAgendaRow'], $rows);
        } catch (\Exception $e) {
            Log::error('[PatientQuickSearch] fetchSurgeries error', ['nr' => $nrAtendimento, 'error' => $e->getMessage()]);

            return [];
        }
    }

    // ── Procedimentos agendados (não-cirúrgicos) ──────────────────────────────

    private function fetchProcedures(int $nrAtendimento): array
    {
        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.hr_inicio,
                    ap.dt_agenda,
                    NVL(pi.ds_proc_exame, ap.ds_cirurgia)           AS ds_procedimento,
                    NULL                                             AS sala,
                    NVL(sa.ds_prescricao, sa.ds_setor_atendimento)  AS local_execucao,
                    (
                        SELECT MAX(vd.ds_valor_dominio)
                        FROM TASY.VALOR_DOMINIO vd
                        WHERE vd.cd_dominio = 83 AND vd.vl_dominio = ap.ie_status_agenda
                    ) AS ds_status
                FROM TASY.AGENDA_PACIENTE ap
                LEFT JOIN TASY.PROC_INTERNO pi
                    ON pi.nr_seq_proc_interno = ap.nr_seq_proc_interno
                LEFT JOIN TASY.SETOR_ATENDIMENTO sa
                    ON sa.cd_setor_atendimento = ap.cd_setor_atendimento
                WHERE ap.nr_atendimento = :nr
                  AND ap.dt_agenda >= TRUNC(SYSDATE)
                  AND ap.dt_agenda  < TRUNC(SYSDATE) + 2
                  AND ap.ie_status_agenda NOT IN ('C','S','CR','E','AD','F','FI')
                  AND ap.dt_executada IS NULL
                  AND (ap.ie_carater_cirurgia IS NULL OR ap.ie_carater_cirurgia = 'X')
                  AND ap.nr_seq_proc_interno IS NOT NULL
                ORDER BY ap.hr_inicio NULLS LAST
            ", ['nr' => $nrAtendimento]);

            return array_map([$this, 'formatAgendaRow'], $rows);
        } catch (\Exception $e) {
            Log::error('[PatientQuickSearch] fetchProcedures error', ['nr' => $nrAtendimento, 'error' => $e->getMessage()]);

            return [];
        }
    }

    // ── Exames laboratoriais pendentes de coleta ──────────────────────────────

    private function fetchPendingLabs(int $nrAtendimento): array
    {
        try {
            $rows = DB::connection('tasy')->select("
                SELECT
                    el.nm_exame,
                    pp.dt_prev_execucao,
                    gel.ds_grupo_exame_lab AS grupo
                FROM TASY.PRESCR_MEDICA pm
                JOIN TASY.PRESCR_PROCEDIMENTO pp
                    ON pp.nr_prescricao = pm.nr_prescricao
                JOIN TASY.EXAME_LABORATORIO el
                    ON el.nr_seq_exame = pp.nr_seq_exame
                LEFT JOIN TASY.GRUPO_EXAME_LAB gel
                    ON gel.nr_seq_grupo = el.nr_seq_grupo
                WHERE pm.nr_atendimento = :nr
                  AND pp.nr_seq_exame IS NOT NULL
                  AND pp.dt_coleta IS NULL
                  AND pp.dt_cancelamento IS NULL
                  AND NVL(pp.ie_suspenso, 'N') != 'S'
                  AND pp.ie_status_execucao NOT IN ('40','R','C','BE')
                  AND NOT EXISTS (
                      SELECT 1 FROM TASY.RESULTADO_LABORATORIO rl
                      WHERE rl.nr_prescricao = pp.nr_prescricao
                        AND rl.nr_sequencia  = pp.nr_sequencia
                  )
                  AND (
                      pp.dt_prev_execucao IS NULL
                      OR pp.dt_prev_execucao < TRUNC(SYSDATE) + 2
                  )
                ORDER BY pp.dt_prev_execucao NULLS FIRST, el.nm_exame
            ", ['nr' => $nrAtendimento]);

            return array_map(fn ($r) => [
                'nm_exame' => $r->nm_exame ?? '—',
                'grupo' => $r->grupo,
                'coleta_prevista' => ! empty($r->dt_prev_execucao)
                    ? Carbon::parse($r->dt_prev_execucao)->format('H:i')
                    : null,
            ], $rows);
        } catch (\Exception $e) {
            Log::error('[PatientQuickSearch] fetchPendingLabs error', ['nr' => $nrAtendimento, 'error' => $e->getMessage()]);

            return [];
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function formatAgendaRow(object $r): array
    {
        return [
            'horario' => ! empty($r->hr_inicio) ? Carbon::parse($r->hr_inicio)->format('H:i') : null,
            'descricao' => $r->ds_procedimento ?? '—',
            'local' => $r->sala ?? $r->local_execucao ?? null,
            'status' => $r->ds_status ?? null,
            'carater' => $r->ds_carater ?? null,
        ];
    }
}
