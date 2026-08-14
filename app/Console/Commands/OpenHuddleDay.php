<?php

namespace App\Console\Commands;

use App\Actions\Huddle\OpenPatientDayAction;
use App\Services\Huddle\HuddleAvailability;
use App\Services\PatientData\PatientDataLoader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Materializa o dia do Huddle: garante que cada paciente ativo dos setores tenha
 * um registro do dia (huddle_patient_days), iniciando em VERMELHO (Red2Green).
 *
 * Roda uma vez por dia (via agendador). É idempotente — apoiado no índice único
 * (nr_atendimento, huddle_date), rodar de novo não duplica nem sobrescreve.
 *
 * Setores processados (nesta ordem de prioridade):
 *   1. --sector=ID (um setor específico)
 *   2. config('huddle.sectors') se preenchido
 *   3. todos os setores ativos (nurse_handover_beds + user_sector_preferences)
 */
class OpenHuddleDay extends Command
{
    protected $signature = 'huddle:open-day
                            {--date= : Data (Y-m-d). Padrão: hoje}
                            {--sector= : Processa apenas este setor (cd_setor_atendimento)}
                            {--force : Materializa mesmo em fim de semana/feriado}';

    protected $description = 'Materializa o dia do Huddle (cria os registros do dia em vermelho) a partir do Tasy';

    public function handle(HuddleAvailability $availability, OpenPatientDayAction $openDay): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();

        if (! $this->option('force') && ! $availability->isAvailable(Carbon::parse($date))) {
            $this->warn("Huddle indisponível em {$date} ({$availability->blockedReason(Carbon::parse($date))}). Use --force para materializar mesmo assim.");

            return self::SUCCESS;
        }

        $sectors = $this->resolveSectors();

        if (empty($sectors)) {
            $this->error('Nenhum setor para processar.');

            return self::FAILURE;
        }

        $this->info('Materializando o Huddle de '.$date.' para '.count($sectors).' setor(es)...');

        $totalPatients = 0;
        $totalSectors = 0;

        foreach ($sectors as $sectorId) {
            try {
                $patients = PatientDataLoader::forSector($sectorId)
                    ->include('demographics')
                    ->get();

                $count = 0;

                foreach ($patients as $patient) {
                    if (empty($patient['has_patient']) || empty($patient['nr_atendimento'])) {
                        continue;
                    }

                    $openDay->execute((int) $patient['nr_atendimento'], $sectorId, null, $date);
                    $count++;
                }

                $totalPatients += $count;
                $totalSectors++;
                $this->line("  setor {$sectorId}: {$count} paciente(s)");
            } catch (\Throwable $e) {
                Log::error('[huddle:open-day] falha ao materializar setor', [
                    'sector_id' => $sectorId,
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  setor {$sectorId}: erro — {$e->getMessage()}");
            }
        }

        $this->info("Concluído: {$totalPatients} paciente(s) em {$totalSectors} setor(es).");

        return self::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function resolveSectors(): array
    {
        if ($this->option('sector')) {
            return [(int) $this->option('sector')];
        }

        $configured = array_filter(array_map('intval', (array) config('huddle.sectors', [])));

        if (! empty($configured)) {
            return array_values(array_unique($configured));
        }

        // Todos os setores ativos: mesma fonte usada pelo aquecimento de cache do SBAR.
        $fromBeds = DB::table('nurse_handover_beds')->distinct()->pluck('sector_id');
        $fromPrefs = DB::table('user_sector_preferences')->distinct()->pluck('sector_code');

        return $fromBeds->merge($fromPrefs)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
