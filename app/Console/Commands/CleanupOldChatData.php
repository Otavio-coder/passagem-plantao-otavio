<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Move mensagens antigas de chat_messages → chat_messages_archive e remove-as.
 * Mensagens com menos de N dias (padrão: 30) são mantidas em chat_messages.
 *
 * Uso:
 *   php artisan chat:cleanup --dry-run
 *   php artisan chat:cleanup --days=30
 */
class CleanupOldChatData extends Command
{
    protected $signature = 'chat:cleanup
                            {--days=30  : Número de dias para manter mensagens em chat_messages}
                            {--dry-run  : Apenas simula sem mover nem deletar}';

    protected $description = 'Arquiva mensagens antigas de chat_messages em chat_messages_archive';

    public function handle(): int
    {
        $days   = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $this->info('=== Arquivamento de Mensagens Antigas do Chat ===');
        $this->info("Cutoff : {$cutoff->toDateTimeString()} (mais de {$days} dias)");
        $this->newLine();

        if ($dryRun) {
            $this->warn('Modo dry-run — nenhum dado será alterado.');
        }

        // ── Descobre nr_atendimentos com mensagens antigas ────────────────────
        $nrs = DB::table('chat_messages')
            ->where('created_at', '<', $cutoff)
            ->distinct()
            ->pluck('nr_atendimento');

        if ($nrs->isEmpty()) {
            $this->info('Nenhuma mensagem antiga encontrada.');
            return 0;
        }

        $this->info("Atendimentos com mensagens a arquivar: {$nrs->count()}");
        $this->newLine();

        // Mapa username para identificar autores no payload
        $usernames = DB::table('users')->pluck('username', 'id')->toArray();

        $archived = 0;
        $deleted  = 0;
        $errors   = 0;

        $bar = $this->output->createProgressBar($nrs->count());
        $bar->start();

        foreach ($nrs as $nr) {
            try {
                // Só as mensagens antigas (antes do cutoff) para este atendimento
                $messages = DB::table('chat_messages')
                    ->where('nr_atendimento', $nr)
                    ->where('created_at', '<', $cutoff)
                    ->orderBy('created_at')
                    ->select(['id', 'user_id', 'content', 'created_at'])
                    ->get();

                if ($messages->isEmpty()) {
                    $bar->advance();
                    continue;
                }

                // Monta payload compacto (mesmo formato do import)
                $payload = $messages->map(fn($m) => [
                    'ts' => Carbon::parse($m->created_at)->timestamp,
                    'u'  => $usernames[$m->user_id] ?? 'user_' . $m->user_id,
                    'm'  => $m->content,
                    't'  => $this->inferTurno($m->created_at),
                ])->values()->toArray();

                $json       = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $compressed = base64_encode(gzcompress($json, 6));

                $timestamps  = array_column($payload, 'ts');
                $firstAt     = date('Y-m-d H:i:s', min($timestamps));
                $lastAt      = date('Y-m-d H:i:s', max($timestamps));
                $msgIds      = $messages->pluck('id')->toArray();

                if (!$dryRun) {
                    DB::transaction(function () use ($nr, $compressed, $firstAt, $lastAt, $msgIds, $payload, &$deleted) {
                        // Upsert no archive — mantém o registro existente se já houver,
                        // mesclando a contagem de mensagens ao invés de sobrescrever tudo
                        $existing = DB::table('chat_messages_archive')
                            ->where('nr_atendimento', $nr)
                            ->first();

                        if ($existing) {
                            // Mescla com payload já arquivado
                            $oldPayload  = json_decode(gzuncompress(base64_decode($existing->payload)), true) ?? [];
                            $merged      = array_merge($oldPayload, $payload);
                            usort($merged, fn($a, $b) => $a['ts'] <=> $b['ts']);
                            $merged      = array_values(array_unique($merged, SORT_REGULAR));

                            $mergedJson  = json_encode($merged, JSON_UNESCAPED_UNICODE);
                            $mergedComp  = base64_encode(gzcompress($mergedJson, 6));
                            $allTs       = array_column($merged, 'ts');

                            DB::table('chat_messages_archive')
                                ->where('nr_atendimento', $nr)
                                ->update([
                                    'message_count'    => count($merged),
                                    'first_message_at' => date('Y-m-d H:i:s', min($allTs)),
                                    'last_message_at'  => date('Y-m-d H:i:s', max($allTs)),
                                    'payload'          => $mergedComp,
                                    'source'           => 'cleanup_archive',
                                    'archived_at'      => now(),
                                ]);
                        } else {
                            DB::table('chat_messages_archive')->insert([
                                'nr_atendimento'   => $nr,
                                'message_count'    => count($payload),
                                'first_message_at' => $firstAt,
                                'last_message_at'  => $lastAt,
                                'payload'          => $compressed,
                                'source'           => 'cleanup_archive',
                                'archived_at'      => now(),
                            ]);
                        }

                        // Remove as mensagens antigas (reações e pins em cascade)
                        $deleted += DB::table('chat_messages')
                            ->whereIn('id', $msgIds)
                            ->delete();
                    });

                    $archived++;
                } else {
                    $this->line("  [dry-run] nr={$nr} → {$messages->count()} msgs seriam arquivadas");
                }

            } catch (\Exception $e) {
                $errors++;
                Log::error('[ChatCleanup] Erro ao arquivar', [
                    'nr_atendimento' => $nr,
                    'error'          => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Atendimentos arquivados : {$archived}");
        $this->info("Mensagens removidas     : {$deleted}");
        $this->info("Erros                   : {$errors}");
        $this->newLine();
        $this->line('Totais no banco:');
        $this->line('  chat_messages:         ' . DB::table('chat_messages')->count());
        $this->line('  chat_messages_archive: ' . DB::table('chat_messages_archive')->count());

        Log::info('[ChatCleanup] Arquivamento concluído', [
            'archived_attendances' => $archived,
            'deleted_messages'     => $deleted,
            'errors'               => $errors,
            'cutoff'               => $cutoff->toDateTimeString(),
        ]);

        return $errors > 0 ? 1 : 0;
    }

    private function inferTurno(string $datetime): string
    {
        $hour = (int) Carbon::parse($datetime)->format('H');
        if ($hour >= 7 && $hour < 13) return 'manha';
        if ($hour >= 13 && $hour < 19) return 'tarde';
        return 'noite';
    }
}
