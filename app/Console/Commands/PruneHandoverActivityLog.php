<?php

namespace App\Console\Commands;

use App\Models\HandoverActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneHandoverActivityLog extends Command
{
    protected $signature = 'handover:prune-log {--days=180 : Número de dias a manter}';

    protected $description = 'Remove registros de audit logs (handover + pendências) mais antigos que N dias';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days)->startOfDay();

        $deletedHandover = HandoverActivityLog::where('occurred_at', '<', $cutoff)->delete();
        $deletedPending = DB::table('pending_events_access_logs')
            ->where('occurred_at', '<', $cutoff)
            ->delete();

        $this->info("Removidos {$deletedHandover} registros de handover e {$deletedPending} de pendências anteriores a {$cutoff->toDateString()} ({$days} dias).");

        return self::SUCCESS;
    }
}
