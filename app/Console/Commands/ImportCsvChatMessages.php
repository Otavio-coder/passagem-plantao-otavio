<?php

namespace App\Console\Commands;

use App\Support\ChatArchivePayload;
use App\Support\ChatImportUserPayload;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa mensagens de chat a partir dos CSVs exportados de produção.
 * Sempre sobrepõe o que já existe no banco (overwrite total por nr_atendimento).
 *
 * Uso:
 *   php artisan chat:import-csv                       # dry-run, detecta CSVs em storage/chat-imports/
 *   php artisan chat:import-csv --execute             # executa com auto-detecção
 *   php artisan chat:import-csv --date=1303 --execute # usa arquivos chat-mensagens1303.csv e chat-user1303.csv
 *
 * Padrão de nome dos arquivos (em storage/chat-imports/):
 *   chat-mensagens{DDMM}.csv  →  mensagens
 *   chat-user{DDMM}.csv       →  usuários
 *
 * Ao omitir --date, o comando varre o diretório e usa os arquivos chat-mensagens*.csv
 * mais recentes encontrados.
 */
class ImportCsvChatMessages extends Command
{
    protected $signature = 'chat:import-csv
                            {--execute          : Executa a importação}
                            {--date=            : Data do export no formato DDMM (ex: 1303). Sem esta flag, auto-detecta o arquivo mais recente}
                            {--dir=             : Diretório dos CSVs (padrão: storage/chat-imports)}
                            {--messages=        : Caminho completo do CSV de mensagens (sobrepõe --date e --dir)}
                            {--users=           : Caminho completo do CSV de usuários (sobrepõe --date e --dir)}
                            {--active-days=30   : Mensagens com menos de N dias vão para chat_messages; demais vão para archive}';

    protected $description = 'Importa mensagens de chat do CSV de produção (sobrepõe dados existentes)';

    public function handle(): int
    {
        $csvPath = $this->resolvePath('chat-mensagens', '.csv', 'messages');
        $usersCsvPath = $this->resolvePath('chat-user', '.csv', 'users');
        $execute = $this->option('execute');
        $activeDays = (int) $this->option('active-days');

        $dir = rtrim($this->option('dir') ?: storage_path('chat-imports'), '/');

        $this->info('=== Importação de Mensagens do Chat (CSV) ===');
        $this->info("Diretório : {$dir}");
        $this->info("Mensagens : {$csvPath}");
        $this->info("Usuários  : {$usersCsvPath}");
        $this->newLine();

        if (! file_exists($csvPath)) {
            $this->error("Arquivo não encontrado: {$csvPath}");

            return 1;
        }

        // ─── Lê e valida o CSV ───────────────────────────────────────────────
        $messages = $this->parseCsv($csvPath);
        $active = array_filter($messages, fn ($m) => $m['is_deleted'] === '0');
        $csvUsers = $this->loadUsersCsv($usersCsvPath);

        $this->info('Total de mensagens no CSV:   '.count($messages));
        $this->info('Não deletadas (a importar):  '.count($active));
        $this->newLine();

        // ─── Separa ativos vs arquivo ─────────────────────────────────────────
        $cutoff = Carbon::now()->subDays($activeDays);
        $byNr = [];

        foreach ($active as $msg) {
            $byNr[$msg['nr_atendimento']][] = $msg;
        }

        $activeNrs = [];
        $archiveNrs = [];

        foreach ($byNr as $nr => $msgs) {
            $lastDt = max(array_map(fn ($m) => strtotime($m['dt_criacao']), $msgs));
            if ($lastDt >= $cutoff->timestamp) {
                $activeNrs[] = $nr;
            } else {
                $archiveNrs[] = $nr;
            }
        }

        $this->table(['Destino', 'Atendimentos', 'Mensagens'], [
            ['chat_messages (ativos)',          count($activeNrs),  array_sum(array_map(fn ($nr) => count($byNr[$nr]), $activeNrs))],
            ['chat_messages_archive (encerr.)', count($archiveNrs), array_sum(array_map(fn ($nr) => count($byNr[$nr]), $archiveNrs))],
        ]);

        // ─── O que já existe no banco (informativo) ───────────────────────────
        $existingActive = DB::table('chat_messages')
            ->whereIn('nr_atendimento', array_keys($byNr))->distinct()->pluck('nr_atendimento')->toArray();
        $existingArchive = DB::table('chat_messages_archive')
            ->whereIn('nr_atendimento', array_keys($byNr))->pluck('nr_atendimento')->toArray();

        $existingCount = count(array_unique(array_merge($existingActive, $existingArchive)));
        if ($existingCount > 0) {
            $this->warn("{$existingCount} atendimento(s) já existem no banco e serão sobrescritos.");
        }

        // ─── Verifica usuários no banco ───────────────────────────────────────
        $userIdsInCsv = array_values(array_unique(array_column($csvUsers, 'id')));
        $userIdsInDb = DB::table('users')->whereIn('id', $userIdsInCsv)->pluck('username', 'id')->toArray();
        $missingUsers = array_diff($userIdsInCsv, array_keys($userIdsInDb));

        if (! empty($missingUsers)) {
            $this->warn('user_ids sem correspondência no banco: '.implode(', ', $missingUsers));
            $this->warn('Esses usuários serão inseridos na tabela users com os mesmos IDs antes da importação.');
        } else {
            $this->info('[OK] Todos os user_ids do CSV existem no banco.');
        }

        if (! $execute) {
            $this->newLine();
            $this->warn('Modo dry-run — nenhum dado alterado. Use --execute para executar.');

            return 0;
        }

        $this->syncMissingUsers($csvUsers);

        // ─── Limpa dados existentes em lote antes de reescrever ──────────────
        $allNrs = array_keys($byNr);

        // Deleta pins → mensagens → archive para todos os atendimentos do CSV
        $existingMessageIds = DB::table('chat_messages')
            ->whereIn('nr_atendimento', $allNrs)
            ->pluck('id')
            ->toArray();

        if (! empty($existingMessageIds)) {
            DB::table('chat_message_pins')->whereIn('message_id', $existingMessageIds)->delete();
            DB::table('chat_messages')->whereIn('id', $existingMessageIds)->delete();
        }

        DB::table('chat_messages_archive')->whereIn('nr_atendimento', $allNrs)->delete();

        // ─── Importa ativos → chat_messages ──────────────────────────────────
        $importedMsgs = 0;
        $archivedNrCount = 0;
        $errors = 0;

        foreach ($activeNrs as $nr) {
            try {
                DB::transaction(function () use ($byNr, $nr, &$importedMsgs) {
                    foreach ($byNr[$nr] as $msg) {
                        $updatedAt = $msg['dt_edicao'] ?? '';
                        $updatedAt = ($updatedAt && $updatedAt !== 'NULL') ? $updatedAt : null;

                        $newId = DB::table('chat_messages')->insertGetId([
                            'nr_atendimento' => $msg['nr_atendimento'],
                            'cd_pessoa_fisica' => null,
                            'user_id' => $msg['usuario_id'],
                            'content' => $msg['mensagem'],
                            'created_at' => $msg['dt_criacao'],
                            'updated_at' => $updatedAt,
                        ]);

                        if ($msg['is_fixed'] === '1' && ! empty($msg['fixed_by'])) {
                            DB::table('chat_message_pins')->insert([
                                'message_id' => $newId,
                                'nr_atendimento' => $msg['nr_atendimento'],
                                'pinned_by' => $msg['fixed_by'],
                                'pinned_at' => $msg['fixed_at'] ?: $msg['dt_criacao'],
                                'unpinned_at' => null,
                                'unpinned_by' => null,
                            ]);
                        }

                        $importedMsgs++;
                    }
                });
            } catch (\Exception $e) {
                $errors++;
                $this->warn("Erro ao importar nr={$nr}: ".$e->getMessage());
            }
        }

        // ─── Arquiva encerrados → chat_messages_archive ───────────────────────
        $usersById = DB::table('users')
            ->get()
            ->mapWithKeys(function ($user) {
                return [(int) $user->id => (array) $user];
            })
            ->toArray();

        $usersByUsername = [];
        foreach ($usersById as $user) {
            if (! empty($user['username'])) {
                $usersByUsername[$user['username']] = $user;
            }
        }

        if (file_exists($usersCsvPath)) {
            foreach ($this->parseCsv($usersCsvPath) as $u) {
                $id = (int) $u['id'];
                $usersById[$id] = array_replace($usersById[$id] ?? [], $u);

                if (! empty($u['username'])) {
                    $usersByUsername[$u['username']] = array_replace($usersByUsername[$u['username']] ?? [], $u);
                }
            }
        }

        $usernames = [];
        foreach ($usersById as $id => $user) {
            $usernames[(int) $id] = $user['username'] ?? ('user_'.$id);
        }

        foreach ($archiveNrs as $nr) {
            try {
                $msgs = $byNr[$nr];
                $payload = array_map(function ($m) use ($usernames) {
                    return [
                        'ts' => strtotime($m['dt_criacao']),
                        'u' => $usernames[$m['usuario_id']] ?? 'user_'.$m['usuario_id'],
                        'm' => $m['mensagem'],
                        't' => $m['turno_id'] ?: $this->inferTurno($m['dt_criacao']),
                    ];
                }, $msgs);

                usort($payload, fn ($a, $b) => $a['ts'] <=> $b['ts']);

                $messageUsers = [];
                foreach ($payload as $message) {
                    $username = $message['u'] ?? null;
                    if ($username && isset($usersByUsername[$username])) {
                        $messageUsers[$username] = $usersByUsername[$username];
                    }
                }

                $compressed = ChatArchivePayload::encode($payload, $messageUsers);
                $timestamps = array_column($payload, 'ts');

                DB::table('chat_messages_archive')->insert([
                    'nr_atendimento' => $nr,
                    'message_count' => count($payload),
                    'first_message_at' => date('Y-m-d H:i:s', min($timestamps)),
                    'last_message_at' => date('Y-m-d H:i:s', max($timestamps)),
                    'payload' => $compressed,
                    'source' => 'prod_csv_import',
                    'archived_at' => now(),
                ]);

                $archivedNrCount++;
            } catch (\Exception $e) {
                $errors++;
                $this->warn("Erro ao arquivar nr={$nr}: ".$e->getMessage());
            }
        }

        $personBackfillCount = $this->backfillChatMessagePeople();

        $this->newLine();
        $this->info("Mensagens importadas (chat_messages):        {$importedMsgs}");
        $this->info("Atendimentos arquivados (archive):           {$archivedNrCount}");
        $this->info("Mensagens atualizadas com cd_pessoa_fisica:  {$personBackfillCount}");
        $this->info("Erros:                                       {$errors}");
        $this->newLine();
        $this->line('Total no banco:');
        $this->line('  chat_messages:         '.DB::table('chat_messages')->count());
        $this->line('  chat_messages_archive: '.DB::table('chat_messages_archive')->count());

        return $errors > 0 ? 1 : 0;
    }

    private function resolvePath(string $prefix, string $ext, string $optionName): string
    {
        if ($this->option($optionName)) {
            return $this->option($optionName);
        }

        $dir = rtrim($this->option('dir') ?: storage_path('chat-imports'), '/');

        if ($this->option('date')) {
            return "{$dir}/{$prefix}{$this->option('date')}{$ext}";
        }

        // Auto-detecção: usa o arquivo mais recente que corresponda ao padrão
        $files = glob("{$dir}/{$prefix}*{$ext}");
        if (! empty($files)) {
            usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

            return $files[0];
        }

        // Fallback: nome com data de hoje
        return "{$dir}/{$prefix}".now()->format('dm')."{$ext}";
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $delimiter = $this->detectDelimiter($path);
        $headerLine = fgets($handle);

        if ($headerLine === false) {
            fclose($handle);

            return [];
        }

        $headers = str_getcsv(trim($headerLine), $delimiter);
        if (! empty($headers)) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        $buffer = '';
        while (($line = fgets($handle)) !== false) {
            $buffer .= ($buffer === '' ? '' : "\n").rtrim($line, "\r\n");

            $row = str_getcsv($buffer, $delimiter);
            if (count($row) < count($headers)) {
                continue;
            }

            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
                $buffer = '';

                continue;
            }

            // Se ficou com colunas sobrando, tentamos limpar o buffer atual e seguir.
            $buffer = '';
        }

        if ($buffer !== '') {
            $row = str_getcsv($buffer, $delimiter);
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = $handle ? fgets($handle) : false;

        if ($handle) {
            fclose($handle);
        }

        if ($line === false) {
            return ',';
        }

        $semicolonCount = substr_count($line, ';');
        $commaCount = substr_count($line, ',');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadUsersCsv(string $path): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException("Arquivo de usuários não encontrado: {$path}");
        }

        return $this->parseCsv($path);
    }

    /**
     * @param  array<int, array<string, mixed>>  $csvUsers
     */
    private function syncMissingUsers(array $csvUsers): int
    {
        $ids = array_values(array_unique(array_column($csvUsers, 'id')));
        if (empty($ids)) {
            return 0;
        }

        $existingIds = DB::table('users')->whereIn('id', $ids)->pluck('id')->all();
        $existingLookup = array_fill_keys(array_map('intval', $existingIds), true);

        $missingRows = [];
        foreach ($csvUsers as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($existingLookup[$id])) {
                continue;
            }

            $missingRows[] = ChatImportUserPayload::fromCsvRow($row);
        }

        if (empty($missingRows)) {
            return 0;
        }

        DB::table('users')->insert($missingRows);

        $this->info('Usuários inseridos na tabela users: '.count($missingRows));

        return count($missingRows);
    }

    private function backfillChatMessagePeople(): int
    {
        $attendanceNumbers = DB::table('chat_messages')
            ->distinct()
            ->pluck('nr_atendimento')
            ->filter()
            ->values()
            ->all();

        if (empty($attendanceNumbers)) {
            return 0;
        }

        $attendanceToPerson = [];
        foreach (array_chunk($attendanceNumbers, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));

            $rows = DB::connection('tasy')->select(
                "SELECT nr_atendimento, cd_pessoa_fisica FROM tasy.atendimento_paciente WHERE nr_atendimento IN ({$placeholders})",
                $chunk
            );

            foreach ($rows as $nr => $cdPessoaFisica) {
                if ($cdPessoaFisica->cd_pessoa_fisica !== null) {
                    $attendanceToPerson[(string) $cdPessoaFisica->nr_atendimento] = (int) $cdPessoaFisica->cd_pessoa_fisica;
                }
            }
        }

        if (empty($attendanceToPerson)) {
            return 0;
        }

        $updated = 0;
        foreach ($attendanceToPerson as $nr => $cdPessoaFisica) {
            $updated += DB::table('chat_messages')
                ->where('nr_atendimento', $nr)
                ->update(['cd_pessoa_fisica' => $cdPessoaFisica]);
        }

        return $updated;
    }

    private function inferTurno(string $datetime): string
    {
        $hour = (int) date('H', strtotime($datetime));
        if ($hour >= 7 && $hour < 13) {
            return 'manha';
        }
        if ($hour >= 13 && $hour < 19) {
            return 'tarde';
        }

        return 'noite';
    }
}
