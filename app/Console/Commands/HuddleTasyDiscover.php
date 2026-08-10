<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Descoberta completa da integração Huddle × Tasy (somente leitura).
 *
 * Roda toda a investigação de uma vez e imprime um relatório: ambiente
 * (primário/réplica), o questionário e seu id, perguntas e opções, permissão de
 * escrita, colunas obrigatórias e triggers das tabelas de resposta. Serve para o
 * desenvolvimento avançar de forma autônoma, sem depender de consultas manuais.
 *
 * Uso:
 *   php artisan huddle:tasy-discover                 (procura por "AVALIA")
 *   php artisan huddle:tasy-discover "TESTE AVALIA"  (procura por outro termo)
 *   php artisan huddle:tasy-discover --seq=724976    (usa um NR_SEQUENCIA direto)
 */
class HuddleTasyDiscover extends Command
{
    protected $signature = 'huddle:tasy-discover
                            {termo=AVALIA : Trecho do nome do questionário (sem acento, maiúsculo)}
                            {--seq= : NR_SEQUENCIA do QUE_QUESTIONARIO (ignora a busca por termo)}';

    protected $description = 'Descobre tudo para a integração Huddle × Tasy (ambiente, questionário, perguntas, permissões, colunas, triggers)';

    private const ANSWER_TABLES = ['QUE_ATENDIMENTO', 'QUE_ATENDIMENTO_QUESTAO', 'QUE_ATENDIMENTO_QUESTAO_OP'];

    public function handle(): int
    {
        $this->section('1) Ambiente do banco (conexão do projeto)');
        $this->environment();

        $this->section('2) Questionário');
        $seq = $this->resolveQuestionnaire();

        if ($seq !== null) {
            $this->section('3) Perguntas do questionário '.$seq);
            $questionSeqs = $this->questions($seq);

            $this->section('4) Opções de resposta');
            $this->questionOptions($questionSeqs);
        } else {
            $this->warn('Sem questionário identificado — pulando perguntas/opções.');
        }

        $this->section('5) Permissão de escrita nas tabelas de resposta');
        $this->privileges();

        $this->section('6) Colunas obrigatórias (NOT NULL) das tabelas de resposta');
        $this->requiredColumns();

        $this->section('7) Triggers nas tabelas de resposta');
        $this->triggers();

        $this->newLine();
        $this->info('Descoberta concluída. Use os dados acima para configurar HUDDLE_QUESTIONNAIRE_SEQ e planejar o write-back.');

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info('══ '.$title.' ══');
    }

    private function environment(): void
    {
        try {
            $env = DB::connection('tasy')->selectOne(
                "SELECT sys_context('USERENV','SERVICE_NAME') servico,
                        sys_context('USERENV','SERVER_HOST')  servidor,
                        sys_context('USERENV','DB_NAME')      banco,
                        USER                                  usuario
                 FROM dual"
            );
            $this->line("  Serviço : {$env->servico}");
            $this->line("  Servidor: {$env->servidor}");
            $this->line("  Banco   : {$env->banco}");
            $this->line("  Usuário : {$env->usuario}");
        } catch (\Throwable $e) {
            $this->error('  Falha USERENV: '.$e->getMessage());
        }

        try {
            $role = DB::connection('tasy')->selectOne('SELECT database_role, open_mode FROM v$database');
            $this->line("  Papel   : {$role->database_role} ({$role->open_mode})");
            if (str_contains(strtoupper((string) $role->database_role), 'STANDBY')) {
                $this->warn('  → Réplica Data Guard (só leitura): a gravação precisa ir para o primário.');
            }
        } catch (\Throwable $e) {
            $this->line('  Papel   : indisponível (sem privilégio em v$database).');
        }
    }

    private function resolveQuestionnaire(): ?int
    {
        if ($this->option('seq')) {
            $seq = (int) $this->option('seq');
            $row = $this->safeSelectOne(
                'SELECT nr_sequencia, ds_questionario, ie_situacao FROM tasy.que_questionario WHERE nr_sequencia = ?',
                [$seq]
            );
            if ($row) {
                $this->line("  [{$row->nr_sequencia}] {$row->ds_questionario} (situação: {$row->ie_situacao})");

                return (int) $row->nr_sequencia;
            }
            $this->warn("  Nenhum questionário com NR_SEQUENCIA {$seq}.");

            return null;
        }

        $termo = strtoupper((string) $this->argument('termo'));
        $rows = $this->safeSelect(
            'SELECT nr_sequencia, ds_questionario, ie_situacao
             FROM tasy.que_questionario
             WHERE UPPER(ds_questionario) LIKE ?
             ORDER BY nr_sequencia DESC',
            ['%'.$termo.'%']
        );

        if (empty($rows)) {
            $this->warn("  Nenhum questionário com \"{$termo}\". (Se as tabelas QUE_ estiverem vazias, o formulário ainda não foi publicado neste ambiente.)");

            return null;
        }

        foreach ($rows as $r) {
            $this->line("  [{$r->nr_sequencia}] {$r->ds_questionario} (situação: {$r->ie_situacao})");
        }

        $this->newLine();
        $this->line('  → Defina no .env: HUDDLE_QUESTIONNAIRE_SEQ='.$rows[0]->nr_sequencia);

        return (int) $rows[0]->nr_sequencia;
    }

    private function questions(int $seq): array
    {
        $rows = $this->safeSelect(
            'SELECT nr_sequencia, ds_questao, ie_tipo_resposta, nr_seq_apresentacao, ie_situacao
             FROM tasy.que_questao
             WHERE nr_seq_questionario = ?
             ORDER BY nr_seq_apresentacao',
            [$seq]
        );

        if (empty($rows)) {
            $this->warn('  Nenhuma pergunta encontrada.');

            return [];
        }

        foreach ($rows as $r) {
            $this->line("  [{$r->nr_sequencia}] ({$r->ie_tipo_resposta}) {$r->ds_questao}");
        }

        return array_map(fn ($r) => (int) $r->nr_sequencia, $rows);
    }

    private function questionOptions(array $questionSeqs): void
    {
        if (empty($questionSeqs)) {
            $this->line('  —');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($questionSeqs), '?'));
        $rows = $this->safeSelect(
            "SELECT nr_seq_questao, nr_sequencia, ds_opcao, ie_situacao
             FROM tasy.que_questao_opcao
             WHERE nr_seq_questao IN ({$placeholders})
             ORDER BY nr_seq_questao, nr_seq_apres",
            $questionSeqs
        );

        if (empty($rows)) {
            $this->line('  (sem opções cadastradas — pode ser resposta livre/texto)');

            return;
        }

        foreach ($rows as $r) {
            $this->line("  pergunta {$r->nr_seq_questao} → [{$r->nr_sequencia}] {$r->ds_opcao}");
        }
    }

    private function privileges(): void
    {
        foreach (self::ANSWER_TABLES as $table) {
            $rows = $this->safeSelect(
                'SELECT privilege FROM all_tab_privs WHERE table_name = ? AND grantee = USER',
                [$table]
            );
            $privs = empty($rows) ? 'nenhum (só leitura via role, ou sem acesso)' : implode(', ', array_map(fn ($r) => $r->privilege, $rows));
            $this->line(sprintf('  %-28s %s', $table, $privs));
        }
        $this->line('  (Se não aparecer INSERT/UPDATE, a permissão de escrita precisa ser concedida pelo DBA.)');
    }

    private function requiredColumns(): void
    {
        foreach (self::ANSWER_TABLES as $table) {
            $rows = $this->safeSelect(
                "SELECT column_name, data_type FROM all_tab_columns
                 WHERE owner = 'TASY' AND table_name = ? AND nullable = 'N'
                 ORDER BY column_id",
                [$table]
            );
            $cols = empty($rows) ? '—' : implode(', ', array_map(fn ($r) => $r->column_name, $rows));
            $this->line("  {$table}:");
            $this->line("    obrigatórias: {$cols}");
        }
    }

    private function triggers(): void
    {
        $placeholders = implode(',', array_fill(0, count(self::ANSWER_TABLES), '?'));
        $rows = $this->safeSelect(
            "SELECT table_name, trigger_name, triggering_event, status
             FROM all_triggers
             WHERE owner = 'TASY' AND table_name IN ({$placeholders})
             ORDER BY table_name, trigger_name",
            self::ANSWER_TABLES
        );

        if (empty($rows)) {
            $this->line('  Nenhum trigger (ou sem privilégio para listar).');

            return;
        }

        foreach ($rows as $r) {
            $this->line("  {$r->table_name}: {$r->trigger_name} [{$r->triggering_event}] ({$r->status})");
        }
    }

    /**
     * @return array<int, object>
     */
    private function safeSelect(string $sql, array $bindings = []): array
    {
        try {
            return DB::connection('tasy')->select($sql, $bindings);
        } catch (\Throwable $e) {
            $this->error('  Erro: '.$e->getMessage());

            return [];
        }
    }

    private function safeSelectOne(string $sql, array $bindings = []): ?object
    {
        try {
            return DB::connection('tasy')->selectOne($sql, $bindings);
        } catch (\Throwable $e) {
            $this->error('  Erro: '.$e->getMessage());

            return null;
        }
    }
}
