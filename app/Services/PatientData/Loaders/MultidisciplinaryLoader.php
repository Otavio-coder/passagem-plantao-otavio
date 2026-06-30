<?php

namespace App\Services\PatientData\Loaders;

use App\Repositories\EMR\PatientMultidisciplinaryRepository;
use App\Services\PatientData\Contracts\SectorLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MultidisciplinaryLoader implements SectorLoader
{
    private const CACHE_TTL = 600; // 10 min

    public function __construct(private readonly PatientMultidisciplinaryRepository $repository) {}

    public function load(int $sectorId, array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $cacheKey = "sector_multi_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($attendanceNumbers) {
            return $this->fetch($attendanceNumbers);
        });
    }

    private function fetch(array $attendanceNumbers): array
    {
        try {
            $teams = $this->repository->getMultidisciplinaryTeams($attendanceNumbers);
        } catch (\Throwable $e) {
            Log::error('MultidisciplinaryLoader: failed', ['error' => $e->getMessage()]);

            return [];
        }

        $result = [];

        foreach ($attendanceNumbers as $nr) {
            $result[$nr] = [
                'multidisciplinary' => $teams[$nr] ?? $this->defaultTeams(),
            ];
        }

        return $result;
    }

    private function defaultTeams(): array
    {
        return [
            'fisioterapia' => false,
            'psicologia' => false,
            'nutricao' => false,
            'fonoaudiologia' => false,
            'servico_social' => false,
            'acessos_vasculares' => false,
        ];
    }
}
