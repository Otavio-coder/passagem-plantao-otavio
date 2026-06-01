<?php

namespace App\Livewire;

use App\Models\EMR\Core\Sector;
use App\Models\NurseHandoverBed;
use App\Models\ShiftHandover;
use App\Models\System\UserSectorPreference;
use App\Services\PatientData\PatientDataLoader;
use App\Services\ShiftService;
use App\Services\Tasy\TasyService;
use App\Services\UserDisplayNameResolver;
use App\Support\PendingEventHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Componente Livewire principal do painel SBAR (Situation-Background-Assessment-Recommendation).
 *
 * Arquitetura de estado:
 * - Propriedades públicas primitivas (`selectedSector`, `isLoading`, etc.) são serializadas
 *   no wire:snapshot a cada requisição Livewire. Mantê-las pequenas evita overhead de payload.
 * - Propriedades `#[Computed(persist: true)]` NÃO entram no snapshot; ficam no cache de sessão
 *   do Laravel. Para invalidade-las, usa-se `unset($this->nomeProp)` — isso força recalculo
 *   na próxima leitura sem exigir re-serialização do objeto inteiro.
 * - Dados de paciente (~150 KB por setor) jamais vão para o snapshot; só o sectorId vai.
 */
class SbarReport extends Component
{
    // Estado primitivo — seguro no wire:snapshot
    public $errorMessage = null;

    public $lastRefresh = null;

    // Seleção do usuário
    public $selectedHospital = null;

    public $selectedSector = null;

    /** Verdadeiro durante o carregamento inicial até o wire:init disparar loadPatients(). */
    public bool $isLoading = true;

    // Recuperação de passagem de plantão em andamento
    public bool $hasActiveHandoverSession = false;

    public ?int $activeHandoverSectorId = null;

    public ?int $activeHandoverId = null;

    // Onboarding de setores
    public bool $showSectorOnboarding = false;

    public array $selectedSectors = [];

    // Lembrete de configuração de leitos (exibido uma vez após login para enfermeiros)
    public bool $showBedReminder = false;

    // Serviços
    protected TasyService $tasyService;

    protected UserDisplayNameResolver $userDisplayNameResolver;

    protected $listeners = [
        'refreshData' => 'refreshData',
        'sectorOnboardingSaved' => 'onSectorOnboardingSaved',
        'handover-updated' => 'onHandoverUpdated',
        'nurseHandoverCompleted' => 'onNurseHandoverCompleted',
    ];

    public function boot(TasyService $tasyService, UserDisplayNameResolver $userDisplayNameResolver)
    {
        $this->tasyService = $tasyService;
        $this->userDisplayNameResolver = $userDisplayNameResolver;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Propriedades computadas — NÃO serializadas no wire:snapshot
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Hospitais disponíveis do usuário. Persistido durante o ciclo de vida do componente;
     * invalidado com unset($this->hospitals) quando as preferências mudam.
     */
    #[Computed(persist: true)]
    public function hospitals(): array
    {
        return Auth::user()->sectorPreferences()
            ->select(['hospital_code', 'hospital_name'])
            ->distinct()
            ->orderBy('hospital_name')
            ->get()
            ->map(fn ($p) => [
                'hospital_id' => (int) $p->hospital_code,
                'hospital_name' => $p->hospital_name,
            ])
            ->toArray();
    }

    /**
     * Setores do hospital selecionado. Persistido; invalidado na troca de hospital
     * via unset($this->sectors).
     */
    #[Computed(persist: true)]
    public function sectors(): array
    {
        if (! $this->selectedHospital) {
            return [];
        }

        $freshNames = collect(Sector::allowedForPreferences())
            ->keyBy('sector_code')
            ->map(fn ($s) => $s['sector_name']);

        return Auth::user()->sectorPreferences()
            ->where('hospital_code', $this->selectedHospital)
            ->orderBy('sector_name')
            ->get()
            ->map(fn ($p) => [
                'cd_setor_atendimento' => (int) $p->sector_code,
                'ds_setor_atendimento' => $freshNames->get((string) $p->sector_code, $p->sector_name),
            ])
            ->toArray();
    }

    /**
     * Pacientes do setor selecionado. Persistido; invalidado na troca de setor ou hospital
     * via unset($this->patients). Carrega dados completos do paciente via PatientDataLoader, com cache
     * dinâmico de 5 min para eventos pendentes e passagem de plantão, e cache estático de 15 min para dados do Tasy.
     */
    #[Computed(persist: true)]
    public function patients(): array
    {
        if (! $this->selectedSector) {
            return [];
        }

        try {
            $patients = PatientDataLoader::forSector($this->selectedSector)
                ->include('demographics', 'scales', 'pending_events', 'clinical', 'multidisciplinary', 'surgery')
                ->get();

            $withHandover = Cache::remember(
                "sector_handover_{$this->selectedSector}_".ShiftService::getCurrentShift(),
                300, // 5 min — atualiza dentro do mesmo turno sem re-consultar as tabelas MySQL de chat
                fn () => $this->injectHandoverStatus($patients)
            );

            // Apply per-user bed filter after cache retrieval so cache stays shared across users.
            return $this->preparePatientsForView($this->applyBedFilter($withHandover));
        } catch (\Exception $e) {
            Log::error('Error loading patients', [
                'exception' => $e,
                'selected_sector' => $this->selectedSector,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro ao carregar pacientes: '.$e->getMessage();

            return [];
        }
    }

    /**
     * All available sectors for the onboarding modal. Not persisted — only
     * computed when the modal is open.
     */
    #[Computed]
    public function availableSectors(): array
    {
        if (! $this->showSectorOnboarding) {
            return [];
        }

        try {
            return Sector::allowedForPreferences();
        } catch (\Exception $e) {
            Log::error('Error loading available sectors for onboarding', [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);

            return [];
        }
    }

    /**
     * Display name of the currently selected hospital.
     */
    #[Computed]
    public function currentHospitalName(): string
    {
        if (! $this->selectedHospital) {
            return 'Carregando...';
        }

        $hospital = collect($this->hospitals)->firstWhere('hospital_id', (int) $this->selectedHospital);

        return $hospital['hospital_name'] ?? 'Hospital';
    }

    #[Computed]
    public function currentShiftName(): string
    {
        return ShiftService::getShiftInfo()['shift'];
    }

    /**
     * Filtra pacientes com escalas pendentes diretamente do payload já carregado.
     * Não faz nenhuma query adicional — usa os campos *_needs_assessment e *_score
     * que o ScalesLoader já inclui em cada paciente.
     */
    #[Computed]
    public function expiredScalesPatients(): array
    {
        if ($this->isLoading) {
            return [];
        }

        $scalesMeta = [
            'mews' => 'MEWS',
            'pews' => 'PEWS',
            'braden' => 'Braden',
            'morse' => 'Morse',
            'pain' => 'Dor',
            'vte' => 'TEV',
        ];

        $expiredList = [];

        foreach ($this->patients as $patient) {
            if (! ($patient['has_patient'] ?? false)) {
                continue;
            }

            $isPediatric = $patient['is_pediatric'] ?? false;
            $expiredScales = [];

            foreach ($scalesMeta as $key => $name) {
                // MEWS só para adultos, PEWS só para pediátricos
                if ($key === 'mews' && $isPediatric) {
                    continue;
                }
                if ($key === 'pews' && ! $isPediatric) {
                    continue;
                }

                if (! ($patient["{$key}_needs_assessment"] ?? false)) {
                    continue;
                }

                $expiredScales[] = [
                    'name' => $name,
                    'score' => $patient["{$key}_score"] ?? null,
                    'timestamp_label' => self::formatScaleTimestamp($patient["{$key}_timestamp"] ?? null),
                ];
            }

            if (empty($expiredScales)) {
                continue;
            }

            $count = count($expiredScales);
            $internmentDays = is_numeric($patient['internment_days'] ?? null)
                ? (int) $patient['internment_days']
                : null;

            $expiredList[] = [
                'bed' => $patient['cd_unidade_basica'] ?? 'N/A',
                'name' => $patient['nm_pessoa_fisica'] ?? 'N/A',
                'nr_atendimento' => $patient['nr_atendimento'] ?? null,
                'dt_entrada_formatted' => isset($patient['dt_entrada'])
                    ? Carbon::parse($patient['dt_entrada'])->format('d/m/Y')
                    : null,
                'internment_days' => $internmentDays,
                'expired_scales' => $expiredScales,
                'total_expired' => $count,
                'priority' => $count >= 4 ? 'critical' : ($count >= 2 ? 'high' : 'medium'),
            ];
        }

        usort($expiredList, fn ($a, $b) => $b['total_expired'] <=> $a['total_expired']
            ?: strnatcmp($a['bed'], $b['bed']));

        return $expiredList;
    }

    private static function formatScaleTimestamp(mixed $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        try {
            $dt = Carbon::parse($timestamp);
            if ($dt->isToday()) {
                return 'Hoje '.$dt->format('H:i');
            }
            if ($dt->isYesterday()) {
                return 'Ontem '.$dt->format('H:i');
            }

            return $dt->format('d/m H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────────────────────────────────

    public function mount()
    {
        $user = Auth::user();

        if (! $user->hasConfiguredSectors()) {
            $this->dispatch('checkUserSectors');
            $this->errorMessage = 'Você precisa configurar seus setores de acesso antes de usar o SBAR.';
            $this->showSectorOnboarding = true;

            return;
        }

        try {
            $hospitals = $this->hospitals;

            if (empty($hospitals)) {
                $this->errorMessage = 'Nenhum hospital disponível.';

                return;
            }

            if (! $this->selectedHospital) {
                $this->selectedHospital = $hospitals[0]['hospital_id'];
            }

            $sectors = $this->sectors;

            if (empty($sectors)) {
                $this->errorMessage = 'Nenhum setor configurado para este hospital. Atualize suas preferências de setor.';

                return;
            }

            if (! $this->selectedSector) {
                $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
            }

            $this->lastRefresh = now()->format('H:i:s');

            $this->checkActiveHandoverSession();

            if (session()->pull('nurse_bed_reminder') && $user->isNurse()) {
                $this->showBedReminder = true;
            }
        } catch (\Exception $e) {
            Log::error('SBAR mount error', [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro durante inicialização: '.$e->getMessage();
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Called by wire:init after the initial HTML is rendered in the browser.
     * Triggers patient loading without blocking the first page render.
     */
    public function loadPatients(): void
    {
        $this->isLoading = false;
    }

    public function changeHospital($hospitalId)
    {
        $this->selectedHospital = $hospitalId;
        $this->selectedSector = null;

        unset($this->sectors);
        unset($this->patients);

        $sectors = $this->sectors;

        if (! empty($sectors)) {
            $this->selectedSector = $sectors[0]['cd_setor_atendimento'];
            $this->lastRefresh = now()->format('H:i:s');
            $this->auditSectorView('hospital_change');
        } else {
            $this->errorMessage = 'Nenhum setor configurado para este hospital.';
        }
    }

    public function changeSector($sectorId)
    {
        $user = Auth::user();
        $allowedSectorCodes = $user->sectorPreferences()->pluck('sector_code')->toArray();

        // Validação explícita de autorização: qualquer usuário autenticado pode chamar métodos
        // Livewire diretamente via DevTools ou requests forjados. O setor solicitado deve estar
        // na whitelist de preferências do próprio usuário, não apenas existir no sistema.
        if (! in_array((string) $sectorId, array_map('strval', $allowedSectorCodes))) {
            Log::warning('Tentativa de acesso a setor não autorizado', [
                'user_id' => $user->id,
                'sector_id' => $sectorId,
            ]);
            $this->errorMessage = 'Acesso negado: setor não autorizado.';

            return;
        }

        $this->selectedSector = $sectorId;
        unset($this->patients);
        $this->lastRefresh = now()->format('H:i:s');
        $this->auditSectorView('sector_change');
    }

    public function refreshData()
    {
        try {
            if ($this->selectedSector) {
                // Invalida apenas caches dinâmicos (eventos pendentes + passagem de plantão).
                // Dados demográficos, escalas, clínico, multidisciplinar e cirurgia
                // são estáveis durante a sessão e expiram naturalmente pelo TTL.
                // Evita disparar carga completa do Oracle a cada clique em "Atualizar".
                PatientDataLoader::forSector($this->selectedSector)->clearDynamicCache();
            }

            unset($this->patients);
            $this->lastRefresh = now()->format('H:i:s');
        } catch (\Exception $e) {
            Log::error('Error in refreshData', [
                'exception' => $e,
                'selected_sector' => $this->selectedSector,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro ao atualizar: '.$e->getMessage();
        }
    }

    public function forceRefreshData()
    {
        try {
            if ($this->selectedSector) {
                PatientDataLoader::forSector($this->selectedSector)->clearCache();
            }

            unset($this->patients);
            $this->lastRefresh = now()->format('H:i:s');
        } catch (\Exception $e) {
            Log::error('Error in forceRefreshData', [
                'exception' => $e,
                'selected_sector' => $this->selectedSector,
                'user_id' => Auth::id(),
            ]);
            $this->errorMessage = 'Erro ao atualizar: '.$e->getMessage();
        }
    }

    public function updateSectorPatients()
    {
        unset($this->patients);
        $this->lastRefresh = now()->format('H:i:s');
    }

    public function dismissBedReminder(): void
    {
        $this->showBedReminder = false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Onboarding
    // ──────────────────────────────────────────────────────────────────────────

    public function openSectorOnboarding(): void
    {
        $this->selectedSectors = Auth::user()
            ->sectorPreferences()
            ->pluck('sector_code')
            ->toArray();

        $this->showSectorOnboarding = true;
    }

    public function saveSectorPreferences(): void
    {
        $user = Auth::user();

        if (empty($this->selectedSectors)) {
            return;
        }

        try {
            $sectorsMap = collect($this->availableSectors)->keyBy('sector_code');

            $previousSectors = $user->sectorPreferences()
                ->pluck('sector_name', 'sector_code')
                ->toArray();

            UserSectorPreference::where('user_id', $user->id)->delete();

            foreach ($this->selectedSectors as $sectorCode) {
                $meta = $sectorsMap->get($sectorCode, []);
                UserSectorPreference::create([
                    'user_id' => $user->id,
                    'sector_code' => $sectorCode,
                    'sector_name' => $meta['sector_name'] ?? null,
                    'hospital_code' => $meta['hospital_code'] ?? null,
                    'hospital_name' => $meta['hospital_name'] ?? null,
                ]);
            }

            $newSectors = collect($this->selectedSectors)
                ->mapWithKeys(fn ($code) => [$code => $sectorsMap->get($code)['sector_name'] ?? $code])
                ->toArray();

            Log::channel('audit')->info('preferences.updated', [
                'source' => 'onboarding',
                'user_id' => $user->id,
                'user' => $user->name,
                'previous_sectors' => $previousSectors,
                'new_sectors' => $newSectors,
                'ip' => request()->ip(),
            ]);

            $this->showSectorOnboarding = false;
            $this->selectedSectors = [];
            $this->selectedHospital = null;
            $this->selectedSector = null;

            // Invalida todas as propriedades computadas em cache
            unset($this->hospitals);
            unset($this->sectors);
            unset($this->patients);

            // Re-inicializa com as novas preferências
            $this->mount();
        } catch (\Exception $e) {
            Log::error('Error saving sector preferences', [
                'exception' => $e,
                'user_id' => Auth::id(),
            ]);
        }
    }

    public function onSectorOnboardingSaved(): void
    {
        $this->showSectorOnboarding = false;
        $this->selectedHospital = null;
        $this->selectedSector = null;

        unset($this->hospitals);
        unset($this->sectors);
        unset($this->patients);

        $this->mount();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Event handlers
    // ──────────────────────────────────────────────────────────────────────────

    public function startHandover(): void
    {
        if (! $this->selectedSector) {
            return;
        }

        $this->dispatch('openNurseHandoverSession', sectorId: (int) $this->selectedSector);
    }

    public function onHandoverUpdated(?string $nr = null): void
    {
        // Invalida o cache computado — o próximo render re-busca o status de passagem
        // via query em batch no MySQL, mantendo o cache Oracle de 15 min do TasyService.
        unset($this->patients);

        $this->hasActiveHandoverSession = false;
        $this->activeHandoverId = null;
        $this->activeHandoverSectorId = null;
    }

    /**
     * Fired when the nurse finishes a handover session. Clears dynamic caches
     * (including handover-status) so the card bullet points update immediately.
     */
    public function onNurseHandoverCompleted(int $sectorId): void
    {
        if ($this->selectedSector) {
            PatientDataLoader::forSector($this->selectedSector)->clearDynamicCache();
        }

        unset($this->patients);
        $this->lastRefresh = now()->format('H:i:s');
        $this->hasActiveHandoverSession = false;
        $this->activeHandoverId = null;
        $this->activeHandoverSectorId = null;
    }

    public function resumeHandover(): void
    {
        if (! $this->activeHandoverId) {
            return;
        }

        $this->dispatch('resumeNurseHandoverSession', data: [
            'handoverId' => $this->activeHandoverId,
        ]);
    }

    public function dismissHandoverRecovery(): void
    {
        $this->hasActiveHandoverSession = false;
        $this->activeHandoverId = null;
        $this->activeHandoverSectorId = null;
    }

    private function checkActiveHandoverSession(): void
    {
        [$shiftStart, $shiftEnd] = ShiftService::getShiftWindow();

        $active = ShiftHandover::where('user_id', Auth::id())
            ->where('status', ShiftHandover::STATUS_ACTIVE)
            ->whereBetween('started_at', [$shiftStart, $shiftEnd])
            ->first();

        if ($active) {
            $this->hasActiveHandoverSession = true;
            $this->activeHandoverId = $active->id;
            $this->activeHandoverSectorId = $active->sector_ids[0] ?? null;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Rendering
    // ──────────────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('sbar.report.index', [
            'hospitals' => $this->hospitals,
            'sectors' => $this->sectors,
            'patients' => $this->isLoading ? [] : $this->patients,
            'isLoading' => $this->isLoading,
            'errorMessage' => $this->errorMessage,
            'selectedHospital' => $this->selectedHospital,
            'selectedSector' => $this->selectedSector,
            'currentHospitalName' => $this->currentHospitalName,
            'currentShiftName' => $this->currentShiftName,
            'lastRefresh' => $this->lastRefresh,
            'expiredScalesPatients' => $this->expiredScalesPatients,
            'showSectorOnboarding' => $this->showSectorOnboarding,
            'onboardingSectorsData' => $this->showSectorOnboarding
                ? Sector::allowedForPreferencesGroupedByHospital()
                : [],
            'canStartHandover' => $this->canStartHandover,
            'onlyAssignedBeds' => (bool) Auth::user()?->only_assigned_beds,
            'showBedReminder' => $this->showBedReminder,
        ]);
    }

    #[Computed]
    public function canStartHandover(): bool
    {
        try {
            $user = Auth::user();

            return $user->isNurse()
                && $this->selectedSector !== null
                && NurseHandoverBed::where('user_id', $user->id)
                    ->where('sector_id', $this->selectedSector)
                    ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function auditSectorView(string $action): void
    {
        if (! $this->selectedSector) {
            return;
        }

        $sectorName = collect($this->sectors)
            ->firstWhere('cd_setor_atendimento', $this->selectedSector)['ds_setor_atendimento'] ?? $this->selectedSector;

        Log::channel('audit')->info('sbar.viewed', [
            'action' => $action,
            'user_id' => Auth::id(),
            'user' => Auth::user()->name ?? 'unknown',
            'sector_id' => $this->selectedSector,
            'sector_name' => $sectorName,
            'hospital' => $this->currentHospitalName,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Injects handover_done / handover_last_time / handover_msg_count into
     * each patient via a single batch MySQL query.
     */
    private function injectHandoverStatus(array $patients): array
    {
        $nrs = collect($patients)
            ->filter(fn ($p) => ! empty($p['nr_atendimento']) && ($p['has_patient'] ?? false))
            ->pluck('nr_atendimento')
            ->values()
            ->toArray();

        if (empty($nrs)) {
            return $patients;
        }

        [$shiftStart, $shiftEnd] = ShiftService::getShiftWindow();

        // Tolerância de ±30 min nas bordas do turno: enfermeiros frequentemente registram
        // anotações alguns minutos antes do início oficial ou após o fim do turno.
        // Sem essa margem, anotações feitas às 06:58 não apareceriam no turno da manhã (07h).
        $shiftStart = $shiftStart->copy()->subMinutes(30);
        $shiftEnd = $shiftEnd->copy()->addMinutes(30);

        $rows = DB::table('chat_messages')
            ->whereIn('nr_atendimento', $nrs)
            ->whereBetween('created_at', [$shiftStart, $shiftEnd])
            ->select([
                'nr_atendimento',
                DB::raw('COUNT(*) as msg_count'),
                DB::raw('MAX(created_at) as last_msg'),
            ])
            ->groupBy('nr_atendimento')
            ->get()
            ->keyBy('nr_atendimento');

        // Busca avaliações fixadas (prioridade máxima na exibição)
        $pinnedRows = DB::table('chat_message_pins as cmp')
            ->join('chat_messages as cm', 'cm.id', '=', 'cmp.message_id')
            ->leftJoin('users as u', 'u.id', '=', 'cmp.pinned_by')
            ->whereIn('cm.nr_atendimento', $nrs)
            ->whereNull('cmp.unpinned_at')
            ->orderByDesc('cmp.pinned_at')
            ->select([
                'cm.nr_atendimento',
                'cm.content',
                'cm.created_at as message_created_at',
                'cmp.pinned_at',
                'u.name as pinned_by_name',
                'u.photo',
            ])
            ->get();

        $latestPinnedByAttendance = [];
        foreach ($pinnedRows as $pinnedRow) {
            $attendance = (int) ($pinnedRow->nr_atendimento ?? 0);
            if ($attendance <= 0 || isset($latestPinnedByAttendance[$attendance])) {
                continue;
            }

            $latestPinnedByAttendance[$attendance] = [
                'content' => (string) ($pinnedRow->content ?? ''),
                'pinned_at' => $pinnedRow->pinned_at,
                'pinned_at_formatted' => $pinnedRow->pinned_at
                    ? Carbon::parse($pinnedRow->pinned_at)->format('d/m H:i')
                    : null,
                'message_created_at' => $pinnedRow->message_created_at,
                'message_created_at_formatted' => $pinnedRow->message_created_at
                    ? Carbon::parse($pinnedRow->message_created_at)->format('d/m H:i')
                    : null,
                'pinned_by_name' => $pinnedRow->pinned_by_name,
                'photo' => (string) ($pinnedRow->photo ?? ''),
            ];
        }

        // Busca última avaliação (fallback para atendimentos sem avaliação fixada)
        $attendancesWithoutPinned = array_diff($nrs, array_keys($latestPinnedByAttendance));
        $latestEvaluationByAttendance = [];

        if (! empty($attendancesWithoutPinned)) {
            // Subquery returns the max message id per attendance (last 30 days only),
            // avoiding a full scan and redundant PHP-side deduplication.
            $latestRows = DB::table('chat_messages as cm')
                ->joinSub(
                    DB::table('chat_messages')
                        ->whereIn('nr_atendimento', $attendancesWithoutPinned)
                        ->where('created_at', '>=', now()->subDays(30))
                        ->select(['nr_atendimento', DB::raw('MAX(id) as max_id')])
                        ->groupBy('nr_atendimento'),
                    'latest',
                    fn ($join) => $join
                        ->on('cm.nr_atendimento', '=', 'latest.nr_atendimento')
                        ->on('cm.id', '=', 'latest.max_id')
                )
                ->leftJoin('users as u', 'u.id', '=', 'cm.user_id')
                ->select([
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'u.id as user_id',
                    'u.name as user_name',
                    'u.photo',
                ])
                ->get();

            foreach ($latestRows as $latestRow) {
                $attendance = (int) ($latestRow->nr_atendimento ?? 0);
                if ($attendance <= 0) {
                    continue;
                }

                $latestEvaluationByAttendance[$attendance] = [
                    'content' => (string) ($latestRow->content ?? ''),
                    'created_at' => $latestRow->created_at,
                    'created_at_formatted' => $latestRow->created_at
                        ? Carbon::parse($latestRow->created_at)->format('d/m H:i')
                        : null,
                    'user_name' => $this->userDisplayNameResolver->fromUserId(
                        isset($latestRow->user_id) ? (int) $latestRow->user_id : null,
                        $latestRow->user_name ?? 'Desconhecido'
                    ),
                    'photo' => (string) ($latestRow->photo ?? ''),
                ];
            }
        }

        return array_map(function ($patient) use ($rows, $latestPinnedByAttendance, $latestEvaluationByAttendance) {
            if (! ($patient['has_patient'] ?? false)) {
                return $patient;
            }

            $nr = $patient['nr_atendimento'] ?? null;
            $row = $nr ? $rows->get($nr) : null;

            $patient['handover_done'] = $row !== null;
            $patient['handover_last_time'] = $row
                ? Carbon::parse($row->last_msg)->format('H:i')
                : null;
            $patient['handover_msg_count'] = $row ? (int) $row->msg_count : 0;

            // Prioridade: avaliação fixada; fallback: última avaliação do turno
            $patient['pinned_evaluation'] = $latestPinnedByAttendance[(int) $nr] ?? null;
            $patient['latest_evaluation'] = $latestEvaluationByAttendance[(int) $nr] ?? null;

            return $patient;
        }, $patients);
    }

    /**
     * Transforma o array bruto de cada paciente (vindo dos loaders/repositórios) em campos
     * prontos para o template Blade, sem lógica de display na view.
     *
     * Toda formatação, classificação de risco e montagem de estruturas de exibição ocorre aqui,
     * em PHP, antes do render. Isso evita chamadas de funções complexas dentro do Blade e
     * mantém os templates focados apenas em estrutura HTML.
     */
    private function applyBedFilter(array $patients): array
    {
        $user = Auth::user();

        if (! $user->only_assigned_beds || ! $this->selectedSector) {
            return $patients;
        }

        $assignedBeds = NurseHandoverBed::where('user_id', $user->id)
            ->where('sector_id', $this->selectedSector)
            ->pluck('bed_code')
            ->toArray();

        if (empty($assignedBeds)) {
            return $patients;
        }

        return array_values(array_filter(
            $patients,
            fn (array $p) => in_array($p['cd_unidade_basica'] ?? '', $assignedBeds, true)
        ));
    }

    private function preparePatientsForView(array $patients): array
    {
        return array_map(function (array $patient): array {
            $pendingEvents = $patient['pending_events'] ?? [];

            if (! is_array($pendingEvents)) {
                $pendingEvents = [];
            }

            $structured = PendingEventHelper::buildPendingModalData($pendingEvents);
            $surgeries = $patient['procedimentos_cirurgicos'] ?? [];
            if (! is_array($surgeries)) {
                $surgeries = [];
            }

            $dischargeInfo = $patient['discharge_info'] ?? null;
            if (! is_array($dischargeInfo)) {
                $dischargeInfo = null;
            }

            $multidisciplinaryRequests = $patient['multidisciplinary_requests'] ?? [];
            if (! is_array($multidisciplinaryRequests)) {
                $multidisciplinaryRequests = [];
            }

            $patient['pending_events'] = $structured['events'];
            $patient['pending_groups'] = $structured['groups'];
            $patient['first_pending_event'] = $structured['first_event'];
            $patient['allergy_items'] = self::buildAllergyItems($patient);
            $patient['isolation_items'] = self::buildIsolationItems($patient['motivos_isolamento'] ?? null);
            $patient['first_pending_style'] = PendingEventHelper::firstEventCardStyle($structured['first_event']);
            $patient['sector_exec_fallback'] = self::buildSectorFallbackLabel($patient);
            $patient['handover_shift_name'] = self::shiftDisplayName($this->currentShiftName);
            $patient['procedimentos_cirurgicos'] = self::buildSurgeryItems($surgeries);
            $patient['discharge_display'] = self::buildDischargeDisplay($dischargeInfo);
            $patient['ews_display'] = self::buildEwsDisplay($patient);
            $patient['multidisciplinary_requests'] = self::buildMultidisciplinaryRequests($multidisciplinaryRequests);
            $patient['pending_modal_meta'] = self::buildPendingModalMeta($structured['events']);
            $patient['pending_type_filter'] = collect($structured['events'])
                ->pluck('tipo')
                ->map(fn ($type) => $type === 'proc_exame' ? 'exame' : $type)
                ->filter()
                ->unique()
                ->implode(',');
            $patient['multi_team_filter'] = collect($patient['multidisciplinary'] ?? [])
                ->filter()
                ->keys()
                ->implode(',');
            $patient['convenio_short'] = collect(explode(' ', (string) ($patient['convenio'] ?? 'N/A')))
                ->filter(fn (string $part): bool => trim($part) !== '')
                ->first() ?? 'N/A';

            return $patient;
        }, $patients);
    }

    // ── Helpers de card do paciente (apenas exibição, usados somente aqui) ──────

    private static function shiftDisplayName(?string $shift): string
    {
        return match ($shift) {
            'morning' => 'manhã', 'afternoon' => 'tarde', 'night' => 'noite',
            default => 'turno atual',
        };
    }

    private static function buildEwsDisplay(array $patient): array
    {
        $isPediatric = (bool) ($patient['is_pediatric'] ?? false);

        return ['prefix' => $isPediatric ? 'pews' : 'mews', 'label' => $isPediatric ? 'PEWS' : 'MEWS'];
    }

    private static function buildSurgeryItems(array $surgeries): array
    {
        return array_values(array_map(function (array $surgery): array {
            $raw = trim((string) ($surgery['descricao_padronizada'] ?? $surgery['procedimento'] ?? 'Procedimento'));
            $surgery['display_description'] = trim((string) (preg_replace('/\s*\(\s*Cirurgia\s+agenda\s+para\s+[^\)]*\)\s*$/iu', '', $raw) ?? $raw));

            return $surgery;
        }, $surgeries));
    }

    private static function buildDischargeDisplay(?array $dischargeInfo): array
    {
        $type = (string) ($dischargeInfo['tipo'] ?? '');
        $show = in_array($type, ['alta', 'alta_medica'], true);

        return [
            'show' => $show,
            'type' => $type,
            'label' => $show ? ($type === 'alta' ? 'Alta Efetivada' : 'Prev. Alta') : '',
            'bg' => 'bg-gray-100',
            'icon' => 'alta.svg',
        ];
    }

    private static function buildPendingModalMeta(array $pendingEvents): array
    {
        return [
            'all_count' => count($pendingEvents),
            'near_count' => count(array_filter($pendingEvents, static fn (array $e): bool => (bool) ($e['is_near'] ?? false))),
        ];
    }

    private static function buildAllergyItems(array $patient): array
    {
        $items = $patient['alergias_items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(function (array $item): array {
            $severity = trim((string) ($item['grav'] ?? ''));
            $textClass = 'text-gray-500';
            $bgClass = 'bg-gray-100';

            if ($severity !== '') {
                $lower = mb_strtolower($severity);
                if (str_contains($lower, 'grave') || str_contains($lower, 'severa')) {
                    $textClass = 'text-red-700 font-semibold';
                    $bgClass = 'bg-red-100';
                } elseif (str_contains($lower, 'moderada')) {
                    $textClass = 'text-yellow-700';
                    $bgClass = 'bg-yellow-100';
                }
            }

            return [
                'med' => $item['med'] ?? null,
                'grav' => $item['grav'] ?? null,
                'text' => $item['text'] ?? null,
                'severity_text_class' => $textClass,
                'severity_bg_class' => $bgClass,
            ];
        }, $items));
    }

    private static function buildIsolationItems(?string $rawValue): array
    {
        $raw = trim(strip_tags((string) ($rawValue ?? '')));
        if ($raw === '' || mb_strtolower($raw) === 'não') {
            return [];
        }

        $items = [];
        foreach (preg_split('/[;|\r\n]+/', $raw) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $part, $m)) {
                $items[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
            } else {
                $items[] = ['text' => $part];
            }
        }

        return $items;
    }

    private static function buildSectorFallbackLabel(array $patient): string
    {
        $name = trim((string) ($patient['ds_prescricao'] ?? $patient['ds_setor_atendimento'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $code = $patient['cd_setor_atendimento'] ?? null;

        return $code ? 'Setor '.$code : 'Setor não informado';
    }

    private static function buildMultidisciplinaryRequests(array $requests): array
    {
        $resolver = app(UserDisplayNameResolver::class);

        return array_values(array_map(function (array $req) use ($resolver): array {
            $team = mb_strtolower((string) ($req['ds_equipe_destino'] ?? ''));
            $req['team_icon'] = match (true) {
                str_contains($team, 'fisio') => 'fisioterapia.svg',
                str_contains($team, 'psico') => 'psicologia.svg',
                str_contains($team, 'nutri') => 'nutricao.svg',
                str_contains($team, 'fono') => 'fonoaudiologia.svg',
                str_contains($team, 'social') => 'servico-social.svg',
                str_contains($team, 'acesso'), str_contains($team, 'picc') => 'catheter-svgrepo-com.svg',
                default => null,
            };
            $status = (string) ($req['ie_status'] ?? '');
            $req['status_badge_class'] = $status === 'R' ? 'bg-green-500 text-white' : 'bg-amber-500 text-white';
            $req['card_class'] = $status === 'R' ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200';
            $req['dt_registro_formatted'] = self::formatCardDateTime($req['dt_registro'] ?? null);
            $req['dt_liberacao_formatted'] = self::formatCardDateTime($req['dt_liberacao'] ?? null);
            $req['dt_resposta_formatted'] = self::formatCardDateTime($req['dt_resposta'] ?? null);
            $req['nm_requisitante_display'] = $resolver->fromName($req['nm_requisitante'] ?? null);
            $req['nm_responsavel_resposta_display'] = $resolver->fromName($req['nm_responsavel_resposta'] ?? null);

            return $req;
        }, $requests));
    }

    private static function formatCardDateTime(mixed $value): ?string
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
}
