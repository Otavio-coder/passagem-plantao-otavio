<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientClinicalRepository
{
    protected const CHUNK_SIZE = 200;
    protected $connection = 'tasy';

    /**
     * Busca dados clínicos core de um paciente (isolamento, nursing, dispositivos, alergias)
     */
    public function getPatientClinicalDetails(int $attendanceNumber, ?string $sectorCode = null): object
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
        $basicData->diagnosticos_comorbidades = $this->getDiagnosticsAndComorbidities($attendanceNumber);
        $basicData->materiais = $this->getAntimicrobials($attendanceNumber) ?? 'Nenhum antimicrobiano';

        return $basicData;
    }

    /**
     * Busca dados clínicos core de múltiplos pacientes em batch
     */
    public function getBatchClinicalDetails(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $chunks     = array_chunk($attendanceNumbers, 100);
        $allResults = [];

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

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

        $diagnostics   = $this->getBatchDiagnostics($attendanceNumbers);
        $antimicrobials = $this->getBatchAntimicrobials($attendanceNumbers);

        $finalResults = [];
        foreach ($attendanceNumbers as $nr) {
            $basic = $allResults[$nr] ?? $this->getDefaultClinicalData();

            $basic->diagnosticos_comorbidades = $diagnostics[$nr] ?? 'Sem diagnósticos';
            $basic->materiais                 = $antimicrobials[$nr] ?? 'Nenhum antimicrobiano';

            $finalResults[$nr] = $basic;
        }

        return $finalResults;
    }

    private function getBatchDiagnostics(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) return [];

        $results = [];

        foreach (array_chunk($attendanceNumbers, 100) as $chunk) {
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

        $results = [];

        foreach (array_chunk($attendanceNumbers, 50) as $chunk) {
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

    private function getDiagnosticsAndComorbidities(int $attendanceNumber): string
    {
        $result = DB::connection('tasy')->select("
            SELECT COALESCE(tasy.obter_diagnostico_atendimento(:nr_atendimento, 0), 'Sem diagnósticos') AS diagnosticos
            FROM dual
        ", ['nr_atendimento' => $attendanceNumber]);

        return $result ? $result[0]->diagnosticos : 'Sem diagnósticos';
    }

    private function getAntimicrobials(int $attendanceNumber): ?string
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

    private function getDefaultClinicalData(): object
    {
        return (object)[
            'medida_bloqueio'         => 'Não',
            'motivos_isolamento'      => 'Nenhum motivo de isolamento',
            'avaliacao_enf'           => 'Não realizada',
            'plano_educ'              => 'Não realizado',
            'pe_data'                 => 'Não realizado',
            'ds_queda'                => 'Não avaliado',
            'diag'                    => 'Sem diagnósticos SAE',
            'dispositivos'            => 'Nenhum dispositivo',
            'alergias_detalhadas'     => 'Sem alergias registradas',
            'diagnosticos_comorbidades' => 'Sem diagnósticos',
            'materiais'               => 'Nenhum antimicrobiano',
        ];
    }
}
