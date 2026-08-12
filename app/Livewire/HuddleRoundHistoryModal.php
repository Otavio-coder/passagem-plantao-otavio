<?php

namespace App\Livewire;

use App\Models\Huddle\HuddleSafetyAssessment;
use App\Models\Huddle\HuddleUnitQuestion;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de histórico de Rounds Unidade preenchidos para um setor.
 *
 * Exibe todos os registros de huddle_safety_assessments do setor,
 * ordenados do mais recente para o mais antigo, com os valores
 * preenchidos e quem/quando preencheu.
 */
class HuddleRoundHistoryModal extends Component
{
    public bool $showModal = false;

    public int $sectorId = 0;

    public string $sectorLabel = '';

    /** @var array<int, array<string, mixed>> */
    public array $history = [];

    #[On('openRoundHistory')]
    public function open(int $sectorId, string $sectorLabel = ''): void
    {
        $this->sectorId = $sectorId;
        $this->sectorLabel = $sectorLabel;

        $this->loadHistory();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset(['showModal', 'sectorId', 'sectorLabel', 'history']);
    }

    public function render()
    {
        return view('huddle.round-history-modal.index');
    }

    private function loadHistory(): void
    {
        $records = HuddleSafetyAssessment::query()
            ->with(['createdBy', 'updatedBy'])
            ->forSector($this->sectorId)
            ->orderByDesc('huddle_date')
            ->limit(30)
            ->get();

        $questions = $this->getQuestions();

        $this->history = $records->map(function ($record) use ($questions) {
            $answers = [];

            foreach ($questions as $q) {
                $value = $record->{$q['field_key']};

                if (is_object($value) && property_exists($value, 'value')) {
                    $value = $value->value;
                }

                if ($value === true) {
                    $value = 'Sim';
                } elseif ($value === false) {
                    $value = 'Não';
                }

                $answers[] = [
                    'label' => $q['label'],
                    'axis' => $q['axis'],
                    'value' => $value,
                ];
            }

            $auditUser = $record->updatedBy ?? $record->createdBy;

            return [
                'date' => $record->huddle_date->format('d/m/Y'),
                'filled_by' => $auditUser?->username ?? '—',
                'filled_at' => ($record->updated_at ?? $record->created_at)?->format('d/m/Y H:i'),
                'answers' => $answers,
            ];
        })->toArray();
    }

    /**
     * @return array<int, array{field_key: string, label: string, axis: string}>
     */
    private function getQuestions(): array
    {
        return Cache::remember('huddle_unit_questions_flat', 86400, function () {
            return HuddleUnitQuestion::active()
                ->ordered()
                ->get(['field_key', 'label', 'axis'])
                ->toArray();
        });
    }
}
