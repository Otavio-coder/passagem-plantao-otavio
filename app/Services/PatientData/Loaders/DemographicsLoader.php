<?php

namespace App\Services\PatientData\Loaders;

use App\Models\EMR\Core\Sector;
use App\Services\PatientData\Contracts\SectorLoader;
use App\Services\Tasy\TasyFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                    TASY.obter_nome_paciente(atp.nr_atendimento) AS nm_pessoa_fisica,
                    pf.nr_prontuario,
                    pf.dt_nascimento          AS birth_date,
                    FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento) / 12) AS age,
                    MOD(FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)), 12) AS age_months,
                    TRUNC(SYSDATE - ADD_MONTHS(pf.dt_nascimento, FLOOR(MONTHS_BETWEEN(SYSDATE, pf.dt_nascimento)))) AS age_days,
                    pf.ie_sexo                AS sexo,
                    TASY.obter_desc_convenio(TASY.obter_convenio_atendimento(atp.nr_atendimento)) AS convenio,
                    TASY.obter_medico_resp_atend(atp.nr_atendimento, 'N') AS medico_responsavel,
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
                'nr_prontuario' => $bed->nr_prontuario ?? 'N/A',
                'birth_date' => $bed->birth_date ? Carbon::parse($bed->birth_date)->format('d/m/Y') : null,
                'age' => $age,
                'age_detailed' => $formatter->formatDetailedAgeFromParts($age, $bed->age_months ?? null, $bed->age_days ?? null),
                'sexo' => $bed->sexo ?? 'N/A',
                'convenio' => $bed->convenio ?? 'Não informado',
                'medico_responsavel' => $bed->medico_responsavel ?? 'Não informado',
                'dt_entrada' => $bed->dt_entrada ?? null,
                'internment_days' => $internmentDays,
                'is_new_patient' => $isNewPatient,
                'is_pediatric' => $isPediatric,
                // default card styling (overridden later if scales are loaded)
                'gradient_style' => $hasPatient
                    ? 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);'
                    : 'background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);',
                'border_class' => '',
                'text_color_class' => '',
            ];

            $key = $nr ?? ('empty_'.$bed->cd_unidade_basica);
            $result[$key] = $patient;
        }

        return $result;
    }
}
