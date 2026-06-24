<?php

namespace App\Services\PatientData\Loaders;

use App\Services\PatientData\Contracts\SectorLoader;
use App\Services\PendingEvents\PatientPendingEventsService;

class PendingEventsLoader implements SectorLoader
{
    public function __construct(private readonly PatientPendingEventsService $service) {}

    public function load(int $sectorId, array $attendanceNumbers): array
    {
        // Service already caches with sector_pending_fast_{sectorId} at 3 min TTL
        $raw = $this->service->getPendingEventsForSector($sectorId);

        $result = [];

        foreach ($raw as $nr => $data) {
            $result[$nr] = [
                'pending_events' => $data['pending_events'] ?? [],
                'discharge_info' => $data['discharge'] ?? null,
            ];
        }

        return $result;
    }
}
