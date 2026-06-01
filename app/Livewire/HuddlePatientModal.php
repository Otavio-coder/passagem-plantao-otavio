<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class HuddlePatientModal extends Component
{
    public bool $showModal = false;

    public bool $handoverMode = false;

    public array $currentPatient = [];

    public string $hospitalName = '';

    public array $modalPatients = [];

    public ?int $currentPatientIndex = null;

    public bool $canGoPrevious = false;

    public bool $canGoNext = false;

    #[On('openModal')]
    public function openWithPatient(int $attendanceNumber, string $hospital, array $sbarPatient, array $patients = []): void
    {
        $this->hospitalName = $hospital;

        $this->modalPatients = collect($patients)
            ->filter(fn ($p) => ! empty($p['has_patient']) && ! empty($p['nr_atendimento']))
            ->values()
            ->map(fn ($p) => array_merge($p, [
                'label' => trim(($p['cd_unidade_basica'] ?? '').' – '.($p['nm_pessoa_fisica'] ?? 'Paciente')),
            ]))
            ->toArray();

        $index = collect($this->modalPatients)
            ->search(fn ($p) => ($p['nr_atendimento'] ?? 0) == $attendanceNumber);

        $this->currentPatientIndex = $index !== false ? $index : 0;
        $this->currentPatient = $this->modalPatients[$this->currentPatientIndex] ?? $sbarPatient;

        $this->updateNavigationState();

        $this->showModal = true;
    }

    public function goToPreviousPatient(): void
    {
        if (! $this->canGoPrevious || $this->currentPatientIndex === null) {
            return;
        }

        $this->navigateToIndex($this->currentPatientIndex - 1);
    }

    public function goToNextPatient(): void
    {
        if (! $this->canGoNext || $this->currentPatientIndex === null) {
            return;
        }

        $this->navigateToIndex($this->currentPatientIndex + 1);
    }

    public function goToPatientByAttendance(int $attendanceNumber): void
    {
        $index = collect($this->modalPatients)
            ->search(fn ($p) => ($p['nr_atendimento'] ?? 0) == $attendanceNumber);

        if ($index !== false) {
            $this->navigateToIndex($index);
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->currentPatient = [];
        $this->hospitalName = '';
        $this->modalPatients = [];
        $this->currentPatientIndex = null;
        $this->canGoPrevious = false;
        $this->canGoNext = false;
    }

    public function render()
    {
        return view('huddle.patient-modal.index');
    }

    private function navigateToIndex(int $index): void
    {
        $patient = $this->modalPatients[$index] ?? null;

        if (! $patient) {
            return;
        }

        $this->currentPatient = $patient;
        $this->currentPatientIndex = $index;
        $this->updateNavigationState();
    }

    private function updateNavigationState(): void
    {
        $total = count($this->modalPatients);
        $this->canGoPrevious = $this->currentPatientIndex > 0;
        $this->canGoNext = $this->currentPatientIndex < ($total - 1);
    }
}
