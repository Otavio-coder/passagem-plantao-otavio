<?php

namespace App\Livewire;

use App\Repositories\MySQL\Chat\ChatRepository;
use App\Services\ShiftService;
use App\Services\Tasy\TasyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class ShiftEvaluationsModal extends Component
{
    public bool $isOpen = false;

    public ?int $sectorId = null;

    public bool $loading = false;

    public array $beds = [];

    public int $totalMessages = 0;

    public string $sectorName = '';

    public string $activeShift = 'previous'; // 'previous' | 'current'

    public string $currentShiftLabel = '';

    public string $previousShiftLabel = '';

    protected $listeners = ['openEvaluationsModal' => 'open'];

    public function open($sectorId = null): void
    {
        if ($sectorId) {
            $this->sectorId = (int) $sectorId;
        }

        $shiftInfo = ShiftService::getShiftInfo();
        $this->currentShiftLabel = ShiftService::getShiftLabel($shiftInfo['shift']);

        $previousShiftName = match ($shiftInfo['shift']) {
            'morning' => 'night',
            'afternoon' => 'morning',
            'night' => 'afternoon',
        };
        $this->previousShiftLabel = ShiftService::getShiftLabel($previousShiftName);

        $this->isOpen = true;
        $this->loadEvaluations();
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function switchShift(string $shift): void
    {
        if (! in_array($shift, ['previous', 'current'])) {
            return;
        }

        $this->activeShift = $shift;
        $this->loadEvaluations();
    }

    public function loadEvaluations(): void
    {
        if (! $this->sectorId) {
            $this->loading = false;

            return;
        }

        $this->loading = true;

        try {
            $tasy = new TasyService;
            $patients = $tasy->getSectorPatientsForSbar($this->sectorId);

            if (empty($patients)) {
                $this->beds = [];
                $this->sectorName = '';
                $this->totalMessages = 0;

                return;
            }

            $this->sectorName = $patients[0]['ds_setor_atendimento'] ?? 'Setor';
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));

            [$from, $to] = $this->resolveShiftWindow();

            $rawMessages = ! empty($attendanceNumbers)
                ? DB::table('chat_messages as cm')
                    ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                    ->leftJoin('chat_message_pins as cmp', function ($join) {
                        $join->on('cmp.message_id', '=', 'cm.id')
                            ->whereNull('cmp.unpinned_at');
                    })
                    ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                    ->whereBetween('cm.created_at', [$from, $to])
                    ->select([
                        'cm.id',
                        'cm.nr_atendimento',
                        'cm.content',
                        'cm.created_at',
                        'u.name as user_name',
                        'u.photo as user_photo',
                        DB::raw('CASE WHEN cmp.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned'),
                    ])
                    ->orderBy('cm.created_at', 'asc')
                    ->get()
                : collect();

            $messagesByAttendance = $rawMessages->groupBy('nr_atendimento');

            // Batch-load reactions for all messages in the window.
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

            $beds = [];
            $totalMessages = 0;

            // Iterate all beds in their presentation order (already sorted by nr_seq_apresent).
            foreach ($patients as $patient) {
                $nrAtendimento = $patient['nr_atendimento'] ?? null;
                $hasPatient = (bool) ($patient['has_patient'] ?? false);
                $dischargeInfo = $patient['discharge_info'] ?? null;
                $dischargeTipo = $dischargeInfo['tipo'] ?? null;

                // Determine bed status
                if (! $hasPatient) {
                    $status = 'empty';
                } elseif ($dischargeTipo === 'alta') {
                    $status = 'alta';
                } elseif ($dischargeTipo === 'alta_medica') {
                    $status = 'alta_medica';
                } else {
                    $status = 'occupied';
                }

                // Build discharge label for display
                $altaLabel = null;
                $altaFormatted = null;
                if ($dischargeTipo === 'alta') {
                    $altaLabel = 'Alta';
                    $altaFormatted = $dischargeInfo['dt_alta_formatted'] ?? null;
                } elseif ($dischargeTipo === 'alta_medica') {
                    $altaLabel = 'Alta Médica';
                    $altaFormatted = $dischargeInfo['dt_alta_medico_formatted'] ?? null;
                } elseif ($dischargeTipo === 'previsao_alta') {
                    $altaLabel = 'Prev. Alta';
                    $altaFormatted = $dischargeInfo['dt_previsto_alta_formatted'] ?? null;
                }

                // Format messages
                $formattedMessages = [];
                $photoMap = [];

                if ($nrAtendimento && isset($messagesByAttendance[$nrAtendimento])) {
                    foreach ($messagesByAttendance[$nrAtendimento] as $message) {
                        $dt = Carbon::parse($message->created_at);
                        $userName = $message->user_name ?? 'Desconhecido';

                        if (! isset($photoMap[$userName]) && ! empty($message->user_photo)) {
                            $raw = $message->user_photo;
                            $decoded = base64_decode($raw, true);
                            if ($decoded !== false && strlen($decoded) > 50 && strpos($decoded, '"error"') === false) {
                                $photoMap[$userName] = $raw;
                            }
                        }

                        $msgReactions = $reactionsByMessage->get($message->id, collect());

                        $formattedMessages[] = [
                            'id' => $message->id,
                            'content' => nl2br(e($message->content)),
                            'user_name' => $userName,
                            'user_initials' => $this->getInitials($userName),
                            'photo' => $photoMap[$userName] ?? '',
                            'time' => $dt->format('H:i'),
                            'is_pinned' => (bool) $message->is_pinned,
                            'reactions_count' => $msgReactions->count(),
                            'user_reacted' => $currentUserId && $msgReactions->contains('user_id', $currentUserId),
                        ];
                    }
                }

                $beds[] = [
                    'leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                    'nome_paciente' => $hasPatient ? ($patient['nm_pessoa_fisica'] ?? 'N/A') : '',
                    'prontuario' => $patient['nr_prontuario'] ?? '',
                    'atendimento' => $nrAtendimento,
                    'has_patient' => $hasPatient,
                    'status' => $status,
                    'internment_days' => ($patient['internment_days'] !== null && $hasPatient) ? (int) $patient['internment_days'] : null,
                    'internment_label' => $this->buildInternmentLabel($patient, $hasPatient),
                    'alta_label' => $altaLabel,
                    'alta_formatted' => $altaFormatted,
                    'motivo_alta' => $dischargeInfo['ds_motivo_alta'] ?? null,
                    'mensagens' => $formattedMessages,
                    'total_mensagens' => count($formattedMessages),
                    'has_pinned' => collect($formattedMessages)->contains('is_pinned', true),
                ];

                $totalMessages += count($formattedMessages);
            }

            $this->beds = $beds;
            $this->totalMessages = $totalMessages;

        } catch (\Exception $e) {
            Log::error('[ShiftEvaluationsModal] Error loading evaluations', [
                'sector_id' => $this->sectorId,
                'error' => $e->getMessage(),
            ]);
            $this->beds = [];
            $this->totalMessages = 0;
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

        // Update the specific message in $this->beds without a full reload.
        $beds = $this->beds;
        foreach ($beds as &$bed) {
            foreach ($bed['mensagens'] as &$msg) {
                if ($msg['id'] === $messageId) {
                    $msg['user_reacted'] = $result['reacted'];
                    $msg['reactions_count'] = count($result['reactions']);
                    break 2;
                }
            }
        }
        $this->beds = $beds;
    }

    /**
     * Returns [Carbon $from, Carbon $to] for the selected shift.
     *
     * The current window comes from ShiftService::getShiftWindow().
     * The previous window is computed by stepping back one shift duration:
     *   - morning  → previous night  = 12h back from morning start (19h yesterday – 07h today)
     *   - afternoon→ previous morning =  6h back from afternoon start
     *   - night    → previous afternoon = 6h back from night start
     */
    private function resolveShiftWindow(): array
    {
        $currentWindow = ShiftService::getShiftWindow();
        [$currentStart, $currentEnd] = $currentWindow;

        if ($this->activeShift === 'current') {
            return $currentWindow;
        }

        // Previous shift: step back from the start of the current shift.
        $shiftInfo = ShiftService::getShiftInfo();
        $stepBack = $shiftInfo['shift'] === 'morning' ? 12 : 6;

        return [
            $currentStart->copy()->subHours($stepBack),
            $currentStart->copy(),
        ];
    }

    /**
     * @param  array<string, mixed>  $patient
     */
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

        // Same-day admission: compute hours from dt_entrada.
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
        return view('sbar.report.filters.evaluations.modal');
    }
}
