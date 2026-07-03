<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
use App\Models\HandoverActivityLog;
use App\Services\ChatAnalyticsService;
use App\Services\HandoverSessionService;
use App\Services\ShiftService;
use App\Support\ChatArchivePayload;
use App\Support\ChatArchiveShiftResolver;
use App\Support\ChatArchiveUserResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetricsController extends Controller
{
    private const STATIC_CACHE_KEY = 'metrics_static_v1';

    private const STATIC_CACHE_TTL = 3600;

    // ── Main index ────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $period = $request->input('period') !== null ? (int) $request->input('period') : null;
        $sectorFilter = $request->input('sector') ? (int) $request->input('sector') : null;
        $since = $period !== null ? now()->subDays($period)->startOfDay() : null;

        $staticCached = Cache::get(self::STATIC_CACHE_KEY);

        if (! $staticCached) {
            $staticCached = $this->buildStaticData();
            Cache::put(self::STATIC_CACHE_KEY, $staticCached, self::STATIC_CACHE_TTL);
        }

        $panoramaData = $this->buildPanoramaData($since, $sectorFilter, $period);

        return view('metrics.index', array_merge($staticCached, $panoramaData, [
            'period' => $period,
            'sectorFilter' => $sectorFilter,
            'accessLog' => $this->buildAccessLog(),
        ]));
    }

    public function clearCache()
    {
        DB::table('cache')->where('key', 'like', '%metrics_%')->orWhere('key', 'like', '%panorama_%')->delete();

        return redirect()->route('admin.dashboard')->with('cache_cleared', true);
    }

    // ── DataTables client-side ────────────────────────────────────────────────

    public function clientData()
    {
        $archiveRows = DB::table('chat_messages_archive')
            ->select('nr_atendimento', 'message_count', 'last_message_at', 'sector_name')
            ->get();

        $activeRows = DB::table('chat_messages')
            ->selectRaw("nr_atendimento, COUNT(*) AS message_count, MAX(created_at) AS last_message_at,
                         GROUP_CONCAT(DISTINCT sector_name ORDER BY sector_name SEPARATOR '||') AS sector_name")
            ->whereNotIn('nr_atendimento', DB::table('chat_messages_archive')->pluck('nr_atendimento'))
            ->groupBy('nr_atendimento')
            ->get();

        $all = $archiveRows->merge($activeRows);

        $nrs = $all->pluck('nr_atendimento')->map(fn ($v) => (int) $v)->toArray();
        $oracle = [];
        foreach (array_chunk($nrs, 500) as $chunk) {
            $oracle += $this->fetchPatientData($chunk);
        }

        $data = $all->map(function ($row) use ($oracle) {
            $o = $oracle[(int) $row->nr_atendimento] ?? null;
            $ts = $row->last_message_at;

            return [
                'nr_atendimento' => (string) $row->nr_atendimento,
                'patient_name' => $o['name'] ?? '',
                'sector_name' => $this->formatSectors($row->sector_name ?? $o['sector_name'] ?? null),
                'dt_entrada' => $o['dt_entrada'] ?? '',
                'dt_alta' => $o['dt_alta'] ?? '',
                'still_admitted' => $o ? $o['still_admitted'] : null,
                'admission_days' => $o['admission_days'] ?? null,
                'message_count' => (int) $row->message_count,
                'last_message_at' => $ts ? Carbon::parse($ts)->format('d/m/Y') : '',
                'last_message_raw' => $ts ? Carbon::parse($ts)->format('Y-m-d') : '',
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    // ── DataTables server-side AJAX ──────────────────────────────────────────

    public function datatables(Request $request)
    {
        $draw = (int) $request->input('draw', 1);
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 25)));

        $archiveSql = 'SELECT nr_atendimento, message_count, last_message_at, sector_name FROM chat_messages_archive';
        $activeSql = "SELECT nr_atendimento, COUNT(*) AS message_count, MAX(created_at) AS last_message_at,
                             GROUP_CONCAT(DISTINCT sector_name ORDER BY sector_name SEPARATOR '||') AS sector_name
                       FROM chat_messages
                       WHERE nr_atendimento NOT IN (SELECT nr_atendimento FROM chat_messages_archive)
                       GROUP BY nr_atendimento";

        $unionSql = "({$archiveSql}) UNION ALL ({$activeSql})";
        $query = DB::table(DB::raw("({$unionSql}) AS combined_records"));

        if ($search = trim($request->input('search.value', ''))) {
            $query->where('nr_atendimento', 'like', "%{$search}%");
        }
        if ($from = $request->input('from')) {
            $query->where('last_message_at', '>=', $from.' 00:00:00');
        }
        if ($to = $request->input('to')) {
            $query->where('last_message_at', '<=', $to.' 23:59:59');
        }
        if ($sector = trim($request->input('sector', ''))) {
            $query->where('sector_name', 'like', "%{$sector}%");
        }

        $recordsFiltered = $query->count();
        $recordsTotal = DB::table(DB::raw("({$unionSql}) AS total_count"))->count();

        $sortableMap = [0 => 'nr_atendimento', 6 => 'message_count', 7 => 'last_message_at'];
        $orderColIdx = (int) $request->input('order.0.column', 7);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderByCol = $sortableMap[$orderColIdx] ?? 'last_message_at';

        $query->orderBy($orderByCol, $orderDir);

        $rows = $query->skip($start)->take($length)->get();
        $nrs = $rows->pluck('nr_atendimento')->toArray();
        $oracleData = $this->fetchPatientData($nrs);

        $data = $rows->map(function ($row) use ($oracleData) {
            $o = $oracleData[(int) $row->nr_atendimento] ?? null;

            return [
                'nr_atendimento' => $row->nr_atendimento,
                'patient_name' => $o['name'] ?? null,
                'sector_name' => $this->formatSectors($row->sector_name ?? $o['sector_name'] ?? null),
                'dt_entrada' => $o['dt_entrada'] ?? null,
                'dt_alta' => $o['dt_alta'] ?? null,
                'still_admitted' => $o ? $o['still_admitted'] : null,
                'admission_days' => $o['admission_days'] ?? null,
                'message_count' => $row->message_count,
                'last_message_at' => $row->last_message_at
                    ? Carbon::parse($row->last_message_at)->format('d/m/Y')
                    : null,
                'DT_RowId' => 'row_'.$row->nr_atendimento,
            ];
        })->values()->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    // ── Archive message modal ─────────────────────────────────────────────────

    public function show(Request $request, string $nr)
    {
        HandoverActivityLog::record(HandoverActivityLog::EVENT_ANALYSIS_OPEN, (int) auth()->id(), (int) $nr);

        $archive = DB::table('chat_messages_archive')->where('nr_atendimento', $nr)->first();

        if (! $archive) {
            $activeMessages = DB::table('chat_messages')
                ->leftJoin('users', 'chat_messages.user_id', '=', 'users.id')
                ->where('chat_messages.nr_atendimento', $nr)
                ->orderBy('chat_messages.created_at')
                ->get([
                    'chat_messages.created_at',
                    'chat_messages.user_id',
                    'chat_messages.content',
                    'users.username',
                    'users.name',
                    'users.photo',
                ]);

            if ($activeMessages->isEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json(['messages' => [], 'nr' => $nr, 'patient_name' => null, 'total' => 0]);
                }
                abort(404);
            }

            $messages = ChatArchiveUserResolver::normalizeMessages(
                $activeMessages->map(fn ($m) => [
                    'ts' => strtotime($m->created_at),
                    'date' => date('d/m/Y H:i', strtotime($m->created_at)),
                    'user' => $m->user_id,
                    'turno' => ChatArchiveShiftResolver::label(ChatArchiveShiftResolver::inferShift($m->created_at)),
                    'text' => $m->content ?? '',
                ])->values()->all(),
                $activeMessages->mapWithKeys(function ($message) {
                    $key = (string) ($message->user_id ?? '');

                    return $key !== '' ? [$key => [
                        'id' => $message->user_id ?? null,
                        'username' => $message->username ?? null,
                        'name' => $message->name ?? null,
                        'photo' => $message->photo ?? null,
                    ]] : [];
                })->toArray()
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'messages' => $messages,
                    'nr' => $nr,
                    'patient_name' => $this->resolvePatientName($nr),
                    'total' => count($messages),
                    'users' => $this->resolveUserPhotos(array_column($messages, 'user')),
                ]);
            }

            return view('chat.archive-show', [
                'archive' => null,
                'messages' => collect($messages),
                'timeline' => $this->buildTimeline($messages),
                'nr' => $nr,
                'summary' => [
                    'message_count' => count($messages),
                    'first_date' => $this->formatDate($activeMessages->first()?->created_at),
                    'last_date' => $this->formatDate($activeMessages->last()?->created_at),
                ],
            ]);
        }

        $messages = [];
        $archivePayload = ChatArchivePayload::decode($archive->payload ?? null);
        $archiveUsers = $archivePayload['users'] ?? [];

        if (! empty($archivePayload['messages'])) {
            $raw = $archivePayload['messages'];
            $messages = ChatArchiveUserResolver::normalizeMessages(
                array_map(fn ($m) => [
                    'ts' => $m['ts'] ?? 0,
                    'date' => isset($m['ts']) ? date('d/m/Y H:i', $m['ts']) : '—',
                    'user' => $m['u'] ?? '—',
                    'turno' => ChatArchiveShiftResolver::label($m['t'] ?? null, $m['ts'] ?? null),
                    'text' => $m['m'] ?? '',
                ], $raw),
                $archiveUsers
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'messages' => array_values($messages),
                'nr' => $nr,
                'patient_name' => $this->resolvePatientName($nr),
                'total' => count($messages),
                'users' => ! empty($archiveUsers)
                    ? $this->resolveUserPhotos(array_column($messages, 'user'), $archiveUsers)
                    : $this->resolveUserPhotos(array_column($messages, 'user')),
            ]);
        }

        return view('chat.archive-show', [
            'archive' => $archive,
            'messages' => collect($messages),
            'timeline' => $this->buildTimeline($messages),
            'nr' => $nr,
            'summary' => [
                'message_count' => (int) ($archive->message_count ?? 0),
                'first_date' => $this->formatDate($archive->first_message_at ?? null),
                'last_date' => $this->formatDate($archive->last_message_at ?? null),
            ],
        ]);
    }

    // ── Access audit trail (always fresh) ────────────────────────────────────

    private function buildAccessLog(): Collection
    {
        return DB::table('handover_activity_log as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->whereIn('l.event', array_keys(HandoverActivityLog::EVENT_LABELS))
            ->orderByDesc('l.occurred_at')
            ->limit(300)
            ->get(['u.name as user_name', 'u.role as user_role', 'l.nr_atendimento', 'l.sector_name', 'l.event', 'l.occurred_at']);
    }

    // ── Static data (cached 1h, period/sector-independent) ───────────────────

    private function buildStaticData(): array
    {
        $stats = DB::table('chat_messages_archive')->selectRaw('
            COUNT(*)                                                        AS total,
            SUM(message_count)                                              AS total_msgs,
            SUM(CASE WHEN message_count >= 3 THEN 1 ELSE 0 END)            AS consistent,
            ROUND(AVG(message_count), 1)                                    AS avg_per_attendance,
            MIN(first_message_at)                                           AS oldest,
            MAX(last_message_at)                                            AS newest
        ')->first();

        $activeStats = DB::table('chat_messages')->selectRaw('
            COUNT(*)                       AS total_msgs,
            MIN(created_at)                AS oldest,
            MAX(created_at)                AS newest
        ')->first();

        $activeOnlyAttendances = DB::table('chat_messages')
            ->distinct()
            ->whereNotIn('nr_atendimento', function ($q) {
                $q->select('nr_atendimento')->from('chat_messages_archive');
            })
            ->count('nr_atendimento');

        if ($stats) {
            $stats->total += $activeOnlyAttendances;
            $stats->total_msgs += (int) ($activeStats->total_msgs ?? 0);
            if ($activeStats->oldest && (! $stats->oldest || $activeStats->oldest < $stats->oldest)) {
                $stats->oldest = $activeStats->oldest;
            }
            if ($activeStats->newest && (! $stats->newest || $activeStats->newest > $stats->newest)) {
                $stats->newest = $activeStats->newest;
            }
        }

        $coveragePct = $this->computeDayCoveragePct();
        $seriesData = $this->buildSeriesData();
        $periodStart = $this->formatDate($stats?->oldest);
        $periodEnd = $this->formatDate($stats?->newest);
        $userMetrics = $this->buildUserMetrics();
        $sectorPanorama = $this->buildSectorPanorama();
        $feedbackStats = $this->buildFeedbackStats();

        return compact('stats', 'coveragePct', 'seriesData', 'periodStart', 'periodEnd', 'userMetrics', 'sectorPanorama', 'feedbackStats');
    }

    // ── Dynamic panorama data (per period/sector) ─────────────────────────────

    private function buildPanoramaData(?Carbon $since, ?int $sectorFilter, ?int $period): array
    {
        $service = app(HandoverSessionService::class);
        $sessions = $service->getSessions($since, $sectorFilter);

        if ($sessions->isEmpty()) {
            return [
                'sessions' => $sessions,
                'nurseStats' => collect(),
                'sectorStats' => collect(),
                'heatmap' => [],
                'shiftStats' => ['total' => 0, 'M' => 0, 'T' => 0, 'N' => 0, 'pct_M' => 0, 'pct_T' => 0, 'pct_N' => 0],
                'sectors' => collect(),
                'topTerms' => [],
                'charDistribution' => [],
                'annotationsByDay' => [],
                'continuityStats' => [],
                'contentClassification' => [],
                'institutionalStats' => ['total_sessions' => 0, 'total_messages' => 0, 'unique_patients' => 0, 'avg_patients_per_session' => null, 'avg_msgs_per_patient' => null, 'avg_msgs_per_session' => null, 'avg_duration_min' => null, 'active_nurses' => 0, 'active_sectors' => 0],
            ];
        }

        return [
            'sessions' => $sessions,
            'nurseStats' => $this->buildNurseStats($sessions, $since, $sectorFilter, $period),
            'sectorStats' => $this->buildSectorStats($sessions, $since),
            'heatmap' => $this->buildHeatmap($since, $sectorFilter),
            'shiftStats' => $this->buildShiftStats($sessions),
            'sectors' => $this->buildSectors($since),
            'topTerms' => $this->buildTopTerms($since, $sectorFilter),
            'charDistribution' => $service->getCharDistribution($since, $sectorFilter),
            'annotationsByDay' => $this->buildAnnotationsByDay($since, $sectorFilter),
            'continuityStats' => $service->getContinuityStats($since, $sectorFilter),
            'contentClassification' => $service->getContentClassification($since, $sectorFilter),
            'institutionalStats' => $this->buildInstitutionalStats($sessions, $since, $sectorFilter),
        ];
    }

    private function buildNurseStats(Collection $sessions, ?Carbon $since, ?int $sectorFilter, ?int $period): Collection
    {
        $service = app(HandoverSessionService::class);
        $weeksInPeriod = $period !== null ? max($period / 7, 1) : null;

        $liveLengths = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
            ->selectRaw('user_id, SUM(CHAR_LENGTH(content)) as total_chars, COUNT(*) as msg_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $analyticsLengths = DB::table('chat_analytics_daily')
            ->whereNotNull('user_id')
            ->when($since, fn ($q) => $q->where('date', '>=', $since->toDateString()))
            ->when($sectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
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

        return $sessions
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

    private function buildSectorStats(Collection $sessions, ?Carbon $since): Collection
    {
        $m = ShiftService::SHIFT_M_START;
        $t = ShiftService::SHIFT_T_START;
        $n = ShiftService::SHIFT_N_START;

        $shiftExpr = "CASE WHEN (HOUR(created_at)*60+MINUTE(created_at)) >= {$m} AND (HOUR(created_at)*60+MINUTE(created_at)) < {$t} THEN 'M'"
            ." WHEN (HOUR(created_at)*60+MINUTE(created_at)) >= {$t} AND (HOUR(created_at)*60+MINUTE(created_at)) < {$n} THEN 'T'"
            .' ELSE \'N\' END';
        $shiftDateExpr = "CASE WHEN (HOUR(created_at)*60+MINUTE(created_at)) < {$m} THEN DATE(created_at - INTERVAL 1 DAY) ELSE DATE(created_at) END";

        $archiveFirstDates = DB::table('chat_messages_archive')
            ->whereNotNull('sector_name')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(sector_name,'$[0]')) as sector, MIN(first_message_at) as first_date")
            ->groupByRaw("JSON_UNQUOTE(JSON_EXTRACT(sector_name,'$[0]'))")
            ->get()
            ->keyBy('sector');

        $sectorCoverage = DB::table('chat_messages')
            ->whereNotNull('sector_name')
            ->selectRaw("sector_name, DATE(MIN(created_at)) as first_date, COUNT(DISTINCT CONCAT({$shiftExpr},'-',{$shiftDateExpr})) as covered_shifts")
            ->groupBy('sector_name')
            ->get()
            ->keyBy('sector_name')
            ->map(function ($row) use ($archiveFirstDates) {
                $archiveDate = $archiveFirstDates->get($row->sector_name)?->first_date;
                if ($archiveDate && $archiveDate < $row->first_date) {
                    $row->first_date = $archiveDate;
                }

                return $row;
            });

        $sectorCharStats = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->whereNotNull('sector_name')
            ->selectRaw('sector_name, COUNT(*) as total_msgs, ROUND(AVG(LENGTH(content))) as avg_chars')
            ->groupBy('sector_name')
            ->get()
            ->keyBy('sector_name');

        return $sessions
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
                $daysInactive = $lastSessionAt ? max(0, (int) Carbon::parse($lastSessionAt)->diffInDays(now())) : null;
                $gsWithMsgs = $g->filter(fn ($s) => ($s['messages_written'] ?? 0) > 0);
                $gsWithDuration = $g->filter(fn ($s) => ($s['duration_min'] ?? 0) >= 1);

                return [
                    'sector_name' => $sectorName,
                    'sector_id' => $g->pluck('sector_id')->filter()->first(),
                    'sessions' => $total,
                    'nurses_count' => $g->pluck('user_id')->unique()->count(),
                    'nurses' => $g->pluck('user_name')->filter()->unique()->sort()->values()->all(),
                    'avg_beds' => ($gsb = $g->filter(fn ($s) => $s['beds_visited'] !== null && $s['beds_visited'] > 0))->isNotEmpty() ? (int) round($gsb->avg('beds_visited')) : null,
                    'total_messages' => $charStats ? (int) $charStats->total_msgs : null,
                    'avg_chars' => $charStats ? (int) $charStats->avg_chars : null,
                    'avg_msgs_per_session' => $gsWithMsgs->isNotEmpty() ? round($gsWithMsgs->avg('messages_written'), 1) : null,
                    'avg_duration_min' => $gsWithDuration->isNotEmpty() ? (int) round($gsWithDuration->avg('duration_min')) : null,
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

    private function buildHeatmap(?Carbon $since, ?int $sectorFilter): array
    {
        $liveCounts = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as cnt')
            ->groupBy('hour')
            ->get()
            ->keyBy('hour')
            ->map(fn ($r) => (int) $r->cnt)
            ->toArray();

        $archiveCounts = app(ChatAnalyticsService::class)->getHourCounts($since, $sectorFilter);

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

    private function buildShiftStats(Collection $sessions): array
    {
        $total = $sessions->count();

        return [
            'total' => $total,
            'M' => $sessions->where('shift', 'M')->count(),
            'T' => $sessions->where('shift', 'T')->count(),
            'N' => $sessions->where('shift', 'N')->count(),
            'pct_M' => $total > 0 ? round($sessions->where('shift', 'M')->count() / $total * 100) : 0,
            'pct_T' => $total > 0 ? round($sessions->where('shift', 'T')->count() / $total * 100) : 0,
            'pct_N' => $total > 0 ? round($sessions->where('shift', 'N')->count() / $total * 100) : 0,
        ];
    }

    private function buildSectors(?Carbon $since): Collection
    {
        return DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->whereNotNull('sector_name')
            ->selectRaw('MAX(sector_id) as sector_id, sector_name, COUNT(*) as cnt')
            ->groupBy('sector_name')
            ->orderByDesc('cnt')
            ->get()
            ->pluck('sector_name', 'sector_id');
    }

    private function buildTopTerms(?Carbon $since, ?int $sectorFilter): array
    {
        $key = 'panorama_top_terms_'.($since?->toDateString() ?? 'all').'_'.($sectorFilter ?? '0');

        return cache()->remember($key, now()->addMinutes(30), fn () => app(HandoverSessionService::class)->getTopTerms($since, $sectorFilter));
    }

    private function buildAnnotationsByDay(?Carbon $since, ?int $sectorFilter): array
    {
        $live = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as value')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->pluck('value', 'date')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $archive = DB::table('chat_analytics_daily')
            ->when($since, fn ($q) => $q->where('date', '>=', $since->toDateString()))
            ->when($sectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
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

        return array_map(fn ($date, $value) => ['date' => $date, 'value' => $value], array_keys($merged), $merged);
    }

    private function buildInstitutionalStats(Collection $s, ?Carbon $since, ?int $sectorFilter): array
    {
        $hasSectorFilter = $sectorFilter !== null;

        $activeAgg = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($hasSectorFilter, fn ($q) => $q->where('sector_id', $sectorFilter))
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

    // ── Static data builders ──────────────────────────────────────────────────

    private function buildSeriesData(): array
    {
        $seriesRaw = DB::table('chat_messages_archive')
            ->selectRaw("DATE_FORMAT(first_message_at, '%Y-%m') as month, COUNT(*) as attendances, SUM(message_count) as messages")
            ->where('first_message_at', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $activeSeriesRaw = DB::table('chat_messages')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as messages")
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $sectorByMonth = DB::table('chat_messages')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, sector_name, COUNT(*) as cnt")
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->whereNotNull('sector_name')
            ->where('sector_name', '!=', '')
            ->groupBy('month', 'sector_name')
            ->orderBy('month')
            ->orderByDesc('cnt')
            ->get()
            ->groupBy('month');

        $archiveSectorRaw = DB::table('chat_messages_archive')
            ->selectRaw("DATE_FORMAT(first_message_at, '%Y-%m') as month, sector_name, message_count")
            ->where('first_message_at', '>=', now()->subMonths(12)->startOfMonth())
            ->whereNotNull('sector_name')
            ->get();

        $archiveSectorByMonth = [];
        foreach ($archiveSectorRaw as $row) {
            $names = json_decode($row->sector_name, true);
            $primary = is_array($names) ? ($names[0] ?? null) : ($row->sector_name ?: null);
            if (! $primary) {
                continue;
            }
            $archiveSectorByMonth[$row->month][$primary] = ($archiveSectorByMonth[$row->month][$primary] ?? 0) + (int) $row->message_count;
        }

        $ptMonths = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $dt = now()->subMonths($i);
            $key = $dt->format('Y-m');

            $sectorCounts = [];
            foreach ($sectorByMonth->get($key, collect()) as $r) {
                $sectorCounts[$r->sector_name] = ($sectorCounts[$r->sector_name] ?? 0) + $r->cnt;
            }
            foreach ($archiveSectorByMonth[$key] ?? [] as $name => $cnt) {
                $sectorCounts[$name] = ($sectorCounts[$name] ?? 0) + $cnt;
            }
            arsort($sectorCounts);

            $sectors = [];
            $i2 = 0;
            $othersCount = 0;
            foreach ($sectorCounts as $name => $cnt) {
                if ($i2 < 3) {
                    $sectors[] = ['name' => $name, 'count' => $cnt];
                } else {
                    $othersCount += $cnt;
                }
                $i2++;
            }
            if ($othersCount > 0) {
                $sectors[] = ['name' => 'Outros', 'count' => $othersCount];
            }

            $months[$key] = [
                'label' => $ptMonths[(int) $dt->format('n') - 1].'/'.$dt->format('y'),
                'attendances' => (int) ($seriesRaw->get($key)?->attendances ?? 0),
                'messages' => (int) ($seriesRaw->get($key)?->messages ?? 0) + (int) ($activeSeriesRaw->get($key)?->messages ?? 0),
                'sectors' => $sectors,
            ];
        }

        $maxMessages = max(1, max(array_column($months, 'messages')));

        return array_values(array_map(function (array $month) use ($maxMessages): array {
            return [
                'label' => $month['label'],
                'messages' => $month['messages'],
                'percentage' => (int) round(($month['messages'] / $maxMessages) * 100),
                'sectors' => $month['sectors'] ?? [],
            ];
        }, $months));
    }

    private function buildUserMetrics(): array
    {
        $totalActive = DB::table('users')->where('status', 'A')->count();
        $last7 = DB::table('users')->where('status', 'A')->where('last_access_at', '>=', now()->subDays(7))->count();
        $last30 = DB::table('users')->where('status', 'A')->where('last_access_at', '>=', now()->subDays(30))->count();
        $nurses = DB::table('users')->where('status', 'A')
            ->where(fn ($q) => $q->where('is_nurse', true)->orWhere('role', 'like', '%nfermeiro%')->orWhere('role', 'like', '%nfermagem%'))
            ->count();

        $topRoles = DB::table('users')->whereNotNull('role')->where('role', '!=', '')->where('status', 'A')
            ->selectRaw('role, COUNT(*) as count')->groupBy('role')->orderByDesc('count')->limit(10)->get();

        $todayAccess = DB::table('users')->whereNotNull('last_access_at')->where('status', 'A')
            ->where('last_access_at', '>=', now()->startOfDay())->orderByDesc('last_access_at')
            ->get(['name', 'username', 'role', 'photo', 'last_access_at']);

        $recentAccess = DB::table('users')->whereNotNull('last_access_at')->where('status', 'A')
            ->orderByDesc('last_access_at')->limit(8)->get(['name', 'username', 'role', 'photo', 'last_access_at']);

        $bedsConfigured = DB::table('nurse_handover_beds as b')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->select(['b.user_id', 'u.name as user_name', DB::raw('COUNT(*) as bed_count'), DB::raw('GROUP_CONCAT(DISTINCT b.bed_code ORDER BY b.bed_code SEPARATOR ", ") as bed_list')])
            ->groupBy('b.user_id', 'u.name')->orderByDesc('bed_count')->get();

        $usersWithBedIds = $bedsConfigured->pluck('user_id')->all();
        $withoutBeds = DB::table('chat_messages')
            ->join('users as u', 'u.id', '=', 'chat_messages.user_id')
            ->whereNotIn('chat_messages.user_id', $usersWithBedIds ?: [0])
            ->whereNotNull('chat_messages.user_id')
            ->selectRaw('chat_messages.user_id, u.name as user_name, COUNT(*) as msg_count')
            ->groupBy('chat_messages.user_id', 'u.name')->orderByDesc('msg_count')->get();

        return [
            'total_active' => $totalActive,
            'last_7d' => $last7,
            'last_30d' => $last30,
            'nurses' => $nurses,
            'top_roles' => $topRoles,
            'today_access' => $todayAccess,
            'recent_access' => $recentAccess,
            'beds_configured' => $bedsConfigured,
            'beds_without' => $withoutBeds,
        ];
    }

    private function buildSectorPanorama(): array
    {
        return [
            'total_configured_users' => DB::table('user_sector_preferences')->distinct('user_id')->count('user_id'),
            'total_sectors' => DB::table('user_sector_preferences')->distinct('sector_code')->count('sector_code'),
            'total_hospitals' => DB::table('user_sector_preferences')->distinct('hospital_code')->count('hospital_code'),
            'top_sectors' => DB::table('user_sector_preferences')
                ->selectRaw('sector_name, hospital_name, COUNT(DISTINCT user_id) as user_count')
                ->groupBy('sector_name', 'hospital_name')->orderByDesc('user_count')->limit(15)->get(),
            'top_hospitals' => DB::table('user_sector_preferences')
                ->selectRaw('hospital_name, COUNT(DISTINCT user_id) as user_count, COUNT(DISTINCT sector_code) as sector_count')
                ->groupBy('hospital_name')->orderByDesc('user_count')->get(),
        ];
    }

    private function buildFeedbackStats(): array
    {
        $total = FeedbackSubmission::count();
        if ($total === 0) {
            return ['total' => 0, 'by_rating' => [], 'recent' => collect()];
        }

        return [
            'total' => $total,
            'by_rating' => FeedbackSubmission::selectRaw('rating, COUNT(*) as count')->groupBy('rating')->pluck('count', 'rating')->toArray(),
            'recent' => FeedbackSubmission::with('user:id,name')->orderByDesc('created_at')->limit(10)->get(),
        ];
    }

    private function computeDayCoveragePct(): int
    {
        $activeCovered = DB::table('chat_messages')
            ->selectRaw('nr_atendimento, COUNT(DISTINCT DATE(created_at)) as covered_days')
            ->groupBy('nr_atendimento')->pluck('covered_days', 'nr_atendimento');

        $archiveCovered = DB::table('chat_messages_archive')
            ->selectRaw('nr_atendimento, GREATEST(1, DATEDIFF(last_message_at, first_message_at) + 1) as covered_days')
            ->pluck('covered_days', 'nr_atendimento');

        $coveredByNr = $activeCovered->union($archiveCovered);
        $totalCoveredDays = $coveredByNr->sum();

        if ($totalCoveredDays === 0) {
            return 0;
        }

        $allNrs = $coveredByNr->keys()->map(fn ($v) => (int) $v)->toArray();
        $totalAdmissionDays = 0;

        foreach (array_chunk($allNrs, 500) as $chunk) {
            $placeholders = implode(',', $chunk);
            try {
                $rows = DB::connection('tasy')->select("
                    SELECT ap.nr_atendimento, ap.dt_entrada, ap.dt_alta
                    FROM tasy.atendimento_paciente ap WHERE ap.nr_atendimento IN ({$placeholders})
                ");
            } catch (\Exception $e) {
                Log::warning('[computeDayCoveragePct] Oracle error', ['exception' => $e->getMessage()]);

                return 0;
            }

            foreach ($rows as $row) {
                $entrada = $row->dt_entrada ? Carbon::parse($row->dt_entrada) : null;
                if (! $entrada) {
                    continue;
                }
                $alta = $row->dt_alta ? Carbon::parse($row->dt_alta) : now();
                $totalAdmissionDays += (int) $entrada->diffInDays($alta);
            }
        }

        return $totalAdmissionDays === 0 ? 0 : (int) round(min($totalCoveredDays, $totalAdmissionDays) / $totalAdmissionDays * 100);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function fetchPatientData(array $nrs): array
    {
        if (empty($nrs)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_map('intval', $nrs));
            $rows = DB::connection('tasy')->select("
                SELECT ap.nr_atendimento, pf.nm_pessoa_fisica, ap.dt_entrada, ap.dt_alta,
                    (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                     FROM tasy.setor_atendimento sa
                     WHERE sa.cd_setor_atendimento = tasy.Obter_Setor_Atendimento(ap.nr_atendimento)) AS ds_setor
                FROM tasy.atendimento_paciente ap
                LEFT JOIN tasy.pessoa_fisica pf ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
                WHERE ap.nr_atendimento IN ({$placeholders})
            ");

            $result = [];
            foreach ($rows as $row) {
                $entrada = $row->dt_entrada ? Carbon::parse($row->dt_entrada) : null;
                $alta = $row->dt_alta ? Carbon::parse($row->dt_alta) : null;
                $result[(int) $row->nr_atendimento] = [
                    'name' => $this->fullName($row->nm_pessoa_fisica),
                    'dt_entrada' => $entrada ? $entrada->format('d/m/Y') : null,
                    'dt_alta' => $alta ? $alta->format('d/m/Y') : null,
                    'still_admitted' => is_null($row->dt_alta),
                    'admission_days' => $entrada ? (int) $entrada->diffInDays($alta ?? now()) : null,
                    'sector_name' => $row->ds_setor ?? null,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('MetricsController fetchPatientData failed', ['exception' => $e, 'count' => count($nrs)]);

            return [];
        }
    }

    private function resolvePatientName(string $nr): ?string
    {
        try {
            $row = DB::connection('tasy')->select(
                'SELECT pf.nm_pessoa_fisica FROM tasy.atendimento_paciente atp
                 LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                 WHERE atp.nr_atendimento = ?',
                [(int) $nr]
            );

            return $this->fullName($row[0]->nm_pessoa_fisica ?? null);
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveUserPhotos(array $usernames, array $users = []): array
    {
        $unique = array_values(array_unique(array_filter($usernames)));
        if (empty($unique)) {
            return [];
        }

        if (! empty($users)) {
            $map = [];
            foreach ($users as $username => $user) {
                $photo = is_array($user) ? ($user['photo'] ?? null) : null;
                if ($photo && strlen($photo) > 100 && base64_decode($photo, true) !== false) {
                    $map[$username] = $photo;
                }
            }
            if (! empty($map)) {
                return $map;
            }
        }

        $rows = DB::table('users')->whereIn('username', $unique)->get(['username', 'photo']);
        $map = [];
        foreach ($rows as $row) {
            $raw = $row->photo;
            if ($raw && strlen($raw) > 100 && base64_decode($raw, true) !== false) {
                $map[$row->username] = $raw;
            }
        }

        return $map;
    }

    private function formatSectors(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? implode(', ', array_filter($decoded)) : $raw;
        }
        if (str_contains($raw, '||')) {
            return implode(', ', array_filter(explode('||', $raw)));
        }

        return $raw;
    }

    private function fullName(?string $full): ?string
    {
        if (! $full) {
            return null;
        }
        $parts = preg_split('/\s+/', trim($full));
        if (count($parts) === 1) {
            return ucfirst(strtolower($parts[0]));
        }

        return ucfirst(strtolower($parts[0])).' '.ucfirst(strtolower(end($parts)));
    }

    private function formatDate(mixed $value, string $format = 'd/m/Y'): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatDuration(mixed $seconds): string
    {
        $sec = (int) $seconds;
        if ($sec <= 0) {
            return '—';
        }
        $min = (int) round($sec / 60);
        if ($min < 60) {
            return $min.'min';
        }
        $h = intdiv($min, 60);
        $m = $min % 60;

        return $m > 0 ? "{$h}h{$m}min" : "{$h}h";
    }

    private function buildTimeline(array $messages): array
    {
        $timeline = [];
        $lastKey = null;

        foreach ($messages as $message) {
            $timestamp = (int) ($message['ts'] ?? 0);
            $turnoLabel = $this->normalizeShiftLabel($message['turno'] ?? null);
            $groupDate = $timestamp > 0 ? date('Y-m-d', $timestamp) : 'unknown';
            $groupKey = $groupDate.'|'.$turnoLabel;

            if ($groupKey !== $lastKey) {
                $timeline[] = [
                    'type' => 'separator',
                    'label' => $turnoLabel.' · '.($timestamp > 0 ? date('d/m/Y', $timestamp) : '—'),
                    'badge_class' => $this->turnoBadgeClass($turnoLabel),
                ];
                $lastKey = $groupKey;
            }

            $user = (string) ($message['user'] ?? '—');
            $timeline[] = [
                'type' => 'message',
                'user' => $user,
                'initial' => strtoupper(substr($user, 0, 1)),
                'time' => $timestamp > 0 ? date('H:i', $timestamp) : '—',
                'text' => (string) ($message['text'] ?? ''),
            ];
        }

        return $timeline;
    }

    private function normalizeShiftLabel(mixed $turno): string
    {
        $normalized = mb_strtolower(trim((string) $turno));

        return match ($normalized) {
            'manha', 'manhã' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            default => $turno ? (string) $turno : 'Noite',
        };
    }

    private function turnoBadgeClass(string $turno): string
    {
        return match (mb_strtolower($turno)) {
            'manhã' => 'bg-amber-50 border-amber-200 text-amber-700',
            'tarde' => 'bg-sky-50 border-sky-200 text-sky-700',
            'noite' => 'bg-indigo-50 border-indigo-200 text-indigo-700',
            default => 'bg-gray-50 border-gray-200 text-gray-500',
        };
    }
}
