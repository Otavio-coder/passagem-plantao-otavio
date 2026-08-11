<?php

namespace App\Livewire;

use App\Models\Huddle\HuddleSafetyAssessment;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Consulta dos Round Unidade (huddle_safety_assessments) preenchidos, com filtro
 * por data e por hospital. O vínculo setor → hospital vem de user_sector_preferences
 * (mesma fonte que o board usa).
 */
class HuddleSafetyReport extends Component
{
    public ?string $selectedDate = null;

    public ?int $selectedHospital = null;

    public function clearFilters(): void
    {
        $this->reset(['selectedDate', 'selectedHospital']);
    }

    public function render()
    {
        $sectorMap = $this->sectorMap();

        $query = HuddleSafetyAssessment::query()->with(['createdBy', 'updatedBy']);

        if (! empty($this->selectedDate)) {
            $query->whereDate('huddle_date', $this->selectedDate);
        }

        if (! empty($this->selectedHospital)) {
            $sectorIds = $sectorMap
                ->filter(fn ($m) => (int) $m->hospital_code === (int) $this->selectedHospital)
                ->keys()
                ->all();
            $query->whereIn('sector_id', $sectorIds ?: [-1]);
        }

        $assessments = $query
            ->orderByDesc('huddle_date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(function (HuddleSafetyAssessment $a) use ($sectorMap) {
                $map = $sectorMap->get((int) $a->sector_id);
                $auditUser = $a->updatedBy ?? $a->createdBy;

                return [
                    'id' => $a->id,
                    'date' => $a->huddle_date?->format('d/m/Y'),
                    'hospital' => $map->hospital_name ?? '—',
                    'sector' => $map->sector_name ?? ('Setor '.$a->sector_id),
                    'classification' => $a->unit_classification?->value,
                    'filled_by' => $auditUser?->username ?? '—',
                    'filled_at' => ($a->updated_at ?? $a->created_at)?->format('d/m/Y H:i'),
                    'model' => $a,
                ];
            });

        return view('huddle.safety-report.index', [
            'assessments' => $assessments,
            'hospitals' => $this->hospitals(),
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
}
