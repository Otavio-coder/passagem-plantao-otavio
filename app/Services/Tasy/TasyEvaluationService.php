<?php

namespace App\Services\Tasy;

use App\Enums\Huddle\HuddleChecklistItem;
use App\Enums\Huddle\TasyEvaluationItemMap;
use App\Repositories\EMR\TasyEvaluationRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de avaliações do Tasy — orquestra leitura de perguntas/opções e
 * converte entre o formato Oracle e o formato local do Huddle.
 *
 * Fluxo de dados:
 *   LEITURA:  Tasy Oracle → Cache Redis → Livewire component
 *   ESCRITA:  Livewire → MySQL (primário) → Tasy Oracle (via SyncChecklistToTasyAction)
 *
 * As perguntas são cacheadas por 24h (raramente mudam). Avaliações de paciente
 * usam cache de 10 min (mesmo padrão do TasyService).
 */
class TasyEvaluationService
{
    private const ITEMS_CACHE_TTL = 86400;    // 24h — estrutura do formulário
    private const EVAL_CACHE_TTL = 600;       // 10 min — avaliação do paciente
    private const ITEMS_CACHE_VERSION = 1;

    public function __construct(
        private readonly TasyEvaluationRepository $repository,
    ) {}

    // ─── ESTRUTURA DO FORMULÁRIO ──────────────────────────────────────────

    /**
     * Retorna os itens do formulário Huddle do Tasy, organizados para renderização.
     *
     * Estrutura retornada:
     * [
     *   [
     *     'nr_sequencia'   => 122014,
     *     'ds_item'        => 'Critérios Clínicos estipulados para a alta atendidos?',
     *     'type'           => 'dropdown',        // 'dropdown' | 'text'
     *     'is_triage_gate' => false,
     *     'checklist_code' => 'criterios_clinicos', // HuddleChecklistItem->value ou null
     *     'options'        => [['value' => 1, 'label' => 'Sim'], ['value' => 2, 'label' => 'Não']],
     *   ],
     *   ...
     * ]
     *
     * @return array<int, array>
     */
    public function getFormItems(): array
    {
        $cacheKey = 'tasy_eval_items_v' . self::ITEMS_CACHE_VERSION . '_' . TasyEvaluationItemMap::TIPO_AVALIACAO;

        return Cache::remember($cacheKey, self::ITEMS_CACHE_TTL, function () {
            try {
                $items = $this->repository->getEvaluationItems();

                return array_map(function ($item) {
                    $mapped = TasyEvaluationItemMap::tryFrom($item->nr_sequencia);

                    return [
                        'nr_sequencia' => $item->nr_sequencia,
                        'ds_item' => $item->ds_item,
                        'type' => empty($item->options) ? 'text' : 'dropdown',
                        'is_triage_gate' => $mapped?->isTriageGate() ?? false,
                        'checklist_code' => $mapped?->checklistItem()?->value,
                        'options' => array_map(fn ($opt) => [
                            'value' => $opt->nr_seq_res,
                            'label' => trim($opt->ds_resultado),
                        ], $item->options),
                        'ie_obrigatorio' => $item->ie_obrigatorio === 'S',
                    ];
                }, $items);
            } catch (\Throwable $e) {
                Log::warning('[TasyEvaluation] Falha ao buscar itens do formulário, usando fallback do enum', [
                    'error' => $e->getMessage(),
                ]);

                return $this->getFallbackItems();
            }
        });
    }

    /**
     * Retorna apenas os itens de checklist (exclui gate de triagem e recomendações).
     *
     * @return array<int, array>
     */
    public function getChecklistItems(): array
    {
        return array_values(
            array_filter($this->getFormItems(), fn ($item) => $item['checklist_code'] !== null && ! $item['is_triage_gate'])
        );
    }

    /**
     * Retorna mapa de labels dos itens de checklist vindos do Tasy.
     * Usado pela view para exibir o texto da pergunta do Oracle em vez do hardcoded.
     *
     * @return array<string, string>  ['criterios_clinicos' => 'Critérios Clínicos estipulados...', ...]
     */
    public function getChecklistLabels(): array
    {
        $items = $this->getChecklistItems();
        $labels = [];

        foreach ($items as $item) {
            if (! empty($item['checklist_code'])) {
                $labels[$item['checklist_code']] = $item['ds_item'];
            }
        }

        return $labels;
    }

    // ─── AVALIAÇÕES DO PACIENTE ───────────────────────────────────────────

    /**
     * Busca a última avaliação do Huddle de um paciente no Tasy e converte
     * para o formato usado pelo Livewire (checklist indexado por item_code).
     *
     * @return array{triage: ?string, checklist: array<string, array>, tasy_evaluation: ?object}
     */
    public function getPatientEvaluation(int $attendanceNumber): array
    {
        $cacheKey = "tasy_eval_patient_{$attendanceNumber}";

        return Cache::remember($cacheKey, self::EVAL_CACHE_TTL, function () use ($attendanceNumber) {
            $result = [
                'triage' => null,
                'checklist' => [],
                'tasy_evaluation' => null,
            ];

            try {
                $evaluation = $this->repository->getLatestEvaluation($attendanceNumber);

                if (! $evaluation) {
                    return $result;
                }

                $result['tasy_evaluation'] = (object) [
                    'nr_sequencia' => $evaluation->nr_sequencia,
                    'dt_avaliacao' => $evaluation->dt_avaliacao,
                    'nm_usuario' => $evaluation->nm_usuario,
                ];

                foreach ($evaluation->answers as $nrSeqItem => $answer) {
                    $mapped = TasyEvaluationItemMap::tryFrom($nrSeqItem);
                    if (! $mapped) {
                        continue;
                    }

                    // Gate de triagem
                    if ($mapped->isTriageGate()) {
                        $result['triage'] = $this->tasyAnswerToTriageStatus($answer->ds_resultado);
                        continue;
                    }

                    // Item de checklist
                    $checklistItem = $mapped->checklistItem();
                    if ($checklistItem) {
                        $result['checklist'][$checklistItem->value] = [
                            'answer' => $this->normalizeTasyAnswer($answer->ds_resultado),
                            'signal' => $this->deriveSignal($checklistItem, $answer->ds_resultado),
                        ];
                    }
                }

                return $result;
            } catch (\Throwable $e) {
                Log::warning('[TasyEvaluation] Falha ao buscar avaliação do paciente', [
                    'nr_atendimento' => $attendanceNumber,
                    'error' => $e->getMessage(),
                ]);

                return $result;
            }
        });
    }

    /**
     * Histórico de avaliações do Huddle no Tasy para um paciente.
     *
     * @return array<int, object>
     */
    public function getPatientHistory(int $attendanceNumber, int $limit = 10): array
    {
        try {
            return $this->repository->getEvaluationHistory($attendanceNumber, $limit);
        } catch (\Throwable $e) {
            Log::warning('[TasyEvaluation] Falha ao buscar histórico', [
                'nr_atendimento' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    // ─── ESCRITA: PREPARAR PAYLOAD PARA O TASY ───────────────────────────

    /**
     * Converte as respostas do checklist local (MySQL) para o formato de INSERT
     * no Tasy. Chamado quando a escrita Oracle for habilitada.
     *
     * @param  array<string, array{answer: string, notes: ?string}>  $checklistAnswers
     * @return array<int, array{ds_resultado: string, qt_resultado: ?int}>
     */
    public function buildTasyPayload(array $checklistAnswers, ?string $triageAnswer = null): array
    {
        $payload = [];

        // Gate de triagem (122013)
        if ($triageAnswer !== null) {
            $payload[TasyEvaluationItemMap::Previsao72h->value] = [
                'ds_resultado' => $triageAnswer === 'huddle' ? 'Sim' : 'Não',
                'qt_resultado' => $triageAnswer === 'huddle' ? 1 : 2,
            ];
        }

        // Itens do checklist
        foreach ($checklistAnswers as $itemCode => $answer) {
            $checklistItem = HuddleChecklistItem::tryFrom($itemCode);
            if (! $checklistItem) {
                continue;
            }

            $tasyItem = TasyEvaluationItemMap::fromChecklistItem($checklistItem);

            $payload[$tasyItem->value] = [
                'ds_resultado' => $answer['answer'] === 'sim' ? 'Sim' : 'Não',
                'qt_resultado' => $answer['answer'] === 'sim' ? 1 : 2,
            ];
        }

        return $payload;
    }

    /**
     * Limpa o cache de avaliação de um paciente após nova gravação.
     */
    public function clearPatientCache(int $attendanceNumber): void
    {
        Cache::forget("tasy_eval_patient_{$attendanceNumber}");
    }

    // ─── MÉTODOS PRIVADOS ─────────────────────────────────────────────────

    /**
     * Normaliza resposta do Tasy ('Sim'/'Não') para o formato local ('sim'/'nao').
     */
    private function normalizeTasyAnswer(?string $tasyAnswer): ?string
    {
        if ($tasyAnswer === null) {
            return null;
        }

        return match (mb_strtolower(trim($tasyAnswer))) {
            'sim' => 'sim',
            'não', 'nao' => 'nao',
            default => null,
        };
    }

    /**
     * Converte 'Sim' do Tasy no gate de triagem para o status local.
     */
    private function tasyAnswerToTriageStatus(?string $tasyAnswer): ?string
    {
        return match (mb_strtolower(trim($tasyAnswer ?? ''))) {
            'sim' => 'huddle',
            'não', 'nao' => 'round',
            default => null,
        };
    }

    /**
     * Deriva o sinal Red/Green a partir da resposta do Tasy e da polaridade do item.
     */
    private function deriveSignal(HuddleChecklistItem $item, ?string $tasyAnswer): ?string
    {
        $normalized = $this->normalizeTasyAnswer($tasyAnswer);
        if ($normalized === null) {
            return null;
        }

        return $normalized === $item->greenAnswer() ? 'green' : 'red';
    }

    /**
     * Fallback: gera itens a partir do enum local quando o Oracle está inacessível.
     */
    private function getFallbackItems(): array
    {
        $items = [];

        // Gate de triagem
        $items[] = [
            'nr_sequencia' => TasyEvaluationItemMap::Previsao72h->value,
            'ds_item' => 'Paciente com previsão de altas nas próximas 72 horas?',
            'type' => 'dropdown',
            'is_triage_gate' => true,
            'checklist_code' => null,
            'options' => [
                ['value' => 1, 'label' => 'Sim'],
                ['value' => 2, 'label' => 'Não'],
            ],
            'ie_obrigatorio' => false,
        ];

        // Checklist items
        foreach (HuddleChecklistItem::cases() as $checklistItem) {
            $tasyItem = TasyEvaluationItemMap::fromChecklistItem($checklistItem);

            $items[] = [
                'nr_sequencia' => $tasyItem->value,
                'ds_item' => $checklistItem->label(),
                'type' => 'dropdown',
                'is_triage_gate' => false,
                'checklist_code' => $checklistItem->value,
                'options' => [
                    ['value' => 1, 'label' => 'Sim'],
                    ['value' => 2, 'label' => 'Não'],
                ],
                'ie_obrigatorio' => false,
            ];
        }

        return $items;
    }
}
