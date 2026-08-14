<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Espelho local (MySQL) do questionário do Huddle que vive no Tasy (módulo QUE_).
     *
     * A rotina huddle:sync-questions copia periodicamente a DEFINIÇÃO do questionário
     * (perguntas + opções) do Tasy para cá, para a tela ler rápido e sem depender do
     * Oracle a cada acesso. É apenas a definição — as respostas dos pacientes são
     * gravadas de volta no Tasy (write-back), fora destas tabelas.
     */
    public function up(): void
    {
        Schema::create('huddle_tasy_questions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('questionnaire_seq');       // QUE_QUESTIONARIO.NR_SEQUENCIA
            $table->unsignedBigInteger('tasy_seq')->unique();      // QUE_QUESTAO.NR_SEQUENCIA (o item)
            $table->text('label');                                 // DS_QUESTAO (texto da pergunta)
            $table->string('answer_type', 20)->nullable();         // IE_TIPO_RESPOSTA
            $table->integer('display_order')->nullable();          // NR_SEQ_APRESENTACAO
            $table->string('green_answer', 20)->nullable();        // polaridade Red/Green (definida depois)
            $table->boolean('active')->default(true);              // IE_SITUACAO = 'A'
            $table->timestamps();

            $table->index(['questionnaire_seq', 'display_order']);
        });

        Schema::create('huddle_tasy_question_options', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tasy_seq')->unique();      // QUE_QUESTAO_OPCAO.NR_SEQUENCIA
            $table->unsignedBigInteger('question_tasy_seq');       // NR_SEQ_QUESTAO (liga à pergunta)
            $table->text('label');                                 // DS_OPCAO (texto da opção)
            $table->integer('display_order')->nullable();          // NR_SEQ_APRES
            $table->string('color', 20)->nullable();               // cor Red/Green, quando disponível
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('question_tasy_seq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_tasy_question_options');
        Schema::dropIfExists('huddle_tasy_questions');
    }
};
