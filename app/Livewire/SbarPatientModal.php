<?php

namespace App\Livewire;

use App\Models\EMR\Core\Patient;
use App\Models\ShiftHandover;
use App\Services\PatientData\PatientDataLoader;
use App\Services\ShiftService;
use App\Services\Tasy\TasyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\On;
use Livewire\Component;

#[Isolate]
class SbarPatientModal extends Component
{
    public $showModal = false;

    public $currentPatient = null;

    public $currentHospitalName = '';

    public $patientDetails = null;

    public $patientAlerts = [];

    public $showAlertsModal = false;

    public $currentShift = 'morning';

    public $loadingPatient = false;

    /** @var array<int, array{nr_atendimento:int,label:string,sbar_payload?:array<string,mixed>}> */
    public array $modalPatients = [];

    public ?int $currentPatientIndex = null;

    /** @var array|null Prescriptions data (medications, nutrition, orders, interventions, procedures, etc.) */
    public $prescriptions = null;

    public bool $planLoaded = false;

    public bool $planError = false;

    /** Date shown in the medication schedule grid (Y-m-d). Navigable with shiftScheduleDay(). */
    public string $scheduleDate = '';

    /** Whether the modal is locked in nurse handover mode. */
    public bool $handoverMode = false;

    /** ISO timestamp when the handover session started, used for the timer. */
    public string $handoverStartedAt = '';

    /** Session token for multi-tab safety (passed through finish/cancel events). */
    public string $handoverSessionToken = '';

    /** Attendance numbers of beds actually visited during handover (de-duplicated). */
    public array $visitedBedsAttendances = [];

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
        $this->currentShift = ShiftService::getShiftInfo(now()->addMinutes(60))['shift'];
        $this->scheduleDate = now()->format('Y-m-d');
    }

    #[On('openPatientModalHandover')]
    public function openFromHandover(array $data): void
    {
        $attendanceNumber = $data['attendanceNumber'] ?? null;
        $patients = $data['patients'] ?? [];

        if (! $attendanceNumber) {
            return;
        }

        if (! empty($data['handoverMode'])) {
            $this->handoverMode = true;
            $this->handoverStartedAt = $data['startedAt'] ?? now()->toISOString();
            $this->handoverSessionToken = $data['sessionToken'] ?? '';
            $this->visitedBedsAttendances = [];
        }

        $sbarPatient = collect($patients)
            ->firstWhere('nr_atendimento', (int) $attendanceNumber);

        $this->openModal((int) $attendanceNumber, '', $sbarPatient ?? null, $patients);
    }

    #[On('openModal')]
    public function openModal($attendanceNumber, $hospital = '', $sbarPatient = null, $patients = [])
    {
        if (! $attendanceNumber || $attendanceNumber == 0) {
            return;
        }

        try {
            $previousVisited = $this->visitedBedsAttendances;
            $this->resetModalState();
            $this->visitedBedsAttendances = $previousVisited;

            if ($this->handoverMode) {
                $nr = (int) $attendanceNumber;
                if ($nr > 0 && ! in_array($nr, $this->visitedBedsAttendances, true)) {
                    $this->visitedBedsAttendances[] = $nr;
                }
            }

            // Se a lista de pacientes não veio via evento (window.__sbarModalPatients não executa
            // no morph do Livewire), busca os pacientes do setor direto do cache de demographics
            // (já aquecido pelo SbarReport, lookup Redis ~1 ms).
            $patientsList = is_array($patients) ? $patients : [];
            if (empty($patientsList) && is_array($sbarPatient)) {
                $sectorId = (int) ($sbarPatient['cd_setor_atendimento'] ?? 0);
                if ($sectorId > 0) {
                    try {
                        $patientsList = array_values(
                            PatientDataLoader::forSector($sectorId)->include('demographics')->get()
                        );
                    } catch (\Throwable $e) {
                        Log::warning('PatientModal: Failed to load sector patients for navigation', [
                            'sector_id' => $sectorId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $this->setModalPatients($patientsList, (int) $attendanceNumber);
            $this->loadingPatient = true;
            $this->showModal = true;
            $this->dispatch('modal-opened');

            $this->currentPatient = [
                'nr_atendimento' => $attendanceNumber,
                'has_patient' => true,
                'cd_unidade_basica' => is_array($sbarPatient) ? ($sbarPatient['cd_unidade_basica'] ?? null) : null,
                'ds_setor_atendimento' => is_array($sbarPatient) ? ($sbarPatient['ds_setor_atendimento'] ?? null) : null,
                'ds_prescricao' => is_array($sbarPatient) ? ($sbarPatient['ds_prescricao'] ?? null) : null,
            ];
            $this->currentHospitalName = $hospital;

            $this->dispatch('patient-loading-started', [
                'patientId' => $attendanceNumber,
                'shift' => $this->currentShift,
            ]);

            if (is_array($sbarPatient) && $this->isUsableSbarPayload($sbarPatient)) {
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
            ->filter(fn ($patient) => is_array($patient) || is_object($patient))
            ->map(function ($rawPatient): array {
                $patient = is_array($rawPatient) ? $rawPatient : (array) $rawPatient;
                $attendance = (int) ($patient['nr_atendimento'] ?? 0);
                $existingLabel = trim((string) ($patient['label'] ?? ''));
                $sbarPayload = $patient['sbar_payload'] ?? [];
                if (! is_array($sbarPayload)) {
                    $sbarPayload = [];
                }

                $navigationPayload = [
                    'nr_atendimento' => $attendance,
                    'cd_pessoa_fisica' => isset($patient['cd_pessoa_fisica']) ? (int) $patient['cd_pessoa_fisica'] : null,
                    'cd_unidade_basica' => $patient['cd_unidade_basica'] ?? null,
                    'ds_setor_atendimento' => $patient['ds_setor_atendimento'] ?? null,
                    'ds_prescricao' => $patient['ds_prescricao'] ?? null,
                ];
                $navigationPayload = array_filter(
                    $navigationPayload,
                    fn ($value) => ! (is_null($value) || $value === '')
                );

                if (empty($sbarPayload) && $this->isUsableSbarPayload($patient)) {
                    $sbarPayload = $patient;
                }

                if (empty($sbarPayload) && ! empty($navigationPayload)) {
                    $sbarPayload = $navigationPayload;
                }

                return [
                    'nr_atendimento' => $attendance,
                    'label' => $existingLabel !== ''
                        ? $existingLabel
                        : $this->buildPatientNavigationLabel($patient, $attendance),
                    'sbar_payload' => is_array($sbarPayload) ? $sbarPayload : [],
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
                'sbar_payload' => [],
            ];
        }

        $this->modalPatients = $normalized;

        $currentIndex = collect($this->modalPatients)
            ->search(fn (array $patient) => (int) $patient['nr_atendimento'] === $currentAttendance);

        if ($currentIndex === false && $currentAttendance > 0) {
            $this->modalPatients[] = [
                'nr_atendimento' => $currentAttendance,
                'label' => sprintf('Atendimento %d', $currentAttendance),
                'sbar_payload' => [],
            ];
            $currentIndex = count($this->modalPatients) - 1;
        }

        $this->currentPatientIndex = $currentIndex === false ? null : (int) $currentIndex;
    }

    /**
     * Payload mínimo (somente identificação/label/leito) não deve ser usado como snapshot completo.
     * Isso evita que a UI fique sem dados ao navegar entre atendimentos no modal.
     */
    private function isUsableSbarPayload(array $payload): bool
    {
        // Exige ao menos um campo clínico/SBAR rico que não vem do DemographicsLoader.
        // Payloads com apenas dados demográficos devem ir para loadPatientData() para
        // garantir escalas, avaliação de enfermagem, hemoculturas e dados clínicos completos.
        return array_key_exists('mews_score', $payload)
            || array_key_exists('pews_score', $payload)
            || array_key_exists('braden_score', $payload)
            || array_key_exists('has_isolation', $payload)
            || array_key_exists('alergias_detalhadas', $payload)
            || (isset($payload['pending_events']) && is_array($payload['pending_events']))
            || (isset($payload['hemoc_history']) && is_array($payload['hemoc_history']));
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
                'cd_unidade_basica' => $sbarData['cd_unidade_basica'] ?? ($this->currentPatient['cd_unidade_basica'] ?? null),
                'ds_setor_atendimento' => $sbarData['ds_setor_atendimento'] ?? ($this->currentPatient['ds_setor_atendimento'] ?? null),
                'ds_prescricao' => $sbarData['ds_prescricao'] ?? ($this->currentPatient['ds_prescricao'] ?? null),
                'pending_events' => is_array($sbarData['pending_events'] ?? null) ? $sbarData['pending_events'] : [],
                'hemoc_history' => is_array($sbarData['hemoc_history'] ?? null) ? $sbarData['hemoc_history'] : [],
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

        // Navegação por dia desabilitada na UI: o cronograma de medicação é sempre fixado no dia atual.
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
        $this->openModal(
            (int) $targetPatient['nr_atendimento'],
            $this->currentHospitalName,
            $targetPatient['sbar_payload'] ?? null,
            $this->modalPatients
        );
    }

    public function refreshPatientData()
    {
        if (! $this->currentPatient || ! isset($this->currentPatient['nr_atendimento'])) {
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

    public function finishHandover(): void
    {
        $this->dispatch('handoverFinishedFromModal', [
            'startedAt' => $this->handoverStartedAt,
            'bedsVisited' => count($this->visitedBedsAttendances),
            'sessionToken' => $this->handoverSessionToken,
        ]);

        $this->handoverMode = false;
        $this->handoverStartedAt = '';
        $this->handoverSessionToken = '';
        $this->visitedBedsAttendances = [];
        $this->showModal = false;
        $this->resetModalState();
        $this->dispatch('modal-closed');
    }

    public function cancelHandover(): void
    {
        $this->dispatch('cancelNurseHandoverSession');

        $this->handoverMode = false;
        $this->handoverStartedAt = '';
        $this->handoverSessionToken = '';
        $this->visitedBedsAttendances = [];
        $this->showModal = false;
        $this->resetModalState();
        $this->dispatch('modal-closed');
    }

    public function updateHandoverActivity(): void
    {
        if (! $this->handoverSessionToken) {
            return;
        }

        ShiftHandover::where('session_token', $this->handoverSessionToken)
            ->where('user_id', Auth::id())
            ->where('status', ShiftHandover::STATUS_ACTIVE)
            ->update(['last_activity_at' => now()]);
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

        // Quando o patientDetails contém pending_events do payload SBAR, usa-os como fonte
        // para as abas de Exames e Procedimentos — dados mais ricos e já filtrados por pendência.
        $pendingEvents = [];
        if (isset($this->patientDetails) && is_array($this->patientDetails->pending_events ?? null)) {
            $pendingEvents = (array) $this->patientDetails->pending_events;
        }

        $usePendingForProcedures = ! empty($pendingEvents);

        if ($usePendingForProcedures) {
            $pendingTabs = $this->mapPendingEventsToTabs($pendingEvents);
            $examCount = count($pendingTabs['exams']);
            $procCount = count($pendingTabs['procedures']);
            $procedureItems = array_merge($pendingTabs['exams'], $pendingTabs['procedures']);
        } else {
            $allProcedureItems = collect($plan['procedures']['items'] ?? []);
            $examCount = $allProcedureItems->filter(function (array $item) {
                $eventType = strtolower((string) ($item['event_type'] ?? ''));
                $type = strtolower((string) ($item['type'] ?? ''));

                return $eventType === 'exame'
                    || str_contains($type, 'exame')
                    || str_contains($type, 'laborat');
            })->count();
            $procCount = $allProcedureItems->count() - $examCount;
            $procedureItems = $plan['procedures']['items'] ?? [];
        }

        $counts = [
            'tab-med' => $plan['medications']['count'] ?? 0,
            'tab-exam' => $examCount,
            'tab-proc' => $procCount,
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
            'procedures' => $procedureItems,
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

    /**
     * Mapeia pending_events do payload SBAR para o formato esperado pelos tabs de Exames/Procedimentos.
     *
     * @param  array<int, array<string, mixed>>  $pendingEvents
     * @return array{exams: list<array<string, mixed>>, procedures: list<array<string, mixed>>}
     */
    private function mapPendingEventsToTabs(array $pendingEvents): array
    {
        $exams = [];
        $procedures = [];

        foreach ($pendingEvents as $event) {
            $tipo = (string) ($event['tipo'] ?? '');

            if (in_array($tipo, ['exame', 'proc_exame'], true)) {
                $exams[] = [
                    'id' => $event['nr_prescricao'] ?? uniqid(),
                    'name' => $event['descricao'] ?? 'Exame',
                    'classificacao' => $event['ds_grupo_lab'] ?? null,
                    'material' => null,
                    'checklist_amostra' => $event['ie_amostra'] ?? null,
                    'dt_solicitacao' => $event['dt_solicitacao'] ?? null,
                    'scheduled' => $event['dt_evento_formatted'] ?? null,
                    'scheduled_raw' => $event['dt_evento'] ?? null,
                    'dt_coleta' => $event['dt_coleta'] ?? null,
                    'tempo_pendente' => $event['tempo_pendente'] ?? null,
                    'status_laudo' => $event['status_laudo'] ?? null,
                    'status' => $event['ie_status_execucao'] ?? null,
                    'nr_prescricao' => $event['nr_prescricao'] ?? null,
                    'prescriber' => $event['nm_prescritor_display'] ?? $event['nm_prescritor'] ?? null,
                    'resultado_laudo' => $event['scola_status'] ?? null,
                    'foi_executado_sem_baixa' => (bool) ($event['foi_executado_sem_baixa'] ?? false),
                    'exame_coletado_em_prescricao_mais_nova' => (bool) ($event['exame_coletado_em_prescricao_mais_nova'] ?? false),
                    'prescricao_mais_nova_pendente_info' => $event['prescricao_mais_nova_pendente_info'] ?? null,
                    'motivo_pendente' => $event['motivo_pendente'] ?? null,
                    'scola_status' => $event['scola_status'] ?? null,
                    'event_type' => 'exame',
                    'is_today' => (bool) ($event['is_near'] ?? false),
                    'is_near' => (bool) ($event['is_near'] ?? false),
                    'urgente' => (bool) ($event['urgente'] ?? false),
                ];
            } elseif ($tipo === 'procedimento') {
                $procedures[] = [
                    'id' => uniqid(),
                    'name' => $event['descricao'] ?? 'Procedimento',
                    'type' => $event['ds_subtipo'] ?? null,
                    'sector_name' => $event['setor_execucao'] ?? null,
                    'sector_code' => null,
                    'scheduled' => $event['dt_evento_formatted'] ?? null,
                    'scheduled_raw' => $event['dt_evento'] ?? null,
                    'prescriber' => $event['nm_prescritor_display'] ?? $event['nm_prescritor'] ?? null,
                    'resultado_laudo' => null,
                    'status' => $event['ie_status_execucao'] ?? null,
                    'foi_executado_sem_baixa' => (bool) ($event['foi_executado_sem_baixa'] ?? false),
                    'proc_realizado_em_nova_prescricao' => (bool) ($event['proc_realizado_em_nova_prescricao'] ?? false),
                    'motivo_pendente' => $event['motivo_pendente'] ?? null,
                    'event_type' => 'procedimento',
                    'is_today' => (bool) ($event['is_near'] ?? false),
                    'is_near' => (bool) ($event['is_near'] ?? false),
                    'urgente' => (bool) ($event['urgente'] ?? false),
                ];
            }
        }

        return ['exams' => $exams, 'procedures' => $procedures];
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
