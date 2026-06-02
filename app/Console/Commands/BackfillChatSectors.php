<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfills sector info on chat_messages and chat_messages_archive via UNIDADE_ATEND_HIST.
 *
 * - chat_messages (per row): single sector at message creation time
 * - chat_messages_archive: JSON array of ALL sectors during first→last message period
 *
 * Usage:
 *   php artisan chat:backfill-sectors
 *   php artisan chat:backfill-sectors --dry-run
 */
class BackfillChatSectors extends Command
{
    protected $signature = 'chat:backfill-sectors
                            {--dry-run : Simula sem alterar dados}
                            {--chunk=100 : Número de atendimentos por lote}';

    protected $description = 'Backfill sector_name em chat_messages (single) e chat_messages_archive (JSON array) via Tasy';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunk = (int) $this->option('chunk');

        if ($dryRun) {
            $this->warn('Modo dry-run — nenhum dado será alterado.');
        }

        $this->info('=== Backfill de Setores nas Mensagens de Chat ===');
        $this->newLine();

        $activeStats = $this->backfillActiveMessages($chunk, $dryRun);
        $archiveStats = $this->backfillArchive($chunk, $dryRun);

        $this->newLine();
        $this->info("chat_messages: {$activeStats['updated']} atualizados, {$activeStats['not_found']} sem histórico no Tasy.");
        $this->info("chat_messages_archive: {$archiveStats['updated']} atualizados, {$archiveStats['not_found']} sem histórico no Tasy.");

        return 0;
    }

    /**
     * Backfills chat_messages rows (sector_name IS NULL).
     * Uses the message's own created_at to find the exact sector via UNIDADE_ATEND_HIST.
     */
    private function backfillActiveMessages(int $chunk, bool $dryRun): array
    {
        $this->info('Processando chat_messages...');

        $nrs = DB::table('chat_messages')
            ->whereNull('sector_name')
            ->distinct()
            ->pluck('nr_atendimento');

        if ($nrs->isEmpty()) {
            $this->line('  Nenhum registro sem setor em chat_messages.');

            return ['updated' => 0, 'not_found' => 0];
        }

        $this->line("  {$nrs->count()} atendimentos sem setor.");

        $updated = 0;
        $notFound = 0;
        $bar = $this->output->createProgressBar($nrs->count());
        $bar->start();

        foreach ($nrs->chunk($chunk) as $batch) {
            foreach ($batch as $nr) {
                try {
                    $ts = DB::table('chat_messages')
                        ->where('nr_atendimento', $nr)
                        ->max('created_at');

                    if (! $ts) {
                        $notFound++;
                        $bar->advance();

                        continue;
                    }

                    $sector = $this->resolveSectorAtTime((int) $nr, $ts);

                    if (! $sector) {
                        $notFound++;
                        $bar->advance();

                        continue;
                    }

                    if (! $dryRun) {
                        DB::table('chat_messages')
                            ->where('nr_atendimento', $nr)
                            ->whereNull('sector_name')
                            ->update([
                                'sector_id' => $sector['id'],
                                'sector_name' => $sector['name'],
                            ]);
                    }

                    $updated++;
                } catch (\Exception $e) {
                    Log::warning('[BackfillChatSectors] chat_messages erro', [
                        'nr_atendimento' => $nr,
                        'error' => $e->getMessage(),
                    ]);
                    $notFound++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        return ['updated' => $updated, 'not_found' => $notFound];
    }

    /**
     * Backfills chat_messages_archive rows (sector_name IS NULL).
     * Collects ALL distinct sectors during first_message_at → last_message_at and stores as JSON.
     */
    private function backfillArchive(int $chunk, bool $dryRun): array
    {
        $this->info('Processando chat_messages_archive...');

        $nrs = DB::table('chat_messages_archive')
            ->whereNull('sector_name')
            ->get(['nr_atendimento', 'first_message_at', 'last_message_at']);

        if ($nrs->isEmpty()) {
            $this->line('  Nenhum registro sem setor em chat_messages_archive.');

            return ['updated' => 0, 'not_found' => 0];
        }

        $this->line("  {$nrs->count()} registros sem setor.");

        $updated = 0;
        $notFound = 0;
        $bar = $this->output->createProgressBar($nrs->count());
        $bar->start();

        foreach ($nrs->chunk($chunk) as $batch) {
            foreach ($batch as $row) {
                try {
                    $sectors = $this->resolveSectorsInPeriod(
                        (int) $row->nr_atendimento,
                        $row->first_message_at,
                        $row->last_message_at
                    );

                    if (empty($sectors)) {
                        $notFound++;
                        $bar->advance();

                        continue;
                    }

                    if (! $dryRun) {
                        DB::table('chat_messages_archive')
                            ->where('nr_atendimento', $row->nr_atendimento)
                            ->whereNull('sector_name')
                            ->update(['sector_name' => json_encode($sectors)]);
                    }

                    $updated++;
                } catch (\Exception $e) {
                    Log::warning('[BackfillChatSectors] archive erro', [
                        'nr_atendimento' => $row->nr_atendimento,
                        'error' => $e->getMessage(),
                    ]);
                    $notFound++;
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        return ['updated' => $updated, 'not_found' => $notFound];
    }

    /**
     * Returns the sector the patient was in at a specific timestamp.
     *
     * @return array{id: int, name: string}|null
     */
    private function resolveSectorAtTime(int $nr, string $ts): ?array
    {
        $rows = DB::connection('tasy')->select('
            SELECT ua.cd_setor_atendimento,
                   NVL(sa.ds_prescricao, sa.ds_setor_atendimento) AS ds_setor,
                   uah.dt_historico,
                   uah.dt_fim_historico
            FROM tasy.unidade_atend_hist uah
            JOIN tasy.unidade_atendimento ua ON ua.nr_seq_interno = uah.nr_seq_unidade
            JOIN tasy.setor_atendimento sa ON sa.cd_setor_atendimento = ua.cd_setor_atendimento
            WHERE uah.nr_atendimento = :nr
            ORDER BY uah.dt_historico DESC
        ', ['nr' => $nr]);

        if (empty($rows)) {
            return null;
        }

        $tsCarbon = Carbon::parse($ts);

        foreach ($rows as $row) {
            $start = Carbon::parse($row->dt_historico);
            $end = $row->dt_fim_historico ? Carbon::parse($row->dt_fim_historico) : null;

            if ($tsCarbon->gte($start) && (! $end || $tsCarbon->lte($end))) {
                return ['id' => (int) $row->cd_setor_atendimento, 'name' => $row->ds_setor];
            }
        }

        // Fallback: closest entry before the timestamp
        foreach ($rows as $row) {
            if ($tsCarbon->gte(Carbon::parse($row->dt_historico))) {
                return ['id' => (int) $row->cd_setor_atendimento, 'name' => $row->ds_setor];
            }
        }

        return ['id' => (int) $rows[0]->cd_setor_atendimento, 'name' => $rows[0]->ds_setor];
    }

    /**
     * Returns all distinct sectors the patient was in during [firstAt, lastAt].
     * Ordered by total time spent descending (most prominent sector first).
     *
     * @return string[]
     */
    private function resolveSectorsInPeriod(int $nr, string $firstAt, string $lastAt): array
    {
        $rows = DB::connection('tasy')->select('
            SELECT ua.cd_setor_atendimento,
                   NVL(sa.ds_prescricao, sa.ds_setor_atendimento) AS ds_setor,
                   uah.dt_historico,
                   uah.dt_fim_historico
            FROM tasy.unidade_atend_hist uah
            JOIN tasy.unidade_atendimento ua ON ua.nr_seq_interno = uah.nr_seq_unidade
            JOIN tasy.setor_atendimento sa ON sa.cd_setor_atendimento = ua.cd_setor_atendimento
            WHERE uah.nr_atendimento = :nr
            ORDER BY uah.dt_historico ASC
        ', ['nr' => $nr]);

        if (empty($rows)) {
            return [];
        }

        $periodStart = Carbon::parse($firstAt);
        $periodEnd = Carbon::parse($lastAt);

        // Calculate time each sector overlaps with the message period
        $sectorSeconds = [];
        $sectorNames = [];

        foreach ($rows as $row) {
            $rowStart = Carbon::parse($row->dt_historico);
            $rowEnd = $row->dt_fim_historico ? Carbon::parse($row->dt_fim_historico) : $periodEnd;

            // Clamp to message period
            $overlapStart = $rowStart->lt($periodStart) ? $periodStart : $rowStart;
            $overlapEnd = $rowEnd->gt($periodEnd) ? $periodEnd : $rowEnd;

            if ($overlapStart->gte($overlapEnd)) {
                continue;
            }

            $seconds = $overlapStart->diffInSeconds($overlapEnd);
            $key = (int) $row->cd_setor_atendimento;

            $sectorSeconds[$key] = ($sectorSeconds[$key] ?? 0) + $seconds;
            $sectorNames[$key] = $row->ds_setor;
        }

        if (empty($sectorSeconds)) {
            return [];
        }

        // Sort by time descending so most prominent sector appears first
        arsort($sectorSeconds);

        return array_values(array_map(fn ($id) => $sectorNames[$id], array_keys($sectorSeconds)));
    }
}
