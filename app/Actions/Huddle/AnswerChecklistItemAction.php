<?php

namespace App\Actions\Huddle;

use App\Enums\Huddle\DayColor;
use App\Enums\Huddle\HuddleChecklistItem;
use App\Models\Huddle\HuddleChecklistAnswer;
use App\Models\Huddle\HuddlePatientDay;
use Illuminate\Support\Facades\Log;

/**
 * Grava (ou atualiza) a resposta de um item do checklist e recalcula a cor do dia.
 *
 * A cor do HuddlePatientDay é sempre derivada do conjunto de respostas
 * (Red se qualquer item estiver Red), nunca definida solta — assim a cor do card
 * reflete fielmente o checklist.
 *
 * Quando todos os itens estiverem respondidos, dispara a sincronização para o Tasy
 * (Oracle) via SyncChecklistToTasyAction. A sync é fire-and-forget — se falhar,
 * o erro é logado mas não bloqueia o fluxo.
 */
class AnswerChecklistItemAction
{
    public function execute(
        HuddlePatientDay $day,
        HuddleChecklistItem $item,
        string $answer,
        DayColor $signal,
        int $userId,
        ?string $responsible = null,
        ?string $dueAt = null,
        ?string $notes = null,
        ?string $cdPessoaFisica = null,
        ?string $nmUsuario = null,
        ?int $cdSetorAtendimento = null,
    ): HuddleChecklistAnswer {
        $record = HuddleChecklistAnswer::updateOrCreate(
            [
                'huddle_patient_day_id' => $day->id,
                'item_code' => $item->value,
            ],
            [
                'answer' => $answer,
                'signal' => $signal,
                'responsible' => $responsible ?: null,
                'due_at' => $dueAt ?: null,
                'notes' => $notes ?: null,
                'answered_by_user_id' => $userId,
            ]
        );

        $day->load('checklistAnswers');
        $day->update([
            'color' => $day->deriveColorFromChecklist(),
            'updated_by_user_id' => $userId,
        ]);

        // Sincroniza com Tasy quando todos os itens estiverem respondidos
        $isComplete = $this->isChecklistComplete($day);

        if (! $cdPessoaFisica && $isComplete) {
            Log::warning('[AnswerChecklistItem] Sync Tasy ignorada: cd_pessoa_fisica é null', [
                'nr_atendimento' => $day->nr_atendimento,
                'item' => $item->value,
                'answered_count' => $day->checklistAnswers->count(),
            ]);
        }

        if ($cdPessoaFisica && $isComplete) {
            $this->syncToTasy($day, $cdPessoaFisica, $nmUsuario ?? 'PASSAGEM_PLANTAO', $cdSetorAtendimento);
        }

        return $record;
    }

    /**
     * Verifica se todos os 7 itens do checklist foram respondidos.
     */
    private function isChecklistComplete(HuddlePatientDay $day): bool
    {
        $totalItems = count(HuddleChecklistItem::cases());
        $answeredItems = $day->checklistAnswers->count();

        return $answeredItems >= $totalItems;
    }

    /**
     * Dispara sincronização para o Tasy — fire-and-forget.
     */
    private function syncToTasy(
        HuddlePatientDay $day,
        string $cdPessoaFisica,
        string $nmUsuario,
        ?int $cdSetorAtendimento,
    ): void {
        try {
            Log::info('[AnswerChecklistItem] Sync Tasy disparada', [
                'nr_atendimento' => $day->nr_atendimento,
                'cd_pessoa_fisica' => $cdPessoaFisica,
                'nm_usuario' => $nmUsuario,
            ]);

            app(SyncChecklistToTasyAction::class)->execute(
                day: $day,
                cdPessoaFisica: $cdPessoaFisica,
                nmUsuario: $nmUsuario,
                cdSetorAtendimento: $cdSetorAtendimento,
            );

            Log::info('[AnswerChecklistItem] Sync Tasy concluída com sucesso', [
                'nr_atendimento' => $day->nr_atendimento,
            ]);
        } catch (\Throwable $e) {
            // Nunca bloqueia o fluxo do MySQL — só loga
            Log::error('[AnswerChecklistItem] Sync Tasy falhou (não-bloqueante)', [
                'nr_atendimento' => $day->nr_atendimento,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
