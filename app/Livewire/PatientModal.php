<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\EMR\Patient;
use Illuminate\Support\Facades\Log;

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
    
    // Model centralizada
    protected $patientModel;

    public function boot()
    {
        $this->patientModel = new Patient();
    }

    public function mount()
    {
        $this->currentShift = $this->getCurrentShift();
        
        // Debug: log quando o component é montado
        \Illuminate\Support\Facades\Log::info('PatientModal: Component mounted', [
            'currentShift' => $this->currentShift
        ]);
    }

    /**
     * Determina o turno atual baseado no horário
     * Manhã: 07:15 - 13:14
     * Tarde: 13:15 - 19:14
     * Noite: 19:15 - 07:14
     */
    private function getCurrentShift()
    {
        $now = now();
        $hour = $now->hour;
        $minute = $now->minute;
        $time = $hour * 60 + $minute; // Converte para minutos desde meia-noite
        
        // 07:15 = 435min, 13:15 = 795min, 19:15 = 1155min
        if ($time >= 435 && $time < 795) {
            return 'manha';
        } elseif ($time >= 795 && $time < 1155) {
            return 'tarde';
        } else {
            return 'noite';
        }
    }

    /**
     * Método de teste para abrir modal diretamente
     */
    public function testOpenModal($attendanceNumber = 123)
    {
        \Illuminate\Support\Facades\Log::info('PatientModal: testOpenModal called', [
            'attendanceNumber' => $attendanceNumber
        ]);
        
        $this->showModal = true;
        $this->loadingPatient = false;
        $this->currentPatient = [
            'nr_atendimento' => $attendanceNumber,
            'has_patient' => true,
        ];
        
        \Illuminate\Support\Facades\Log::info('PatientModal: testOpenModal finished', [
            'showModal' => $this->showModal
        ]);
    }

    #[On('openModal')]
    public function openModal($attendanceNumber, $hospital = '')
    {
        // Debug log
        \Illuminate\Support\Facades\Log::info('PatientModal: openModal called', [
            'attendanceNumber' => $attendanceNumber,
            'hospital' => $hospital,
            'showModal' => $this->showModal
        ]);

        if (!$attendanceNumber) {
            \Illuminate\Support\Facades\Log::warning('PatientModal: attendanceNumber is empty');
            return;
        }

        $this->resetModalState();
        $this->loadingPatient = true;
        $this->showModal = true;
        
        \Illuminate\Support\Facades\Log::info('PatientModal: modal state set', [
            'showModal' => $this->showModal,
            'loadingPatient' => $this->loadingPatient
        ]);
        
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

    /**
     * USA O MÉTODO CENTRALIZADO DA PATIENT MODEL
     */
    private function loadPatientData($attendanceNumber)
    {
        try {
            // Limpa cache antes de buscar dados atualizados
            $this->patientModel->clearPatientCache($attendanceNumber);
            
            // USA O MÉTODO CENTRALIZADO getFullPatientData
            $this->patientDetails = $this->patientModel->getFullPatientData($attendanceNumber);

            if ($this->patientDetails) {
                // Extrai alertas dos dados completos
                $this->patientAlerts = $this->patientDetails->alerts ?? [];
                
                // Atualiza dados do paciente atual
                $this->currentPatient = array_merge($this->currentPatient, [
                    'nm_pessoa_fisica' => $this->patientDetails->nm_pessoa_fisica,
                    'nr_prontuario' => $this->patientDetails->nr_prontuario,
                    'age_detailed' => $this->patientDetails->age_detailed,
                    'sexo' => $this->patientDetails->sexo,
                    'convenio' => $this->patientDetails->convenio,
                    'hospital_name' => $this->patientDetails->hospital_name ?? $this->currentHospitalName
                ]);
            } else {
                $this->patientAlerts = [];
            }

            $this->checkAndShowAlertsModal();
            $this->loadingPatient = false;

            $this->dispatch('patient-data-loaded', [
                'patientId' => $attendanceNumber,
                'shift' => $this->currentShift,
                'success' => true,
                'hasAlerts' => !empty($this->patientAlerts)
            ]);

        } catch (\Exception $e) {
            $this->handlePatientLoadError($e, $attendanceNumber);
        }
    }

    /**
     * Tratamento de erro
     */
    private function handlePatientLoadError(\Exception $e, $attendanceNumber)
    {
        $this->patientDetails = null;
        $this->patientAlerts = [];
        $this->loadingPatient = false;
        
        Log::error("PatientModal: Erro ao carregar dados do paciente", [
            'attendance_number' => $attendanceNumber,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $this->dispatch('patient-data-loaded', [
            'patientId' => $attendanceNumber,
            'shift' => $this->currentShift,
            'success' => false,
            'error' => 'Erro ao carregar dados do paciente: ' . $e->getMessage()
        ]);
    }

    /**
     * Verificação e exibição de alertas
     */
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

    /**
     * Reset do estado do modal
     */
    private function resetModalState()
    {
        $this->currentPatient = null;
        $this->currentHospitalName = '';
        $this->patientDetails = null;
        $this->patientAlerts = [];
        $this->showAlertsModal = false;
        $this->loadingPatient = false;
    }

    /**
     * Força refresh dos dados (limpa cache e recarrega)
     */
    public function refreshPatientData()
    {
        if (!$this->currentPatient || !isset($this->currentPatient['nr_atendimento'])) {
            return;
        }

        $this->loadingPatient = true;
        
        // Limpa cache antes de recarregar
        $this->patientModel->clearPatientCache($this->currentPatient['nr_atendimento']);
        
        $this->loadPatientData($this->currentPatient['nr_atendimento']);    
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetModalState();
        
        $this->dispatch('modal-closed');
        $this->dispatch('closeModal');
     
    }

    public function closeAlertsModal()
    {
        $this->showAlertsModal = false;
    }

    /**
     * Verifica se tem dados do paciente
     */
    public function hasPatientData()
    {
        return $this->patientDetails !== null;
    }

    /**
     * Getter para alertas ativos (para uso na view)
     */
    public function getActiveAlertsProperty()
    {
        if (empty($this->patientAlerts)) {
            return collect([]);
        }

        return collect($this->patientAlerts)->filter(function($alert) {
            return !isset($alert['end_date']) || 
                   $alert['end_date'] === null || 
                   \Carbon\Carbon::parse($alert['end_date'])->isFuture();
        });
    }

    /**
     * Getter para contagem de alertas críticos
     */
    public function getCriticalAlertsCountProperty()
    {
        return $this->activeAlerts->filter(function($alert) {
            return ($alert['severity'] ?? 'warning') === 'danger' || 
                   ($alert['type'] ?? '') === 'ALERTA';
        })->count();
    }

    /**
     * Getter para dados CPOE organizados
     */
    public function getCpoeDataProperty()
    {
        if (!$this->patientDetails) {
            return null;
        }

        return [
            'procedures' => $this->patientDetails->cpoe_procedures ?? 'Nenhum procedimento',
            'medications' => $this->patientDetails->cpoe_medications ?? 'Nenhuma medicação',
            'nutrition' => $this->patientDetails->cpoe_nutrition ?? 'Nenhuma dieta',
            'recommendations' => $this->patientDetails->cpoe_recommendations ?? 'Nenhuma recomendação',
            'interventions' => $this->patientDetails->cpoe_interventions ?? 'Nenhuma intervenção',
        ];
    }

    /**
     * Getter para escalas organizadas - NOVA ESTRUTURA SIMPLIFICADA
     */
    public function getScalesDataProperty()
    {
        if (!$this->patientDetails) {
            return null;
        }

        $isPediatric = isset($this->patientDetails->age) && intval($this->patientDetails->age) < 16;

        $scales = [];

        // MEWS (adultos) ou PEWS (pediátricos)
        if (!$isPediatric) {
            $scales['mews'] = [
                'score' => $this->patientDetails->mews_score ?? null,
                'timestamp' => $this->patientDetails->mews_timestamp ?? null,
                'classification' => $this->patientDetails->mews_classification ?? null,
                'increased' => $this->patientDetails->mews_increased ?? false,
                'needs_assessment' => $this->patientDetails->mews_needs_assessment ?? true
            ];
        } else {
            $scales['pews'] = [
                'score' => $this->patientDetails->pews_score ?? null,
                'timestamp' => $this->patientDetails->pews_timestamp ?? null,
                'classification' => $this->patientDetails->pews_classification ?? null,
                'increased' => $this->patientDetails->pews_increased ?? false,
                'needs_assessment' => $this->patientDetails->pews_needs_assessment ?? true
            ];
        }

        // Escalas comuns
        $scales['braden'] = [
            'score' => $this->patientDetails->braden_score ?? null,
            'timestamp' => $this->patientDetails->braden_timestamp ?? null,
            'classification' => $this->patientDetails->braden_classification ?? null,
            'increased' => $this->patientDetails->braden_increased ?? false,
            'needs_assessment' => $this->patientDetails->braden_needs_assessment ?? true
        ];

        $scales['morse'] = [
            'score' => $this->patientDetails->morse_score ?? null,
            'timestamp' => $this->patientDetails->morse_timestamp ?? null,
            'classification' => $this->patientDetails->morse_classification ?? null,
            'increased' => $this->patientDetails->morse_increased ?? false,
            'needs_assessment' => $this->patientDetails->morse_needs_assessment ?? true
        ];

        $scales['dor'] = [
            'score' => $this->patientDetails->dor_score ?? null,
            'timestamp' => $this->patientDetails->dor_timestamp ?? null,
            'classification' => $this->patientDetails->dor_classification ?? null,
            'increased' => $this->patientDetails->dor_increased ?? false,
            'needs_assessment' => $this->patientDetails->dor_needs_assessment ?? true
        ];

        $scales['tev'] = [
            'score' => $this->patientDetails->tev_score ?? null,
            'timestamp' => $this->patientDetails->tev_timestamp ?? null,
            'classification' => $this->patientDetails->tev_classification ?? null,
            'increased' => $this->patientDetails->tev_increased ?? false,
            'needs_assessment' => $this->patientDetails->tev_needs_assessment ?? true
        ];

        return $scales;
    }

    /**
     * Getter para dados clínicos organizados
     */
    public function getClinicalDataProperty()
    {
        if (!$this->patientDetails) {
            return null;
        }

        return [
            'diagnosticos' => $this->patientDetails->diagnosticos_comorbidades ?? 'Sem diagnósticos',
            'isolamento' => $this->patientDetails->medida_bloqueio ?? 'Não',
            'motivos_isolamento' => $this->patientDetails->motivos_isolamento ?? 'Nenhum motivo de isolamento',
            'dispositivos' => $this->patientDetails->dispositivos ?? 'Nenhum dispositivo',
            'alergias' => $this->patientDetails->alergias_detalhadas ?? 'Sem alergias registradas',
            'antimicrobianos' => $this->patientDetails->materiais ?? 'Nenhum antimicrobiano',
            'exames_prioritarios' => $this->patientDetails->prioridade_exames ?? 'Nenhum exame prioritário',
            'cirurgias' => $this->patientDetails->procedimentos_cirurgicos ?? [],
            'avaliacao_enfermagem' => $this->patientDetails->avaliacao_enf ?? 'Não realizada',
            'plano_educacional' => $this->patientDetails->plano_educ ?? 'Não realizado',
            'pe_data' => $this->patientDetails->pe_data ?? 'Não realizado',
            'historico_queda' => $this->patientDetails->ds_queda ?? 'Não avaliado'
        ];
    }

    public function changeShift($shift)
    {
        $this->currentShift = $shift;
    }

    public function render()
    {
        return view('livewire.patient-modal', [
            'activeAlerts' => $this->activeAlerts,
            'criticalAlertsCount' => $this->criticalAlertsCount,
            'hasPatientData' => $this->hasPatientData(),
            'cpoeData' => $this->cpoeData,
            'scalesData' => $this->scalesData,
            'clinicalData' => $this->clinicalData
        ]);
    }
}