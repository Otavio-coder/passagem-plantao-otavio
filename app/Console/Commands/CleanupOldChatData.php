<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupOldChatData extends Command
{
    protected $signature = 'chat:cleanup
                            {--days=90 : Número de dias para manter mensagens}
                            {--dry-run : Apenas simula sem deletar}';

    protected $description = 'Limpa mensagens antigas do chat para manter o sistema otimizado';

    public function handle()
    {
        $days   = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $cutoff = Carbon::now()->subDays($days);

        $this->info('=== Limpeza de Dados Antigos do Chat ===');
        $this->info("Cutoff: {$cutoff->toDateTimeString()} (mensagens mais antigas que {$days} dias)");

        if ($dryRun) {
            $this->warn('Modo DRY-RUN — nenhum dado será deletado');
        }

        $oldMessagesCount = DB::table('chat_messages')
            ->where('created_at', '<', $cutoff)
            ->count();

        $this->info("Mensagens antigas encontradas: {$oldMessagesCount}");

        if (!$dryRun && $oldMessagesCount > 0) {
            DB::beginTransaction();
            try {
                // chat_reactions and chat_message_pins are deleted in cascade
                $deleted = DB::table('chat_messages')
                    ->where('created_at', '<', $cutoff)
                    ->delete();

                DB::commit();
                $this->info("Mensagens removidas: {$deleted}");

                Log::info('[ChatCleanup] Limpeza concluída', [
                    'deleted_messages' => $deleted,
                    'cutoff'           => $cutoff->toDateTimeString(),
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error('Erro durante a limpeza: ' . $e->getMessage());
                Log::error('[ChatCleanup] Erro', ['error' => $e->getMessage()]);
                return 1;
            }
        } elseif ($dryRun) {
            $this->info('Simulação concluída. Use sem --dry-run para executar.');
        }

        $this->info('');
        $this->info('=== Estatísticas ===');
        $this->line('Total de mensagens: ' . DB::table('chat_messages')->count());
        $this->line('Total de reações:   ' . DB::table('chat_reactions')->count());
        $this->line('Total de pins:      ' . DB::table('chat_message_pins')->count());

        return 0;
    }
}
