<?php

namespace App\Console\Commands;

use App\Models\Huddle\HuddleTasyQuestion;
use App\Models\Huddle\HuddleTasyQuestionOption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Espelha a definição do questionário do Huddle (perguntas + opções) do Tasy
 * (módulo QUE_) para as tabelas locais huddle_tasy_questions / _options.
 *
 * Idempotente: usa updateOrCreate pela chave estável tasy_seq (NR_SEQUENCIA do Tasy),
 * então rodar de novo atualiza sem duplicar. As RESPOSTAS não passam por aqui —
 * são gravadas de volta no Tasy (write-back).
 */
class SyncHuddleQuestions extends Command
{
    protected $signature = 'huddle:sync-questions
                            {questionnaire? : NR_SEQUENCIA do QUE_QUESTIONARIO. Padrão: config huddle.questionnaire_seq}';

    protected $description = 'Espelha as perguntas/opções do questionário do Huddle do Tasy para o banco local';

    public function handle(): int
    {
        $questionnaireSeq = (int) ($this->argument('questionnaire') ?: config('huddle.questionnaire_seq'));

        if ($questionnaireSeq <= 0) {
            $this->error('Nenhum questionário informado. Passe o NR_SEQUENCIA como argumento ou defina HUDDLE_QUESTIONNAIRE_SEQ no .env.');

            return self::FAILURE;
        }

        $this->info("Sincronizando o questionário {$questionnaireSeq} do Tasy...");

        try {
            $questions = DB::connection('tasy')->select(
                'SELECT nr_sequencia, ds_questao, ie_tipo_resposta, nr_seq_apresentacao, ie_situacao
                 FROM tasy.que_questao
                 WHERE nr_seq_questionario = ?
                 ORDER BY nr_seq_apresentacao',
                [$questionnaireSeq]
            );
        } catch (\Throwable $e) {
            $this->error('Falha ao ler as perguntas no Tasy: '.$e->getMessage());

            return self::FAILURE;
        }

        if (empty($questions)) {
            $this->warn("Nenhuma pergunta encontrada para o questionário {$questionnaireSeq}.");
            $this->line('Confirme o NR_SEQUENCIA e se o formulário está publicado no ambiente que o sistema lê.');

            return self::SUCCESS;
        }

        foreach ($questions as $q) {
            HuddleTasyQuestion::updateOrCreate(
                ['tasy_seq' => (int) $q->nr_sequencia],
                [
                    'questionnaire_seq' => $questionnaireSeq,
                    'label' => (string) $q->ds_questao,
                    'answer_type' => $q->ie_tipo_resposta,
                    'display_order' => $q->nr_seq_apresentacao !== null ? (int) $q->nr_seq_apresentacao : null,
                    'active' => ($q->ie_situacao ?? 'A') === 'A',
                ]
            );
        }

        $questionSeqs = array_map(fn ($q) => (int) $q->nr_sequencia, $questions);
        $optionCount = $this->syncOptions($questionSeqs);

        $this->info('Concluído: '.count($questions).' pergunta(s) e '.$optionCount.' opção(ões) sincronizadas.');

        return self::SUCCESS;
    }

    /**
     * @param  int[]  $questionSeqs
     */
    private function syncOptions(array $questionSeqs): int
    {
        if (empty($questionSeqs)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($questionSeqs), '?'));

        try {
            $options = DB::connection('tasy')->select(
                "SELECT nr_sequencia, nr_seq_questao, ds_opcao, nr_seq_apres, ie_situacao
                 FROM tasy.que_questao_opcao
                 WHERE nr_seq_questao IN ({$placeholders})
                 ORDER BY nr_seq_questao, nr_seq_apres",
                $questionSeqs
            );
        } catch (\Throwable $e) {
            $this->error('Falha ao ler as opções no Tasy: '.$e->getMessage());

            return 0;
        }

        foreach ($options as $o) {
            HuddleTasyQuestionOption::updateOrCreate(
                ['tasy_seq' => (int) $o->nr_sequencia],
                [
                    'question_tasy_seq' => (int) $o->nr_seq_questao,
                    'label' => (string) $o->ds_opcao,
                    'display_order' => $o->nr_seq_apres !== null ? (int) $o->nr_seq_apres : null,
                    'active' => ($o->ie_situacao ?? 'A') === 'A',
                ]
            );
        }

        return count($options);
    }
}
