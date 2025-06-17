<?php

namespace App\Models\EMR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PatientClinical extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    
    /**
     * Get clinical alerts for multiple patients (allergies, isolation, etc.)
     */
    public function getClinicalAlertsForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "clinical_alerts_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 300, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT 
                    atp.nr_atendimento,
                    CASE WHEN EXISTS(
                        SELECT 1 FROM tasy.W_PAN_PACIENTE wpp 
                        WHERE wpp.cd_pessoa_paciente = atp.cd_pessoa_fisica 
                        AND wpp.ds_alergias IS NOT NULL 
                        AND LENGTH(TRIM(wpp.ds_alergias)) > 0
                    ) THEN 1 ELSE 0 END AS has_allergy,
                    
                    CASE WHEN EXISTS(
                        SELECT 1 FROM tasy.atendimento_precaucao ap
                        WHERE ap.nr_atendimento = atp.nr_atendimento
                        AND SYSDATE BETWEEN ap.dt_inicio AND NVL(ap.dt_termino, SYSDATE)
                        AND ap.dt_liberacao IS NOT NULL
                        AND ap.dt_inativacao IS NULL
                    ) THEN 1 ELSE 0 END AS has_isolation
                FROM tasy.atendimento_paciente atp
                WHERE atp.nr_atendimento IN ({$placeholders})
            ", $attendanceNumbers);
            
            $alerts = [];
            foreach ($results as $result) {
                $alerts[$result->nr_atendimento] = [
                    'has_allergy' => (bool)$result->has_allergy,
                    'has_isolation' => (bool)$result->has_isolation
                ];
            }
            
            return $alerts;
        });
    }
    
    /**
     * Get surgical procedures for multiple patients (last week + next 45 days)
     */
    public function getSurgicalProceduresForPatients($attendanceNumbers)
    {
        if (empty($attendanceNumbers)) {
            return [];
        }
        
        $cacheKey = "surgical_procedures_" . md5(implode(',', $attendanceNumbers));
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumbers) {
            $placeholders = str_repeat('?,', count($attendanceNumbers) - 1) . '?';
            
            $results = DB::connection('tasy')->select("
                SELECT DISTINCT 
                    b.nr_atendimento,
                    tasy.OBTER_DESC_CARATER_CIR(pac.IE_CARATER_CIRURGIA) AS DS_CARATER_CIRURGIA,
                    pac.cd_pessoa_fisica,
                    pac.hr_inicio,
                    pac.nr_minuto_duracao,
                    tasy.OBTER_DESC_PROC_INTERNO(pac.nr_seq_proc_interno) AS DS_PROCEDIMENTO,
                    REPLACE(pac.DS_OBSERVACAO, ' / ', ' | ') AS observacao_formatada,
                    pac.dt_agenda,
                    CASE 
                        WHEN pac.dt_agenda < SYSDATE THEN 'REALIZADA'
                        WHEN pac.dt_agenda >= SYSDATE THEN 'AGENDADA'
                        ELSE 'INDEFINIDO'
                    END AS status_cirurgia
                FROM 
                    tasy.unidade_atendimento b 
                JOIN 
                    tasy.atendimento_paciente atp 
                    ON atp.nr_atendimento = b.nr_atendimento
                JOIN 
                    tasy.agenda_paciente pac 
                    ON pac.cd_pessoa_fisica = atp.cd_pessoa_fisica
                WHERE 
                    b.ie_situacao = 'A'
                    AND b.nr_atendimento IN ({$placeholders})
                    AND pac.IE_CARATER_CIRURGIA IS NOT NULL
                    AND pac.IE_CARATER_CIRURGIA <> 'X'
                    AND (
                        (pac.dt_agenda >= SYSDATE - 7 AND pac.dt_agenda < SYSDATE) -- Last week
                        OR (pac.dt_agenda >= SYSDATE AND pac.dt_agenda <= SYSDATE + 45) -- Next 45 days
                    )
                ORDER BY 
                    b.nr_atendimento, pac.dt_agenda DESC
            ", $attendanceNumbers);
            
            $surgicalData = [];
            foreach ($results as $result) {
                if (!isset($surgicalData[$result->nr_atendimento])) {
                    $surgicalData[$result->nr_atendimento] = [
                        'has_surgery' => true,
                        'procedures' => []
                    ];
                }
                
                $surgicalData[$result->nr_atendimento]['procedures'][] = [
                    'carater' => $result->ds_carater_cirurgia,
                    'procedimento' => $result->ds_procedimento,
                    'data_agenda' => $result->dt_agenda,
                    'hora_inicio' => $result->hr_inicio,
                    'duracao_minutos' => $result->nr_minuto_duracao,
                    'observacoes' => $result->observacao_formatada,
                    'status' => $result->status_cirurgia
                ];
            }
            
            return $surgicalData;
        });
    }

    /**
     * Recupera procedimentos cirúrgicos de um atendimento em um intervalo de datas - ENHANCED.
     *
     * @param  int                $attendanceNumber  Número do atendimento
     * @param  \Carbon\Carbon|null $startDate        Data de início (inclusiva). Padrão: hoje -7 dias
     * @param  \Carbon\Carbon|null $endDate          Data de fim (inclusiva).   Padrão: hoje +45 dias
     * @return array              Array de objetos com dados detalhados da cirurgia
     */
    private function getPatientSurgicalProcedures(
        int $attendanceNumber,
        ?\Carbon\Carbon $startDate = null,
        ?\Carbon\Carbon $endDate   = null
    ) {
        // Definir defaults
        $startDate = $startDate
            ? $startDate->startOfDay()
            : \Carbon\Carbon::now()->subDays(7)->startOfDay();
        $endDate   = $endDate
            ? $endDate->endOfDay()
            : \Carbon\Carbon::now()->addDays(45)->endOfDay();

        // Formatar para DD/MM/YYYY que o Oracle reconhece em TO_DATE()
        $startStr = $startDate->format('d/m/Y');
        $endStr   = $endDate->format('d/m/Y');

        try {
            $rows = DB::connection('tasy')->select(
                <<<'SQL'
    SELECT DISTINCT
        tasy.OBTER_DESC_CARATER_CIR(pac.IE_CARATER_CIRURGIA) AS ds_carater_cirurgia,
        pac.cd_pessoa_fisica,
        pac.hr_inicio,
        TO_CHAR(pac.hr_inicio, 'DD/MM/YY HH24:MI:SS') AS hr_inicio_formatada,
        TO_CHAR(pac.hr_inicio, 'DD/MM/YYYY') AS data_cirurgia,
        TO_CHAR(pac.hr_inicio, 'HH24:MI') AS hora_cirurgia,
        pac.nr_minuto_duracao,
        tasy.OBTER_DESC_PROC_INTERNO(pac.nr_seq_proc_interno) AS ds_procedimento,
        REPLACE(pac.DS_OBSERVACAO, ' / ', ' | ') AS observacao_formatada,
        pac.dt_agenda,
        CASE
            WHEN pac.dt_agenda < SYSDATE THEN 'REALIZADA'
            ELSE 'AGENDADA'
        END AS status_cirurgia,
        TO_CHAR(pac.dt_agenda, 'DD/MM/YYYY') AS data_agenda_formatada,
        TO_CHAR(pac.dt_agenda, 'HH24:MI') AS hora_agenda,
        -- Calcular se é cirurgia de emergência (agendada no mesmo dia)
        CASE 
            WHEN TRUNC(pac.dt_agenda) = TRUNC(pac.hr_inicio) THEN 'EMERGÊNCIA'
            ELSE 'ELETIVA'
        END AS tipo_agendamento,
        -- Duração formatada
        CASE 
            WHEN pac.nr_minuto_duracao IS NOT NULL THEN
                CASE 
                    WHEN pac.nr_minuto_duracao >= 60 THEN
                        FLOOR(pac.nr_minuto_duracao / 60) || 'h ' || MOD(pac.nr_minuto_duracao, 60) || 'min'
                    ELSE
                        pac.nr_minuto_duracao || 'min'
                END
            ELSE 'Duração não informada'
        END AS duracao_formatada
    FROM tasy.unidade_atendimento b
    JOIN tasy.atendimento_paciente   atp  ON atp.nr_atendimento = b.nr_atendimento
    JOIN tasy.agenda_paciente        pac  ON pac.cd_pessoa_fisica = atp.cd_pessoa_fisica
    WHERE
        b.ie_situacao            = 'A'
        AND b.nr_atendimento     = :attendance
        AND pac.IE_CARATER_CIRURGIA IS NOT NULL
        AND pac.IE_CARATER_CIRURGIA <> 'X'
        AND pac.dt_agenda BETWEEN
            TO_DATE(:startDate, 'DD/MM/YYYY')
            AND TO_DATE(:endDate,   'DD/MM/YYYY')
    ORDER BY pac.dt_agenda DESC, pac.hr_inicio DESC
    SQL
                ,
                [
                    'attendance' => $attendanceNumber,
                    'startDate'  => $startStr,
                    'endDate'    => $endStr,
                ]
            );

            if (empty($rows)) {
                return [];
            }

            // Converter para array de objetos com dados estruturados
            $procedures = array_map(static function ($row) {
                // Filtrar informações de custos das observações
                $observacaoLimpa = static::filterSensitiveData($row->observacao_formatada ?? '');
                
                return [
                    'carater_cirurgia' => $row->ds_carater_cirurgia ?? 'Caráter não informado',
                    'procedimento' => $row->ds_procedimento ?? 'Procedimento não informado',
                    'status' => $row->status_cirurgia,
                    'tipo_agendamento' => $row->tipo_agendamento ?? 'ELETIVA',
                    'data_agenda' => $row->data_agenda_formatada,
                    'hora_agenda' => $row->hora_agenda,
                    'data_cirurgia' => $row->data_cirurgia,
                    'hora_cirurgia' => $row->hora_cirurgia,
                    'hr_inicio_completa' => $row->hr_inicio_formatada,
                    'duracao_minutos' => $row->nr_minuto_duracao,
                    'duracao_formatada' => $row->duracao_formatada,
                    'observacoes' => $observacaoLimpa,
                    'has_observacoes' => !empty(trim($observacaoLimpa)),
                    // Dados brutos para processamento adicional se necessário
                    'raw_data' => [
                        'hr_inicio' => $row->hr_inicio,
                        'dt_agenda' => $row->dt_agenda,
                        'cd_pessoa_fisica' => $row->cd_pessoa_fisica
                    ]
                ];
            }, $rows);

            return $procedures;

        } catch (\Throwable $e) {
            Log::warning(
                "Erro ao buscar procedimentos cirúrgicos (atendimento {$attendanceNumber}): " .
                $e->getMessage()
            );
            return [];
        }
    }

    /**
     * Filter sensitive data from surgical observations
     * Remove cost information and other sensitive data - ENHANCED v2
     */
    private static function filterSensitiveData($observacao)
    {
        if (empty($observacao)) {
            return '';
        }
        
        // Primeiro, remover quebras de linha para facilitar o processamento
        $observacaoLimpa = str_replace(["\r\n", "\n", "\r"], ' | ', $observacao);
        
        // Padrões mais agressivos para remoção de valores monetários
        $patternsToRemove = [
            // Valores monetários específicos
            '/Valor\s+Total\s+Previsto\s+R\$\s*[\d.,]+[^|]*/i',
            '/Valor\s+Previsto\s+R\$\s*[\d.,]+[^|]*/i',
            '/Valor\s+Total\s+R\$\s*[\d.,]+[^|]*/i',
            '/Valor\s+R\$\s*[\d.,]+[^|]*/i',
            '/Custo\s+R\$\s*[\d.,]+[^|]*/i',
            '/Preço\s+R\$\s*[\d.,]+[^|]*/i',
            '/Total\s+R\$\s*[\d.,]+[^|]*/i',
            
            // Padrão para capturar R$ seguido de números em qualquer contexto
            '/R\$\s*[\d.,]+/i',
            '/\$\s*[\d.,]+/i',
            
            // Termos relacionados a autorização e coordenação médica
            '/TERMO\s+AUTORIZADO\s+PELA\s+COORDENAÇÃO\s+MÉDICA/i',
            '/AUTORIZADO\s+PELA\s+COORDENAÇÃO/i',
            '/COORDENAÇÃO\s+MÉDICA/i',
            '/TERMO\s+AUTORIZADO/i',
            
            // Remover pipes múltiplos
            '/\|\s*\|+/i',
        ];
        
        // Aplicar filtros sequencialmente
        foreach ($patternsToRemove as $pattern) {
            $observacaoLimpa = preg_replace($pattern, ' ', $observacaoLimpa);
        }
        
        // Processamento linha por linha mais agressivo
        $linhas = explode('|', $observacaoLimpa);
        $linhasLimpas = [];
        
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            
            // Skip linha se estiver vazia
            if (empty($linha)) {
                continue;
            }
            
            // Skip linha se contém valores monetários ou termos financeiros
            if (preg_match('/R\$\s*[\d.,]+/i', $linha) || 
                preg_match('/\$\s*[\d.,]+/i', $linha) ||
                preg_match('/valor.*[\d.,]+/i', $linha) ||
                preg_match('/custo.*[\d.,]+/i', $linha) ||
                preg_match('/preço.*[\d.,]+/i', $linha) ||
                preg_match('/total.*[\d.,]+/i', $linha) ||
                preg_match('/autorizado.*coordenação/i', $linha) ||
                preg_match('/termo.*autorizado/i', $linha) ||
                preg_match('/coordenação.*médica/i', $linha) ||
                preg_match('/valor\s+total\s+previsto/i', $linha) ||
                preg_match('/^[\d.,]+$/', $linha)) { // Linha só com números
                continue; // Pula esta linha
            }
            
            // Adiciona linha apenas se não estiver vazia após limpeza
            if (!empty($linha) && strlen($linha) > 3) {
                $linhasLimpas[] = $linha;
            }
        }
        
        // Reconstroi a observação
        $observacaoLimpa = implode(' | ', $linhasLimpas);
        
        // Limpeza final mais agressiva
        $observacaoLimpa = preg_replace('/\s*\|\s*\|\s*/', ' | ', $observacaoLimpa);
        $observacaoLimpa = preg_replace('/^\s*\|\s*/', '', $observacaoLimpa);
        $observacaoLimpa = preg_replace('/\s*\|\s*$/', '', $observacaoLimpa);
        $observacaoLimpa = trim($observacaoLimpa);
        
        // Remove espaços duplos e múltiplos
        $observacaoLimpa = preg_replace('/\s+/', ' ', $observacaoLimpa);
        
        // Limpeza final de termos que possam ter sobrado
        $termosFiltrar = [
            '/\bvalor\s+total\s+previsto\b/i',
            '/\btermos?\s+autorizado\b/i',
            '/\bcoordenação\s+médica\b/i',
            '/\bautorizado\s+pela\b/i'
        ];
        
        foreach ($termosFiltrar as $termo) {
            $observacaoLimpa = preg_replace($termo, '', $observacaoLimpa);
        }
        
        // Limpeza final
        $observacaoLimpa = trim($observacaoLimpa);
        $observacaoLimpa = preg_replace('/\s+/', ' ', $observacaoLimpa);
        
        // Se ficou muito vazio após a limpeza, retornar uma mensagem padrão
        if (empty($observacaoLimpa) || $observacaoLimpa === '|' || strlen($observacaoLimpa) < 15) {
            return 'Informações técnicas da cirurgia disponíveis no prontuário';
        }
        
        return $observacaoLimpa;
    }

    /**
     * Get detailed clinical information for a patient - EXPANDED
     */
    public function getPatientClinicalDetails($attendanceNumber)
    {
        $cacheKey = "patient_clinical_{$attendanceNumber}";
        
        return Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
            $result = DB::connection('tasy')->select("
                SELECT
                    -- Basic patient info (enhanced)
                    atp.cd_pessoa_fisica,
                    tasy.obter_nome_paciente(atp.nr_atendimento) AS nm_pessoa_fisica,
                    pf.dt_nascimento,
                    FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS idade_anos,
                    MOD(FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)), 12) AS idade_meses,
                    FLOOR(SYSDATE - ADD_MONTHS(pf.dt_nascimento, FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)))) AS idade_dias,
                    pf.ie_sexo AS sexo,
                    TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS tempo_internacao_dias,
                    tasy.obter_medico_resp_atend(atp.nr_atendimento, 'N') AS medico_responsavel,
                    tasy.obter_desc_convenio(tasy.obter_convenio_atendimento(atp.nr_atendimento)) AS convenio,
                    
                    -- Isolation check
                    (SELECT DECODE(
                        COUNT(nr_sequencia),
                        0, 'Não',
                        'Sim'
                    ) FROM tasy.atendimento_precaucao
                    WHERE nr_atendimento = atp.nr_atendimento
                      AND SYSDATE BETWEEN dt_inicio AND NVL(dt_termino, SYSDATE)
                      AND dt_liberacao IS NOT NULL
                      AND dt_inativacao IS NULL
                    ) AS medida_bloqueio,
                    
                    -- Isolation motives
                    (SELECT LISTAGG(cp.ds_precaucao || ' - ' || mi.ds_motivo, '; ') WITHIN GROUP (ORDER BY 1)
                     FROM tasy.atendimento_precaucao ap,
                          tasy.motivo_isolamento mi,
                          tasy.cih_precaucao cp
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND SYSDATE BETWEEN ap.dt_inicio AND NVL(ap.dt_termino, SYSDATE)
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL
                       AND ap.nr_seq_motivo_isol = mi.nr_sequencia
                       AND ap.nr_seq_precaucao = cp.nr_sequencia
                    ) AS motivos_isolamento,
                    
                    -- Nursing evaluation date
                    (SELECT TO_CHAR(MAX(ap.dt_avaliacao), 'DD/MM/YYYY HH24:MI')
                     FROM tasy.med_avaliacao_paciente ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND ap.nr_seq_tipo_avaliacao = 7781
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL
                    ) AS avaliacao_enf,
                    
                    -- Educational plan date
                    (SELECT TO_CHAR(TRUNC(MAX(ap.dt_avaliacao)), 'DD/MM/YYYY')
                     FROM tasy.med_avaliacao_paciente ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND ap.nr_seq_tipo_avaliacao = 793
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL
                    ) AS plano_educ,
                    
                    -- PE date
                    (SELECT TO_CHAR(MAX(ap.dt_avaliacao), 'DD/MM/YYYY HH24:MI')
                     FROM tasy.med_avaliacao_paciente ap
                     WHERE ap.nr_atendimento = atp.nr_atendimento
                       AND ap.nr_seq_tipo_avaliacao = 1154
                       AND ap.dt_liberacao IS NOT NULL
                       AND ap.dt_inativacao IS NULL
                    ) AS pe_data,
                    
                    -- Fall history
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
                             AND b.nr_atendimento = a.nr_atendimento
                       )
                    ) AS ds_queda,
                    
                    -- Diagnoses (SAE)
                    COALESCE(
                        (SELECT SUBSTR(LISTAGG(SUBSTR(tasy.PE_obter_desc_diag(d.nr_seq_diag, 'DI'), 1, 100), ' | ') 
                                WITHIN GROUP (ORDER BY 1), 1, 400)
                         FROM tasy.pe_prescr_diag d
                         JOIN tasy.pe_prescricao p ON d.nr_seq_prescr = p.nr_sequencia
                         WHERE p.nr_atendimento = atp.nr_atendimento
                           AND p.dt_prescricao = (SELECT MAX(c.dt_prescricao) FROM tasy.pe_prescricao c WHERE c.nr_atendimento = atp.nr_atendimento)
                         AND ROWNUM <= 3),
                        'Sem diagnósticos SAE'
                    ) AS diag,
                    
                    -- Devices
                    COALESCE(
                        (SELECT LISTAGG(SUBSTR(tasy.obter_descricao_padrao('DISPOSITIVO', 'DS_DISPOSITIVO', NR_SEQ_DISPOSITIVO), 1, 60), ' | ') 
                                WITHIN GROUP (ORDER BY 1)
                         FROM tasy.ATEND_PAC_DISPOSITIVO 
                         WHERE nr_atendimento = atp.nr_atendimento 
                           AND dt_retirada IS NULL),
                        'Nenhum dispositivo'
                    ) AS dispositivos,
                    
                    -- Allergies (cleaned up)
                    COALESCE(
                        (SELECT REGEXP_REPLACE(
                            SUBSTR(ds_alergias, 1, 300),
                            ' - (Não informado|desconhecido|N/A)[^;]*',
                            '',
                            1, 0, 'i'
                        ) FROM tasy.W_PAN_PACIENTE 
                         WHERE cd_pessoa_paciente = atp.cd_pessoa_fisica AND ROWNUM = 1),
                        'Sem alergias registradas'
                    ) AS alergias_detalhadas
                       
                FROM tasy.atendimento_paciente atp
                LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                WHERE atp.nr_atendimento = :attendance
            ", ['attendance' => $attendanceNumber]);
            
            if (empty($result)) {
                return null;
            }
            
            $basicData = $result[0];
            
            // Get diagnoses and comorbidities separately due to complexity
            $diagResult = DB::connection('tasy')->select("
                SELECT
                    tasy.obter_diagnostico_atendimento(:nr_atendimento, 0)
                    || (
                        SELECT
                            CHR(13)
                            || REPLACE(
                                REPLACE(
                                    REPLACE(DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25583),
                                        'S',
                                        '>Asma<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25584),
                                        'S',
                                        '>AVC<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25585),
                                        'S',
                                        '>DPOV<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25586),
                                        'S',
                                        '>Enfisema<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25587),
                                        'S',
                                        '>HIV<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25588),
                                        'S',
                                        '>IAM<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25589),
                                        'S',
                                        '>IRC/IRA<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25590),
                                        'S',
                                        '>TBC<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25591),
                                        'S',
                                        '>Hepatite A<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25592),
                                        'S',
                                        '>Hepatite B<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25593),
                                        'S',
                                        '>Hepatite C<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25594),
                                        'S',
                                        '>Hepatite D<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25580),
                                        'S',
                                        '>Diabetes Mellitus<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25595),
                                        'S',
                                        '>HAS<='
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25596),
                                        'S',
                                        NVL2(
                                                             tasy.aval(mapa.nr_sequencia, 25597),
                                                             '>Câncer - Protocolo:'
                                                             || tasy.aval(mapa.nr_sequencia, 25597)
                                                             || '<=',
                                                             '>Câncer<='
                                                         )
                                    )
                                            || DECODE(
                                        tasy.aval(mapa.nr_sequencia, 25598),
                                        'S',
                                        NVL2(
                                                             tasy.aval(mapa.nr_sequencia, 25599),
                                                             '>Outros - Desc.: '
                                                             || tasy.aval(mapa.nr_sequencia, 25599)
                                                             || '<=',
                                                             '>Outros<='
                                                         )
                                    ),
                                            '<=>',
                                            ', '),
                                    '<=',
                                    ''
                                ),
                                '>',
                                'Doenças Prévias: '
                            )
                        FROM
                            tasy.atendimento_paciente   atepa,
                            tasy.med_avaliacao_paciente mapa
                        WHERE
                                atepa.nr_atendimento = mapa.nr_atendimento (+)
                            AND mapa.nr_atendimento (+) = :nr_atendimento
                            AND mapa.nr_sequencia = (
                                SELECT
                                    MAX(nr_sequencia)
                                FROM
                                    tasy.med_avaliacao_paciente mp
                                WHERE
                                        mp.nr_atendimento = mapa.nr_atendimento
                                    AND mp.nr_seq_tipo_avaliacao = 1154
                                    AND mp.dt_liberacao IS NOT NULL
                                    AND mp.dt_inativacao IS NULL
                            )
                    ) AS diagnosticos_comorbidades
                FROM dual
            ", ['nr_atendimento' => $attendanceNumber]);
            
            $basicData->diagnosticos_comorbidades = $diagResult ? $diagResult[0]->diagnosticos_comorbidades : 'Sem diagnósticos e comorbidades';
            
            // Get antimicrobials data
            $antimicrobialsResult = $this->getPatientAntimicrobials($attendanceNumber);
            $basicData->materiais = $antimicrobialsResult ?: 'Nenhum antimicrobiano prescrito';
            
            // NEW: Get priority exams data
            $priorityExamsResult = $this->getPatientPriorityExams($attendanceNumber);
            $basicData->prioridade_exames = $priorityExamsResult ?: 'Nenhum exame prioritário identificado';
            
            // NEW: Get surgical procedures data
            $surgicalProceduresResult = $this->getPatientSurgicalProcedures($attendanceNumber);
            $basicData->procedimentos_cirurgicos = $surgicalProceduresResult ?: 'Nenhuma cirurgia programada ou realizada recentemente';
            
            // Default values for missing fields
            $basicData->exams = 'Nenhum exame agendado';
            $basicData->ds_isolamento = $basicData->medida_bloqueio ?? 'Não informado';
            
            return $basicData;
        });
    }
    
    /**
     * NEW: Get antimicrobials for a patient
     */
    private function getPatientAntimicrobials($attendanceNumber)
    {
        try {
            $result = DB::connection('tasy')->select("
                SELECT
                    LISTAGG(INITCAP(ds_material)
                            || ' '
                            || nr_dia_util
                            || ' Dia(s) - ',
                            CHR(13)
                            || CHR(13)) WITHIN GROUP(
                    ORDER BY
                        ds_material
                    ) AS materiais
                FROM
                    (
                        SELECT DISTINCT
                            m.ds_material,
                            pt.nr_dia_util,
                            pm.dt_prescricao
                        FROM
                            tasy.material            m,
                            tasy.medic_ficha_tecnica mf,
                            tasy.prescr_medica       pm,
                            tasy.prescr_material     pt
                        WHERE
                                mf.nr_sequencia = (
                                    SELECT
                                        nr_seq_ficha_tecnica
                                    FROM
                                        tasy.material
                                    WHERE
                                        cd_material = m.cd_material_estoque
                                )
                            AND m.cd_material = pt.cd_material
                            AND pm.nr_prescricao = pt.nr_prescricao
                            AND mf.ie_antimicrobiano = 'S'
                            AND pm.dt_prescricao = (
                                SELECT
                                    MAX(dt_prescricao)
                                FROM
                                    tasy.prescr_medica   a,
                                    tasy.prescr_material b
                                WHERE
                                        nr_atendimento = pm.nr_atendimento
                                    AND a.nr_prescricao = b.nr_prescricao
                                    AND b.cd_material = pt.cd_material
                                    AND a.dt_liberacao IS NOT NULL
                                    AND a.dt_validade_prescr >= SYSDATE
                                    AND a.dt_suspensao IS NULL
                            )
                            AND pt.dt_suspensao IS NULL
                            AND pt.nr_dia_util IS NOT NULL
                            AND pm.nr_atendimento = :nr_atendimento_p
                    )
            ", ['nr_atendimento_p' => $attendanceNumber]);
            
            return $result && !empty($result[0]->materiais) ? $result[0]->materiais : null;
            
        } catch (\Exception $e) {
            // Log error but don't break the main flow
            Log::warning("Error fetching antimicrobials for attendance {$attendanceNumber}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * NEW: Get priority exams for a patient
     */
    private function getPatientPriorityExams($attendanceNumber)
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
                // Format the output to be more readable
                $exams = $result[0]->prioridade_exames;
                // Replace line breaks with bullet points for better formatting
                $exams = str_replace(chr(13), chr(13) . '• ', $exams);
                // Add bullet to the beginning if not empty
                if (!empty(trim($exams))) {
                    $exams = '• ' . $exams;
                }
                return $exams;
            }
            
            return null;
            
        } catch (\Exception $e) {
            // Log error but don't break the main flow
            Log::warning("Error fetching priority exams for attendance {$attendanceNumber}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get active alerts for a patient (allergies and isolation warnings)
     * Prevents duplicates and filters out expired alerts properly
     */
    public function getPatientActiveAlerts($attendanceNumber, $personId)
    {
        $cacheKey = "patient_alerts_{$attendanceNumber}_{$personId}";
        
        return Cache::remember($cacheKey, 300, function() use ($attendanceNumber, $personId) {
            $results = DB::connection('tasy')->select("
                SELECT DISTINCT
                    ua.cd_unidade_basica AS leito,
                    ua.nr_atendimento,
                    atp.cd_pessoa_fisica,
                    
                    alp.ds_alerta,
                    alp.nr_seq_tipo_alerta,
                    alp.dt_fim_alerta,
                    
                    apc.nr_seq_motivo_isol,
                    apc.dt_inicio AS dt_inicio_precaucao,
                    apc.dt_termino AS dt_termino_precaucao,
                    
                    mi.ds_motivo AS motivo_isolamento,
                    
                    -- Priority for ordering (alerts first, then isolation)
                    CASE 
                        WHEN alp.ds_alerta IS NOT NULL THEN 1
                        WHEN mi.ds_motivo IS NOT NULL THEN 2
                        ELSE 3
                    END AS alert_priority
                FROM tasy.atendimento_paciente atp
                JOIN tasy.unidade_atendimento ua 
                    ON ua.nr_atendimento = atp.nr_atendimento
                    AND ua.ie_situacao = 'A'
                LEFT JOIN tasy.alerta_paciente alp 
                    ON atp.cd_pessoa_fisica = alp.cd_pessoa_fisica
                    AND alp.ds_alerta IS NOT NULL
                    AND LENGTH(TRIM(alp.ds_alerta)) > 0
                    -- Only active alerts: either no end date OR end date is in the future
                    AND (alp.dt_fim_alerta IS NULL OR TRUNC(alp.dt_fim_alerta) > TRUNC(SYSDATE))
                LEFT JOIN tasy.atendimento_precaucao apc 
                    ON ua.nr_atendimento = apc.nr_atendimento
                    AND apc.dt_liberacao IS NOT NULL
                    AND apc.dt_inativacao IS NULL
                    -- Only active precautions: either no end date OR end date is in the future
                    AND (apc.dt_termino IS NULL OR TRUNC(apc.dt_termino) > TRUNC(SYSDATE))
                LEFT JOIN tasy.motivo_isolamento mi 
                    ON apc.nr_seq_motivo_isol = mi.nr_sequencia
                    AND mi.ds_motivo IS NOT NULL
                    AND LENGTH(TRIM(mi.ds_motivo)) > 0
                WHERE atp.nr_atendimento = :attendance
                  AND atp.cd_pessoa_fisica = :person_id
                  AND atp.dt_alta IS NULL
                  AND (
                    (alp.ds_alerta IS NOT NULL AND LENGTH(TRIM(alp.ds_alerta)) > 0)
                    OR 
                    (mi.ds_motivo IS NOT NULL AND LENGTH(TRIM(mi.ds_motivo)) > 0)
                  )
                ORDER BY alert_priority, 
                         CASE WHEN alp.dt_fim_alerta IS NULL THEN 0 ELSE 1 END, -- Active alerts first
                         alp.dt_fim_alerta DESC,
                         CASE WHEN apc.dt_termino IS NULL THEN 0 ELSE 1 END, -- Active isolation first
                         apc.dt_termino DESC
            ", [
                'attendance' => $attendanceNumber,
                'person_id' => $personId
            ]);
            
            $alerts = [];
            $processedAlerts = []; // Track processed alerts to prevent duplicates
            
            foreach ($results as $result) {
                // Process ALERTA type
                if (!empty($result->ds_alerta) && !empty(trim($result->ds_alerta))) {
                    // Create unique key based on alert content and type
                    $alertKey = 'alert_' . ($result->nr_seq_tipo_alerta ?? 'null') . '_' . md5(trim($result->ds_alerta));
                    
                    if (!isset($processedAlerts[$alertKey])) {
                        $alerts[] = [
                            'type' => 'ALERTA',
                            'message' => trim($result->ds_alerta),
                            'end_date' => $result->dt_fim_alerta,
                            'severity' => 'warning',
                            'type_id' => $result->nr_seq_tipo_alerta,
                            'unique_key' => $alertKey,
                            'is_active' => $result->dt_fim_alerta === null || strtotime($result->dt_fim_alerta) > time()
                        ];
                        $processedAlerts[$alertKey] = true;
                    }
                }
                
                // Process ISOLAMENTO type
                if (!empty($result->motivo_isolamento) && !empty(trim($result->motivo_isolamento))) {
                    // Create unique key based on isolation content and dates
                    $isolationKey = 'isolation_' . ($result->nr_seq_motivo_isol ?? 'null') . '_' . 
                                   md5(trim($result->motivo_isolamento) . '_' . ($result->dt_inicio_precaucao ?? ''));
                    
                    if (!isset($processedAlerts[$isolationKey])) {
                        $alerts[] = [
                            'type' => 'ISOLAMENTO',
                            'message' => trim($result->motivo_isolamento),
                            'start_date' => $result->dt_inicio_precaucao,
                            'end_date' => $result->dt_termino_precaucao,
                            'severity' => 'danger',
                            'motivo_id' => $result->nr_seq_motivo_isol,
                            'unique_key' => $isolationKey,
                            'is_active' => $result->dt_termino_precaucao === null || strtotime($result->dt_termino_precaucao) > time()
                        ];
                        $processedAlerts[$isolationKey] = true;
                    }
                }
            }
            
            // Filter out any alerts that somehow got through but are expired
            $alerts = array_filter($alerts, function($alert) {
                if (isset($alert['end_date']) && $alert['end_date'] !== null) {
                    return strtotime($alert['end_date']) > time();
                }
                return true; // Keep alerts without end dates (they're active)
            });
            
            // Re-index array after filtering
            return array_values($alerts);
        });
    }
}
