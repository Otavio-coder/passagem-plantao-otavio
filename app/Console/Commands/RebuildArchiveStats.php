<?php

namespace App\Console\Commands;

use App\Services\ShiftService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildArchiveStats extends Command
{
    protected $signature = 'cache:rebuild-archive-stats
        {--force : Reprocessa todos os payloads, ignorando stats_rebuilt_at}';

    protected $description = 'Extrai sessões e contagens do chat_messages_archive (processa só delta por padrão)';

    public function handle(): int
    {
        ini_set('memory_limit', '256M');

        $force = (bool) $this->option('force');

        $users = DB::table('users')
            ->whereNotNull('username')
            ->get(['id', 'username', 'name'])
            ->keyBy(fn ($u) => mb_strtolower($u->username));

        // Só processa: nunca processados OU com last_message_at mais recente que o último rebuild
        $pendingCount = DB::table('chat_messages_archive')
            ->when(! $force, fn ($q) => $q->where(fn ($q) => $q->whereNull('stats_rebuilt_at')
                ->orWhereColumn('last_message_at', '>', 'stats_rebuilt_at')
            ))
            ->count();

        $totalCount = DB::table('chat_messages_archive')->count();
        $skipped = $totalCount - $pendingCount;

        if ($pendingCount === 0) {
            $this->info("Nada a processar — {$totalCount} registros já atualizados.");

            return self::SUCCESS;
        }

        $this->info("Processando {$pendingCount} de {$totalCount} registros ({$skipped} sem alteração, ignorados)...");

        // Acumuladores apenas para os registros pendentes
        $msgCountsDelta = [];
        $sessionsDelta = [];
        $processedIds = [];

        DB::table('chat_messages_archive')
            ->select(['id', 'payload', 'sector_name', 'last_message_at'])
            ->when(! $force, fn ($q) => $q->where(fn ($q) => $q->whereNull('stats_rebuilt_at')
                ->orWhereColumn('last_message_at', '>', 'stats_rebuilt_at')
            ))
            ->orderBy('id')
            ->chunk(50, function ($rows) use ($users, &$msgCountsDelta, &$sessionsDelta, &$processedIds) {
                foreach ($rows as $row) {
                    try {
                        $decoded = json_decode(gzuncompress(base64_decode($row->payload)), true) ?? [];
                        $messages = isset($decoded['messages']) ? $decoded['messages'] : $decoded;

                        $sectorRaw = $row->sector_name;
                        $sectorName = null;
                        if ($sectorRaw) {
                            $arr = json_decode($sectorRaw, true);
                            $sectorName = is_array($arr) ? ($arr[0] ?? null) : $sectorRaw;
                        }

                        foreach ($messages as $msg) {
                            $username = mb_strtolower(trim($msg['u'] ?? ''));
                            $ts = (int) ($msg['ts'] ?? 0);

                            if (! $username || ! $ts) {
                                continue;
                            }

                            $msgCountsDelta[$username] = ($msgCountsDelta[$username] ?? 0) + 1;

                            $user = $users->get($username);
                            if (! $user) {
                                continue;
                            }

                            $totalMin = (int) date('H', $ts) * 60 + (int) date('i', $ts);
                            $shift = ShiftService::shiftFromMinutes($totalMin);
                            $shiftDate = ShiftService::shiftDateFromTimestamp($ts);
                            $key = $shift.'_'.$shiftDate;

                            if (! isset($sessionsDelta[$user->id][$key])) {
                                $sessionsDelta[$user->id][$key] = [
                                    'user_id' => $user->id,
                                    'user_name' => $user->name,
                                    'shift' => $shift,
                                    'shift_date' => $shiftDate,
                                    'sector_name' => $sectorName,
                                    'message_count' => 0,
                                    'started_at' => $ts,
                                ];
                            }

                            $sessionsDelta[$user->id][$key]['message_count']++;
                            if ($ts < $sessionsDelta[$user->id][$key]['started_at']) {
                                $sessionsDelta[$user->id][$key]['started_at'] = $ts;
                            }
                            if ($sectorName && ! $sessionsDelta[$user->id][$key]['sector_name']) {
                                $sessionsDelta[$user->id][$key]['sector_name'] = $sectorName;
                            }
                        }

                        $processedIds[] = $row->id;
                    } catch (\Throwable) {
                        // skip payload corrompido
                    }
                }
            });

        // ── Atualiza archived_message_count (recalcula total do zero pro usuário) ─
        // Para usuários no delta, soma o total completo do archive
        $affectedUsernames = array_keys($msgCountsDelta);
        $updatedUsers = 0;
        foreach ($affectedUsernames as $username) {
            $user = $users->get($username)
                ?? $users->first(fn ($u) => strcasecmp($u->username, $username) === 0);

            if (! $user) {
                continue;
            }

            // Recontagem completa do archive para este usuário
            $fullCount = 0;
            DB::table('chat_messages_archive')->select(['payload'])->orderBy('id')->chunk(100, function ($rows) use ($username, &$fullCount) {
                foreach ($rows as $row) {
                    try {
                        $decoded = json_decode(gzuncompress(base64_decode($row->payload)), true) ?? [];
                        $msgs = isset($decoded['messages']) ? $decoded['messages'] : $decoded;
                        foreach ($msgs as $m) {
                            if (mb_strtolower($m['u'] ?? '') === $username && trim($m['m'] ?? '') !== '') {
                                $fullCount++;
                            }
                        }
                    } catch (\Throwable) {
                    }
                }
            });

            DB::table('users')->where('id', $user->id)->update(['archived_message_count' => $fullCount]);
            $updatedUsers++;
        }
        $this->line("  ✓ archived_message_count: {$updatedUsers} plantonistas recalculados.");

        // ── Upsert sessões (não apaga — merge com o que existe) ───────────────
        $upsertBatch = [];
        foreach ($sessionsDelta as $userSessions) {
            foreach ($userSessions as $s) {
                $upsertBatch[] = [
                    'user_id' => $s['user_id'],
                    'user_name' => $s['user_name'],
                    'shift' => $s['shift'],
                    'shift_date' => $s['shift_date'],
                    'sector_name' => $s['sector_name'],
                    'message_count' => $s['message_count'],
                    'beds_visited' => 0,
                    'started_at' => date('Y-m-d H:i:s', $s['started_at']),
                ];
            }
        }

        if (! empty($upsertBatch)) {
            DB::transaction(function () use ($upsertBatch) {
                foreach (array_chunk($upsertBatch, 200) as $chunk) {
                    DB::table('nurse_archive_sessions')->upsert(
                        $chunk,
                        ['user_id', 'shift', 'shift_date'],
                        ['message_count', 'started_at', 'sector_name']
                    );
                }
            });
        }
        $this->line('  ✓ nurse_archive_sessions: '.count($upsertBatch).' sessões inseridas/atualizadas.');

        // ── Marca registros processados ───────────────────────────────────────
        if (! empty($processedIds)) {
            foreach (array_chunk($processedIds, 500) as $chunk) {
                DB::table('chat_messages_archive')
                    ->whereIn('id', $chunk)
                    ->update(['stats_rebuilt_at' => now()]);
            }
            $this->line('  ✓ '.count($processedIds).' registros marcados como processados.');
        }

        return self::SUCCESS;
    }
}
