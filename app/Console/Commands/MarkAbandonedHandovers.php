<?php

namespace App\Console\Commands;

use App\Models\ShiftHandover;
use Illuminate\Console\Command;

class MarkAbandonedHandovers extends Command
{
    protected $signature = 'handover:mark-abandoned
                            {--timeout=20 : Minutes of inactivity before marking a session as abandoned}
                            {--dry-run : Preview affected sessions without updating}';

    protected $description = 'Mark active handover sessions as abandoned when they show no activity for the configured timeout';

    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes($timeout);

        $query = ShiftHandover::where('status', ShiftHandover::STATUS_ACTIVE)
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_activity_at', '<', $cutoff)
                    ->orWhere(function ($q2) use ($cutoff): void {
                        $q2->whereNull('last_activity_at')
                            ->where('started_at', '<', $cutoff);
                    });
            });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No abandoned handover sessions found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("DRY RUN — {$count} session(s) would be marked as abandoned.");
            $query->each(fn ($s) => $this->line("  ID {$s->id} | user_id {$s->user_id} | started {$s->started_at}"));

            return self::SUCCESS;
        }

        $query->update([
            'status' => ShiftHandover::STATUS_ABANDONED,
            'abandoned_at' => now(),
        ]);

        $this->info("Marked {$count} handover session(s) as abandoned.");

        return self::SUCCESS;
    }
}
