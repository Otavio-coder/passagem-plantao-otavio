<?php

namespace App\Livewire;

use App\Jobs\RunArtisanCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CommandCenter extends Component
{
    public ?int $activeRunId = null;

    // ── Catálogo de comandos ───────────────────────────────────────────────────

    public function catalog(): array
    {
        return [
            'Cache e performance' => [
                [
                    'id' => 'cache-clear',
                    'label' => 'Limpar cache da aplicação',
                    'description' => 'Remove todos os dados em cache (Redis/file). Recomendado após mudanças de config ou dados corrompidos.',
                    'icon' => 'fa-broom',
                    'sync' => true,
                    'command' => 'cache:clear',
                    'options' => [],
                    'confirm' => false,
                    'danger' => false,
                ],
                [
                    'id' => 'view-clear',
                    'label' => 'Limpar views compiladas',
                    'description' => 'Remove o cache de templates Blade. Necessário quando mudanças na view não aparecem.',
                    'icon' => 'fa-code',
                    'sync' => true,
                    'command' => 'view:clear',
                    'options' => [],
                    'confirm' => false,
                    'danger' => false,
                ],
                [
                    'id' => 'config-clear',
                    'label' => 'Limpar cache de configuração',
                    'description' => 'Força releitura do .env e config/. Necessário após alterar variáveis de ambiente.',
                    'icon' => 'fa-gear',
                    'sync' => true,
                    'command' => 'config:clear',
                    'options' => [],
                    'confirm' => false,
                    'danger' => false,
                ],
                [
                    'id' => 'route-clear',
                    'label' => 'Limpar cache de rotas',
                    'description' => 'Recompila o mapa de rotas. Útil se novas rotas não estão sendo reconhecidas.',
                    'icon' => 'fa-route',
                    'sync' => true,
                    'command' => 'route:clear',
                    'options' => [],
                    'confirm' => false,
                    'danger' => false,
                ],
                [
                    'id' => 'panorama-cache',
                    'label' => 'Limpar cache do Panorama',
                    'description' => 'Remove o cache de métricas do Panorama (1h TTL). As métricas serão recalculadas na próxima visita.',
                    'icon' => 'fa-gauge-high',
                    'sync' => true,
                    'command' => 'cache:forget',
                    'options' => ['panorama_index_v3'],
                    'confirm' => false,
                    'danger' => false,
                ],
                [
                    'id' => 'sbar-cache',
                    'label' => 'Limpar cache SBAR de todos os setores',
                    'description' => 'Remove cache de pacientes de todos os setores configurados (força recarregamento do Oracle).',
                    'icon' => 'fa-hospital',
                    'sync' => true,
                    'command' => 'cache:clear-sbar',
                    'options' => [],
                    'confirm' => false,
                    'danger' => false,
                ],
                [
                    'id' => 'pending-cache',
                    'label' => 'Limpar cache de pendências',
                    'description' => 'Remove sector_pending_fast_* de todos os setores. Útil após correções em prescrições, Scola ou quando pendências não aparecem corretamente.',
                    'icon' => 'fa-list-check',
                    'sync' => true,
                    'command' => 'cache:clear-pending',
                    'options' => [],
                    'confirm' => false,
                    'danger' => false,
                ],
            ],
            'Manutenção' => [
                [
                    'id' => 'prune-logs',
                    'label' => 'Limpar logs antigos (>180 dias)',
                    'description' => 'Remove registros de handover_activity_log e pending_events_access_logs mais antigos que 180 dias.',
                    'icon' => 'fa-trash-can',
                    'sync' => true,
                    'command' => 'handover:prune-log',
                    'options' => ['--days' => 180],
                    'confirm' => true,
                    'danger' => false,
                ],
                [
                    'id' => 'prune-failed',
                    'label' => 'Limpar jobs falhos (>7 dias)',
                    'description' => 'Remove entradas antigas da fila de jobs com falha.',
                    'icon' => 'fa-circle-xmark',
                    'sync' => true,
                    'command' => 'queue:prune-failed',
                    'options' => ['--hours' => 168],
                    'confirm' => false,
                    'danger' => false,
                ],
                [
                    'id' => 'horizon-status',
                    'label' => 'Status do Horizon',
                    'description' => 'Verifica se o Horizon está ativo e processando filas.',
                    'icon' => 'fa-circle-play',
                    'sync' => true,
                    'command' => 'horizon:status',
                    'options' => [],
                    'confirm' => false,
                    'danger' => false,
                ],
            ],
        ];
    }

    // ── Executar comando ───────────────────────────────────────────────────────

    public function run(string $commandId): void
    {
        abort_unless(auth()->user()?->can('configurar sistema'), 403);

        $command = $this->findCommand($commandId);
        if (! $command) {
            return;
        }

        if ($command['sync']) {
            $this->runSync($commandId, $command);
        } else {
            $this->runAsync($commandId, $command);
        }
    }

    private function runSync(string $commandId, array $command): void
    {
        $runId = DB::table('command_runs')->insertGetId([
            'user_id' => Auth::id(),
            'command_id' => $commandId,
            'label' => $command['label'],
            'parameters' => json_encode(['command' => $command['command'], 'options' => $command['options']]),
            'status' => 'running',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->activeRunId = $runId;

        try {
            if ($command['command'] === 'cache:forget') {
                // cache:forget espera key como argumento posicional
                Cache::forget($command['options'][0] ?? '');
                $exitCode = 0;
                $output = 'Cache key removida: '.($command['options'][0] ?? '');
            } elseif ($command['command'] === 'cache:clear-sbar') {
                $sectorIds = DB::table('user_sector_preferences')
                    ->distinct()
                    ->pluck('sector_code')
                    ->map(fn ($v) => (int) $v)
                    ->filter()
                    ->unique();

                $prefixes = [
                    'sector_demographics_', 'sector_scales_', 'sector_clinical_',
                    'sector_multi_', 'sector_surgery_', 'sector_pending_fast_',
                    'sector_patients_sbar_',
                ];

                foreach ($sectorIds as $id) {
                    foreach ($prefixes as $prefix) {
                        Cache::forget($prefix.$id);
                    }
                }

                $exitCode = 0;
                $output = "Cache SBAR removido para {$sectorIds->count()} setor(es) ({$sectorIds->count()} × ".count($prefixes).' keys).';
            } elseif ($command['command'] === 'cache:clear-pending') {
                $sectorIds = DB::table('user_sector_preferences')
                    ->distinct()
                    ->pluck('sector_code')
                    ->map(fn ($v) => (int) $v)
                    ->filter()
                    ->unique();

                foreach ($sectorIds as $id) {
                    Cache::forget("sector_pending_fast_{$id}");
                }

                $exitCode = 0;
                $output = "Cache de pendências removido para {$sectorIds->count()} setor(es).";
            } else {
                $exitCode = Artisan::call($command['command'], $command['options']);
                $output = Artisan::output() ?: '(sem saída)';
            }

            $status = $exitCode === 0 ? 'done' : 'failed';
            DB::table('command_runs')->where('id', $runId)->update([
                'status' => $status,
                'output' => $output,
                'exit_code' => $exitCode,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->dispatch('show-toast', message: $command['label'].': concluído', type: $status === 'done' ? 'success' : 'error');
        } catch (\Throwable $e) {
            DB::table('command_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'output' => $e->getMessage(),
                'exit_code' => 1,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $this->dispatch('show-toast', message: $command['label'].': falhou', type: 'error');
        }
    }

    private function runAsync(string $commandId, array $command): void
    {
        $runId = DB::table('command_runs')->insertGetId([
            'user_id' => Auth::id(),
            'command_id' => $commandId,
            'label' => $command['label'],
            'parameters' => json_encode(['command' => $command['command'], 'options' => $command['options']]),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        RunArtisanCommand::dispatch($runId);

        $this->activeRunId = $runId;
    }

    // Chamado pelo wire:poll — só quando há run ativo pendente/rodando
    public function checkStatus(): void
    {
        if (! $this->activeRunId) {
            return;
        }

        $run = DB::table('command_runs')->find($this->activeRunId);

        if ($run && in_array($run->status, ['done', 'failed'])) {
            $msg = $run->status === 'done' ? $run->label.': concluído' : $run->label.': falhou';
            $this->dispatch('show-toast', message: $msg, type: $run->status === 'done' ? 'success' : 'error');
        }
    }

    // ── Dados para a view ─────────────────────────────────────────────────────

    public function render()
    {
        $recentRuns = DB::table('command_runs as cr')
            ->join('users as u', 'u.id', '=', 'cr.user_id')
            ->orderByDesc('cr.created_at')
            ->limit(20)
            ->get(['cr.*', 'u.name as user_name']);

        $activeRun = $this->activeRunId
            ? DB::table('command_runs')->find($this->activeRunId)
            : null;

        $scheduleItems = $this->buildScheduleList();
        $hasActiveRun = $activeRun && in_array($activeRun->status, ['pending', 'running']);

        return view('commands.command-center', compact('recentRuns', 'activeRun', 'scheduleItems', 'hasActiveRun'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findCommand(string $id): ?array
    {
        foreach ($this->catalog() as $group) {
            foreach ($group as $cmd) {
                if ($cmd['id'] === $id) {
                    return $cmd;
                }
            }
        }

        return null;
    }

    private function buildScheduleList(): array
    {
        return [
            ['label' => 'Limpar logs antigos', 'schedule' => 'Semanal', 'command' => 'handover:prune-log --days=180'],
            ['label' => 'Limpar jobs falhos', 'schedule' => 'Semanal', 'command' => 'queue:prune-failed --hours=168'],
            ['label' => 'Limpar cache SBAR', 'schedule' => 'A cada hora', 'command' => 'Cache de setores (sector_*) + pending events — síncrono'],
            ['label' => 'Pré-aquecer cache SBAR', 'schedule' => '06:45, 12:45, 18:45', 'command' => 'PatientDataLoader todos os setores — síncrono no scheduler'],
            ['label' => 'Arquivar mensagens', 'schedule' => 'Diário 03:00', 'command' => 'chat:cleanup --days=30'],
            ['label' => 'Reconstruir stats de arquivo', 'schedule' => 'Domingos 03:00', 'command' => 'cache:rebuild-archive-stats'],
        ];
    }
}
