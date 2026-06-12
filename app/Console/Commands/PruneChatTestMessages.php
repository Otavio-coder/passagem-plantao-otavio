<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Identifica e remove mensagens de teste/lixo do chat_messages.
 *
 * Uso:
 *   php artisan chat:prune-test --dry-run
 *   php artisan chat:prune-test --confirm
 *   php artisan chat:prune-test --confirm --before=2025-01-01
 */
class PruneChatTestMessages extends Command
{
    protected $signature = 'chat:prune-test
        {--dry-run    : Apenas lista, não apaga}
        {--confirm    : Executa a exclusão}
        {--before=    : Limitar a mensagens anteriores a esta data (YYYY-MM-DD)}
        {--limit=500  : Número máximo de mensagens a processar por execução}';

    protected $description = 'Remove mensagens de teste/lixo do chat_messages com base em padrões de conteúdo';

    /**
     * Padrões conservadores de conteúdo de teste (REGEXP MySQL, aplicados em LOWER(content)).
     * Propositalmente restritivos para não tocar anotações clínicas reais (ATB, BCP, SVD…).
     */
    private const CONTENT_PATTERNS = [
        '^(teste?s?|testando|testeee+|test)$',
        '^(t{2,}|tt+)$',
        '^(asdf|qwer|zxcv|aaa{2,}|bbb{2,}|kkk{2,}|hhh{2,}|lll{2,})[a-z0-9]*$',
        '^(testando|testeee+|testttt+)',
        '^(teste|test) (1{1,3}|2|3|123|de mensagem|passagem|de plantao|envio|sistema|msg)',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $confirm = $this->option('confirm');
        $before = $this->option('before');
        $limit = (int) $this->option('limit');

        if (! $dryRun && ! $confirm) {
            $this->error('Passe --dry-run para visualizar ou --confirm para executar.');

            return self::FAILURE;
        }

        $query = DB::table('chat_messages');

        if ($before) {
            $query->where('created_at', '<', $before);
        }

        $pattern = implode('|', self::CONTENT_PATTERNS);
        $query->whereRaw('LOWER(content) REGEXP ?', [$pattern]);

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nenhuma mensagem de teste encontrada.');

            return self::SUCCESS;
        }

        $rows = $query->orderBy('id')->limit($limit)->get(['id', 'content', 'nr_atendimento', 'created_at', 'user_id']);

        $this->info("Encontradas {$total} mensagens suspeitas (exibindo até {$limit}):");
        $this->table(
            ['ID', 'Conteúdo', 'Atendimento', 'Usuário', 'Data'],
            $rows->map(fn ($r) => [
                $r->id,
                mb_strimwidth($r->content ?? '', 0, 50, '…'),
                $r->nr_atendimento,
                $r->user_id,
                $r->created_at,
            ])->all(),
        );

        if ($dryRun) {
            $this->warn('Modo --dry-run: nenhuma mensagem foi apagada. Use --confirm para executar.');

            return self::SUCCESS;
        }

        $ids = $rows->pluck('id')->all();
        $deleted = DB::table('chat_messages')->whereIn('id', $ids)->delete();

        $this->info("Removidas {$deleted} mensagens de teste.");

        if ($total > $limit) {
            $remaining = $total - $deleted;
            $this->warn("Restam {$remaining} mensagens. Rode novamente para continuar.");
        }

        return self::SUCCESS;
    }
}
