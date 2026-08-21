<?php

namespace App\Livewire;

use App\Enums\Huddle\DayColor;
use App\Enums\Huddle\HuddleChecklistItem;
use App\Models\Huddle\HuddlePatientDay;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Consulta dos checklists de pacientes (huddle_patient_days + checklist_answers),
 * com filtro por data, hospital e unidade. Espelha o padrão visual do
 * HuddleSafetyReport (Round Unidade — Consulta).
 */
class HuddleChecklistReport extends Component
{
    public ?string $selectedDate = null;

    public ?int $selectedHospital = null;

    public ?int $selectedSector = null;

    public function updatedSelectedHospital(): void
    {
        $this->selectedSector = null;
    }

    public function clearFilters(): void
    {
        $this->reset(['selectedDate', 'selectedHospital', 'selectedSector']);
    }

    public function render()
    {
        $sectorMap = $this->sectorMap();

        $query = HuddlePatientDay::query()
            ->with(['createdBy', 'updatedBy', 'checklistAnswers.answeredBy']);

        if (! empty($this->selectedDate)) {
            $query->whereDate('huddle_date', $this->selectedDate);
        }

        if (! empty($this->selectedSector)) {
            $query->where('sector_id', (int) $this->selectedSector);
        } elseif (! empty($this->selectedHospital)) {
            $sectorIds = $sectorMap
                ->filter(fn ($m) => (int) $m->hospital_code === (int) $this->selectedHospital)
                ->keys()
                ->all();
            $query->whereIn('sector_id', $sectorIds ?: [-1]);
        }

        $days = $query
            ->orderByDesc('huddle_date')
            ->orderBy('sector_id')
            ->orderBy('nr_atendimento')
            ->limit(500)
            ->get()
            ->map(function (HuddlePatientDay $day) use ($sectorMap) {
                $map = $sectorMap->get((int) $day->sector_id);
                $auditUser = $day->updatedBy ?? $day->createdBy;

                $answers = [];
                foreach ($day->checklistAnswers as $answer) {
                    $code = $answer->item_code instanceof HuddleChecklistItem
                        ? $answer->item_code->value
                        : (string) $answer->item_code;

                    $answers[$code] = [
                        'answer' => $answer->answer,
                        'signal' => $answer->signal instanceof DayColor ? $answer->signal->value : $answer->signal,
                        'notes' => $answer->notes,
                        'answered_by' => $answer->answeredBy?->username ?? '—',
                        'answered_at' => $answer->updated_at?->format('d/m/Y H:i'),
                    ];
                }

                return [
                    'id' => $day->id,
                    'date' => $day->huddle_date?->format('d/m/Y'),
                    'hospital' => $map->hospital_name ?? '—',
                    'sector' => $map->sector_name ?? ('Setor '.$day->sector_id),
                    'nr_atendimento' => $day->nr_atendimento,
                    'color' => $day->color instanceof DayColor ? $day->color->value : (string) $day->color,
                    'status' => $day->status,
                    'comments' => $day->comments,
                    'finalized' => $day->isFinalized(),
                    'finalized_at' => $day->finalized_at?->format('d/m/Y H:i'),
                    'filled_by' => $auditUser?->username ?? '—',
                    'filled_at' => ($day->updated_at ?? $day->created_at)?->format('d/m/Y H:i'),
                    'answers' => $answers,
                    'answers_count' => count($answers),
                    'red_count' => collect($answers)->where('signal', 'red')->count(),
                ];
            });

        return view('huddle.checklist-report.index', [
            'days' => $days,
            'hospitals' => $this->hospitals(),
            'sectors' => $this->sectorsForHospital(),
            'checklistItems' => HuddleChecklistItem::cases(),
        ]);
    }

    /**
     * Mapa sector_code => { hospital_code, hospital_name, sector_name }.
     */
    private function sectorMap()
    {
        return DB::table('user_sector_preferences')
            ->select('sector_code', 'hospital_code', 'hospital_name', 'sector_name')
            ->get()
            ->keyBy(fn ($m) => (int) $m->sector_code);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function hospitals(): array
    {
        return DB::table('user_sector_preferences')
            ->select('hospital_code', 'hospital_name')
            ->whereNotNull('hospital_code')
            ->distinct()
            ->orderBy('hospital_name')
            ->get()
            ->map(fn ($h) => ['id' => (int) $h->hospital_code, 'name' => $h->hospital_name])
            ->all();
    }

    /**
     * Setores do hospital selecionado (para o segundo filtro).
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function sectorsForHospital(): array
    {
        if (empty($this->selectedHospital)) {
            return [];
        }

        return DB::table('user_sector_preferences')
            ->select('sector_code', 'sector_name')
            ->where('hospital_code', $this->selectedHospital)
            ->distinct()
            ->orderBy('sector_name')
            ->get()
            ->map(fn ($s) => ['id' => (int) $s->sector_code, 'name' => $s->sector_name])
            ->all();
    }
}
