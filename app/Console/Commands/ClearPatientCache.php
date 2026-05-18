<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClearPatientCache extends Command
{
    protected $signature = 'cache:patient
                            {sector? : ID do setor (omitir limpa todos os setores)}
                            {--attendance= : Nr de atendimento específico}';

    protected $description = 'Limpa caches de pacientes/setores sem afetar sessões Livewire de outros usuários.';

    public function handle(): int
    {
        $sectorId = $this->argument('sector');
        $attendanceNr = $this->option('attendance');

        if ($attendanceNr) {
            $this->clearAttendance((int) $attendanceNr);

            return self::SUCCESS;
        }

        if ($sectorId) {
            $this->clearSector((int) $sectorId);

            return self::SUCCESS;
        }

        $this->clearAllSectors();

        return self::SUCCESS;
    }

    private function clearAttendance(int $nr): void
    {
        $keys = [
            "sbar_patient_details_v3_{$nr}",
            "patient_prescriptions_v5_{$nr}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->info("Cache do atendimento {$nr} removido (".count($keys).' chaves).');
    }

    private function clearSector(int $sectorId): void
    {
        $keys = [
            "sector_demographics_{$sectorId}",
            "sector_scales_{$sectorId}",
            "sector_clinical_{$sectorId}",
            "sector_multi_{$sectorId}",
            "sector_surgery_{$sectorId}",
            "sector_pending_fast_{$sectorId}",
            "sector_handover_{$sectorId}_M",
            "sector_handover_{$sectorId}_T",
            "sector_handover_{$sectorId}_N",
            "expired_scales_sector_{$sectorId}_".now()->format('YmdHi'),
            "expired_scales_sector_{$sectorId}_".now()->subMinute()->format('YmdHi'),
        ];

        $removed = 0;
        foreach ($keys as $key) {
            if (Cache::forget($key)) {
                $removed++;
            }
        }

        $this->info("Setor {$sectorId}: {$removed} chaves removidas.");
    }

    private function clearAllSectors(): void
    {
        // Deleta diretamente na tabela para evitar iterar chave a chave,
        // mas preserva as entradas lw_computed (Livewire sessões ativas).
        $prefix = config('cache.prefix', 'laravel_cache');
        $prefixDb = $prefix ? $prefix.'_' : '';

        $patterns = [
            "{$prefixDb}sector_%",
            "{$prefixDb}sbar_patient_details%",
            "{$prefixDb}patient_prescriptions%",
            "{$prefixDb}patient_med_schedule%",
            "{$prefixDb}expired_scales_sector%",
        ];

        $total = 0;
        foreach ($patterns as $pattern) {
            $deleted = DB::table('cache')->where('key', 'like', $pattern)->delete();
            $this->line("  {$pattern}: {$deleted} entradas removidas");
            $total += $deleted;
        }

        $this->info("Total: {$total} entradas de cache de pacientes removidas.");
        $this->line('<fg=gray>Entradas lw_computed (Livewire) preservadas.</>');
    }
}
