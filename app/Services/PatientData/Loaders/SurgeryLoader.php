<?php

namespace App\Services\PatientData\Loaders;

use App\Repositories\EMR\PatientSurgeryRepository;
use App\Services\PatientData\Contracts\SectorLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SurgeryLoader implements SectorLoader
{
    private const CACHE_TTL = 600; // 10 min

    public function __construct(private readonly PatientSurgeryRepository $repository) {}

    public function load(int $sectorId, array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $cacheKey = "sector_surgery_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($attendanceNumbers) {
            return $this->fetch($attendanceNumbers);
        });
    }

    private function fetch(array $attendanceNumbers): array
    {
        try {
            $surgeries = $this->repository->getFutureSurgeriesForAttendances($attendanceNumbers);
        } catch (\Throwable $e) {
            Log::error('SurgeryLoader: failed', ['error' => $e->getMessage()]);

            return [];
        }

        $result = [];

        foreach ($attendanceNumbers as $nr) {
            $result[$nr] = [
                'has_surgery' => $surgeries['surgery'][$nr] ?? false,
                'procedimentos_cirurgicos' => $surgeries['surgery_detailed'][$nr] ?? [],
            ];
        }

        return $result;
    }
}
