<?php

namespace App\Services\PendingEvents\Handlers;

use App\Services\PendingEvents\AbstractPendingHandler;
use Illuminate\Support\Facades\DB;

/**
 * Pendências de procedimentos/exames prescritos ainda não executados.
 *
 * Fonte: prescr_medica + prescr_procedimento
 * Filtros alinhados com OBTER_EXAM_PEND_BAR: ie_suspenso<>'S', dt_cancelamento IS NULL,
 * ie_status_atend<35, NOT EXISTS result_laboratorio coletado, status NOT IN ('40','R','C','BE')
 *
 * Performance:
 *   - Substitui chamadas por linha a obter_desc_proc_interno(), obter_descricao_procedimento()
 *     e obter_valor_dominio() por LEFT JOINs estáticos avaliados uma única vez pelo otimizador.
 *   - Exclui nr_seq_proc_interno 5970/1341/5927 (procedimentos internos de sistema).
 */
class PrescriptionPendingHandler extends AbstractPendingHandler
{
    private const STATUS_MAP = [
        // Mapeamento observável no banco Tasy para pendências de prescrição.
        '10' => 'Pendente',
        '11' => 'Agendado',
        '14' => 'Aguardando execução',
        '15' => 'Coletado',
        '20' => 'Coletado',
        '30' => 'Em análise',
    ];

    protected function handlerName(): string
    {
        return 'Prescrições';
    }

    protected function processChunk(array &$results, array $chunk): void
    {
        $rows = DB::connection('tasy')->select("
            WITH base AS (
                SELECT
                    pm.nr_atendimento,
                    pm.nr_prescricao,
                    pp.nr_sequencia                              AS nr_sequencia_pp,
                    pp.nr_seq_exame,
                    pp.nr_seq_proc_interno,
                    pp.dt_prev_execucao                         AS dt_evento,
                    pp.dt_coleta,
                    pp.ie_amostra,
                    pm.dt_prescricao                            AS dt_solicitacao,
                    pm.dt_liberacao                             AS dt_autorizacao,
                    NVL(pf.nm_pessoa_fisica, pm.nm_usuario)     AS nm_prescritor,
                    pp.ie_status_execucao,
                    NVL(el.nm_exame, NVL(pi.ds_proc_exame, proced.ds_procedimento)) AS descricao,
                    dv.ds_valor_dominio                         AS ds_subtipo,
                    COALESCE(gel.ds_grupo_exame_lab, pic.ds_classificacao, cih.ds_tipo_cirurgia) AS ds_grupo_lab,
                    -- Computed once; reused in outer JOIN (eliminates duplicate function call)
                    NVL(
                        tasy.AGEINT_OBTER_SETOR_PROC_INT(
                            pp.nr_seq_proc_interno,
                            NULL, NULL, NULL, NULL, NULL,
                            pm.cd_estabelecimento
                        ),
                        NVL(proced.cd_setor_exclusivo, pm.cd_setor_atendimento)
                    ) AS cd_setor_execucao
                FROM tasy.prescr_medica pm
                JOIN tasy.prescr_procedimento pp
                    ON pp.nr_prescricao = pm.nr_prescricao
                LEFT JOIN tasy.result_laboratorio rl
                    ON  rl.nr_prescricao     = pm.nr_prescricao
                    AND rl.nr_seq_prescricao = pp.nr_sequencia
                    AND rl.dt_coleta         IS NOT NULL
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
                WHERE pm.nr_atendimento IN ({$this->placeholders($chunk)})
                    AND pp.ie_status_execucao NOT IN ('40', 'R', 'C', 'BE')
                    AND pp.dt_baixa        IS NULL
                    AND pp.dt_cancelamento IS NULL
                    AND pp.ie_suspenso     <> 'S'
                    AND pp.ie_status_atend < 35
                    AND pm.dt_liberacao    IS NOT NULL
                    AND pm.dt_suspensao    IS NULL
                    -- ie_origem_proced=4 (CPOE): exclui apenas procedimentos sem exame de lab; exames de lab com origem 4 são legítimos
                    AND (pp.ie_origem_proced <> 4 OR pp.nr_seq_exame IS NOT NULL)
                    AND (pp.nr_seq_proc_interno IS NULL OR pp.nr_seq_proc_interno NOT IN (5970, 1341, 5927))
                    AND rl.nr_prescricao IS NULL  -- anti-join replaces NOT EXISTS
            )
            SELECT
                b.*,
                sa.ds_setor_atendimento AS setor_desc_raw,
                (SELECT tasy.obter_status_laudo(MAX(pp_pac.nr_laudo))
                 FROM tasy.procedimento_paciente pp_pac
                 WHERE pp_pac.nr_prescricao          = b.nr_prescricao
                   AND pp_pac.nr_sequencia_prescricao = b.nr_sequencia_pp) AS ds_status_laudo,
                -- Flag: procedimento registrado como executado em procedimento_paciente (prescrição não foi baixada)
                CASE WHEN EXISTS (
                    SELECT 1 FROM tasy.procedimento_paciente pp_exec
                    WHERE pp_exec.nr_prescricao           = b.nr_prescricao
                      AND pp_exec.nr_sequencia_prescricao = b.nr_sequencia_pp
                ) THEN 1 ELSE 0 END AS foi_executado_sem_baixa,
                -- Flag: existe prescrição mais recente do mesmo exame já executada no mesmo atendimento
                CASE WHEN b.nr_seq_exame IS NOT NULL AND EXISTS (
                    SELECT 1 FROM tasy.procedimento_paciente pp_dup
                    WHERE pp_dup.nr_atendimento = b.nr_atendimento
                      AND pp_dup.nr_seq_exame   = b.nr_seq_exame
                      AND pp_dup.nr_prescricao  > b.nr_prescricao
                ) THEN 1 ELSE 0 END AS exame_coletado_em_prescricao_mais_nova
            FROM base b
            LEFT JOIN tasy.setor_atendimento sa
                ON sa.cd_setor_atendimento = b.cd_setor_execucao
            ORDER BY b.nr_atendimento, b.dt_evento NULLS LAST
        ", $chunk);

        $now = time();

        foreach ($rows as $row) {
            if (! isset($results[$row->nr_atendimento])) {
                continue;
            }

            $isExam = ! empty($row->nr_seq_exame);

            $tempo = '';
            if ($row->dt_evento) {
                $diff = strtotime($row->dt_evento) - $now;
                if ($diff > 0) {
                    $tempo = $diff < 3600
                        ? 'em '.(int) round($diff / 60).'min'
                        : ($diff < 86400
                            ? 'em '.(int) round($diff / 3600).'h'
                            : 'em '.(int) floor($diff / 86400).'d');
                } else {
                    $diff = abs($diff);
                    $tempo = $diff < 3600
                        ? (int) round($diff / 60).'min em aberto'
                        : ($diff < 86400
                            ? (int) round($diff / 3600).'h em aberto'
                            : (int) floor($diff / 86400).'d em aberto');
                }
            } elseif ($row->dt_autorizacao) {
                $diff = $now - strtotime($row->dt_autorizacao);
                $tempo = $diff < 3600
                    ? (int) round($diff / 60).'min em aberto'
                    : ($diff < 86400
                        ? (int) round($diff / 3600).'h em aberto'
                        : (int) floor($diff / 86400).'d em aberto');
            }

            $results[$row->nr_atendimento]['events'][] = [
                'tipo' => $isExam ? 'exame' : 'procedimento',
                'icone' => $isExam ? 'outpatient-department.svg' : 'tac.svg',
                'descricao' => substr($row->descricao ?? '', 0, 80),
                'ds_subtipo' => $row->ds_subtipo ?? null,
                'ds_grupo_lab' => $row->ds_grupo_lab ?? null,
                'nm_prescritor' => $row->nm_prescritor ?? null,
                'nr_prescricao' => $row->nr_prescricao ?? null,
                'dt_evento' => $row->dt_evento,
                'dt_evento_formatted' => $row->dt_evento ? date('d/m/Y H:i', strtotime($row->dt_evento)) : null,
                'dt_solicitacao' => $row->dt_solicitacao ? date('d/m/Y H:i', strtotime($row->dt_solicitacao)) : null,
                'dt_autorizacao' => $row->dt_autorizacao ? date('d/m/Y H:i', strtotime($row->dt_autorizacao)) : null,
                'dt_coleta' => $row->dt_coleta ? date('d/m/Y H:i', strtotime($row->dt_coleta)) : null,
                'ie_amostra' => $isExam ? ($row->ie_amostra ?? null) : null,
                'setor_execucao' => $row->setor_desc_raw ?? ($row->cd_setor_execucao ?? null),
                'tempo_pendente' => $tempo,
                'status_laudo' => self::STATUS_MAP[$row->ie_status_execucao] ?? 'Pendente',
                'ds_status_laudo' => $row->ds_status_laudo ?? null,
                'foi_executado_sem_baixa' => (int) ($row->foi_executado_sem_baixa ?? 0) === 1,
                'exame_coletado_em_prescricao_mais_nova' => (int) ($row->exame_coletado_em_prescricao_mais_nova ?? 0) === 1,
                'urgente' => false,
            ];
        }
    }
}
