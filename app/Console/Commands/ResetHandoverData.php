<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetHandoverData extends Command
{
    protected $signature = 'handover:reset
                            {--chat : Limpa também mensagens de chat (chat_messages, reações e pins)}
                            {--force : Executa sem confirmação}';

    protected $description = 'Remove todas as passagens de plantão registradas. Use --chat para limpar também o chat.';

    public function handle(): int
    {
        $withChat = $this->option('chat');

        $this->line('');
        $this->warn('ATENÇÃO: Esta operação é irreversível!');
        $this->line('');
        $this->line('Serão apagados:');
        $this->line('  • Todas as passagens (shift_handovers)');
        if ($withChat) {
            $this->line('  • Todas as mensagens de chat (chat_messages)');
            $this->line('  • Todas as reações (chat_reactions)');
            $this->line('  • Todos os pins (chat_message_pins)');
            $this->line('  • Todo o histórico arquivado (chat_messages_archive)');
        }
        $this->line('');

        if (! $this->option('force') && ! $this->confirm('Confirmar remoção?', false)) {
            $this->info('Operação cancelada.');

            return self::SUCCESS;
        }

        $this->info('Removendo dados...');

        DB::table('shift_handovers')->delete();
        $this->line('  ✓ shift_handovers limpo');

        if ($withChat) {
            DB::table('chat_message_pins')->delete();
            DB::table('chat_reactions')->delete();
            DB::table('chat_messages')->delete();
            DB::table('chat_messages_archive')->delete();
            $this->line('  ✓ chat_message_pins limpo');
            $this->line('  ✓ chat_reactions limpo');
            $this->line('  ✓ chat_messages limpo');
            $this->line('  ✓ chat_messages_archive limpo');
        }

        $this->line('');
        $this->info('Concluído. Use "php artisan cache:clear" se necessário.');

        return self::SUCCESS;
    }
}
