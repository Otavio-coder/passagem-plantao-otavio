<?php

namespace App\Services\PatientData\Loaders;

use App\Models\EMR\Core\Sector;
use App\Services\PatientData\Contracts\SectorLoader;
use App\Services\Tasy\TasyFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Loader de dados demográficos e identificação dos pacientes de um setor.
 *
 * É o primeiro loader a executar no PatientDataLoader: retorna um mapa indexado por
 * nr_atendimento com todos os leitos do setor (ocupados e vazios). Os demais loaders
 * recebem os nr_atendimento extraídos deste mapa para fazer suas queries em batch.
 *
 * Cache de 15 min (maior que os outros loaders): dados demográficos mudam menos
 * frequentemente que eventos pendentes ou passagens de plantão.
 */
class DemographicsLoader implements SectorLoader
{
    private const CACHE_TTL = 900; // 15 min

    public function load(int $sectorId, array $attendanceNumbers = []): array
    {
        $cacheKey = "sector_demographics_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($sectorId) {
            return $this->fetchAndFormat($sectorId);
        });
    }

    private function fetchAndFormat(int $sectorId): array
    {
        $sector = Sector::with('hospital')->find($sectorId);
        $hospitalId = $sector?->hospital?->nr_sequencia ?? null;
        $hospitalName = $sector?->hospital?->ds_estabelecimento ?? 'Hospital não identificado';

        $sectorContext = [
            'sector_id' => $sectorId,
            'hospital_id' => $hospitalId,
            'hospital_name' => $hospitalName,
        ];

        try {
            // Substituição de 3 stored functions Oracle por linha (obter_nome_paciente,
            // obter_desc_convenio/obter_convenio_atendimento, obter_medico_resp_atend)
            // por JOINs diretos e subqueries escalares — equivalência verificada em produção.
            // Cada função era executada N vezes (uma por linha) pelo motor PL/SQL do Oracle,
            // tornando a query O(N) em round-trips ao banco.
            $rows = DB::connection('tasy')->select("
                SELECT
                    ua.cd_unidade_basica,
                    ua.nr_seq_interno         AS bed_sequence,
                    ua.nr_seq_apresent        AS bed_display_order,
                    ua.ie_situacao            AS bed_status,
                    ua.cd_setor_atendimento,
                    NVL(sa.ds_prescricao, sa.ds_setor_atendimento) AS ds_setor_atendimento,
                    sa.nr_seq_agrupamento     AS hospital_id,
                    CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END AS is_occupied,
                    CASE WHEN atp.nr_atendimento IS NOT NULL THEN 1 ELSE 0 END AS has_patient,
                    atp.nr_atendimento,
                    atp.cd_pessoa_fisica,
                    NVL(pf.nm_social, pf.nm_pessoa_fisica) AS nm_pessoa_fisica,
                    pf.nm_social              AS nm_social,
                    pf.nr_prontuario,
                    pf.dt_nascimento          AS birth_date,
                    FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS age,
                    MOD(FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)), 12) AS age_months,
                    TRUNC(SYSDATE - ADD_MONTHS(pf.dt_nascimento, FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)))) AS age_days,
                    pf.ie_sexo                AS sexo,
                    (SELECT c.ds_convenio
                     FROM tasy.atend_categoria_convenio acc
                     JOIN tasy.convenio c ON c.cd_convenio = acc.cd_convenio
                     WHERE acc.nr_atendimento = atp.nr_atendimento
                       AND ROWNUM = 1)        AS convenio,
                    pf_med.nm_pessoa_fisica   AS medico_responsavel,
                    atp.dt_entrada,
                    TRUNC(SYSDATE - TRUNC(atp.dt_entrada)) AS internment_days,
                    atp.dt_alta_medico,
                    ma.ds_motivo_alta,
                    apa.dt_previsto_alta      AS apa_dt_previsto_alta,
                    apa.dt_registro           AS apa_dt_registro
                FROM tasy.unidade_atendimento ua
                JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
                LEFT JOIN tasy.atendimento_paciente atp ON ua.nr_atendimento = atp.nr_atendimento AND atp.dt_alta IS NULL
                LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                LEFT JOIN tasy.pessoa_fisica pf_med ON pf_med.cd_pessoa_fisica = atp.cd_medico_resp
                LEFT JOIN tasy.motivo_alta ma ON atp.cd_motivo_alta_medica = ma.cd_motivo_alta
                LEFT JOIN (
                    SELECT nr_atendimento, dt_previsto_alta, dt_registro,
                           ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_registro DESC) AS rn
                    FROM tasy.atend_previsao_alta
                    WHERE dt_registro >= SYSDATE - 10
                ) apa ON apa.nr_atendimento = ua.nr_atendimento AND apa.rn = 1
                WHERE ua.cd_setor_atendimento = :sector_id
                  AND ua.ie_situacao = 'A'
                ORDER BY ua.nr_seq_apresent ASC, ua.cd_unidade_basica ASC
            ", ['sector_id' => $sectorId]);
        } catch (\Throwable $e) {
            Log::error('DemographicsLoader: failed to fetch beds', [
                'sector_id' => $sectorId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $formatter = new TasyFormatter;
        $result = [];

        foreach ($rows as $bed) {
            $nr = $bed->nr_atendimento;
            $hasPatient = (bool) ($bed->has_patient ?? false);
            $age = $bed->age ?? 99;
            $isPediatric = $age < 18;
            $internmentDays = is_numeric($bed->internment_days ?? null) ? floatval($bed->internment_days) : null;
            $isNewPatient = ($internmentDays === null || $internmentDays < 1);

            $patient = [
                'cd_unidade_basica' => $bed->cd_unidade_basica ?? 'N/A',
                'bed_sequence' => $bed->bed_sequence ?? 0,
                'bed_display_order' => (int) ($bed->bed_display_order ?? $bed->bed_sequence ?? 0),
                'bed_status' => $bed->bed_status ?? 'A',
                'cd_setor_atendimento' => $bed->cd_setor_atendimento ?? null,
                'ds_setor_atendimento' => $bed->ds_setor_atendimento ?? 'Setor não identificado',
                'hospital_id' => $sectorContext['hospital_id'],
                'hospital_name' => $sectorContext['hospital_name'],
                'nr_atendimento' => $nr,
                'is_occupied' => $hasPatient,
                'has_patient' => $hasPatient,
                'cd_pessoa_fisica' => $bed->cd_pessoa_fisica ?? null,
                'nm_pessoa_fisica' => $bed->nm_pessoa_fisica ?? 'Nome não informado',
                'nm_social' => $bed->nm_social ?? null,
                'nr_prontuario' => $bed->nr_prontuario ?? 'N/A',
                'birth_date' => $bed->birth_date ? Carbon::parse($bed->birth_date)->format('d/m/Y') : null,
                'age' => $age,
                'age_months' => is_numeric($bed->age_months ?? null) ? (int) $bed->age_months : null,
                'age_days' => is_numeric($bed->age_days ?? null) ? (int) $bed->age_days : null,
                'age_detailed' => $formatter->formatDetailedAgeFromParts($age, $bed->age_months ?? null, $bed->age_days ?? null),
                'sexo' => $bed->sexo ?? 'N/A',
                'convenio' => $bed->convenio ?? 'Não informado',
                'medico_responsavel' => $bed->medico_responsavel ?? 'Não informado',
                'dt_entrada' => $bed->dt_entrada ?? null,
                'internment_days' => $internmentDays,
                'is_new_patient' => $isNewPatient,
                'is_pediatric' => $isPediatric,
                // estilo padrão do card (sobrescrito pelo TasyFormatter se escalas forem carregadas)
                'gradient_style' => $hasPatient
                    ? 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);'
                    : 'background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);',
                'border_class' => '',
                'text_color_class' => '',
            ];

            // Chave do mapa: nr_atendimento para leitos ocupados, 'empty_X' para vazios.
            // Leitos vazios precisam ser incluídos para exibir a grade completa do setor
            // (um leito vazio é informação relevante na passagem de plantão).
            $key = $nr ?? ('empty_'.$bed->cd_unidade_basica);
            $result[$key] = $patient;
        }

        return $result;
    }
}
