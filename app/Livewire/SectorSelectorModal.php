<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\EMR\Core\Sector;
use App\Models\System\UserSectorPreference;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SectorSelectorModal extends Component
{
    /**
     * @var array<string, string>
     */
    private const HOSPITAL_COLORS = [
        '1' => '#8C3134',
        '8' => '#42909A',
        '2' => '#A96538',
        '6' => '#3E6B35',
        '4' => '#4586B4',
        '3' => '#9574A1',
        '25' => '#CE7D74',
        '7' => '#563174',
        '18' => '#073772',
    ];

    public bool $show = false;

    public string $search = '';

    public string $activeHospital = '';

    public array $selectedSectors = [];

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

    #[Computed]
    public function hospitalSections(): array
    {
        $sections = [];

        foreach ($this->filteredSectors as $hospitalCode => $sectors) {
            $selectedCount = collect($sectors)
                ->filter(fn ($sector) => in_array($sector['sector_code'], $this->selectedSectors, true))
                ->count();

            $hospitalName = $sectors[0]['hospital_name'] ?? 'Hospital';
            $allSelected = $selectedCount === count($sectors) && count($sectors) > 0;

            $sections[] = [
                'code' => (string) $hospitalCode,
                'name' => $hospitalName,
                'color' => self::HOSPITAL_COLORS[(string) $hospitalCode] ?? '#004D9D',
                'selected_count' => $selectedCount,
                'all_selected' => $allSelected,
                'is_expanded' => $selectedCount > 0 || empty($sections),
                'total_sectors' => count($sectors),
                'icon_label' => mb_substr($hospitalName, 0, 1),
                'sectors' => array_map(function ($sector): array {
                    $isSelected = in_array($sector['sector_code'], $this->selectedSectors, true);

                    return [
                        'code' => $sector['sector_code'],
                        'name' => $sector['sector_name'],
                        'is_selected' => $isSelected,
                    ];
                }, $sectors),
            ];
        }

        return $sections;
    }

    private function getSectorsByHospital(): array
    {
        return Sector::allowedForPreferencesGroupedByHospital();
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
