<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CloseExpiredChatSessions extends Command
{
    protected $signature = 'chat:close-sessions {--dry-run : Apenas simula}';

    protected $description = '[DEPRECATED] O sistema de sessões foi removido. Este comando é um no-op.';

    public function handle()
    {
        $this->warn('Este comando foi descontinuado. O chat não usa mais sessões por turno.');
        $this->info('As mensagens são agrupadas por turno dinamicamente com base em created_at.');
        return 0;
    }
}
