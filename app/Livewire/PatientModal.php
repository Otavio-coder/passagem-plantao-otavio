<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\EMR\Patient;
use App\Repositories\EMR\PatientClinical;
use Illuminate\Support\Facades\Log;

class PatientModal extends Component
{
    public $showModal = false;
    public $loadingPatient = false;
    public $currentPatient = null;
    public $currentHospitalName = '';
    public $patientDetails = null;
    public $patientAlerts = [];
    public $showAlertsModal = false;
    public $currentShift = 'dia';

    // FIXED: Use both attributes and property for Livewire compatibility
    protected $listeners = [
        'openPatientModal' => 'openModal',
        'closePatientModal' => 'closeModal'
    ];

    public function mount()
    {
        $hour = now()->hour;
        $this->currentShift = ($hour >= 7 && $hour < 19) ? 'dia' : 'noite';
    }

    // FIXED: Use both attribute and method for maximum compatibility
    #[On('openPatientModal')]
    public function openModal($data = null)
    {
        try {
            Log::info('🔄 PatientModal: openModal called', ['data' => $data]);
            
            // Handle both array and direct parameters
            if (is_array($data) && isset($data['patient'])) {
                $patient = $data['patient'];
                $hospital = $data['hospital'] ?? '';
            } else {
                // Fallback for direct parameters (legacy compatibility)
                $patient = $data;
                $hospital = func_get_args()[1] ?? '';
            }
            
            if (!$patient) {
                Log::warning('❌ PatientModal: No patient data provided');
                return;
            }
            
            // Set basic modal state first
            $this->showModal = true;
            $this->loadingPatient = true;
            $this->currentPatient = $patient;
            $this->currentHospitalName = $hospital;
            
            Log::info('✅ PatientModal: Modal opened', [
                'patient_id' => $patient['nr_atendimento'] ?? 'N/A',
                'hospital' => $hospital
            ]);
            
            // Dispatch event to disable auto-scroll
            $this->dispatch('openPatientModal');
            
            // Load detailed patient data if patient exists
            if (isset($patient['has_patient']) && $patient['has_patient'] && isset($patient['nr_atendimento'])) {
                $this->loadPatientDetails($patient['nr_atendimento']);
            } else {
                // For empty beds, just stop loading
                $this->loadingPatient = false;
                Log::info('ℹ️ PatientModal: Empty bed, no details to load');
            }
            
        } catch (\Exception $e) {
            Log::error('❌ PatientModal: Error opening modal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->loadingPatient = false;
            $this->showModal = false;
        }
    }

    public function loadPatientDetails($attendanceNumber)
    {
        try {
            $this->loadingPatient = true;
            
            Log::info('🔄 PatientModal: Loading patient details', ['attendance' => $attendanceNumber]);
            
            // Get full patient data
            $patientModel = new Patient();
            $this->patientDetails = $patientModel->getFullPatientData($attendanceNumber);
            
            // Load patient alerts
            if ($this->patientDetails) {
                $clinicalRepo = new PatientClinical();
                $this->patientAlerts = $clinicalRepo->getPatientActiveAlerts(
                    $attendanceNumber, 
                    $this->patientDetails->cd_pessoa_fisica ?? null
                );
                
                Log::info('✅ PatientModal: Patient details loaded successfully', [
                    'attendance' => $attendanceNumber,
                    'alerts_count' => count($this->patientAlerts)
                ]);
            } else {
                Log::warning('⚠️ PatientModal: No patient details found', ['attendance' => $attendanceNumber]);
            }
            
        } catch (\Exception $e) {
            Log::error('❌ PatientModal: Error loading patient details', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
            $this->patientDetails = null;
            $this->patientAlerts = [];
        } finally {
            $this->loadingPatient = false;
        }
    }

    public function closeModal()
    {
        Log::info('🔄 PatientModal: Closing modal');
        
        $this->showModal = false;
        $this->loadingPatient = false;
        $this->currentPatient = null;
        $this->currentHospitalName = '';
        $this->patientDetails = null;
        $this->patientAlerts = [];
        $this->showAlertsModal = false;
        
        // Dispatch close event to re-enable auto-scroll
        $this->dispatch('closePatientModal');
        
        Log::info('✅ PatientModal: Modal closed');
    }

    public function showAlerts()
    {
        if (!empty($this->patientAlerts)) {
            $this->showAlertsModal = true;
            Log::info('🔄 PatientModal: Alerts modal opened', ['alerts_count' => count($this->patientAlerts)]);
        }
    }

    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
        Log::info('✅ PatientModal: Alerts modal closed');
    }

    public function render()
    {
        return view('livewire.patient-modal');
    }
}