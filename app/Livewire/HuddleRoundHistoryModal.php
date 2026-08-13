<?php

namespace App\Livewire;

use App\Models\Huddle\HuddleSafetyAssessment;
use App\Models\Huddle\HuddleUnitQuestion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de histórico de Rounds Unidade, dividido por unidade (setor).
 *
 * Carrega os registros de huddle_safety_assessments de todas as unidades
 * que o usuário tem acesso, agrupados por setor e ordenados por data.
 */
class HuddleRoundHistoryModal extends Component
{
    public bool $showModal = false;

    /** @var array<string, array<int, array<string, mixed>>> setor_label => [records] */
    public array $historyByUnit = [];

    #[On('openRoundHistory')]
    public function open(int $sectorId = 0, string $sectorLabel = ''): void
    {
        $this->loadHistory();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset(['showModal', 'historyByUnit']);
    }

    public function render()
    {
        return view('huddle.round-history-modal.index');
    }

    private function loadHistory(): void
    {
        $user = Auth::user();

        // Setores do usuário: código → nome
        $sectorMap = $user->sectorPreferences()
            ->get(['sector_code', 'sector_name'])
            ->pluck('sector_name', 'sector_code')
            ->mapWithKeys(fn ($name, $code) => [(int) $code => $name])
            ->all();

        $sectorIds = array_keys($sectorMap);

        if (empty($sectorIds)) {
            $this->historyByUnit = [];

            return;
        }

        $records = HuddleSafetyAssessment::query()
            ->with(['createdBy', 'updatedBy'])
            ->whereIn('sector_id', $sectorIds)
            ->orderByDesc('huddle_date')
            ->limit(100)
            ->get();

        $questions = $this->getQuestions();

        $grouped = [];

        foreach ($records as $record) {
            $sectorName = $sectorMap[$record->sector_id] ?? "Setor {$record->sector_id}";

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

            $grouped[$sectorName][] = [
                'date' => $record->huddle_date->format('d/m/Y'),
                'filled_by' => $auditUser?->username ?? '—',
                'filled_at' => ($record->updated_at ?? $record->created_at)?->format('d/m/Y H:i'),
                'answers' => $answers,
            ];
        }

        // Ordena por nome do setor
        ksort($grouped);

        $this->historyByUnit = $grouped;
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
