<?php

namespace App\Repositories\EMR;

use App\Models\EMR\Core\Patient;
use App\Models\EMR\CPOE\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientClinicalRepository
{
    protected const CHUNK_SIZE = 200;
    protected $connection = 'tasy';

    /**
     * Busca detalhes clínicos completos de um paciente
     */
    public function getPatientClinicalDetails($attendanceNumber, $sectorCode = null)
    {
        $result = DB::connection('tasy')->select("
            SELECT
                -- Isolamento
                COALESCE(
                    (SELECT DECODE(COUNT(nr_sequencia), 0, 'Não', 'Sim')
                     FROM tasy.atendimento_precaucao
                     WHERE nr_atendimento = atp.nr_atendimento
                       AND SYSDATE BETWEEN dt_inicio AND NVL(dt_termino, SYSDATE)
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL),
                    'Não'
                ) AS medida_bloqueio,

                -- Motivos de isolamento
                COALESCE(
                    (SELECT LISTAGG(cp.ds_precaucao || ' - ' || mi.ds_motivo, '; ') WITHIN GROUP (ORDER BY 1)
                     FROM tasy.atendimento_precaucao ap,
                          tasy.motivo_isolamento mi,
                          tasy.cih_precaucao cp
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND SYSDATE BETWEEN ap.dt_inicio AND NVL(ap.dt_termino, SYSDATE)
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL
                       AND ap.nr_seq_motivo_isol = mi.nr_sequencia
                       AND ap.nr_seq_precaucao = cp.nr_sequencia),
                    'Nenhum motivo de isolamento'
                ) AS motivos_isolamento,

                -- Avaliação de enfermagem
                COALESCE(
                    (SELECT TO_CHAR(MAX(ap.dt_avaliacao), 'DD/MM/YYYY HH24:MI')
                     FROM tasy.med_avaliacao_paciente ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND ap.nr_seq_tipo_avaliacao = 7781
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL),
                    'Não realizada'
                ) AS avaliacao_enf,

                -- Plano educacional
                COALESCE(
                    (SELECT TO_CHAR(TRUNC(MAX(ap.dt_avaliacao)), 'DD/MM/YYYY')
                     FROM tasy.med_avaliacao_paciente ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND ap.nr_seq_tipo_avaliacao = 793
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL),
                    'Não realizado'
                ) AS plano_educ,

                -- PE
                COALESCE(
                    (SELECT TO_CHAR(MAX(dt_prescricao), 'DD/MM/YYYY')
                     FROM tasy.PE_PRESCRICAO
                     WHERE nr_atendimento = atp.nr_atendimento),
                    'Não realizado'
                ) AS pe_data,

                -- Histórico de queda
                COALESCE(
                    (SELECT CASE
                        WHEN a.ie_hist_queda = 'S'
                        THEN TO_CHAR(a.dt_avaliacao, 'DD/MM/YYYY HH24:MI:SS')
                        ELSE 'Não'
                     END
                     FROM tasy.escala_morse a
                     WHERE a.dt_liberacao IS NOT NULL
                       AND a.nr_atendimento = atp.nr_atendimento
                       AND a.nr_sequencia = (
                           SELECT MAX(b.nr_sequencia)
                           FROM tasy.escala_morse b
                           WHERE b.dt_liberacao IS NOT NULL
                             AND b.nr_atendimento = a.nr_atendimento)),
                    'Não avaliado'
                ) AS ds_queda,

                -- Diagnósticos SAE
                COALESCE(
                    (SELECT SUBSTR(LISTAGG(SUBSTR(tasy.PE_obter_desc_diag(d.nr_seq_diag, 'DI'), 1, 100), ' | ')
                            WITHIN GROUP (ORDER BY 1), 1, 400)
                     FROM tasy.pe_prescr_diag d
                     JOIN tasy.pe_prescricao p ON d.nr_seq_prescr = p.nr_sequencia
                     WHERE p.nr_atendimento = atp.nr_atendimento
                       AND p.dt_prescricao = (SELECT MAX(c.dt_prescricao)
                                              FROM tasy.pe_prescricao c
                                              WHERE c.nr_atendimento = atp.nr_atendimento)
                     AND ROWNUM <= 3),
                    'Sem diagnósticos SAE'
                ) AS diag,

                -- Dispositivos
                COALESCE(
                    (SELECT LISTAGG(SUBSTR(tasy.obter_descricao_padrao('DISPOSITIVO', 'DS_DISPOSITIVO', NR_SEQ_DISPOSITIVO), 1, 60), ' | ')
                            WITHIN GROUP (ORDER BY 1)
                     FROM tasy.ATEND_PAC_DISPOSITIVO
                     WHERE nr_atendimento = atp.nr_atendimento
                       AND dt_retirada IS NULL),
                    'Nenhum dispositivo'
                ) AS dispositivos,

                -- Alergias
                COALESCE(
                    (SELECT REGEXP_REPLACE(
                        SUBSTR(ds_alergias, 1, 300),
                        ' - (Não informado|desconhecido|N/A)[^;]*', '', 1, 0, 'i')
                     FROM tasy.W_PAN_PACIENTE
                     WHERE cd_pessoa_paciente = atp.cd_pessoa_fisica
                     AND ROWNUM = 1),
                    'Sem alergias registradas'
                ) AS alergias_detalhadas

            FROM tasy.atendimento_paciente atp
            WHERE atp.nr_atendimento = :attendance
        ", ['attendance' => $attendanceNumber]);

        if (empty($result)) {
            return $this->getDefaultClinicalData();
        }

        $basicData = $result[0];

        // Busca diagnósticos e comorbidades
        $basicData->diagnosticos_comorbidades = $this->getDiagnosticsAndComorbidities($attendanceNumber);

        // Busca antimicrobianos
        $basicData->materiais = $this->getAntimicrobials($attendanceNumber) ?? 'Nenhum antimicrobiano';

        // Busca exames prioritários
        $basicData->prioridade_exames = $this->getPriorityExams($attendanceNumber) ?? 'Nenhum exame prioritário';

        // Busca procedimentos cirúrgicos
        $basicData->procedimentos_cirurgicos = $this->getSurgicalProcedures($attendanceNumber, $sectorCode) ?? [];

        return $basicData;
    }

    /**
     * ✅ NOVO: Busca detalhes clínicos de múltiplos pacientes em batch
     * Substitui loop de getPatientClinicalDetails individual
     */
    public function getBatchClinicalDetails(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        // Dividir em chunks para evitar queries muito grandes
        $chunks = array_chunk($attendanceNumbers, 100);
        $allResults = [];

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            // ✅ Query unificada para dados básicos
            $results = DB::connection($this->connection)->select("
            SELECT
                atp.nr_atendimento,
                -- Isolamento
                COALESCE(
                    (SELECT DECODE(COUNT(nr_sequencia), 0, 'Não', 'Sim')
                     FROM tasy.atendimento_precaucao
                     WHERE nr_atendimento = atp.nr_atendimento
                       AND SYSDATE BETWEEN dt_inicio AND NVL(dt_termino, SYSDATE)
                       AND dt_liberacao IS NOT NULL
                       AND dt_inativacao IS NULL),
                    'Não'
                ) AS medida_bloqueio,

                -- Motivos de isolamento
                COALESCE(
                    (SELECT LISTAGG(cp.ds_precaucao || ' - ' || mi.ds_motivo, '; ') WITHIN GROUP (ORDER BY 1)
                     FROM tasy.atendimento_precaucao ap,
                          tasy.motivo_isolamento mi,
                          tasy.cih_precaucao cp
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND SYSDATE BETWEEN ap.dt_inicio AND NVL(ap.dt_termino, SYSDATE)
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL
                       AND ap.nr_seq_motivo_isol = mi.nr_sequencia
                       AND ap.nr_seq_precaucao = cp.nr_sequencia),
                    'Nenhum motivo de isolamento'
                ) AS motivos_isolamento,

                -- Avaliação de enfermagem
                COALESCE(
                    (SELECT TO_CHAR(MAX(ap.dt_avaliacao), 'DD/MM/YYYY HH24:MI')
                     FROM tasy.med_avaliacao_paciente ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND ap.nr_seq_tipo_avaliacao = 7781
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL),
                    'Não realizada'
                ) AS avaliacao_enf,

                -- Plano educacional
                COALESCE(
                    (SELECT TO_CHAR(TRUNC(MAX(ap.dt_avaliacao)), 'DD/MM/YYYY')
                     FROM tasy.med_avaliacao_paciente ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND ap.nr_seq_tipo_avaliacao = 793
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL),
                    'Não realizado'
                ) AS plano_educ,

                -- PE
                COALESCE(
                    (SELECT TO_CHAR(MAX(dt_prescricao), 'DD/MM/YYYY')
                     FROM tasy.PE_PRESCRICAO
                     WHERE nr_atendimento = atp.nr_atendimento),
                    'Não realizado'
                ) AS pe_data,

                -- Histórico de queda
                COALESCE(
                    (SELECT CASE
                        WHEN a.ie_hist_queda = 'S'
                        THEN TO_CHAR(a.dt_avaliacao, 'DD/MM/YYYY HH24:MI:SS')
                        ELSE 'Não'
                     END
                     FROM tasy.escala_morse a
                     WHERE a.dt_liberacao IS NOT NULL
                       AND a.nr_atendimento = atp.nr_atendimento
                       AND a.nr_sequencia = (
                           SELECT MAX(b.nr_sequencia)
                           FROM tasy.escala_morse b
                           WHERE b.dt_liberacao IS NOT NULL
                             AND b.nr_atendimento = a.nr_atendimento)),
                    'Não avaliado'
                ) AS ds_queda,

                -- Diagnósticos SAE
                COALESCE(
                    (SELECT SUBSTR(LISTAGG(SUBSTR(tasy.PE_obter_desc_diag(d.nr_seq_diag, 'DI'), 1, 100), ' | ')
                            WITHIN GROUP (ORDER BY 1), 1, 400)
                     FROM tasy.pe_prescr_diag d
                     JOIN tasy.pe_prescricao p ON d.nr_seq_prescr = p.nr_sequencia
                     WHERE p.nr_atendimento = atp.nr_atendimento
                       AND p.dt_prescricao = (SELECT MAX(c.dt_prescricao)
                                              FROM tasy.pe_prescricao c
                                              WHERE c.nr_atendimento = atp.nr_atendimento)
                     AND ROWNUM <= 3),
                    'Sem diagnósticos SAE'
                ) AS diag,

                -- Dispositivos
                COALESCE(
                    (SELECT LISTAGG(SUBSTR(tasy.obter_descricao_padrao('DISPOSITIVO', 'DS_DISPOSITIVO', NR_SEQ_DISPOSITIVO), 1, 60), ' | ')
                            WITHIN GROUP (ORDER BY 1)
                     FROM tasy.ATEND_PAC_DISPOSITIVO
                     WHERE nr_atendimento = atp.nr_atendimento
                       AND dt_retirada IS NULL),
                    'Nenhum dispositivo'
                ) AS dispositivos,

                -- Alergias
                COALESCE(
                    (SELECT REGEXP_REPLACE(
                        SUBSTR(ds_alergias, 1, 300),
                        ' - (Não informado|desconhecido|N/A)[^;]*', '', 1, 0, 'i')
                     FROM tasy.W_PAN_PACIENTE
                     WHERE cd_pessoa_paciente = atp.cd_pessoa_fisica
                     AND ROWNUM = 1),
                    'Sem alergias registradas'
                ) AS alergias_detalhadas

            FROM tasy.atendimento_paciente atp
            WHERE atp.nr_atendimento IN ($placeholders)
        ", $chunk);

            foreach ($results as $result) {
                $allResults[$result->nr_atendimento] = $result;
            }
        }

        // ✅ Busca diagnósticos em batch
        $diagnostics = $this->getBatchDiagnostics($attendanceNumbers);

        // ✅ Busca antimicrobianos em batch
        $antimicrobials = $this->getBatchAntimicrobials($attendanceNumbers);

        // ✅ Busca exames em batch (já existe)
        $exams = $this->getPriorityExamsForAttendances($attendanceNumbers);

        // ✅ Busca cirurgias em batch (já existe)
        $surgeries = $this->getFutureSurgeriesForAttendances($attendanceNumbers);

        // Monta resultado final
        $finalResults = [];
        foreach ($attendanceNumbers as $nr) {
            $basic = $allResults[$nr] ?? $this->getDefaultClinicalData();

            $basic->diagnosticos_comorbidades = $diagnostics[$nr] ?? 'Sem diagnósticos';
            $basic->materiais = $antimicrobials[$nr] ?? 'Nenhum antimicrobiano';
            $basic->prioridade_exames = $exams[$nr] ?? 'Nenhum exame prioritário';
            $basic->procedimentos_cirurgicos = $surgeries['surgery_detailed'][$nr] ?? [];

            $finalResults[$nr] = $basic;
        }

        return $finalResults;
    }

    private function getBatchDiagnostics(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) return [];

        $chunks = array_chunk($attendanceNumbers, 100);
        $results = [];

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection($this->connection)->select("
            SELECT
                nr_atendimento,
                COALESCE(tasy.obter_diagnostico_atendimento(nr_atendimento, 0), 'Sem diagnósticos') AS diagnosticos
            FROM (
                SELECT COLUMN_VALUE as nr_atendimento
                FROM TABLE(SYS.ODCINUMBERLIST(" . implode(',', $chunk) . "))
            )
        ");

            foreach ($rows as $row) {
                $results[$row->nr_atendimento] = $row->diagnosticos;
            }
        }

        return $results;
    }

    private function getBatchAntimicrobials(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) return [];

        $chunks = array_chunk($attendanceNumbers, 50); // Menor por ser query pesada
        $results = [];

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection($this->connection)->select("
            SELECT
                pm.nr_atendimento,
                LISTAGG(INITCAP(m.ds_material) || ' ' || pt.nr_dia_util || ' Dia(s)', CHR(13))
                    WITHIN GROUP(ORDER BY m.ds_material) AS materiais
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
            AND pm.dt_prescricao = (
                SELECT MAX(dt_prescricao)
                FROM tasy.prescr_medica a
                JOIN tasy.prescr_material b ON a.nr_prescricao = b.nr_prescricao
                WHERE a.nr_atendimento = pm.nr_atendimento
                AND b.cd_material = pt.cd_material
                AND a.dt_liberacao IS NOT NULL
                AND a.dt_validade_prescr >= SYSDATE
                AND a.dt_suspensao IS NULL
            )
            AND pt.dt_suspensao IS NULL
            AND pt.nr_dia_util IS NOT NULL
            GROUP BY pm.nr_atendimento
        ", $chunk);

            foreach ($rows as $row) {
                $results[$row->nr_atendimento] = $row->materiais;
            }
        }

        return $results;
    }

    /**
     * Retorna dados clínicos padrão quando não há dados
     */
    private function getDefaultClinicalData()
    {
        return (object)[
            'medida_bloqueio' => 'Não',
            'motivos_isolamento' => 'Nenhum motivo de isolamento',
            'avaliacao_enf' => 'Não realizada',
            'plano_educ' => 'Não realizado',
            'pe_data' => 'Não realizado',
            'ds_queda' => 'Não avaliado',
            'diag' => 'Sem diagnósticos SAE',
            'dispositivos' => 'Nenhum dispositivo',
            'alergias_detalhadas' => 'Sem alergias registradas',
            'diagnosticos_comorbidades' => 'Sem diagnósticos',
            'materiais' => 'Nenhum antimicrobiano',
            'prioridade_exames' => 'Nenhum exame prioritário',
            'procedimentos_cirurgicos' => []
        ];
    }

    /**
     * Return two maps for the given attendance numbers:
     *  - 'surgery' => [nr_atendimento => bool has_surgery]
     *  - 'surgery_detailed' => [nr_atendimento => array of surgery detail arrays]
     */
    public function getFutureSurgeriesForAttendances(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return ['surgery' => [], 'surgery_detailed' => []];
        }

        // map attendance -> person id
        $attToPerson = Patient::whereIn('nr_atendimento', $attendanceNumbers)
            ->pluck('cd_pessoa_fisica', 'nr_atendimento')
            ->toArray();

        $personIds = array_values(array_filter(array_unique(array_values($attToPerson))));

        $hasMap = [];
        $detailed = [];

        // default values
        foreach ($attendanceNumbers as $nr) {
            $hasMap[$nr] = false;
            $detailed[$nr] = [];
        }

        if (empty($personIds)) {
            return ['surgery' => $hasMap, 'surgery_detailed' => $detailed];
        }

        // limit to 1 month ahead
        $cutoff = Carbon::now()->addMonth();

        $surgeriesByPerson = Appointment::surgeries()
            ->whereIn('cd_pessoa_fisica', $personIds)
            ->where('dt_agenda', '>=', Carbon::today())
            ->where('dt_agenda', '<=', $cutoff)
            ->whereNull('dt_executada')
            ->orderBy('dt_agenda')
            ->orderBy('hr_inicio')
            ->get()
            ->groupBy('cd_pessoa_fisica');

        foreach ($attToPerson as $nr => $personId) {
            $group = $surgeriesByPerson->get($personId, collect());
            if ($group->isNotEmpty()) {
                $hasMap[$nr] = true;
                $detailed[$nr] = $group->map(function (Appointment $s) {
                    $proc = $s->getProcedureDescriptionAttribute() ?? ($s->ds_cirurgia ?? 'Procedimento cirúrgico');
                    return [
                        'data_agenda' => $s->dt_agenda ? Carbon::parse($s->dt_agenda)->format('d/m/Y') : '',
                        'hora_agenda' => $s->hr_inicio ? Carbon::parse($s->hr_inicio)->format('H:i') : '00:00',
                        'procedimento' => $proc,
                        'carater_cirurgia' => $s->getSurgeryCharacterAttribute() ?? ($s->ie_carater_cirurgia ?? ''),
                        'observacoes' => $s->ds_observacao ?? '',
                        // keep extra fields used by views if present
                        'nr_sequencia' => $s->nr_sequencia,
                        'cd_procedimento' => $s->cd_procedimento,
                        'ie_origem_proced' => $s->ie_origem_proced,
                    ];
                })->values()->all();
            }
        }

        return ['surgery' => $hasMap, 'surgery_detailed' => $detailed];
    }

    /**
     * Busca alertas ativos de um paciente
     * Usado pela Patient::fetchPatientClinicalDetails()
     */
    public function getPatientActiveAlerts($attendanceNumber, $personId)
    {
        $results = DB::connection('tasy')->select("
            SELECT DISTINCT
                alp.ds_alerta,
                alp.nr_seq_tipo_alerta,
                alp.dt_fim_alerta,
                mi.ds_motivo AS motivo_isolamento,
                apc.dt_inicio AS dt_inicio_precaucao,
                apc.dt_termino AS dt_termino_precaucao,
                CASE
                    WHEN alp.ds_alerta IS NOT NULL THEN 1
                    WHEN mi.ds_motivo IS NOT NULL THEN 2
                    ELSE 3
                END AS alert_priority
            FROM tasy.atendimento_paciente atp
            LEFT JOIN tasy.alerta_paciente alp
                ON atp.cd_pessoa_fisica = alp.cd_pessoa_fisica
                AND alp.ds_alerta IS NOT NULL
                AND LENGTH(TRIM(alp.ds_alerta)) > 0
                AND (alp.dt_fim_alerta IS NULL OR TRUNC(alp.dt_fim_alerta) > TRUNC(SYSDATE))
            LEFT JOIN tasy.atendimento_precaucao apc
                ON atp.nr_atendimento = apc.nr_atendimento
                AND apc.dt_liberacao IS NOT NULL
                AND apc.dt_inativacao IS NULL
                AND (apc.dt_termino IS NULL OR TRUNC(apc.dt_termino) > TRUNC(SYSDATE))
            LEFT JOIN tasy.motivo_isolamento mi
                ON apc.nr_seq_motivo_isol = mi.nr_sequencia
            WHERE atp.nr_atendimento = :attendance
              AND atp.cd_pessoa_fisica = :person_id
              AND atp.dt_alta IS NULL
              AND (alp.ds_alerta IS NOT NULL OR mi.ds_motivo IS NOT NULL)
            ORDER BY alert_priority
        ", [
            'attendance' => $attendanceNumber,
            'person_id' => $personId
        ]);

        $alerts = [];
        $processedAlerts = [];

        foreach ($results as $result) {
            // Alerta tipo ALERTA
            if (!empty($result->ds_alerta)) {
                $alertKey = 'alert_' . md5(trim($result->ds_alerta));
                if (!isset($processedAlerts[$alertKey])) {
                    $alerts[] = [
                        'type' => 'ALERTA',
                        'message' => trim($result->ds_alerta),
                        'end_date' => $result->dt_fim_alerta,
                        'severity' => 'warning'
                    ];
                    $processedAlerts[$alertKey] = true;
                }
            }

            // Alerta tipo ISOLAMENTO
            if (!empty($result->motivo_isolamento)) {
                $isolationKey = 'isolation_' . md5(trim($result->motivo_isolamento));
                if (!isset($processedAlerts[$isolationKey])) {
                    $alerts[] = [
                        'type' => 'ISOLAMENTO',
                        'message' => trim($result->motivo_isolamento),
                        'start_date' => $result->dt_inicio_precaucao,
                        'end_date' => $result->dt_termino_precaucao,
                        'severity' => 'danger'
                    ];
                    $processedAlerts[$isolationKey] = true;
                }
            }
        }

        return array_values($alerts);
    }

    /**
     * Diagnósticos e comorbidades
     */
    private function getDiagnosticsAndComorbidities($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT COALESCE(tasy.obter_diagnostico_atendimento(:nr_atendimento, 0), 'Sem diagnósticos') AS diagnosticos
            FROM dual
        ", ['nr_atendimento' => $attendanceNumber]);

        return $result ? $result[0]->diagnosticos : 'Sem diagnósticos';
    }

    /**
     * Antimicrobianos com valor padrão
     */
    private function getAntimicrobials($attendanceNumber)
    {
        $result = DB::connection('tasy')->select("
            SELECT LISTAGG(INITCAP(ds_material) || ' ' || nr_dia_util || ' Dia(s)', CHR(13))
                WITHIN GROUP(ORDER BY ds_material) AS materiais
            FROM (
                SELECT DISTINCT
                    m.ds_material,
                    pt.nr_dia_util,
                    pm.dt_prescricao
                FROM tasy.material m,
                     tasy.medic_ficha_tecnica mf,
                     tasy.prescr_medica pm,
                     tasy.prescr_material pt
                WHERE mf.nr_sequencia = (
                    SELECT nr_seq_ficha_tecnica
                    FROM tasy.material
                    WHERE cd_material = m.cd_material_estoque
                )
                AND m.cd_material = pt.cd_material
                AND pm.nr_prescricao = pt.nr_prescricao
                AND mf.ie_antimicrobiano = 'S'
                AND pm.dt_prescricao = (
                    SELECT MAX(dt_prescricao)
                    FROM tasy.prescr_medica a,
                         tasy.prescr_material b
                    WHERE nr_atendimento = pm.nr_atendimento
                    AND a.nr_prescricao = b.nr_prescricao
                    AND b.cd_material = pt.cd_material
                    AND a.dt_liberacao IS NOT NULL
                    AND a.dt_validade_prescr >= SYSDATE
                    AND a.dt_suspensao IS NULL
                )
                AND pt.dt_suspensao IS NULL
                AND pt.nr_dia_util IS NOT NULL
                AND pm.nr_atendimento = :nr_atendimento
            )
        ", ['nr_atendimento' => $attendanceNumber]);

        return (!empty($result) && !empty($result[0]->materiais)) ? $result[0]->materiais : null;
    }

    public function getPriorityExamsForAttendances(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $result = [];
        // initialize defaults
        foreach ($attendanceNumbers as $nr) {
            $result[$nr] = null;
        }

        // call existing private getPriorityExams per attendance
        foreach ($attendanceNumbers as $nr) {
            try {
                $result[$nr] = $this->getPriorityExams($nr);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('getPriorityExamsForAttendances failed for ' . $nr . ': ' . $e->getMessage());
                $result[$nr] = null;
            }
        }

        return $result;
    }

    /**
     * Exames prioritários com valor padrão
     */
    private function getPriorityExams($attendanceNumber)
    {
        try {
            $result = DB::connection('tasy')->select("
                SELECT
                    tasy.obter_select_concatenado_bv(
                        'select obter_valor_dominio(95,proc.cd_tipo_procedimento) tipo
                        from prescr_procedimento prescrp,
                            prescr_medica prescrm,
                            procedimento proc
                        where prescrp.nr_prescricao = prescrm.nr_prescricao
                        and prescrp.cd_procedimento = proc.cd_procedimento
                        and prescrm.nr_atendimento = :nr_atend
                        and prescrp.ie_status_execucao = ''10''
                        and prescrp.dt_coleta is null
                        and prescrm.dt_liberacao is not null
                        and prescrp.ie_origem_proced <> 4
                        group by obter_valor_dominio(95,proc.cd_tipo_procedimento)',
                        'nr_atend=' || :nr_atendimento,
                        CHR(13)
                    ) AS prioridade_exames
                FROM dual
            ", ['nr_atendimento' => $attendanceNumber]);

            if ($result && !empty($result[0]->prioridade_exames)) {
                $exams = $result[0]->prioridade_exames;
                $exams = str_replace(chr(13), chr(13) . '• ', $exams);
                if (!empty(trim($exams))) {
                    $exams = '• ' . $exams;
                }
                return $exams;
            }

            return null;

        } catch (\Exception $e) {
            Log::warning("Error fetching priority exams for attendance {$attendanceNumber}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Procedimentos cirúrgicos - agora usa exatamente a mesma lógica do card
     */
    private function getSurgicalProcedures($attendanceNumber, $sectorCode = null)
    {
        try {
            // USA EXATAMENTE A MESMA LÓGICA DO CARD
            $query = "
                SELECT
                    SUBSTR(
                        TASY.obter_desc_agenda(aaa.cd_agenda) ||
                        ' / Data e hora: ' || TO_CHAR(aaa.dt_agenda, 'DD/MM/YYYY') ||
                        CASE WHEN aaa.hr_inicio IS NOT NULL THEN ' ' || TO_CHAR(aaa.hr_inicio, 'HH24:MI') ELSE ' 00:00' END ||
                        ' - Proced.: ' || TASY.obter_descricao_procedimento(aaa.cd_procedimento, aaa.ie_origem_proced),
                        1, 200
                    ) AS ds_agenda_contat,
                    aaa.hr_inicio,
                    aaa.dt_agenda,
                    aaa.cd_procedimento,
                    aaa.ie_origem_proced,
                    aaa.ie_carater_cirurgia,
                    aaa.ds_observacao
                FROM tasy.atendimento_paciente atp
                JOIN tasy.agenda_paciente aaa ON atp.cd_pessoa_fisica = aaa.cd_pessoa_fisica
                WHERE atp.nr_atendimento = :nr_atendimento
                AND aaa.IE_CARATER_CIRURGIA IS NOT NULL
                AND aaa.IE_CARATER_CIRURGIA <> 'X'
                AND aaa.dt_agenda > SYSDATE
                ORDER BY aaa.dt_agenda, aaa.hr_inicio
            ";

            $params = ['nr_atendimento' => $attendanceNumber];
            $rows = DB::connection('tasy')->select($query, $params);

            if (empty($rows)) {
                return [];
            }

            return array_map(function($row) {
                return [
                    'procedimento' => $row->ds_agenda_contat ?? 'Cirurgia agendada',
                    'data_agenda' => $row->dt_agenda ? \Carbon\Carbon::parse($row->dt_agenda)->format('d/m/Y') : 'Data não informada',
                    'hora_agenda' => $row->hr_inicio ? \Carbon\Carbon::parse($row->hr_inicio)->format('H:i') : '00:00',
                    'status' => 'AGENDADA',
                    'tipo_agendamento' => 'Cirúrgico',
                    'carater_cirurgia' => $this->getCaraterCirurgiaDescription($row->ie_carater_cirurgia ?? ''),
                    'observacoes' => $this->filterSensitiveData($row->ds_observacao ?? ''),
                    'duracao_formatada' => 'A definir',
                    'cd_procedimento' => $row->cd_procedimento,
                    'ie_origem_proced' => $row->ie_origem_proced
                ];
            }, $rows);

        } catch (\Exception $e) {
            Log::warning("Erro ao buscar procedimentos cirúrgicos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Método auxiliar para obter descrição do caráter da cirurgia
     */
    private function getCaraterCirurgiaDescription($carater)
    {
        return match($carater) {
            'E' => 'Eletiva',
            'U' => 'Urgência',
            'G' => 'Emergência',
            default => 'Não informado'
        };
    }

    private const TEAM_SEQ_MAP = [
        'fonoaudiologia'     => [43],
        'servico_social'     => [39],
        'nutricao'           => [42],
        'fisioterapia'       => [593, 609, 608],
        'psicologia'         => [41],
        'acessos_vasculares' => [639],
    ];

    /**
     * Single-attendance multidisciplinary detection using nr_seq_equipe_dest
     */
    public function getMultidisciplinaryTeamEvaluations(int $attendanceNumber): array
    {
        $default = [
            'fisioterapia' => false,
            'psicologia' => false,
            'nutricao' => false,
            'fonoaudiologia' => false,
            'servico_social' => false,
            'acessos_vasculares' => false,
        ];

        try {
            $rows = DB::connection($this->connection)->select("
                SELECT pr.nr_seq_equipe_dest
                FROM tasy.PARECER_MEDICO_REQ pr
                WHERE pr.nr_atendimento = :attendance
                  AND pr.dt_liberacao IS NOT NULL
                  AND pr.dt_inativacao IS NULL
                  AND pr.nr_seq_equipe_dest IS NOT NULL
            ", ['attendance' => $attendanceNumber]);

            if (empty($rows)) return $default;

            $teams = $default;

            foreach ($rows as $r) {
                $seq = (int)$r->nr_seq_equipe_dest;
                foreach (self::TEAM_SEQ_MAP as $key => $validSeqs) {
                    if (in_array($seq, $validSeqs, true)) {
                        $teams[$key] = true;
                    }
                }
            }

            return $teams;
        } catch (\Throwable $e) {
            Log::warning('PatientClinicalRepository::getMultidisciplinaryTeamEvaluations failed', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * Retorna equipes multidisciplinares associadas aos atendimentos (BATCH)
     * SIMPLIFICADO: Apenas verifica EXISTS para cada equipe
     */
    public function getMultidisciplinaryTeams(array $attendances): array
    {
        if (empty($attendances)) {
            return [];
        }

        $chunks = array_chunk($attendances, 100);
        $result = [];

        foreach ($chunks as $chunk) {
            try {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));

                // Query SIMPLIFICADA: apenas pega nr_atendimento e nr_seq_equipe_dest
                $sql = "
                    SELECT
                        nr_atendimento,
                        nr_seq_equipe_dest
                    FROM tasy.PARECER_MEDICO_REQ
                    WHERE nr_atendimento IN ($placeholders)
                      AND dt_liberacao IS NOT NULL
                      AND dt_inativacao IS NULL
                      AND nr_seq_equipe_dest IS NOT NULL
                ";

                $rows = DB::connection('tasy')->select($sql, $chunk);

                // Inicializa todos os atendimentos com false
                foreach ($chunk as $nr) {
                    if (!isset($result[$nr])) {
                        $result[$nr] = [
                            'fisioterapia'        => false,
                            'psicologia'          => false,
                            'nutricao'            => false,
                            'fonoaudiologia'      => false,
                            'servico_social'      => false,
                            'acessos_vasculares'  => false,
                        ];
                    }
                }

                // Processa as linhas
                foreach ($rows as $row) {
                    $nr = $row->nr_atendimento;
                    $seq = (int)$row->nr_seq_equipe_dest;

                    // Marca como true se encontrar uma equipe válida
                    foreach (self::TEAM_SEQ_MAP as $key => $validSeqs) {
                        if (in_array($seq, $validSeqs, true)) {
                            $result[$nr][$key] = true;
                        }
                    }
                }

            } catch (\Throwable $e) {
                Log::warning('PatientClinicalRepository::getMultidisciplinaryTeams chunk failed', [
                    'error' => $e->getMessage(),
                    'chunk_sample' => array_slice($chunk, 0, 5),
                ]);

                // Fallback padrão vazio se chunk falhar
                foreach ($chunk as $nr) {
                    if (!isset($result[$nr])) {
                        $result[$nr] = [
                            'fisioterapia'        => false,
                            'psicologia'          => false,
                            'nutricao'            => false,
                            'fonoaudiologia'      => false,
                            'servico_social'      => false,
                            'acessos_vasculares'  => false,
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Remove informações sensíveis de cirurgias
     */
    private function filterSensitiveData($observacao)
    {
        if (empty($observacao)) {
            return '';
        }

        // Remove valores monetários e informações sensíveis
        $patterns = [
            '/R\$\s*[\d.,]+/i',
            '/valor.*[\d.,]+/i',
            '/custo.*[\d.,]+/i',
            '/autorizado.*coordenação/i'
        ];

        foreach ($patterns as $pattern) {
            $observacao = preg_replace($pattern, '', $observacao);
        }

        return trim($observacao) ?: 'Informações disponíveis no prontuário';
    }
}
