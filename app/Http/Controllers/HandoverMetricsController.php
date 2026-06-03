<?php

namespace App\Http\Controllers;

use App\Models\NurseHandoverBed;
use App\Services\ShiftService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HandoverMetricsController extends Controller
{
    // Nominal shift start in minutes from midnight
    private const SHIFT_STARTS = ['M' => 420, 'T' => 780, 'N' => 1140];

    private const SHIFT_LABELS = ['M' => 'Manhã', 'T' => 'Tarde', 'N' => 'Noite'];

    private const VERBOSITY_THRESHOLDS = ['lacônico' => 60, 'normal' => 200];

    public function index(Request $request): View
    {
        $period = (int) $request->query('period', 30);
        $sectorFilter = $request->query('sector');
        $since = now()->subDays($period)->startOfDay();

        $logStart = DB::table('handover_activity_log')->min('occurred_at');
        $logCutoff = $logStart ? Carbon::parse($logStart)->startOfDay() : now();

        // ── Source 1: activity_log sessions ──────────────────────────────────
        $logSessions = DB::table('handover_activity_log as hal')
            ->join('users as u', 'u.id', '=', 'hal.user_id')
            ->select([
                'hal.user_id',
                'u.name as user_name',
                DB::raw('MAX(NULLIF(hal.sector_id, 0)) as sector_id'),
                DB::raw('MAX(hal.sector_name) as sector_name'),
                'hal.shift',
                'hal.shift_date',
                DB::raw('COUNT(DISTINCT hal.nr_atendimento) as beds_visited'),
                DB::raw('MIN(hal.occurred_at) as started_at'),
                DB::raw('TIMESTAMPDIFF(SECOND, MIN(hal.occurred_at), MAX(hal.occurred_at)) as duration_seconds'),
                DB::raw('SUM(CASE WHEN hal.event = "chat_post" THEN 1 ELSE 0 END) as messages_written'),
                DB::raw('MAX(hal.only_assigned_beds) as only_assigned_beds'),
                DB::raw('"log" as source'),
            ])
            ->where('hal.occurred_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('hal.sector_id', $sectorFilter))
            ->groupBy('hal.user_id', 'hal.shift', 'hal.shift_date', 'u.name')
            ->having(DB::raw('SUM(CASE WHEN hal.event = "chat_post" THEN 1 ELSE 0 END)'), '>=', 1)
            ->get();

        // ── Source 2: chat_messages sessions (historical) ────────────────────
        $shiftExpr = DB::raw(ShiftService::shiftSqlExpr('cm.created_at').' as shift');
        $shiftDateExpr = DB::raw(ShiftService::shiftDateSqlExpr('cm.created_at').' as shift_date');

        $chatSessions = DB::table('chat_messages as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->select([
                'cm.user_id',
                'u.name as user_name',
                DB::raw('MAX(NULLIF(cm.sector_id, 0)) as sector_id'),
                DB::raw('MAX(cm.sector_name) as sector_name'),
                $shiftExpr,
                $shiftDateExpr,
                DB::raw('COUNT(DISTINCT cm.nr_atendimento) as beds_visited'),
                DB::raw('MIN(cm.created_at) as started_at'),
                DB::raw('TIMESTAMPDIFF(SECOND, MIN(cm.created_at), MAX(cm.created_at)) as duration_seconds'),
                DB::raw('COUNT(*) as messages_written'),
                DB::raw('0 as only_assigned_beds'),
                DB::raw('"chat" as source'),
            ])
            ->where('cm.created_at', '>=', $since)
            ->where('cm.created_at', '<', $logCutoff)
            ->when($sectorFilter, fn ($q) => $q->where('cm.sector_id', $sectorFilter))
            ->groupBy('cm.user_id', 'shift', 'shift_date', 'u.name')
            ->having('beds_visited', '>=', 1)
            ->having(DB::raw('COUNT(*)'), '>=', 3)
            ->get();

        $sessions = $logSessions->merge($chatSessions)
            ->sortByDesc('started_at')
            ->values()
            ->map(function ($s) {
                $startedAt = Carbon::parse($s->started_at);
                $shiftStartMin = self::SHIFT_STARTS[$s->shift] ?? null;
                $actualMin = $startedAt->hour * 60 + $startedAt->minute;
                $minutesFromShiftStart = $shiftStartMin !== null ? $actualMin - $shiftStartMin : null;

                if ($s->shift === 'N' && $minutesFromShiftStart !== null && $minutesFromShiftStart < -600) {
                    $minutesFromShiftStart += 1440;
                }

                return [
                    'user_id' => (int) $s->user_id,
                    'user_name' => $s->user_name,
                    'sector_id' => $s->sector_id,
                    'sector_name' => $s->sector_name ?? '—',
                    'shift' => self::SHIFT_LABELS[$s->shift] ?? $s->shift,
                    'shift_key' => $s->shift,
                    'shift_date' => $s->shift_date,
                    'beds_visited' => (int) $s->beds_visited,
                    'started_at' => $startedAt->format('d/m/Y H:i'),
                    'started_at_raw' => $s->started_at,
                    'duration_min' => ($s->duration_seconds > 0 && $s->duration_seconds <= 10800) ? round($s->duration_seconds / 60, 1) : null,
                    'messages_written' => (int) $s->messages_written,
                    'only_assigned_beds' => (bool) $s->only_assigned_beds,
                    'minutes_from_shift_start' => $minutesFromShiftStart,
                ];
            });

        if ($sessions->isEmpty()) {
            return view('handover.report', [
                'empty' => true,
                'sectors' => collect(),
                'period' => $period,
                'sectorFilter' => $sectorFilter,
            ]);
        }

        $weeksInPeriod = max($period / 7, 1);

        // ── Message length stats per user ────────────────────────────────────
        $msgLengths = DB::table('chat_messages')
            ->where('created_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
            ->selectRaw('user_id, ROUND(AVG(CHAR_LENGTH(content)), 0) as avg_chars')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $globalAvgChars = (int) (DB::table('chat_messages')
            ->where('created_at', '>=', $since)
            ->selectRaw('ROUND(AVG(CHAR_LENGTH(content)), 0) as avg_chars')
            ->value('avg_chars') ?? 0);

        // ── Sector scorecards ─────────────────────────────────────────────────
        $sectorStats = $sessions
            ->groupBy('sector_name')
            ->map(function ($g, $sectorName) {
                $nurses = $g->pluck('user_name')->filter()->unique()->sort()->values();
                $total = $g->count();

                return [
                    'sector_name' => $sectorName,
                    'sector_id' => $g->first()['sector_id'],
                    'sessions' => $total,
                    'nurses_count' => $nurses->count(),
                    'nurses' => $nurses->all(),
                    'avg_beds' => round($g->avg('beds_visited'), 1),
                    'shift_M' => $g->where('shift_key', 'M')->count(),
                    'shift_T' => $g->where('shift_key', 'T')->count(),
                    'shift_N' => $g->where('shift_key', 'N')->count(),
                    'pct_M' => $total > 0 ? round($g->where('shift_key', 'M')->count() / $total * 100) : 0,
                    'pct_T' => $total > 0 ? round($g->where('shift_key', 'T')->count() / $total * 100) : 0,
                    'pct_N' => $total > 0 ? round($g->where('shift_key', 'N')->count() / $total * 100) : 0,
                ];
            })
            ->sortByDesc('sessions')
            ->values();

        // ── Nurse scorecards ─────────────────────────────────────────────────
        $nurseStats = $sessions
            ->groupBy('user_name')
            ->map(function ($g, $name) use ($weeksInPeriod, $msgLengths) {
                $userId = $g->first()['user_id'];
                $msgData = $msgLengths->get($userId);
                $avgChars = $msgData ? (int) $msgData->avg_chars : null;

                $shiftCounts = ['M' => $g->where('shift_key', 'M')->count(), 'T' => $g->where('shift_key', 'T')->count(), 'N' => $g->where('shift_key', 'N')->count()];
                $dominantShift = array_search(max($shiftCounts), $shiftCounts);

                return [
                    'user_id' => $userId,
                    'name' => $name,
                    'sessions' => $g->count(),
                    'sessions_per_week' => round($g->count() / $weeksInPeriod, 1),
                    'avg_beds' => round($g->avg('beds_visited'), 1),
                    'avg_messages' => round($g->avg('messages_written'), 1),
                    'sector_list' => $g->pluck('sector_name')->filter()->unique()->values()->all(),
                    'sectors' => $g->pluck('sector_name')->filter()->unique()->implode(', '),
                    'avg_chars_per_msg' => $avgChars,
                    'verbosity_label' => $avgChars !== null ? self::verbosityLabel($avgChars) : null,
                    'dominant_shift' => self::SHIFT_LABELS[$dominantShift] ?? '—',
                    'shift_pct' => [
                        'M' => $g->count() > 0 ? round($shiftCounts['M'] / $g->count() * 100) : 0,
                        'T' => $g->count() > 0 ? round($shiftCounts['T'] / $g->count() * 100) : 0,
                        'N' => $g->count() > 0 ? round($shiftCounts['N'] / $g->count() * 100) : 0,
                    ],
                ];
            })
            ->sortByDesc('sessions')
            ->values();

        // ── Heatmap: mensagens por hora do dia ───────────────────────────────
        $heatmapRaw = DB::table('chat_messages')
            ->where('created_at', '>=', $since)
            ->when($sectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $heatmapMax = max(1, $heatmapRaw->max('cnt'));
        $heatmap = [
            ['key' => 'M', 'label' => 'Manhã',  'color' => '#D97706', 'hours' => range(7, 12)],
            ['key' => 'T', 'label' => 'Tarde',  'color' => '#EA580C', 'hours' => range(13, 18)],
            ['key' => 'N', 'label' => 'Noite',  'color' => '#4F46E5', 'hours' => array_merge(range(19, 23), range(0, 6))],
        ];
        $heatmap = array_map(function ($row) use ($heatmapRaw, $heatmapMax) {
            $row['cells'] = array_map(fn ($h) => [
                'hour' => str_pad($h, 2, '0', STR_PAD_LEFT),
                'count' => (int) ($heatmapRaw->get($h)?->cnt ?? 0),
                'pct' => (int) round(($heatmapRaw->get($h)?->cnt ?? 0) / $heatmapMax * 100),
            ], $row['hours']);

            return $row;
        }, $heatmap);

        // ── Sector list for filter dropdown ──────────────────────────────────
        $sectors = DB::table('chat_messages')
            ->whereNotNull('sector_name')
            ->where('created_at', '>=', $since)
            ->distinct()
            ->orderBy('sector_name')
            ->pluck('sector_name', 'sector_id');

        return view('handover.report', compact(
            'nurseStats',
            'sectorStats',
            'heatmap',
            'heatmapMax',
            'sectors',
            'period',
            'sectorFilter',
            'globalAvgChars',
        ) + ['empty' => false]);
    }

    public function nurseDetail(Request $request, int $userId): JsonResponse
    {
        $period = (int) $request->query('period', 30);
        $since = now()->subDays($period)->startOfDay();

        $user = DB::table('users')->find($userId);
        if (! $user) {
            return response()->json(['error' => 'Não encontrado'], 404);
        }

        $logStart = DB::table('handover_activity_log')->min('occurred_at');
        $logCutoff = $logStart ? Carbon::parse($logStart)->startOfDay() : now();

        // ── Activity-log sessions ─────────────────────────────────────────────
        $logRows = DB::table('handover_activity_log')
            ->where('user_id', $userId)
            ->where('occurred_at', '>=', $since)
            ->select([
                'shift',
                'shift_date',
                DB::raw('MAX(sector_name) as sector_name'),
                DB::raw('COUNT(DISTINCT nr_atendimento) as beds_visited'),
                DB::raw('MIN(occurred_at) as started_at'),
                DB::raw('TIMESTAMPDIFF(SECOND, MIN(occurred_at), MAX(occurred_at)) as duration_seconds'),
                DB::raw('SUM(CASE WHEN event = "chat_post" THEN 1 ELSE 0 END) as messages_written'),
            ])
            ->groupBy('shift', 'shift_date')
            ->orderByDesc('shift_date')
            ->get();

        // ── Historical chat sessions ──────────────────────────────────────────
        $chatRows = DB::table('chat_messages')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->where('created_at', '<', $logCutoff)
            ->selectRaw(ShiftService::shiftSqlExpr('created_at').' as shift,
                '.ShiftService::shiftDateSqlExpr('created_at').' as shift_date,
                MAX(sector_name) as sector_name,
                COUNT(DISTINCT nr_atendimento) as beds_visited,
                MIN(created_at) as started_at,
                TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as duration_seconds,
                COUNT(*) as messages_written
            ')
            ->groupByRaw('shift, shift_date')
            ->orderByDesc('shift_date')
            ->get();

        $allRows = $logRows->merge($chatRows)->sortByDesc('shift_date');

        // ── Real message counts per session from chat_messages ────────────────
        $realMsgCounts = DB::table('chat_messages')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->selectRaw(ShiftService::shiftSqlExpr('created_at').' as shift_key,
                '.ShiftService::shiftDateSqlExpr('created_at').' as shift_date_key,
                COUNT(*) as msg_count
            ')
            ->groupByRaw('shift_key, shift_date_key')
            ->get()
            ->keyBy(fn ($r) => $r->shift_key.'_'.$r->shift_date_key);

        // ── Beds assigned to this nurse ───────────────────────────────────────
        $totalBedsAssigned = NurseHandoverBed::where('user_id', $userId)->count();

        // ── Per-session normalization ─────────────────────────────────────────
        $sessions = $allRows->map(function ($s) use ($realMsgCounts) {
            $startedAt = Carbon::parse($s->started_at);
            $shiftStartMin = self::SHIFT_STARTS[$s->shift] ?? null;
            $actualMin = $startedAt->hour * 60 + $startedAt->minute;
            $punctMin = $shiftStartMin !== null ? $actualMin - $shiftStartMin : null;

            if ($s->shift === 'N' && $punctMin !== null && $punctMin < -600) {
                $punctMin += 1440;
            }

            $durMin = ($s->duration_seconds > 0 && $s->duration_seconds <= 10800) ? round($s->duration_seconds / 60, 1) : null;

            return [
                'shift' => self::SHIFT_LABELS[$s->shift] ?? $s->shift,
                'shift_key' => $s->shift,
                'shift_date' => $s->shift_date,
                'sector_name' => $s->sector_name ?? '—',
                'beds_visited' => (int) $s->beds_visited,
                'duration_min' => $durMin,
                'messages_written' => (int) ($realMsgCounts->get($s->shift.'_'.$s->shift_date)?->msg_count ?? $s->messages_written),
                'started_at' => $startedAt->format('d/m/Y H:i'),
                'punctuality_min' => $punctMin,
            ];
        });

        // ── Message content analysis: chat_messages + chat_messages_archive ────
        $msgStats = DB::table('chat_messages')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total_messages,
                ROUND(AVG(CHAR_LENGTH(content)), 0) as avg_chars,
                ROUND(AVG(CHAR_LENGTH(content) - CHAR_LENGTH(REPLACE(content, " ", "")) + 1), 1) as avg_words,
                MAX(CHAR_LENGTH(content)) as max_chars,
                MIN(CHAR_LENGTH(content)) as min_chars,
                SUM(CASE WHEN CHAR_LENGTH(content) < 60 THEN 1 ELSE 0 END) as count_laconic,
                SUM(CASE WHEN CHAR_LENGTH(content) BETWEEN 60 AND 200 THEN 1 ELSE 0 END) as count_normal,
                SUM(CASE WHEN CHAR_LENGTH(content) > 200 THEN 1 ELSE 0 END) as count_verbose
            ')
            ->first();

        // Supplement with archived messages (stored as base64(gzcompress(JSON)) per patient)
        $archiveMsgStats = $this->getArchiveMessageStats($user->name, $since);

        // Merge aggregate stats: recompute weighted averages
        $liveTotal = (int) ($msgStats->total_messages ?? 0);
        $liveChars = (int) ($msgStats->avg_chars ?? 0) * $liveTotal;
        $liveWords = (float) ($msgStats->avg_words ?? 0) * $liveTotal;
        $archTotal = $archiveMsgStats['total'];
        $archChars = $archiveMsgStats['total_chars'];
        $archWords = $archiveMsgStats['total_words'];

        $combinedTotal = $liveTotal + $archTotal;
        $combinedAvgChars = $combinedTotal > 0 ? (int) round(($liveChars + $archChars) / $combinedTotal) : 0;
        $combinedAvgWords = $combinedTotal > 0 ? round(($liveWords + $archWords) / $combinedTotal, 1) : 0.0;

        $globalMsgStats = DB::table('chat_messages')
            ->where('created_at', '>=', $since)
            ->selectRaw('ROUND(AVG(CHAR_LENGTH(content)), 0) as avg_chars, ROUND(AVG(CHAR_LENGTH(content) - CHAR_LENGTH(REPLACE(content, " ", "")) + 1), 1) as avg_words')
            ->first();

        // ── Computed summary ──────────────────────────────────────────────────
        $withDuration = $sessions->filter(fn ($s) => $s['duration_min'] !== null && $s['duration_min'] > 0);
        $punctValues = $sessions->pluck('punctuality_min')->filter(fn ($v) => $v !== null);
        $avgChars = $combinedAvgChars;

        $durationBuckets = [
            '< 10min' => 0,
            '10–20min' => 0,
            '20–40min' => 0,
            '40–60min' => 0,
            '> 60min' => 0,
        ];
        foreach ($withDuration as $s) {
            $d = $s['duration_min'];
            if ($d < 10) {
                $durationBuckets['< 10min']++;
            } elseif ($d < 20) {
                $durationBuckets['10–20min']++;
            } elseif ($d < 40) {
                $durationBuckets['20–40min']++;
            } elseif ($d < 60) {
                $durationBuckets['40–60min']++;
            } else {
                $durationBuckets['> 60min']++;
            }
        }

        $shiftDist = $sessions->groupBy('shift_key')
            ->map(fn ($g) => $g->count())
            ->all();

        $sectorDist = $sessions->groupBy('sector_name')
            ->map(fn ($g) => $g->count())
            ->sortByDesc(fn ($c) => $c)
            ->all();

        $avgPunct = $punctValues->isNotEmpty() ? round($punctValues->avg()) : null;

        $durationArr = $withDuration->pluck('duration_min')->sort()->values()->all();

        return response()->json([
            'name' => $user->name,
            'period_days' => $period,
            'summary' => [
                'total_sessions' => $sessions->count(),
                'sessions_per_week' => round($sessions->count() / max($period / 7, 1), 1),
                'avg_beds' => $sessions->isNotEmpty() ? round($sessions->avg('beds_visited'), 1) : null,
                'avg_duration_min' => $withDuration->isNotEmpty() ? round($withDuration->avg('duration_min'), 1) : null,
                'median_duration_min' => ! empty($durationArr) ? $this->median($durationArr) : null,
                'total_messages' => $combinedTotal,
                'avg_messages_per_session' => $sessions->isNotEmpty() ? round($sessions->avg('messages_written'), 1) : null,
                'avg_chars_per_message' => $avgChars,
                'avg_words_per_message' => $combinedAvgWords,
                'max_chars' => max((int) ($msgStats->max_chars ?? 0), $archiveMsgStats['max_chars']),
                'count_laconic' => (int) ($msgStats->count_laconic ?? 0) + $archiveMsgStats['count_laconic'],
                'count_normal' => (int) ($msgStats->count_normal ?? 0) + $archiveMsgStats['count_normal'],
                'count_verbose' => (int) ($msgStats->count_verbose ?? 0) + $archiveMsgStats['count_verbose'],
                'verbosity_label' => self::verbosityLabel($avgChars),
                'avg_punctuality_min' => $avgPunct,
                'beds_assigned' => $totalBedsAssigned ?: null,
                'avg_coverage_pct' => ($totalBedsAssigned > 0 && $sessions->isNotEmpty())
                    ? min(100, round($sessions->avg('beds_visited') / $totalBedsAssigned * 100))
                    : null,
                'global_avg_chars' => (int) ($globalMsgStats->avg_chars ?? 0),
                'global_avg_words' => (float) ($globalMsgStats->avg_words ?? 0),
            ],
            'duration_buckets' => $durationBuckets,
            'shift_distribution' => $shiftDist,
            'sector_distribution' => $sectorDist,
            'recent_sessions' => $sessions->values()->all(),
        ]);
    }

    private function getArchiveMessageStats(string $userName, Carbon $since): array
    {
        $empty = ['total' => 0, 'total_chars' => 0, 'total_words' => 0, 'max_chars' => 0, 'count_laconic' => 0, 'count_normal' => 0, 'count_verbose' => 0];

        $rows = DB::table('chat_messages_archive')
            ->where(function ($q) use ($since) {
                $q->where('last_message_at', '>=', $since)->orWhereNull('last_message_at');
            })
            ->get(['payload']);

        if ($rows->isEmpty()) {
            return $empty;
        }

        $stats = $empty;
        foreach ($rows as $row) {
            try {
                $messages = json_decode(gzuncompress(base64_decode($row->payload)), true) ?? [];
                foreach ($messages as $msg) {
                    if (($msg['u'] ?? '') !== $userName) {
                        continue;
                    }
                    $ts = $msg['ts'] ?? null;
                    if ($ts && Carbon::parse($ts)->lt($since)) {
                        continue;
                    }
                    $content = $msg['m'] ?? '';
                    $chars = mb_strlen($content);
                    $words = $content !== '' ? substr_count(trim($content), ' ') + 1 : 0;
                    $stats['total']++;
                    $stats['total_chars'] += $chars;
                    $stats['total_words'] += $words;
                    $stats['max_chars'] = max($stats['max_chars'], $chars);
                    if ($chars < 60) {
                        $stats['count_laconic']++;
                    } elseif ($chars <= 200) {
                        $stats['count_normal']++;
                    } else {
                        $stats['count_verbose']++;
                    }
                }
            } catch (\Throwable) {
                // skip corrupt rows
            }
        }

        return $stats;
    }

    private static function verbosityLabel(int $avgChars): string
    {
        if ($avgChars < self::VERBOSITY_THRESHOLDS['lacônico']) {
            return 'lacônico';
        }

        if ($avgChars < self::VERBOSITY_THRESHOLDS['normal']) {
            return 'normal';
        }

        return 'detalhado';
    }

    private function median(array $sorted): float
    {
        $count = count($sorted);
        $mid = (int) floor($count / 2);

        return $count % 2 === 0
            ? round(($sorted[$mid - 1] + $sorted[$mid]) / 2, 1)
            : round((float) $sorted[$mid], 1);
    }
}
