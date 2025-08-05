<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\EMR\Patient;
use App\Repositories\EMR\PatientClinical;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PatientModal extends Component
{
    public $showModal = false;
    public $currentPatient = null;
    public $currentHospitalName = '';
    public $patientDetails = null;
    public $patientAlerts = [];
    public $showAlertsModal = false;
    public $currentShift = 'dia';
    public $loadingPatient = false;

    public function mount()
    {
        $hour = now()->hour;
        $this->currentShift = ($hour >= 7 && $hour < 19) ? 'dia' : 'noite';
    }

    #[On('openModal')]
    public function openModal($attendanceNumber, $hospital = '')
    {
        if (!$attendanceNumber) {
            return;
        }

        $this->resetModalState();
        $this->loadingPatient = true;
        $this->showModal = true;
        
        $this->dispatch('modal-opened');
        
        $this->currentPatient = [
            'nr_atendimento' => $attendanceNumber,
            'has_patient' => true,
        ];
        $this->currentHospitalName = $hospital;

        $this->dispatch('patient-loading-started', [
            'patientId' => $attendanceNumber,
            'shift' => $this->currentShift
        ]);

        $this->loadPatientData($attendanceNumber);
    }

    private function loadPatientData($attendanceNumber)
    {
        try {
            $cacheKey = "patient_details_{$attendanceNumber}";
            $this->patientDetails = Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
                $patientModel = new Patient();
                return $patientModel->getFullPatientData($attendanceNumber);
            });

            $alertsCacheKey = "patient_alerts_{$attendanceNumber}";
            $this->patientAlerts = Cache::remember($alertsCacheKey, 300, function() use ($attendanceNumber) {
                $clinicalRepo = new PatientClinical();
                return $clinicalRepo->getPatientActiveAlerts(
                    $attendanceNumber, 
                    $this->patientDetails->cd_pessoa_fisica ?? null
                );
            });

            $this->checkAndShowAlertsModal();
            $this->loadingPatient = false;

            $this->dispatch('patient-data-loaded', [
                'patientId' => $attendanceNumber,
                'shift' => $this->currentShift,
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading patient data: " . $e->getMessage());
            $this->patientDetails = null;
            $this->patientAlerts = [];
            $this->loadingPatient = false;
            
            $this->dispatch('patient-data-loaded', [
                'patientId' => $attendanceNumber,
                'shift' => $this->currentShift,
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function checkAndShowAlertsModal()
    {
        if (empty($this->patientAlerts)) {
            return;
        }

        $activeAlerts = collect($this->patientAlerts)->filter(function($alert) {
            return !isset($alert['end_date']) || 
                   $alert['end_date'] === null || 
                   \Carbon\Carbon::parse($alert['end_date'])->isFuture();
        });

        if ($activeAlerts->count() > 0) {
            $this->showAlertsModal = true;
        }
    }

    private function resetModalState()
    {
        $this->currentPatient = null;
        $this->currentHospitalName = '';
        $this->patientDetails = null;
        $this->patientAlerts = [];
        $this->showAlertsModal = false;
        $this->loadingPatient = false;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetModalState();
        
        $this->dispatch('modal-closed');
        $this->dispatch('closeModal');
    }

    public function hasPatientData()
    {
        return $this->patientDetails !== null;
    }

    public function render()
    {
        return view('livewire.patient-modal');
    }
}