<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpa dados de teste do Huddle (MySQL).
 *
 * Apaga registros das tabelas de estado do Huddle para permitir testes
 * sem deixar rastro. Não afeta o Oracle/Tasy — é somente MySQL local.
 *
 * Uso:
 *   php artisan huddle:purge-test-data                     # limpa TUDO
 *   php artisan huddle:purge-test-data --sector=123         # só um setor
 *   php artisan huddle:purge-test-data --today              # só registros de hoje
 *   php artisan huddle:purge-test-data --sector=123 --today # combina os dois
 */
class HuddlePurgeTestData extends Command
{
    protected $signature = 'huddle:purge-test-data
                            {--sector= : Limpar apenas um setor específico (sector_id)}
                            {--today : Limpar apenas registros de hoje}
                            {--force : Pular confirmação}';

    protected $description = 'Limpa dados de teste do Huddle (checklist, rounds, dias) — somente MySQL, não afeta Tasy';

    public function handle(): int
    {
        $sector = $this->option('sector');
        $today = $this->option('today');

        $scope = [];
        if ($sector) {
            $scope[] = "setor {$sector}";
        }
        if ($today) {
            $scope[] = 'somente hoje ('.now()->format('d/m/Y').')';
        }
        $scopeLabel = empty($scope) ? 'TODOS os dados do Huddle' : implode(', ', $scope);

        if (! $this->option('force')) {
            if (! $this->confirm("Isso vai apagar: {$scopeLabel}. Continuar?")) {
                $this->info('Cancelado.');

                return self::SUCCESS;
            }
        }

        // 1. huddle_checklist_answers (filhas de huddle_patient_days)
        $answersQuery = DB::table('huddle_checklist_answers');
        if ($sector || $today) {
            $dayIds = $this->filteredDayIds($sector, $today);
            $answersQuery->whereIn('huddle_patient_day_id', $dayIds);
        }
        $deletedAnswers = $answersQuery->delete();

        // 2. huddle_red_reasons (filhas de huddle_patient_days)
        $reasonsQuery = DB::table('huddle_red_reasons');
        if ($sector || $today) {
            $dayIds = $dayIds ?? $this->filteredDayIds($sector, $today);
            $reasonsQuery->whereIn('huddle_patient_day_id', $dayIds);
        }
        $deletedReasons = $reasonsQuery->delete();

        // 3. huddle_patient_days
        $daysQuery = DB::table('huddle_patient_days');
        if ($sector) {
            $daysQuery->where('sector_id', $sector);
        }
        if ($today) {
            $daysQuery->whereDate('huddle_date', now()->toDateString());
        }
        $deletedDays = $daysQuery->delete();

        // 4. huddle_safety_assessments (Round Unidade)
        $safetyQuery = DB::table('huddle_safety_assessments');
        if ($sector) {
            $safetyQuery->where('sector_id', $sector);
        }
        if ($today) {
            $safetyQuery->whereDate('huddle_date', now()->toDateString());
        }
        $deletedSafety = $safetyQuery->delete();

        $this->info('Dados do Huddle limpos:');
        $this->table(
            ['Tabela', 'Registros removidos'],
            [
                ['huddle_checklist_answers', $deletedAnswers],
                ['huddle_red_reasons', $deletedReasons],
                ['huddle_patient_days', $deletedDays],
                ['huddle_safety_assessments', $deletedSafety],
            ]
        );

        $this->newLine();
        $this->info('✅ Nenhum dado do Oracle/Tasy foi afetado.');

        return self::SUCCESS;
    }

    /**
     * @return array<int>
     */
    private function filteredDayIds(?string $sector, bool $today): array
    {
        $query = DB::table('huddle_patient_days');

        if ($sector) {
            $query->where('sector_id', $sector);
        }
        if ($today) {
            $query->whereDate('huddle_date', now()->toDateString());
        }

        return $query->pluck('id')->all();
    }
}
