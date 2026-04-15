<?php

namespace App\Livewire;

use App\Models\EMR\Core\Patient;
use App\Services\ShiftService;
use App\Services\Tasy\TasyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\On;
use Livewire\Component;

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

    /** @var array<int, array{nr_atendimento:int,label:string}> */
    public array $modalPatients = [];

    public ?int $currentPatientIndex = null;

    /** @var array|null Prescriptions data (medications, nutrition, orders, interventions, procedures, etc.) */
    public $prescriptions = null;

    public bool $planLoaded = false;

    public bool $planError = false;

    /** Date shown in the medication schedule grid (Y-m-d). Navigable with shiftScheduleDay(). */
    public string $scheduleDate = '';

    /** @var array Per-medication hour slots: [ med_id => [ 'HH:MI' => 'administered'|'scheduled'|'missed' ] ] */
    public array $medicationSchedule = [];

    // Model centralizada
    protected $patientModel;

    protected TasyService $tasyService;

    public function boot(TasyService $tasyService)
    {
        $this->patientModel = new Patient;
        $this->tasyService = $tasyService;
    }

    public function mount()
    {
        $this->currentShift = ShiftService::getShiftInfo()['shift'];
        $this->scheduleDate = now()->format('Y-m-d');
    }

    #[On('openModal')]
    public function openModal($attendanceNumber, $hospital = '', $sbarPatient = null, $patients = [])
    {
        if (! $attendanceNumber || $attendanceNumber == 0) {
            Log::warning('PatientModal: Invalid attendanceNumber', [
                'attendanceNumber' => $attendanceNumber,
            ]);

            return;
        }

        try {
            $this->resetModalState();
            $this->setModalPatients(is_array($patients) ? $patients : [], (int) $attendanceNumber);
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
                'shift' => $this->currentShift,
            ]);

            // Se o payload do SBAR foi passado, usa diretamente (evita re-fetch)
            if (! empty($sbarPatient) && is_array($sbarPatient)) {
                $this->loadFromSbarData($sbarPatient, $attendanceNumber);
            } else {
                $this->loadPatientData($attendanceNumber);
            }

        } catch (\Exception $e) {
            Log::error('PatientModal: Error in openModal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->loadingPatient = false;
            $this->showModal = false;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $patients
     */
    private function setModalPatients(array $patients, int $currentAttendance): void
    {
        $normalized = collect($patients)
            ->filter(fn ($patient) => is_array($patient))
            ->map(function (array $patient): array {
                $attendance = (int) ($patient['nr_atendimento'] ?? 0);
                $existingLabel = trim((string) ($patient['label'] ?? ''));

                return [
                    'nr_atendimento' => $attendance,
                    'label' => $existingLabel !== ''
                        ? $existingLabel
                        : $this->buildPatientNavigationLabel($patient, $attendance),
                ];
            })
            ->filter(fn (array $patient) => $patient['nr_atendimento'] > 0)
            ->unique('nr_atendimento')
            ->values()
            ->all();

        if (empty($normalized) && $currentAttendance > 0) {
            $normalized[] = [
                'nr_atendimento' => $currentAttendance,
                'label' => sprintf('Atendimento %d', $currentAttendance),
            ];
        }

        $this->modalPatients = $normalized;

        $currentIndex = collect($this->modalPatients)
            ->search(fn (array $patient) => (int) $patient['nr_atendimento'] === $currentAttendance);

        if ($currentIndex === false && $currentAttendance > 0) {
            $this->modalPatients[] = [
                'nr_atendimento' => $currentAttendance,
                'label' => sprintf('Atendimento %d', $currentAttendance),
            ];
            $currentIndex = count($this->modalPatients) - 1;
        }

        $this->currentPatientIndex = $currentIndex === false ? null : (int) $currentIndex;
    }

    /**
     * @param  array<string, mixed>  $patient
     */
    private function buildPatientNavigationLabel(array $patient, int $attendance): string
    {
        $patientName = trim((string) ($patient['nm_social'] ?? $patient['nm_pessoa_fisica'] ?? ''));
        $bed = trim((string) ($patient['cd_unidade_basica'] ?? ''));

        $parts = [];
        if ($bed !== '') {
            $parts[] = 'Leito '.$bed;
        }

        if ($patientName !== '') {
            $parts[] = $patientName;
        }

        if (empty($parts)) {
            $parts[] = 'Paciente';
        }

        return $attendance.' - '.implode(' - ', $parts);
    }

    private function loadFromSbarData(array $sbarData, int $attendanceNumber): void
    {
        try {
            $details = (object) $sbarData;

            // Busca campos que não vêm no payload do SBAR
            $details->alerts = [];
            $personId = $sbarData['cd_pessoa_fisica'] ?? null;
            if ($personId) {
                try {
                    $details->alerts = $this->tasyService->getPatientAlerts($attendanceNumber, (int) $personId);
                } catch (\Throwable $e) {
                    Log::warning('PatientModal: Failed to fetch alerts', [
                        'attendance' => $attendanceNumber,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $details->cid_history = $this->tasyService->getPatientCidHistory($attendanceNumber);
            } catch (\Throwable $e) {
                Log::warning('PatientModal: Failed to fetch CID history', [
                    'attendance' => $attendanceNumber,
                    'error' => $e->getMessage(),
                ]);
                $details->cid_history = [];
            }

            $this->patientDetails = $details;
            $this->patientAlerts = $details->alerts;

            $this->currentPatient = array_merge($this->currentPatient, [
                'cd_pessoa_fisica' => $sbarData['cd_pessoa_fisica'] ?? null,
                'nm_pessoa_fisica' => $sbarData['nm_pessoa_fisica'] ?? 'Paciente',
                'nm_social' => $sbarData['nm_social'] ?? null,
                'nr_prontuario' => $sbarData['nr_prontuario'] ?? 'N/A',
                'age_detailed' => $sbarData['age_detailed'] ?? 'N/A',
                'sexo' => $sbarData['sexo'] ?? 'N/A',
                'convenio' => $sbarData['convenio'] ?? 'N/A',
                'hospital_name' => $sbarData['hospital_name'] ?? $this->currentHospitalName,
            ]);

            $this->checkAndShowAlertsModal();

        } catch (\Throwable $e) {
            Log::warning('PatientModal: Failed to load from SBAR data, falling back to DB', [
                'attendance' => $attendanceNumber,
                'error' => $e->getMessage(),
            ]);
            $this->loadPatientData($attendanceNumber);

            return;
        }

        $this->loadingPatient = false;
        $this->loadPrescriptionsData($attendanceNumber);

        $this->dispatch('patient-data-loaded', [
            'patientId' => $attendanceNumber,
            'shift' => $this->currentShift,
            'success' => true,
            'hasAlerts' => ! empty($this->patientAlerts),
        ]);
    }

    /**
     * Retries loading prescriptions after a failure.
     * Clears the (possibly corrupt) cache entry first.
     */
    public function reloadPrescriptions(): void
    {
        if (! $this->currentPatient) {
            return;
        }

        $nr = (int) ($this->currentPatient['nr_atendimento'] ?? 0);
        if (! $nr) {
            return;
        }

        $this->tasyService->clearPatientCache($nr);

        $this->planLoaded = false;
        $this->planError = false;
        $this->loadPrescriptionsData($nr);
    }

    /**
     * Advances or retreats the medication schedule grid by N days.
     * Only the schedule grid re-fetches; the therapeutic plan stays cached.
     */
    public function shiftScheduleDay(int $delta): void
    {
        if (! $this->currentPatient) {
            return;
        }

        // Day navigation is disabled in UI: medication schedule is always locked to today.
        $this->scheduleDate = now()->format('Y-m-d');

        $this->loadMedicationSchedule();
    }

    /**
     * Loads patient prescriptions from TasyService (Redis cache first, Oracle fallback).
     * Called synchronously inside openModal so data is ready on first render.
     */
    private function loadPrescriptionsData(int $nr): void
    {
        if (! $nr) {
            return;
        }

        try {
            $this->prescriptions = $this->tasyService->getPatientPrescriptions($nr);
            $this->planLoaded = true;
            $this->planError = false;
            $this->loadMedicationSchedule();
        } catch (\Throwable $e) {
            Log::error('PatientModal: Failed to load patient prescriptions', [
                'attendance' => $nr,
                'error' => $e->getMessage(),
            ]);
            $this->prescriptions = null;
            $this->planLoaded = false;
            $this->planError = true;
        }
    }

    private function loadMedicationSchedule(): void
    {
        $nr = (int) ($this->currentPatient['nr_atendimento'] ?? 0);
        if (! $nr || ! $this->scheduleDate) {
            return;
        }

        try {
            $this->medicationSchedule = $this->tasyService->getMedicationSchedule($nr, $this->scheduleDate);
        } catch (\Throwable $e) {
            Log::warning('PatientModal: Failed to load medication schedule', [
                'attendance' => $nr,
                'date' => $this->scheduleDate,
                'error' => $e->getMessage(),
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
                    'nm_social' => $this->patientDetails->nm_social ?? null,
                    'nr_prontuario' => $this->patientDetails->nr_prontuario ?? 'N/A',
                    'age_detailed' => $this->patientDetails->age_detailed ?? 'N/A',
                    'sexo' => $this->patientDetails->sexo ?? 'N/A',
                    'convenio' => $this->patientDetails->convenio ?? 'N/A',
                    'hospital_name' => $this->patientDetails->hospital_name ?? $this->currentHospitalName,
                ]);

                // Verifica e mostra alertas se necessário
                $this->checkAndShowAlertsModal();
            } else {
                Log::warning('PatientModal: No patient data found', [
                    'attendanceNumber' => $attendanceNumber,
                ]);

                $this->patientAlerts = [];

                // Mantém dados básicos mesmo sem detalhes
                $this->currentPatient = array_merge($this->currentPatient, [
                    'nm_pessoa_fisica' => 'Paciente',
                    'nr_prontuario' => 'N/A',
                    'age_detailed' => 'N/A',
                    'sexo' => 'N/A',
                    'convenio' => 'N/A',
                    'hospital_name' => $this->currentHospitalName,
                ]);
            }

            $this->loadingPatient = false;
            $this->loadPrescriptionsData($attendanceNumber);

            $this->dispatch('patient-data-loaded', [
                'patientId' => $attendanceNumber,
                'shift' => $this->currentShift,
                'success' => true,
                'hasAlerts' => ! empty($this->patientAlerts),
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

        Log::error('PatientModal: Erro ao carregar dados do paciente', [
            'attendance_number' => $attendanceNumber,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Define dados básicos para exibir mensagem de erro
        if (! $this->currentPatient) {
            $this->currentPatient = [
                'nr_atendimento' => $attendanceNumber,
                'has_patient' => true,
                'nm_pessoa_fisica' => 'Erro ao carregar',
                'nr_prontuario' => 'N/A',
                'age_detailed' => 'N/A',
                'sexo' => 'N/A',
                'convenio' => 'N/A',
                'hospital_name' => $this->currentHospitalName,
            ];
        }

        $this->dispatch('patient-data-loaded', [
            'patientId' => $attendanceNumber,
            'shift' => $this->currentShift,
            'success' => false,
            'error' => 'Erro ao carregar dados do paciente. Por favor, tente novamente.',
        ]);
    }

    private function checkAndShowAlertsModal()
    {
        if ($this->activeAlerts->isNotEmpty()) {
            $this->showAlertsModal = true;
        }
    }

    private function filterActiveAlerts(array $alerts): Collection
    {
        return collect($alerts)->filter(function ($alert) {
            $endDate = $alert['end_date'] ?? null;

            return ! $endDate || Carbon::parse($endDate)->isFuture();
        });
    }

    private function parsePipeSeparatedList(?string $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return collect(preg_split('/\|+/', $value) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function parseAllergyItems(?string $value): array
    {
        $cleaned = trim((string) ($value ?? ''));
        if ($cleaned === '') {
            return [];
        }

        $cleaned = preg_replace('/\s*-\s*(Não informado|desconhecido|N\/A)[^;]*/iu', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/;\s*;/', ';', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned, '; ');

        if ($cleaned === '') {
            return [];
        }

        $items = collect(explode(';', $cleaned))
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->values();

        return $items->map(function (string $item) {
            if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $item, $matches)) {
                return [
                    'med' => trim($matches[1]),
                    'grav' => trim($matches[2]),
                ];
            }

            return ['text' => $item];
        })->all();
    }

    private function resetModalState()
    {
        $this->currentPatient = null;
        $this->currentHospitalName = '';
        $this->patientDetails = null;
        $this->patientAlerts = [];
        $this->showAlertsModal = false;
        $this->loadingPatient = false;

        $this->prescriptions = null;
        $this->planLoaded = false;
        $this->planError = false;
        $this->scheduleDate = now()->format('Y-m-d');
        $this->medicationSchedule = [];
        $this->modalPatients = [];
        $this->currentPatientIndex = null;
    }

    public function goToPreviousPatient(): void
    {
        if (! $this->canGoPrevious) {
            return;
        }

        $this->goToPatientByIndex((int) $this->currentPatientIndex - 1);
    }

    public function goToNextPatient(): void
    {
        if (! $this->canGoNext) {
            return;
        }

        $this->goToPatientByIndex((int) $this->currentPatientIndex + 1);
    }

    public function goToPatientByAttendance(int|string $attendanceNumber): void
    {
        $attendance = (int) $attendanceNumber;
        if ($attendance <= 0) {
            return;
        }

        if ((int) ($this->currentPatient['nr_atendimento'] ?? 0) === $attendance) {
            return;
        }

        $targetIndex = collect($this->modalPatients)
            ->search(fn (array $patient) => (int) ($patient['nr_atendimento'] ?? 0) === $attendance);

        if ($targetIndex === false) {
            return;
        }

        $this->goToPatientByIndex((int) $targetIndex);
    }

    private function goToPatientByIndex(int $targetIndex): void
    {
        $targetPatient = $this->modalPatients[$targetIndex] ?? null;
        if (! $targetPatient) {
            return;
        }

        $this->currentPatientIndex = $targetIndex;
        $this->openModal((int) $targetPatient['nr_atendimento'], $this->currentHospitalName, null, $this->modalPatients);
    }

    public function refreshPatientData()
    {
        if (! $this->currentPatient || ! isset($this->currentPatient['nr_atendimento'])) {
            Log::warning('PatientModal: Cannot refresh - no current patient');

            return;
        }

        $this->loadingPatient = true;

        $this->patientModel->clearPatientCache($this->currentPatient['nr_atendimento']);

        $this->prescriptions = null;
        $this->planLoaded = false;
        $this->planError = false;
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

    public function openAlertsModal(): void
    {
        if ($this->activeAlerts->isEmpty()) {
            return;
        }

        $this->showAlertsModal = true;
    }

    public function hasPatientData()
    {
        return $this->patientDetails !== null;
    }

    public function getActiveAlertsProperty()
    {
        return $this->filterActiveAlerts($this->patientAlerts ?? []);
    }

    public function getCriticalAlertsCountProperty()
    {
        return $this->activeAlerts->filter(function ($alert) {
            return ($alert['severity'] ?? 'warning') === 'danger' ||
                ($alert['type'] ?? '') === 'ALERTA';
        })->count();
    }

    public function getCanGoPreviousProperty(): bool
    {
        return $this->currentPatientIndex !== null && $this->currentPatientIndex > 0;
    }

    public function getCanGoNextProperty(): bool
    {
        return $this->currentPatientIndex !== null
            && $this->currentPatientIndex < (count($this->modalPatients) - 1);
    }

    public function getAlertsGroupedByTypeProperty(): array
    {
        return $this->activeAlerts
            ->map(function ($alert) {
                $alert['sent_date_formatted'] = $this->formatAlertDateTime($alert['sent_date'] ?? null);
                $alert['start_date_formatted'] = $this->formatAlertDate($alert['start_date'] ?? null);
                $alert['end_date_formatted'] = $this->formatAlertDate($alert['end_date'] ?? null);

                return $alert;
            })
            ->groupBy('type')
            ->map(fn ($alerts) => $alerts->values()->all())
            ->all();
    }

    private function formatAlertDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatAlertDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Pre-computes all data needed by plan.blade.php so the template contains no logic.
     *
     * @return array{tabs: array, default_tab: string, date_label: string, date_badge: string|null, time_columns: string[], current_hour: string}
     */
    public function getPlanDisplayDataProperty(): array
    {
        $plan = $this->prescriptions ?? [];

        $allProcedureItems = collect($plan['procedures']['items'] ?? []);
        $examCount = $allProcedureItems->filter(function (array $item) {
            $eventType = strtolower((string) ($item['event_type'] ?? ''));
            $type = strtolower((string) ($item['type'] ?? ''));

            return $eventType === 'exame'
                || str_contains($type, 'exame')
                || str_contains($type, 'laborat');
        })->count();

        $counts = [
            'tab-med' => $plan['medications']['count'] ?? 0,
            'tab-exam' => $examCount,
            'tab-proc' => $allProcedureItems->count() - $examCount,
            'tab-surg' => $plan['surgery']['count'] ?? 0,
            'tab-hemo' => $plan['hemotherapy']['count'] ?? 0,
            'tab-chemo' => $plan['chemotherapy']['count'] ?? 0,
            'tab-nut' => $plan['nutrition']['count'] ?? 0,
            'tab-rec' => $plan['orders']['count'] ?? 0,
            'tab-int' => $plan['interventions']['count'] ?? 0,
            'tab-gas' => $plan['gasotherapy']['count'] ?? 0,
            'tab-dial' => $plan['dialysis']['count'] ?? 0,
        ];

        $defaultTab = 'tab-med';
        foreach ($counts as $tabId => $count) {
            if ($count > 0) {
                $defaultTab = $tabId;
                break;
            }
        }

        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        $dateBadge = match ($this->scheduleDate) {
            $today => 'Hoje',
            $yesterday => 'Ontem',
            $tomorrow => 'Amanhã',
            default => null,
        };

        $timeColumns = array_map(fn (int $h) => str_pad($h, 2, '0', STR_PAD_LEFT).':00', range(0, 23));

        $alpinePayload = [
            'meds' => $plan['medications']['items'] ?? [],
            'schedule' => $this->medicationSchedule,
            'time_columns' => $timeColumns,
            'current_hour' => now()->format('H').':00',
            'procedures' => $plan['procedures']['items'] ?? [],
            'orders' => $plan['orders']['items'] ?? [],
            'interventions' => $plan['interventions']['items'] ?? [],
            'hemotherapy' => $plan['hemotherapy']['items'] ?? [],
            'surgery' => $plan['surgery']['items'] ?? [],
            'nutrition' => $plan['nutrition']['items'] ?? [],
            'chemotherapy' => $plan['chemotherapy']['items'] ?? [],
            'gasotherapy' => $plan['gasotherapy']['items'] ?? [],
            'dialysis' => $plan['dialysis']['items'] ?? [],
        ];

        return [
            'counts' => $counts,
            'default_tab' => $defaultTab,
            'date_label' => Carbon::parse($this->scheduleDate)->format('d/m/Y'),
            'date_badge' => $dateBadge,
            'time_columns' => $timeColumns,
            'current_hour' => now()->format('H').':00',
            'alpine_payload' => $alpinePayload,
        ];
    }

    public function getScalesDataProperty()
    {
        if (! $this->patientDetails) {
            return null;
        }

        $isPediatric = isset($this->patientDetails->age) && intval($this->patientDetails->age) < 18;
        $scales = [];

        // MEWS (adultos) ou PEWS (pediátricos)
        if (! $isPediatric) {
            $scales['mews'] = [
                'score' => $this->patientDetails->mews_score ?? null,
                'timestamp' => $this->patientDetails->mews_timestamp ?? null,
                'classification' => $this->patientDetails->mews_classification ?? null,
                'increased' => $this->patientDetails->mews_increased ?? false,
                'needs_assessment' => $this->patientDetails->mews_needs_assessment ?? true,
            ];
        } else {
            $scales['pews'] = [
                'score' => $this->patientDetails->pews_score ?? null,
                'timestamp' => $this->patientDetails->pews_timestamp ?? null,
                'classification' => $this->patientDetails->pews_classification ?? null,
                'increased' => $this->patientDetails->pews_increased ?? false,
                'needs_assessment' => $this->patientDetails->pews_needs_assessment ?? true,
            ];
        }

        // Escalas comuns
        $scales['braden'] = [
            'score' => $this->patientDetails->braden_score ?? null,
            'timestamp' => $this->patientDetails->braden_timestamp ?? null,
            'classification' => $this->patientDetails->braden_classification ?? null,
            'increased' => $this->patientDetails->braden_increased ?? false,
            'needs_assessment' => $this->patientDetails->braden_needs_assessment ?? true,
        ];

        $scales['morse'] = [
            'score' => $this->patientDetails->morse_score ?? null,
            'timestamp' => $this->patientDetails->morse_timestamp ?? null,
            'classification' => $this->patientDetails->morse_classification ?? null,
            'increased' => $this->patientDetails->morse_increased ?? false,
            'needs_assessment' => $this->patientDetails->morse_needs_assessment ?? true,
        ];

        $scales['pain'] = [
            'score' => $this->patientDetails->pain_score ?? null,
            'timestamp' => $this->patientDetails->pain_timestamp ?? null,
            'classification' => $this->patientDetails->pain_classification ?? null,
            'increased' => $this->patientDetails->pain_increased ?? false,
            'needs_assessment' => $this->patientDetails->pain_needs_assessment ?? true,
        ];

        $scales['vte'] = [
            'score' => $this->patientDetails->vte_score ?? null,
            'timestamp' => $this->patientDetails->vte_timestamp ?? null,
            'classification' => $this->patientDetails->vte_classification ?? null,
            'increased' => $this->patientDetails->vte_increased ?? false,
            'needs_assessment' => $this->patientDetails->vte_needs_assessment ?? true,
        ];

        return $scales;
    }

    public function getClinicalDataProperty()
    {
        if (! $this->patientDetails) {
            return [
                'diagnosticos' => 'Sem diagnósticos registrados',
                'diagnosticos_list' => [],
                'isolamento' => 'Não',
                'motivos_isolamento' => 'Nenhum motivo de isolamento',
                'dispositivos' => 'Nenhum dispositivo registrado',
                'dispositivos_list' => [],
                'alergias' => 'Sem alergias registradas',
                'alergias_items' => [],
                'antimicrobianos' => 'Nenhum antimicrobiano',
                'cirurgias' => [],
                'avaliacao_enfermagem' => 'Não realizada',
                'plano_educacional' => 'Não realizado',
                'pe_data' => 'Não realizado',
                'historico_queda' => 'Não avaliado',
                'cid_history' => [],
            ];
        }

        $diagnosticos = (string) ($this->patientDetails->diagnosticos_comorbidades ?? 'Sem diagnósticos registrados');
        $dispositivos = (string) ($this->patientDetails->dispositivos ?? 'Nenhum dispositivo registrado');
        $alergias = (string) ($this->patientDetails->alergias_detalhadas ?? 'Sem alergias registradas');

        return [
            'diagnosticos' => $diagnosticos,
            'diagnosticos_list' => $this->parsePipeSeparatedList($diagnosticos),
            'isolamento' => $this->patientDetails->medida_bloqueio ?? 'Não',
            'motivos_isolamento' => $this->patientDetails->motivos_isolamento ?? 'Nenhum motivo de isolamento',
            'dispositivos' => $dispositivos,
            'dispositivos_list' => $this->parsePipeSeparatedList($dispositivos),
            'alergias' => $alergias,
            'alergias_items' => $this->parseAllergyItems($alergias),
            'antimicrobianos' => $this->patientDetails->materiais ?? 'Nenhum antimicrobiano',
            'cirurgias' => $this->patientDetails->procedimentos_cirurgicos ?? [],
            'avaliacao_enfermagem' => $this->patientDetails->avaliacao_enf ?? 'Não realizada',
            'plano_educacional' => $this->patientDetails->plano_educ ?? 'Não realizado',
            'pe_data' => $this->patientDetails->pe_data ?? 'Não realizado',
            'historico_queda' => $this->patientDetails->ds_queda ?? 'Não avaliado',
            'cid_history' => $this->patientDetails->cid_history ?? [],
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
            'alertsGroupedByType' => $this->alertsGroupedByType,
            'criticalAlertsCount' => $this->criticalAlertsCount,
            'hasPatientData' => $this->hasPatientData(),
            'scalesData' => $this->scalesData,
            'clinicalData' => $this->clinicalData,
            'planDisplayData' => $this->planDisplayData,
            'modalPatients' => $this->modalPatients,
            'currentPatientIndex' => $this->currentPatientIndex,
            'canGoPrevious' => $this->canGoPrevious,
            'canGoNext' => $this->canGoNext,
        ]);
    }
}
