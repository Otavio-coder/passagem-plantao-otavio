<?php

namespace App\Console\Commands;

use App\Models\System\Chat\ChatSessao;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseExpiredChatSessions extends Command
{
    protected $signature = 'chat:close-sessions {--dry-run : Apenas simula sem fechar}';

    protected $description = 'Encerra automaticamente as sessões de chat dos turnos anteriores';

    /**
     * Turnos e seus horários de término
     * - Manhã: 07:00-13:00 -> encerra às 13:00
     * - Tarde: 13:00-19:00 -> encerra às 19:00
     * - Noite: 19:00-07:00 -> encerra às 07:00 do dia seguinte
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $now = now();
        $hour = $now->hour;

        $this->info('Verificando sessões para encerrar...');
        $this->info('Horário atual: ' . $now->format('Y-m-d H:i:s'));

        $sessionsToClose = collect();

        // Determinar quais turnos devem estar encerrados
        if ($hour >= 7 && $hour < 13) {
            // Estamos na manhã - encerrar noite do dia anterior
            $sessionsToClose = $sessionsToClose->merge(
                $this->getSessionsToClose('noite', $now->copy()->subDay()->toDateString())
            );
        } elseif ($hour >= 13 && $hour < 19) {
            // Estamos na tarde - encerrar manhã de hoje e noite de ontem
            $sessionsToClose = $sessionsToClose->merge(
                $this->getSessionsToClose('manha', $now->toDateString())
            );
            $sessionsToClose = $sessionsToClose->merge(
                $this->getSessionsToClose('noite', $now->copy()->subDay()->toDateString())
            );
        } else {
            // Estamos na noite - encerrar tarde e manhã de hoje, e noite de ontem
            $sessionsToClose = $sessionsToClose->merge(
                $this->getSessionsToClose('tarde', $now->toDateString())
            );
            $sessionsToClose = $sessionsToClose->merge(
                $this->getSessionsToClose('manha', $now->toDateString())
            );
            // Noite de antes de ontem também (se ainda estiver aberta)
            $sessionsToClose = $sessionsToClose->merge(
                $this->getSessionsToClose('noite', $now->copy()->subDays(2)->toDateString())
            );
        }

        // Também encerrar sessões muito antigas (mais de 2 dias) independente do turno
        $oldSessions = ChatSessao::where('encerrada', false)
            ->where('data_sessao', '<', $now->copy()->subDays(2)->toDateString())
            ->get();

        $sessionsToClose = $sessionsToClose->merge($oldSessions);

        $count = $sessionsToClose->count();

        if ($count === 0) {
            $this->info('Nenhuma sessão para encerrar.');
            return 0;
        }

        $this->info("Encontradas {$count} sessões para encerrar.");

        if ($dryRun) {
            $this->warn('Modo DRY-RUN - Nenhuma alteração será feita.');
            foreach ($sessionsToClose as $session) {
                $this->line("  - Sessão #{$session->id}: {$session->data_sessao} / {$session->turno_id} (At: {$session->nr_atendimento})");
            }
            return 0;
        }

        $closed = 0;
        foreach ($sessionsToClose as $session) {
            try {
                $session->update([
                    'encerrada' => true,
                    'fim' => $this->getShiftEndTime($session->turno_id, $session->data_sessao),
                ]);
                $closed++;
                $this->line("  Encerrada sessão #{$session->id}");
            } catch (\Exception $e) {
                $this->error("  Erro ao encerrar sessão #{$session->id}: " . $e->getMessage());
                Log::error('[CloseExpiredChatSessions] Erro ao encerrar sessão', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Total de sessões encerradas: {$closed}");

        Log::info('[CloseExpiredChatSessions] Sessões encerradas', [
            'total_found' => $count,
            'total_closed' => $closed,
        ]);

        return 0;
    }

    private function getSessionsToClose(string $turnoId, string $date)
    {
        return ChatSessao::where('encerrada', false)
            ->where('turno_id', $turnoId)
            ->where('data_sessao', $date)
            ->get();
    }

    private function getShiftEndTime(string $turnoId, string $date): Carbon
    {
        return match ($turnoId) {
            'manha' => Carbon::parse($date)->setTime(13, 0, 0),
            'tarde' => Carbon::parse($date)->setTime(19, 0, 0),
            'noite' => Carbon::parse($date)->addDay()->setTime(7, 0, 0),
            default => Carbon::parse($date)->endOfDay(),
        };
    }
}
