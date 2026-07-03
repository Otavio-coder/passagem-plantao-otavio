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
                // is_oculto (HGT/curativos/fisioterapia — origem 4): só o relatório de
                // pendências exibe, via "Mostrar ocultos"; cards SBAR não.
                'pending_events' => array_values(array_filter(
                    $data['pending_events'] ?? [],
                    fn (array $event): bool => empty($event['is_oculto'])
                )),
                'discharge_info' => $data['discharge'] ?? null,
            ];
        }

        return $result;
    }
}
