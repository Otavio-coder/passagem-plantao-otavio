<?php

namespace App\Livewire;

use App\Models\HandoverAiAnalysis;
use App\Services\ChatAnalyticsService;
use App\Services\Handover\AiAnalysis\HandoverAiAnalysisService;
use App\Services\Handover\AiAnalysis\HandoverTermsAnalysisService;
use App\Services\HandoverSessionService;
use App\Services\ShiftService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

    public ?string $aiTermsAnalysis = null;

    public ?string $nurseAiAnalysis = null;

    public ?string $aiShiftAnalysis = null;

    public ?string $aiHourlyAnalysis = null;

    public ?string $aiContentAnalysis = null;

    public ?string $aiCharDistributionAnalysis = null;

    public ?string $aiActivityAnalysis = null;

    // ── General analysis (inline panel) ───────────────────────────────────────

    public bool $generalAnalysisOpen = false;

    public int $generalAnalysisDays = 30;

    public ?int $generalAnalysisId = null;

    public bool $generalAnalysisLoading = false;

    // ── Reactive ───────────────────────────────────────────────────────────────

    public function setPeriod(?int $period): void
    {
        $this->period = $period;
        $this->sectorFilter = null;
        $this->clearAllChartAnalyses();
        $this->dispatchCalHeatmapUpdate();
    }

    public function setSector(?int $sectorId): void
    {
        $this->sectorFilter = $sectorId > 0 ? (int) $sectorId : null;
        $this->clearAllChartAnalyses();
        $this->dispatchCalHeatmapUpdate();
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

        $avgChars = $service->getAvgCharsForUser($userId, $since);

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
                'avg_beds' => ($sb = $sessions->filter(fn ($s) => ($s['beds_visited'] ?? 0) > 0))->isNotEmpty() ? round($sb->avg('beds_visited'), 1) : null,
                'avg_messages_per_session' => $sessions->isNotEmpty() ? round($sessions->avg('messages_written'), 1) : null,
                'avg_duration_min' => $withDuration->isNotEmpty() ? round($withDuration->avg('duration_min'), 1) : null,
                'avg_chars' => $avgChars,
                'size_label' => $avgChars > 0 ? $service->messageSizeLabel($avgChars) : null,
                'all_archive' => $sessions->every(fn ($s) => ($s['source'] ?? 'chat') === 'archive'),
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
        $this->nurseAiAnalysis = null;
    }

    // ── Análise setorial — delega ao HandoverSectorAnalysis (child component) ───

    public function openSectorAnalysis(int $sectorId, string $sectorName): void
    {
        $this->dispatch('openHandoverSectorAnalysis', sectorId: $sectorId, sectorName: $sectorName);
    }

    public function analyzeTermsWithAi(): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->aiTermsAnalysis = null;

        $sessionCount = $this->sessions->count();
        $professionalCount = $this->sessions->pluck('user_id')->unique()->count();
        $sectorNames = $this->sessions->pluck('sector_name')->filter()->unique()->implode(', ');

        try {
            $this->aiTermsAnalysis = app(HandoverTermsAnalysisService::class)->analyze(
                topTerms: $this->topTerms,
                since: $this->since(),
                sectorId: $this->sectorFilter,
                sessionCount: $sessionCount,
                professionalCount: $professionalCount,
                sectorNames: $sectorNames
            );
        } catch (\Throwable $e) {
            $this->aiTermsAnalysis = 'Erro ao consultar IA: '.$e->getMessage();
        }
    }

    public function clearAiAnalysis(): void
    {
        $this->aiTermsAnalysis = null;
    }

    public function analyzeNurseWithAi(int $userId): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->nurseAiAnalysis = null;

        if (! $this->nurseDetail || $this->nurseDetail['user_id'] !== $userId) {
            return;
        }

        $since = $this->since();
        $topTerms = app(HandoverSessionService::class)->getTopTerms($since, null, $userId);

        try {
            $this->nurseAiAnalysis = app(HandoverTermsAnalysisService::class)->analyzeNurseProfile(
                nurseDetail: $this->nurseDetail,
                topTerms: $topTerms,
                since: $since
            );
        } catch (\Throwable $e) {
            $this->nurseAiAnalysis = 'Erro ao consultar IA: '.$e->getMessage();
        }
    }

    public function clearNurseAiAnalysis(): void
    {
        $this->nurseAiAnalysis = null;
    }

    public function analyzeShiftStatsWithAi(): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->aiShiftAnalysis = null;

        try {
            $this->aiShiftAnalysis = app(HandoverTermsAnalysisService::class)->analyzeShiftStats(
                shiftStats: $this->shiftStats,
                periodLabel: $this->periodLabel()
            );
        } catch (\Throwable $e) {
            $this->aiShiftAnalysis = 'Erro ao consultar IA: '.$e->getMessage();
        }
    }

    public function clearShiftAnalysis(): void
    {
        $this->aiShiftAnalysis = null;
    }

    public function analyzeHourlyWithAi(): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->aiHourlyAnalysis = null;

        try {
            $this->aiHourlyAnalysis = app(HandoverTermsAnalysisService::class)->analyzeHourly(
                heatmap: $this->heatmap,
                periodLabel: $this->periodLabel()
            );
        } catch (\Throwable $e) {
            $this->aiHourlyAnalysis = 'Erro ao consultar IA: '.$e->getMessage();
        }
    }

    public function clearHourlyAnalysis(): void
    {
        $this->aiHourlyAnalysis = null;
    }

    public function analyzeContentWithAi(): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->aiContentAnalysis = null;

        try {
            $this->aiContentAnalysis = app(HandoverTermsAnalysisService::class)->analyzeContent(
                contentClassification: $this->contentClassification,
                periodLabel: $this->periodLabel()
            );
        } catch (\Throwable $e) {
            $this->aiContentAnalysis = 'Erro ao consultar IA: '.$e->getMessage();
        }
    }

    public function clearContentAnalysis(): void
    {
        $this->aiContentAnalysis = null;
    }

    public function analyzeCharDistributionWithAi(): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->aiCharDistributionAnalysis = null;

        try {
            $this->aiCharDistributionAnalysis = app(HandoverTermsAnalysisService::class)->analyzeCharDistribution(
                charDistribution: $this->charDistribution,
                periodLabel: $this->periodLabel()
            );
        } catch (\Throwable $e) {
            $this->aiCharDistributionAnalysis = 'Erro ao consultar IA: '.$e->getMessage();
        }
    }

    public function clearCharDistributionAnalysis(): void
    {
        $this->aiCharDistributionAnalysis = null;
    }

    public function analyzeActivityWithAi(): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $this->aiActivityAnalysis = null;

        try {
            $this->aiActivityAnalysis = app(HandoverTermsAnalysisService::class)->analyzeActivity(
                annotationsByDay: $this->annotationsByDay,
                periodLabel: $this->periodLabel()
            );
        } catch (\Throwable $e) {
            $this->aiActivityAnalysis = 'Erro ao consultar IA: '.$e->getMessage();
        }
    }

    public function clearActivityAnalysis(): void
    {
        $this->aiActivityAnalysis = null;
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
        $service = app(HandoverSessionService::class);

        $liveLengths = DB::table('chat_messages')
            ->when($this->since(), fn ($q) => $q->where('created_at', '>=', $this->since()))
            ->when($this->sectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('user_id, SUM(CHAR_LENGTH(content)) as total_chars, COUNT(*) as msg_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $analyticsLengths = DB::table('chat_analytics_daily')
            ->whereNotNull('user_id')
            ->when($this->since(), fn ($q) => $q->where('date', '>=', $this->since()->toDateString()))
            ->when($this->sectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('user_id, SUM(total_chars) as total_chars, SUM(message_count) as msg_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $msgLengths = $liveLengths->map(function ($row, $userId) use ($analyticsLengths) {
            $a = $analyticsLengths->get($userId);
            $totalChars = (int) $row->total_chars + (int) ($a?->total_chars ?? 0);
            $totalMsgs = (int) $row->msg_count + (int) ($a?->msg_count ?? 0);
            $row->avg_chars = $totalMsgs > 0 ? round($totalChars / $totalMsgs) : 0;

            return $row;
        })->union(
            $analyticsLengths->filter(fn ($a, $id) => ! $liveLengths->has($id))
                ->map(function ($a) {
                    $a->avg_chars = (int) $a->msg_count > 0 ? round((int) $a->total_chars / (int) $a->msg_count) : 0;

                    return $a;
                })
        );

        return $this->sessions
            ->groupBy('user_id')
            ->map(function (Collection $g) use ($weeksInPeriod, $msgLengths, $service) {
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
                    'avg_beds' => ($gb = $g->filter(fn ($s) => $s['beds_visited'] !== null && $s['beds_visited'] > 0))->isNotEmpty() ? (int) round($gb->avg('beds_visited')) : null,
                    'avg_messages' => (int) round($g->avg('messages_written')),
                    'total_messages' => $totalMsgs,
                    'sectors' => $g->pluck('sector_name')->filter()->unique()->implode(', '),
                    'avg_chars' => $avgChars,
                    'size_label' => $avgChars > 0 ? $service->messageSizeLabel($avgChars) : null,
                    'all_archive' => $g->every(fn ($s) => ($s['source'] ?? 'chat') === 'archive'),
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

        // Batch: avg chars + total messages por sector_name (uma query só)
        $sectorCharStats = DB::table('chat_messages')
            ->when($this->since(), fn ($q) => $q->where('created_at', '>=', $this->since()))
            ->whereNotNull('sector_name')
            ->selectRaw('sector_name, COUNT(*) as total_msgs, ROUND(AVG(LENGTH(content))) as avg_chars')
            ->groupBy('sector_name')
            ->get()
            ->keyBy('sector_name');

        return $this->sessions
            ->groupBy('sector_name')
            ->map(function (Collection $g, string $sectorName) use ($sectorCoverage, $sectorCharStats) {
                $total = $g->count();
                $cov = $sectorCoverage->get($sectorName);
                $firstDate = $cov?->first_date ? Carbon::parse($cov->first_date) : null;
                $daysSinceStart = $firstDate ? max(1, (int) $firstDate->diffInDays(now())) : null;
                $totalPossibleShifts = $daysSinceStart ? $daysSinceStart * 3 : null;
                $coveredShifts = $cov ? (int) $cov->covered_shifts : 0;
                $shiftCoveragePct = ($totalPossibleShifts && $coveredShifts)
                    ? min(100, (int) round($coveredShifts / $totalPossibleShifts * 100))
                    : null;

                $charStats = $sectorCharStats->get($sectorName);
                $lastSessionAt = $g->max('started_at');
                $daysInactive = $lastSessionAt
                    ? max(0, (int) Carbon::parse($lastSessionAt)->diffInDays(now()))
                    : null;

                $gsWithMsgs = $g->filter(fn ($s) => ($s['messages_written'] ?? 0) > 0);
                $avgMsgsPerSession = $gsWithMsgs->isNotEmpty()
                    ? round($gsWithMsgs->avg('messages_written'), 1)
                    : null;

                $gsWithDuration = $g->filter(fn ($s) => ($s['duration_min'] ?? 0) >= 1);
                $avgDurationMin = $gsWithDuration->isNotEmpty()
                    ? (int) round($gsWithDuration->avg('duration_min'))
                    : null;

                return [
                    'sector_name' => $sectorName,
                    'sector_id' => $g->pluck('sector_id')->filter()->first(),
                    'sessions' => $total,
                    'nurses_count' => $g->pluck('user_id')->unique()->count(),
                    'nurses' => $g->pluck('user_name')->filter()->unique()->sort()->values()->all(),
                    'avg_beds' => ($gsb = $g->filter(fn ($s) => $s['beds_visited'] !== null && $s['beds_visited'] > 0))->isNotEmpty() ? (int) round($gsb->avg('beds_visited')) : null,
                    'total_messages' => $charStats ? (int) $charStats->total_msgs : null,
                    'avg_chars' => $charStats ? (int) $charStats->avg_chars : null,
                    'avg_msgs_per_session' => $avgMsgsPerSession,
                    'avg_duration_min' => $avgDurationMin,
                    'last_session_at' => $lastSessionAt,
                    'days_inactive' => $daysInactive,
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
        // Live messages
        $liveCounts = DB::table('chat_messages')
            ->when($this->since(), fn ($q) => $q->where('created_at', '>=', $this->since()))
            ->when($this->sectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->get()
            ->keyBy('hour')
            ->map(fn ($r) => (int) $r->cnt)
            ->toArray();

        // Archive analytics
        $archiveCounts = app(ChatAnalyticsService::class)->getHourCounts($this->since(), $this->sectorFilter);

        // Merge
        $merged = $liveCounts;
        foreach ($archiveCounts as $hour => $count) {
            $merged[$hour] = ($merged[$hour] ?? 0) + $count;
        }

        $max = max(1, ! empty($merged) ? max($merged) : 1);
        $rows = [
            ['key' => 'M', 'label' => 'Manhã', 'color' => '#D97706', 'hours' => range(7, 13)],
            ['key' => 'T', 'label' => 'Tarde', 'color' => '#EA580C', 'hours' => range(13, 19)],
            ['key' => 'N', 'label' => 'Noite', 'color' => '#4F46E5', 'hours' => array_merge(range(19, 23), range(0, 7))],
        ];

        return array_map(function ($row) use ($merged, $max) {
            $row['cells'] = array_map(fn ($h) => [
                'hour' => str_pad($h, 2, '0', STR_PAD_LEFT),
                'count' => (int) ($merged[$h] ?? 0),
                'pct' => (int) round(($merged[$h] ?? 0) / $max * 100),
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
    public function topTerms(): array
    {
        $since = $this->since();
        $sector = $this->sectorFilter;
        $key = 'panorama_top_terms_'.($since?->toDateString() ?? 'all').'_'.($sector ?? '0');

        return cache()->remember($key, now()->addMinutes(30), fn () => app(HandoverSessionService::class)->getTopTerms($since, $sector)
        );
    }

    #[Computed]
    public function charDistribution(): array
    {
        return app(HandoverSessionService::class)->getCharDistribution($this->since(), $this->sectorFilter);
    }

    #[Computed]
    public function sessionsByDay(): array
    {
        return $this->sessions
            ->groupBy('shift_date')
            ->map(fn ($g, $date) => ['date' => $date, 'value' => $g->count()])
            ->values()
            ->all();
    }

    #[Computed]
    public function annotationsByDay(): array
    {
        $live = DB::table('chat_messages')
            ->when($this->since(), fn ($q) => $q->where('created_at', '>=', $this->since()))
            ->when($this->sectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as value')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->pluck('value', 'date')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $archive = DB::table('chat_analytics_daily')
            ->when($this->since(), fn ($q) => $q->where('date', '>=', $this->since()->toDateString()))
            ->when($this->sectorFilter, fn ($q) => $q->where('sector_id', $this->sectorFilter))
            ->selectRaw('date, SUM(message_count) as value')
            ->groupBy('date')
            ->get()
            ->pluck('value', 'date')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $merged = $live;
        foreach ($archive as $date => $count) {
            $merged[$date] = ($merged[$date] ?? 0) + $count;
        }

        ksort($merged);

        return array_map(
            fn ($date, $value) => ['date' => $date, 'value' => $value],
            array_keys($merged),
            $merged
        );
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
            'avg_patients_per_session' => ($sp = $s->filter(fn ($x) => ($x['beds_visited'] ?? 0) > 0))->isNotEmpty() ? (int) round($sp->avg('beds_visited')) : null,
            'avg_msgs_per_patient' => $uniquePatients > 0 ? round($totalMessages / $uniquePatients, 1) : null,
            'avg_msgs_per_session' => $s->isNotEmpty() ? (int) round($s->avg('messages_written')) : null,
            'avg_duration_min' => $withDuration->isNotEmpty() ? (int) round($withDuration->avg('duration_min')) : null,
            'active_nurses' => $s->pluck('user_id')->unique()->count(),
            'active_sectors' => $s->pluck('sector_name')->filter()->unique()->count(),
        ];
    }

    // ── Análise geral inline ──────────────────────────────────────────────────

    public function toggleGeneralAnalysis(): void
    {
        $this->generalAnalysisOpen = ! $this->generalAnalysisOpen;
        if ($this->generalAnalysisOpen) {
            $this->findExistingGeneralAnalysis();
        }
    }

    public function setGeneralAnalysisDays(int $days): void
    {
        $this->generalAnalysisDays = $days;
        $this->generalAnalysisId = null;
        $this->findExistingGeneralAnalysis();
    }

    public function generateGeneralAnalysis(): void
    {
        if (! env('PRISM_WRITING_ANALYSIS', false)) {
            $this->dispatch('show-ai-disabled');

            return;
        }

        $end = now();
        $start = now()->subDays($this->generalAnalysisDays)->startOfDay();

        $analysis = HandoverAiAnalysis::create([
            'analysis_type' => 'general',
            'sector_id' => null,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'generated_by' => Auth::id(),
            'status' => 'pending',
        ]);

        $this->generalAnalysisId = $analysis->id;
        $this->generalAnalysisLoading = true;

        try {
            app(HandoverAiAnalysisService::class)->analyze($analysis);
        } catch (\Throwable) {
            // status updated to failed inside service
        }

        $this->generalAnalysisLoading = false;
    }

    private function findExistingGeneralAnalysis(): void
    {
        $end = now();
        $start = now()->subDays($this->generalAnalysisDays)->startOfDay();

        $existing = HandoverAiAnalysis::findCompleted('general', $start, $end);
        $this->generalAnalysisId = $existing?->id;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('panorama.index', [
            'sessions' => $this->sessions,
            'nurseStats' => $this->nurseStats,
            'sectorStats' => $this->sectorStats,
            'heatmap' => $this->heatmap,
            'shiftStats' => $this->shiftStats,
            'sectors' => $this->sectors,
            'continuityStats' => $this->continuityStats,
            'contentClassification' => $this->contentClassification,
            'institutionalStats' => $this->institutionalStats,
            'topTerms' => $this->topTerms,
            'charDistribution' => $this->charDistribution,
            'sessionsByDay' => $this->sessionsByDay,
            'annotationsByDay' => $this->annotationsByDay,
            'aiTermsAnalysis' => $this->aiTermsAnalysis,
            'nurseAiAnalysis' => $this->nurseAiAnalysis,
            'aiShiftAnalysis' => $this->aiShiftAnalysis,
            'aiHourlyAnalysis' => $this->aiHourlyAnalysis,
            'aiContentAnalysis' => $this->aiContentAnalysis,
            'aiCharDistributionAnalysis' => $this->aiCharDistributionAnalysis,
            'aiActivityAnalysis' => $this->aiActivityAnalysis,
            'generalAnalysis' => $this->generalAnalysisId ? HandoverAiAnalysis::find($this->generalAnalysisId) : null,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function periodLabel(): string
    {
        return match ($this->period) {
            7 => 'últimos 7 dias',
            30 => 'últimos 30 dias',
            90 => 'últimos 90 dias',
            180 => 'últimos 6 meses',
            default => 'todo o período disponível',
        };
    }

    private function clearAllChartAnalyses(): void
    {
        $this->aiTermsAnalysis = null;
        $this->aiShiftAnalysis = null;
        $this->aiHourlyAnalysis = null;
        $this->aiContentAnalysis = null;
        $this->aiCharDistributionAnalysis = null;
        $this->aiActivityAnalysis = null;
    }

    private function dispatchCalHeatmapUpdate(): void
    {
        $sessionsByDay = $this->annotationsByDay;

        if ($this->period === null) {
            $calRangeMonths = 12;
            $calStart = now()->subMonths(11)->startOfMonth()->toDateString();
        } else {
            $calRangeMonths = match (true) {
                $this->period === 7 => 1,
                $this->period === 30 => 2,
                $this->period === 90 => 4,
                $this->period === 180 => 7,
                default => 2,
            };
            $calStart = now()->subDays($this->period)->startOfMonth()->toDateString();
        }

        $calMax = max(1, count($sessionsByDay) > 0 ? max(array_column($sessionsByDay, 'value')) : 1);

        $this->dispatch('cal-heatmap-update',
            data: $sessionsByDay,
            start: $calStart,
            range: $calRangeMonths,
            maxVal: $calMax,
        );
    }

    private function since(): ?Carbon
    {
        return $this->period !== null ? now()->subDays($this->period)->startOfDay() : null;
    }
}
