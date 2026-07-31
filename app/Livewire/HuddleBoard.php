<?php

namespace App\Livewire;

use App\Models\EMR\Core\Sector;
use App\Services\Huddle\HuddleBoardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Painel do Huddle de Gestão de Altas.
 *
 * Espelha a arquitetura de estado do SbarReport: propriedades primitivas vão no
 * wire:snapshot; os dados de paciente (~150 KB) ficam em #[Computed(persist:true)],
 * fora do snapshot. Reaproveita as preferências de setor do usuário e delega a
 * montagem do board ao HuddleBoardService.
 */
class HuddleBoard extends Component
{
    public $errorMessage = null;

    public $lastRefresh = null;

    public $selectedHospital = null;

    public $selectedSector = null;

    /** Verdadeiro até o wire:init disparar loadPatients(). */
    public bool $isLoading = true;

    protected $listeners = [
        'refreshData' => 'refreshData',
    ];

    /**
     * Hospitais disponíveis ao usuário (a partir das preferências de setor).
     */
    #[Computed(persist: true)]
    public function hospitals(): array
    {
        return Auth::user()->sectorPreferences()
            ->select(['hospital_code', 'hospital_name'])
            ->distinct()
            ->orderBy('hospital_name')
            ->get()
            ->map(fn ($p) => [
                'hospital_id' => (int) $p->hospital_code,
                'hospital_name' => $p->hospital_name,
            ])
            ->toArray();
    }

    /**
     * Setores do hospital selecionado. Persistido; invalidado na troca de hospital.
     */
    #[Computed(persist: true)]
    public function sectors(): array
    {
        if (! $this->selectedHospital) {
            return [];
        }

        $freshNames = collect(Sector::allowedForPreferences())
            ->keyBy('sector_code')
            ->map(fn ($s) => $s['sector_name']);

        return Auth::user()->sectorPreferences()
            ->where('hospital_code', $this->selectedHospital)
            ->orderBy('sector_name')
            ->get()
            ->map(fn ($p) => [
                'cd_setor_atendimento' => (int) $p->sector_code,
                'ds_setor_atendimento' => $freshNames->get((string) $p->sector_code, $p->sector_name),
            ])
            ->toArray();
    }

    /**
     * Pacientes do setor enriquecidos com o estado do Huddle. Fora do snapshot.
     */
    #[Computed(persist: true)]
    public function patients(): array
    {
        if (! $this->selectedSector) {
            return [];
        }

        try {
            return app(HuddleBoardService::class)->forSector((int) $this->selectedSector);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar board do Huddle', [
                'exception' => $e,
                'selected_sector' => $this->selectedSector,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro ao carregar pacientes: '.$e->getMessage();

            return [];
        }
    }

    #[Computed]
    public function currentHospitalName(): string
    {
        return collect($this->hospitals)
            ->firstWhere('hospital_id', $this->selectedHospital)['hospital_name'] ?? '';
    }

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user->hasConfiguredSectors()) {
            $this->errorMessage = 'Você precisa configurar seus setores de acesso antes de usar o Huddle.';

            return;
        }

        $hospitals = $this->hospitals;

        if (empty($hospitals)) {
            $this->errorMessage = 'Nenhum hospital disponível.';

            return;
        }

        if (! $this->selectedHospital) {
            $this->selectedHospital = $hospitals[0]['hospital_id'];
        }

        $sectors = $this->sectors;

        if (empty($sectors)) {
            $this->errorMessage = 'Nenhum setor configurado para este hospital.';

            return;
        }

        if (! $this->selectedSector) {
            $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
        }

        $this->lastRefresh = now()->format('H:i:s');
    }

    /**
     * Disparado pelo wire:init após o HTML inicial renderizar.
     */
    public function loadPatients(): void
    {
        $this->isLoading = false;
    }

    public function changeHospital($hospitalId): void
    {
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;

        unset($this->sectors, $this->patients);

        $sectors = $this->sectors;

        if (! empty($sectors)) {
            $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
            $this->lastRefresh = now()->format('H:i:s');
        } else {
            $this->errorMessage = 'Nenhum setor configurado para este hospital.';
        }
    }

    public function changeSector($sectorId): void
    {
        $user = Auth::user();
        $allowedSectorCodes = $user->sectorPreferences()->pluck('sector_code')->toArray();

        // Autorização explícita: métodos Livewire podem ser chamados diretamente.
        // O setor precisa estar na whitelist de preferências do próprio usuário.
        if (! in_array((string) $sectorId, array_map('strval', $allowedSectorCodes))) {
            Log::warning('Huddle: tentativa de acesso a setor não autorizado', [
                'user_id' => $user->id,
                'sector_id' => $sectorId,
            ]);
            $this->errorMessage = 'Acesso negado: setor não autorizado.';

            return;
        }

        $this->selectedSector = $sectorId;

        unset($this->patients);

        $this->lastRefresh = now()->format('H:i:s');
    }

    public function refreshData(): void
    {
        unset($this->patients);
        $this->lastRefresh = now()->format('H:i:s');
    }

    public function render()
    {
        return view('huddle.report.index', [
            'hospitals' => $this->hospitals,
            'sectors' => $this->sectors,
            'patients' => $this->isLoading ? [] : $this->patients,
            'isLoading' => $this->isLoading,
            'errorMessage' => $this->errorMessage,
            'selectedHospital' => $this->selectedHospital,
            'selectedSector' => $this->selectedSector,
            'currentHospitalName' => $this->currentHospitalName,
            'lastRefresh' => $this->lastRefresh,
        ]);
    }
}
