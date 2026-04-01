<?php

namespace App\Console\Commands;

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
        $userIdsInCsv = array_unique(array_column(array_values($active), 'usuario_id'));
        $userIdsInDb = DB::table('users')->whereIn('id', $userIdsInCsv)->pluck('username', 'id')->toArray();
        $missingUsers = array_diff($userIdsInCsv, array_keys($userIdsInDb));

        if (! empty($missingUsers)) {
            $this->warn('user_ids sem correspondência no banco: '.implode(', ', $missingUsers));
        } else {
            $this->info('[OK] Todos os user_ids do CSV existem no banco.');
        }

        if (! $execute) {
            $this->newLine();
            $this->warn('Modo dry-run — nenhum dado alterado. Use --execute para executar.');

            return 0;
        }

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
        $usernames = DB::table('users')->pluck('username', 'id')->toArray();
        if (file_exists($usersCsvPath)) {
            foreach ($this->parseCsv($usersCsvPath) as $u) {
                $usernames[(int) $u['id']] = $u['username'];
            }
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

                $json = json_encode(array_values($payload), JSON_UNESCAPED_UNICODE);
                $compressed = base64_encode(gzcompress($json, 6));
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

        $this->newLine();
        $this->info("Mensagens importadas (chat_messages):        {$importedMsgs}");
        $this->info("Atendimentos arquivados (archive):           {$archivedNrCount}");
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
        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return $rows;
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
