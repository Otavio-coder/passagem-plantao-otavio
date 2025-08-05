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
    public $isLoading = false;
    public $loadingStage = 'initial'; // initial, fetching, processing, rendering, complete
    public $loadingMessage = '';
    public $currentPatient = null;
    public $currentHospitalName = '';
    public $patientDetails = null;
    public $patientAlerts = [];
    public $showAlertsModal = false;
    public $currentShift = 'dia';
    public $dataLoaded = false;

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

        // Reset all states
        $this->resetModalState();
        
        // Inicia o loading imediatamente
        $this->isLoading = true;
        $this->showModal = true;
        $this->loadingStage = 'fetching';
        $this->loadingMessage = 'Buscando dados do paciente...';
        $this->dataLoaded = false;

        // Set basic patient info
        $this->currentPatient = [
            'nr_atendimento' => $attendanceNumber,
            'has_patient' => true,
        ];
        $this->currentHospitalName = $hospital;

        // Force render to show loading state
        $this->dispatch('modal-state-changed', ['loading' => true]);

        // Load data asynchronously
        $this->loadPatientDataAsync($attendanceNumber);
    }

    #[On('showAlertsModal')]
    public function showAlertsModal()
    {
        $this->showAlertsModal = true;
    }

    public function loadPatientDataAsync($attendanceNumber)
    {
        try {
            $this->loadingStage = 'processing';
            $this->loadingMessage = 'Processando informações clínicas...';
            
            // Simulate processing delay for better UX
            usleep(500000); // 0.5 seconds

            // Load patient details
            $cacheKey = "patient_full_data_{$attendanceNumber}";
            $this->patientDetails = Cache::remember($cacheKey, 600, function() use ($attendanceNumber) {
                $patientModel = new Patient();
                return $patientModel->getFullPatientData($attendanceNumber);
            });

            $this->loadingStage = 'alerts';
            $this->loadingMessage = 'Carregando alertas clínicos...';

            // Load patient alerts
            $alertsCacheKey = "patient_alerts_{$attendanceNumber}";
            $this->patientAlerts = Cache::remember($alertsCacheKey, 300, function() use ($attendanceNumber) {
                $clinicalRepo = new PatientClinical();
                return $clinicalRepo->getPatientActiveAlerts(
                    $attendanceNumber, 
                    $this->patientDetails->cd_pessoa_fisica ?? null
                );
            });

            $this->loadingStage = 'rendering';
            $this->loadingMessage = 'Preparando interface...';
            $this->dataLoaded = true;

            // Delay before showing content for smooth transition
            usleep(800000); // 0.8 seconds

            $this->loadingStage = 'complete';
            $this->isLoading = false;
            $this->loadingMessage = '';

            // Dispatch event to trigger any client-side initialization
            $this->dispatch('patient-data-loaded', [
                'patientId' => $attendanceNumber,
                'shift' => $this->currentShift
            ]);

        } catch (\Exception $e) {
            $this->loadingStage = 'error';
            $this->loadingMessage = 'Erro ao carregar dados';
            $this->isLoading = false;
            
            Log::error('PatientModal: Error loading patient data', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Show error state
            $this->dispatch('patient-load-error', ['message' => $e->getMessage()]);
        }
    }

    private function resetModalState()
    {
        $this->isLoading = false;
        $this->loadingStage = 'initial';
        $this->loadingMessage = '';
        $this->currentPatient = null;
        $this->currentHospitalName = '';
        $this->patientDetails = null;
        $this->patientAlerts = [];
        $this->showAlertsModal = false;
        $this->dataLoaded = false;
    }

    #[On('hideAlertsModal')]
    public function hideAlertsModal()
    {
        $this->showAlertsModal = false;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetModalState();
        
        $this->dispatch('closePatientModal');
    }

    // Method to check if modal is ready to display content
    public function isContentReady()
    {
        return $this->dataLoaded && 
               $this->patientDetails !== null && 
               $this->loadingStage === 'complete';
    }

    public function render()
    {
        return view('livewire.patient-modal');
    }
}