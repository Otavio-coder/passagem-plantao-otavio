<?php

namespace App\Repositories\EMR;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PatientPrescricoesRepository
{
    protected $connection = 'tasy';

    /**
     * Busca medicamentos ativos das ordens médicas.
     *
     * Uma linha por ordem médica — sem repetição por horário agendado.
     * prescr_mat_hor gerava N linhas por dose (repetição que foi removida).
     *
     * IE_ADMINISTRACAO: H=Conforme Horário | N=Se Necessário | A=A Critério Médico
     *
     * Inclui medicamentos suspensos/administrados para exibir status completo ao enfermeiro.
     */
    public function getMedicamentos($attendanceNumber): array
    {
        $rows = DB::connection('tasy')->select("
            SELECT
                cm.NR_SEQUENCIA,
                SUBSTR(mat.ds_material, 1, 255)          AS ds_material,
                cm.QT_DOSE,
                cm.CD_UNIDADE_MEDIDA,
                va.ds_via_aplicacao                      AS via_aplicacao,
                cm.DS_HORARIOS,
                cm.HR_PRIM_HORARIO,
                TO_CHAR(cm.DT_INICIO, 'DD/MM/YY')        AS dt_inicio_fmt,
                TO_CHAR(cm.DT_FIM,    'DD/MM/YY')        AS dt_fim_fmt,
                cm.DT_INICIO,
                cm.DT_FIM,
                cm.IE_ADMINISTRACAO,
                cm.DS_OBSERVACAO,
                cm.DS_JUSTIFICATIVA,
                cm.DT_SUSPENSAO,
                SUBSTR(pf.nm_pessoa_fisica, 1, 100)      AS nome_prescritor,
                -- Doses de hoje via PRESCR_MATERIAL -> PRESCR_MAT_HOR
                -- DT_CHECAGEM preenchido = dose administrada pelo enfermeiro
                NVL(dose_stats.total_hoje, 0)            AS total_doses_hoje,
                NVL(dose_stats.checadas,   0)            AS doses_checadas
            FROM tasy.CPOE_MATERIAL cm
            LEFT JOIN tasy.material mat          ON cm.CD_MATERIAL       = mat.cd_material
            LEFT JOIN tasy.via_aplicacao va      ON cm.IE_VIA_APLICACAO  = va.ie_via_aplicacao
                                                AND va.ie_situacao       = 'A'
            LEFT JOIN tasy.pessoa_fisica pf      ON cm.CD_PESSOA_FISICA  = pf.cd_pessoa_fisica
            LEFT JOIN (
                SELECT pmt.NR_SEQ_MAT_CPOE,
                       COUNT(pmh.NR_SEQUENCIA)                                      AS total_hoje,
                       SUM(CASE WHEN pmh.DT_CHECAGEM IS NOT NULL THEN 1 ELSE 0 END) AS checadas
                FROM tasy.PRESCR_MATERIAL pmt
                JOIN tasy.PRESCR_MAT_HOR pmh ON pmh.NR_PRESCRICAO   = pmt.NR_PRESCRICAO
                                             AND pmh.NR_SEQ_MATERIAL = pmt.NR_SEQUENCIA
                WHERE TRUNC(pmh.DT_HORARIO) = TRUNC(SYSDATE)
                  AND pmt.NR_SEQ_MAT_CPOE   IS NOT NULL
                  AND pmh.DT_SUSPENSAO      IS NULL
                GROUP BY pmt.NR_SEQ_MAT_CPOE
            ) dose_stats ON dose_stats.NR_SEQ_MAT_CPOE = cm.NR_SEQUENCIA
            WHERE cm.NR_ATENDIMENTO             = :attendance
              AND cm.DT_LIBERACAO               IS NOT NULL
              AND TRUNC(cm.DT_INICIO)           <= TRUNC(SYSDATE)
              AND (cm.DT_FIM IS NULL OR TRUNC(cm.DT_FIM) >= TRUNC(SYSDATE))
            ORDER BY
                -- suspensos por último
                CASE WHEN cm.DT_SUSPENSAO IS NOT NULL THEN 2
                     -- administrados (todas doses de hoje checadas) após ativos pendentes
                     WHEN NVL(dose_stats.total_hoje, 0) > 0
                      AND NVL(dose_stats.checadas, 0) >= NVL(dose_stats.total_hoje, 0) THEN 1
                     ELSE 0
                END,
                cm.HR_PRIM_HORARIO NULLS LAST,
                cm.NR_SEQUENCIA
        ", ['attendance' => $attendanceNumber]);

        $items = collect($rows)->map(fn ($r) => $this->formatMedicamento($r))->values()->toArray();

        return ['total_count' => count($items), 'items' => $items];
    }

    /**
     * Busca procedimentos prescritos + exames agendados.
     *
     * Procedimentos prescritos: prescr_medica + prescr_procedimento
     * Exames agendados:         agenda_paciente (próximos 30 dias, excluindo cirurgias)
     *
     * Bate com os dados que aparecem no card do paciente:
     *   - procedimentos_cirurgicos → agenda_paciente com ie_carater_cirurgia (excluído aqui)
     */
    public function getProcedimentos($attendanceNumber): array
    {
        $prescricoes = DB::connection('tasy')->select("
            SELECT
                'prescricao'                                        AS origem,
                pp.nr_sequencia,
                pp.ie_status_execucao,
                pp.dt_prev_execucao,
                pp.dt_coleta,
                pp.dt_baixa,
                TO_CHAR(pp.dt_prev_execucao, 'DD/MM/YY')           AS dt_prevista_fmt,
                TO_CHAR(pp.dt_prev_execucao, 'HH24:MI')            AS hr_prevista,
                COALESCE(
                    (SELECT MAX(pi.ds_proc_exame)
                     FROM tasy.proc_interno pi
                     WHERE pi.nr_sequencia = pp.nr_seq_proc_interno),
                    (SELECT MAX(proc.ds_procedimento)
                     FROM tasy.procedimento proc
                     WHERE proc.cd_procedimento  = pp.cd_procedimento
                       AND proc.ie_origem_proced = pp.ie_origem_proced)
                ) AS ds_descricao
            FROM tasy.prescr_medica pm
            JOIN tasy.prescr_procedimento pp ON pp.nr_prescricao = pm.nr_prescricao
            WHERE pm.nr_atendimento  = :attendance
              AND pm.dt_liberacao    IS NOT NULL
              AND pm.dt_suspensao    IS NULL
            ORDER BY
                CASE WHEN pp.dt_baixa IS NOT NULL THEN 1 ELSE 0 END,
                pp.dt_prev_execucao NULLS LAST,
                pp.nr_sequencia
        ", ['attendance' => $attendanceNumber]);

        $agendamentos = DB::connection('tasy')->select("
            SELECT
                'agendamento'                                       AS origem,
                ap.nr_sequencia,
                ap.ie_status_agenda                                 AS ie_status_execucao,
                ap.dt_agenda                                        AS dt_prev_execucao,
                NULL                                                AS dt_coleta,
                ap.dt_executada                                     AS dt_baixa,
                TO_CHAR(ap.dt_agenda, 'DD/MM/YY')                  AS dt_prevista_fmt,
                CASE WHEN ap.hr_inicio IS NOT NULL
                     THEN TO_CHAR(ap.hr_inicio, 'HH24:MI')
                     ELSE NULL END                                  AS hr_prevista,
                COALESCE(
                    (SELECT MAX(pi.ds_proc_exame)
                     FROM tasy.proc_interno pi
                     WHERE pi.nr_sequencia = ap.nr_seq_proc_interno),
                    (SELECT MAX(proc.ds_procedimento)
                     FROM tasy.procedimento proc
                     WHERE proc.cd_procedimento  = ap.cd_procedimento
                       AND proc.ie_origem_proced = ap.ie_origem_proced),
                    ap.ds_observacao
                ) AS ds_descricao
            FROM tasy.agenda_paciente ap
            WHERE ap.nr_atendimento      = :attendance
              AND ap.dt_agenda           >= TRUNC(SYSDATE)
              AND ap.dt_agenda           <= SYSDATE + 30
              AND ap.ie_carater_cirurgia IS NULL
              AND ap.ie_status_agenda    NOT IN ('C', 'S')
              AND ap.dt_executada        IS NULL
              AND (ap.nr_seq_proc_interno IS NOT NULL OR ap.cd_procedimento IS NOT NULL)
            ORDER BY
                CASE WHEN ap.dt_executada IS NOT NULL THEN 1 ELSE 0 END,
                ap.dt_agenda,
                ap.hr_inicio NULLS LAST
        ", ['attendance' => $attendanceNumber]);

        $items = collect(array_merge($prescricoes, $agendamentos))
            ->map(fn ($r) => $this->formatProcedimento($r))
            ->values()->toArray();

        return ['total_count' => count($items), 'items' => $items];
    }

    /**
     * Busca prescrições nutricionais ativas.
     */
    public function getNutricao($attendanceNumber): array
    {
        $rows = DB::connection('tasy')->select("
            SELECT
                cd.NR_SEQUENCIA,
                cd.CD_DIETA,
                d.DS_DIETA,
                cd.CD_MATERIAL,
                SUBSTR(mat.ds_material, 1, 255)     AS DS_MATERIAL,
                cd.QT_DOSE,
                cd.CD_UNIDADE_MEDIDA_DOSE,
                cd.QT_VOLUME,
                cd.QT_VOLUME_TOTAL,
                cd.QT_KCAL_TOTAL,
                cd.DS_HORARIOS,
                cd.HR_PRIM_HORARIO,
                cd.DT_INICIO,
                cd.DT_FIM,
                cd.IE_TIPO_DIETA,
                cd.DS_OBSERVACAO,
                cd.IE_DIETA_ENTERAL,
                cd.IE_DIETA_ESPECIAL,
                cd.DS_ALERGIAS_ALIMENTARES,
                cd.IE_JEJUM,
                obj.DS_OBJETIVO     AS DS_OBJETIVO_JEJUM,
                tj.DS_TIPO          AS DS_TIPO_JEJUM,
                cd.DT_INICIO_JEJUM,
                cd.DT_FIM_JEJUM,
                SUBSTR(pf.nm_pessoa_fisica, 1, 100) AS NOME_NUTRICIONISTA,
                CASE
                    WHEN cd.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) BETWEEN 7  AND 13 THEN 'MANHÃ'
                    WHEN cd.HR_PRIM_HORARIO IS NOT NULL AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) BETWEEN 13 AND 19 THEN 'TARDE'
                    ELSE 'NOITE'
                END AS turno
            FROM tasy.cpoe_dieta cd
            LEFT JOIN tasy.dieta d                ON cd.CD_DIETA          = d.CD_DIETA
            LEFT JOIN tasy.REP_OBJETIVO_JEJUM obj ON cd.NR_SEQ_OBJETIVO   = obj.NR_SEQUENCIA
            LEFT JOIN tasy.REP_TIPO_JEJUM tj      ON cd.NR_SEQ_TIPO       = tj.NR_SEQUENCIA
            LEFT JOIN tasy.material mat           ON cd.CD_MATERIAL       = mat.cd_material
            LEFT JOIN tasy.pessoa_fisica pf       ON cd.CD_PESSOA_FISICA  = pf.cd_pessoa_fisica
            WHERE cd.NR_ATENDIMENTO = :attendance
              AND cd.DT_LIBERACAO   IS NOT NULL
              AND cd.DT_SUSPENSAO   IS NULL
                            AND TRUNC(cd.DT_INICIO) <= TRUNC(SYSDATE)
                            AND (cd.DT_FIM >= SYSDATE OR (cd.DT_FIM IS NULL AND cd.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
            ORDER BY cd.DT_INICIO, cd.NR_SEQUENCIA
        ", ['attendance' => $attendanceNumber]);

        return $this->organizarPorTurno($rows, 'nutricao');
    }

    /**
     * Busca recomendações médicas ativas.
     * São orientações e informações do médico — não são prescrições diretas.
     * Podem conter informações de periodicidade e observações sobre medicamentos.
     */
    public function getRecomendacoes($attendanceNumber): array
    {
        $rows = DB::connection('tasy')->select('
            SELECT
                cr.NR_SEQUENCIA,
                cr.CD_RECOMENDACAO,
                tr.DS_TIPO_RECOMENDACAO,
                cr.DS_RECOMENDACAO,
                cr.DS_HORARIOS,
                cr.HR_PRIM_HORARIO,
                cr.DT_INICIO,
                cr.DT_FIM,
                cr.DS_OBSERVACAO,
                SUBSTR(pf.nm_pessoa_fisica, 1, 100) AS NOME_PROFISSIONAL
            FROM tasy.cpoe_recomendacao cr
            LEFT JOIN tasy.TIPO_RECOMENDACAO tr ON cr.CD_RECOMENDACAO  = tr.CD_TIPO_RECOMENDACAO
            LEFT JOIN tasy.pessoa_fisica pf     ON cr.CD_PESSOA_FISICA = pf.cd_pessoa_fisica
            WHERE cr.NR_ATENDIMENTO = :attendance
              AND cr.DT_LIBERACAO   IS NOT NULL
              AND cr.DT_SUSPENSAO   IS NULL
                            AND TRUNC(cr.DT_INICIO) <= TRUNC(SYSDATE)
                            AND (cr.DT_FIM >= SYSDATE OR (cr.DT_FIM IS NULL AND cr.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
            ORDER BY cr.DT_INICIO, cr.NR_SEQUENCIA
        ', ['attendance' => $attendanceNumber]);

        $items = collect($rows)->map(fn ($r) => $this->formatRecomendacao($r))->values()->toArray();

        return ['total_count' => count($items), 'items' => $items];
    }

    /**
     * Busca intervenções ativas de enfermagem.
     * São cuidados/procedimentos de suporte — não são prescrições diretas.
     */
    public function getIntervencoes($attendanceNumber): array
    {
        $rows = DB::connection('tasy')->select('
            SELECT
                ci.NR_SEQUENCIA,
                ci.NR_SEQ_PROC,
                (
                    SELECT MAX(pi.ds_proc_exame)
                    FROM tasy.proc_interno pi
                    WHERE pi.nr_sequencia = ci.NR_SEQ_PROC
                )                                                   AS DS_PROCEDIMENTO,
                ci.DS_HORARIOS,
                ci.HR_PRIM_HORARIO,
                ci.DT_INICIO,
                ci.DT_FIM,
                ci.DS_OBSERVACAO,
                SUBSTR(pf_prof.nm_pessoa_fisica,  1, 100)           AS NOME_PROFISSIONAL,
                SUBSTR(pf_presc.nm_pessoa_fisica, 1, 100)           AS NOME_PRESCRITOR,
                ci.IE_SE_NECESSARIO,
                ci.IE_ACM,
                ci.IE_URGENCIA,
                ci.IE_LADO
            FROM tasy.CPOE_INTERVENCAO ci
            LEFT JOIN tasy.pessoa_fisica pf_prof  ON ci.CD_PESSOA_FISICA = pf_prof.cd_pessoa_fisica
            LEFT JOIN tasy.pessoa_fisica pf_presc ON ci.CD_PRESCRITOR    = pf_presc.cd_pessoa_fisica
            WHERE ci.NR_ATENDIMENTO = :attendance
              AND ci.DT_LIBERACAO   IS NOT NULL
              AND ci.DT_SUSPENSAO   IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(ci.DT_INICIO) AND NVL(TRUNC(ci.DT_FIM), TRUNC(SYSDATE))
            ORDER BY ci.DT_INICIO, ci.NR_SEQUENCIA
        ', ['attendance' => $attendanceNumber]);

        $items = collect($rows)->map(fn ($r) => $this->formatIntervencao($r))->values()->toArray();

        return ['total_count' => count($items), 'items' => $items];
    }

    // ==================== FORMATADORES ====================

    private function formatMedicamento($item): array
    {
        $totalDoses = (int) ($item->total_doses_hoje ?? 0);
        $dosesChecadas = (int) ($item->doses_checadas ?? 0);

        // Status em 4 níveis:
        // suspenso   → ordem médica suspensa (DT_SUSPENSAO em CPOE_MATERIAL)
        // administrado → todas as doses de hoje confirmadas pelo enfermeiro (DT_CHECAGEM)
        // ativo      → tem doses hoje mas nenhuma/parcialmente checada; ou sem doses agendadas (SOS/PRN)
        if (! empty($item->dt_suspensao)) {
            $status = 'suspenso';
        } elseif ($totalDoses > 0 && $dosesChecadas >= $totalDoses) {
            $status = 'administrado';
        } else {
            $status = 'ativo';
        }

        $adminMap = [
            'P' => 'Padrão',
            'H' => 'Conforme Horário',
            'N' => 'Se Necessário',
            'A' => 'A Critério Médico',
        ];

        return [
            'nome' => $item->ds_material ?? 'Medicamento não identificado',
            'dose' => trim(($item->qt_dose ?? '').' '.($item->cd_unidade_medida ?? '')),
            'via_aplicacao' => $item->via_aplicacao ?? '',
            'horarios' => $item->ds_horarios ?? ($item->hr_prim_horario ?? ''),
            'dt_inicio' => $item->dt_inicio_fmt ?? '',
            'dt_fim' => $item->dt_fim_fmt ?? null,
            'ie_administracao' => $item->ie_administracao ?? null,
            'administracao' => $adminMap[$item->ie_administracao ?? ''] ?? ($item->ie_administracao ?? ''),
            'observacao' => ! empty($item->ds_observacao) ? $item->ds_observacao : null,
            'justificativa' => ! empty($item->ds_justificativa) ? $item->ds_justificativa : null,
            'nome_prescritor' => $item->nome_prescritor ?? null,
            'status' => $status,
            'total_doses_hoje' => $totalDoses,
            'doses_checadas' => $dosesChecadas,
            'has_details' => ! empty($item->ds_observacao) || ! empty($item->ds_justificativa),
        ];
    }

    private function formatProcedimento($item): array
    {
        $statusMap = [
            '10' => 'Aguardando',
            '20' => 'Coletado',
            '30' => 'Em análise',
            '40' => 'Concluído',
            'A' => 'Agendado',
            'R' => 'Realizado',
        ];

        $status = 'Pendente';
        if (! empty($item->dt_baixa)) {
            $status = 'Realizado';
        } elseif (! empty($item->ie_status_execucao)) {
            $status = $statusMap[$item->ie_status_execucao] ?? 'Pendente';
        }

        $dtFormatada = $item->dt_prevista_fmt ?? null;
        if ($dtFormatada && ! empty($item->hr_prevista)) {
            $dtFormatada .= ' '.$item->hr_prevista;
        }

        return [
            'descricao' => $item->ds_descricao ?? 'Procedimento não identificado',
            'origem' => $item->origem,
            'dt_prevista' => $dtFormatada,
            'status' => $status,
            'realizado' => ! empty($item->dt_baixa),
        ];
    }

    private function formatNutricao($item): array
    {
        $isPejeJejum = $item->ie_tipo_dieta === 'J';
        $tipoNutricao = $isPejeJejum ? 'Jejum' :
            ($item->ie_dieta_enteral === 'S' ? 'Enteral' :
            ($item->ie_dieta_especial === 'S' ? 'Especial' : 'Dieta'));

        return [
            'prescricao' => $isPejeJejum
                ? 'JEJUM'.($item->ds_tipo_jejum ? ' - '.$item->ds_tipo_jejum : '')
                : ($item->ds_dieta ?? $item->ds_material ?? 'Prescrição nutricional'),
            'tipo_nutricao' => $tipoNutricao,
            'is_jejum' => $isPejeJejum,
            'tipo_jejum' => $item->ds_tipo_jejum ?? null,
            'objetivo_jejum' => $item->ds_objetivo_jejum ?? null,
            'observacoes' => $item->ds_observacao ?? '',
            'horarios' => $item->ds_horarios ?? ($item->hr_prim_horario ?? ''),
            'volume' => $item->qt_volume ?? null,
            'volume_total' => $item->qt_volume_total ?? null,
            'kcal_total' => $item->qt_kcal_total ?? null,
            'turno' => $item->turno,
            'alergias_alimentares' => $item->ds_alergias_alimentares ?? null,
            'is_enteral' => ($item->ie_dieta_enteral ?? '') === 'S',
            'is_especial' => ($item->ie_dieta_especial ?? '') === 'S',
            'nome_nutricionista' => $item->nome_nutricionista ?? null,
            'dt_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : null,
            'dt_fim' => $item->dt_fim ? Carbon::parse($item->dt_fim)->format('d/m/Y') : null,
            'has_details' => true,
        ];
    }

    private function formatRecomendacao($item): array
    {
        $texto = $item->ds_recomendacao ?? '';
        // Se não há texto livre, o tipo já é a recomendação em si
        $textoExibicao = $texto ?: ($item->ds_tipo_recomendacao ?? 'Recomendação não especificada');

        return [
            'recomendacao' => $textoExibicao,
            'tipo_recomendacao' => $item->ds_tipo_recomendacao ?? '',
            'observacoes' => $item->ds_observacao ?? '',
            'horarios' => $item->ds_horarios ?? ($item->hr_prim_horario ?? ''),
            'nome_profissional' => $item->nome_profissional ?? '',
            'dt_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : '',
            'dt_fim' => $item->dt_fim ? Carbon::parse($item->dt_fim)->format('d/m/Y') : null,
            'has_details' => ! empty($item->ds_observacao) || mb_strlen($textoExibicao) > 100,
        ];
    }

    private function formatIntervencao($item): array
    {
        $labels = [];
        if (($item->ie_urgencia ?? '') === '1') {
            $labels[] = 'Urgente';
        }
        if (($item->ie_se_necessario ?? '') === 'S') {
            $labels[] = 'Se Necessário';
        }
        if (($item->ie_acm ?? '') === 'S') {
            $labels[] = 'ACM';
        }
        if (! empty($item->ie_lado)) {
            $lado_map = ['D' => 'Direito', 'E' => 'Esquerdo', 'B' => 'Bilateral'];
            $labels[] = 'Lado: '.($lado_map[$item->ie_lado] ?? $item->ie_lado);
        }

        return [
            'procedimento' => $item->ds_procedimento ?? 'Procedimento não especificado',
            'observacoes' => $item->ds_observacao ?? '',
            'horarios' => $item->ds_horarios ?? ($item->hr_prim_horario ?? ''),
            'nome_profissional' => $item->nome_profissional ?? '',
            'nome_prescritor' => $item->nome_prescritor ?? '',
            'labels' => $labels,
            'dt_inicio' => $item->dt_inicio ? Carbon::parse($item->dt_inicio)->format('d/m/Y') : '',
            'dt_fim' => $item->dt_fim ? Carbon::parse($item->dt_fim)->format('d/m/Y') : null,
            'has_details' => ! empty($item->ds_observacao) || ! empty($labels),
        ];
    }

    // ==================== ORGANIZADOR POR TURNO ====================

    private function organizarPorTurno(array $data, string $tipo): array
    {
        $dataByShift = collect($data)->groupBy('turno');
        $chaveMap = ['nutricao' => 'prescriptions'];
        $chave = $chaveMap[$tipo] ?? $tipo;

        $result = ['total_count' => count($data), 'shifts' => []];

        foreach (['MANHÃ', 'TARDE', 'NOITE'] as $turno) {
            $turnoData = $dataByShift->get($turno, collect());
            $result['shifts'][$turno] = [
                'count' => $turnoData->count(),
                $chave => $turnoData->map(fn ($item) => $this->formatNutricao($item))->values()->toArray(),
            ];
        }

        return $result;
    }
}
