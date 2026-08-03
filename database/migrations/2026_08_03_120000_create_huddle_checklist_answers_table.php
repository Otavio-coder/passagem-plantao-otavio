<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Respostas do checklist do Huddle (uma por pergunta, por dia de huddle) e o
     * status do gate de previsão de alta em 72h (huddle = entra no checklist;
     * round = discutir em round, sem checklist).
     */
    public function up(): void
    {
        Schema::create('huddle_checklist_answers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('huddle_patient_day_id')
                ->constrained('huddle_patient_days')
                ->cascadeOnDelete();

            $table->string('item_code', 40);          // App\Enums\Huddle\HuddleChecklistItem
            $table->string('answer', 10)->nullable(); // 'sim' | 'nao'
            $table->string('signal', 10)->nullable(); // App\Enums\Huddle\DayColor ('red' | 'green')

            $table->string('responsible')->nullable(); // responsável pela pendência (item Red)
            $table->date('due_at')->nullable();         // prazo de devolutiva
            $table->text('notes')->nullable();

            $table->foreignId('answered_by_user_id')->nullable()->constrained('users');

            $table->timestamps();

            // Uma resposta por pergunta por dia de huddle
            $table->unique(['huddle_patient_day_id', 'item_code']);
        });

        // Gate 72h: define se o paciente entra no Huddle ou vai para o Round.
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->string('status', 10)->nullable()->after('color'); // 'huddle' | 'round'
        });
    }

    public function down(): void
    {
        Schema::table('huddle_patient_days', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::dropIfExists('huddle_checklist_answers');
    }
};
