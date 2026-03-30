<?php
namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\On;
use App\Models\EMR\Core\Patient;
use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Isolate]
class PatientModal extends Component
{
    public $showModal = false;
    public $currentPatient = null;
    public $currentHospitalName = '';
    public $patientDetails = null;
    public $patientAlerts = [];
    public $showAlertsModal = false;
    public $currentShift = 'morning';
    public $loadingPatient = false;

    /** @var array|null Therapeutic plan data (medications, nutrition, orders, interventions, procedures) */
    public $therapeuticPlan = null;
    public bool $planLoaded = false;
    public bool $planError  = false;

    /** Date shown in the medication schedule grid (Y-m-d). Navigable with shiftScheduleDay(). */
    public string $scheduleDate = '';
    /** @var array Per-medication hour slots: [ med_id => [ 'HH:MI' => 'administered'|'scheduled'|'missed' ] ] */
    public array $medicationSchedule = [];

    // Model centralizada
    protected $patientModel;
    protected TasyService $tasyService;

    public function boot(TasyService $tasyService)
    {
        $this->patientModel = new Patient();
        $this->tasyService = $tasyService;
    }

    public function mount()
    {
        $this->currentShift = $this->getCurrentShift();
        $this->scheduleDate = now()->format('Y-m-d');
    }

    private function getCurrentShift()
    {
        $now = now();
        $hour = $now->hour;
        $minute = $now->minute;
        $time = $hour * 60 + $minute;

        if ($time >= 435 && $time < 795) {
            return 'morning';
        } elseif ($time >= 795 && $time < 1155) {
            return 'afternoon';
        } else {
            return 'night';
        }
    }

    #[On('openModal')]
    public function openModal($attendanceNumber, $hospital = '', $sbarPatient = null)
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

            // Se o payload do SBAR foi passado, usa diretamente (evita re-fetch)
            if (!empty($sbarPatient) && is_array($sbarPatient)) {
                $this->loadFromSbarData($sbarPatient, $attendanceNumber);
            } else {
                $this->loadPatientData($attendanceNumber);
            }

        } catch (\Exception $e) {
            Log::error('PatientModal: Error in openModal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->loadingPatient = false;
            $this->showModal = false;
        }
    }

    private function loadFromSbarData(array $sbarData, int $attendanceNumber): void
    {
        try {
            $details = (object) $sbarData;

            // O SBAR usa 'priority_exams', o modal espera 'prioridade_exames'
            $details->prioridade_exames = $sbarData['priority_exams'] ?? null;

            // Busca apenas os alertas (não vêm no payload do SBAR)
            $details->alerts = [];
            $personId = $sbarData['cd_pessoa_fisica'] ?? null;
            if ($personId) {
                try {
                    $details->alerts = $this->tasyService->getPatientAlerts($attendanceNumber, (int) $personId);
                } catch (\Throwable $e) {
                    Log::warning('PatientModal: Failed to fetch alerts', [
                        'attendance' => $attendanceNumber,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->patientDetails = $details;
            $this->patientAlerts = $details->alerts;

            $this->currentPatient = array_merge($this->currentPatient, [
                'nm_pessoa_fisica' => $sbarData['nm_pessoa_fisica'] ?? 'Paciente',
                'nm_social'        => $sbarData['nm_social'] ?? null,
                'nr_prontuario'    => $sbarData['nr_prontuario'] ?? 'N/A',
                'age_detailed'     => $sbarData['age_detailed'] ?? 'N/A',
                'sexo'             => $sbarData['sexo'] ?? 'N/A',
                'convenio'         => $sbarData['convenio'] ?? 'N/A',
                'hospital_name'    => $sbarData['hospital_name'] ?? $this->currentHospitalName,
            ]);

            $this->checkAndShowAlertsModal();

        } catch (\Throwable $e) {
            Log::warning('PatientModal: Failed to load from SBAR data, falling back to DB', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage()
            ]);
            $this->loadPatientData($attendanceNumber);
            return;
        }

        $this->loadingPatient = false;
        $this->loadTherapeuticPlanData($attendanceNumber);

        $this->dispatch('patient-data-loaded', [
            'patientId'  => $attendanceNumber,
            'shift'      => $this->currentShift,
            'success'    => true,
            'hasAlerts'  => !empty($this->patientAlerts),
        ]);
    }

    /**
     * Retries loading the therapeutic plan after a failure.
     * Clears the (possibly corrupt) cache entry first.
     */
    public function reloadTherapeuticPlan(): void
    {
        if (!$this->currentPatient) return;

        $nr = (int) ($this->currentPatient['nr_atendimento'] ?? 0);
        if (!$nr) return;

        Cache::forget("patient_therapeutic_plan_{$nr}");
        Cache::forget("patient_therapeutic_plan_v2_{$nr}");

        $this->planLoaded = false;
        $this->planError  = false;
        $this->loadTherapeuticPlanData($nr);
    }

    /**
     * Advances or retreats the medication schedule grid by N days.
     * Only the schedule grid re-fetches; the therapeutic plan stays cached.
     */
    public function shiftScheduleDay(int $delta): void
    {
        if (!$this->currentPatient) return;

        // Day navigation is disabled in UI: medication schedule is always locked to today.
        $this->scheduleDate = now()->format('Y-m-d');

        $this->loadMedicationSchedule();
    }

    /**
     * Loads the therapeutic plan from TasyService (Redis cache first, Oracle fallback).
     * Called synchronously inside openModal so data is ready on first render.
     */
    private function loadTherapeuticPlanData(int $nr): void
    {
        if (!$nr) return;

        try {
            $this->therapeuticPlan = $this->tasyService->getTherapeuticPlan($nr);
            $this->planLoaded      = true;
            $this->planError       = false;
            $this->loadMedicationSchedule();
        } catch (\Throwable $e) {
            Log::error('PatientModal: Failed to load therapeutic plan', [
                'attendance' => $nr,
                'error'      => $e->getMessage(),
            ]);
            $this->therapeuticPlan = null;
            $this->planLoaded      = false;
            $this->planError       = true;
        }
    }

    private function loadMedicationSchedule(): void
    {
        $nr = (int) ($this->currentPatient['nr_atendimento'] ?? 0);
        if (!$nr || !$this->scheduleDate) return;

        try {
            $this->medicationSchedule = $this->tasyService->getMedicationSchedule($nr, $this->scheduleDate);
        } catch (\Throwable $e) {
            Log::warning('PatientModal: Failed to load medication schedule', [
                'attendance' => $nr,
                'date'       => $this->scheduleDate,
                'error'      => $e->getMessage(),
            ]);
            $this->medicationSchedule = [];
        }
    }

    private function loadPatientData($attendanceNumber)
    {
        try {
            $this->patientDetails = $this->patientModel->getFullPatientDataWithoutCPOE($attendanceNumber);

            if ($this->patientDetails) {
                // Extrai alertas dos dados completos
                $this->patientAlerts = $this->patientDetails->alerts ?? [];

                // Atualiza dados do paciente atual
                $this->currentPatient = array_merge($this->currentPatient, [
                    'cd_pessoa_fisica' => $this->patientDetails->cd_pessoa_fisica ?? null,
                    'nm_pessoa_fisica' => $this->patientDetails->nm_pessoa_fisica ?? 'Paciente',
                    'nm_social'        => $this->patientDetails->nm_social ?? null,
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
            $this->loadTherapeuticPlanData($attendanceNumber);

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
        $this->currentPatient      = null;
        $this->currentHospitalName = '';
        $this->patientDetails      = null;
        $this->patientAlerts       = [];
        $this->showAlertsModal     = false;
        $this->loadingPatient      = false;

        $this->therapeuticPlan     = null;
        $this->planLoaded          = false;
        $this->planError           = false;
        $this->scheduleDate        = now()->format('Y-m-d');
        $this->medicationSchedule  = [];
    }

    public function refreshPatientData()
    {
        if (!$this->currentPatient || !isset($this->currentPatient['nr_atendimento'])) {
            Log::warning('PatientModal: Cannot refresh - no current patient');
            return;
        }

        $this->loadingPatient = true;

        $this->patientModel->clearPatientCache($this->currentPatient['nr_atendimento']);

        $this->therapeuticPlan    = null;
        $this->planLoaded         = false;
        $this->planError          = false;
        $this->medicationSchedule = [];

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

        $scales['pain'] = [
            'score' => $this->patientDetails->pain_score ?? null,
            'timestamp' => $this->patientDetails->pain_timestamp ?? null,
            'classification' => $this->patientDetails->pain_classification ?? null,
            'increased' => $this->patientDetails->pain_increased ?? false,
            'needs_assessment' => $this->patientDetails->pain_needs_assessment ?? true
        ];

        $scales['vte'] = [
            'score' => $this->patientDetails->vte_score ?? null,
            'timestamp' => $this->patientDetails->vte_timestamp ?? null,
            'classification' => $this->patientDetails->vte_classification ?? null,
            'increased' => $this->patientDetails->vte_increased ?? false,
            'needs_assessment' => $this->patientDetails->vte_needs_assessment ?? true
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
        return view('sbar.patient.modal.index', [
            'activeAlerts' => $this->activeAlerts,
            'criticalAlertsCount' => $this->criticalAlertsCount,
            'hasPatientData' => $this->hasPatientData(),
            'scalesData' => $this->scalesData,
            'clinicalData' => $this->clinicalData,
        ]);
    }
}
