<?php

namespace App\Services\PatientData\Contracts;

interface SectorLoader
{
    /**
     * Load data for the given attendances within a sector.
     *
     * @param  int[]  $attendanceNumbers  Active attendances in the sector (empty for demographics loader)
     * @return array<int, array<string, mixed>> Keyed by nr_atendimento, flat data ready for merge
     */
    public function load(int $sectorId, array $attendanceNumbers): array;
}
