<?php

namespace App\Repositories\EMR;

use App\Support\PendingEventTypeClassifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for all prescription-related Tasy (Oracle) data.
 *
 * Exposes two modes:
 *   1. Single-patient (for patient modal): getPrescriptions() runs 10 separate queries
 *      and returns full-detail formatted data for all categories.
 *   2. Batch / sector (for pending event handlers): queryXxxChunk() methods accept an
 *      already-chunked attendance list and return raw stdClass rows — the handler
 *      is responsible for transforming them into pending events.
 *
 * Categories (single-patient):
 *   medications   – CPOE_MATERIAL (active only, with NR_PRESCRICAO)
 *   nutrition     – CPOE_DIETA (dietary / enteral / fasting prescriptions)
 *   orders        – CPOE_RECOMENDACAO (standing medical orders)
 *   interventions – CPOE_INTERVENCAO (nursing care interventions)
 *   procedures    – PRESCR_PROCEDIMENTO UNION AGENDA_PACIENTE (exams + procedures)
 *   hemotherapy   – CPOE_HEMOTERAPIA (blood product orders)
 *   surgery       – AGENDA_PACIENTE (future surgeries)
 *   chemotherapy  – AGENDA_QUIMIOTERAPIA_PEP_V (upcoming chemo sessions)
 *   gasotherapy   – CPOE_GASOTERAPIA (active gas therapy)
 *   dialysis      – CPOE_DIALISE (active dialysis)
 */
class PatientPrescriptionsRepository
{
    // ==================== SINGLE-PATIENT API (modal) ====================

    public function getPrescriptions(int $attendanceNumber): array
    {
        $nr = $attendanceNumber;

        $medRows = DB::connection('tasy')->select($this->medicationsQuery(), [':nr' => $nr]);
        $nutRows = DB::connection('tasy')->select($this->nutritionQuery(), [':nr' => $nr]);
        $ordRows = DB::connection('tasy')->select($this->ordersQuery(), [':nr' => $nr]);
        $intRows = DB::connection('tasy')->select($this->interventionsQuery(), [':nr' => $nr]);
        $procRows = DB::connection('tasy')->select($this->proceduresQuery(), [
            ':nr_proc' => $nr,
            ':nr_surg' => $nr,
            ':nr_surg_setor' => $nr,
        ]);
        $hemoRows = DB::connection('tasy')->select($this->hemotherapyQuery(), [':nr' => $nr]);
        $surgRows = DB::connection('tasy')->select($this->surgeryQuery(), [
            ':nr' => $nr,
        ]);
        $chemoRows = DB::connection('tasy')->select($this->chemotherapyQuery(), [':nr' => $nr]);
        $gasRows = DB::connection('tasy')->select($this->gasotherapyQuery(), [':nr' => $nr]);
        $dialRows = DB::connection('tasy')->select($this->dialysisQuery(), [':nr' => $nr]);

        $formattedMeds = collect($medRows)->map(fn ($r) => $this->formatMedication($r))->values()->all();
        $formattedNut = $this->organizeNutritionByShift(collect($nutRows));
        $formattedOrd = collect($ordRows)->map(fn ($r) => $this->formatOrder($r))->values()->all();
        $formattedInt = collect($intRows)->map(fn ($r) => $this->formatIntervention($r))->values()->all();
        $formattedProc = collect($procRows)->map(fn ($r) => $this->formatProcedure($r))->values()->all();
        $formattedHemo = collect($hemoRows)->map(fn ($r) => $this->formatHemotherapy($r))->values()->all();
        $formattedSurg = collect($surgRows)->map(fn ($r) => $this->formatSurgery($r))->values()->all();
        $formattedChemo = collect($chemoRows)->map(fn ($r) => $this->formatChemotherapy($r))->values()->all();
        $formattedGas = collect($gasRows)->map(fn ($r) => $this->formatGasotherapy($r))->values()->all();
        $formattedDial = collect($dialRows)->map(fn ($r) => $this->formatDialysis($r))->values()->all();

        return [
            'medications' => ['count' => count($formattedMeds),  'items' => $formattedMeds],
            'nutrition' => $formattedNut,
            'orders' => ['count' => count($formattedOrd),   'items' => $formattedOrd],
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

    private function ordersQuery(): string
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
                                tasy.OBTER_DS_SETOR_ATENDIMENTO(ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw,
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
                -- Flags de status complementares (alinhados com PrescriptionPendingHandler)
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
            -- Status domain 1030: lab exams
            LEFT JOIN tasy.valor_dominio vd_exam
                ON vd_exam.cd_dominio = 1030
               AND vd_exam.vl_dominio = pp.IE_STATUS_EXECUCAO
               AND pp.NR_SEQ_EXAME IS NOT NULL
            -- Status domain 1226: procedures / imaging
            LEFT JOIN tasy.valor_dominio vd_proc_s
                ON vd_proc_s.cd_dominio = 1226
               AND vd_proc_s.vl_dominio = pp.IE_STATUS_EXECUCAO
               AND pp.NR_SEQ_EXAME IS NULL
            -- Exclude lab exams that have already been collected
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
              AND (pp.NR_SEQ_PROC_INTERNO IS NULL OR pp.NR_SEQ_PROC_INTERNO NOT IN (5970, 1341, 5927))
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
                tasy.OBTER_DS_SETOR_ATENDIMENTO(
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
                    )
                )                                                  AS setor_desc_raw,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE)     THEN 1 ELSE 0 END AS is_today,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE - 1) THEN 1 ELSE 0 END AS is_yesterday,
                CASE WHEN TRUNC(ap.DT_AGENDA) = TRUNC(SYSDATE + 1) THEN 1 ELSE 0 END AS is_tomorrow,
                CAST(NULL AS VARCHAR2(255))                 AS ds_resultado_laudo,
                CAST(NULL AS DATE)                          AS dt_resultado,
                0                                           AS foi_executado_sem_baixa,
                0                                           AS exame_coletado_em_prescricao_mais_nova,
                CAST(NULL AS VARCHAR2(255))                 AS prescricao_mais_nova_pendente_info
            FROM tasy.AGENDA_PACIENTE ap
            -- Status domain 83: agenda_paciente statuses
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
                tasy.OBTER_DS_SETOR_ATENDIMENTO(
                    NVL(
                        TASY.OBTER_SETOR_PRESCR_AGENDA(ap.NR_SEQUENCIA),
                        NVL(tasy.OBTER_SETOR_AGENDA(ap.CD_AGENDA), ap.CD_SETOR_ATENDIMENTO)
                    )
                )                                                  AS setor_desc_raw,
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
            -- Status domain 83: agenda_paciente statuses
            LEFT JOIN tasy.valor_dominio vd_surg_stat
                ON vd_surg_stat.cd_dominio = 83
               AND vd_surg_stat.vl_dominio = ap.IE_STATUS_AGENDA
            -- Caráter domain 1016: cirurgia character
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
                                tasy.OBTER_DS_SETOR_ATENDIMENTO(ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw,
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
                                tasy.OBTER_DS_SETOR_ATENDIMENTO(ua.CD_SETOR_ATENDIMENTO) AS setor_desc_raw,
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

    // ==================== ITEM FORMATTERS ====================

    private function formatMedication(object $row): array
    {
        $totalDoses = (int) ($row->num1 ?? 0);
        $checkedDoses = (int) ($row->num2 ?? 0);

        // Status: always 'active' since we filter DT_SUSPENSAO IS NULL at query level
        $status = 'active';

        $doseDisplay = trim(($row->qty ?? '').' '.($row->unit_measure ?? ''));

        $isAntibiotic = ($row->flag2 ?? '') === 'S';
        $antibioticDay = ! empty($row->flag3) ? (int) $row->flag3 : null;
        $antibioticDays = ! empty($row->flag4) ? (int) $row->flag4 : null;

        $hasDialuent = ! empty($row->diluent_name);
        $diluentDisplay = $hasDialuent
            ? trim($row->diluent_name.(! empty($row->diluent_qty) ? ' – '.$row->diluent_qty : ''))
            : null;

        $hasPrepNotes = ! empty($row->prep_notes);
        $hasObs = ! empty($row->observation);
        $hasJust = ! empty($row->note_text);

        $volume = ! empty($row->med_volume) ? trim($row->med_volume).' mL' : null;
        $infusionMin = ! empty($row->infusion_min) ? (int) $row->infusion_min : null;

        // NR_PRESCRICAO from subquery
        $nrPrescricao = isset($row->nr_prescricao) && $row->nr_prescricao !== null
            ? (int) $row->nr_prescricao
            : null;

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $row->name ?? 'Medicamento não identificado',
            'dose' => $doseDisplay ?: null,
            'route' => $row->route ?? null,
            'frequency' => $row->secondary_info ?? null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'admin_code' => $row->flag1 ?? null,
            'is_antibiotic' => $isAntibiotic,
            'antibiotic_day' => $antibioticDay,
            'antibiotic_days' => $antibioticDays,
            'diluent' => $diluentDisplay,
            'volume' => $volume,
            'infusion_min' => $infusionMin,
            'prep_notes' => $hasPrepNotes ? $row->prep_notes : null,
            'dt_start' => ! empty($row->dt_start ?? null) ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => ! empty($row->dt_end ?? null) ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'status' => $status,
            'total_doses' => $totalDoses,
            'checked_doses' => $checkedDoses,
            'observation' => $hasObs ? $row->observation : null,
            'justification' => $hasJust ? $row->note_text : null,
            'prescriber' => $row->extra1 ?? ($row->professional_name ?? null),
            'is_high_alert' => ($row->flag5 ?? '') === 'S',
            'is_controlled' => ($row->flag6 ?? '') === 'S',
            'drug_class' => ! empty($row->extra2) ? $row->extra2 : null,
            'has_details' => $hasObs || $hasJust || $hasPrepNotes || $hasDialuent || $infusionMin || $volume,
            'nr_prescricao' => $nrPrescricao,
        ];
    }

    public function formatOrder(object $row): array
    {
        $text = trim((string) ($row->name ?? 'No description'));
        $type = ! empty($row->secondary_info) ? trim((string) $row->secondary_info) : null;
        if ($type !== null && mb_strtolower($type) === mb_strtolower($text)) {
            $type = null;
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'text' => $text !== '' ? $text : 'No description',
            'type' => $type,
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'prescriber' => $row->professional_name ?? null,
            'dt_start' => ! empty($row->dt_start ?? null) ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => ! empty($row->dt_end ?? null) ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation),
        ];
    }

    public function formatIntervention(object $row): array
    {
        $labels = [];
        if (($row->flag1 ?? '') === '1') {
            $labels[] = 'Urgente';
        }
        if (($row->flag2 ?? '') === 'S') {
            $labels[] = 'Se necessário';
        }
        if (($row->flag3 ?? '') === 'S') {
            $labels[] = 'A critério médico';
        }
        if (! empty($row->flag4)) {
            $sideMap = ['D' => 'Direito', 'E' => 'Esquerdo', 'B' => 'Bilateral', 'U' => 'Unilateral', 'N' => 'Não se aplica'];
            $side = $sideMap[$row->flag4] ?? $row->flag4;
            $labels[] = 'Lado: '.$side;
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'procedure_code' => ! empty($row->procedure_code) ? (string) $row->procedure_code : null,
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'name' => $row->name ?? 'Unidentified intervention',
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'assignee' => $row->professional_name ?? null,
            'prescriber' => null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'labels' => $labels,
            'dt_start' => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => $row->dt_end ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation) || ! empty($labels),
        ];
    }

    private function formatProcedure(object $row): array
    {
        $origem = $row->origem ?? 'PRESCRICAO';
        $statusRaw = (string) ($row->status_raw ?? '');
        $statusLabel = ! empty($row->status_label) ? trim((string) $row->status_label) : null;

        $status = 'Pendente';
        if (! empty($row->dt_baixa)) {
            $status = 'Realizado';
        } elseif ($statusLabel !== null) {
            $status = $statusLabel;
        } elseif ($statusRaw !== '') {
            $status = $statusRaw;
        }

        $type = ! empty($row->tipo) ? $row->tipo : null;

        $eventType = PendingEventTypeClassifier::fromTherapeuticProcedure([
            'event_type' => $row->event_type ?? null,
            'type' => $type,
            'name' => $row->name ?? null,
        ]);

        if ($origem === 'AGENDAMENTO' && $type === null) {
            $type = $eventType === PendingEventTypeClassifier::EXAM ? 'Exame' : 'Procedimento';
        }
        $isToday = (int) ($row->is_today ?? 0) === 1;
        $isYest = (int) ($row->is_yesterday ?? 0) === 1;
        $isTomorrow = (int) ($row->is_tomorrow ?? 0) === 1;
        $isNear = $isToday || $isYest || $isTomorrow;

        $scheduledRaw = null;
        if (! empty($row->scheduled_raw)) {
            try {
                $scheduledRaw = Carbon::parse($row->scheduled_raw)->format('Y-m-d');
            } catch (\Exception $e) {
                $scheduledRaw = null;
            }
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $row->name ?? 'Procedimento não identificado',
            'origem' => $origem,
            'type' => $type,
            'scheduled' => $row->scheduled ?? null,
            'scheduled_raw' => $scheduledRaw,
            'status' => $status,
            'event_type' => $eventType,
            'is_today' => $isToday,
            'is_yesterday' => $isYest,
            'is_tomorrow' => $isTomorrow,
            'is_near' => $isNear,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'prescriber' => ! empty($row->professional_name) ? $row->professional_name : null,
            'nr_prescricao' => isset($row->nr_prescricao) && $row->nr_prescricao !== null ? (int) $row->nr_prescricao : null,
            'checklist_amostra' => ! empty($row->sample_check) ? (string) $row->sample_check : null,
            'dt_coleta' => ! empty($row->collected_at_raw) ? Carbon::parse($row->collected_at_raw)->format('d/m/Y H:i') : null,
            'dt_solicitacao' => ! empty($row->dt_solicitacao_raw) ? Carbon::parse($row->dt_solicitacao_raw)->format('d/m/Y H:i') : null,
            'dt_liberacao' => ! empty($row->dt_liberacao_raw) ? Carbon::parse($row->dt_liberacao_raw)->format('d/m/Y H:i') : null,
            'classificacao' => ! empty($row->ds_grupo_lab) ? trim((string) $row->ds_grupo_lab) : null,
            'status_laudo' => ! empty($row->ds_status_laudo) ? trim((string) $row->ds_status_laudo) : null,
            'tempo_pendente' => $this->formatTempoPendenteFromDate(
                ! empty($row->dt_solicitacao_raw) ? (string) $row->dt_solicitacao_raw : (! empty($row->scheduled_raw) ? (string) $row->scheduled_raw : null)
            ),
            'resultado_laudo' => ! empty($row->ds_resultado_laudo) ? trim((string) $row->ds_resultado_laudo) : null,
            'dt_resultado' => ! empty($row->dt_resultado) ? Carbon::parse($row->dt_resultado)->format('d/m/Y H:i') : null,
            'foi_executado_sem_baixa' => (int) ($row->foi_executado_sem_baixa ?? 0) === 1,
            'exame_coletado_em_prescricao_mais_nova' => (int) ($row->exame_coletado_em_prescricao_mais_nova ?? 0) === 1,
            'prescricao_mais_nova_pendente_info' => ! empty($row->prescricao_mais_nova_pendente_info) ? (string) $row->prescricao_mais_nova_pendente_info : null,
        ];
    }

    private function formatTempoPendenteFromDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            $start = Carbon::parse($date);
            $now = now();

            if ($start->greaterThan($now)) {
                $diffMinutes = (int) $now->diffInMinutes($start);
                if ($diffMinutes < 60) {
                    return 'em '.$diffMinutes.'min';
                }

                $diffHours = intdiv($diffMinutes, 60);

                return $diffHours < 24 ? 'em '.$diffHours.'h' : 'em '.intdiv($diffHours, 24).'d';
            }

            $diffMinutes = (int) $start->diffInMinutes($now);
            if ($diffMinutes < 60) {
                return $diffMinutes.'min em aberto';
            }

            $diffHours = intdiv($diffMinutes, 60);

            return $diffHours < 24 ? $diffHours.'h em aberto' : intdiv($diffHours, 24).'d em aberto';
        } catch (\Throwable) {
            return null;
        }
    }

    public function formatHemotherapy(object $row): array
    {
        $volume = null;
        if (! empty($row->qty)) {
            $volume = trim($row->qty.(! empty($row->unit_measure) ? ' '.$row->unit_measure : ''));
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $row->name ?? 'Hemocomponente',
            'tipo_code' => $row->secondary_info ?? null,
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'is_urgent' => ($row->flag1 ?? 'N') === 'S',
            'schedule' => $row->schedule ?? null,
            'volume' => $volume,
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'prescriber' => $row->professional_name ?? null,
            'dt_start' => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => $row->dt_end ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation),
        ];
    }

    public function formatSurgery(object $row): array
    {
        $caraterCode = (string) ($row->flag1 ?? '');
        $carater = ! empty($row->carater_label) ? trim((string) $row->carater_label) : ($caraterCode !== '' ? $caraterCode : 'Não informado');
        $is_urgent = in_array($caraterCode, ['U', 'M']);

        $statusRaw = (string) ($row->status_raw ?? '');
        $status = ! empty($row->status_label) ? trim((string) $row->status_label) : ($statusRaw !== '' ? $statusRaw : 'Aguardando');

        $name = $this->normalizeSurgeryDescription((string) ($row->name ?? 'Cirurgia não especificada'));

        $surgeryObservation = $this->filterSensitiveSurgeryObservation($row->observation ?? null);

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $name,
            'carater' => $carater,
            'status' => $status,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'is_urgent' => $is_urgent,
            'dt' => $row->schedule ?? null,
            'sala' => ! empty($row->extra1) ? 'Sala '.$row->extra1 : null,
            'description' => ! empty($row->extra2) ? $row->extra2 : null,
            'tipo_cirurgia_codigo' => ! empty($row->extra3) ? (int) $row->extra3 : null,
            'local' => ! empty($row->extra4) ? trim((string) $row->extra4) : (! empty($row->extra2) ? trim((string) $row->extra2) : null),
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'observacoes' => $surgeryObservation,
            'has_details' => ! empty($row->observation) || ! empty($surgeryObservation),
        ];
    }

    private function normalizeSurgeryDescription(string $description): string
    {
        $cleaned = preg_replace('/\s*\(\s*Cirurgia[^\)]*\)\s*$/iu', '', $description);
        $normalized = trim((string) ($cleaned ?? $description));

        return $normalized !== '' ? $normalized : $description;
    }

    private function filterSensitiveSurgeryObservation(?string $observation): ?string
    {
        if (empty($observation)) {
            return null;
        }

        $patterns = [
            '/R\$\s*[\d.,]+/i',
            '/valor.*[\d.,]+/i',
            '/custo.*[\d.,]+/i',
            '/autorizado.*coordenação/i',
        ];

        $filtered = $observation;
        foreach ($patterns as $pattern) {
            $filtered = preg_replace($pattern, '', (string) $filtered);
        }

        $normalized = trim((string) $filtered);

        return $normalized !== '' ? $normalized : 'Informações disponíveis no prontuário';
    }

    public function formatChemotherapy(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'patient_person_id' => ! empty($row->patient_person_id) ? (string) $row->patient_person_id : null,
            'name' => $row->name ?? 'Quimioterapia',
            'secondary_info' => ! empty($row->secondary_info) ? $row->secondary_info : null,
            'protocol' => ! empty($row->extra1) ? $row->extra1 : null,
            'cycle' => ! empty($row->extra2) ? (int) $row->extra2 : null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
            'scheduled' => $row->schedule ?? null,
            'local' => ! empty($row->observation) ? $row->observation : null,
            'prescriber' => ! empty($row->professional_name) ? $row->professional_name : null,
            'dt_start' => $row->dt_start ? Carbon::parse($row->dt_start)->format('d/m/Y H:i') : null,
            'has_details' => ! empty($row->observation) || ! empty($row->professional_name),
        ];
    }

    private function formatGasotherapy(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'gas_code' => ! empty($row->gas_code) ? (string) $row->gas_code : null,
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'tipo_gas' => $row->tipo_gas ?? null,
            'modalidade' => $row->modalidade ?? null,
            'modo_administracao' => $row->modo_administracao ?? null,
            'quantidade' => $row->quantidade ?? null,
            'unidade' => $row->unidade ?? null,
            'fio2' => $row->fio2 ?? null,
            'fluxo_inspiratorio' => $row->fluxo_inspiratorio ?? null,
            'pip' => $row->pip ?? null,
            'peep' => $row->peep ?? null,
            'volume_corrente' => $row->volume_corrente ?? null,
            'freq_ventilatoria' => $row->freq_ventilatoria ?? null,
            'pressao_suporte' => $row->pressao_suporte ?? null,
            'equipamento_1' => $row->equipamento_1 ?? null,
            'equipamento_2' => $row->equipamento_2 ?? null,
            'equipamento_3' => $row->equipamento_3 ?? null,
            'horarios' => $row->horarios ?? null,
            'dt_inicio' => $row->dt_inicio ? Carbon::parse($row->dt_inicio)->format('d/m/Y') : null,
            'dt_fim' => $row->dt_fim ? Carbon::parse($row->dt_fim)->format('d/m/Y') : null,
            'urgente' => ($row->urgente ?? 'N') === 'S',
            'se_necessario' => ($row->se_necessario ?? 'N') === 'S',
            'a_criterio_medico' => ($row->a_criterio_medico ?? 'N') === 'S',
            'observacao' => ! empty($row->observacao) ? $row->observacao : null,
            'justificativa' => ! empty($row->justificativa) ? $row->justificativa : null,
            'prescriber' => $row->professional_name ?? null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
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
            'id' => (int) ($row->id ?? 0),
            'prescriber_id' => ! empty($row->prescriber_id) ? (string) $row->prescriber_id : null,
            'modalidade' => $modalidade,
            'sessoes_por_semana' => $row->sessoes_por_semana ?? null,
            'duracao_sessao' => $row->duracao_sessao ?? null,
            'dias_semana' => implode(', ', $diasSemana) ?: null,
            'fluxo_sangue' => $row->fluxo_sangue ?? null,
            'ktv' => $row->ktv ?? null,
            'ultrafiltracao' => $row->ultrafiltracao ?? null,
            'horarios' => $row->horarios ?? null,
            'dt_inicio' => $row->dt_inicio ? Carbon::parse($row->dt_inicio)->format('d/m/Y') : null,
            'dt_fim' => $row->dt_fim ? Carbon::parse($row->dt_fim)->format('d/m/Y') : null,
            'observacao' => ! empty($row->observacao) ? $row->observacao : null,
            'justificativa' => ! empty($row->justificativa) ? $row->justificativa : null,
            'prescriber' => $row->professional_name ?? null,
            'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
            'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
        ];
    }

    public function formatNutritionItem(object $row): array
    {
        $isFasting = in_array((string) ($row->flag1 ?? ''), ['J', 'S'], true)
            || ! empty($row->fasting_goal)
            || ! empty($row->fasting_type)
            || ! empty($row->fasting_goal_id)
            || ! empty($row->fasting_type_id)
            || ! empty($row->fasting_start)
            || ! empty($row->fasting_end);

        if ($isFasting) {
            $type = 'Fasting';
            $fastingLabel = collect([$row->fasting_goal ?? null, $row->fasting_type ?? null])
                ->filter()
                ->map(fn ($value) => trim((string) $value))
                ->implode(' · ');
            $displayName = $fastingLabel !== '' ? $fastingLabel : 'Jejum';
        } elseif (($row->flag3 ?? '') === 'S') {
            $type = 'Enteral';
            $displayName = $row->name ?? 'Nutrição Enteral';
        } elseif (($row->flag4 ?? '') === 'S') {
            $type = 'Special';
            $displayName = $row->name ?? 'Dieta Especial';
        } else {
            $type = 'Diet';
            $displayName = $row->name ?? 'Dieta';
        }

        $volume = null;
        if (! empty($row->qty)) {
            $volume = trim($row->qty.(! empty($row->unit_measure) ? ' '.$row->unit_measure : ''));
        }

        $products = [];
        foreach ([1, 2, 3, 4, 5] as $idx) {
            $productName = $row->{"product_{$idx}"} ?? null;
            if (empty($productName)) {
                continue;
            }

            $dose = $row->{"product_{$idx}_qty"} ?? null;
            $products[] = [
                'name' => trim((string) $productName),
                'dose' => ! empty($dose) ? trim((string) $dose) : null,
            ];
        }

        $deliveryMode = collect([
            ($row->flag7 ?? 'N') === 'S' ? 'Contínuo' : null,
            ($row->flag6 ?? 'N') === 'S' ? 'Bolus' : null,
            ($row->flag5 ?? 'N') === 'S' ? 'Bomba de infusão' : null,
            ($row->flag8 ?? 'N') === 'S' ? 'Leite materno' : null,
        ])->filter()->values()->all();

        return [
            'id' => (int) ($row->id ?? 0),
            'name' => $displayName,
            'type' => $type,
            'diet_code' => ! empty($row->diet_code) ? (string) $row->diet_code : null,
            'material_code' => ! empty($row->material_code) ? (string) $row->material_code : null,
            'is_fasting' => $isFasting,
            'observation' => ! empty($row->observation) ? $row->observation : null,
            'allergies' => ! empty($row->note_text) ? $row->note_text : null,
            'schedule' => $row->schedule ?? ($row->first_hour ?? null),
            'volume' => $volume,
            'total_volume' => ! empty($row->total_volume) ? trim((string) $row->total_volume) : null,
            'total_kcal' => ! empty($row->total_kcal) ? trim((string) $row->total_kcal) : null,
            'infusion_speed' => ! empty($row->infusion_speed) ? trim((string) $row->infusion_speed) : null,
            'route' => ! empty($row->route) ? trim((string) $row->route) : null,
            'route_code' => ! empty($row->route_code) ? trim((string) $row->route_code) : null,
            'interval_code' => ! empty($row->interval_code) ? trim((string) $row->interval_code) : null,
            'interval_description' => ! empty($row->interval_description)
                ? trim((string) $row->interval_description)
                : (! empty($row->interval_code) ? trim((string) $row->interval_code) : null),
            'guidance' => ! empty($row->guidance) ? $row->guidance : null,
            'justification' => ! empty($row->justification) ? $row->justification : null,
            'delivery_mode' => $deliveryMode,
            'products' => $products,
            'prescriber' => $row->professional_name ?? null,
            'nutritionist' => ! empty($row->nutritionist_name)
                ? trim((string) $row->nutritionist_name)
                : ($row->professional_name ?? null),
            'nutritionist_id' => ! empty($row->nutritionist_id) ? (string) $row->nutritionist_id : null,
            'fasting_goal' => ! empty($row->fasting_goal) ? trim((string) $row->fasting_goal) : null,
            'fasting_type' => ! empty($row->fasting_type) ? trim((string) $row->fasting_type) : null,
            'fasting_start' => ! empty($row->fasting_start ?? null) ? (string) $row->fasting_start : null,
            'fasting_end' => ! empty($row->fasting_end ?? null) ? (string) $row->fasting_end : null,
            'dt_start' => ! empty($row->dt_start ?? null) ? Carbon::parse($row->dt_start)->format('d/m/Y') : null,
            'dt_end' => ! empty($row->dt_end ?? null) ? Carbon::parse($row->dt_end)->format('d/m/Y') : null,
            'has_details' => ! empty($row->observation)
                || ! empty($row->note_text)
                || ! empty($row->guidance)
                || ! empty($row->justification)
                || ! empty($row->route)
                || ! empty($row->infusion_speed)
                || ! empty($row->total_kcal)
                || ! empty($row->nutritionist_name)
                || ! empty($row->fasting_goal)
                || ! empty($row->fasting_type)
                || ! empty($products),
        ];
    }

    public function organizeNutritionByShift($nutrition): array
    {
        $items = $nutrition->map(fn ($r) => $this->formatNutritionItem($r))->values()->all();

        return ['count' => count($items), 'items' => $items];
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

    // ==================== BATCH QUERIES (pending event handlers) ====================

    /**
     * Hemotherapy scheduled in the next 48h for a chunk of attendances.
     * Used by HemotherapyPendingHandler.
     *
     * @param  int[]  $chunk  Already-chunked attendance numbers (max ~200)
     * @return \stdClass[]
     */
    public function queryHemotherapyChunk(array $chunk): array
    {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        return DB::connection('tasy')->select("
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
            WHERE ch.nr_atendimento IN ({$placeholders})
              AND ch.dt_programada BETWEEN SYSDATE AND SYSDATE + 2
              AND ch.dt_suspensao IS NULL
        ", $chunk);
    }

    /**
     * Chemotherapy sessions scheduled in the next 30 days for a chunk of attendances.
     * Used by ChemotherapyPendingHandler.
     *
     * @param  int[]  $chunk
     * @return \stdClass[]
     */
    public function queryChemotherapyChunk(array $chunk): array
    {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        return DB::connection('tasy')->select("
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
            WHERE ap.nr_atendimento IN ({$placeholders})
                AND aq.dt_agenda BETWEEN SYSDATE AND SYSDATE + 30
            ORDER BY ap.nr_atendimento, aq.dt_agenda
        ", $chunk);
    }

    /**
     * Pending antibiotic administration slots for today, for a chunk of attendances.
     * Used by AntibioticPendingHandler.
     *
     * Returns one row per pending slot (already filtered by HAVING priority < 400).
     *
     * @param  int[]  $chunk
     * @return \stdClass[]
     */
    public function queryAntibioticsChunk(array $chunk): array
    {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        return DB::connection('tasy')->select("
            WITH base AS (
                SELECT
                    cm.nr_atendimento,
                    cm.nr_sequencia                                                          AS med_id,
                    INITCAP(TRIM(REGEXP_REPLACE(m.ds_material, '\\s*&&\\s*\$', '')))        AS descricao,
                    pmh.dt_horario,
                    cm.qt_dose,
                    cm.cd_unidade_medida                                                     AS cd_unidade_medida_dose,
                    cm.ie_via_aplicacao,
                    cm.nr_dia_util,
                    pma.ie_alteracao,
                    CASE pma.ie_alteracao
                        WHEN 3  THEN 600
                        WHEN 58 THEN 500
                        WHEN 8  THEN 400
                        WHEN 38 THEN 300
                        WHEN 4  THEN 200
                        WHEN 10 THEN  30
                        WHEN 15 THEN  20
                        ELSE           1
                    END                                                                      AS priority
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
                WHERE cm.nr_atendimento IN ({$placeholders})
                  AND mf.ie_antimicrobiano = 'S'
                  AND cm.dt_liberacao      IS NOT NULL
                  AND cm.dt_suspensao      IS NULL
                  AND TRUNC(pmh.dt_horario) = TRUNC(SYSDATE)
            ),
            grouped AS (
                SELECT
                    nr_atendimento,
                    med_id,
                    descricao,
                    dt_horario,
                    qt_dose,
                    cd_unidade_medida_dose,
                    ie_via_aplicacao,
                    nr_dia_util,
                    MAX(priority) AS priority,
                    MAX(ie_alteracao) KEEP (DENSE_RANK LAST ORDER BY priority NULLS FIRST) AS ie_alteracao_code
                FROM base
                GROUP BY nr_atendimento, med_id, descricao, dt_horario,
                         qt_dose, cd_unidade_medida_dose, ie_via_aplicacao, nr_dia_util
                HAVING MAX(priority) < 400
            )
            SELECT
                g.*,
                vd.ds_valor_dominio AS ds_alteracao_label
            FROM grouped g
            LEFT JOIN tasy.valor_dominio vd
                ON vd.cd_dominio = 1620
               AND vd.vl_dominio = TO_CHAR(g.ie_alteracao_code)
            ORDER BY g.nr_atendimento, g.dt_horario
        ", $chunk);
    }

    /**
     * Scheduled surgeries and exams (agenda_paciente) in the next 30 days for a chunk.
     * Used by AgendaPendingHandler.
     *
     * @param  int[]  $chunk
     * @return \stdClass[]
     */
    public function queryAgendaChunk(array $chunk): array
    {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        return DB::connection('tasy')->select("
            SELECT
                atp.nr_atendimento,
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
                ap.ie_status_agenda,
                vd_ag_stat.ds_valor_dominio AS ds_status_agenda_label,
                vd_ag_car.ds_valor_dominio  AS ds_carater_label,
                ap.nr_seq_proc_interno,
                ap.nr_seq_sala,
                NVL(
                    TASY.OBTER_SETOR_PRESCR_AGENDA(ap.nr_sequencia),
                    NVL(TASY.OBTER_SETOR_AGENDA(ap.cd_agenda), ap.cd_setor_atendimento)
                ) AS cd_setor_execucao,
                TASY.OBTER_DS_SETOR_ATENDIMENTO(
                    NVL(
                        TASY.OBTER_SETOR_PRESCR_AGENDA(ap.nr_sequencia),
                        NVL(TASY.OBTER_SETOR_AGENDA(ap.cd_agenda), ap.cd_setor_atendimento)
                    )
                ) AS ds_setor_execucao,
                COALESCE(
                    TASY.OBTER_TIPO_CIRUR_PROC(ap.nr_seq_proc_interno),
                    (
                        SELECT MAX(p.cd_tipo_procedimento)
                        FROM tasy.procedimento p
                        WHERE p.cd_procedimento = ap.cd_procedimento
                          AND p.ie_origem_proced = ap.ie_origem_proced
                    )
                ) AS cd_tipo_cirurgia,
                COALESCE(
                    CASE
                        WHEN ap.ie_carater_cirurgia IS NOT NULL AND ap.ie_carater_cirurgia <> 'X'
                        THEN TASY.OBTER_CIRURGIA_PACIENTE(ap.nr_atendimento, 'AA')
                        ELSE NULL
                    END,
                    pi.ds_proc_exame,
                    proced.ds_procedimento,
                    ap.ds_cirurgia
                )                                        AS descricao_proc,
                pi.nr_seq_exame_lab
            FROM tasy.agenda_paciente ap
            JOIN tasy.atendimento_paciente atp
                ON atp.cd_pessoa_fisica = ap.cd_pessoa_fisica
            -- Status domain 83: agenda_paciente statuses
            LEFT JOIN tasy.valor_dominio vd_ag_stat
                ON vd_ag_stat.cd_dominio = 83
               AND vd_ag_stat.vl_dominio = ap.ie_status_agenda
            -- Caráter domain 1016: cirurgia character
            LEFT JOIN tasy.valor_dominio vd_ag_car
                ON vd_ag_car.cd_dominio = 1016
               AND vd_ag_car.vl_dominio = ap.ie_carater_cirurgia
            LEFT JOIN tasy.proc_interno pi
                ON pi.nr_sequencia = ap.nr_seq_proc_interno
            LEFT JOIN (
                SELECT cd_procedimento, MIN(ds_procedimento) AS ds_procedimento
                FROM tasy.procedimento
                GROUP BY cd_procedimento
            ) proced ON proced.cd_procedimento = ap.cd_procedimento
                     AND ap.nr_seq_proc_interno IS NULL
            WHERE atp.nr_atendimento IN ({$placeholders})
                AND ap.dt_agenda >= TRUNC(SYSDATE)
                AND ap.dt_agenda <= SYSDATE + 30
                AND ap.ie_status_agenda NOT IN ('C', 'S', 'CR', 'E', 'AD')
                AND ap.dt_executada IS NULL
                AND (
                    (ap.ie_carater_cirurgia IS NOT NULL AND ap.ie_carater_cirurgia <> 'X')
                    OR
                    (ap.ie_carater_cirurgia IS NULL
                     AND (ap.nr_seq_proc_interno IS NOT NULL OR ap.cd_procedimento IS NOT NULL))
                )
            ORDER BY ap.nr_atendimento, ap.dt_agenda, ap.hr_inicio NULLS LAST
        ", $chunk);
    }
}
