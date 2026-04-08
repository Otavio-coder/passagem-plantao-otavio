<?php

namespace App\Livewire;

use App\Services\Tasy\TasyService;
use Carbon\Carbon;
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

    public array $userPhotos = []; // map: user_name => base64 photo

    public int $totalMessages = 0;

    public string $sectorName = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    protected $listeners = ['openEvaluationsModal' => 'open'];

    public function mount(): void
    {
        $this->dateFrom = Carbon::today()->format('Y-m-d');
        $this->dateTo = Carbon::today()->format('Y-m-d');
    }

    public function open($sectorId = null): void
    {
        if ($sectorId) {
            $this->sectorId = (int) $sectorId;
        }

        if (empty($this->dateFrom)) {
            $this->dateFrom = Carbon::today()->format('Y-m-d');
            $this->dateTo = Carbon::today()->format('Y-m-d');
        }

        $this->isOpen = true;
        $this->loadEvaluations();
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function updatedDateFrom(): void
    {
        $this->loadEvaluations();
    }

    public function updatedDateTo(): void
    {
        $this->loadEvaluations();
    }

    public function loadEvaluations(): void
    {
        if (! $this->sectorId) {
            $this->loading = false;

            return;
        }

        $this->loading = true;
        $this->userPhotos = [];

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

            $fromDate = Carbon::parse($this->dateFrom)->startOfDay();
            $toDate = Carbon::parse($this->dateTo)->endOfDay();

            $rawMessages = DB::table('chat_messages as cm')
                ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                ->leftJoin('chat_message_pins as cmp', function ($join) {
                    $join->on('cmp.message_id', '=', 'cm.id')
                        ->whereNull('cmp.unpinned_at');
                })
                ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                ->whereBetween('cm.created_at', [$fromDate, $toDate])
                ->select([
                    'cm.id',
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'u.name as user_name',
                    'u.photo as user_photo',
                    DB::raw('CASE WHEN cmp.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned'),
                ])
                ->orderBy('cm.created_at', 'desc')
                ->get();

            $patientsMap = collect($patients)->keyBy('nr_atendimento');
            $messagesByAttendance = $rawMessages->groupBy('nr_atendimento');

            $beds = [];
            $totalMessages = 0;

            foreach ($messagesByAttendance as $nrAtendimento => $messages) {
                $patient = $patientsMap->get($nrAtendimento);
                if (! $patient) {
                    continue;
                }

                $formattedMessages = [];
                foreach ($messages as $message) {
                    $dt = Carbon::parse($message->created_at);
                    $shiftInfo = getShiftInfo($dt);
                    $userName = $message->user_name ?? 'Desconhecido';

                    // Popula o mapa de fotos (uma vez por usuário)
                    if (! isset($this->userPhotos[$userName]) && ! empty($message->user_photo)) {
                        $raw = $message->user_photo;
                        $decoded = base64_decode($raw, true);
                        if ($decoded !== false && strlen($decoded) > 50
                            && strpos($decoded, '"error"') === false) {
                            $this->userPhotos[$userName] = $raw;
                        }
                    }

                    $formattedMessages[] = [
                        'id' => $message->id,
                        'content' => nl2br(e($message->content)),
                        'user_name' => $userName,
                        'user_initials' => $this->getInitials($userName),
                        'timestamp' => $this->formatTimestamp($dt),
                        'turno' => $this->getShiftLabel($shiftInfo['shift']),
                        'is_pinned' => (bool) $message->is_pinned,
                    ];
                }

                $dtEntrada = $patient['dt_entrada'] ?? null;
                $dischargeInfo = $patient['discharge_info'] ?? null;

                $beds[] = [
                    'leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                    'nome_paciente' => $patient['nm_pessoa_fisica'] ?? 'N/A',
                    'prontuario' => $patient['nr_prontuario'] ?? 'N/A',
                    'atendimento' => $nrAtendimento,
                    'setor' => $patient['ds_setor_atendimento'] ?? 'N/A',
                    'dt_entrada_formatted' => $dtEntrada ? Carbon::parse($dtEntrada)->format('d/m/Y') : null,
                    'dt_alta_formatted' => $dischargeInfo['dt_alta_medico_formatted'] ?? null,
                    'internment_days' => $patient['internment_days'] !== null ? (int) $patient['internment_days'] : null,
                    'mensagens' => $formattedMessages,
                    'total_mensagens' => count($formattedMessages),
                    'has_pinned' => collect($formattedMessages)->contains('is_pinned', true),
                ];

                $totalMessages += count($formattedMessages);
            }

            $this->beds = array_values($beds);
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

    private function getShiftLabel(string $turno): string
    {
        return match ($turno) {
            'morning' => 'Manhã',
            'afternoon' => 'Tarde',
            'night' => 'Noite',
            default => ucfirst($turno),
        };
    }

    private function formatTimestamp(Carbon $dt): string
    {
        if ($dt->isToday()) {
            return $dt->format('H:i');
        }
        if ($dt->isYesterday()) {
            return 'Ontem '.$dt->format('H:i');
        }

        return $dt->format('d/m H:i');
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
