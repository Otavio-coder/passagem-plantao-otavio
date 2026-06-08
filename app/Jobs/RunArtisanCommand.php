<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RunArtisanCommand implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600; // 10 min — cobre analysis:run com muitos plantonistas

    public int $tries = 1;

    public function __construct(
        public readonly int $runId
    ) {}

    public function handle(): void
    {
        $run = DB::table('command_runs')->find($this->runId);
        if (! $run || $run->status !== 'pending') {
            return;
        }

        DB::table('command_runs')->where('id', $this->runId)->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $params = $run->parameters ? json_decode($run->parameters, true) : [];
        $command = $params['command'] ?? '';
        $options = $params['options'] ?? [];

        try {
            $exitCode = Artisan::call($command, $options);
            $output = Artisan::output();

            DB::table('command_runs')->where('id', $this->runId)->update([
                'status' => $exitCode === 0 ? 'done' : 'failed',
                'output' => $output ?: '(sem saída)',
                'exit_code' => $exitCode,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('command_runs')->where('id', $this->runId)->update([
                'status' => 'failed',
                'output' => $e->getMessage(),
                'exit_code' => 1,
                'completed_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        DB::table('command_runs')->where('id', $this->runId)->update([
            'status' => 'failed',
            'output' => 'Job falhou: '.$e->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
