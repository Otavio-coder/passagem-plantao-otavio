<?php

namespace App\Jobs;

use App\Services\PatientData\PatientDataLoader;
use App\Services\PendingEvents\PatientPendingEventsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WarmSectorCacheJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public readonly int $sectorId,
        public readonly bool $forceStatic = false,
    ) {}

    public function handle(PatientPendingEventsService $pendingService): void
    {
        if ($this->sectorId <= 0) {
            return;
        }

        $start = microtime(true);

        try {
            // Only warm static data if not already cached (or if forced)
            $demographicsKey = "sector_demographics_{$this->sectorId}";
            if ($this->forceStatic || ! Cache::has($demographicsKey)) {
                PatientDataLoader::forSector($this->sectorId)
                    ->include('demographics', 'scales', 'clinical', 'multidisciplinary', 'surgery')
                    ->get();
            }

            // Always warm pending events (dynamic, short TTL)
            $pendingKey = "sector_pending_fast_{$this->sectorId}";
            if (! Cache::has($pendingKey)) {
                $pendingService->getPendingEventsForSector($this->sectorId);
            }

            Log::info('WarmSectorCacheJob: sector warmed', [
                'sector_id' => $this->sectorId,
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WarmSectorCacheJob: failed', [
                'sector_id' => $this->sectorId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
