<?php

namespace App\Livewire;

use App\Models\EMR\Core\Bed;
use App\Models\EMR\Core\Person;
use App\Models\EMR\Core\Sector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HuddleReport extends Component
{
    public ?int $selectedHospital = null;

    public ?int $selectedSector = null;

    public ?string $errorMessage = null;

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

    #[Computed(persist: true)]
    public function patients(): array
    {
        if (! $this->selectedSector) {
            return [];
        }

        $beds = Bed::with([
            'currentPatient' => fn ($q) => $q->whereNull('dt_alta'),
        ])
            ->active()
            ->where('cd_setor_atendimento', $this->selectedSector)
            ->orderBy('nr_seq_apresent')
            ->get();

        // Carrega todos os registros de Person em uma única query
        $personIds = $beds->pluck('currentPatient.cd_pessoa_fisica')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $personsByIdFromTasy = Person::whereIn('cd_pessoa_fisica', $personIds)
            ->get()
            ->keyBy('cd_pessoa_fisica');
        $selectedSectorName = data_get(
            collect($this->sectors)->firstWhere('cd_setor_atendimento', $this->selectedSector),
            'ds_setor_atendimento'
        );

        // Mapeia leitos para a resposta, anexando dados de Person
        return $beds->map(function ($bed) use ($personsByIdFromTasy, $selectedSectorName) {
            $patient = $bed->currentPatient;
            $person = $patient ? $personsByIdFromTasy->get($patient->cd_pessoa_fisica) : null;
            $hasPatient = ! is_null($patient);

            return [
                // ── Identificação do card ──────────────────────────────
                'cd_unidade_basica' => $bed->cd_unidade_basica,
                'cd_setor_atendimento' => $this->selectedSector,
                'has_patient' => $hasPatient,
                'nr_atendimento' => $patient?->nr_atendimento,
                'ds_setor_atendimento' => $selectedSectorName,

                // ── Estilo do card (exigido pelo componente x-patient-card) ────
                'gradient_style' => $hasPatient
                    ? 'background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);'
                    : 'background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);',
                'border_class' => '',
                'text_color_class' => '',

                // ── Patient basic data ─────────────────────────────────
                'nm_pessoa_fisica' => $person?->name ?? $patient?->nm_pessoa_fisica,
                'nr_prontuario' => $person?->record_number,
                'sexo' => $person?->sex,
                'age' => $person?->birth_date
                    ? (int) $person->birth_date->diffInYears(now())
                    : null,
                'birth_date' => $person?->birth_date?->format('d/m/Y'),
                'internment_days' => $patient?->dt_entrada
                    ? (int) Carbon::parse($patient->dt_entrada)->diffInDays(now())
                    : null,
                'is_new_patient' => $patient?->dt_entrada
                    ? now()->isSameDay($patient->dt_entrada)
                    : false,
            ];
        })
            ->toArray();
    }

    public function mount(): void
    {
        $hospitals = $this->hospitals;

        if (empty($hospitals)) {
            $this->errorMessage = 'Nenhum hospital disponível.';

            return;
        }

        $this->selectedHospital = $hospitals[0]['hospital_id'];
        $sectors = $this->sectors;

        if (empty($sectors)) {
            $this->errorMessage = 'Nenhum setor configurado para este hospital.';

            return;
        }

        $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
    }

    public function changeHospital(int $hospitalId): void
    {
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;
        unset($this->sectors, $this->patients);

        $sectors = $this->sectors;

        if (! empty($sectors)) {
            $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
        }
    }

    public function changeSector(int $sectorId): void
    {
        $this->selectedSector = $sectorId;
        unset($this->patients);
    }

    public function render()
    {
        return view('huddle.report.index');
    }
}
