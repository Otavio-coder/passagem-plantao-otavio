<?php

namespace App\Http\Controllers;

use App\Models\ShiftHandover;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HandoverMetricsController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->query('period', '30'); // days
        $sectorFilter = $request->query('sector');

        $since = now()->subDays((int) $period);

        $base = ShiftHandover::where('status', ShiftHandover::STATUS_COMPLETED)
            ->where('started_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter));

        // ── Incomplete/abandoned stats ─────────────────────────────────────────
        $abandonedCount = ShiftHandover::where('status', ShiftHandover::STATUS_ABANDONED)
            ->where('started_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter))
            ->count();

        $canceledCount = ShiftHandover::where('status', ShiftHandover::STATUS_CANCELED)
            ->where('started_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter))
            ->count();

        $avgCompletionPct = (clone $base)
            ->whereNotNull('completion_percentage')
            ->avg('completion_percentage');

        // ── Overview cards ────────────────────────────────────────────────────
        $totalSessions = (clone $base)->count();

        $avgDurationSec = (clone $base)->whereNotNull('duration_seconds')->avg('duration_seconds');

        $avgBedsPerSession = (clone $base)->avg('beds_visited');

        $avgSecPerBed = (clone $base)
            ->whereNotNull('duration_seconds')
            ->where('beds_visited', '>', 0)
            ->selectRaw('AVG(duration_seconds / beds_visited) as avg')
            ->value('avg');

        $avgBedsPerMinute = (clone $base)
            ->whereNotNull('duration_seconds')
            ->where('duration_seconds', '>', 0)
            ->where('beds_visited', '>', 0)
            ->selectRaw('AVG(beds_visited / (duration_seconds / 60.0)) as avg')
            ->value('avg');

        // Hours saved vs. 45-min baseline (estimated)
        $baselineMinutes = 45;
        $actualAvgMinutes = $avgDurationSec ? $avgDurationSec / 60 : null;
        $savedMinutesPerSession = $actualAvgMinutes ? max(0, $baselineMinutes - $actualAvgMinutes) : null;
        $hoursSavedMonth = $savedMinutesPerSession !== null ? round($savedMinutesPerSession * $totalSessions / 60, 1) : null;

        // ── Risk index: sessions where avg time per bed < 60s ─────────────────
        $riskyCount = (clone $base)
            ->whereNotNull('duration_seconds')
            ->where('beds_visited', '>', 0)
            ->whereRaw('duration_seconds / beds_visited < 60')
            ->count();

        $riskIndex = $totalSessions > 0 ? round($riskyCount / $totalSessions * 100, 1) : 0;

        // ── Consistency index: coefficient of variation per sector ────────────
        $consistencyBySector = $this->buildConsistencyBySector($since, $sectorFilter);

        // ── Per-sector breakdown ──────────────────────────────────────────────
        $bySector = (clone $base)
            ->whereNotNull('duration_seconds')
            ->where('beds_visited', '>', 0)
            ->groupBy('sector_name')
            ->selectRaw('
                sector_name,
                COUNT(*) as sessions,
                AVG(duration_seconds) as avg_duration_sec,
                AVG(duration_seconds / beds_visited) as avg_sec_per_bed,
                AVG(beds_visited / (duration_seconds / 60.0)) as avg_beds_per_min,
                AVG(beds_visited) as avg_beds,
                SUM(CASE WHEN duration_seconds / beds_visited < 60 THEN 1 ELSE 0 END) as risky_count
            ')
            ->orderByDesc('sessions')
            ->get()
            ->map(fn ($r) => [
                'sector_name' => $r->sector_name ?? 'Setor desconhecido',
                'sessions' => (int) $r->sessions,
                'avg_duration_min' => $r->avg_duration_sec ? round($r->avg_duration_sec / 60, 1) : null,
                'avg_sec_per_bed' => $r->avg_sec_per_bed ? round($r->avg_sec_per_bed) : null,
                'avg_beds_per_min' => $r->avg_beds_per_min ? round($r->avg_beds_per_min, 2) : null,
                'avg_beds' => $r->avg_beds ? round($r->avg_beds, 1) : null,
                'risk_pct' => $r->sessions > 0 ? round($r->risky_count / $r->sessions * 100, 1) : 0,
            ]);

        // ── Per-shift breakdown ───────────────────────────────────────────────
        $byShift = (clone $base)
            ->whereNotNull('duration_seconds')
            ->groupBy('shift')
            ->selectRaw('
                shift,
                COUNT(*) as sessions,
                AVG(duration_seconds) as avg_duration_sec,
                AVG(beds_visited) as avg_beds
            ')
            ->get()
            ->map(fn ($r) => [
                'shift' => match ($r->shift) {
                    'morning' => 'Manhã',
                    'afternoon' => 'Tarde',
                    'night' => 'Noite',
                    default => $r->shift,
                },
                'shift_key' => $r->shift,
                'sessions' => (int) $r->sessions,
                'avg_duration_min' => $r->avg_duration_sec ? round($r->avg_duration_sec / 60, 1) : null,
                'avg_beds' => $r->avg_beds ? round($r->avg_beds, 1) : null,
            ]);

        // ── Weekly evolution (last 8 weeks) ───────────────────────────────────
        $weeklyEvolution = ShiftHandover::where('status', ShiftHandover::STATUS_COMPLETED)
            ->whereNotNull('duration_seconds')
            ->where('started_at', '>=', now()->subWeeks(8))
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter))
            ->selectRaw('
                YEARWEEK(started_at, 1) as week_key,
                MIN(DATE(started_at)) as week_start,
                COUNT(*) as sessions,
                AVG(duration_seconds) as avg_duration_sec,
                AVG(beds_visited) as avg_beds
            ')
            ->groupByRaw('YEARWEEK(started_at, 1)')
            ->orderBy('week_key')
            ->get()
            ->map(fn ($r) => [
                'week_label' => Carbon::parse($r->week_start)->locale('pt_BR')->isoFormat('D MMM'),
                'sessions' => (int) $r->sessions,
                'avg_duration_min' => $r->avg_duration_sec ? round($r->avg_duration_sec / 60, 1) : null,
                'avg_beds' => $r->avg_beds ? round($r->avg_beds, 1) : null,
            ]);

        // ── Per-nurse detail (last sessions) ─────────────────────────────────
        $recentSessions = ShiftHandover::with('user:id,name')
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->whereNotNull('duration_seconds')
            ->where('started_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter))
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn ($h) => [
                'user_name' => $h->user?->name ?? '—',
                'sector_name' => $h->sector_name ?? '—',
                'shift' => match ($h->shift) {
                    'morning' => 'Manhã',
                    'afternoon' => 'Tarde',
                    'night' => 'Noite',
                    default => $h->shift,
                },
                'shift_key' => $h->shift,
                'beds_visited' => $h->beds_visited,
                'beds_total' => $h->beds_total,
                'beds_expected' => $h->beds_expected,
                'completion_pct' => $h->completion_percentage,
                'forced_finish' => $h->forced_finish,
                'duration_min' => $h->duration_seconds ? round($h->duration_seconds / 60, 1) : null,
                'sec_per_bed' => ($h->duration_seconds && $h->beds_visited > 0)
                    ? round($h->duration_seconds / $h->beds_visited)
                    : null,
                'is_risky' => ($h->duration_seconds && $h->beds_visited > 0)
                    && ($h->duration_seconds / $h->beds_visited) < 60,
                'started_at' => $h->started_at?->format('d/m H:i'),
                'bed_codes' => $h->bed_codes ?? [],
            ]);

        // ── Available sectors (for filter) ────────────────────────────────────
        $sectors = ShiftHandover::whereNotNull('sector_name')
            ->distinct()
            ->orderBy('sector_name')
            ->pluck('sector_name');

        return view('handover.metrics', compact(
            'totalSessions',
            'avgDurationSec',
            'avgBedsPerSession',
            'avgSecPerBed',
            'avgBedsPerMinute',
            'hoursSavedMonth',
            'riskIndex',
            'riskyCount',
            'abandonedCount',
            'canceledCount',
            'avgCompletionPct',
            'consistencyBySector',
            'bySector',
            'byShift',
            'weeklyEvolution',
            'recentSessions',
            'sectors',
            'period',
            'sectorFilter',
        ));
    }

    /** @return array<string, array{avg_min: float, std_min: float, cv: float, label: string}> */
    private function buildConsistencyBySector(?string $since, ?string $sectorFilter): array
    {
        $rows = ShiftHandover::where('status', ShiftHandover::STATUS_COMPLETED)
            ->whereNotNull('duration_seconds')
            ->where('started_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter))
            ->selectRaw('sector_name, duration_seconds')
            ->get();

        return $rows
            ->groupBy('sector_name')
            ->filter(fn ($group) => $group->count() >= 3)
            ->map(function ($group, $sector) {
                $durations = $group->pluck('duration_seconds')->map(fn ($s) => $s / 60);
                $avg = $durations->average();
                $variance = $durations->reduce(fn ($carry, $v) => $carry + ($v - $avg) ** 2, 0) / $durations->count();
                $std = sqrt($variance);
                $cv = $avg > 0 ? round($std / $avg, 3) : null;

                $label = match (true) {
                    $cv === null => 'Dados insuficientes',
                    $cv <= 0.15 => 'Estável',
                    $cv <= 0.35 => 'Moderado',
                    default => 'Instável',
                };

                return [
                    'sector' => $sector ?? 'Desconhecido',
                    'avg_min' => round($avg, 1),
                    'std_min' => round($std, 1),
                    'cv' => $cv,
                    'label' => $label,
                    'sessions' => $durations->count(),
                ];
            })
            ->values()
            ->all();
    }
}
