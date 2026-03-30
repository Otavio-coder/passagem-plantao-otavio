<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa usuários de produção a partir do CSV exportado.
 *
 * - Preserva os IDs originais (essencial para manter referência nas mensagens)
 * - Usa INSERT IGNORE para não sobrescrever usuários já existentes (ex: caio.pontes)
 * - Após importar, cria user_sector_preferences com todos os setores de system_configurations
 *   para que os usuários consigam acessar o sistema logo após o deploy
 *
 * Uso:
 *   php artisan prod:import-users                     # dry-run com data de hoje
 *   php artisan prod:import-users --date=1303         # dry-run com data 13/03
 *   php artisan prod:import-users --date=1303 --execute
 *   php artisan prod:import-users --execute --skip-sectors
 *
 * Padrão de nome dos arquivos: /home/caio.pontes/sql_docs/chat-user{DDMM}.csv
 */
class ImportProdUsersFromCsv extends Command
{
    protected $signature = 'prod:import-users
                            {--execute         : Executa a importação (sem esta flag apenas mostra o que faria)}
                            {--date=           : Data do export no formato DDMM (ex: 1303). Padrão: data de hoje}
                            {--dir=            : Diretório dos CSVs (padrão: /home/caio.pontes/sql_docs)}
                            {--csv=            : Caminho completo do CSV (sobrepõe --date e --dir)}
                            {--skip-sectors    : Não atribui setores padrão aos usuários importados}';

    protected $description = 'Importa usuários de produção (CSV) e atribui setores padrão';

    public function handle(): int
    {
        $csvPath = $this->resolvePath('chat-user', '.csv');
        $execute = $this->option('execute');
        $skipSectors = $this->option('skip-sectors');

        $this->info('=== Importação de Usuários de Produção ===');
        $this->info("Arquivo: {$csvPath}");
        $this->newLine();

        if (!file_exists($csvPath)) {
            $this->error("Arquivo não encontrado: {$csvPath}");
            return 1;
        }

        // ─── Lê o CSV ────────────────────────────────────────────────────────
        $users = $this->parseCsv($csvPath);

        if (empty($users)) {
            $this->error('Nenhum usuário encontrado no CSV.');
            return 1;
        }

        $this->info("Usuários no CSV: " . count($users));

        // ─── Verifica quais já existem ────────────────────────────────────────
        $existingIds       = DB::table('users')->pluck('id')->toArray();
        $existingUsernames = DB::table('users')->pluck('username')->toArray();

        $toInsert   = [];
        $toSkip     = [];
        $toUpdate   = []; // mesmo username mas ID diferente (conflito)

        foreach ($users as $user) {
            if (in_array($user['id'], $existingIds)) {
                $toSkip[] = $user;
            } elseif (in_array($user['username'], $existingUsernames)) {
                $toUpdate[] = $user; // username existe com ID diferente
            } else {
                $toInsert[] = $user;
            }
        }

        $this->table(
            ['Ação', 'Qtd', 'Detalhes'],
            [
                ['Inserir (novo)',        count($toInsert), implode(', ', array_column($toInsert, 'username'))],
                ['Pular (ID já existe)',  count($toSkip),   implode(', ', array_column($toSkip, 'username'))],
                ['Conflito de username',  count($toUpdate), implode(', ', array_column($toUpdate, 'username'))],
            ]
        );

        if (!empty($toUpdate)) {
            $this->newLine();
            $this->warn('CONFLITO DE USERNAME — usuários com mesmo username mas ID diferente:');
            foreach ($toUpdate as $u) {
                $existing = DB::table('users')->where('username', $u['username'])->first();
                $this->warn("  {$u['username']}: prod.id={$u['id']} | dev.id={$existing->id}");
                $this->warn("  → As mensagens do prod com usuario_id={$u['id']} serão atribuídas ao username correto no archive (mapeamento via CSV).");
            }
        }

        // ─── Setores disponíveis ──────────────────────────────────────────────
        $sectors = collect();
        if (Schema::hasTable('system_configurations')) {
            $sectors = DB::table('system_configurations')
                ->where('configuration_type', 'sector')
                ->where('is_active', true)
                ->get(['hospital_code', 'hospital_name', 'sector_code', 'sector_name']);
        }

        $this->newLine();
        $this->info("Setores disponíveis em system_configurations: " . $sectors->count());

        if ($sectors->isEmpty() && !$skipSectors) {
            $this->warn('Nenhum setor ativo em system_configurations. Usuários serão importados sem setores.');
            $this->warn('Eles verão o onboarding modal ao primeiro acesso para configurar seus setores.');
        }

        if (!$execute) {
            $this->newLine();
            $this->warn('Modo dry-run — nenhum dado alterado. Use --execute para executar.');
            return 0;
        }

        // ─── Importa usuários ─────────────────────────────────────────────────
        $inserted = 0;
        $sectorsAssigned = 0;
        $errors = 0;

        // Desabilita auto_increment temporariamente para preservar IDs
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($toInsert as $user) {
            try {
                DB::table('users')->insert([
                    'id'         => $user['id'],
                    'username'   => $user['username'],
                    'name'       => $user['name'],
                    'email'      => $user['email'],
                    'photo'      => $user['photo'] ?: null,
                    'password'   => $user['password'] ?: null,
                    'status'     => $user['status'] ?: 'A',
                    'guid'       => $user['guid'] ?: null,
                    'domain'     => $user['domain'] ?: null,
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['updated_at'],
                ]);
                $inserted++;
            } catch (\Exception $e) {
                $errors++;
                $this->warn("Erro ao inserir {$user['username']}: " . $e->getMessage());
            }
        }

        // Ajusta o auto_increment para ficar acima do maior ID importado
        $maxId = DB::table('users')->max('id');
        DB::statement("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info("Usuários inseridos: {$inserted}");

        // ─── Atribui setores ──────────────────────────────────────────────────
        if (!$skipSectors && $sectors->isNotEmpty()) {
            $allUserIds = DB::table('users')->pluck('id')->toArray();

            foreach ($allUserIds as $userId) {
                foreach ($sectors as $sector) {
                    try {
                        DB::table('user_sector_preferences')->insertOrIgnore([
                            'user_id'       => $userId,
                            'sector_code'   => $sector->sector_code,
                            'sector_name'   => $sector->sector_name,
                            'hospital_code' => $sector->hospital_code,
                            'hospital_name' => $sector->hospital_name,
                            'created_at'    => now(),
                        ]);
                        $sectorsAssigned++;
                    } catch (\Exception $e) {
                        // silencia duplicatas
                    }
                }
            }

            $this->info("Preferências de setor criadas: {$sectorsAssigned}");
        }

        if ($errors > 0) {
            $this->warn("Erros encontrados: {$errors}");
        }

        $this->newLine();
        $this->info('Resumo final:');
        $this->line('  Total de usuários no sistema: ' . DB::table('users')->count());
        $this->line('  Total de preferências de setor: ' . DB::table('user_sector_preferences')->count());

        return $errors > 0 ? 1 : 0;
    }

    private function resolvePath(string $prefix, string $ext): string
    {
        if ($this->option('csv')) {
            return $this->option('csv');
        }

        $dir  = rtrim($this->option('dir') ?: '/home/caio.pontes/sql_docs', '/');
        $date = $this->option('date') ?: now()->format('dm'); // ex: 1303

        return "{$dir}/{$prefix}{$date}{$ext}";
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
}
