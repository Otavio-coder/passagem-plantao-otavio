<?php

namespace App\Console\Commands;

use App\Services\PatientData\PatientDataLoader;
use App\Services\PendingEvents\PatientPendingEventsService;
use App\Services\Tasy\TasyService;
use Illuminate\Console\Command;

class WarmSectorCache extends Command
{
    protected $signature = 'cache:warm-sector
                            {sector_id : ID do setor (cd_setor_atendimento)}
                            {--force : Limpa caches existentes antes de aquecer}
                            {--skip-static : Pula dados estáticos (demographics, scales, clinical)}';

    protected $description = 'Pré-aquece o cache de um setor para o SBAR (Oracle → Redis)';

    public function handle(
        PatientPendingEventsService $pendingService,
        TasyService $tasyService,
    ): int {
        $sectorId = (int) $this->argument('sector_id');

        if ($sectorId <= 0) {
            $this->error('sector_id inválido.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            PatientDataLoader::forSector($sectorId)->clearCache();
            $this->line("  Cache do setor {$sectorId} limpo.");
        }

        $start = microtime(true);
        $this->info("Aquecendo setor {$sectorId}...");

        // 1. Static loaders (demographics + scales + clinical + multidisciplinary + surgery)
        if (! $this->option('skip-static')) {
            $this->line('  [1/2] Carregando dados estáticos (demographics, escalas, clínico)...');
            $t = microtime(true);

            $attendances = PatientDataLoader::forSector($sectorId)
                ->include('demographics', 'scales', 'clinical', 'multidisciplinary', 'surgery')
                ->get();

            $elapsed = round((microtime(true) - $t) * 1000);
            $count = count(array_filter($attendances, fn ($p) => $p['has_patient'] ?? false));
            $this->line("     ✓ {$count} pacientes — {$elapsed}ms");
        }

        // 2. Pending events (the slowest — separate so it can be skipped/retried)
        $this->line('  [2/2] Carregando pendências (pending events)...');
        $t = microtime(true);

        $pending = $pendingService->getPendingEventsForSector($sectorId);

        $elapsed = round((microtime(true) - $t) * 1000);
        $this->line('     ✓ '.count($pending)." atendimentos com pendências — {$elapsed}ms");

        $total = round((microtime(true) - $start) * 1000);
        $this->info("Setor {$sectorId} aquecido em {$total}ms.");

        return self::SUCCESS;
    }
}
