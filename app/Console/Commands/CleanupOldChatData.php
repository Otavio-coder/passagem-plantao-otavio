<?php

namespace App\Console\Commands;

use App\Models\System\Chat\ChatAuditoria;
use App\Models\System\Chat\ChatMensagem;
use App\Models\System\Chat\ChatSessao;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupOldChatData extends Command
{
    protected $signature = 'chat:cleanup
                            {--days=90 : Número de dias para manter dados}
                            {--dry-run : Apenas simula sem deletar}
                            {--keep-audits=365 : Dias para manter auditorias}';

    protected $description = 'Limpa dados antigos de chat para manter o sistema otimizado';

    public function handle()
    {
        $days = (int) $this->option('days');
        $keepAudits = (int) $this->option('keep-audits');
        $dryRun = $this->option('dry-run');

        $cutoffDate = Carbon::now()->subDays($days)->toDateString();
        $auditCutoffDate = Carbon::now()->subDays($keepAudits)->toDateString();

        $this->info('=== Limpeza de Dados Antigos do Chat ===');
        $this->info("Data de corte para sessões/mensagens: {$cutoffDate}");
        $this->info("Data de corte para auditorias: {$auditCutoffDate}");

        if ($dryRun) {
            $this->warn('Modo DRY-RUN - Nenhum dado será deletado');
        }

        // 1. Contar sessões antigas
        $oldSessionsCount = ChatSessao::where('data_sessao', '<', $cutoffDate)->count();
        $this->info("Sessões antigas encontradas: {$oldSessionsCount}");

        // 2. Contar mensagens soft-deleted antigas
        $softDeletedCount = ChatMensagem::where('is_deleted', true)
            ->where('updated_at', '<', $cutoffDate)
            ->count();
        $this->info("Mensagens soft-deleted antigas: {$softDeletedCount}");

        // 3. Contar auditorias antigas
        $oldAuditsCount = ChatAuditoria::where('dt_acao', '<', $auditCutoffDate)->count();
        $this->info("Auditorias antigas: {$oldAuditsCount}");

        // 4. Contar sessões vazias (sem mensagens)
        $emptySessionsCount = ChatSessao::where('total_mensagens', 0)
            ->where('data_sessao', '<', Carbon::now()->subDays(7)->toDateString())
            ->count();
        $this->info("Sessões vazias (>7 dias): {$emptySessionsCount}");

        if (!$dryRun) {
            $this->info('');
            $this->info('Iniciando limpeza...');

            DB::beginTransaction();

            try {
                // 1. Deletar auditorias de sessões antigas
                $deletedAudits = ChatAuditoria::where('dt_acao', '<', $auditCutoffDate)->delete();
                $this->line("  - Auditorias deletadas: {$deletedAudits}");

                // 2. Deletar mensagens soft-deleted antigas permanentemente
                $deletedSoftDeleted = ChatMensagem::where('is_deleted', true)
                    ->where('updated_at', '<', $cutoffDate)
                    ->forceDelete();
                $this->line("  - Mensagens soft-deleted removidas permanentemente: {$deletedSoftDeleted}");

                // 3. Deletar sessões vazias antigas
                $deletedEmptySessions = ChatSessao::where('total_mensagens', 0)
                    ->where('data_sessao', '<', Carbon::now()->subDays(7)->toDateString())
                    ->delete();
                $this->line("  - Sessões vazias removidas: {$deletedEmptySessions}");

                // 4. Deletar sessões muito antigas (mensagens são deletadas em cascata)
                $deletedOldSessions = ChatSessao::where('data_sessao', '<', $cutoffDate)->delete();
                $this->line("  - Sessões antigas removidas: {$deletedOldSessions}");

                DB::commit();

                $this->info('');
                $this->info('Limpeza concluída com sucesso!');

                Log::info('[ChatCleanup] Limpeza concluída', [
                    'audits_deleted' => $deletedAudits,
                    'soft_deleted_removed' => $deletedSoftDeleted,
                    'empty_sessions_removed' => $deletedEmptySessions,
                    'old_sessions_removed' => $deletedOldSessions,
                    'cutoff_date' => $cutoffDate,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error('Erro durante a limpeza: ' . $e->getMessage());
                Log::error('[ChatCleanup] Erro durante limpeza', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return 1;
            }
        } else {
            $this->info('');
            $this->info('Simulação concluída. Use sem --dry-run para executar a limpeza.');
        }

        // Otimização: Sugestão de índices
        $this->info('');
        $this->info('=== Verificação de Performance ===');
        $this->checkPerformanceIssues();

        return 0;
    }

    private function checkPerformanceIssues()
    {
        // Verificar tabelas grandes
        $sessionsCount = ChatSessao::count();
        $messagesCount = ChatMensagem::count();
        $auditsCount = ChatAuditoria::count();

        $this->line("Total de sessões: {$sessionsCount}");
        $this->line("Total de mensagens: {$messagesCount}");
        $this->line("Total de auditorias: {$auditsCount}");

        if ($messagesCount > 100000) {
            $this->warn('AVISO: Tabela de mensagens com muitos registros. Considere executar limpeza.');
        }

        if ($auditsCount > 500000) {
            $this->warn('AVISO: Tabela de auditorias com muitos registros. Considere reduzir --keep-audits.');
        }

        // Verificar sessões não encerradas antigas
        $oldOpenSessions = ChatSessao::where('encerrada', false)
            ->where('data_sessao', '<', Carbon::now()->subDays(2)->toDateString())
            ->count();

        if ($oldOpenSessions > 0) {
            $this->warn("AVISO: {$oldOpenSessions} sessões antigas não encerradas. Execute 'php artisan chat:close-sessions'");
        }
    }
}
