<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\EMR\Core\Bed;
use App\Models\EMR\Core\Hospital;
use App\Models\EMR\Core\Sector;
use App\Models\System\UserSectorPreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SectorSelectorModal extends Component
{
    public bool $show = false;

    public string $search = '';

    public string $activeHospital = '';

    public array $selectedSectors = [];

    private const ALLOWED_HOSPITAL_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 10, 18, 25];

    public function mount(): void
    {
        $user = Auth::user();

        // Se não tem setores configurados, mostra o modal automaticamente
        if (! $user->hasConfiguredSectors()) {
            $this->show = true;
        }

        // Define o primeiro hospital como ativo
        $hospitals = $this->getSectorsByHospital();
        if (! empty($hospitals)) {
            $this->activeHospital = (string) array_key_first($hospitals);
        }
    }

    #[Computed]
    public function sectorsByHospital(): array
    {
        return $this->getSectorsByHospital();
    }

    #[Computed]
    public function filteredSectors(): array
    {
        $sectors = $this->sectorsByHospital;

        if (empty($this->search)) {
            return $sectors;
        }

        $search = mb_strtolower($this->search);
        $filtered = [];

        foreach ($sectors as $hospitalCode => $hospitalSectors) {
            $matchingSectors = array_filter($hospitalSectors, function ($sector) use ($search) {
                return str_contains(mb_strtolower($sector['sector_name']), $search) ||
                       str_contains(mb_strtolower($sector['hospital_name']), $search);
            });

            if (! empty($matchingSectors)) {
                $filtered[$hospitalCode] = array_values($matchingSectors);
            }
        }

        // Se o hospital ativo não está nos resultados filtrados, seleciona o primeiro disponível
        if (! empty($filtered) && ! isset($filtered[$this->activeHospital])) {
            $this->activeHospital = (string) array_key_first($filtered);
        }

        return $filtered;
    }

    #[Computed]
    public function totalSelected(): int
    {
        return count($this->selectedSectors);
    }

    private function getSectorsByHospital(): array
    {
        $cacheKey = 'sectors_for_modal_v1';

        return Cache::remember($cacheKey, 3600, function () {
            // Obtém hospitais
            $hospitals = Hospital::whereIn('nr_sequencia', self::ALLOWED_HOSPITAL_IDS)
                ->where('ie_situacao', 'A')
                ->get()
                ->mapWithKeys(function ($h) {
                    return [(string) $h->nr_sequencia => $h->ds_agrupamento];
                });

            // Obtém setores apenas cd_classif_setor = 3 ou 4
            $sectors = Sector::whereIn('nr_seq_agrupamento', self::ALLOWED_HOSPITAL_IDS)
                ->whereIn('cd_classif_setor', [3, 4])
                ->where('ie_situacao', 'A')
                ->orderBy('ds_setor_atendimento')
                ->get();

            // Batch query — 1 query instead of N (one per sector)
            $codesWithBeds = Bed::whereIn('cd_setor_atendimento', $sectors->pluck('cd_setor_atendimento')->toArray())
                ->where('ie_situacao', 'A')
                ->distinct()
                ->pluck('cd_setor_atendimento')
                ->flip()
                ->toArray();

            return $sectors
                ->filter(fn ($s) => isset($codesWithBeds[$s->cd_setor_atendimento]))
                ->map(function ($s) use ($hospitals) {
                    return [
                        'sector_code' => (string) $s->cd_setor_atendimento,
                        'sector_name' => $s->ds_setor_atendimento,
                        'hospital_code' => (string) $s->nr_seq_agrupamento,
                        'hospital_name' => $hospitals->get((string) $s->nr_seq_agrupamento, 'Hospital'),
                    ];
                })
                ->groupBy('hospital_code')
                ->toArray();
        });
    }

    public function toggleSector(string $sectorCode): void
    {
        if (in_array($sectorCode, $this->selectedSectors)) {
            $this->selectedSectors = array_values(array_diff($this->selectedSectors, [$sectorCode]));
        } else {
            $this->selectedSectors[] = $sectorCode;
        }
    }

    public function selectAllFromHospital(string $hospitalCode): void
    {
        $hospitalSectors = $this->sectorsByHospital[$hospitalCode] ?? [];
        $codes = array_column($hospitalSectors, 'sector_code');

        $allSelected = true;
        foreach ($codes as $code) {
            if (! in_array($code, $this->selectedSectors)) {
                $allSelected = false;
                break;
            }
        }

        if ($allSelected) {
            $this->selectedSectors = array_values(array_diff($this->selectedSectors, $codes));
        } else {
            $this->selectedSectors = array_values(array_unique(array_merge($this->selectedSectors, $codes)));
        }
    }

    public function savePreferences(): void
    {
        if (empty($this->selectedSectors)) {
            return;
        }

        $user = Auth::user();

        // Remove preferências antigas
        UserSectorPreference::where('user_id', $user->id)->delete();

        // Cria novas preferências
        foreach ($this->sectorsByHospital as $hospitalSectors) {
            foreach ($hospitalSectors as $sector) {
                if (in_array($sector['sector_code'], $this->selectedSectors)) {
                    UserSectorPreference::create([
                        'user_id' => $user->id,
                        'sector_code' => $sector['sector_code'],
                        'sector_name' => $sector['sector_name'],
                        'hospital_code' => $sector['hospital_code'],
                        'hospital_name' => $sector['hospital_name'],
                        'is_active' => true,
                    ]);
                }
            }
        }

        $this->show = false;
        $this->dispatch('show-toast', [
            'message' => 'Setores configurados com sucesso!',
            'type' => 'success',
        ]);
        $this->dispatch('sectors-configured');
    }

    public function skipForNow(): void
    {
        $this->show = false;
    }

    public function render()
    {
        return view('configuration.system.sector-selector-modal');
    }
}
