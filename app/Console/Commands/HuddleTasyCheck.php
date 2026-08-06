<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico da conexão com o Tasy para o Huddle.
 *
 * Mostra em qual banco o projeto está conectado (e se é primário ou réplica
 * Data Guard) e verifica se o questionário do Huddle (ex.: "Teste avaliação")
 * já está disponível para leitura. Útil para saber, sem abrir o tinker, quando
 * o time do Tasy publicar o formulário no ambiente que o Huddle lê.
 */
class HuddleTasyCheck extends Command
{
    protected $signature = 'huddle:tasy-check
                            {nome=AVALIA : Trecho do nome do questionário a procurar (sem acento, maiúsculo)}';

    protected $description = 'Diagnostica a conexão Tasy e verifica se o questionário do Huddle já existe';

    public function handle(): int
    {
        $nome = strtoupper((string) $this->argument('nome'));

        $this->info('== Conexão do projeto com o Tasy ==');
        $this->identidade();

        $this->newLine();
        $this->info('== Disponibilidade do questionário ==');
        $total = $this->contarQuestionarios($nome);

        $this->newLine();
        $this->info('== Outros módulos de questionário (contagem) ==');
        $this->contarAlternativas();

        $this->newLine();
        if ($total > 0) {
            $this->info("✔ Encontrado questionário contendo \"{$nome}\" — pronto para iniciar a leitura no Huddle.");

            return self::SUCCESS;
        }

        $this->warn("• Nenhum questionário \"{$nome}\" no banco que o Huddle lê. O time do Tasy ainda não publicou o formulário neste ambiente.");

        return self::SUCCESS;
    }

    /**
     * Identidade do banco: serviço, servidor e papel (primário x Data Guard).
     */
    private function identidade(): void
    {
        try {
            $env = DB::connection('tasy')->selectOne(
                "SELECT sys_context('USERENV','SERVICE_NAME') AS servico,
                        sys_context('USERENV','SERVER_HOST')  AS servidor,
                        sys_context('USERENV','DB_NAME')      AS banco
                 FROM dual"
            );

            $this->line("  Serviço : {$env->servico}");
            $this->line("  Servidor: {$env->servidor}");
            $this->line("  Banco   : {$env->banco}");
        } catch (\Throwable $e) {
            $this->error('  Falha ao ler USERENV: '.$e->getMessage());
        }

        try {
            $role = DB::connection('tasy')->selectOne(
                'SELECT database_role, open_mode FROM v$database'
            );

            $this->line("  Papel   : {$role->database_role} ({$role->open_mode})");

            if (str_contains(strtoupper((string) $role->database_role), 'STANDBY')) {
                $this->warn('  → É réplica Data Guard: só LEITURA. A gravação (write-back) precisa apontar para o primário.');
            }
        } catch (\Throwable $e) {
            $this->line('  Papel   : indisponível (sem privilégio em v$database) — confirmar com o DBA se é primário ou Data Guard.');
        }
    }

    /**
     * Conta e lista questionários do módulo QUE_ cujo nome contém o trecho informado.
     */
    private function contarQuestionarios(string $nome): int
    {
        try {
            $rows = DB::connection('tasy')->select(
                'SELECT nr_sequencia, ds_questionario, ie_situacao
                 FROM tasy.que_questionario
                 WHERE UPPER(ds_questionario) LIKE ?
                 ORDER BY nr_sequencia DESC',
                ['%'.$nome.'%']
            );

            if (empty($rows)) {
                $this->line("  QUE_QUESTIONARIO: nenhum com \"{$nome}\".");

                return 0;
            }

            foreach ($rows as $row) {
                $this->line("  [{$row->nr_sequencia}] {$row->ds_questionario} (situação: {$row->ie_situacao})");
            }

            return count($rows);
        } catch (\Throwable $e) {
            $this->error('  Falha ao consultar QUE_QUESTIONARIO: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Contagem geral dos módulos candidatos, para saber onde há dados.
     */
    private function contarAlternativas(): void
    {
        $tabelas = [
            'tasy.que_questionario',
            'tasy.que_questao',
            'tasy.que_atendimento',
            'tasy.atend_questionario',
            'tasy.questionario_paciente',
        ];

        foreach ($tabelas as $tabela) {
            try {
                $r = DB::connection('tasy')->selectOne("SELECT COUNT(*) AS total FROM {$tabela}");
                $this->line(sprintf('  %-32s %s linha(s)', $tabela, $r->total));
            } catch (\Throwable $e) {
                $this->line(sprintf('  %-32s erro: %s', $tabela, $e->getMessage()));
            }
        }
    }
}
