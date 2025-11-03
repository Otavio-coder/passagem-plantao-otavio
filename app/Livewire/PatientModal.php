<?php
namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\EMR\Core\Patient;
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

    public $cpoeData = null;
    public $cpoeLoaded = false;
    public $cpoeLoading = false;
    public $cpoeExpanded = false;

    // Model centralizada
    protected $patientModel;

    public function boot()
    {
        $this->patientModel = new Patient();
    }

    public function mount()
    {
        $this->currentShift = $this->getCurrentShift();
    }

    private function getCurrentShift()
    {
        $now = now();
        $hour = $now->hour;
        $minute = $now->minute;
        $time = $hour * 60 + $minute;

        if ($time >= 435 && $time < 795) {
            return 'manha';
        } elseif ($time >= 795 && $time < 1155) {
            return 'tarde';
        } else {
            return 'noite';
        }
    }

    #[On('openModal')]
    public function openModal($attendanceNumber, $hospital = '')
    {
        if (!$attendanceNumber || $attendanceNumber == 0) {
            Log::warning('PatientModal: Invalid attendanceNumber', [
                'attendanceNumber' => $attendanceNumber
            ]);
            return;
        }

        try {
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

            // Carrega dados do paciente (SEM CPOE)
            $this->loadPatientData($attendanceNumber);

        } catch (\Exception $e) {
            Log::error('PatientModal: Error in openModal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->loadingPatient = false;
            $this->showModal = false;
        }
    }

    #[On('loadCpoeData')]
    public function loadCpoeData()
    {
        if (!$this->currentPatient || !isset($this->currentPatient['nr_atendimento'])) {
            Log::warning('PatientModal: Cannot load CPOE - no current patient');
            return;
        }

        if ($this->cpoeLoaded) {
            // Se já carregou, apenas expande/colapsa
            $this->cpoeExpanded = !$this->cpoeExpanded;
            return;
        }

        try {
            $this->cpoeLoading = true;

            $startTime = microtime(true);

            // Carrega apenas dados CPOE
            $this->cpoeData = $this->patientModel->getPatientCPOEOnly($this->currentPatient['nr_atendimento']);

            $loadTime = microtime(true) - $startTime;


            if ($this->patientDetails) {
                $this->patientDetails->cpoe_procedures = $this->cpoeData->cpoe_procedures ?? null;
                $this->patientDetails->cpoe_medications = $this->cpoeData->cpoe_medications ?? null;
                $this->patientDetails->cpoe_nutrition = $this->cpoeData->cpoe_nutrition ?? null;
                $this->patientDetails->cpoe_recommendations = $this->cpoeData->cpoe_recommendations ?? null;
                $this->patientDetails->cpoe_interventions = $this->cpoeData->cpoe_interventions ?? null;
            }

            $this->cpoeLoaded = true;
            $this->cpoeExpanded = true;

            $this->dispatch('cpoe-data-loaded', [
                'success' => true,
                'loadTime' => round($loadTime, 2)
            ]);

        } catch (\Exception $e) {
            Log::error('PatientModal: Error loading CPOE data', [
                'error' => $e->getMessage(),
                'attendance_number' => $this->currentPatient['nr_atendimento'],
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('cpoe-data-loaded', [
                'success' => false,
                'error' => 'Erro ao carregar prescrições'
            ]);

        } finally {
            $this->cpoeLoading = false;
        }
    }

    private function loadPatientData($attendanceNumber)
    {
        try {
            // Limpa cache antes de buscar dados atualizados
            $this->patientModel->clearPatientCache($attendanceNumber);

            // ✅ Busca dados completos do paciente SEM CPOE (mais rápido)
            $this->patientDetails = $this->patientModel->getFullPatientDataWithoutCPOE($attendanceNumber);

            if ($this->patientDetails) {
                // Extrai alertas dos dados completos
                $this->patientAlerts = $this->patientDetails->alerts ?? [];

                // Atualiza dados do paciente atual
                $this->currentPatient = array_merge($this->currentPatient, [
                    'nm_pessoa_fisica' => $this->patientDetails->nm_pessoa_fisica ?? 'Paciente',
                    'nr_prontuario' => $this->patientDetails->nr_prontuario ?? 'N/A',
                    'age_detailed' => $this->patientDetails->age_detailed ?? 'N/A',
                    'sexo' => $this->patientDetails->sexo ?? 'N/A',
                    'convenio' => $this->patientDetails->convenio ?? 'N/A',
                    'hospital_name' => $this->patientDetails->hospital_name ?? $this->currentHospitalName
                ]);

                // Verifica e mostra alertas se necessário
                $this->checkAndShowAlertsModal();
            } else {
                Log::warning('PatientModal: No patient data found', [
                    'attendanceNumber' => $attendanceNumber
                ]);

                $this->patientAlerts = [];

                // Mantém dados básicos mesmo sem detalhes
                $this->currentPatient = array_merge($this->currentPatient, [
                    'nm_pessoa_fisica' => 'Paciente',
                    'nr_prontuario' => 'N/A',
                    'age_detailed' => 'N/A',
                    'sexo' => 'N/A',
                    'convenio' => 'N/A',
                    'hospital_name' => $this->currentHospitalName
                ]);
            }

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

        // Define dados básicos para exibir mensagem de erro
        if (!$this->currentPatient) {
            $this->currentPatient = [
                'nr_atendimento' => $attendanceNumber,
                'has_patient' => true,
                'nm_pessoa_fisica' => 'Erro ao carregar',
                'nr_prontuario' => 'N/A',
                'age_detailed' => 'N/A',
                'sexo' => 'N/A',
                'convenio' => 'N/A',
                'hospital_name' => $this->currentHospitalName
            ];
        }

        $this->dispatch('patient-data-loaded', [
            'patientId' => $attendanceNumber,
            'shift' => $this->currentShift,
            'success' => false,
            'error' => 'Erro ao carregar dados do paciente. Por favor, tente novamente.'
        ]);
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

        $this->cpoeData = null;
        $this->cpoeLoaded = false;
        $this->cpoeLoading = false;
        $this->cpoeExpanded = false;
    }

    public function refreshPatientData()
    {
        if (!$this->currentPatient || !isset($this->currentPatient['nr_atendimento'])) {
            Log::warning('PatientModal: Cannot refresh - no current patient');
            return;
        }

        $this->loadingPatient = true;

        // Limpa cache antes de recarregar
        $this->patientModel->clearPatientCache($this->currentPatient['nr_atendimento']);

        // ✅ Se CPOE estava carregado, limpa também
        if ($this->cpoeLoaded) {
            $this->cpoeData = null;
            $this->cpoeLoaded = false;
            $this->cpoeExpanded = false;
        }

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

    public function hasPatientData()
    {
        return $this->patientDetails !== null;
    }

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

    public function getCriticalAlertsCountProperty()
    {
        return $this->activeAlerts->filter(function($alert) {
            return ($alert['severity'] ?? 'warning') === 'danger' ||
                ($alert['type'] ?? '') === 'ALERTA';
        })->count();
    }

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

    public function getClinicalDataProperty()
    {
        if (!$this->patientDetails) {
            return [
                'diagnosticos' => 'Sem diagnósticos registrados',
                'isolamento' => 'Não',
                'motivos_isolamento' => 'Nenhum motivo de isolamento',
                'dispositivos' => 'Nenhum dispositivo registrado',
                'alergias' => 'Sem alergias registradas',
                'antimicrobianos' => 'Nenhum antimicrobiano',
                'exames_prioritarios' => 'Nenhum exame prioritário',
                'cirurgias' => [],
                'avaliacao_enfermagem' => 'Não realizada',
                'plano_educacional' => 'Não realizado',
                'pe_data' => 'Não realizado',
                'historico_queda' => 'Não avaliado'
            ];
        }

        return [
            'diagnosticos' => $this->patientDetails->diagnosticos_comorbidades ?? 'Sem diagnósticos registrados',
            'isolamento' => $this->patientDetails->medida_bloqueio ?? 'Não',
            'motivos_isolamento' => $this->patientDetails->motivos_isolamento ?? 'Nenhum motivo de isolamento',
            'dispositivos' => $this->patientDetails->dispositivos ?? 'Nenhum dispositivo registrado',
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
            'scalesData' => $this->scalesData,
            'clinicalData' => $this->clinicalData,
            'cpoeLoaded' => $this->cpoeLoaded,
            'cpoeLoading' => $this->cpoeLoading,
            'cpoeExpanded' => $this->cpoeExpanded,
        ]);
    }
}
