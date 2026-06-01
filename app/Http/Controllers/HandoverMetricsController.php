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
        $period = (int) $request->query('period', 30);
        $sectorFilter = $request->query('sector');
        $since = now()->subDays($period)->startOfDay();

        $base = ShiftHandover::where('started_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter));

        // ── Top-level utilization numbers ─────────────────────────────────────
        $totalCompleted = (clone $base)->where('status', ShiftHandover::STATUS_COMPLETED)->count();
        $totalAbandoned = (clone $base)->where('status', ShiftHandover::STATUS_ABANDONED)->count();
        $totalCanceled = (clone $base)->where('status', ShiftHandover::STATUS_CANCELED)->count();

        // Active nurses = distinct users with ≥1 completed session
        $activeNurses = (clone $base)
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->distinct('user_id')
            ->count('user_id');

        // Sectors with ≥1 completed session
        $activeSectors = (clone $base)
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->whereNotNull('sector_name')
            ->distinct('sector_name')
            ->count('sector_name');

        // Completion rate (completed / all started)
        $totalStarted = $totalCompleted + $totalAbandoned + $totalCanceled;
        $completionRate = $totalStarted > 0
            ? round($totalCompleted / $totalStarted * 100)
            : null;

        // Average beds visited per completed session
        $avgBeds = (clone $base)
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->whereNotNull('beds_visited')
            ->avg('beds_visited');

        // Average duration of completed sessions
        $avgDurationSec = (clone $base)
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        // ── By sector ─────────────────────────────────────────────────────────
        $bySector = (clone $base)
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->whereNotNull('sector_name')
            ->selectRaw('
                sector_name,
                COUNT(*)                              AS sessions,
                COUNT(DISTINCT user_id)               AS nurses,
                ROUND(AVG(beds_visited), 1)           AS avg_beds,
                ROUND(AVG(duration_seconds) / 60, 1) AS avg_min,
                MAX(started_at)                       AS last_session
            ')
            ->groupBy('sector_name')
            ->orderByDesc('sessions')
            ->get()
            ->map(fn ($r) => [
                'sector_name' => $r->sector_name,
                'sessions' => (int) $r->sessions,
                'nurses' => (int) $r->nurses,
                'avg_beds' => $r->avg_beds,
                'avg_min' => $r->avg_min,
                'last_session' => $r->last_session
                    ? Carbon::parse($r->last_session)->format('d/m/Y H:i')
                    : null,
            ]);

        // ── By shift ──────────────────────────────────────────────────────────
        $shiftLabels = ['morning' => 'Manhã', 'afternoon' => 'Tarde', 'night' => 'Noite'];

        $byShift = (clone $base)
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->selectRaw('shift, COUNT(*) AS sessions, COUNT(DISTINCT user_id) AS nurses')
            ->groupBy('shift')
            ->get()
            ->map(fn ($r) => [
                'shift' => $shiftLabels[$r->shift] ?? $r->shift,
                'sessions' => (int) $r->sessions,
                'nurses' => (int) $r->nurses,
            ])
            ->keyBy('shift');

        // Ensure all three shifts present
        foreach (['Manhã', 'Tarde', 'Noite'] as $sh) {
            $byShift->getOrPut($sh, ['shift' => $sh, 'sessions' => 0, 'nurses' => 0]);
        }

        // ── Daily series (last 14 days or up to $period) ──────────────────────
        $seriesDays = min($period, 14);
        $dailySeries = (clone $base)
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->where('started_at', '>=', now()->subDays($seriesDays)->startOfDay())
            ->selectRaw('DATE(started_at) AS day, COUNT(*) AS sessions')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('sessions', 'day');

        $dailyLabels = [];
        $dailyCounts = [];
        for ($i = $seriesDays - 1; $i >= 0; $i--) {
            $dt = now()->subDays($i)->toDateString();
            $dailyLabels[] = now()->subDays($i)->format('d/m');
            $dailyCounts[] = (int) ($dailySeries[$dt] ?? 0);
        }

        // ── Recent sessions (last 50, touch-friendly list) ────────────────────
        $recentSessions = ShiftHandover::with('user:id,name')
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->where('started_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_name', $sectorFilter))
            ->orderByDesc('started_at')
            ->limit(50)
            ->get()
            ->map(fn ($h) => [
                'user_name' => $h->user?->name ?? '—',
                'sector_name' => $h->sector_name ?? '—',
                'shift' => $shiftLabels[$h->shift] ?? $h->shift,
                'shift_key' => $h->shift,
                'beds_visited' => $h->beds_visited ?? 0,
                'beds_expected' => $h->beds_expected ?? $h->beds_total ?? 0,
                'completion_pct' => $h->completion_percentage !== null
                    ? (int) round($h->completion_percentage)
                    : null,
                'duration_min' => $h->duration_seconds
                    ? round($h->duration_seconds / 60, 1)
                    : null,
                'forced_finish' => (bool) $h->forced_finish,
                'started_at' => $h->started_at?->format('d/m/Y'),
                'started_time' => $h->started_at?->format('H:i'),
            ]);

        // ── Sector list for filter ─────────────────────────────────────────────
        $sectors = ShiftHandover::whereNotNull('sector_name')
            ->where('status', ShiftHandover::STATUS_COMPLETED)
            ->distinct()
            ->orderBy('sector_name')
            ->pluck('sector_name');

        return view('handover.report', compact(
            'totalCompleted',
            'totalAbandoned',
            'totalCanceled',
            'totalStarted',
            'activeNurses',
            'activeSectors',
            'completionRate',
            'avgBeds',
            'avgDurationSec',
            'bySector',
            'byShift',
            'dailyLabels',
            'dailyCounts',
            'recentSessions',
            'sectors',
            'period',
            'sectorFilter',
        ));
    }
}
