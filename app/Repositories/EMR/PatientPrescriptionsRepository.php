<?php

namespace App\Repositories\EMR;

use App\Services\Tasy\PrescriptionFormatter;
use Illuminate\Support\Facades\DB;

/**
 * Single-patient prescription data for the patient modal (therapeutic plan tab).
 *
 * Categories:
 *   medications   – CPOE_MATERIAL (active only, with NR_PRESCRICAO)
 *   nutrition     – CPOE_DIETA
 *   orders        – CPOE_RECOMENDACAO
 *   interventions – CPOE_INTERVENCAO
 *   procedures    – PRESCR_PROCEDIMENTO UNION AGENDA_PACIENTE
 *   hemotherapy   – CPOE_HEMOTERAPIA
 *   surgery       – AGENDA_PACIENTE
 *   chemotherapy  – AGENDA_QUIMIOTERAPIA_PEP_V
 *   gasotherapy   – CPOE_GASOTERAPIA
 *   dialysis      – CPOE_DIALISE
 */
class PatientPrescriptionsRepository
{
    public function __construct(private readonly PrescriptionFormatter $formatter) {}

    // ==================== SINGLE-PATIENT API (modal) ====================

    public function getPrescriptions(int $attendanceNumber): array
    {
        $nr = $attendanceNumber;

        $medRows = DB::connection('tasy')->select($this->medicationsQuery(), [':nr' => $nr]);
        $nutRows = DB::connection('tasy')->select($this->nutritionQuery(), [':nr' => $nr]);
        $recRows = DB::connection('tasy')->select($this->recommendationsQuery(), [':nr' => $nr]);
        $intRows = DB::connection('tasy')->select($this->interventionsQuery(), [':nr' => $nr]);
        $procRows = DB::connection('tasy')->select($this->proceduresQuery(), [
            ':nr_proc' => $nr,
            ':nr_surg' => $nr,
            ':nr_surg_setor' => $nr,
        ]);
        $hemoRows = DB::connection('tasy')->select($this->hemotherapyQuery(), [':nr' => $nr]);
        $surgRows = DB::connection('tasy')->select($this->surgeryQuery(), [':nr' => $nr]);
        $chemoRows = DB::connection('tasy')->select($this->chemotherapyQuery(), [':nr' => $nr]);
        $gasRows = DB::connection('tasy')->select($this->gasotherapyQuery(), [':nr' => $nr]);
        $dialRows = DB::connection('tasy')->select($this->dialysisQuery(), [':nr' => $nr]);

        $fmt = $this->formatter;

        $formattedMeds = collect($medRows)->map(fn ($r) => $fmt->formatMedication($r))->values()->all();
        $formattedNut = $fmt->organizeNutritionByShift(collect($nutRows));
        $formattedRec = collect($recRows)->map(fn ($r) => $fmt->formatRecommendation($r))->values()->all();
        $formattedInt = collect($intRows)->map(fn ($r) => $fmt->formatIntervention($r))->values()->all();
        $formattedProc = collect($procRows)->map(fn ($r) => $fmt->formatProcedure($r))->values()->all();
        $formattedHemo = collect($hemoRows)->map(fn ($r) => $fmt->formatHemotherapy($r))->values()->all();
        $formattedSurg = collect($surgRows)->map(fn ($r) => $fmt->formatSurgery($r))->values()->all();
        $formattedChemo = collect($chemoRows)->map(fn ($r) => $fmt->formatChemotherapy($r))->values()->all();
        $formattedGas = collect($gasRows)->map(fn ($r) => $fmt->formatGasotherapy($r))->values()->all();
        $formattedDial = collect($dialRows)->map(fn ($r) => $fmt->formatDialysis($r))->values()->all();

        return [
            'medications' => ['count' => count($formattedMeds),  'items' => $formattedMeds],
            'nutrition' => $formattedNut,
            'recommendations' => ['count' => count($formattedRec), 'items' => $formattedRec],
            'interventions' => ['count' => count($formattedInt),   'items' => $formattedInt],
            'procedures' => ['count' => count($formattedProc),  'items' => $formattedProc],
            'hemotherapy' => ['count' => count($formattedHemo),  'items' => $formattedHemo],
            'surgery' => ['count' => count($formattedSurg),  'items' => $formattedSurg],
            'chemotherapy' => ['count' => count($formattedChemo), 'items' => $formattedChemo],
            'gasotherapy' => ['count' => count($formattedGas),   'items' => $formattedGas],
            'dialysis' => ['count' => count($formattedDial),  'items' => $formattedDial],
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
                NVL(ip.DS_INTERVALO, cm.CD_INTERVALO)           AS secondary_info,
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
            LEFT JOIN tasy.intervalo_prescricao ip
                   ON cm.CD_INTERVALO      = ip.cd_intervalo
                  AND ip.ie_situacao       = 'A'
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
        $nutritionistName = $this->nameFromCpoeUser('pf.CD_PESSOA_FISICA');

        return "
            SELECT
                cd.NR_SEQUENCIA                            AS id,
                cd.CD_DIETA                                AS diet_code,
                cd.CD_MATERIAL                             AS material_code,
                NVL(d.DS_DIETA, SUBSTR(mat.ds_material, 1, 255)) AS name,
                cd.CD_INTERVALO                            AS interval_code,
                                NVL(
                                        (
                                                SELECT MAX(
                                                        NVL(
                                                                tasy.Obter_intervalo_dieta(pd.NR_PRESCRICAO, pd.IE_REFEICAO),
                                                                SUBSTR(tasy.Obter_Unid_Med_Dieta(pd.CD_DIETA), 1, 150)
                                                        )
                                                )
                                                FROM tasy.PRESCR_DIETA pd
                                                JOIN tasy.PRESCR_MEDICA pm
                                                    ON pm.NR_PRESCRICAO = pd.NR_PRESCRICAO
                                                WHERE pd.NR_SEQ_DIETA_CPOE = cd.NR_SEQUENCIA
                                                    AND pm.DT_SUSPENSAO IS NULL
                                                    AND NVL(pd.IE_SUSPENSO, 'N') <> 'S'
                                        ),
                                        ip.DS_INTERVALO
                                )                                           AS interval_description,
                cd.DS_HORARIOS                             AS schedule,
                cd.HR_PRIM_HORARIO                         AS first_hour,
                cd.DT_INICIO                               AS dt_start,
                cd.DT_FIM                                  AS dt_end,
                cd.IE_TIPO_DIETA                           AS flag1,
                cd.IE_JEJUM                                AS flag2,
                cd.IE_DIETA_ENTERAL                        AS flag3,
                cd.IE_DIETA_ESPECIAL                       AS flag4,
                cd.IE_BOMBA_INFUSAO                        AS flag5,
                cd.IE_APLIC_BOLUS                          AS flag6,
                cd.IE_CONTINUO                             AS flag7,
                cd.IE_LEITE_MATERNO                        AS flag8,
                cd.DS_OBSERVACAO                           AS observation,
                cd.DS_ALERGIAS_ALIMENTARES                 AS note_text,
                cd.DS_ORIENTACAO                           AS guidance,
                cd.DS_JUSTIFICATIVA                        AS justification,
                TO_CHAR(cd.QT_KCAL_TOTAL)                  AS total_kcal,
                cd.IE_VIA_APLICACAO                        AS route_code,
                cd.CD_PESSOA_FISICA                        AS nutritionist_id,
                SUBSTR(pf.nm_pessoa_fisica, 1, 120)        AS nutritionist_name,
                cd.NR_SEQ_OBJETIVO                         AS fasting_goal_id,
                obj.DS_OBJETIVO                            AS fasting_goal,
                cd.NR_SEQ_TIPO                             AS fasting_type_id,
                tj.DS_TIPO                                 AS fasting_type,
                TO_CHAR(cd.DT_INICIO_JEJUM, 'DD/MM/YYYY HH24:MI')  AS fasting_start,
                TO_CHAR(cd.DT_FIM_JEJUM, 'DD/MM/YYYY HH24:MI')     AS fasting_end,
                {$prescriberName}                          AS professional_name,
                TO_CHAR(cd.QT_VOLUME)                      AS qty,
                TO_CHAR(cd.QT_VOLUME_TOTAL)                AS total_volume,
                TO_CHAR(cd.QT_VEL_INFUSAO)                 AS infusion_speed,
                NULL                                       AS unit_measure,
                va.DS_VIA_APLICACAO                        AS route,
                (SELECT MAX(m1.DS_MATERIAL) FROM tasy.MATERIAL m1 WHERE m1.CD_MATERIAL = cd.CD_MAT_PROD1) AS product_1,
                TO_CHAR(cd.QT_DOSE_PROD1)                  AS product_1_qty,
                (SELECT MAX(m2.DS_MATERIAL) FROM tasy.MATERIAL m2 WHERE m2.CD_MATERIAL = cd.CD_MAT_PROD2) AS product_2,
                TO_CHAR(cd.QT_DOSE_PROD2)                  AS product_2_qty,
                (SELECT MAX(m3.DS_MATERIAL) FROM tasy.MATERIAL m3 WHERE m3.CD_MATERIAL = cd.CD_MAT_PROD3) AS product_3,
                TO_CHAR(cd.QT_DOSE_PROD3)                  AS product_3_qty,
                (SELECT MAX(m4.DS_MATERIAL) FROM tasy.MATERIAL m4 WHERE m4.CD_MATERIAL = cd.CD_MAT_PROD4) AS product_4,
                TO_CHAR(cd.QT_DOSE_PROD4)                  AS product_4_qty,
                (SELECT MAX(m5.DS_MATERIAL) FROM tasy.MATERIAL m5 WHERE m5.CD_MATERIAL = cd.CD_MAT_PROD5) AS product_5,
                TO_CHAR(cd.QT_DOSE_PROD5)                  AS product_5_qty
            FROM tasy.CPOE_DIETA cd
            LEFT JOIN tasy.dieta d
                   ON cd.CD_DIETA         = d.CD_DIETA
            LEFT JOIN tasy.material mat
                   ON cd.CD_MATERIAL      = mat.cd_material
                 LEFT JOIN tasy.REP_OBJETIVO_JEJUM obj
                     ON cd.NR_SEQ_OBJETIVO = obj.NR_SEQUENCIA
                 LEFT JOIN tasy.REP_TIPO_JEJUM tj
                     ON cd.NR_SEQ_TIPO = tj.NR_SEQUENCIA
                 LEFT JOIN tasy.pessoa_fisica pf
                     ON cd.CD_PESSOA_FISICA = pf.CD_PESSOA_FISICA
            LEFT JOIN tasy.via_aplicacao va
                   ON cd.IE_VIA_APLICACAO = va.IE_VIA_APLICACAO
                  AND va.IE_SITUACAO      = 'A'
            LEFT JOIN tasy.INTERVALO_PRESCRICAO ip
                 ON UPPER(ip.CD_INTERVALO) = UPPER(cd.CD_INTERVALO)
                AND NVL(ip.IE_SITUACAO, 'A') = 'A'
            WHERE cd.NR_ATENDIMENTO = :nr
              AND cd.DT_LIBERACAO   IS NOT NULL
              AND cd.DT_SUSPENSAO   IS NULL
              AND TRUNC(cd.DT_INICIO) <= TRUNC(SYSDATE)
              AND (cd.DT_FIM >= SYSDATE OR (cd.DT_FIM IS NULL AND cd.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
              AND cd.NR_SEQUENCIA   = (
                  SELECT MAX(cd2.NR_SEQUENCIA)
                  FROM tasy.CPOE_DIETA cd2
                  WHERE cd2.NR_ATENDIMENTO         = cd.NR_ATENDIMENTO
                    AND NVL(cd2.CD_DIETA,     0)   = NVL(cd.CD_DIETA,     0)
                    AND NVL(cd2.CD_MATERIAL,  0)   = NVL(cd.CD_MATERIAL,  0)
                    AND cd2.DT_LIBERACAO            IS NOT NULL
                    AND cd2.DT_SUSPENSAO            IS NULL
                    AND TRUNC(cd2.DT_INICIO) <= TRUNC(SYSDATE)
                    AND (cd2.DT_FIM >= SYSDATE OR (cd2.DT_FIM IS NULL AND cd2.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
              )
        ";
    }

    private function recommendationsQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('cr.NM_USUARIO');

        return "
            SELECT
                cr.NR_SEQUENCIA                            AS id,
                cr.CD_RECOMENDACAO                         AS code,
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
              AND TRUNC(cr.DT_INICIO) <= TRUNC(SYSDATE)
                            AND (cr.DT_FIM >= SYSDATE OR (cr.DT_FIM IS NULL AND cr.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
              AND (
                  cr.CD_RECOMENDACAO IS NULL
                  OR cr.NR_SEQUENCIA = (
                      SELECT MAX(cr2.NR_SEQUENCIA)
                      FROM tasy.CPOE_RECOMENDACAO cr2
                      WHERE cr2.NR_ATENDIMENTO  = cr.NR_ATENDIMENTO
                        AND cr2.CD_RECOMENDACAO = cr.CD_RECOMENDACAO
                        AND cr2.DT_LIBERACAO    IS NOT NULL
                        AND cr2.DT_SUSPENSAO    IS NULL
                        AND TRUNC(cr2.DT_INICIO) <= TRUNC(SYSDATE)
                        AND (cr2.DT_FIM >= SYSDATE OR (cr2.DT_FIM IS NULL AND cr2.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
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
                ci.NR_SEQ_PROC                              AS procedure_code,
                ci.CD_PRESCRITOR                            AS prescriber_id,
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
                                ua.CD_SETOR_ATENDIMENTO                    AS setor_raw,
                                (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                                 FROM tasy.setor_atendimento sa
                                 WHERE sa.cd_setor_atendimento = ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw,
                {$prescriberName}                          AS professional_name
            FROM tasy.CPOE_INTERVENCAO ci
                        JOIN tasy.UNIDADE_ATENDIMENTO ua
                            ON ua.NR_ATENDIMENTO = ci.NR_ATENDIMENTO
                         AND ua.IE_SITUACAO = 'A'
            WHERE ci.NR_ATENDIMENTO = :nr
              AND ci.DT_LIBERACAO   IS NOT NULL
              AND ci.DT_SUSPENSAO   IS NULL
              AND TRUNC(ci.DT_INICIO) <= TRUNC(SYSDATE)
              AND (ci.DT_FIM IS NULL OR ci.DT_FIM >= SYSDATE)
              AND ci.NR_SEQUENCIA   = (
                  SELECT MAX(ci2.NR_SEQUENCIA)
                  FROM tasy.CPOE_INTERVENCAO ci2
                  WHERE ci2.NR_ATENDIMENTO = ci.NR_ATENDIMENTO
                    AND ci2.NR_SEQ_PROC    = ci.NR_SEQ_PROC
                    AND ci2.DT_LIBERACAO   IS NOT NULL
                    AND ci2.DT_SUSPENSAO   IS NULL
                    AND TRUNC(ci2.DT_INICIO) <= TRUNC(SYSDATE)
                    AND (ci2.DT_FIM IS NULL OR ci2.DT_FIM >= SYSDATE)
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
                COALESCE(vd_exam.ds_valor_dominio, vd_proc_s.ds_valor_dominio) AS status_label,
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
                pm.DT_PRESCRICAO                            AS dt_solicitacao_raw,
                pm.DT_LIBERACAO                             AS dt_liberacao_raw,
                CASE
                    WHEN pp.NR_SEQ_EXAME IS NOT NULL THEN 'exame'
                    ELSE 'procedimento'
                END                                         AS event_type,
                pp.IE_AMOSTRA                               AS sample_check,
                pp.DT_COLETA                                AS collected_at_raw,
                (SELECT MAX(gel.DS_GRUPO_EXAME_LAB)
                 FROM tasy.EXAME_LABORATORIO el
                 LEFT JOIN tasy.GRUPO_EXAME_LAB gel
                    ON gel.NR_SEQUENCIA = el.NR_SEQ_GRUPO
                 WHERE el.NR_SEQ_EXAME = pp.NR_SEQ_EXAME)   AS ds_grupo_lab,
                (SELECT tasy.OBTER_STATUS_LAUDO(MAX(pp_pac.nr_laudo))
                 FROM tasy.PROCEDIMENTO_PACIENTE pp_pac
                 WHERE pp_pac.nr_prescricao            = pm.NR_PRESCRICAO
                   AND pp_pac.nr_sequencia_prescricao  = pp.nr_sequencia) AS ds_status_laudo,
                CAST(NULL AS NUMBER)                        AS setor_raw,
                CAST(NULL AS VARCHAR2(255))                 AS setor_desc_raw,
                CASE WHEN TRUNC(pp.DT_PREV_EXECUCAO) = TRUNC(SYSDATE)     THEN 1 ELSE 0 END AS is_today,
                CASE WHEN TRUNC(pp.DT_PREV_EXECUCAO) = TRUNC(SYSDATE - 1) THEN 1 ELSE 0 END AS is_yesterday,
                CASE WHEN TRUNC(pp.DT_PREV_EXECUCAO) = TRUNC(SYSDATE + 1) THEN 1 ELSE 0 END AS is_tomorrow,
                (SELECT CASE
                            WHEN elri.ds_resultado IS NOT NULL THEN elri.ds_resultado
                            ELSE REPLACE(
                                NVL(
                                    TO_CHAR(elri.qt_resultado, 'FM9G999G990D099999', 'NLS_NUMERIC_CHARACTERS='',.'''),
                                    TO_CHAR(elri.pr_resultado, 'FM9G999G990D099999', 'NLS_NUMERIC_CHARACTERS='',.''')
                                ), '.', ','
                            )
                        END
                 FROM tasy.result_laboratorio rl_res
                 JOIN tasy.exame_lab_result_item elri
                      ON elri.nr_seq_resultado = rl_res.nr_sequencia
                     AND elri.nr_sequencia     = 1
                 WHERE rl_res.nr_prescricao     = pm.NR_PRESCRICAO
                   AND rl_res.nr_seq_prescricao = pp.NR_SEQUENCIA
                   AND ROWNUM = 1)                              AS ds_resultado_laudo,
                pp.DT_RESULTADO                             AS dt_resultado,
                CASE WHEN EXISTS (
                    SELECT 1 FROM tasy.procedimento_paciente pp_exec
                    WHERE pp_exec.nr_prescricao           = pm.NR_PRESCRICAO
                      AND pp_exec.nr_sequencia_prescricao = pp.NR_SEQUENCIA
                ) THEN 1 ELSE 0 END AS foi_executado_sem_baixa,
                CASE WHEN pp.NR_SEQ_EXAME IS NOT NULL AND EXISTS (
                    SELECT 1 FROM tasy.procedimento_paciente pp_dup
                    WHERE pp_dup.nr_atendimento = pm.NR_ATENDIMENTO
                      AND pp_dup.nr_seq_exame   = pp.NR_SEQ_EXAME
                      AND pp_dup.nr_prescricao  > pm.NR_PRESCRICAO
                ) THEN 1 ELSE 0 END AS exame_coletado_em_prescricao_mais_nova,
                CASE WHEN pp.NR_SEQ_EXAME IS NOT NULL THEN (
                    SELECT nova.nr_prescricao || ' — ' || TO_CHAR(nova.dt_prescricao, 'DD/MM/YYYY')
                    FROM (
                        SELECT pm2.nr_prescricao, pm2.dt_prescricao
                        FROM tasy.prescr_medica pm2
                        JOIN tasy.prescr_procedimento pp2
                            ON pp2.nr_prescricao = pm2.nr_prescricao
                        WHERE pm2.nr_atendimento = pm.NR_ATENDIMENTO
                          AND pp2.nr_seq_exame   = pp.NR_SEQ_EXAME
                          AND pm2.nr_prescricao  > pm.NR_PRESCRICAO
                          AND pp2.ie_status_execucao NOT IN ('40','R','C','BE')
                          AND pp2.dt_baixa        IS NULL
                          AND pp2.dt_cancelamento IS NULL
                          AND pp2.ie_suspenso     <> 'S'
                          AND pm2.dt_suspensao    IS NULL
                          AND pm2.dt_liberacao    IS NOT NULL
                        ORDER BY pm2.nr_prescricao DESC
                    ) nova WHERE ROWNUM = 1
                ) ELSE NULL END AS prescricao_mais_nova_pendente_info
            FROM tasy.PRESCR_PROCEDIMENTO pp
            JOIN tasy.PRESCR_MEDICA pm
              ON pm.NR_PRESCRICAO = pp.NR_PRESCRICAO
            LEFT JOIN tasy.valor_dominio vd_exam
                ON vd_exam.cd_dominio = 1030
               AND vd_exam.vl_dominio = pp.IE_STATUS_EXECUCAO
               AND pp.NR_SEQ_EXAME IS NOT NULL
            LEFT JOIN tasy.valor_dominio vd_proc_s
                ON vd_proc_s.cd_dominio = 1226
               AND vd_proc_s.vl_dominio = pp.IE_STATUS_EXECUCAO
               AND pp.NR_SEQ_EXAME IS NULL
            LEFT JOIN tasy.result_laboratorio rl_coll
                ON rl_coll.nr_prescricao     = pm.NR_PRESCRICAO
               AND rl_coll.nr_seq_prescricao = pp.NR_SEQUENCIA
               AND rl_coll.dt_coleta         IS NOT NULL
            WHERE pm.NR_ATENDIMENTO = :nr_proc
              AND pm.DT_LIBERACAO   IS NOT NULL
              AND pm.DT_SUSPENSAO   IS NULL
              AND pp.DT_BAIXA       IS NULL
              AND pp.DT_CANCELAMENTO IS NULL
              AND pp.IE_SUSPENSO    <> 'S'
              AND pp.IE_STATUS_EXECUCAO NOT IN ('40', 'R', 'C', 'BE')
              AND pp.IE_STATUS_ATEND < 35
                            AND (pp.IE_ORIGEM_PROCED <> 4 OR pp.NR_SEQ_EXAME IS NOT NULL)
                            AND rl_coll.NR_PRESCRICAO IS NULL
                            AND NOT EXISTS (
                                    SELECT 1 FROM tasy.procedimento_paciente pp_laudo
                                    WHERE pp_laudo.nr_prescricao           = pm.NR_PRESCRICAO
                                        AND pp_laudo.nr_sequencia_prescricao  = pp.NR_SEQUENCIA
                                        AND pp_laudo.nr_laudo                 IS NOT NULL
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
                vd_agt.ds_valor_dominio                     AS status_label,
                TO_CHAR(ap.DT_AGENDA, 'DD/MM/YY')
                    || CASE WHEN ap.HR_INICIO IS NOT NULL
                       THEN ' ' || SUBSTR(TO_CHAR(ap.HR_INICIO, 'HH24:MI'), 1, 5)
                       ELSE '' END                          AS scheduled,
                ap.DT_AGENDA                                AS scheduled_raw,
                NULL                                        AS tipo,
                NULL                                        AS professional_name,
                NULL                                        AS nr_prescricao,
                CAST(NULL AS DATE)                          AS dt_baixa,
                CAST(NULL AS DATE)                          AS dt_solicitacao_raw,
                CAST(NULL AS DATE)                          AS dt_liberacao_raw,
                CASE
                    WHEN (SELECT MAX(pi.NR_SEQ_EXAME_LAB)
                          FROM tasy.PROC_INTERNO pi
                          WHERE pi.NR_SEQUENCIA = ap.NR_SEQ_PROC_INTERNO) IS NOT NULL
                    THEN 'exame'
                    ELSE 'procedimento'
                END                                         AS event_type,
                CAST(NULL AS VARCHAR2(1))                   AS sample_check,
                CAST(NULL AS DATE)                          AS collected_at_raw,
                CAST(NULL AS VARCHAR2(255))                 AS ds_grupo_lab,
                CAST(NULL AS VARCHAR2(255))                 AS ds_status_laudo,
                NVL(
                    TASY.OBTER_SETOR_CIRURGIA(
                        ap.NR_ATENDIMENTO,
                        (
                            SELECT MAX(apu.DT_ENTRADA_UNIDADE)
                            FROM tasy.ATEND_PACIENTE_UNIDADE apu
                            WHERE apu.NR_ATENDIMENTO = ap.NR_ATENDIMENTO
                        )
                    ),
                    NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO)
                )                                                  AS setor_raw,
                (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                 FROM tasy.setor_atendimento sa
                 WHERE sa.cd_setor_atendimento = NVL(
                     TASY.OBTER_SETOR_CIRURGIA(
                         ap.NR_ATENDIMENTO,
                         (
                             SELECT MAX(apu.DT_ENTRADA_UNIDADE)
                             FROM tasy.ATEND_PACIENTE_UNIDADE apu
                             WHERE apu.NR_ATENDIMENTO = ap.NR_ATENDIMENTO
                         )
                     ),
                     NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO)
                 ))                                                 AS setor_desc_raw,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE)     THEN 1 ELSE 0 END AS is_today,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE - 1) THEN 1 ELSE 0 END AS is_yesterday,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE + 1) THEN 1 ELSE 0 END AS is_tomorrow,
                CAST(NULL AS VARCHAR2(255))                 AS ds_resultado_laudo,
                CAST(NULL AS DATE)                          AS dt_resultado,
                0                                           AS foi_executado_sem_baixa,
                0                                           AS exame_coletado_em_prescricao_mais_nova,
                CAST(NULL AS VARCHAR2(255))                 AS prescricao_mais_nova_pendente_info
            FROM tasy.AGENDA_PACIENTE ap
            LEFT JOIN tasy.valor_dominio vd_agt
                ON vd_agt.cd_dominio = 83
               AND vd_agt.vl_dominio = ap.IE_STATUS_AGENDA
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
                ch.CD_PESSOA_FISICA                        AS prescriber_id,
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
                                (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                                 FROM tasy.setor_atendimento sa
                                 WHERE sa.cd_setor_atendimento = ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw
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
                    TASY.OBTER_CIRURGIA_PACIENTE(ap.NR_ATENDIMENTO, 'AA'),
                    (SELECT MAX(pi.DS_PROC_EXAME)
                     FROM tasy.proc_interno pi
                     WHERE pi.NR_SEQUENCIA = ap.NR_SEQ_PROC_INTERNO),
                    ap.DS_CIRURGIA,
                    'Cirurgia não especificada'
                )                                                  AS name,
                ap.IE_CARATER_CIRURGIA                             AS flag1,
                ap.IE_STATUS_AGENDA                                AS status_raw,
                vd_surg_stat.ds_valor_dominio                      AS status_label,
                vd_surg_car.ds_valor_dominio                       AS carater_label,
                NVL(
                    TASY.OBTER_SETOR_PRESCR_AGENDA(ap.NR_SEQUENCIA),
                    NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO)
                )                                                  AS setor_raw,
                (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                 FROM tasy.setor_atendimento sa
                 WHERE sa.cd_setor_atendimento = NVL(
                     TASY.OBTER_SETOR_PRESCR_AGENDA(ap.NR_SEQUENCIA),
                     NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO)
                 ))                                                 AS setor_desc_raw,
                TO_CHAR(ap.DT_AGENDA, 'DD/MM/YY')
                    || CASE WHEN ap.HR_INICIO IS NOT NULL
                       THEN ' ' || SUBSTR(TO_CHAR(ap.HR_INICIO,'HH24:MI'), 1, 5)
                       ELSE '' END                                 AS schedule,
                ap.DT_AGENDA                                       AS dt_start,
                ap.DS_OBSERVACAO                                   AS observation,
                TO_CHAR(ap.NR_SEQ_SALA)                            AS extra1,
                ap.DS_CIRURGIA                                     AS extra2,
                COALESCE(
                    TASY.OBTER_TIPO_CIRUR_PROC(ap.NR_SEQ_PROC_INTERNO),
                    (
                        SELECT MAX(p.CD_TIPO_PROCEDIMENTO)
                        FROM tasy.PROCEDIMENTO p
                        WHERE p.CD_PROCEDIMENTO = ap.CD_PROCEDIMENTO
                          AND p.IE_ORIGEM_PROCED = ap.IE_ORIGEM_PROCED
                    )
                )                                                  AS extra3,
                ap.DS_CIRURGIA                                     AS extra4
            FROM tasy.AGENDA_PACIENTE ap
            JOIN tasy.ATENDIMENTO_PACIENTE atp
                ON atp.CD_PESSOA_FISICA = ap.CD_PESSOA_FISICA
            LEFT JOIN tasy.valor_dominio vd_surg_stat
                ON vd_surg_stat.cd_dominio = 83
               AND vd_surg_stat.vl_dominio = ap.IE_STATUS_AGENDA
            LEFT JOIN tasy.valor_dominio vd_surg_car
                ON vd_surg_car.cd_dominio = 1016
               AND vd_surg_car.vl_dominio = ap.IE_CARATER_CIRURGIA
            WHERE atp.NR_ATENDIMENTO       = :nr
              AND ap.DT_AGENDA             >= TRUNC(SYSDATE)
              AND ap.IE_CARATER_CIRURGIA   IS NOT NULL
              AND ap.IE_CARATER_CIRURGIA   <> 'X'
              AND ap.IE_STATUS_AGENDA      NOT IN ('C', 'S', 'CR', 'E', 'AD')
                            AND ap.DT_EXECUTADA          IS NULL
        ";
    }

    private function chemotherapyQuery(): string
    {
        return "
            SELECT
                ROWNUM                                             AS id,
                aq.CD_PESSOA_FISICA                                AS patient_person_id,
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
                (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                 FROM tasy.setor_atendimento sa
                 WHERE sa.cd_setor_atendimento = ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw
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
                cg.NR_SEQ_GAS                              AS gas_code,
                cg.CD_PESSOA_FISICA                        AS prescriber_id,
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
                                ua.CD_SETOR_ATENDIMENTO                    AS setor_raw,
                                (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                                 FROM tasy.setor_atendimento sa
                                 WHERE sa.cd_setor_atendimento = ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw,
                {$prescriberName}                          AS professional_name
            FROM tasy.CPOE_GASOTERAPIA cg
                        JOIN tasy.UNIDADE_ATENDIMENTO ua
                            ON ua.NR_ATENDIMENTO = cg.NR_ATENDIMENTO
                         AND ua.IE_SITUACAO = 'A'
            WHERE cg.NR_ATENDIMENTO = :nr
              AND cg.DT_LIBERACAO   IS NOT NULL
              AND cg.DT_SUSPENSAO   IS NULL
              AND TRUNC(cg.DT_INICIO) <= TRUNC(SYSDATE)
                            AND (cg.DT_FIM >= SYSDATE OR (cg.DT_FIM IS NULL AND cg.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
            ORDER BY cg.NR_SEQUENCIA ASC
        ";
    }

    private function dialysisQuery(): string
    {
        $prescriberName = $this->nameFromCpoeUser('cd.NM_USUARIO');

        return "
            SELECT
                cd.NR_SEQUENCIA                            AS id,
                cd.CD_PESSOA_FISICA                        AS prescriber_id,
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
                                ua.CD_SETOR_ATENDIMENTO                    AS setor_raw,
                                (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                                 FROM tasy.setor_atendimento sa
                                 WHERE sa.cd_setor_atendimento = ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw,
                {$prescriberName}                          AS professional_name
            FROM tasy.CPOE_DIALISE cd
                        JOIN tasy.UNIDADE_ATENDIMENTO ua
                            ON ua.NR_ATENDIMENTO = cd.NR_ATENDIMENTO
                         AND ua.IE_SITUACAO = 'A'
            WHERE cd.NR_ATENDIMENTO = :nr
              AND cd.DT_LIBERACAO   IS NOT NULL
              AND cd.DT_SUSPENSAO   IS NULL
              AND TRUNC(cd.DT_INICIO) <= TRUNC(SYSDATE)
                            AND (cd.DT_FIM >= SYSDATE OR (cd.DT_FIM IS NULL AND cd.DT_LIBERACAO >= TRUNC(SYSDATE) - 1))
            ORDER BY cd.NR_SEQUENCIA ASC
        ";
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
            'att_a' => $attendanceNumber,
            'date_a' => $date,
            'att_b' => $attendanceNumber,
            'date_b' => $date,
        ]);

        $schedule = [];
        foreach ($rows as $row) {
            $id = (int) $row->med_id;
            $priority = (int) $row->priority;
            $time = trim($row->time_label ?? '');

            if ($time && strlen($time) === 4 && $time[0] !== '0') {
                $time = '0'.$time;
            }
            if (! $time || ! preg_match('/^\d{2}:\d{2}$/', $time)) {
                continue;
            }

            $status = match (true) {
                $priority >= 600 => 'administered',
                $priority >= 500 => 'conferido',
                $priority >= 400 => 'coletado',
                $priority >= 300 => 'refused',
                $priority >= 200 => 'undone',
                $priority >= 30 => 'rescheduled',
                default => 'scheduled',
            };

            $schedule[$id][$time] = $status;
        }

        foreach ($schedule as &$slots) {
            ksort($slots);
        }

        return $schedule;
    }
}
