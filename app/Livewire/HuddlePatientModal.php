<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class HuddlePatientModal extends Component
{
    public bool $showModal = false;

    public array $currentPatient = [];

    public string $hospitalName = '';

    #[On('openModal')]
    public function openWithPatient(int $attendanceNumber, string $hospital, array $sbarPatient, array $patients = []): void
    {
        $this->currentPatient = $sbarPatient;
        $this->hospitalName = $hospital;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('huddle.patient-modal.index');
    }
}
