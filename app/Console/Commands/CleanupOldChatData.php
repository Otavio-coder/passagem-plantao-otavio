<?php

namespace App\Console\Commands;

use App\Services\ShiftService;
use App\Support\ChatArchivePayload;
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
        $days = (int) $this->option('days');
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

        $usersById = DB::table('users')
            ->get()
            ->mapWithKeys(function ($user) {
                return [(int) $user->id => (array) $user];
            })
            ->toArray();

        $usernames = [];
        $usersByUsername = [];
        foreach ($usersById as $user) {
            if (! empty($user['username'])) {
                $usernames[(int) $user['id']] = $user['username'];
                $usersByUsername[$user['username']] = $user;
            }
        }

        $archived = 0;
        $deleted = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($nrs->count());
        $bar->start();

        foreach ($nrs as $nr) {
            try {
                // Só as mensagens antigas (antes do cutoff) para este atendimento
                $messages = DB::table('chat_messages')
                    ->where('nr_atendimento', $nr)
                    ->where('created_at', '<', $cutoff)
                    ->orderBy('created_at')
                    ->select(['id', 'user_id', 'content', 'created_at', 'sector_name'])
                    ->get();

                if ($messages->isEmpty()) {
                    $bar->advance();

                    continue;
                }

                // Coleta todos os setores distintos das mensagens sendo arquivadas
                $sectorNames = $messages
                    ->pluck('sector_name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                // Monta payload compacto (mesmo formato do import)
                $payload = $messages->map(fn ($m) => [
                    'ts' => Carbon::parse($m->created_at)->timestamp,
                    'u' => $usernames[$m->user_id] ?? 'user_'.$m->user_id,
                    'm' => $m->content,
                    't' => $this->inferTurno($m->created_at),
                ])->values()->toArray();

                $payloadUsers = [];
                foreach ($payload as $message) {
                    $username = $message['u'] ?? null;
                    if ($username && isset($usersByUsername[$username])) {
                        $payloadUsers[$username] = $usersByUsername[$username];
                    }
                }

                $compressed = ChatArchivePayload::encode($payload, $payloadUsers);

                $timestamps = array_column($payload, 'ts');
                $firstAt = date('Y-m-d H:i:s', min($timestamps));
                $lastAt = date('Y-m-d H:i:s', max($timestamps));
                $msgIds = $messages->pluck('id')->toArray();

                if (! $dryRun) {
                    DB::transaction(function () use ($nr, $compressed, $firstAt, $lastAt, $msgIds, $payload, $payloadUsers, $sectorNames, &$deleted) {
                        // Upsert no archive — mantém o registro existente se já houver,
                        // mesclando a contagem de mensagens ao invés de sobrescrever tudo
                        $existing = DB::table('chat_messages_archive')
                            ->where('nr_atendimento', $nr)
                            ->first();

                        if ($existing) {
                            // Mescla com payload já arquivado
                            $oldPayload = ChatArchivePayload::decode($existing->payload);
                            $mergedComp = ChatArchivePayload::merge(
                                $oldPayload['messages'],
                                $oldPayload['users'],
                                $payload,
                                $payloadUsers
                            );
                            $merged = ChatArchivePayload::decode($mergedComp);
                            $allTs = array_column($merged['messages'], 'ts');

                            // Mescla lista de setores com a já armazenada
                            $existingSectors = $existing->sector_name
                                ? json_decode($existing->sector_name, true) ?? []
                                : [];
                            $mergedSectors = array_values(array_unique(array_merge($existingSectors, $sectorNames)));

                            DB::table('chat_messages_archive')
                                ->where('nr_atendimento', $nr)
                                ->update([
                                    'message_count' => count($merged['messages']),
                                    'first_message_at' => date('Y-m-d H:i:s', min($allTs)),
                                    'last_message_at' => date('Y-m-d H:i:s', max($allTs)),
                                    'payload' => $mergedComp,
                                    'source' => 'cleanup_archive',
                                    'archived_at' => now(),
                                    'sector_name' => ! empty($mergedSectors) ? json_encode($mergedSectors) : null,
                                ]);
                        } else {
                            DB::table('chat_messages_archive')->insert([
                                'nr_atendimento' => $nr,
                                'message_count' => count($payload),
                                'first_message_at' => $firstAt,
                                'last_message_at' => $lastAt,
                                'payload' => $compressed,
                                'source' => 'cleanup_archive',
                                'archived_at' => now(),
                                'sector_name' => ! empty($sectorNames) ? json_encode($sectorNames) : null,
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
                    'error' => $e->getMessage(),
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
        $this->line('  chat_messages:         '.DB::table('chat_messages')->count());
        $this->line('  chat_messages_archive: '.DB::table('chat_messages_archive')->count());

        Log::info('[ChatCleanup] Arquivamento concluído', [
            'archived_attendances' => $archived,
            'deleted_messages' => $deleted,
            'errors' => $errors,
            'cutoff' => $cutoff->toDateTimeString(),
        ]);

        return $errors > 0 ? 1 : 0;
    }

    private function inferTurno(string $datetime): string
    {
        $dt = Carbon::parse($datetime);

        return match (ShiftService::shiftFromMinutes($dt->hour * 60 + $dt->minute)) {
            'M' => 'manha',
            'T' => 'tarde',
            default => 'noite',
        };
    }
}
