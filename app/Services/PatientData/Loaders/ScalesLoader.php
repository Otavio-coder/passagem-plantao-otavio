<?php

namespace App\Services\PatientData\Loaders;

use App\Repositories\EMR\PatientScalesRepository;
use App\Services\PatientData\Contracts\SectorLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScalesLoader implements SectorLoader
{
    private const CACHE_TTL = 600; // 10 min

    public function __construct(private readonly PatientScalesRepository $repository) {}

    public function load(int $sectorId, array $attendanceNumbers): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $cacheKey = "sector_scales_{$sectorId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($attendanceNumbers) {
            return $this->fetchAndFlatten($attendanceNumbers);
        });
    }

    private function fetchAndFlatten(array $attendanceNumbers): array
    {
        try {
            $raw = $this->repository->getPatientsScalesUnified($attendanceNumbers);
        } catch (\Throwable $e) {
            Log::error('ScalesLoader: failed to fetch scales', ['error' => $e->getMessage()]);

            return [];
        }

        $result = [];

        foreach ($raw as $nr => $scales) {
            $flat = [];

            foreach (['mews', 'pews', 'braden', 'morse', 'pain', 'vte'] as $key) {
                $scale = $scales[$key] ?? null;
                if ($scale === null) {
                    continue;
                }

                $flat["{$key}_score"] = $scale['score'];
                $flat["{$key}_previous_score"] = $scale['previous_score'] ?? null;
                $flat["{$key}_previous_timestamp"] = $scale['previous_timestamp'] ?? null;
                $flat["{$key}_needs_assessment"] = $scale['needs_assessment'];
                $flat["{$key}_increased"] = $scale['increased'];
                $flat["{$key}_classification"] = $scale['classification'];
                $flat["{$key}_styling"] = $scale['styling'];
                $flat["{$key}_shift"] = $scale['shift'];
                $flat["{$key}_timestamp"] = $scale['timestamp'] ?? null;
            }

            $flat['ews_display'] = $this->buildEwsDisplay($scales);
            $result[$nr] = $flat;
        }

        return $result;
    }

    private function buildEwsDisplay(array $scales): array
    {
        $mewsScore = $scales['mews']['score'] ?? null;
        $pewsScore = $scales['pews']['score'] ?? null;

        if ($pewsScore !== null) {
            return ['label' => 'PEWS', 'prefix' => 'pews'];
        }

        return ['label' => 'MEWS', 'prefix' => 'mews'];
    }
}
