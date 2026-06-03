<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProdChatData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:import-prod-csv
                            {--dir= : Path to export folder (default: storage/chat-imports/export-0306-tabelas)}
                            {--skip-users : Skip users import}
                            {--skip-messages : Skip messages import}
                            {--dry-run : Show counts without inserting}';

    protected $description = 'Import prod users + chat_mensagens CSV preserving prod IDs';

    private string $dir;

    public function handle(): int
    {
        $this->dir = $this->option('dir')
            ?? storage_path('chat-imports/export-0306-tabelas');

        if (! is_dir($this->dir)) {
            $this->error("Directory not found: {$this->dir}");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        if (! $this->option('skip-users')) {
            $this->importUsers($dry);
        }

        if (! $this->option('skip-messages')) {
            $this->importMessages($dry);
        }

        $this->importPins($dry);
        $this->importArchiveJson($dry);

        return self::SUCCESS;
    }

    private function importUsers(bool $dry): void
    {
        $file = $this->dir.'/users.csv';
        if (! file_exists($file)) {
            $this->warn('users.csv not found — skipping');

            return;
        }

        $this->info('Importing users...');

        $rows = $this->parseCsv($file);
        $count = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (! $id) {
                continue;
            }

            $data = [
                'id' => $id,
                'username' => $row['username'] ?? '',
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'role' => ($row['role'] && strtoupper($row['role']) !== 'NULL') ? $row['role'] : null,
                'is_nurse' => (bool) ($row['is_nurse'] ?? 0),
                'only_assigned_beds' => (bool) ($row['only_assigned_beds'] ?? 0),
                'status' => $row['status'] ?? 'A',
                'password' => $row['password'] ?? '',
                'last_access_at' => $this->nullableDate($row['last_access_at'] ?? null),
                'created_at' => $this->nullableDate($row['created_at'] ?? null) ?? now(),
                'updated_at' => $this->nullableDate($row['updated_at'] ?? null) ?? now(),
                'guid' => $row['guid'] ?? null,
                'domain' => $row['domain'] ?? 'default',
            ];

            if (! $dry) {
                $dataWithPhoto = $data + ['photo' => $row['photo'] ?? null];
                DB::table('users')->upsert(
                    [$dataWithPhoto],
                    ['id'],
                    ['username', 'name', 'email', 'role', 'is_nurse', 'status', 'photo', 'last_access_at', 'updated_at', 'guid', 'domain']
                );
            }

            $count++;
        }

        $this->info("  Users: {$count} processed".($dry ? ' (dry-run)' : ''));
    }

    private function importMessages(bool $dry): void
    {
        $file = $this->dir.'/chat_mensagens.csv';
        if (! file_exists($file)) {
            $this->warn('chat_mensagens.csv not found — skipping');

            return;
        }

        $this->info('Importing chat messages (this may take a moment)...');

        $existingIds = DB::table('chat_messages')->pluck('id')->flip()->all();
        $rows = $this->parseCsv($file);
        $inserted = 0;
        $skipped = 0;
        $batch = [];
        $batchSize = 500;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (! $id) {
                continue;
            }

            if (isset($existingIds[$id])) {
                $skipped++;

                continue;
            }

            $batch[] = [
                'id' => $id,
                'nr_atendimento' => $row['nr_atendimento'] ?? '',
                'cd_pessoa_fisica' => $row['cd_pessoa_fisica'] ?: null,
                'sector_id' => ($row['sector_id'] !== '' && $row['sector_id'] !== null) ? (int) $row['sector_id'] : null,
                'sector_name' => $row['sector_name'] ?: null,
                'user_id' => (int) ($row['user_id'] ?? 0),
                'content' => $row['content'] ?? '',
                'created_at' => $this->nullableDate($row['created_at'] ?? null) ?? now(),
                'updated_at' => $this->nullableDate($row['updated_at'] ?? null) ?? now(),
            ];

            if (count($batch) >= $batchSize) {
                if (! $dry) {
                    DB::table('chat_messages')->insert($batch);
                }
                $inserted += count($batch);
                $batch = [];
                $this->output->write('.');
            }
        }

        if (! empty($batch)) {
            if (! $dry) {
                DB::table('chat_messages')->insert($batch);
            }
            $inserted += count($batch);
        }

        $this->newLine();
        $this->info("  Messages: {$inserted} inserted, {$skipped} already existed".($dry ? ' (dry-run)' : ''));
        $this->info('  Total in DB: '.DB::table('chat_messages')->count());
    }

    private function importArchiveJson(bool $dry): void
    {
        $file = $this->dir.'/chat_messages_archive.json';
        if (! file_exists($file)) {
            $this->warn('chat_messages_archive.json not found — skipping');

            return;
        }

        // Guard: archive messages have no stable ID — skip if already imported
        $existingArchiveCount = DB::table('chat_messages')->where('id', '>', 46076)->count();
        if ($existingArchiveCount > 0) {
            $this->info("  Archive already imported ({$existingArchiveCount} messages) — skipping");

            return;
        }

        $this->info('Importing archive from JSON (decompressing payloads)...');

        $userMap = DB::table('users')->pluck('id', 'username')->all();
        $records = json_decode(file_get_contents($file), true) ?? [];
        $inserted = 0;
        $skipped = 0;
        $batch = [];
        $batchSize = 500;
        $nextId = (int) DB::table('chat_messages')->max('id') + 1;

        foreach ($records as $record) {
            $nrAtendimento = $record['nr_atendimento'] ?? '';
            $sectorName = $record['sector_name'] ?? null;
            if ($sectorName && str_starts_with($sectorName, '[')) {
                $decoded = json_decode($sectorName, true);
                $sectorName = is_array($decoded) ? ($decoded[0] ?? null) : $sectorName;
            }

            try {
                $raw = base64_decode($record['payload']);
                $decompressed = @gzuncompress($raw) ?: @gzinflate($raw) ?: @gzdecode($raw);
                $payload = $decompressed ? json_decode($decompressed, true) : null;
            } catch (\Throwable) {
                $skipped++;

                continue;
            }

            $messages = $payload['messages'] ?? (is_array($payload) && isset($payload[0]) ? $payload : null);
            if (! is_array($messages)) {
                $skipped++;

                continue;
            }

            foreach ($messages as $msg) {
                $username = $msg['u'] ?? null;
                $userId = $username ? ($userMap[$username] ?? null) : null;
                if (! $userId || empty($msg['m'])) {
                    $skipped++;

                    continue;
                }

                $ts = isset($msg['ts']) && is_numeric($msg['ts'])
                    ? Carbon::createFromTimestamp((int) $msg['ts'])->toDateTimeString()
                    : now()->toDateTimeString();

                $batch[] = [
                    'id' => $nextId++,
                    'nr_atendimento' => $nrAtendimento,
                    'cd_pessoa_fisica' => null,
                    'sector_id' => null,
                    'sector_name' => $sectorName,
                    'user_id' => $userId,
                    'content' => $msg['m'],
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];

                if (count($batch) >= $batchSize) {
                    if (! $dry) {
                        DB::table('chat_messages')->insert($batch);
                    }
                    $inserted += count($batch);
                    $batch = [];
                    $this->output->write('.');
                }
            }
        }

        if (! empty($batch)) {
            if (! $dry) {
                DB::table('chat_messages')->insert($batch);
            }
            $inserted += count($batch);
        }

        $this->newLine();
        $this->info("  Archive JSON: {$inserted} inserted, {$skipped} skipped");
        $this->info('  Total in DB: '.DB::table('chat_messages')->count());
    }

    private function importArchive(bool $dry): void
    {
        $file = $this->dir.'/chat_mensagens_archive.csv';
        if (! file_exists($file)) {
            $this->warn('chat_mensagens_archive.csv not found — skipping');

            return;
        }

        $this->info('Importing archive messages (decompressing payloads)...');

        // Build username → user_id lookup from users table
        $userMap = DB::table('users')->pluck('id', 'username')->all();

        $rows = $this->parseCsv($file);
        $inserted = 0;
        $skipped = 0;
        $batch = [];
        $batchSize = 500;

        // Use high IDs to avoid collision with active messages
        $nextId = (int) DB::table('chat_messages')->max('id') + 1;

        foreach ($rows as $row) {
            $nrAtendimento = $row['nr_atendimento'] ?? '';
            $sectorName = $row['sector_name'] ?? null;

            // sector_name stored as JSON array e.g. ["HSR 2º PRT/CNV"]
            if ($sectorName && str_starts_with($sectorName, '[')) {
                $decoded = json_decode($sectorName, true);
                $sectorName = is_array($decoded) ? ($decoded[0] ?? null) : $sectorName;
            }

            try {
                $raw = base64_decode($row['payload']);
                $decompressed = @gzuncompress($raw) ?: @gzinflate($raw) ?: @gzdecode($raw);
                $decoded = $decompressed ? json_decode($decompressed, true) : null;
            } catch (\Throwable) {
                $skipped++;

                continue;
            }

            $messages = $decoded['messages'] ?? (is_array($decoded) && isset($decoded[0]) ? $decoded : null);

            if (! is_array($messages)) {
                $skipped++;

                continue;
            }

            foreach ($messages as $msg) {
                $username = $msg['u'] ?? null;
                $userId = $username ? ($userMap[$username] ?? null) : null;

                if (! $userId || empty($msg['m'])) {
                    $skipped++;

                    continue;
                }

                $ts = isset($msg['ts']) && is_numeric($msg['ts'])
                    ? Carbon::createFromTimestamp((int) $msg['ts'])->toDateTimeString()
                    : now()->toDateTimeString();

                $batch[] = [
                    'id' => $nextId++,
                    'nr_atendimento' => $nrAtendimento,
                    'cd_pessoa_fisica' => null,
                    'sector_id' => null,
                    'sector_name' => $sectorName,
                    'user_id' => $userId,
                    'content' => $msg['m'],
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];

                if (count($batch) >= $batchSize) {
                    if (! $dry) {
                        DB::table('chat_messages')->insert($batch);
                    }
                    $inserted += count($batch);
                    $batch = [];
                    $this->output->write('.');
                }
            }
        }

        if (! empty($batch)) {
            if (! $dry) {
                DB::table('chat_messages')->insert($batch);
            }
            $inserted += count($batch);
        }

        $this->newLine();
        $this->info("  Archive: {$inserted} messages inserted, {$skipped} skipped (unknown user or empty)");
        $this->info('  Total in DB: '.DB::table('chat_messages')->count());
    }

    private function importPins(bool $dry): void
    {
        $file = $this->dir.'/chat_mensagens_pins.csv';
        if (! file_exists($file)) {
            $this->warn('chat_mensagens_pins.csv not found — skipping');

            return;
        }

        $this->info('Importing pins...');

        $rows = $this->parseCsv($file);
        $existingIds = DB::table('chat_message_pins')->pluck('id')->flip()->all();
        $inserted = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (! $id || isset($existingIds[$id])) {
                continue;
            }

            if (! $dry) {
                DB::table('chat_message_pins')->insert([
                    'id' => $id,
                    'message_id' => (int) $row['message_id'],
                    'nr_atendimento' => $row['nr_atendimento'] ?? '',
                    'pinned_by' => (int) $row['pinned_by'],
                    'pinned_at' => $this->nullableDate($row['pinned_at'] ?? null) ?? now(),
                    'unpinned_at' => $this->nullableDate($row['unpinned_at'] ?? null),
                    'unpinned_by' => ($row['unpinned_by'] !== '' && $row['unpinned_by'] !== null) ? (int) $row['unpinned_by'] : null,
                ]);
            }

            $inserted++;
        }

        $this->info("  Pins: {$inserted} inserted".($dry ? ' (dry-run)' : ''));
    }

    private function nullableDate(?string $value): ?string
    {
        if (! $value || strtoupper($value) === 'NULL' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        return $value;
    }

    private function parseCsv(string $file): array
    {
        $handle = fopen($file, 'r');
        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        $rows = [];

        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, $line);
            }
        }

        fclose($handle);

        return $rows;
    }
}
