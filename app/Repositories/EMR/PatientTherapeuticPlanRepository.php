<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Fetches the complete therapeutic plan for a patient using separate query methods
 * per category (not UNION ALL). Includes gasotherapy and dialysis.
 *
 * Categories returned:
 *   medications   – CPOE_MATERIAL (active only, with NR_PRESCRICAO)
 *   nutrition     – CPOE_DIETA (dietary / enteral / fasting prescriptions)
 *   orders        – CPOE_RECOMENDACAO (standing medical orders)
 *   interventions – CPOE_INTERVENCAO (nursing care interventions)
 *   procedures    – PRESCR_PROCEDIMENTO UNION AGENDA_PACIENTE (exams + surgeries as procedures)
 *   hemotherapy   – CPOE_HEMOTERAPIA (blood product orders)
 *   surgery       – AGENDA_PACIENTE (future surgeries)
 *   chemotherapy  – AGENDA_QUIMIOTERAPIA_PEP_V (upcoming chemo sessions)
 *   gasotherapy   – CPOE_GASOTERAPIA (active gas therapy)
 *   dialysis      – CPOE_DIALISE (active dialysis)
 */
class PatientTherapeuticPlanRepository
{
    // ==================== PUBLIC API ====================

    public function getTherapeuticPlan(int $attendanceNumber): array
    {
        $nr = $attendanceNumber;

        $medRows   = DB::connection('tasy')->select($this->medicationsQuery(),   [':nr' => $nr]);
        $nutRows   = DB::connection('tasy')->select($this->nutritionQuery(),     [':nr' => $nr]);
        $ordRows   = DB::connection('tasy')->select($this->ordersQuery(),        [':nr' => $nr]);
        $intRows   = DB::connection('tasy')->select($this->interventionsQuery(), [':nr' => $nr]);
        $procRows  = DB::connection('tasy')->select($this->proceduresQuery(),    [
            ':nr_proc'       => $nr,
            ':nr_surg'       => $nr,
            ':nr_surg_setor' => $nr,
        ]);
        $hemoRows  = DB::connection('tasy')->select($this->hemotherapyQuery(),   [':nr' => $nr]);
        $surgRows  = DB::connection('tasy')->select($this->surgeryQuery(),       [
            ':nr'       => $nr,
            ':nr_setor' => $nr,
        ]);
        $chemoRows = DB::connection('tasy')->select($this->chemotherapyQuery(),  [':nr' => $nr]);
        $gasRows   = DB::connection('tasy')->select($this->gasotherapyQuery(),   [':nr' => $nr]);
        $dialRows  = DB::connection('tasy')->select($this->dialysisQuery(),      [':nr' => $nr]);

        $formattedMeds  = collect($medRows)->map(fn($r) => $this->formatMedication($r))->values()->all();
        $formattedNut   = $this->organizeNutritionByShift(collect($nutRows));
        $formattedOrd   = collect($ordRows)->map(fn($r) => $this->formatOrder($r))->values()->all();
        $formattedInt   = collect($intRows)->map(fn($r) => $this->formatIntervention($r))->values()->all();
        $formattedProc  = collect($procRows)->map(fn($r) => $this->formatProcedure($r))->values()->all();
        $formattedHemo  = collect($hemoRows)->map(fn($r) => $this->formatHemotherapy($r))->values()->all();
        $formattedSurg  = collect($surgRows)->map(fn($r) => $this->formatSurgery($r))->values()->all();
        $formattedChemo = collect($chemoRows)->map(fn($r) => $this->formatChemotherapy($r))->values()->all();
        $formattedGas   = collect($gasRows)->map(fn($r) => $this->formatGasotherapy($r))->values()->all();
        $formattedDial  = collect($dialRows)->map(fn($r) => $this->formatDialysis($r))->values()->all();

        return [
            'medications'   => ['count' => count($formattedMeds),  'items' => $formattedMeds],
            'nutrition'     => $formattedNut,
            'orders'        => ['count' => count($formattedOrd),   'items' => $formattedOrd],
            'interventions' => ['count' => count($formattedInt),   'items' => $formattedInt],
            'procedures'    => ['count' => count($formattedProc),  'items' => $formattedProc],
            'hemotherapy'   => ['count' => count($formattedHemo),  'items' => $formattedHemo],
            'surgery'       => ['count' => count($formattedSurg),  'items' => $formattedSurg],
            'chemotherapy'  => ['count' => count($formattedChemo), 'items' => $formattedChemo],
            'gasotherapy'   => ['count' => count($formattedGas),   'items' => $formattedGas],
            'dialysis'      => ['count' => count($formattedDial),  'items' => $formattedDial],
        ];
    }

    // ==================== QUERY BUILDERS ====================

    /**
     * Correlated subquery: CPOE tables store a CPF/doc in NM_USUARIO.
     * Join path: USUARIO.NM_USUARIO = cpoe.NM_USUARIO → PESSOA_FISICA.CD_PESSOA_FISICA
     */
    private function nameFromCpoeUser(string $nmUsuarioCol): string
    {
        return "(SELECT MAX(pf2.NM_PESSOA_FISICA)
                  FROM tasy.USUARIO u2
                  JOIN tasy.PESSOA_FISICA pf2 ON pf2.CD_PESSOA_FISICA = u2.CD_PESSOA_FISICA
                  WHERE u2.NM_USUARIO = {$nmUsuarioCol})";
    }

    /**
     * Correlated subquery: PRESCR_MEDICA has CD_PRESCRITOR = PESSOA_FISICA.CD_PESSOA_FISICA (direct).
     */
    private function nameFromPrescritor(string $cdPrescritorCol): string
    {
        return "(SELECT MAX(pf2.NM_PESSOA_FISICA)
                  FROM tasy.PESSOA_FISICA pf2
                  WHERE pf2.CD_PESSOA_FISICA = {$cdPrescritorCol})";
    }

    private function medicationsQuery(): string
    {
        return "
            SELECT
                cm.NR_SEQUENCIA                                 AS id,
                SUBSTR(mat.ds_material, 1, 255)                 AS name,
                cm.CD_INTERVALO                                 AS secondary_info,
                cm.DS_HORARIOS                                  AS schedule,
                cm.HR_PRIM_HORARIO                              AS first_hour,
                cm.DT_INICIO                                    AS dt_start,
                cm.DT_FIM                                       AS dt_end,
                cm.DT_SUSPENSAO                                 AS dt_suspended,
                cm.IE_ADMINISTRACAO                             AS flag1,
                NVL(mf.ie_antimicrobiano, 'N')                  AS flag2,
                TO_CHAR(cm.NR_DIA_UTIL)                         AS flag3,
                CAST(NULL AS VARCHAR2(10))                       AS flag4,
                cm.DS_OBSERVACAO                                AS observation,
                cm.DS_JUSTIFICATIVA                             AS note_text,
                SUBSTR(pf.nm_pessoa_fisica, 1, 100)             AS professional_name,
                TO_CHAR(cm.QT_DOSE)                             AS qty,
                cm.CD_UNIDADE_MEDIDA                            AS unit_measure,
                va.ds_via_aplicacao                             AS route,
                NVL(ds.total_hoje, 0)                           AS num1,
                NVL(ds.checadas, 0)                             AS num2,
                CAST(NULL AS VARCHAR2(255))                      AS diluent_name,
                CAST(NULL AS VARCHAR2(255))                      AS diluent_qty,
                CAST(NULL AS VARCHAR2(50))                       AS diluent_unit,
                CAST(NULL AS VARCHAR2(500))                      AS prep_notes,
                CAST(NULL AS VARCHAR2(20))                       AS med_volume,
                CAST(NULL AS VARCHAR2(20))                       AS infusion_min,
                CAST(NULL AS VARCHAR2(1))                        AS flag5,
                CAST(NULL AS VARCHAR2(1))                        AS flag6,
                CAST(NULL AS VARCHAR2(255))                      AS extra1,
                CAST(NULL AS VARCHAR2(255))                      AS extra2,
                CAST(NULL AS VARCHAR2(1))                        AS extra3,
                (SELECT MAX(pm.NR_PRESCRICAO)
                 FROM tasy.PRESCR_MATERIAL pm
                 WHERE pm.NR_SEQ_MAT_CPOE = cm.NR_SEQUENCIA)    AS nr_prescricao
            FROM tasy.CPOE_MATERIAL cm
            LEFT JOIN tasy.material mat
                   ON cm.CD_MATERIAL       = mat.cd_material
                 LEFT JOIN tasy.material m_stock
                     ON m_stock.cd_material  = mat.cd_material_estoque
                 LEFT JOIN tasy.medic_ficha_tecnica mf
                     ON mf.nr_sequencia      = m_stock.nr_seq_ficha_tecnica
            LEFT JOIN tasy.via_aplicacao va
                   ON cm.IE_VIA_APLICACAO  = va.ie_via_aplicacao
                  AND va.ie_situacao       = 'A'
            LEFT JOIN tasy.pessoa_fisica pf
                   ON cm.CD_PESSOA_FISICA  = pf.cd_pessoa_fisica
            LEFT JOIN (
                SELECT pmt.NR_SEQ_MAT_CPOE,
                       COUNT(pmh.NR_SEQUENCIA)                                      AS total_hoje,
                       SUM(CASE WHEN pmh.DT_CHECAGEM IS NOT NULL THEN 1 ELSE 0 END) AS checadas
                FROM tasy.PRESCR_MATERIAL pmt
                JOIN tasy.PRESCR_MAT_HOR pmh
                     ON pmh.NR_PRESCRICAO   = pmt.NR_PRESCRICAO
                    AND pmh.NR_SEQ_MATERIAL = pmt.NR_SEQUENCIA
                WHERE TRUNC(pmh.DT_HORARIO) = TRUNC(SYSDATE)
                  AND pmt.NR_SEQ_MAT_CPOE   IS NOT NULL
                  AND pmh.DT_SUSPENSAO      IS NULL
                GROUP BY pmt.NR_SEQ_MAT_CPOE
            ) ds ON ds.NR_SEQ_MAT_CPOE = cm.NR_SEQUENCIA
            WHERE cm.NR_ATENDIMENTO   = :nr
              AND cm.DT_LIBERACAO     IS NOT NULL
              AND cm.DT_SUSPENSAO     IS NULL
              AND TRUNC(cm.DT_INICIO) <= TRUNC(SYSDATE)
              AND (cm.DT_FIM IS NULL OR TRUNC(cm.DT_FIM) >= TRUNC(SYSDATE))
            ORDER BY nr_prescricao NULLS LAST, cm.NR_SEQUENCIA ASC
        ";
    }

    private function nutritionQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('cd.NM_USUARIO');
        return "
            SELECT
                cd.NR_SEQUENCIA                            AS id,
                NVL(d.DS_DIETA, SUBSTR(mat.ds_material, 1, 255)) AS name,
                cd.DS_HORARIOS                             AS schedule,
                cd.HR_PRIM_HORARIO                         AS first_hour,
                cd.DT_INICIO                               AS dt_start,
                cd.DT_FIM                                  AS dt_end,
                cd.IE_TIPO_DIETA                           AS flag1,
                cd.IE_JEJUM                                AS flag2,
                cd.IE_DIETA_ENTERAL                        AS flag3,
                cd.IE_DIETA_ESPECIAL                       AS flag4,
                cd.DS_OBSERVACAO                           AS observation,
                cd.DS_ALERGIAS_ALIMENTARES                 AS note_text,
                {$prescriberName}                          AS professional_name,
                TO_CHAR(cd.QT_VOLUME)                      AS qty,
                NULL                                       AS unit_measure,
                CASE
                    WHEN cd.HR_PRIM_HORARIO IS NOT NULL
                     AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) >= 7
                     AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) <  13 THEN 'MORNING'
                    WHEN cd.HR_PRIM_HORARIO IS NOT NULL
                     AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) >= 13
                     AND TO_NUMBER(SUBSTR(cd.HR_PRIM_HORARIO, 1, 2)) <  19 THEN 'AFTERNOON'
                    ELSE 'NIGHT'
                END                                        AS shift
            FROM tasy.CPOE_DIETA cd
            LEFT JOIN tasy.dieta d
                   ON cd.CD_DIETA         = d.CD_DIETA
            LEFT JOIN tasy.material mat
                   ON cd.CD_MATERIAL      = mat.cd_material
            WHERE cd.NR_ATENDIMENTO = :nr
              AND cd.DT_LIBERACAO   IS NOT NULL
              AND cd.DT_SUSPENSAO   IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(cd.DT_INICIO) AND NVL(TRUNC(cd.DT_FIM), TRUNC(SYSDATE))
              AND cd.NR_SEQUENCIA   = (
                  SELECT MAX(cd2.NR_SEQUENCIA)
                  FROM tasy.CPOE_DIETA cd2
                  WHERE cd2.NR_ATENDIMENTO         = cd.NR_ATENDIMENTO
                    AND NVL(cd2.CD_DIETA,     0)   = NVL(cd.CD_DIETA,     0)
                    AND NVL(cd2.CD_MATERIAL,  0)   = NVL(cd.CD_MATERIAL,  0)
                    AND cd2.DT_LIBERACAO            IS NOT NULL
                    AND cd2.DT_SUSPENSAO            IS NULL
                    AND TRUNC(SYSDATE) BETWEEN TRUNC(cd2.DT_INICIO) AND NVL(TRUNC(cd2.DT_FIM), TRUNC(SYSDATE))
              )
        ";
    }

    private function ordersQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('cr.NM_USUARIO');
        return "
            SELECT
                cr.NR_SEQUENCIA                            AS id,
                NVL(cr.DS_RECOMENDACAO, tr.DS_TIPO_RECOMENDACAO) AS name,
                tr.DS_TIPO_RECOMENDACAO                    AS secondary_info,
                cr.DS_HORARIOS                             AS schedule,
                cr.HR_PRIM_HORARIO                         AS first_hour,
                cr.DT_INICIO                               AS dt_start,
                cr.DT_FIM                                  AS dt_end,
                cr.IE_URGENCIA                             AS flag1,
                cr.DS_OBSERVACAO                           AS observation,
                {$prescriberName}                          AS professional_name
            FROM tasy.CPOE_RECOMENDACAO cr
            LEFT JOIN tasy.TIPO_RECOMENDACAO tr
                   ON cr.CD_RECOMENDACAO  = tr.CD_TIPO_RECOMENDACAO
            WHERE cr.NR_ATENDIMENTO = :nr
              AND cr.DT_LIBERACAO   IS NOT NULL
              AND cr.DT_SUSPENSAO   IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(cr.DT_INICIO) AND NVL(TRUNC(cr.DT_FIM), TRUNC(SYSDATE))
              AND (
                  cr.CD_RECOMENDACAO IS NULL
                  OR cr.NR_SEQUENCIA = (
                      SELECT MAX(cr2.NR_SEQUENCIA)
                      FROM tasy.CPOE_RECOMENDACAO cr2
                      WHERE cr2.NR_ATENDIMENTO  = cr.NR_ATENDIMENTO
                        AND cr2.CD_RECOMENDACAO = cr.CD_RECOMENDACAO
                        AND cr2.DT_LIBERACAO    IS NOT NULL
                        AND cr2.DT_SUSPENSAO    IS NULL
                        AND TRUNC(SYSDATE) BETWEEN TRUNC(cr2.DT_INICIO) AND NVL(TRUNC(cr2.DT_FIM), TRUNC(SYSDATE))
                  )
              )
        ";
    }

    private function interventionsQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('ci.NM_USUARIO');
        return "
            SELECT
                ci.NR_SEQUENCIA                            AS id,
                (SELECT MAX(pr.DS_PROCEDIMENTO)
                 FROM tasy.PE_PROCEDIMENTO pr
                 WHERE pr.NR_SEQUENCIA = ci.NR_SEQ_PROC)  AS name,
                ci.DS_HORARIOS                             AS schedule,
                ci.HR_PRIM_HORARIO                         AS first_hour,
                ci.DT_INICIO                               AS dt_start,
                ci.DT_FIM                                  AS dt_end,
                ci.IE_URGENCIA                             AS flag1,
                ci.IE_SE_NECESSARIO                        AS flag2,
                ci.IE_ACM                                  AS flag3,
                ci.IE_LADO                                 AS flag4,
                ci.DS_OBSERVACAO                           AS observation,
                {$prescriberName}                          AS professional_name
            FROM tasy.CPOE_INTERVENCAO ci
            WHERE ci.NR_ATENDIMENTO = :nr
              AND ci.DT_LIBERACAO   IS NOT NULL
              AND ci.DT_SUSPENSAO   IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(ci.DT_INICIO) AND NVL(TRUNC(ci.DT_FIM), TRUNC(SYSDATE))
              AND ci.NR_SEQUENCIA   = (
                  SELECT MAX(ci2.NR_SEQUENCIA)
                  FROM tasy.CPOE_INTERVENCAO ci2
                  WHERE ci2.NR_ATENDIMENTO = ci.NR_ATENDIMENTO
                    AND ci2.NR_SEQ_PROC    = ci.NR_SEQ_PROC
                    AND ci2.DT_LIBERACAO   IS NOT NULL
                    AND ci2.DT_SUSPENSAO   IS NULL
                    AND TRUNC(SYSDATE) BETWEEN TRUNC(ci2.DT_INICIO) AND NVL(TRUNC(ci2.DT_FIM), TRUNC(SYSDATE))
              )
        ";
    }

    private function proceduresQuery(): string
    {
        $prescriberName = $this->nameFromPrescritor('pm.CD_PRESCRITOR');
        return "
            SELECT
                pp.NR_SEQUENCIA                             AS id,
                COALESCE(
                    (SELECT MAX(pi.DS_PROC_EXAME)
                     FROM tasy.PROC_INTERNO pi
                     WHERE pi.NR_SEQUENCIA = pp.NR_SEQ_PROC_INTERNO),
                    (SELECT MAX(proc.DS_PROCEDIMENTO)
                     FROM tasy.PROCEDIMENTO proc
                     WHERE proc.CD_PROCEDIMENTO  = pp.CD_PROCEDIMENTO
                       AND proc.IE_ORIGEM_PROCED = pp.IE_ORIGEM_PROCED)
                )                                           AS name,
                'PRESCRICAO'                                AS origem,
                pp.IE_STATUS_EXECUCAO                       AS status_raw,
                TO_CHAR(pp.DT_PREV_EXECUCAO, 'DD/MM/YY HH24:MI') AS scheduled,
                pp.DT_PREV_EXECUCAO                         AS scheduled_raw,
                (SELECT tasy.OBTER_VALOR_DOMINIO(95, proc2.CD_TIPO_PROCEDIMENTO)
                 FROM tasy.PROCEDIMENTO proc2
                 WHERE proc2.CD_PROCEDIMENTO  = pp.CD_PROCEDIMENTO
                   AND proc2.IE_ORIGEM_PROCED = pp.IE_ORIGEM_PROCED
                   AND ROWNUM = 1)                          AS tipo,
                {$prescriberName}                           AS professional_name,
                pm.NR_PRESCRICAO                            AS nr_prescricao,
                pp.DT_BAIXA                                 AS dt_baixa,
                CAST(NULL AS NUMBER)                        AS setor_raw,
                CAST(NULL AS VARCHAR2(255))                 AS setor_desc_raw,
                CASE WHEN TRUNC(pp.DT_PREV_EXECUCAO) = TRUNC(SYSDATE)     THEN 1 ELSE 0 END AS is_today,
                CASE WHEN TRUNC(pp.DT_PREV_EXECUCAO) = TRUNC(SYSDATE - 1) THEN 1 ELSE 0 END AS is_yesterday,
                CASE WHEN TRUNC(pp.DT_PREV_EXECUCAO) = TRUNC(SYSDATE + 1) THEN 1 ELSE 0 END AS is_tomorrow
            FROM tasy.PRESCR_PROCEDIMENTO pp
            JOIN tasy.PRESCR_MEDICA pm
              ON pm.NR_PRESCRICAO = pp.NR_PRESCRICAO
            WHERE pm.NR_ATENDIMENTO = :nr_proc
              AND pm.DT_LIBERACAO   IS NOT NULL
              AND pm.DT_SUSPENSAO   IS NULL
              AND pp.DT_BAIXA       IS NULL
              AND pp.IE_STATUS_EXECUCAO NOT IN ('40', 'R', 'C')
                            AND (
                                    pp.DT_PREV_EXECUCAO IS NOT NULL
                                    OR
                                    pp.DT_PREV_EXECUCAO IS NULL
                            )

            UNION ALL

            SELECT
                ap.NR_SEQUENCIA                             AS id,
                COALESCE(
                    (SELECT MAX(pi.DS_PROC_EXAME)
                     FROM tasy.PROC_INTERNO pi
                     WHERE pi.NR_SEQUENCIA = ap.NR_SEQ_PROC_INTERNO),
                    (SELECT MAX(proc.DS_PROCEDIMENTO)
                     FROM tasy.PROCEDIMENTO proc
                     WHERE proc.CD_PROCEDIMENTO  = ap.CD_PROCEDIMENTO
                       AND proc.IE_ORIGEM_PROCED = ap.IE_ORIGEM_PROCED),
                    ap.DS_OBSERVACAO
                )                                           AS name,
                'AGENDAMENTO'                               AS origem,
                ap.IE_STATUS_AGENDA                         AS status_raw,
                TO_CHAR(ap.DT_AGENDA, 'DD/MM/YY')
                    || CASE WHEN ap.HR_INICIO IS NOT NULL
                       THEN ' ' || SUBSTR(TO_CHAR(ap.HR_INICIO, 'HH24:MI'), 1, 5)
                       ELSE '' END                          AS scheduled,
                ap.DT_AGENDA                                AS scheduled_raw,
                NULL                                        AS tipo,
                NULL                                        AS professional_name,
                NULL                                        AS nr_prescricao,
                CAST(NULL AS DATE)                          AS dt_baixa,
                NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO) AS setor_raw,
                tasy.OBTER_DS_SETOR_ATENDIMENTO(NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO)) AS setor_desc_raw,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE)     THEN 1 ELSE 0 END AS is_today,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE - 1) THEN 1 ELSE 0 END AS is_yesterday,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE + 1) THEN 1 ELSE 0 END AS is_tomorrow
            FROM tasy.AGENDA_PACIENTE ap
            WHERE ap.NR_ATENDIMENTO      = :nr_surg
              AND ap.IE_CARATER_CIRURGIA IS NULL
              AND ap.DT_EXECUTADA        IS NULL
              AND ap.IE_STATUS_AGENDA    NOT IN ('C', 'S')
                            AND NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO) = (
                                                                                SELECT MAX(ua2.CD_SETOR_ATENDIMENTO)
                                                                                FROM tasy.UNIDADE_ATENDIMENTO ua2
                                                                                WHERE ua2.NR_ATENDIMENTO = :nr_surg_setor
                                                                                    AND ua2.IE_SITUACAO = 'A'
                            )
                            AND (ap.NR_SEQ_PROC_INTERNO IS NOT NULL OR ap.CD_PROCEDIMENTO IS NOT NULL)
        ";
    }

    private function hemotherapyQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('ch.NM_USUARIO');
        return "
            SELECT
                ch.NR_SEQUENCIA                            AS id,
                CASE ch.IE_TIPO_HEMOTERAP
                    WHEN '1' THEN 'Concentrado de Hemácias'
                    WHEN '2' THEN 'Concentrado de Plaquetas'
                    WHEN '3' THEN 'Plasma Fresco Congelado'
                    WHEN '4' THEN 'Crioprecipitado'
                    WHEN '5' THEN 'Concentrado de Granulócitos'
                    ELSE 'Hemocomponente'
                END                                        AS name,
                ch.IE_TIPO_HEMOTERAP                       AS secondary_info,
                ch.DS_HORARIOS                             AS schedule,
                ch.DT_INICIO                               AS dt_start,
                ch.DT_FIM                                  AS dt_end,
                ch.IE_URGENCIA                             AS flag1,
                ch.DS_OBSERVACAO                           AS observation,
                {$prescriberName}                          AS professional_name,
                TO_CHAR(ch.QT_VOL_HEMOCOMP)               AS qty,
                                ch.IE_UNID_MED_HEMO                        AS unit_measure,
                                ua.CD_SETOR_ATENDIMENTO                    AS setor_raw,
                                tasy.OBTER_DS_SETOR_ATENDIMENTO(ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw
            FROM tasy.CPOE_HEMOTERAPIA ch
                        JOIN tasy.UNIDADE_ATENDIMENTO ua
                            ON ua.NR_ATENDIMENTO = ch.NR_ATENDIMENTO
                         AND ua.IE_SITUACAO    = 'A'
            WHERE ch.NR_ATENDIMENTO = :nr
              AND ch.DT_LIBERACAO   IS NOT NULL
              AND ch.DT_SUSPENSAO   IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(ch.DT_INICIO) AND NVL(TRUNC(ch.DT_FIM), TRUNC(SYSDATE))
              AND ch.NR_SEQUENCIA   = (
                  SELECT MAX(ch2.NR_SEQUENCIA)
                  FROM tasy.CPOE_HEMOTERAPIA ch2
                  WHERE ch2.NR_ATENDIMENTO    = ch.NR_ATENDIMENTO
                    AND ch2.IE_TIPO_HEMOTERAP = ch.IE_TIPO_HEMOTERAP
                    AND ch2.DT_LIBERACAO       IS NOT NULL
                    AND ch2.DT_SUSPENSAO       IS NULL
                    AND TRUNC(SYSDATE) BETWEEN TRUNC(ch2.DT_INICIO) AND NVL(TRUNC(ch2.DT_FIM), TRUNC(SYSDATE))
              )
        ";
    }

    private function surgeryQuery(): string
    {
        return "
            SELECT
                ap.NR_SEQUENCIA                                    AS id,
                COALESCE(
                    (SELECT MAX(pi.DS_PROC_EXAME)
                     FROM tasy.proc_interno pi
                     WHERE pi.NR_SEQUENCIA = ap.NR_SEQ_PROC_INTERNO),
                    ap.DS_CIRURGIA,
                    'Cirurgia não especificada'
                )                                                  AS name,
                ap.IE_CARATER_CIRURGIA                             AS flag1,
                ap.IE_STATUS_AGENDA                                AS status_raw,
                NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO) AS setor_raw,
                tasy.OBTER_DS_SETOR_ATENDIMENTO(NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO)) AS setor_desc_raw,
                TO_CHAR(ap.DT_AGENDA, 'DD/MM/YY')
                    || CASE WHEN ap.HR_INICIO IS NOT NULL
                       THEN ' ' || SUBSTR(TO_CHAR(ap.HR_INICIO,'HH24:MI'), 1, 5)
                       ELSE '' END                                 AS schedule,
                ap.DT_AGENDA                                       AS dt_start,
                ap.DS_OBSERVACAO                                   AS observation,
                TO_CHAR(ap.NR_SEQ_SALA)                            AS extra1,
                ap.DS_CIRURGIA                                     AS extra2
            FROM tasy.AGENDA_PACIENTE ap
            WHERE ap.NR_ATENDIMENTO        = :nr
              AND ap.DT_AGENDA             >= TRUNC(SYSDATE)
              AND ap.IE_CARATER_CIRURGIA   IS NOT NULL
              AND ap.IE_CARATER_CIRURGIA   <> 'X'
              AND ap.IE_STATUS_AGENDA      NOT IN ('C', 'S')
              AND ap.DT_EXECUTADA          IS NULL
              AND NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO) = (
                  SELECT MAX(ua2.CD_SETOR_ATENDIMENTO)
                  FROM tasy.UNIDADE_ATENDIMENTO ua2
                  WHERE ua2.NR_ATENDIMENTO = :nr_setor
                    AND ua2.IE_SITUACAO = 'A'
              )
        ";
    }

    private function chemotherapyQuery(): string
    {
        return "
            SELECT
                ROWNUM                                             AS id,
                COALESCE(
                    'Quimioterapia' || CASE WHEN aq.DS_PROTOCOLO_MEDIC IS NOT NULL
                        THEN ' – ' || aq.DS_PROTOCOLO_MEDIC ELSE '' END,
                    'Quimioterapia'
                )                                                  AS name,
                CASE WHEN aq.NR_CICLO IS NOT NULL
                     THEN 'Ciclo ' || TO_CHAR(aq.NR_CICLO)
                     ELSE NULL END                                 AS secondary_info,
                TO_CHAR(aq.DT_AGENDA, 'DD/MM/YY HH24:MI')         AS schedule,
                aq.DT_AGENDA                                       AS dt_start,
                aq.DS_LOCAL                                        AS observation,
                aq.NM_MEDICO_RESP                                  AS professional_name,
                aq.DS_PROTOCOLO_MEDIC                              AS extra1,
                TO_CHAR(aq.NR_CICLO)                               AS extra2,
                ua.CD_SETOR_ATENDIMENTO                            AS setor_raw,
                tasy.OBTER_DS_SETOR_ATENDIMENTO(ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw
            FROM tasy.AGENDA_QUIMIOTERAPIA_PEP_V aq
            JOIN tasy.ATENDIMENTO_PACIENTE ap2
              ON ap2.CD_PESSOA_FISICA = aq.CD_PESSOA_FISICA
             AND ap2.NR_ATENDIMENTO   = :nr
            JOIN tasy.UNIDADE_ATENDIMENTO ua
              ON ua.NR_ATENDIMENTO = ap2.NR_ATENDIMENTO
             AND ua.IE_SITUACAO    = 'A'
            WHERE 1 = 1
              AND aq.DT_AGENDA BETWEEN SYSDATE AND SYSDATE + 30
        ";
    }

    private function gasotherapyQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('cg.NM_USUARIO');
        return "
            SELECT
                cg.NR_SEQUENCIA                            AS id,
                (SELECT MAX(g.DS_GAS)
                 FROM tasy.GAS g
                 WHERE g.NR_SEQUENCIA = cg.NR_SEQ_GAS)     AS tipo_gas,
                cg.CD_MODALIDADE_VENT                      AS modalidade,
                cg.IE_MODO_ADM                             AS modo_administracao,
                TO_CHAR(cg.QT_GASOTERAPIA)                 AS quantidade,
                cg.IE_UNIDADE_MEDIDA                       AS unidade,
                TO_CHAR(cg.QT_FIO2)                        AS fio2,
                TO_CHAR(cg.QT_FLUXO_INSP)                  AS fluxo_inspiratorio,
                TO_CHAR(cg.QT_PIP)                         AS pip,
                TO_CHAR(cg.QT_PEEP)                        AS peep,
                TO_CHAR(cg.QT_VC_PROG)                     AS volume_corrente,
                TO_CHAR(cg.QT_FREQ_VENT)                   AS freq_ventilatoria,
                TO_CHAR(cg.QT_PS)                          AS pressao_suporte,
                (SELECT MAX(m1.DS_MATERIAL)
                 FROM tasy.MATERIAL m1
                 WHERE m1.CD_MATERIAL = cg.CD_MAT_EQUIP1)  AS equipamento_1,
                (SELECT MAX(m2.DS_MATERIAL)
                 FROM tasy.MATERIAL m2
                 WHERE m2.CD_MATERIAL = cg.CD_MAT_EQUIP2)  AS equipamento_2,
                (SELECT MAX(m3.DS_MATERIAL)
                 FROM tasy.MATERIAL m3
                 WHERE m3.CD_MATERIAL = cg.CD_MAT_EQUIP3)  AS equipamento_3,
                cg.DS_HORARIOS                             AS horarios,
                cg.DT_INICIO                               AS dt_inicio,
                cg.DT_FIM                                  AS dt_fim,
                cg.IE_URGENCIA                             AS urgente,
                cg.IE_SE_NECESSARIO                        AS se_necessario,
                cg.IE_ACM                                  AS a_criterio_medico,
                cg.DS_OBSERVACAO                           AS observacao,
                cg.DS_JUSTIFICATIVA                        AS justificativa,
                {$prescriberName}                          AS professional_name
            FROM tasy.CPOE_GASOTERAPIA cg
            WHERE cg.NR_ATENDIMENTO = :nr
              AND cg.DT_LIBERACAO   IS NOT NULL
              AND cg.DT_SUSPENSAO   IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(cg.DT_INICIO) AND NVL(TRUNC(cg.DT_FIM), TRUNC(SYSDATE))
            ORDER BY cg.NR_SEQUENCIA ASC
        ";
    }

    private function dialysisQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('cd.NM_USUARIO');
        return "
            SELECT
                cd.NR_SEQUENCIA                            AS id,
                cd.IE_HEMODIALISE                          AS ie_hemodialise,
                cd.QT_SESSAO_SEM                           AS sessoes_por_semana,
                cd.QT_HORA_MIN_SESSAO                      AS duracao_sessao,
                cd.IE_SEGUNDA                              AS dia_seg,
                cd.IE_TERCA                                AS dia_ter,
                cd.IE_QUARTA                               AS dia_qua,
                cd.IE_QUINTA                               AS dia_qui,
                cd.IE_SEXTA                                AS dia_sex,
                cd.IE_SABADO                               AS dia_sab,
                cd.IE_DOMINGO                              AS dia_dom,
                TO_CHAR(cd.QT_FLUXO_SANGUE)               AS fluxo_sangue,
                TO_CHAR(cd.QT_KTV)                         AS ktv,
                TO_CHAR(cd.QT_ULTRAFILTRACAO)              AS ultrafiltracao,
                cd.DS_HORARIOS                             AS horarios,
                cd.DT_INICIO                               AS dt_inicio,
                cd.DT_FIM                                  AS dt_fim,
                cd.DS_OBSERVACAO                           AS observacao,
                cd.DS_JUSTIFICATIVA                        AS justificativa,
                {$prescriberName}                          AS professional_name
            FROM tasy.CPOE_DIALISE cd
            WHERE cd.NR_ATENDIMENTO = :nr
              AND cd.DT_LIBERACAO   IS NOT NULL
              AND cd.DT_SUSPENSAO   IS NULL
              AND TRUNC(SYSDATE) BETWEEN TRUNC(cd.DT_INICIO) AND NVL(TRUNC(cd.DT_FIM), TRUNC(SYSDATE))
            ORDER BY cd.NR_SEQUENCIA ASC
        ";
    }

    // ==================== ITEM FORMATTERS ====================

    private function formatMedication(object $row): array
    {
        $totalDoses   = (int) ($row->num1 ?? 0);
        $checkedDoses = (int) ($row->num2 ?? 0);

        // Status: always 'active' since we filter DT_SUSPENSAO IS NULL at query level
        $status = 'active';

        $doseDisplay = trim(($row->qty ?? '') . ' ' . ($row->unit_measure ?? ''));

        $isAntibiotic   = ($row->flag2 ?? '') === 'S';
        $antibioticDay  = !empty($row->flag3) ? (int) $row->flag3 : null;
        $antibioticDays = !empty($row->flag4) ? (int) $row->flag4 : null;

        $hasDialuent    = !empty($row->diluent_name);
        $diluentDisplay = $hasDialuent
            ? trim($row->diluent_name . (!empty($row->diluent_qty) ? ' – ' . $row->diluent_qty : ''))
            : null;

        $hasPrepNotes = !empty($row->prep_notes);
        $hasObs       = !empty($row->observation);
        $hasJust      = !empty($row->note_text);

        $volume      = !empty($row->med_volume) ? trim($row->med_volume) . ' mL' : null;
        $infusionMin = !empty($row->infusion_min) ? (int) $row->infusion_min : null;

        // NR_PRESCRICAO from subquery
        $nrPrescricao = isset($row->nr_prescricao) && $row->nr_prescricao !== null
            ? (int) $row->nr_prescricao
            : null;

        return [
            'id'              => (int) ($row->id ?? 0),
            'name'            => $row->name ?? 'Medicamento não identificado',
            'dose'            => $doseDisplay ?: null,
            'route'           => $row->route ?? null,
            'frequency'       => $row->secondary_info ?? null,
            'schedule'        => $row->schedule ?? ($row->first_hour ?? null),
            'admin_code'      => $row->flag1 ?? null,
            'is_antibiotic'   => $isAntibiotic,
            'antibiotic_day'  => $antibioticDay,
            'antibiotic_days' => $antibioticDays,
            'diluent'         => $diluentDisplay,
            'volume'          => $volume,
            'infusion_min'    => $infusionMin,
            'prep_notes'      => $hasPrepNotes ? $row->prep_notes : null,
            'dt_start'        => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end'          => $row->dt_end   ? Carbon::parse($row->dt_end)->format('d/m/Y')   : null,
            'status'          => $status,
            'total_doses'     => $totalDoses,
            'checked_doses'   => $checkedDoses,
            'observation'     => $hasObs ? $row->observation : null,
            'justification'   => $hasJust ? $row->note_text  : null,
            'prescriber'      => $row->extra1 ?? ($row->professional_name ?? null),
            'is_high_alert'   => ($row->flag5 ?? '') === 'S',
            'is_controlled'   => ($row->flag6 ?? '') === 'S',
            'drug_class'      => !empty($row->extra2) ? $row->extra2 : null,
            'has_details'     => $hasObs || $hasJust || $hasPrepNotes || $hasDialuent || $infusionMin || $volume,
            'nr_prescricao'   => $nrPrescricao,
        ];
    }

    public function formatOrder(object $row): array
    {
        return [
            'text'        => $row->name ?? 'No description',
            'type'        => $row->secondary_info ?? null,
            'observation' => !empty($row->observation) ? $row->observation : null,
            'schedule'    => $row->schedule ?? ($row->first_hour ?? null),
            'prescriber'  => $row->professional_name ?? null,
            'dt_start'    => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end'      => $row->dt_end   ? Carbon::parse($row->dt_end)->format('d/m/Y')   : null,
            'has_details' => !empty($row->observation),
        ];
    }

    public function formatIntervention(object $row): array
    {
        $labels = [];
        if (($row->flag1 ?? '') === '1') $labels[] = 'Urgent';
        if (($row->flag2 ?? '') === 'S') $labels[] = 'PRN';
        if (($row->flag3 ?? '') === 'S') $labels[] = 'ACM';
        if (!empty($row->flag4)) {
            $sideMap = ['D' => 'Right', 'E' => 'Left', 'B' => 'Bilateral'];
            $labels[] = 'Side: ' . ($sideMap[$row->flag4] ?? $row->flag4);
        }

        return [
            'name'        => $row->name ?? 'Unidentified intervention',
            'observation' => !empty($row->observation) ? $row->observation : null,
            'schedule'    => $row->schedule ?? ($row->first_hour ?? null),
            'assignee'    => $row->professional_name ?? null,
            'prescriber'  => null,
            'labels'      => $labels,
            'dt_start'    => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end'      => $row->dt_end   ? Carbon::parse($row->dt_end)->format('d/m/Y')   : null,
            'has_details' => !empty($row->observation) || !empty($labels),
        ];
    }

    private function formatProcedure(object $row): array
    {
        // For PRESCRICAO rows
        $prescricaoStatusMap = [
            '10' => 'Waiting',
            '20' => 'Collected',
            '30' => 'Analyzing',
            '40' => 'Completed',
            'A'  => 'Scheduled',
            'R'  => 'Done',
        ];

        $origem    = $row->origem ?? 'PRESCRICAO';
        $statusRaw = (string) ($row->status_raw ?? '');
        $status    = 'Pendente';
        if ($origem === 'AGENDAMENTO') {
            $status = $this->agendaStatusLabel($statusRaw);
        } elseif (!empty($row->dt_baixa)) {
            $status = 'Done';
        } elseif ($statusRaw !== '') {
            $status = $prescricaoStatusMap[$statusRaw] ?? 'Pendente';
        }

        $type      = !empty($row->tipo) ? $row->tipo : null;
        if ($origem === 'AGENDAMENTO' && $type === null) {
            $type = 'Cirurgia';
        }
        $isToday   = (int) ($row->is_today ?? 0) === 1;
        $isYest    = (int) ($row->is_yesterday ?? 0) === 1;
        $isTomorrow = (int) ($row->is_tomorrow ?? 0) === 1;
        $isNear    = $isToday || $isYest || $isTomorrow;

        $scheduledRaw = null;
        if (!empty($row->scheduled_raw)) {
            try {
                $scheduledRaw = Carbon::parse($row->scheduled_raw)->format('Y-m-d');
            } catch (\Exception $e) {
                $scheduledRaw = null;
            }
        }

        return [
            'id'           => (int) ($row->id ?? 0),
            'name'         => $row->name ?? 'Procedimento não identificado',
            'origem'       => $origem,
            'type'         => $type,
            'scheduled'    => $row->scheduled ?? null,
            'scheduled_raw'=> $scheduledRaw,
            'status'       => $status,
            'is_today'     => $isToday,
            'is_yesterday' => $isYest,
            'is_tomorrow'  => $isTomorrow,
            'is_near'      => $isNear,
            'sector_code'  => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name'  => !empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'prescriber'   => !empty($row->professional_name) ? $row->professional_name : null,
            'nr_prescricao'=> isset($row->nr_prescricao) && $row->nr_prescricao !== null ? (int) $row->nr_prescricao : null,
        ];
    }

    public function formatHemotherapy(object $row): array
    {
        $volume = null;
        if (!empty($row->qty)) {
            $volume = trim($row->qty . (!empty($row->unit_measure) ? ' ' . $row->unit_measure : ''));
        }

        return [
            'id'          => (int) ($row->id ?? 0),
            'name'        => $row->name ?? 'Hemocomponente',
            'tipo_code'   => $row->secondary_info ?? null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => !empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'is_urgent'   => ($row->flag1 ?? 'N') === 'S',
            'schedule'    => $row->schedule ?? null,
            'volume'      => $volume,
            'observation' => !empty($row->observation) ? $row->observation : null,
            'prescriber'  => $row->professional_name ?? null,
            'dt_start'    => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end'      => $row->dt_end   ? Carbon::parse($row->dt_end)->format('d/m/Y')   : null,
            'has_details' => !empty($row->observation),
        ];
    }

    public function formatSurgery(object $row): array
    {
        $caracterMap = [
            'E' => 'Eletiva',
            'U' => 'Urgência',
            'G' => 'Emergência',
        ];

        $carater   = $caracterMap[$row->flag1 ?? ''] ?? 'Não informado';
        $is_urgent = in_array($row->flag1 ?? '', ['U', 'G']);

        return [
            'id'          => (int) ($row->id ?? 0),
            'name'        => $row->name ?? 'Cirurgia não especificada',
            'carater'     => $carater,
            'status'      => $this->agendaStatusLabel((string) ($row->status_raw ?? '')),
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => !empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'is_urgent'   => $is_urgent,
            'dt'          => $row->schedule ?? null,
            'sala'        => !empty($row->extra1) ? 'Sala ' . $row->extra1 : null,
            'description' => !empty($row->extra2) ? $row->extra2 : null,
            'observation' => !empty($row->observation) ? $row->observation : null,
            'has_details' => !empty($row->observation),
        ];
    }

    private function agendaStatusLabel(?string $code): string
    {
        $map = [
            'A'  => 'Aguardando',
            'AD' => 'Atendido',
            'AE' => 'Aguardando remarcação',
            'AP' => 'Aguardando paciente',
            'AT' => 'Aguardando atendimento',
            'B'  => 'Bloqueada',
            'C'  => 'Cancelada',
            'CN' => 'Confirmada',
            'CR' => 'Cirurgia realizada',
            'E'  => 'Executada',
            'EE' => 'Em exame',
            'EP' => 'Em preparo',
            'F'  => 'Falta justificada',
            'I'  => 'Falta não justificada',
            'II' => 'Inativo',
            'IN' => 'Iniciada',
            'IT' => 'Interrompida',
            'L'  => 'Livre',
            'LF' => 'Livre forçado',
            'N'  => 'Normal',
            'O'  => 'Em Consulta',
            'P'  => 'Paciente internado',
            'PA' => 'Pré-agenda',
            'PH' => 'Paciente chamado',
            'PO' => 'Pós-operatório',
            'PS' => 'Paciente em sala',
            'R'  => 'Reservada',
            'RE' => 'Remarcada',
            'RV' => 'Revisar',
            'S'  => 'Suspenso',
        ];

        $normalized = strtoupper(trim((string) $code));
        return $map[$normalized] ?? ($normalized !== '' ? $normalized : 'Aguardando');
    }

    public function formatChemotherapy(object $row): array
    {
        return [
            'id'          => (int) ($row->id ?? 0),
            'name'        => $row->name ?? 'Quimioterapia',
            'protocol'    => !empty($row->extra1) ? $row->extra1 : null,
            'cycle'       => !empty($row->extra2) ? (int) $row->extra2 : null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => !empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'scheduled'   => $row->schedule ?? null,
            'local'       => !empty($row->observation) ? $row->observation : null,
            'prescriber'  => !empty($row->professional_name) ? $row->professional_name : null,
            'dt_start'    => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y H:i') : null,
            'has_details' => !empty($row->observation) || !empty($row->professional_name),
        ];
    }

    private function formatGasotherapy(object $row): array
    {
        return [
            'id'                 => (int) ($row->id ?? 0),
            'tipo_gas'           => $row->tipo_gas ?? null,
            'modalidade'         => $row->modalidade ?? null,
            'modo_administracao' => $row->modo_administracao ?? null,
            'quantidade'         => $row->quantidade ?? null,
            'unidade'            => $row->unidade ?? null,
            'fio2'               => $row->fio2 ?? null,
            'fluxo_inspiratorio' => $row->fluxo_inspiratorio ?? null,
            'pip'                => $row->pip ?? null,
            'peep'               => $row->peep ?? null,
            'volume_corrente'    => $row->volume_corrente ?? null,
            'freq_ventilatoria'  => $row->freq_ventilatoria ?? null,
            'pressao_suporte'    => $row->pressao_suporte ?? null,
            'equipamento_1'      => $row->equipamento_1 ?? null,
            'equipamento_2'      => $row->equipamento_2 ?? null,
            'equipamento_3'      => $row->equipamento_3 ?? null,
            'horarios'           => $row->horarios ?? null,
            'dt_inicio'          => $row->dt_inicio ? Carbon::parse($row->dt_inicio)->format('d/m/Y') : null,
            'dt_fim'             => $row->dt_fim    ? Carbon::parse($row->dt_fim)->format('d/m/Y')    : null,
            'urgente'            => ($row->urgente ?? 'N') === 'S',
            'se_necessario'      => ($row->se_necessario ?? 'N') === 'S',
            'a_criterio_medico'  => ($row->a_criterio_medico ?? 'N') === 'S',
            'observacao'         => !empty($row->observacao) ? $row->observacao : null,
            'justificativa'      => !empty($row->justificativa) ? $row->justificativa : null,
            'prescriber'         => $row->professional_name ?? null,
        ];
    }

    private function formatDialysis(object $row): array
    {
        $modalidade = ($row->ie_hemodialise ?? 'S') === 'S'
            ? 'Hemodiálise'
            : 'Diálise Peritoneal';

        $diasMap = [
            'dia_seg' => 'Seg',
            'dia_ter' => 'Ter',
            'dia_qua' => 'Qua',
            'dia_qui' => 'Qui',
            'dia_sex' => 'Sex',
            'dia_sab' => 'Sáb',
            'dia_dom' => 'Dom',
        ];
        $diasSemana = [];
        foreach ($diasMap as $col => $label) {
            if (($row->$col ?? 'N') === 'S') {
                $diasSemana[] = $label;
            }
        }

        return [
            'id'                  => (int) ($row->id ?? 0),
            'modalidade'          => $modalidade,
            'sessoes_por_semana'  => $row->sessoes_por_semana ?? null,
            'duracao_sessao'      => $row->duracao_sessao ?? null,
            'dias_semana'         => implode(', ', $diasSemana) ?: null,
            'fluxo_sangue'        => $row->fluxo_sangue ?? null,
            'ktv'                 => $row->ktv ?? null,
            'ultrafiltracao'      => $row->ultrafiltracao ?? null,
            'horarios'            => $row->horarios ?? null,
            'dt_inicio'           => $row->dt_inicio ? Carbon::parse($row->dt_inicio)->format('d/m/Y') : null,
            'dt_fim'              => $row->dt_fim    ? Carbon::parse($row->dt_fim)->format('d/m/Y')    : null,
            'observacao'          => !empty($row->observacao) ? $row->observacao : null,
            'justificativa'       => !empty($row->justificativa) ? $row->justificativa : null,
            'prescriber'          => $row->professional_name ?? null,
        ];
    }

    public function formatNutritionItem(object $row): array
    {
        $isFasting = ($row->flag1 ?? '') === 'J';

        if ($isFasting) {
            $type        = 'Fasting';
            $displayName = 'FASTING';
        } elseif (($row->flag3 ?? '') === 'S') {
            $type        = 'Enteral';
            $displayName = $row->name ?? 'Enteral nutrition';
        } elseif (($row->flag4 ?? '') === 'S') {
            $type        = 'Special';
            $displayName = $row->name ?? 'Special diet';
        } else {
            $type        = 'Diet';
            $displayName = $row->name ?? 'Dietary prescription';
        }

        $volume = null;
        if (!empty($row->qty)) {
            $volume = trim($row->qty . (!empty($row->unit_measure) ? ' ' . $row->unit_measure : ''));
        }

        return [
            'name'        => $displayName,
            'type'        => $type,
            'is_fasting'  => $isFasting,
            'observation' => !empty($row->observation) ? $row->observation : null,
            'allergies'   => !empty($row->note_text)   ? $row->note_text   : null,
            'schedule'    => $row->schedule ?? ($row->first_hour ?? null),
            'shift'       => $row->shift ?? 'NIGHT',
            'volume'      => $volume,
            'prescriber'  => $row->professional_name ?? null,
            'dt_start'    => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end'      => $row->dt_end   ? Carbon::parse($row->dt_end)->format('d/m/Y')   : null,
            'has_details' => !empty($row->observation) || !empty($row->note_text),
        ];
    }

    public function organizeNutritionByShift($nutrition): array
    {
        $items   = $nutrition->map(fn($r) => $this->formatNutritionItem($r))->values()->all();
        $byShift = ['MORNING' => [], 'AFTERNOON' => [], 'NIGHT' => []];

        foreach ($items as $item) {
            $byShift[$item['shift'] ?? 'NIGHT'][] = $item;
        }

        return ['count' => count($items), 'shifts' => $byShift];
    }

    // ==================== DAILY SCHEDULE GRID ====================

    /**
     * Returns per-medication hour slots for a given date using ADEP_V.
     *
     * ADEP_V is the correct source because:
     *  - PRESCR_MAT_HOR.DT_HORARIO stores only the DATE (time always 00:00:00);
     *    the real time is in DS_HORARIO (text) but ADEP_V.DT_HORARIO stores full timestamp.
     *  - ADEP_V preserves full history (PRESCR_MAT_HOR advances pointer after administration).
     *  - IE_EXECUCAO gives the authoritative status per slot.
     *
     * DT_HORARIO is stored in local Brasília time — no UTC conversion needed.
     *
     * Returns: [ med_id => [ 'HH:MI' => 'administered'|'scheduled'|'missed' ], ... ]
     */
    public function getDailyMedicationSchedule(int $attendanceNumber, string $date): array
    {
        $rows = DB::connection('tasy')->select("
            SELECT med_id, time_label, MAX(priority) AS priority
            FROM (
                -- Source A: ADEP_V (executed + pre-scheduled aprazamento)
                SELECT
                    pm.NR_SEQ_MAT_CPOE                          AS med_id,
                    TO_CHAR(a.DT_HORARIO, 'HH24') || ':00'       AS time_label,
                    CASE a.IE_EXECUCAO
                        WHEN 3  THEN 600   -- Administrado
                        WHEN 58 THEN 500   -- Conferido
                        WHEN 8  THEN 400   -- Coletado
                        WHEN 38 THEN 300   -- Recusado
                        WHEN 4  THEN 200   -- Desfeito
                        WHEN 10 THEN  30   -- Reaprazado
                        WHEN 15 THEN  20   -- Aprazado (registro inicial)
                        ELSE           1   -- Pendente / outros
                    END                                          AS priority
                FROM tasy.ADEP_V a
                JOIN tasy.PRESCR_MAT_HOR pmh
                     ON pmh.NR_SEQUENCIA  = a.NR_SEQ_HORARIO
                JOIN tasy.PRESCR_MATERIAL pm
                     ON pm.NR_PRESCRICAO  = pmh.NR_PRESCRICAO
                    AND pm.NR_SEQUENCIA   = pmh.NR_SEQ_MATERIAL
                JOIN tasy.CPOE_MATERIAL cm
                     ON cm.NR_SEQUENCIA   = pm.NR_SEQ_MAT_CPOE
                WHERE cm.NR_ATENDIMENTO   = :att_a
                  AND a.IE_TIPO_ITEM       = 'M'
                  AND pm.NR_SEQ_MAT_CPOE   IS NOT NULL
                  AND TRUNC(a.DT_HORARIO)  = TO_DATE(:date_a, 'YYYY-MM-DD')
                  AND NVL(a.IE_EXECUCAO, 0) NOT IN (5, 12)

                UNION ALL

                -- Source B: PRESCR_MAT_HOR (pending future slots from the base schedule)
                SELECT
                    pm2.NR_SEQ_MAT_CPOE                         AS med_id,
                    SUBSTR(pmh2.DS_HORARIO, 1, 2) || ':00'       AS time_label,
                    1                                            AS priority
                FROM tasy.PRESCR_MAT_HOR pmh2
                JOIN tasy.PRESCR_MATERIAL pm2
                     ON pm2.NR_PRESCRICAO = pmh2.NR_PRESCRICAO
                    AND pm2.NR_SEQUENCIA  = pmh2.NR_SEQ_MATERIAL
                JOIN tasy.CPOE_MATERIAL cm2
                     ON cm2.NR_SEQUENCIA  = pm2.NR_SEQ_MAT_CPOE
                WHERE cm2.NR_ATENDIMENTO  = :att_b
                  AND pm2.NR_SEQ_MAT_CPOE  IS NOT NULL
                  AND TRUNC(pmh2.DT_HORARIO) = TO_DATE(:date_b, 'YYYY-MM-DD')
                  AND pmh2.DS_HORARIO       IS NOT NULL
                  AND LENGTH(TRIM(pmh2.DS_HORARIO)) >= 4
            )
            GROUP BY med_id, time_label
            ORDER BY med_id, time_label
        ", [
            'att_a'  => $attendanceNumber,
            'date_a' => $date,
            'att_b'  => $attendanceNumber,
            'date_b' => $date,
        ]);

        $schedule = [];
        foreach ($rows as $row) {
            $id       = (int) $row->med_id;
            $priority = (int) $row->priority;
            $time     = trim($row->time_label ?? '');

            if ($time && strlen($time) === 4 && $time[0] !== '0') {
                $time = '0' . $time;
            }
            if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) continue;

            $status = match(true) {
                $priority >= 600 => 'administered',
                $priority >= 500 => 'conferido',
                $priority >= 400 => 'coletado',
                $priority >= 300 => 'refused',
                $priority >= 200 => 'undone',
                $priority >= 30  => 'rescheduled',
                default          => 'scheduled',
            };

            $schedule[$id][$time] = $status;
        }

        foreach ($schedule as &$slots) {
            ksort($slots);
        }

        return $schedule;
    }
}
