<?php

namespace App\Livewire;

use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ShiftEvaluationsModal extends Component
{
    public bool $isOpen = false;
    public ?int $sectorId = null;
    public bool $loading = false;
    public array $beds = [];
    public string $search = '';
    public string $shiftWindowLabel = '';
    public string $sectorName = '';
    public int $totalMessages = 0;

    protected $listeners = ['openEvaluationsModal' => 'open'];

    public function open($sectorId = null)
    {
        if ($sectorId) {
            $this->sectorId = (int) $sectorId;
        }
        $this->search = '';
        $this->isOpen = true;
        $this->loadEvaluations();
    }

    public function close()
    {
        $this->isOpen = false;
    }

    public function updatedSearch()
    {
        $this->filterBeds();
    }

    public function loadEvaluations()
    {
        if (!$this->sectorId) {
            $this->loading = false;
            return;
        }

        $this->loading = true;

        try {
            $tasy = new TasyService();
            $patients = $tasy->getSectorPatientsForSbar($this->sectorId);

            if (empty($patients)) {
                $this->beds = [];
                $this->sectorName = '';
                $this->totalMessages = 0;
                return;
            }

            $this->sectorName = $patients[0]['ds_setor_atendimento'] ?? 'Setor';
            $since = $this->computeShiftSince();
            $this->shiftWindowLabel = $this->computeShiftWindowLabel($since);

            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));

            $rawMessages = DB::table('chat_messages as cm')
                ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                ->leftJoin('chat_message_pins as cmp', function ($join) {
                    $join->on('cmp.message_id', '=', 'cm.id')
                        ->whereNull('cmp.unpinned_at');
                })
                ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                ->where('cm.created_at', '>=', $since)
                ->select([
                    'cm.id',
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'cm.updated_at',
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
                if (!$patient) continue;

                $formattedMessages = [];
                foreach ($messages as $message) {
                    $dt = Carbon::parse($message->created_at);
                    $shiftInfo = getShiftInfo($dt);

                    $formattedMessages[] = [
                        'id'            => $message->id,
                        'content'       => nl2br(e($message->content)),
                        'user_name'     => $message->user_name ?? 'Desconhecido',
                        'user_initials' => $this->getInitials($message->user_name ?? 'U'),
                        'timestamp'     => $this->formatTimestamp($dt),
                        'turno'         => $this->getShiftLabel($shiftInfo['shift']),
                        'is_pinned'     => (bool) $message->is_pinned,
                    ];
                }

                $beds[] = [
                    'leito'          => $patient['cd_unidade_basica'] ?? 'N/A',
                    'nome_paciente'  => $patient['nm_pessoa_fisica'] ?? 'N/A',
                    'prontuario'     => $patient['nr_prontuario'] ?? 'N/A',
                    'atendimento'    => $nrAtendimento,
                    'mensagens'      => $formattedMessages,
                    'total_mensagens' => count($formattedMessages),
                    'has_pinned'     => collect($formattedMessages)->contains('is_pinned', true),
                ];

                $totalMessages += count($formattedMessages);
            }

            usort($beds, fn($a, $b) => strnatcmp($a['leito'], $b['leito']));

            $this->beds = $beds;
            $this->totalMessages = $totalMessages;

        } catch (\Exception $e) {
            Log::error('[ShiftEvaluationsModal] Error loading evaluations', [
                'sector_id' => $this->sectorId,
                'error'     => $e->getMessage(),
            ]);
            $this->beds = [];
            $this->totalMessages = 0;
        } finally {
            $this->loading = false;
        }
    }

    public function filterBeds()
    {
        // Search is applied in the blade via Alpine.js on the loaded data
        // Nothing to do server-side; Alpine handles the filtering
    }

    private function computeShiftSince(): Carbon
    {
        $now = Carbon::now();
        $hour = (int) $now->format('H');

        if ($hour >= 7 && $hour < 13) {
            // Morning: show previous night (19h yesterday) + current morning
            return $now->copy()->subDay()->setTime(19, 0, 0);
        } elseif ($hour >= 13 && $hour < 19) {
            // Afternoon: show morning (07h today) + current afternoon
            return $now->copy()->setTime(7, 0, 0);
        } else {
            // Night: show afternoon (13h today or yesterday) + current night
            return $hour >= 19
                ? $now->copy()->setTime(13, 0, 0)
                : $now->copy()->subDay()->setTime(13, 0, 0);
        }
    }

    private function computeShiftWindowLabel(Carbon $since): string
    {
        $hour = (int) Carbon::now()->format('H');

        if ($hour >= 7 && $hour < 13) {
            return 'Noite anterior (19h–07h) + Manhã atual (07h–13h)';
        } elseif ($hour >= 13 && $hour < 19) {
            return 'Manhã (07h–13h) + Tarde atual (13h–19h)';
        } else {
            return 'Tarde (13h–19h) + Noite atual (19h–07h)';
        }
    }

    private function getShiftLabel(string $turno): string
    {
        return match ($turno) {
            'morning'   => 'Manhã',
            'afternoon' => 'Tarde',
            'night'     => 'Noite',
            default     => ucfirst($turno),
        };
    }

    private function formatTimestamp(Carbon $dt): string
    {
        if ($dt->isToday()) {
            return $dt->format('H:i');
        }
        if ($dt->isYesterday()) {
            return 'Ontem ' . $dt->format('H:i');
        }
        return $dt->format('d/m H:i');
    }

    private function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }
        return strtoupper(substr($parts[0] ?? 'U', 0, 2));
    }

    public function render()
    {
        return view('livewire.shift-evaluations-modal');
    }
}
