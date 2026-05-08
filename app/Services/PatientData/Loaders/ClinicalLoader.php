<?php

namespace App\Services\PatientData\Loaders;

use App\Repositories\EMR\PatientClinicalRepository;
use App\Services\PatientData\Contracts\SectorLoader;
use App\Services\Scola\ScolaExamStatusService;
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
                'hemoc_history' => $this->parseHemocHistory($details->hemoc_history ?? null),
                'has_allergy' => $formatter->checkHasAllergy($alergias),
                'has_isolation' => $formatter->checkHasIsolation($medidaBloqueio),
                'alerts' => [],
            ];
        }

        $this->enrichHemocHistoryWithScolaResults($result);

        return $result;
    }

    /** @param  array<int|string, array<string, mixed>>  $result */
    private function enrichHemocHistoryWithScolaResults(array &$result): void
    {
        $prescricoes = [];

        foreach ($result as $patient) {
            foreach ($patient['hemoc_history'] as $hist) {
                $nr = $hist['nr_prescricao'] ?? '';
                if ($nr !== '') {
                    $prescricoes[$nr] = $nr;
                }
            }
        }

        if (empty($prescricoes)) {
            return;
        }

        try {
            $resultados = app(ScolaExamStatusService::class)->getHemocResults(array_values($prescricoes));
        } catch (\Throwable $e) {
            Log::warning('ClinicalLoader: falha ao enriquecer hemoc_history com SCOLA', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($result as $nr => &$patient) {
            foreach ($patient['hemoc_history'] as $i => $hist) {
                $result[$nr]['hemoc_history'][$i]['scola_resultado'] = $resultados[$hist['nr_prescricao']] ?? null;
            }
        }
        unset($patient);
    }

    /**
     * @return array<int, array{nr_prescricao: string, date: string, name: string, status_execucao: string, has_result: bool, dt_prescricao: string, scola_resultado: string|null}>
     */
    private function parseHemocHistory(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        $items = [];
        foreach (explode('||', $value) as $raw) {
            $parts = explode('::', trim($raw), 6);
            $nr = trim($parts[0] ?? '');
            if ($nr === '') {
                continue;
            }

            $items[] = [
                'nr_prescricao' => $nr,
                'date' => trim($parts[1] ?? ''),
                'name' => trim($parts[2] ?? ''),
                'status_execucao' => trim($parts[3] ?? ''),
                'has_result' => ((int) trim($parts[4] ?? '0')) > 0,
                'dt_prescricao' => trim($parts[5] ?? ''),
            ];
        }

        return $items;
    }

    private function parsePipeSeparated(?string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $value))));
    }
}
