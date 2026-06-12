<?php

namespace App\Console\Commands;

use App\Services\ChatAnalyticsService;
use App\Support\ChatArchivePayload;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Popula chat_analytics_daily com dados históricos.
 *
 * Fontes:
 *  1. chat_messages          — dados recentes ainda não arquivados (padrão).
 *  2. chat_messages_archive  — blobs históricos; requer --include-archive (lento, descomprime cada blob).
 *
 * Uso em produção:
 *   php artisan chat:backfill-analytics --dry-run
 *   php artisan chat:backfill-analytics
 *   php artisan chat:backfill-analytics --include-archive
 *   php artisan chat:backfill-analytics --from=2026-01-01 --to=2026-06-01
 */
class BackfillChatAnalytics extends Command
{
    protected $signature = 'chat:backfill-analytics
                            {--from=        : Data inicial Y-m-d (padrão: mensagem mais antiga em chat_messages)}
                            {--to=          : Data final Y-m-d (padrão: hoje)}
                            {--chunk=1000   : Mensagens por lote ao processar chat_messages}
                            {--include-archive : Também processa blobs de chat_messages_archive (lento)}
                            {--dry-run      : Mostra estatísticas sem gravar}';

    protected $description = 'Popula chat_analytics_daily com dados históricos de chat_messages (e opcionalmente do archive)';

    public function handle(ChatAnalyticsService $analytics): int
    {
        $dryRun = $this->option('dry-run');
        $chunk = (int) ($this->option('chunk') ?: 1000);

        $fromOpt = $this->option('from');
        $toOpt = $this->option('to');

        $from = $fromOpt
            ? Carbon::parse($fromOpt)->startOfDay()
            : Carbon::parse(DB::table('chat_messages')->min('created_at') ?? now())->startOfDay();

        $to = $toOpt
            ? Carbon::parse($toOpt)->endOfDay()
            : now()->endOfDay();

        $this->info('=== Backfill de chat_analytics_daily ===');
        $this->info("Período  : {$from->toDateString()} → {$to->toDateString()}");
        $this->info("Chunk    : {$chunk}");
        $this->info('Archive  : '.($this->option('include-archive') ? 'sim' : 'não'));
        $this->newLine();

        if ($dryRun) {
            $this->warn('Modo dry-run — nenhum dado será gravado.');
        }

        $totalMessages = 0;
        $totalRows = 0;

        // ── chat_messages_archive ────────────────────────────────────────────
        // Backfill popula analytics APENAS com dados do archive (mensagens antigas).
        // Mensagens recentes (chat_messages) são consultadas diretamente nos gráficos.
        // chat:cleanup registra analytics automaticamente ao arquivar no futuro.
        if ($this->option('include-archive')) {
            $this->newLine();
            $this->info('Processando chat_messages_archive...');
            $this->warn('  Atenção: descomprime cada blob individualmente — pode ser lento em produção.');

            $archiveCount = DB::table('chat_messages_archive')
                ->when($fromOpt, fn ($q) => $q->where('last_message_at', '>=', $from))
                ->when($toOpt, fn ($q) => $q->where('first_message_at', '<=', $to))
                ->count();

            $this->info("  {$archiveCount} registros de archive");

            $bar2 = $this->output->createProgressBar($archiveCount ?: 1);
            $bar2->start();

            DB::table('chat_messages_archive')
                ->when($fromOpt, fn ($q) => $q->where('last_message_at', '>=', $from))
                ->when($toOpt, fn ($q) => $q->where('first_message_at', '<=', $to))
                ->select(['id', 'payload', 'sector_name', 'message_count'])
                ->orderBy('first_message_at')
                ->chunk(100, function ($archiveRows) use ($analytics, $dryRun, &$totalMessages, &$totalRows, $bar2) {
                    foreach ($archiveRows as $row) {
                        $decoded = ChatArchivePayload::decode($row->payload);
                        $messages = $decoded['messages'] ?? [];

                        if (empty($messages)) {
                            $bar2->advance();

                            continue;
                        }

                        // Pega o primeiro setor do JSON array de sector_name
                        $sectorNames = $row->sector_name ? json_decode($row->sector_name, true) : [];
                        $primarySector = is_array($sectorNames) ? ($sectorNames[0] ?? null) : null;

                        if (! $dryRun) {
                            $analytics->recordFromArchive($messages, $primarySector);
                        }

                        $totalMessages += count($messages);
                        $totalRows++;
                        $bar2->advance();
                    }
                });

            $bar2->finish();
            $this->newLine();
        }

        if (! $this->option('include-archive')) {
            $this->warn('Nenhuma fonte processada. Use --include-archive para popular dados históricos do archive.');
        }

        $this->newLine();
        $this->info('Concluído!');
        $this->info("  Mensagens processadas : {$totalMessages}");

        if (! $dryRun) {
            $rowsInTable = DB::table('chat_analytics_daily')->count();
            $this->info("  Linhas em analytics   : {$rowsInTable}");
        }

        return 0;
    }
}
