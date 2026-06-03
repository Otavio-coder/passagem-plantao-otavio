<?php

namespace App\Livewire;

use App\Models\NurseHandoverBed;
use App\Models\System\User;
use App\Repositories\MySQL\Chat\ChatRepository;
use App\Services\PatientData\PatientDataLoader;
use App\Services\ShiftService;
use App\Services\UserDisplayNameResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class SbarShiftEvaluationsModal extends Component
{
    private UserDisplayNameResolver $userDisplayNameResolver;

    public bool $isOpen = false;

    public ?int $sectorId = null;

    public bool $loading = false;

    public string $sectorName = '';

    /**
     * Metadados serializáveis dos 3 turnos (sem objetos Carbon).
     * Estrutura: [{key, label, time_range, date_label, full_label}]
     */
    public array $shiftsMeta = [];

    /**
     * Mapa de fotos por user_id — base64 armazenado uma única vez por usuário.
     * Mensagens referenciam user_id; Alpine faz o lookup no template.
     * Evita duplicar ~8 KB de base64 a cada mensagem do mesmo autor.
     *
     * @var array<int, string>
     */
    public array $photos = [];

    /**
     * Lista de leitos com mensagens agrupadas por turno.
     *
     * Estrutura de cada item:
     *  - leito, nome_paciente, prontuario, atendimento, has_patient, status
     *  - internment_label, alta_label, alta_formatted
     *  - mensagens: {current: [...], previous: [...], previous2: [...]}
     *  - totais:    {current: int, previous: int, previous2: int}
     *  - has_pinned: {current: bool, previous: bool, previous2: bool}
     */
    public array $beds = [];

    protected $listeners = ['openSbarEvaluationsModal' => 'open'];

    public function boot(UserDisplayNameResolver $userDisplayNameResolver): void
    {
        $this->userDisplayNameResolver = $userDisplayNameResolver;
    }

    public function open($sectorId = null): void
    {
        if ($sectorId) {
            $this->sectorId = (int) $sectorId;
        }

        $this->isOpen = true;
        $this->loadEvaluations();
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function loadEvaluations(): void
    {
        if (! $this->sectorId) {
            $this->loading = false;

            return;
        }

        $this->loading = true;

        try {
            $user = Auth::user();
            $userCached = $user->only_assigned_beds
                ? Cache::get("sector_patients_{$this->sectorId}_{$user->id}")
                : null;

            if ($userCached !== null) {
                $patients = $userCached;
            } else {
                $patients = PatientDataLoader::forSector($this->sectorId)
                    ->include('demographics')
                    ->get();

                if ($user->only_assigned_beds) {
                    $assignedBeds = NurseHandoverBed::where('user_id', $user->id)
                        ->where('sector_id', $this->sectorId)
                        ->pluck('bed_code')
                        ->toArray();

                    if (! empty($assignedBeds)) {
                        $patients = array_values(array_filter(
                            $patients,
                            fn (array $p) => in_array($p['cd_unidade_basica'] ?? '', $assignedBeds, true)
                        ));
                    }
                }
            }

            if (empty($patients)) {
                $this->beds = [];
                $this->photos = [];
                $this->shiftsMeta = [];

                return;
            }

            $attendanceNumbers = array_values(array_filter(array_column($patients, 'nr_atendimento')));

            $windows = $this->buildThreeShiftWindows();

            // Uma única query MySQL cobrindo os 3 turnos (janela total = prev2.from → current.to).
            // Índice 0 = previous2 (mais antigo), índice 2 = current (mais recente).
            $totalFrom = $windows[0]['from'];
            $totalTo = $windows[2]['to'];

            $rawMessages = ! empty($attendanceNumbers)
                ? DB::table('chat_messages as cm')
                    ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                    ->leftJoin('chat_message_pins as cmp', function ($join) {
                        $join->on('cmp.message_id', '=', 'cm.id')
                            ->whereNull('cmp.unpinned_at');
                    })
                    ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                    ->whereBetween('cm.created_at', [$totalFrom, $totalTo])
                    ->select([
                        'cm.id',
                        'cm.nr_atendimento',
                        'cm.content',
                        'cm.created_at',
                        'u.id as user_id',
                        'u.name as user_name',
                        DB::raw('CASE WHEN cmp.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned'),
                    ])
                    ->orderBy('cm.created_at', 'asc')
                    ->get()
                : collect();

            // Fotos: carrega UMA vez por user_id único e armazena em $photos.
            // Reduz o payload de estado Livewire de O(msgs) para O(unique_users).
            $userIds = $rawMessages->pluck('user_id')->filter()->unique()->values()->all();
            $this->photos = [];
            if (! empty($userIds)) {
                User::whereIn('id', $userIds)
                    ->select(['id', 'photo', 'role', 'role_synced_at', 'name', 'username'])
                    ->get()
                    ->each(function (User $user): void {
                        $photo = $user->getUserPhoto('64x64');
                        if (! empty($photo) && $user->hasValidPhoto()) {
                            $this->photos[$user->id] = $photo;
                        }
                    });
            }

            // Reações: carregadas em batch para todas as mensagens dos 3 turnos.
            $messageIds = $rawMessages->pluck('id')->filter()->values()->all();
            $currentUserId = Auth::id();
            $reactionsByMessage = ! empty($messageIds)
                ? DB::table('chat_reactions as cr')
                    ->join('users as u', 'cr.user_id', '=', 'u.id')
                    ->whereIn('cr.message_id', $messageIds)
                    ->select(['cr.message_id', 'cr.user_id', 'u.name'])
                    ->get()
                    ->groupBy('message_id')
                : collect();

            // Agrupa mensagens por atendimento.
            $messagesByAttendance = $rawMessages->groupBy('nr_atendimento');

            $this->beds = [];

            foreach ($patients as $patient) {
                $nrAtendimento = $patient['nr_atendimento'] ?? null;
                $hasPatient = (bool) ($patient['has_patient'] ?? false);
                $dischargeInfo = $patient['discharge_info'] ?? null;
                $dischargeTipo = $dischargeInfo['tipo'] ?? null;

                $status = match (true) {
                    ! $hasPatient => 'empty',
                    $dischargeTipo === 'alta' => 'alta',
                    $dischargeTipo === 'alta_medica' => 'alta_medica',
                    default => 'occupied',
                };

                [$altaLabel, $altaFormatted] = match ($dischargeTipo) {
                    'alta' => ['Alta', $dischargeInfo['dt_alta_formatted'] ?? null],
                    'alta_medica' => ['Alta Médica', $dischargeInfo['dt_alta_medico_formatted'] ?? null],
                    'previsao_alta' => ['Prev. Alta', $dischargeInfo['dt_previsto_alta_formatted'] ?? null],
                    default => [null, null],
                };

                // Classifica cada mensagem no turno correto e formata para exibição.
                $mensagensPorTurno = ['current' => [], 'previous' => [], 'previous2' => []];

                if ($nrAtendimento && isset($messagesByAttendance[$nrAtendimento])) {
                    foreach ($messagesByAttendance[$nrAtendimento] as $message) {
                        $shiftKey = $this->classifyMessageToShift($message->created_at, $windows);
                        $dt = Carbon::parse($message->created_at);
                        $userId = isset($message->user_id) ? (int) $message->user_id : null;
                        $userName = $this->userDisplayNameResolver->fromUserId(
                            $userId,
                            $message->user_name ?? 'Desconhecido'
                        );
                        $msgReactions = $reactionsByMessage->get($message->id, collect());

                        $mensagensPorTurno[$shiftKey][] = [
                            'id' => $message->id,
                            'content' => nl2br(e($message->content)),
                            'user_id' => $userId,
                            'user_name' => $userName,
                            'user_initials' => $this->getInitials($userName),
                            'time' => $dt->format('H:i'),
                            'date' => $dt->format('d/m/Y'),
                            'datetime_label' => $this->formatMessageDateTime($dt),
                            'is_pinned' => (bool) $message->is_pinned,
                            'reactions_count' => $msgReactions->count(),
                            'user_reacted' => $currentUserId && $msgReactions->contains('user_id', $currentUserId),
                        ];
                    }
                }

                $totais = array_map('count', $mensagensPorTurno);
                $hasPinned = array_map(
                    fn ($msgs) => collect($msgs)->contains('is_pinned', true),
                    $mensagensPorTurno
                );

                $this->beds[] = [
                    'leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                    'nome_paciente' => $hasPatient ? ($patient['nm_pessoa_fisica'] ?? 'N/A') : '',
                    'prontuario' => $patient['nr_prontuario'] ?? '',
                    'atendimento' => $nrAtendimento,
                    'has_patient' => $hasPatient,
                    'status' => $status,
                    'internment_label' => $this->buildInternmentLabel($patient, $hasPatient),
                    'alta_label' => $altaLabel,
                    'alta_formatted' => $altaFormatted,
                    'mensagens' => $mensagensPorTurno,
                    'totais' => $totais,
                    'has_pinned' => $hasPinned,
                ];
            }

            // Serializa apenas os metadados (sem objetos Carbon) para o frontend.
            $this->shiftsMeta = array_map(fn ($w) => [
                'key' => $w['key'],
                'label' => $w['label'],
                'time_range' => $w['time_range'],
                'date_label' => $w['date_label'],
                'full_label' => $w['full_label'],
            ], $windows);

        } catch (\Exception $e) {
            Log::error('[SbarShiftEvaluationsModal] Error loading evaluations', [
                'sector_id' => $this->sectorId,
                'error' => $e->getMessage(),
            ]);
            $this->beds = [];
            $this->photos = [];
            $this->shiftsMeta = [];
        } finally {
            $this->loading = false;
        }
    }

    public function toggleReaction(int $messageId): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        $repo = new ChatRepository;
        $result = $repo->toggleReaction($messageId, $userId);

        // Atualiza a mensagem específica em $this->beds sem re-renderizar tudo.
        $beds = $this->beds;
        foreach ($beds as &$bed) {
            foreach (['current', 'previous', 'previous2'] as $shiftKey) {
                foreach ($bed['mensagens'][$shiftKey] as &$msg) {
                    if ($msg['id'] === $messageId) {
                        $msg['user_reacted'] = $result['reacted'];
                        $msg['reactions_count'] = count($result['reactions']);
                        break 3;
                    }
                }
                unset($msg);
            }
        }
        unset($bed);
        $this->beds = $beds;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Computa as janelas dos 3 turnos retroativos ao atual.
     *
     * Durações: manhã = 6h, tarde = 6h, noite = 12h.
     * Retorna array de 3 janelas em ordem decrescente (current primeiro).
     *
     * @return array<int, array{key: string, label: string, time_range: string, date_label: string, full_label: string, from: Carbon, to: Carbon}>
     */
    private function buildThreeShiftWindows(): array
    {
        $durations = ['morning' => 6, 'afternoon' => 6, 'night' => 12];
        $labels = ['morning' => 'Manhã', 'afternoon' => 'Tarde', 'night' => 'Noite'];
        $timeRanges = ['morning' => '07:00 – 13:00', 'afternoon' => '13:00 – 19:00', 'night' => '19:00 – 07:00'];
        $prevOf = fn (string $s): string => match ($s) {
            'morning' => 'night',
            'afternoon' => 'morning',
            'night' => 'afternoon',
        };

        $effectiveNow = now()->addMinutes(60);
        $info = ShiftService::getShiftInfo($effectiveNow);
        [$curFrom, $curTo] = ShiftService::getShiftWindow($effectiveNow);
        $curShift = $info['shift'];

        $prevShift = $prevOf($curShift);
        $prev2Shift = $prevOf($prevShift);

        $prevFrom = $curFrom->copy()->subHours($durations[$prevShift]);
        $prevTo = $curFrom->copy();
        $prev2From = $prevFrom->copy()->subHours($durations[$prev2Shift]);
        $prev2To = $prevFrom->copy();

        $dateLabel = function (Carbon $from, Carbon $to): string {
            $fromLabel = $from->isToday() ? 'Hoje' : ($from->isYesterday() ? 'Ontem' : $from->format('d/m'));
            $toLabel = $to->isToday() ? 'Hoje' : ($to->isYesterday() ? 'Ontem' : $to->format('d/m'));

            return $fromLabel === $toLabel ? $fromLabel : "{$fromLabel} – {$toLabel}";
        };

        $fullLabel = function (string $shiftName, Carbon $from, Carbon $to) use ($labels, $timeRanges, $dateLabel): string {
            return $labels[$shiftName].' · '.$dateLabel($from, $to).' · '.$timeRanges[$shiftName];
        };

        // Ordem cronológica: mais antigo à esquerda, turno atual à direita.
        return [
            [
                'key' => 'previous2',
                'label' => $labels[$prev2Shift],
                'time_range' => $timeRanges[$prev2Shift],
                'date_label' => $dateLabel($prev2From, $prev2To),
                'full_label' => $fullLabel($prev2Shift, $prev2From, $prev2To),
                'from' => $prev2From,
                'to' => $prev2To,
            ],
            [
                'key' => 'previous',
                'label' => $labels[$prevShift],
                'time_range' => $timeRanges[$prevShift],
                'date_label' => $dateLabel($prevFrom, $prevTo),
                'full_label' => $fullLabel($prevShift, $prevFrom, $prevTo),
                'from' => $prevFrom,
                'to' => $prevTo,
            ],
            [
                'key' => 'current',
                'label' => $labels[$curShift],
                'time_range' => $timeRanges[$curShift],
                'date_label' => $dateLabel($curFrom, $curTo),
                'full_label' => $fullLabel($curShift, $curFrom, $curTo),
                'from' => $curFrom,
                'to' => $curTo,
            ],
        ];
    }

    /**
     * Determina a qual turno uma mensagem pertence comparando seu timestamp
     * com as janelas pré-computadas.
     */
    private function classifyMessageToShift(string $createdAt, array $windows): string
    {
        $ts = Carbon::parse($createdAt)->timestamp;

        foreach ($windows as $window) {
            if ($ts >= $window['from']->timestamp && $ts < $window['to']->timestamp) {
                return $window['key'];
            }
        }

        return 'current';
    }

    /**
     * Formata a data/hora de uma mensagem de forma legível:
     * "Hoje 14:32", "Ontem 21:10", "15/05 08:00"
     */
    private function formatMessageDateTime(Carbon $dt): string
    {
        $prefix = $dt->isToday()
            ? 'Hoje'
            : ($dt->isYesterday() ? 'Ontem' : $dt->format('d/m'));

        return $prefix.' '.$dt->format('H:i');
    }

    private function buildInternmentLabel(array $patient, bool $hasPatient): ?string
    {
        if (! $hasPatient) {
            return null;
        }

        $days = is_numeric($patient['internment_days'] ?? null) ? (int) $patient['internment_days'] : null;

        if ($days === null) {
            return null;
        }

        if ($days > 0) {
            return $days.'d internado';
        }

        $dtEntrada = $patient['dt_entrada'] ?? null;
        if ($dtEntrada) {
            try {
                $hours = (int) Carbon::parse($dtEntrada)->diffInHours(Carbon::now());

                return $hours > 0 ? $hours.'h internado' : '< 1h internado';
            } catch (\Exception) {
                // fall through
            }
        }

        return '< 1d internado';
    }

    private function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr(end($parts), 0, 1));
        }

        return strtoupper(substr($parts[0] ?? 'U', 0, 2));
    }

    public function render()
    {
        return view('sbar.report.shift-evaluations-modal');
    }
}
