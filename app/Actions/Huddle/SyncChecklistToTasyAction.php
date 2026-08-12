<?php

namespace App\Actions\Huddle;

use App\Enums\Huddle\HuddleChecklistItem;
use App\Enums\Huddle\TasyEvaluationItemMap;
use App\Models\Huddle\HuddlePatientDay;
use App\Repositories\EMR\TasyEvaluationRepository;
use App\Services\Tasy\TasyEvaluationService;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza as respostas do checklist do Huddle (MySQL) para o Tasy (Oracle).
 *
 * Chamada após o preenchimento completo do checklist no modal do paciente.
 * O dual-write garante que:
 *   1. MySQL é a fonte primária (sempre salva, mesmo se Oracle falhar)
 *   2. Oracle recebe uma cópia para integração com o prontuário Tasy
 *
 * Se a escrita no Oracle falhar, o erro é logado mas NÃO interrompe o fluxo
 * do usuário — a avaliação já está salva no MySQL. Um Job de retry pode ser
 * implementado futuramente para reprocessar falhas.
 */
class SyncChecklistToTasyAction
{
    public function __construct(
        private readonly TasyEvaluationRepository $repository,
        private readonly TasyEvaluationService $service,
    ) {}

    /**
     * @param  HuddlePatientDay  $day       Dia do Huddle com checklistAnswers carregado
     * @param  string            $cdPessoaFisica  Código da pessoa física do paciente (Tasy)
     * @param  string            $nmUsuario       Login do usuário que preencheu (max 15 chars)
     * @param  int|null          $cdSetorAtendimento  Código do setor (opcional)
     * @return int|null  nr_sequencia da avaliação criada no Tasy, ou null se falhou
     */
    public function execute(
        HuddlePatientDay $day,
        string $cdPessoaFisica,
        string $nmUsuario,
        ?int $cdSetorAtendimento = null,
    ): ?int {
        $day->loadMissing('checklistAnswers');

        if ($day->checklistAnswers->isEmpty()) {
            return null;
        }

        // Monta o payload no formato Tasy a partir das respostas do MySQL
        $checklistAnswers = [];
        foreach ($day->checklistAnswers as $answer) {
            $itemCode = is_string($answer->item_code) ? $answer->item_code : $answer->item_code->value;
            $checklistAnswers[$itemCode] = [
                'answer' => $answer->answer,
                'notes' => $answer->notes,
            ];
        }

        $triageAnswer = $day->status; // 'huddle' | 'round'
        $payload = $this->service->buildTasyPayload($checklistAnswers, $triageAnswer);

        // Inclui as recomendações (campos de texto livre) no payload
        $this->appendRecommendations($payload, $checklistAnswers);

        if (empty($payload)) {
            return null;
        }

        try {
            $nrSequencia = $this->repository->saveEvaluation(
                attendanceNumber: $day->nr_atendimento,
                cdPessoaFisica: $cdPessoaFisica,
                nmUsuario: mb_substr($nmUsuario, 0, 15),
                answers: $payload,
                cdSetorAtendimento: $cdSetorAtendimento,
            );

            // Limpa cache para que a próxima leitura reflita a gravação
            $this->service->clearPatientCache($day->nr_atendimento);

            Log::info('[SyncChecklistToTasy] Avaliação sincronizada', [
                'nr_atendimento' => $day->nr_atendimento,
                'nr_sequencia_tasy' => $nrSequencia,
                'huddle_date' => $day->huddle_date,
                'items_count' => count($payload),
            ]);

            return $nrSequencia;
        } catch (\Throwable $e) {
            Log::error('[SyncChecklistToTasy] Falha ao sincronizar com Tasy', [
                'nr_atendimento' => $day->nr_atendimento,
                'huddle_date' => $day->huddle_date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Mapeia os campos de notes do checklist para os itens de "Recomendação" do Tasy.
     *
     * Mapeamento completo (dropdown → recomendação):
     *   CriteriosClinicos (122014) → RecCriteriosClinicos (122027)
     *   ExamesLaudo (122023)       → RecExamesLaudo (122028)
     *   Procedimentos (122024)     → RecProcedimentos (122018)
     *   Terapias (122025)          → RecTerapias (122019)
     *   Consultorias (122015)      → RecConsultorias (122020)
     *   OrientacaoAlta (122026)    → RecOrientacaoAlta (122021)
     *   Transporte (122016)        → RecTransporte (122022)
     */
    private function appendRecommendations(array &$payload, array $checklistAnswers): void
    {
        foreach ($checklistAnswers as $itemCode => $answer) {
            $checklistItem = HuddleChecklistItem::tryFrom($itemCode);
            if (! $checklistItem) {
                continue;
            }

            $recItem = TasyEvaluationItemMap::recommendationForChecklist($checklistItem);
            if (! $recItem) {
                continue;
            }

            $notes = $answer['notes'] ?? null;
            if ($notes !== null && trim($notes) !== '') {
                $payload[$recItem->value] = [
                    'ds_resultado' => mb_substr(trim($notes), 0, 4000),
                    'qt_resultado' => null,
                ];
            }
        }
    }
}
