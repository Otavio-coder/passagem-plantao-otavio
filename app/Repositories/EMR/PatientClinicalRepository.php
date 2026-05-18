<?php

namespace App\Repositories\EMR;

use Illuminate\Support\Facades\DB;

/**
 * Repositório de dados clínicos do paciente no EMR Tasy (Oracle).
 *
 * Todas as queries são executadas na conexão 'tasy' (Oracle) via PDO.
 * Métodos batch recebem arrays de nr_atendimento e retornam arrays indexados por nr_atendimento,
 * permitindo que o chamador faça merge sem round-trips adicionais.
 *
 * ANTIMICROBIAL_CHUNK_SIZE menor que CLINICAL_CHUNK_SIZE: as queries de antimicrobianos usam
 * CTEs com múltiplos self-joins, o que eleva o custo por linha no Oracle e requer chunks menores
 * para manter o plano de execução eficiente.
 */
class PatientClinicalRepository
{
    protected const CHUNK_SIZE = 200;

    protected const CLINICAL_CHUNK_SIZE = 200;

    protected const ANTIMICROBIAL_CHUNK_SIZE = 100;

    protected $connection = 'tasy';

    /** @deprecated No external callers — use getBatchClinicalDetails */
    private function getPatientClinicalDetails(int $attendanceNumber, ?string $sectorCode = null): object
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
                NVL(
                    (SELECT RTRIM(
                        XMLCAST(
                            XMLAGG(
                                XMLELEMENT(e, tasy.PE_obter_desc_diag(d.nr_seq_diag, 'DI') || ' | ')
                                ORDER BY 1
                            ) AS CLOB
                        ),
                        ' | '
                    )
                     FROM tasy.pe_prescr_diag d
                     JOIN tasy.pe_prescricao p ON d.nr_seq_prescr = p.nr_sequencia
                     WHERE p.nr_atendimento = atp.nr_atendimento
                       AND p.dt_prescricao = (SELECT MAX(c.dt_prescricao)
                                              FROM tasy.pe_prescricao c
                                              WHERE c.nr_atendimento = atp.nr_atendimento)),
                    TO_CLOB('Sem diagnósticos SAE')
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

                -- Alergias (alergia_v: cascade de nomes + marcação de intolerância, sem truncamento)
                COALESCE(
                    NULLIF((SELECT LISTAGG(
                                agente || CASE WHEN classificacao = 'I' THEN ' (Intolerância)' ELSE '' END,
                                ', ') WITHIN GROUP (ORDER BY agente)
                            FROM (
                                SELECT DISTINCT
                                    COALESCE(ds_ficha_tecnica, ds_dcb, ds_dcb_mat,
                                             REPLACE(ds_material, ',', '.'),
                                             ds_classe_mat, ds_medic_nao_cad,
                                             ds_alergeno, ds_familia_mat) AS agente,
                                    ie_classificacao                      AS classificacao
                                FROM tasy.alergia_v
                                WHERE cd_pessoa_fisica = atp.cd_pessoa_fisica
                                  AND COALESCE(ds_ficha_tecnica, ds_dcb, ds_dcb_mat,
                                               REPLACE(ds_material, ',', '.'),
                                               ds_classe_mat, ds_medic_nao_cad,
                                               ds_alergeno, ds_familia_mat) IS NOT NULL
                                  AND dt_liberacao IS NOT NULL
                            )), ''),
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
        $basicData->cid_history = $this->getCidHistory($attendanceNumber);

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

        $chunks = array_chunk($attendanceNumbers, self::CLINICAL_CHUNK_SIZE);
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
                NVL(
                    (SELECT RTRIM(
                        XMLCAST(
                            XMLAGG(
                                XMLELEMENT(e, tasy.PE_obter_desc_diag(d.nr_seq_diag, 'DI') || ' | ')
                                ORDER BY 1
                            ) AS CLOB
                        ),
                        ' | '
                    )
                     FROM tasy.pe_prescr_diag d
                     JOIN tasy.pe_prescricao p ON d.nr_seq_prescr = p.nr_sequencia
                     WHERE p.nr_atendimento = atp.nr_atendimento
                       AND p.dt_prescricao = (SELECT MAX(c.dt_prescricao)
                                              FROM tasy.pe_prescricao c
                                              WHERE c.nr_atendimento = atp.nr_atendimento)),
                    TO_CLOB('Sem diagnósticos SAE')
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

                -- Última hemocultura coletada: usa dt_coleta de prescr_procedimento
                -- (result_laboratorio.dt_coleta está sempre NULL no Tasy desta instituição)
                (SELECT TO_CHAR(MAX(pp_h.dt_coleta), 'DD/MM/YY HH24:MI')
                 FROM tasy.prescr_procedimento pp_h
                 JOIN tasy.prescr_medica pm_h ON pm_h.nr_prescricao = pp_h.nr_prescricao
                 JOIN tasy.exame_laboratorio el_h ON el_h.nr_seq_exame = pp_h.nr_seq_exame
                 WHERE pm_h.nr_atendimento = atp.nr_atendimento
                   AND UPPER(el_h.nm_exame) LIKE '%HEMOCULTURA%'
                   AND pp_h.dt_coleta IS NOT NULL) AS ultima_hemocultura,

                -- Hemocultura prescrita mas ainda sem coleta registrada (dt_coleta IS NULL = não coletada)
                (SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                 FROM tasy.prescr_procedimento pp_h
                 JOIN tasy.prescr_medica pm_h ON pm_h.nr_prescricao = pp_h.nr_prescricao
                 JOIN tasy.exame_laboratorio el_h ON el_h.nr_seq_exame = pp_h.nr_seq_exame
                 WHERE pm_h.nr_atendimento = atp.nr_atendimento
                   AND UPPER(el_h.nm_exame) LIKE '%HEMOCULTURA%'
                   AND pp_h.dt_coleta IS NULL
                   AND pp_h.dt_cancelamento IS NULL
                   AND pp_h.ie_suspenso <> 'S'
                   AND pp_h.ie_status_execucao NOT IN ('40', 'R', 'C', 'BE')) AS hemocultura_pendente,

                -- Últimas 5 requisições de hemocultura com coleta (agrupadas por nr_prescricao)
                -- Formato: nr_prescricao::data_max_coleta::nm_exame::ie_status_execucao::has_result::dt_prescricao
                (SELECT LISTAGG(
                            h.nr_prescricao || '::' ||
                            TO_CHAR(h.max_coleta, 'DD/MM/YY HH24:MI') || '::' ||
                            SUBSTR(h.nm_exame, 1, 50) || '::' ||
                            h.status_exec || '::' ||
                            h.has_result || '::' ||
                            TO_CHAR(h.dt_prescricao, 'DD/MM/YY HH24:MI'),
                            '||'
                        ) WITHIN GROUP (ORDER BY h.max_coleta DESC)
                 FROM (
                    -- LEFT JOIN em result_laboratorio por nr_seq_prescricao = pp_h.nr_sequencia:
                    -- vincula resultado ao item exato da hemocultura, não à prescrição inteira.
                    -- COUNT(rl.nr_sequencia) é 0 se nenhum resultado, > 0 se existe laudo.
                    -- Funciona corretamente com N itens de hemocultura na mesma prescrição.
                    SELECT pm_h.nr_prescricao,
                           MAX(pp_h.dt_coleta) AS max_coleta,
                           MAX(UPPER(el_h.nm_exame)) AS nm_exame,
                           MAX(pp_h.ie_status_execucao) AS status_exec,
                           COUNT(rl.nr_sequencia) AS has_result,
                           MAX(pm_h.dt_prescricao) AS dt_prescricao
                    FROM tasy.prescr_procedimento pp_h
                    JOIN tasy.prescr_medica pm_h ON pm_h.nr_prescricao = pp_h.nr_prescricao
                    JOIN tasy.exame_laboratorio el_h ON el_h.nr_seq_exame = pp_h.nr_seq_exame
                    LEFT JOIN tasy.result_laboratorio rl
                        ON rl.nr_prescricao     = pp_h.nr_prescricao
                       AND rl.nr_seq_prescricao = pp_h.nr_sequencia
                    WHERE pm_h.nr_atendimento = atp.nr_atendimento
                      AND UPPER(el_h.nm_exame) LIKE '%HEMOCULTURA%'
                      AND pp_h.dt_coleta IS NOT NULL
                      AND pp_h.ie_suspenso <> 'S'
                      AND pp_h.dt_cancelamento IS NULL
                    GROUP BY pm_h.nr_prescricao
                    ORDER BY MAX(pp_h.dt_coleta) DESC
                    FETCH FIRST 5 ROWS ONLY
                 ) h) AS hemoc_history,

                -- Alergias (alergia_v: cascade de nomes + marcação de intolerância, sem truncamento)
                COALESCE(
                    NULLIF((SELECT LISTAGG(
                                agente || CASE WHEN classificacao = 'I' THEN ' (Intolerância)' ELSE '' END,
                                ', ') WITHIN GROUP (ORDER BY agente)
                            FROM (
                                SELECT DISTINCT
                                    COALESCE(ds_ficha_tecnica, ds_dcb, ds_dcb_mat,
                                             REPLACE(ds_material, ',', '.'),
                                             ds_classe_mat, ds_medic_nao_cad,
                                             ds_alergeno, ds_familia_mat) AS agente,
                                    ie_classificacao                      AS classificacao
                                FROM tasy.alergia_v
                                WHERE cd_pessoa_fisica = atp.cd_pessoa_fisica
                                  AND COALESCE(ds_ficha_tecnica, ds_dcb, ds_dcb_mat,
                                               REPLACE(ds_material, ',', '.'),
                                               ds_classe_mat, ds_medic_nao_cad,
                                               ds_alergeno, ds_familia_mat) IS NOT NULL
                                  AND dt_liberacao IS NOT NULL
                            )), ''),
                    'Sem alergias registradas'
                ) AS alergias_detalhadas

            FROM tasy.atendimento_paciente atp
            WHERE atp.nr_atendimento IN ($placeholders)
        ", $chunk);

            foreach ($results as $result) {
                $allResults[$result->nr_atendimento] = $result;
            }
        }

        $diagnostics = $this->getBatchDiagnostics($attendanceNumbers);
        $antimicrobials = $this->getBatchAntimicrobials($attendanceNumbers);

        $finalResults = [];
        foreach ($attendanceNumbers as $nr) {
            $basic = $allResults[$nr] ?? $this->getDefaultClinicalData();

            $basic->diagnosticos_comorbidades = $diagnostics[$nr] ?? 'Sem diagnósticos';
            $basic->materiais = $antimicrobials[$nr] ?? 'Nenhum antimicrobiano';

            $finalResults[$nr] = $basic;
        }

        return $finalResults;
    }

    private function getBatchDiagnostics(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $results = [];

        foreach (array_chunk($attendanceNumbers, self::CLINICAL_CHUNK_SIZE) as $chunk) {
            // ODCINUMBERLIST: tipo de coleção nativa do Oracle que aceita uma lista de números
            // como parâmetro de tabela virtual (TABLE(...)). Usada aqui porque a stored function
            // `obter_diagnostico_atendimento` precisa ser chamada linha a linha — não há como
            // reescrever sua lógica com SQL puro. O TABLE() evita criar uma temp table e funciona
            // em uma única query ao invés de N chamadas individuais.
            $rows = DB::connection($this->connection)->select("
            SELECT
                nr_atendimento,
                COALESCE(tasy.obter_diagnostico_atendimento(nr_atendimento, 0), 'Sem diagnósticos') AS diagnosticos
            FROM (
                SELECT COLUMN_VALUE as nr_atendimento
                FROM TABLE(SYS.ODCINUMBERLIST(".implode(',', $chunk).'))
            )
        ');

            foreach ($rows as $row) {
                $results[$row->nr_atendimento] = $row->diagnosticos;
            }
        }

        return $results;
    }

    private function getBatchAntimicrobials(array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $results = [];

        foreach (array_chunk($attendanceNumbers, self::ANTIMICROBIAL_CHUNK_SIZE) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            // antimicrobial_mats: materializa o catálogo de antimicrobianos uma única vez via JOIN,
            // evitando a scalar subquery original que executava SELECT por linha.
            // ranked_prescr: ROW_NUMBER por (atendimento, material) substitui a correlated subquery
            // de MAX(dt_prescricao) que disparava N queries para cada combinação atendimento × material.
            $rows = DB::connection($this->connection)->select("
                WITH antimicrobial_mats AS (
                    SELECT m.cd_material,
                           INITCAP(m.ds_material) AS ds_material
                    FROM tasy.material m
                    JOIN tasy.material m_stock
                        ON m_stock.cd_material = m.cd_material_estoque
                    JOIN tasy.medic_ficha_tecnica mf
                        ON mf.nr_sequencia = m_stock.nr_seq_ficha_tecnica
                    WHERE mf.ie_antimicrobiano = 'S'
                ),
                ranked_prescr AS (
                    SELECT
                        pm.nr_atendimento,
                        pt.cd_material,
                        pt.nr_dia_util,
                        ROW_NUMBER() OVER (
                            PARTITION BY pm.nr_atendimento, pt.cd_material
                            ORDER BY pm.dt_prescricao DESC
                        ) AS rn
                    FROM tasy.prescr_medica pm
                    JOIN tasy.prescr_material pt
                        ON pt.nr_prescricao = pm.nr_prescricao
                    JOIN antimicrobial_mats am
                        ON am.cd_material = pt.cd_material
                    WHERE pm.nr_atendimento     IN ($placeholders)
                      AND pm.dt_liberacao       IS NOT NULL
                      AND pm.dt_validade_prescr >= SYSDATE
                      AND pm.dt_suspensao       IS NULL
                      AND pt.dt_suspensao       IS NULL
                      AND pt.nr_dia_util        IS NOT NULL
                )
                SELECT rp.nr_atendimento,
                       LISTAGG(am.ds_material || ' ' || rp.nr_dia_util || ' Dia(s)', CHR(13))
                           WITHIN GROUP (ORDER BY am.ds_material) AS materiais
                FROM ranked_prescr rp
                JOIN antimicrobial_mats am ON am.cd_material = rp.cd_material
                WHERE rp.rn = 1
                GROUP BY rp.nr_atendimento
            ", $chunk);

            foreach ($rows as $row) {
                $results[$row->nr_atendimento] = $row->materiais;
            }
        }

        return $results;
    }

    /**
     * Retorna o histórico completo de CIDs registrados durante o atendimento,
     * ordenados do mais recente para o mais antigo.
     *
     * Comportamento do Tasy: cada edição de diagnóstico cria uma nova linha em DIAGNOSTICO_DOENCA
     * com o mesmo CD_DOENCA. A deduplicação por ROW_NUMBER mantém apenas o registro mais recente
     * por código, evitando duplicatas na exibição.
     *
     * @return array<int, array{cd_cid: string, ds_cid: string, classificacao: string, situacao: string, dt_diagnostico: string, dt_inativacao: string|null, nm_usuario: string}>
     */
    public function getCidHistory(int $attendanceNumber): array
    {
        // O Tasy insere uma nova linha por atualização do diagnóstico.
        // Deduplica por CD_DOENCA mantendo somente o registro mais recente (DT_ATUALIZACAO DESC).
        $rows = DB::connection($this->connection)->select("
            SELECT cd_cid, ds_cid, classificacao, situacao, dt_diagnostico, dt_inativacao
            FROM (
                SELECT
                    dd.CD_DOENCA                                                AS cd_cid,
                    COALESCE(cd.DS_DOENCA_CID, dd.DS_DIAGNOSTICO, dd.CD_DOENCA) AS ds_cid,
                    CASE dd.IE_CLASSIFICACAO_DOENCA
                        WHEN 'P' THEN 'Principal'
                        WHEN 'S' THEN 'Secundário'
                        ELSE NVL(dd.IE_CLASSIFICACAO_DOENCA, '-')
                    END                                                         AS classificacao,
                    CASE dd.IE_SITUACAO
                        WHEN 'A' THEN 'Ativo'
                        WHEN 'I' THEN 'Inativo'
                        ELSE NVL(dd.IE_SITUACAO, 'Ativo')
                    END                                                         AS situacao,
                    TO_CHAR(dd.DT_DIAGNOSTICO, 'DD/MM/YYYY')                   AS dt_diagnostico,
                    TO_CHAR(dd.DT_INATIVACAO, 'DD/MM/YYYY')                    AS dt_inativacao,
                    ROW_NUMBER() OVER (
                        PARTITION BY dd.CD_DOENCA
                        ORDER BY dd.DT_ATUALIZACAO DESC, dd.NR_SEQ_INTERNO DESC
                    ) AS rn
                FROM tasy.DIAGNOSTICO_DOENCA dd
                LEFT JOIN tasy.CID_DOENCA cd ON cd.CD_DOENCA_CID = dd.CD_DOENCA
                WHERE dd.NR_ATENDIMENTO = :nr_atendimento
            )
            WHERE rn = 1
            ORDER BY
                CASE situacao WHEN 'Ativo' THEN 0 ELSE 1 END,
                CASE classificacao WHEN 'Principal' THEN 0 WHEN 'Secundário' THEN 1 ELSE 2 END,
                dt_diagnostico DESC
        ", ['nr_atendimento' => $attendanceNumber]);

        return array_map(function ($row) {
            $row = (array) $row;
            $row['ds_cid'] = self::stripCidCodeFromDescription(
                (string) ($row['cd_cid'] ?? ''),
                (string) ($row['ds_cid'] ?? '')
            );

            return $row;
        }, $rows);
    }

    /**
     * Removes leading CID code tokens from a description string.
     *
     * DS_DIAGNOSTICO in Tasy sometimes stores entries like "D729 D72.9 Transt NE dos globulos brancos"
     * where the code without dot and with dot both appear before the description text.
     * This strips those prefixes so the display shows only the description next to cd_cid.
     */
    public static function stripCidCodeFromDescription(string $code, string $desc): string
    {
        $code = trim($code);
        $desc = trim($desc);

        if ($code === '' || $desc === '') {
            return $desc;
        }

        $normalizedCode = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $code));
        $variants = [];

        if ($normalizedCode !== '') {
            $variants[] = $normalizedCode;

            if (strlen($normalizedCode) > 3) {
                $variants[] = substr($normalizedCode, 0, 3).'.'.substr($normalizedCode, 3);
            }
        }

        $codeUpper = strtoupper($code);
        if ($codeUpper !== '') {
            $variants[] = $codeUpper;
        }

        $variants = array_values(array_unique(array_filter($variants)));

        if (empty($variants)) {
            return $desc;
        }

        $cleanedDescription = preg_replace('/\s+/', ' ', $desc) ?? $desc;

        // Alguns registros repetem o código CID no início da descrição (ex: "R520 R52.0 ...").
        // Remove apenas os tokens iniciais que correspondem às variantes do código, preservando o texto clínico.
        for ($i = 0; $i < 3; $i++) {
            $stripped = false;

            foreach ($variants as $variant) {
                $pattern = '/^'.preg_quote($variant, '/').'(?=\s|$)\s*/i';
                if (preg_match($pattern, $cleanedDescription) === 1) {
                    $cleanedDescription = (string) preg_replace($pattern, '', $cleanedDescription);
                    $stripped = true;
                    break;
                }
            }

            if (! $stripped) {
                break;
            }
        }

        return trim($cleanedDescription);
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

        return (! empty($result) && ! empty($result[0]->materiais)) ? $result[0]->materiais : null;
    }

    private function getDefaultClinicalData(): object
    {
        return (object) [
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
        ];
    }
}
