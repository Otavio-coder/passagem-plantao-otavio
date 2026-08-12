<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perguntas dinâmicas do Round Unidade (Huddle de Segurança por unidade).
     *
     * Armazena a definição de cada pergunta/campo do formulário, organizadas por eixo.
     * Substitui o hardcoded na view — a aplicação lê daqui e renderiza dinâmicamente.
     *
     * Cada pergunta tem um tipo (number, boolean, select, text) que define o widget
     * na tela, e um 'field_key' único que mapeia para a coluna na tabela
     * huddle_safety_assessments onde a resposta é salva.
     */
    public function up(): void
    {
        Schema::create('huddle_unit_questions', function (Blueprint $table): void {
            $table->id();

            // Eixo (agrupamento): 1 = Ocupação e fluxo, 2 = Risco clínico, 3 = Condições operacionais, 4 = Classificação
            $table->unsignedTinyInteger('axis')->comment('Eixo (1-4)');
            $table->string('axis_label', 100)->comment('Título do eixo, ex: Ocupação e fluxo');

            // Identificação da pergunta
            $table->string('field_key', 60)->unique()->comment('Chave que mapeia para a coluna em huddle_safety_assessments');
            $table->string('label', 255)->comment('Texto da pergunta exibido na tela');

            // Tipo de campo: number, boolean, select, text
            $table->string('field_type', 20)->default('number')->comment('Tipo do widget: number, boolean, select, text');

            // Para campos tipo 'select': opções em JSON, ex: [{"value":"verde","label":"Verde"}, ...]
            $table->json('options')->nullable()->comment('Opções para campo select, em JSON');

            // Ordenação dentro do eixo
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Ativo/inativo: permite desabilitar perguntas sem deletar
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['axis', 'sort_order', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('huddle_unit_questions');
    }
};
