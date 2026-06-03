<?php

namespace App\Console\Commands;

use App\Models\HandoverActivityLog;
use Illuminate\Console\Command;

class PruneHandoverActivityLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'handover:prune-log {--days=180 : Número de dias a manter}';

    protected $description = 'Remove registros de handover_activity_log mais antigos que N dias';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days)->startOfDay();

        $deleted = HandoverActivityLog::where('occurred_at', '<', $cutoff)->delete();

        $this->info("Removidos {$deleted} registros anteriores a {$cutoff->toDateString()} ({$days} dias).");

        return self::SUCCESS;
    }
}
