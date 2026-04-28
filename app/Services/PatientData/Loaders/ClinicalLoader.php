<?php

namespace App\Services\PatientData\Loaders;

use App\Repositories\EMR\PatientClinicalRepository;
use App\Services\PatientData\Contracts\SectorLoader;
use App\Services\Tasy\TasyFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClinicalLoader implements SectorLoader
{
    private const CACHE_TTL = 900; // 15 min

    public function __construct(private readonly PatientClinicalRepository $repository) {}

    public function load(int $sectorId, array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $cacheKey = "sector_clinical_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($attendanceNumbers) {
            return $this->fetchAndFlatten($attendanceNumbers);
        });
    }

    private function fetchAndFlatten(array $attendanceNumbers): array
    {
        try {
            $batch = $this->repository->getBatchClinicalDetails($attendanceNumbers);
        } catch (\Throwable $e) {
            Log::error('ClinicalLoader: failed to fetch clinical details', ['error' => $e->getMessage()]);

            return [];
        }

        $formatter = new TasyFormatter;
        $result = [];

        foreach ($attendanceNumbers as $nr) {
            $details = $batch[$nr] ?? null;

            $alergias = $details->alergias_detalhadas ?? null;
            $medidaBloqueio = $details->medida_bloqueio ?? 'Não';
            $dispositivos = $details->dispositivos ?? null;
            $diagnosticos = $details->diagnosticos_comorbidades ?? null;

            $result[$nr] = [
                'diagnosticos_comorbidades' => $diagnosticos,
                'diagnosticos_list' => $this->parsePipeSeparated($diagnosticos),
                'medida_bloqueio' => $medidaBloqueio,
                'motivos_isolamento' => $details->motivos_isolamento ?? null,
                'avaliacao_enf' => $details->avaliacao_enf ?? null,
                'plano_educ' => $details->plano_educ ?? null,
                'pe_data' => $details->pe_data ?? null,
                'ds_queda' => $details->ds_queda ?? 'Não',
                'diag' => $details->diag ?? null,
                'dispositivos' => $dispositivos,
                'dispositivos_list' => $this->parsePipeSeparated($dispositivos),
                'alergias_detalhadas' => $alergias,
                'alergias_items' => $formatter->parseAllergyItemsPublic($alergias),
                'materiais' => $details->materiais ?? null,
                'ultima_hemocultura' => $details->ultima_hemocultura ?? null,
                'hemocultura_pendente' => (int) ($details->hemocultura_pendente ?? 0) === 1,
                'has_allergy' => $formatter->checkHasAllergy($alergias),
                'has_isolation' => $formatter->checkHasIsolation($medidaBloqueio),
                'alerts' => [],
            ];
        }

        return $result;
    }

    private function parsePipeSeparated(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $value))));
    }
}
