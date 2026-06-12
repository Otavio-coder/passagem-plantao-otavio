<?php

namespace App\Livewire;

use App\Repositories\EMR\PatientQuickSearchRepository;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class PatientQuickSearch extends Component
{
    public bool $isOpen = false;

    public string $search = '';

    /** @var array<int, array> Resultados da busca de nome */
    public array $results = [];

    /** @var array|null Paciente selecionado */
    public ?array $patient = null;

    /** @var array{surgeries: array, procedures: array, labs: array}|null */
    public ?array $pending = null;

    public bool $loadingSearch = false;

    public bool $loadingPending = false;

    public ?string $error = null;

    #[On('open-patient-quick-search')]
    public function open(): void
    {
        $this->isOpen = true;
        $this->reset(['search', 'results', 'patient', 'pending', 'error']);
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->reset(['search', 'results', 'patient', 'pending', 'error']);
    }

    public function updatedSearch(): void
    {
        $this->patient = null;
        $this->pending = null;
        $this->error = null;

        $term = trim($this->search);
        if (mb_strlen($term) < 3) {
            $this->results = [];

            return;
        }

        $repo = app(PatientQuickSearchRepository::class);
        $this->results = $repo->searchByName($term)
            ->map(fn ($r) => [
                'nr_atendimento' => $r->nr_atendimento,
                'cd_pessoa_fisica' => $r->cd_pessoa_fisica,
                'nm_paciente' => $r->nm_paciente,
                'nr_prontuario' => $r->nr_prontuario,
                'dt_nascimento' => $r->dt_nascimento,
                'leito' => $r->leito,
                'setor' => $r->setor,
            ])
            ->values()
            ->all();
    }

    public function selectPatient(int $nrAtendimento, int $cdPessoaFisica): void
    {
        $match = collect($this->results)->first(
            fn ($r) => (int) $r['nr_atendimento'] === $nrAtendimento
        );

        if (! $match) {
            return;
        }

        $this->patient = $match;
        $this->results = [];
        $this->pending = null;
        $this->error = null;

        $repo = app(PatientQuickSearchRepository::class);
        $this->pending = $repo->getPending24h($nrAtendimento);
    }

    public function render()
    {
        return view('livewire.patient-quick-search');
    }
}
