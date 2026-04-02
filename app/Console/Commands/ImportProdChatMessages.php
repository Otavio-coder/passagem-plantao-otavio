<?php

namespace App\Console\Commands;

use App\Support\ChatArchivePayload;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importa mensagens do banco de produção (formato antigo com sessões) para
 * o banco atual (chat_messages sem sessões).
 *
 * Estrutura antiga (prod):
 *   chat_sessoes  → id, nr_atendimento, turno_id, data_sessao, inicio, fim, encerrada
 *   chat_mensagens → id, sessao_id, nr_atendimento, turno_id, usuario_id, mensagem,
 *                    dt_criacao, dt_edicao, is_fixed, fixed_by, fixed_at, is_deleted
 *
 * Mapeamento para chat_messages atual:
 *   nr_atendimento  → nr_atendimento
 *   usuario_id      → user_id  (mesmo ID — tabela users compartilhada)
 *   mensagem        → content
 *   dt_criacao      → created_at
 *   dt_edicao       → updated_at
 *   is_fixed=true   → gera entrada em chat_message_pins
 *   is_deleted=true → descartada (não importa)
 *
 * Mensagens de atendimentos ativos (presentes em TASY): importadas para chat_messages.
 * Mensagens de atendimentos encerrados (não ativos):   arquivadas em chat_messages_archive.
 *
 * Uso:
 *   php artisan chat:import-prod               # diagnóstico apenas
 *   php artisan chat:import-prod --execute     # executa a importação
 *   php artisan chat:import-prod --execute --active-only   # só atendimentos ativos
 *   php artisan chat:import-prod --execute --archive-only  # só atendimentos encerrados
 *   php artisan chat:import-prod --execute --chunk=500     # tamanho do lote
 */
class ImportProdChatMessages extends Command
{
    protected $signature = 'chat:import-prod
                            {--execute    : Executa a importação (sem esta flag apenas diagnostica)}
                            {--active-only  : Importa somente atendimentos ainda ativos no chat_messages}
                            {--archive-only : Importa somente atendimentos encerrados para o arquivo}
                            {--chunk=200  : Quantidade de mensagens processadas por lote}
                            {--since=     : Importa apenas mensagens criadas após esta data (Y-m-d)}';

    protected $description = 'Importa mensagens do banco de produção (formato antigo) para o novo esquema';

    // Tabelas na conexão de produção
    private const PROD_SESSIONS = 'chat_sessoes';

    private const PROD_MESSAGES = 'chat_mensagens';

    public function handle(): int
    {
        $execute = $this->option('execute');
        $activeOnly = $this->option('active-only');
        $archiveOnly = $this->option('archive-only');
        $chunk = (int) $this->option('chunk');
        $since = $this->option('since');

        $this->info('=== Importação de Mensagens de Produção ===');
        $this->info('Conexão origem : mysql_prod ('.config('database.connections.mysql_prod.host').')');
        $this->info('Conexão destino: '.config('database.default'));
        $this->newLine();

        // ─── Testa conectividade ─────────────────────────────────────────────
        try {
            DB::connection('mysql_prod')->getPdo();
            $this->info('[OK] Conectado ao banco de produção.');
        } catch (\Exception $e) {
            $this->error('[ERRO] Não foi possível conectar ao banco de produção: '.$e->getMessage());
            $this->warn('Verifique as variáveis DB_HOST2, DB_PORT2, DB_DATABASE2, DB_USERNAME2, DB_PASSWORD2 no .env');

            return 1;
        }

        // ─── Diagnóstico ─────────────────────────────────────────────────────
        $this->runDiagnostics($since);

        if (! $execute) {
            $this->newLine();
            $this->warn('Modo diagnóstico — nenhum dado foi alterado.');
            $this->warn('Use --execute para executar a importação.');

            return 0;
        }

        // ─── Importação ──────────────────────────────────────────────────────
        $this->newLine();
        $this->info('Iniciando importação...');

        $imported = 0;
        $archived = 0;
        $skipped = 0;
        $errors = 0;

        // Busca todos os nr_atendimento distintos com mensagens não deletadas
        $query = DB::connection('mysql_prod')
            ->table(self::PROD_MESSAGES)
            ->select('nr_atendimento')
            ->where('is_deleted', false)
            ->distinct();

        if ($since) {
            $query->where('m.dt_criacao', '>=', $since.' 00:00:00');
        }

        $attendances = $query->pluck('nr_atendimento')->toArray();

        $this->info('Atendimentos com mensagens encontrados: '.count($attendances));

        // Atendimentos que já têm mensagens no destino (para evitar duplicatas)
        $existingAttendances = DB::table('chat_messages')
            ->whereIn('nr_atendimento', $attendances)
            ->distinct()
            ->pluck('nr_atendimento')
            ->toArray();

        // Atendimentos já arquivados
        $archivedAttendances = DB::table('chat_messages_archive')
            ->whereIn('nr_atendimento', $attendances)
            ->pluck('nr_atendimento')
            ->toArray();

        $alreadyHandled = array_merge($existingAttendances, $archivedAttendances);

        $toProcess = array_diff($attendances, $alreadyHandled);

        $this->info('Já importados/arquivados: '.count($alreadyHandled));
        $this->info('A processar nesta execução: '.count($toProcess));
        $this->newLine();

        if (empty($toProcess)) {
            $this->info('Nada a importar.');

            return 0;
        }

        // ─── Determina quais atendimentos são "ativos" ────────────────────────
        // "Ativo" = ainda existem mensagens nos últimos 30 dias (proxy para internados)
        $cutoffActive = Carbon::now()->subDays(30)->toDateTimeString();

        $activeAttendances = DB::connection('mysql_prod')
            ->table(self::PROD_MESSAGES)
            ->whereIn('nr_atendimento', $toProcess)
            ->where('dt_criacao', '>=', $cutoffActive)
            ->where('is_deleted', false)
            ->distinct()
            ->pluck('nr_atendimento')
            ->toArray();

        $inactiveAttendances = array_diff($toProcess, $activeAttendances);

        $this->info('Ativos (importar para chat_messages): '.count($activeAttendances));
        $this->info('Encerrados (arquivar em chat_messages_archive): '.count($inactiveAttendances));
        $this->newLine();

        $bar = $this->output->createProgressBar(count($toProcess));
        $bar->start();

        // ─── Processa ativos ─────────────────────────────────────────────────
        if (! $archiveOnly) {
            foreach ($activeAttendances as $nr) {
                try {
                    $count = $this->importActiveAttendance($nr, $since);
                    $imported += $count;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('[ImportProdChat] Erro ao importar ativo', [
                        'nr_atendimento' => $nr,
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        }

        // ─── Processa encerrados ─────────────────────────────────────────────
        if (! $activeOnly) {
            foreach ($inactiveAttendances as $nr) {
                try {
                    $this->archiveAttendance($nr, $since);
                    $archived++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('[ImportProdChat] Erro ao arquivar', [
                        'nr_atendimento' => $nr,
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Importadas (chat_messages):          {$imported} mensagens");
        $this->info("Arquivados (chat_messages_archive):  {$archived} atendimentos");
        $this->info("Erros:                               {$errors}");

        Log::info('[ImportProdChat] Importação concluída', compact('imported', 'archived', 'errors'));

        return $errors > 0 ? 1 : 0;
    }

    // ─── Diagnóstico ──────────────────────────────────────────────────────────

    private function runDiagnostics(?string $since): void
    {
        $prod = DB::connection('mysql_prod');

        // Verifica se as tabelas antigas existem
        $tables = $prod->select("SHOW TABLES LIKE 'chat_%'");
        $tableNames = array_map(fn ($t) => array_values((array) $t)[0], $tables);

        $this->line('Tabelas encontradas no banco de produção:');
        foreach ($tableNames as $t) {
            $this->line("  - {$t}");
        }
        $this->newLine();

        if (! in_array('chat_mensagens', $tableNames)) {
            $this->warn('Tabela chat_mensagens não encontrada. Verifique a conexão.');

            return;
        }

        $sessionsCount = $prod->table(self::PROD_SESSIONS)->count();
        $messagesCount = $prod->table(self::PROD_MESSAGES)->count();
        $deletedCount = $prod->table(self::PROD_MESSAGES)->where('is_deleted', true)->count();
        $activeCount = $prod->table(self::PROD_MESSAGES)->where('is_deleted', false)->count();
        $fixedCount = $prod->table(self::PROD_MESSAGES)->where('is_fixed', true)->where('is_deleted', false)->count();

        $this->table(['Métrica', 'Valor'], [
            ['Total de sessões',            number_format($sessionsCount)],
            ['Total de mensagens',          number_format($messagesCount)],
            ['Mensagens não deletadas',     number_format($activeCount)],
            ['Mensagens deletadas',         number_format($deletedCount)],
            ['Mensagens fixadas (pins)',     number_format($fixedCount)],
        ]);

        // Distribuição por turno
        $turnos = $prod->table(self::PROD_MESSAGES)
            ->where('is_deleted', false)
            ->selectRaw('turno_id, COUNT(*) as total')
            ->groupBy('turno_id')
            ->orderByDesc('total')
            ->get();

        $this->newLine();
        $this->line('Distribuição por turno:');
        $this->table(['Turno', 'Mensagens'], $turnos->map(fn ($r) => [$r->turno_id, number_format($r->total)])->toArray());

        // Distribuição temporal
        $byMonth = $prod->table(self::PROD_MESSAGES)
            ->where('is_deleted', false)
            ->selectRaw("DATE_FORMAT(dt_criacao, '%Y-%m') as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->orderByDesc('mes')
            ->limit(12)
            ->get();

        $this->newLine();
        $this->line('Mensagens por mês (últimos 12 meses):');
        $this->table(['Mês', 'Mensagens'], $byMonth->map(fn ($r) => [$r->mes, number_format($r->total)])->toArray());

        // Atendimentos distintos
        $distinctNr = $prod->table(self::PROD_MESSAGES)
            ->where('is_deleted', false)
            ->distinct()
            ->count('nr_atendimento');

        $cutoffActive = Carbon::now()->subDays(30)->toDateTimeString();
        $activeNr = $prod->table(self::PROD_MESSAGES)
            ->where('is_deleted', false)
            ->where('dt_criacao', '>=', $cutoffActive)
            ->distinct()
            ->count('nr_atendimento');

        $this->newLine();
        $this->line("Atendimentos distintos com mensagens: {$distinctNr}");
        $this->line("Destes, ativos (últimos 30 dias):     {$activeNr}");
        $this->line('Provavelmente encerrados:              '.($distinctNr - $activeNr));

        // Estimativa de espaço para arquivo
        $avgLen = $prod->table(self::PROD_MESSAGES)
            ->where('is_deleted', false)
            ->selectRaw('AVG(LENGTH(mensagem)) as avg_len')
            ->value('avg_len');

        $estimatedBytes = $avgLen * ($activeCount * 0.5); // zlib reduz ~50%
        $this->newLine();
        $this->line(sprintf(
            'Tamanho médio de mensagem: %d bytes | Estimativa comprimida total: ~%s MB',
            (int) $avgLen,
            number_format($estimatedBytes / 1024 / 1024, 1)
        ));
    }

    // ─── Importa atendimento ativo → chat_messages ────────────────────────────

    private function importActiveAttendance(string $nr, ?string $since): int
    {
        $query = DB::connection('mysql_prod')
            ->table(self::PROD_MESSAGES)
            ->where('nr_atendimento', $nr)
            ->where('is_deleted', false)
            ->orderBy('dt_criacao')
            ->select([
                'nr_atendimento',
                'usuario_id  as user_id',
                'mensagem    as content',
                'dt_criacao  as created_at',
                'dt_edicao   as updated_at',
                'is_fixed',
                'fixed_by',
                'fixed_at',
            ]);

        if ($since) {
            $query->where('dt_criacao', '>=', $since.' 00:00:00');
        }

        $messages = $query->get();
        $count = 0;

        DB::transaction(function () use ($messages, &$count) {
            foreach ($messages as $msg) {
                $newId = DB::table('chat_messages')->insertGetId([
                    'nr_atendimento' => $msg->nr_atendimento,
                    'cd_pessoa_fisica' => null,
                    'user_id' => $msg->user_id,
                    'content' => $msg->content,
                    'created_at' => $msg->created_at,
                    'updated_at' => $msg->updated_at,
                ]);

                // Recria pin se a mensagem estava fixada
                if ($msg->is_fixed && $msg->fixed_by) {
                    DB::table('chat_message_pins')->insertOrIgnore([
                        'message_id' => $newId,
                        'nr_atendimento' => $msg->nr_atendimento,
                        'pinned_by' => $msg->fixed_by,
                        'pinned_at' => $msg->fixed_at ?? $msg->created_at,
                        'unpinned_at' => null,
                        'unpinned_by' => null,
                    ]);
                }

                $count++;
            }
        });

        return $count;
    }

    // ─── Arquiva atendimento encerrado → chat_messages_archive ───────────────

    private function archiveAttendance(string $nr, ?string $since): void
    {
        $query = DB::connection('mysql_prod')
            ->table(self::PROD_MESSAGES)
            ->where('nr_atendimento', $nr)
            ->where('is_deleted', false)
            ->orderBy('dt_criacao')
            ->select(['usuario_id', 'mensagem', 'dt_criacao', 'turno_id']);

        if ($since) {
            $query->where('dt_criacao', '>=', $since.' 00:00:00');
        }

        $messages = $query->get();

        if ($messages->isEmpty()) {
            return;
        }

        $userIds = $messages->pluck('usuario_id')->unique()->toArray();
        $userNames = DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('username', 'id')
            ->toArray();

        $payload = $messages->map(function ($m) use ($userNames) {
            return [
                'ts' => Carbon::parse($m->dt_criacao)->timestamp,
                'u' => $userNames[$m->usuario_id] ?? (string) $m->usuario_id,
                'm' => $m->mensagem,
                't' => $m->turno_id ?? $this->inferTurno($m->dt_criacao),
            ];
        })->values()->toArray();

        $usersByUsername = [];
        $userRows = DB::table('users')
            ->whereIn('id', $userIds)
            ->get();

        foreach ($userRows as $user) {
            if (! empty($user->username)) {
                $usersByUsername[$user->username] = (array) $user;
            }
        }

        foreach ($payload as $message) {
            $username = $message['u'] ?? null;
            if ($username && ! isset($usersByUsername[$username])) {
                $usersByUsername[$username] = [
                    'username' => $username,
                    'id' => null,
                ];
            }
        }

        $compressed = ChatArchivePayload::encode($payload, $usersByUsername);

        $firstAt = $messages->first()->dt_criacao;
        $lastAt = $messages->last()->dt_criacao;

        DB::table('chat_messages_archive')->updateOrInsert(
            ['nr_atendimento' => $nr],
            [
                'message_count' => $messages->count(),
                'first_message_at' => $firstAt,
                'last_message_at' => $lastAt,
                'payload' => $compressed,
                'source' => 'prod_import',
                'archived_at' => now(),
            ]
        );
    }

    private function inferTurno(string $datetime): string
    {
        $hour = (int) Carbon::parse($datetime)->format('H');
        if ($hour >= 7 && $hour < 13) {
            return 'manha';
        }
        if ($hour >= 13 && $hour < 19) {
            return 'tarde';
        }

        return 'noite';
    }
}
