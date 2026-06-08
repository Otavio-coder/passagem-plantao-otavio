<?php

namespace App\Livewire;

use App\Services\HandoverSessionService;
use App\Services\ShiftService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Panorama extends Component
{
    public ?int $period = null;

    public ?int $sectorFilter = null;

    public bool $nurseModalOpen = false;

    #[Locked]
    public ?array $nurseDetail = null;

    public bool $nurseDetailLoading = false;

    // ── Reactive ───────────────────────────────────────────────────────────────

    public function setPeriod(?int $period): void
    {
        $this->period = $period;
        $this->sectorFilter = null;
    }

    public function setSector(?int $sectorId): void
    {
        $this->sectorFilter = $sectorId > 0 ? (int) $sectorId : null;
    }

    // ── Nurse modal ────────────────────────────────────────────────────────────

    public function openAndLoadNurse(int $userId): void
    {
        $this->nurseModalOpen = true;
        $this->nurseDetail = null;
        $this->nurseDetailLoading = true;
    }

    public function loadNurseDetail(int $userId): void
    {
        $since = $this->since();
        $service = app(HandoverSessionService::class);

        $sessions = $service->getSessions($since)
            ->where('user_id', $userId)
            ->values();

        $user = DB::table('users')->find($userId);

        $avgChars = (int) DB::table('chat_messages')
            ->where('user_id', $userId)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('ROUND(AVG(CHAR_LENGTH(content)), 0) as avg_chars')
            ->value('avg_chars');

        $withDuration = $sessions->filter(fn ($s) => $s['duration_min'] !== null && $s['duration_min'] > 0);
        $weeksInPeriod = $this->period !== null ? max($this->period / 7, 1) : max($sessions->count(), 1);

        $shiftCounts = [
            'M' => $sessions->where('shift', 'M')->count(),
            'T' => $sessions->where('shift', 'T')->count(),
            'N' => $sessions->where('shift', 'N')->count(),
        ];

        $sectorDist = $sessions->groupBy('sector_name')
            ->map(fn ($g) => $g->count())
            ->sortByDesc(fn ($c) => $c)
            ->all();

        $durationBuckets = ['< 10min' => 0, '10–20min' => 0, '20–40min' => 0, '40–60min' => 0, '> 60min' => 0];
        foreach ($withDuration as $s) {
            $d = $s['duration_min'];
            $durationBuckets[match (true) {
                $d < 10 => '< 10min',
                $d < 20 => '10–20min',
                $d < 40 => '20–40min',
                $d < 60 => '40–60min',
                default => '> 60min',
            }]++;
        }

        $this->nurseDetail = [
            'user_id' => $userId,
            'name' => $user?->name ?? '—',
            'period_days' => $this->period,
            'summary' => [
                'total_sessions' => $sessions->count(),
                'sessions_per_week' => round($sessions->count() / $weeksInPeriod, 1),
                'avg_beds' => $sessions->isNotEmpty() ? round($sessions->avg('beds_visited'), 1) : null,
                'avg_messages_per_session' => $sessions->isNotEmpty() ? round($sessions->avg('messages_written'), 1) : null,
                'avg_duration_min' => $withDuration->isNotEmpty() ? round($withDuration->avg('duration_min'), 1) : null,
                'avg_chars' => $avgChars,
                'tamanho_label' => $avgChars > 0 ? $this->tamanhoLabel($avgChars) : null,
            ],
            'shift_distribution' => $shiftCounts,
            'sector_distribution' => $sectorDist,
            'duration_buckets' => $durationBuckets,
            'recent_sessions' => $sessions->take(50)->values()->all(),
        ];

        $this->nurseDetailLoading = false;
    }

    public function closeNurseModal(): void
    {
        $this->nurseModalOpen = false;
        $this->nurseDetail = null;
    }

    // ── Computed properties — sem cache, sempre fresh ─────────────────────────

    #[Computed]
    public function sessions(): Collection
    {
        return app(HandoverSessionService::class)->getSessions($this->since(), $this->sectorFilter);
    }

    #[Computed]
    public function nurseStats(): Collection
    {
        $weeksInPeriod = $this->period !== null ? max($this->period / 7, 1) : null;

        $msgLengths = DB::table('chat_messages')
            ->when($this->since(), fn ($q) => $q->where('created_at', '>=', $this->since()))
            ->when($this->sectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('user_id, ROUND(AVG(CHAR_LENGTH(content)), 0) as avg_chars')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $this->sessions
            ->groupBy('user_id')
            ->map(function (Collection $g) use ($weeksInPeriod, $msgLengths) {
                $userId = $g->first()['user_id'];
                $avgChars = (int) ($msgLengths->get($userId)?->avg_chars ?? 0);
                $shiftCounts = [
                    'M' => $g->where('shift', 'M')->count(),
                    'T' => $g->where('shift', 'T')->count(),
                    'N' => $g->where('shift', 'N')->count(),
                ];
                $dominantShift = array_search(max($shiftCounts), $shiftCounts);
                $sessions = $g->count();
                // messages_written já inclui sessões do archive (nurse_archive_sessions.message_count)
                $totalMsgs = $g->sum('messages_written');

                return [
                    'user_id' => $userId,
                    'name' => $g->first()['user_name'],
                    'sessions' => $sessions,
                    'sessions_per_week' => $weeksInPeriod ? round($sessions / $weeksInPeriod, 1) : null,
                    'avg_beds' => (int) round($g->avg('beds_visited')),
                    'avg_messages' => (int) round($g->avg('messages_written')),
                    'total_messages' => $totalMsgs,
                    'sectors' => $g->pluck('sector_name')->filter()->unique()->implode(', '),
                    'avg_chars' => $avgChars,
                    'tamanho_label' => $avgChars > 0 ? $this->tamanhoLabel($avgChars) : null,
                    'dominant_shift' => $dominantShift ?: null,
                    'shift_pct' => [
                        'M' => $sessions > 0 ? round($shiftCounts['M'] / $sessions * 100) : 0,
                        'T' => $sessions > 0 ? round($shiftCounts['T'] / $sessions * 100) : 0,
                        'N' => $sessions > 0 ? round($shiftCounts['N'] / $sessions * 100) : 0,
                    ],
                ];
            })
            ->sortByDesc('total_messages')
            ->values();
    }

    #[Computed]
    public function sectorStats(): Collection
    {
        // Cobertura real por setor: turnos com anotação / turnos possíveis desde início do uso.
        // Um turno possível = (setor, turno M/T/N, data lógica). Total possível = dias × 3.
        // Usa chat_messages pois o archive não tem granularidade de hora/turno por mensagem.
        $m = ShiftService::SHIFT_M_START;
        $t = ShiftService::SHIFT_T_START;
        $n = ShiftService::SHIFT_N_START;

        $shiftExpr = "CASE WHEN (HOUR(created_at)*60+MINUTE(created_at)) >= {$m} AND (HOUR(created_at)*60+MINUTE(created_at)) < {$t} THEN 'M'"
            ." WHEN (HOUR(created_at)*60+MINUTE(created_at)) >= {$t} AND (HOUR(created_at)*60+MINUTE(created_at)) < {$n} THEN 'T'"
            .' ELSE \'N\' END';

        $shiftDateExpr = "CASE WHEN (HOUR(created_at)*60+MINUTE(created_at)) < {$m} THEN DATE(created_at - INTERVAL 1 DAY) ELSE DATE(created_at) END";

        // Data mais antiga por setor — combina chat_messages + archive (sector_name JSON array)
        $archiveFirstDates = DB::table('chat_messages_archive')
            ->whereNotNull('sector_name')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(sector_name,'$[0]')) as sector, MIN(first_message_at) as first_date")
            ->groupByRaw("JSON_UNQUOTE(JSON_EXTRACT(sector_name,'$[0]'))")
            ->get()
            ->keyBy('sector');

        $sectorCoverage = DB::table('chat_messages')
            ->whereNotNull('sector_name')
            ->selectRaw("
                sector_name,
                DATE(MIN(created_at)) as first_date,
                COUNT(DISTINCT CONCAT({$shiftExpr},'-',{$shiftDateExpr})) as covered_shifts
            ")
            ->groupBy('sector_name')
            ->get()
            ->keyBy('sector_name')
            ->map(function ($row) use ($archiveFirstDates) {
                // Usa a data mais antiga entre chat_messages e archive
                $archiveDate = $archiveFirstDates->get($row->sector_name)?->first_date;
                if ($archiveDate && $archiveDate < $row->first_date) {
                    $row->first_date = $archiveDate;
                }

                return $row;
            });

        return $this->sessions
            ->groupBy('sector_name')
            ->map(function (Collection $g, string $sectorName) use ($sectorCoverage) {
                $total = $g->count();
                $cov = $sectorCoverage->get($sectorName);
                $firstDate = $cov?->first_date ? Carbon::parse($cov->first_date) : null;
                $daysSinceStart = $firstDate ? max(1, (int) $firstDate->diffInDays(now())) : null;
                $totalPossibleShifts = $daysSinceStart ? $daysSinceStart * 3 : null;
                $coveredShifts = $cov ? (int) $cov->covered_shifts : 0;
                $shiftCoveragePct = ($totalPossibleShifts && $coveredShifts)
                    ? min(100, (int) round($coveredShifts / $totalPossibleShifts * 100))
                    : null;

                return [
                    'sector_name' => $sectorName,
                    'sector_id' => $g->first()['sector_id'],
                    'sessions' => $total,
                    'nurses_count' => $g->pluck('user_id')->unique()->count(),
                    'nurses' => $g->pluck('user_name')->filter()->unique()->sort()->values()->all(),
                    'avg_beds' => (int) round($g->avg('beds_visited')),
                    'shift_M' => $g->where('shift', 'M')->count(),
                    'shift_T' => $g->where('shift', 'T')->count(),
                    'shift_N' => $g->where('shift', 'N')->count(),
                    'pct_M' => $total > 0 ? round($g->where('shift', 'M')->count() / $total * 100) : 0,
                    'pct_T' => $total > 0 ? round($g->where('shift', 'T')->count() / $total * 100) : 0,
                    'pct_N' => $total > 0 ? round($g->where('shift', 'N')->count() / $total * 100) : 0,
                    'shift_coverage_pct' => $shiftCoveragePct,
                    'covered_shifts' => $coveredShifts,
                    'total_possible_shifts' => $totalPossibleShifts,
                    'days_since_start' => $daysSinceStart,
                ];
            })
            ->sortByDesc('sessions')
            ->values();
    }

    #[Computed]
    public function heatmap(): array
    {
        $heatmapRaw = DB::table('chat_messages')
            ->when($this->since(), fn ($q) => $q->where('created_at', '>=', $this->since()))
            ->when($this->sectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $max = max(1, $heatmapRaw->max('cnt'));
        $rows = [
            ['key' => 'M', 'label' => 'Manhã', 'color' => '#D97706', 'hours' => range(7, 13)],
            ['key' => 'T', 'label' => 'Tarde', 'color' => '#EA580C', 'hours' => range(13, 19)],
            ['key' => 'N', 'label' => 'Noite', 'color' => '#4F46E5', 'hours' => array_merge(range(19, 23), range(0, 7))],
        ];

        return array_map(function ($row) use ($heatmapRaw, $max) {
            $row['cells'] = array_map(fn ($h) => [
                'hour' => str_pad($h, 2, '0', STR_PAD_LEFT),
                'count' => (int) ($heatmapRaw->get($h)?->cnt ?? 0),
                'pct' => (int) round(($heatmapRaw->get($h)?->cnt ?? 0) / $max * 100),
            ], $row['hours']);

            return $row;
        }, $rows);
    }

    #[Computed]
    public function shiftStats(): array
    {
        $total = $this->sessions->count();

        return [
            'total' => $total,
            'M' => $this->sessions->where('shift', 'M')->count(),
            'T' => $this->sessions->where('shift', 'T')->count(),
            'N' => $this->sessions->where('shift', 'N')->count(),
            'pct_M' => $total > 0 ? round($this->sessions->where('shift', 'M')->count() / $total * 100) : 0,
            'pct_T' => $total > 0 ? round($this->sessions->where('shift', 'T')->count() / $total * 100) : 0,
            'pct_N' => $total > 0 ? round($this->sessions->where('shift', 'N')->count() / $total * 100) : 0,
        ];
    }

    #[Computed]
    public function sectors(): Collection
    {
        return DB::table('chat_messages')
            ->when($this->since(), fn ($q) => $q->where('created_at', '>=', $this->since()))
            ->whereNotNull('sector_name')
            ->selectRaw('MAX(sector_id) as sector_id, sector_name, COUNT(*) as cnt')
            ->groupBy('sector_name')
            ->orderByDesc('cnt')
            ->get()
            ->pluck('sector_name', 'sector_id');
    }

    #[Computed]
    public function topPendings(): array
    {
        return app(HandoverSessionService::class)->getTopPendings($this->since(), $this->sectorFilter);
    }

    #[Computed]
    public function topTerms(): array
    {
        return app(HandoverSessionService::class)->getTopTerms($this->since(), $this->sectorFilter);
    }

    #[Computed]
    public function continuityStats(): array
    {
        return app(HandoverSessionService::class)->getContinuityStats($this->since(), $this->sectorFilter);
    }

    #[Computed]
    public function contentClassification(): array
    {
        return app(HandoverSessionService::class)->getContentClassification($this->since(), $this->sectorFilter);
    }

    #[Computed]
    public function institutionalStats(): array
    {
        $s = $this->sessions; // já inclui log + chat_messages + nurse_archive_sessions
        $since = $this->since();
        $hasSectorFilter = $this->sectorFilter !== null;

        // ── Totais de mensagens — 1 query ativa + 1 archive (sem filtro de setor) ──
        $activeAgg = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($hasSectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('COUNT(*) as msgs, COUNT(DISTINCT nr_atendimento) as patients')
            ->first();

        $archivedMsgs = 0;
        $archivedPatients = 0;
        if (! $hasSectorFilter) {
            $archivedMsgs = (int) DB::table('chat_messages_archive')
                ->when($since, fn ($q) => $q->where('last_message_at', '>=', $since))
                ->sum('message_count');

            $archivedPatients = DB::table('chat_messages_archive')
                ->when($since, fn ($q) => $q->where('last_message_at', '>=', $since))
                ->whereNotIn('nr_atendimento', DB::table('chat_messages')->select('nr_atendimento')->whereNotNull('nr_atendimento'))
                ->count();
        }

        $totalMessages = (int) ($activeAgg->msgs ?? 0) + $archivedMsgs;
        $uniquePatients = (int) ($activeAgg->patients ?? 0) + $archivedPatients;

        // ── Derivados de sessions() — sem queries adicionais ──────────────────
        $withDuration = $s->filter(fn ($x) => $x['duration_min'] !== null && $x['duration_min'] > 0);

        return [
            'total_sessions' => $s->count(),
            'total_messages' => $totalMessages,
            'unique_patients' => $uniquePatients,
            'avg_patients_per_session' => $s->isNotEmpty() ? (int) round($s->avg('beds_visited')) : null,
            'avg_msgs_per_patient' => $uniquePatients > 0 ? round($totalMessages / $uniquePatients, 1) : null,
            'avg_msgs_per_session' => $s->isNotEmpty() ? (int) round($s->avg('messages_written')) : null,
            'avg_duration_min' => $withDuration->isNotEmpty() ? (int) round($withDuration->avg('duration_min')) : null,
            'active_nurses' => $s->pluck('user_id')->unique()->count(),
            'active_sectors' => $s->pluck('sector_name')->filter()->unique()->count(),
        ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.panorama', [
            'sessions' => $this->sessions,
            'nurseStats' => $this->nurseStats,
            'sectorStats' => $this->sectorStats,
            'heatmap' => $this->heatmap,
            'shiftStats' => $this->shiftStats,
            'sectors' => $this->sectors,
            'continuityStats' => $this->continuityStats,
            'contentClassification' => $this->contentClassification,
            'institutionalStats' => $this->institutionalStats,
            'topPendings' => $this->topPendings,
            'topTerms' => $this->topTerms,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function since(): ?Carbon
    {
        return $this->period !== null ? now()->subDays($this->period)->startOfDay() : null;
    }

    private function tamanhoLabel(int $avgChars): string
    {
        return match (true) {
            $avgChars < 60 => 'notas curtas',
            $avgChars <= 200 => 'tamanho adequado',
            default => 'notas longas',
        };
    }
}
