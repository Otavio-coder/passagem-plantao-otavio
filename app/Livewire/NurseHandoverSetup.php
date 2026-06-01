<?php

namespace App\Livewire;

use App\Models\NurseHandoverBed;
use App\Models\System\UserSectorPreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NurseHandoverSetup extends Component
{
    /** @var array<int, array<string>> sector_id => [bed_code, ...] (selected beds) */
    public array $selectedBeds = [];

    public bool $saved = false;

    /** Mirrors users.only_assigned_beds — toggled via UI, persisted to DB. */
    public bool $onlyAssignedBeds = false;

    public function mount(): void
    {
        $user = Auth::user();

        $saved = NurseHandoverBed::where('user_id', $user->id)
            ->get()
            ->groupBy('sector_id')
            ->map(fn ($rows) => $rows->pluck('bed_code')->all())
            ->all();

        // Converte chaves de setor para int por consistência
        foreach ($saved as $sectorId => $beds) {
            $this->selectedBeds[(int) $sectorId] = $beds;
        }

        $this->onlyAssignedBeds = (bool) Auth::user()->only_assigned_beds;
    }

    /**
     * Returns sectors grouped by hospital, each with their bed list.
     *
     * @return array<int, array{hospital_code: int, hospital_name: string, sectors: list<array{sector_id: int, sector_name: string, beds: list<string>}>}>
     */
    #[Computed]
    public function hospitals(): array
    {
        $user = Auth::user();

        $prefs = UserSectorPreference::where('user_id', $user->id)
            ->select(['hospital_code', 'hospital_name', 'sector_code', 'sector_name'])
            ->distinct()
            ->orderBy('hospital_name')
            ->orderBy('sector_name')
            ->get();

        if ($prefs->isEmpty()) {
            return [];
        }

        $sectorIds = $prefs->pluck('sector_code')->map(fn ($v) => (int) $v)->unique()->all();

        // Cache keyed by sorted sector list — different sector sets get different entries.
        $sortedIds = $sectorIds;
        sort($sortedIds);
        $cacheKey = 'setup_beds_'.implode('_', $sortedIds);

        $bedsBySector = Cache::remember($cacheKey, 600, function () use ($sectorIds) {
            $placeholders = implode(',', array_map('intval', $sectorIds));
            $bedRows = DB::connection('tasy')->select("
                SELECT DISTINCT cd_setor_atendimento, cd_unidade_basica
                FROM tasy.unidade_atendimento
                WHERE ie_situacao = 'A'
                  AND cd_unidade_basica IS NOT NULL
                  AND cd_setor_atendimento IN ({$placeholders})
                ORDER BY cd_setor_atendimento, cd_unidade_basica
            ");

            $result = [];
            foreach ($bedRows as $row) {
                $result[(int) $row->cd_setor_atendimento][] = $row->cd_unidade_basica;
            }

            return $result;
        });

        $grouped = [];

        foreach ($prefs as $pref) {
            $hospitalCode = (int) $pref->hospital_code;
            $sectorId = (int) $pref->sector_code;
            $beds = $bedsBySector[$sectorId] ?? [];

            if (empty($beds)) {
                continue;
            }

            if (! isset($grouped[$hospitalCode])) {
                $grouped[$hospitalCode] = [
                    'hospital_code' => $hospitalCode,
                    'hospital_name' => $pref->hospital_name,
                    'sectors' => [],
                ];
            }

            $grouped[$hospitalCode]['sectors'][] = [
                'sector_id' => $sectorId,
                'sector_name' => $pref->sector_name,
                'beds' => $beds,
            ];
        }

        return array_values(array_filter($grouped, fn ($h) => ! empty($h['sectors'])));
    }

    /** Flat list of selected beds for chip display */
    public function selectedChips(): array
    {
        $chips = [];
        foreach ($this->hospitals as $hospital) {
            foreach ($hospital['sectors'] as $sector) {
                foreach ($this->selectedBeds[$sector['sector_id']] ?? [] as $bed) {
                    $chips[] = [
                        'sector_id' => $sector['sector_id'],
                        'bed' => $bed,
                        'label' => $bed.' · '.$sector['sector_name'],
                    ];
                }
            }
        }

        return $chips;
    }

    public function toggleBed(int $sectorId, string $bedCode): void
    {
        $current = $this->selectedBeds[$sectorId] ?? [];

        if (in_array($bedCode, $current, true)) {
            $this->selectedBeds[$sectorId] = array_values(
                array_filter($current, fn ($b) => $b !== $bedCode)
            );
        } else {
            $this->selectedBeds[$sectorId][] = $bedCode;
        }

        $this->saved = false;
    }

    public function selectAllBeds(int $sectorId): void
    {
        foreach ($this->hospitals as $hospital) {
            foreach ($hospital['sectors'] as $sector) {
                if ($sector['sector_id'] === $sectorId) {
                    $this->selectedBeds[$sectorId] = $sector['beds'];
                    break 2;
                }
            }
        }
        $this->saved = false;
    }

    public function clearSectorBeds(int $sectorId): void
    {
        $this->selectedBeds[$sectorId] = [];
        $this->saved = false;
    }

    public function save(): void
    {
        $user = Auth::user();

        NurseHandoverBed::where('user_id', $user->id)->delete();

        $rows = [];
        $now = now();

        foreach ($this->selectedBeds as $sectorId => $beds) {
            foreach (array_unique($beds) as $bedCode) {
                if ($bedCode === '') {
                    continue;
                }
                $rows[] = [
                    'user_id' => $user->id,
                    'sector_id' => (int) $sectorId,
                    'bed_code' => (string) $bedCode,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            NurseHandoverBed::insert($rows);
        }

        $this->saved = true;
        $this->dispatch('nurse-beds-saved');
    }

    public function toggleOnlyAssignedBeds(): void
    {
        $user = Auth::user();
        $newValue = ! $this->onlyAssignedBeds;

        $user->update(['only_assigned_beds' => $newValue]);
        $this->onlyAssignedBeds = $newValue;
    }

    public function render(): View
    {
        return view('livewire.nurse-handover-setup', [
            'hospitals' => $this->hospitals(),
            'chips' => $this->selectedChips(),
        ]);
    }
}
