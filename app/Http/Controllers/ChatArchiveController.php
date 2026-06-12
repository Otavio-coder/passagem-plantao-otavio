<?php

namespace App\Http\Controllers;

use App\Models\FeedbackSubmission;
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

class ChatArchiveController extends Controller
{
    // ── Index: carrega stats + ranking; tabela via DataTables AJAX ───────────

    private const PANORAMA_CACHE_KEY = 'panorama_index_v3';

    private const PANORAMA_CACHE_TTL = 3600; // 1 hora

    public function clearCache()
    {
        // Limpa todas as chaves panorama_ no cache (index + top_terms por período/setor)
        DB::table('cache')
            ->where('key', 'like', '%panorama_%')
            ->delete();

        return redirect()->route('admin.dashboard')->with('cache_cleared', true);
    }

    public function index()
    {
        $cached = Cache::get(self::PANORAMA_CACHE_KEY);

        if ($cached) {
            return view('chat.archive', $cached);
        }

        // ── Métricas globais (arquivo)
        $stats = DB::table('chat_messages_archive')->selectRaw('
            COUNT(*)                                                        AS total,
            SUM(message_count)                                              AS total_msgs,
            SUM(CASE WHEN message_count >= 3 THEN 1 ELSE 0 END)            AS consistent,
            ROUND(AVG(message_count), 1)                                    AS avg_per_attendance,
            MIN(first_message_at)                                           AS oldest,
            MAX(last_message_at)                                            AS newest
        ')->first();

        // ── Adiciona mensagens ativas ao total para consistência com o ranking
        // Conta apenas atendimentos que NÃO estão no arquivo (evita duplicação)
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

        // ── Cobertura: dias com anotação / dias totais de internação (via Oracle)
        $coveragePct = $this->computeDayCoveragePct();

        // ── Série temporal: últimos 6 meses
        $seriesRaw = DB::table('chat_messages_archive')
            ->selectRaw("DATE_FORMAT(first_message_at, '%Y-%m') as month, COUNT(*) as attendances, SUM(message_count) as messages")
            ->where('first_message_at', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Inclui mensagens ativas na série temporal
        $activeSeriesRaw = DB::table('chat_messages')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as messages")
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // Distribuição de setores por mês — chat_messages (ativo)
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

        // Distribuição de setores do archive (sector_name é JSON array ordenado por tempo decrescente;
        // índice 0 = setor onde o paciente ficou mais tempo durante o período das mensagens)
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

            // Combinar chat_messages + archive
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
                'messages' => (int) ($seriesRaw->get($key)?->messages ?? 0)
                              + (int) ($activeSeriesRaw->get($key)?->messages ?? 0),
                'sectors' => $sectors,
            ];
        }

        $seriesData = $this->buildMonthlySeries($months);
        $periodStart = $this->formatDate($stats?->oldest);
        $periodEnd = $this->formatDate($stats?->newest);
        $userMetrics = $this->buildUserMetrics();
        $sectorPanorama = $this->buildSectorPanorama();
        $feedbackStats = $this->buildFeedbackStats();

        $viewData = compact(
            'stats',
            'coveragePct',
            'seriesData',
            'periodStart',
            'periodEnd',
            'userMetrics',
            'sectorPanorama',
            'feedbackStats',
        );

        Cache::put(self::PANORAMA_CACHE_KEY, $viewData, self::PANORAMA_CACHE_TTL);

        return view('chat.archive', $viewData);
    }

    // ── DataTables client-side: retorna TODOS os registros de uma vez ────────

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

        // Enriquece com Oracle em lotes de 500 para não explodir o IN clause
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

        // UNION: archive tem sector_name como JSON; ativos têm sector_name por mensagem
        $archiveSql = 'SELECT nr_atendimento, message_count, last_message_at, sector_name FROM chat_messages_archive';
        $activeSql = "SELECT nr_atendimento, COUNT(*) AS message_count, MAX(created_at) AS last_message_at,
                             GROUP_CONCAT(DISTINCT sector_name ORDER BY sector_name SEPARATOR '||') AS sector_name
                       FROM chat_messages
                       WHERE nr_atendimento NOT IN (SELECT nr_atendimento FROM chat_messages_archive)
                       GROUP BY nr_atendimento";

        $unionSql = "({$archiveSql}) UNION ALL ({$activeSql})";

        $query = DB::table(DB::raw("({$unionSql}) AS combined_records"));

        // ── Filtros customizados (passados via ajax.data)
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

        // ── Ordenação (apenas colunas MySQL disponíveis)
        $sortableMap = [
            0 => 'nr_atendimento',
            5 => 'admission_days',   // placeholder — PHP sort
            6 => 'message_count',
            7 => 'last_message_at',
        ];
        $orderColIdx = (int) $request->input('order.0.column', 7);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderByCol = $sortableMap[$orderColIdx] ?? 'last_message_at';

        if (! isset($sortableMap[$orderColIdx])) {
            $orderByCol = 'last_message_at';
        }

        $query->orderBy($orderByCol, $orderDir);

        $rows = $query->skip($start)->take($length)->get();

        // ── Enriquece apenas as linhas visíveis com Oracle
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

    // ── Modal: mensagens de um atendimento ───────────────────────────────────

    public function show(Request $request, string $nr)
    {
        $archive = DB::table('chat_messages_archive')
            ->where('nr_atendimento', $nr)
            ->first();

        // Fallback: mensagens ativas em chat_messages
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

    // ── Helpers ──────────────────────────────────────────────────────────────

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

        $rows = DB::table('users')
            ->whereIn('username', $unique)
            ->get(['username', 'photo']);

        $map = [];
        foreach ($rows as $row) {
            $raw = $row->photo;
            if ($raw && strlen($raw) > 100 && base64_decode($raw, true) !== false) {
                $map[$row->username] = $raw;
            }
        }

        return $map;
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

    /**
     * Parses sector_name into a human-readable comma-separated string.
     *
     * Handles three input formats:
     *  - JSON array (archive): '["HSR 2º","HDVS-CIEM"]' → 'HSR 2º, HDVS-CIEM'
     *  - Pipe-separated (GROUP_CONCAT from active records): 'HSR 2º||HDVS-CIEM' → 'HSR 2º, HDVS-CIEM'
     *  - Plain string (fallback from Oracle): 'HSR 2º' → 'HSR 2º'
     */
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

    /**
     * Cobertura = dias com anotação / dias totais de internação dos atendimentos no sistema.
     * Numerador (MySQL): dias distintos com mensagem por atendimento.
     * Denominador (Oracle): diffInDays(dt_entrada, dt_alta ?? hoje) por atendimento.
     */
    private function computeDayCoveragePct(): int
    {
        // Numerador: dias cobertos por mensagens ativas
        $activeCovered = DB::table('chat_messages')
            ->selectRaw('nr_atendimento, COUNT(DISTINCT DATE(created_at)) as covered_days')
            ->groupBy('nr_atendimento')
            ->pluck('covered_days', 'nr_atendimento');

        // Numerador: dias cobertos pelo archive (proxy: span entre primeira e última mensagem)
        $archiveCovered = DB::table('chat_messages_archive')
            ->selectRaw('nr_atendimento, GREATEST(1, DATEDIFF(last_message_at, first_message_at) + 1) as covered_days')
            ->pluck('covered_days', 'nr_atendimento');

        $coveredByNr = $activeCovered->union($archiveCovered);
        $totalCoveredDays = $coveredByNr->sum();

        if ($totalCoveredDays === 0) {
            return 0;
        }

        // Denominador: admission_days via Oracle (query leve, sem subqueries por row)
        $allNrs = $coveredByNr->keys()->map(fn ($v) => (int) $v)->toArray();
        $totalAdmissionDays = 0;

        foreach (array_chunk($allNrs, 500) as $chunk) {
            $placeholders = implode(',', $chunk);

            try {
                $rows = DB::connection('tasy')->select("
                    SELECT ap.nr_atendimento, ap.dt_entrada, ap.dt_alta
                    FROM tasy.atendimento_paciente ap
                    WHERE ap.nr_atendimento IN ({$placeholders})
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

        if ($totalAdmissionDays === 0) {
            return 0;
        }

        return (int) round(min($totalCoveredDays, $totalAdmissionDays) / $totalAdmissionDays * 100);
    }

    private function fetchPatientData(array $nrs): array
    {
        if (empty($nrs)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_map('intval', $nrs));
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.nr_atendimento,
                    pf.nm_pessoa_fisica,
                    ap.dt_entrada,
                    ap.dt_alta,
                    (SELECT NVL(sa.ds_prescricao, sa.ds_setor_atendimento)
                     FROM tasy.setor_atendimento sa
                     WHERE sa.cd_setor_atendimento = tasy.Obter_Setor_Atendimento(ap.nr_atendimento)) AS ds_setor
                FROM tasy.atendimento_paciente ap
                LEFT JOIN tasy.pessoa_fisica pf
                       ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
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
            Log::error('ChatArchive fetchPatientData failed', [
                'exception' => $e,
                'count' => count($nrs),
            ]);

            return [];
        }
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

    /**
     * @param  array{manha:int,tarde:int,noite:int}  $shiftStats
     * @return array<int, array{key:string,label:string,color:string,percentage:int}>
     */
    private function buildShiftDistribution(array $shiftStats): array
    {
        $meta = [
            'manha' => ['label' => 'Manhã', 'color' => '#F59E0B'],
            'tarde' => ['label' => 'Tarde', 'color' => '#0071B9'],
            'noite' => ['label' => 'Noite', 'color' => '#073772'],
        ];

        $total = max(1, array_sum($shiftStats));
        $distribution = [];

        foreach ($meta as $key => $item) {
            $distribution[] = [
                'key' => $key,
                'label' => $item['label'],
                'color' => $item['color'],
                'percentage' => (int) round((($shiftStats[$key] ?? 0) / $total) * 100),
            ];
        }

        return $distribution;
    }

    /**
     * @param  array<string, array{label:string,attendances:int,messages:int}>  $months
     * @return array<int, array{label:string,messages:int,percentage:int}>
     */
    private function buildMonthlySeries(array $months): array
    {
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

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array{type:string,label?:string,badge_class?:string,user?:string,initial?:string,time?:string,text?:string}>
     */
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

    private function computeStats(): array
    {
        $rows = DB::table('chat_messages_archive')
            ->pluck('payload');

        $userCounts = [];
        $shiftStats = ['manha' => 0, 'tarde' => 0, 'noite' => 0];

        foreach ($rows as $payload) {
            $decoded = ChatArchivePayload::decode($payload);
            foreach ($decoded['messages'] as $m) {
                $u = $m['u'] ?? null;
                if ($u) {
                    $userCounts[$u] = ($userCounts[$u] ?? 0) + 1;
                }
                $t = $m['t'] ?? null;
                if ($t && isset($shiftStats[$t])) {
                    $shiftStats[$t]++;
                }
            }
        }

        // Inclui mensagens ativas (chat_messages) no ranking e na distribuição de turnos
        $activeUsers = DB::table('chat_messages')
            ->join('users', 'chat_messages.user_id', '=', 'users.id')
            ->selectRaw('users.username, COUNT(*) as cnt')
            ->groupBy('users.username', 'users.id')
            ->get();

        foreach ($activeUsers as $row) {
            if ($row->username) {
                $userCounts[$row->username] = ($userCounts[$row->username] ?? 0) + $row->cnt;
            }
        }

        $m = ShiftService::SHIFT_M_START;
        $t = ShiftService::SHIFT_T_START;
        $n = ShiftService::SHIFT_N_START;
        $activeShifts = DB::table('chat_messages')
            ->selectRaw("
                CASE
                    WHEN (HOUR(created_at)*60+MINUTE(created_at)) >= {$m} AND (HOUR(created_at)*60+MINUTE(created_at)) < {$t} THEN 'manha'
                    WHEN (HOUR(created_at)*60+MINUTE(created_at)) >= {$t} AND (HOUR(created_at)*60+MINUTE(created_at)) < {$n} THEN 'tarde'
                    ELSE 'noite'
                END AS turno,
                COUNT(*) as cnt
            ")
            ->groupBy('turno')
            ->get();

        foreach ($activeShifts as $row) {
            if (isset($shiftStats[$row->turno])) {
                $shiftStats[$row->turno] += $row->cnt;
            }
        }

        arsort($userCounts);

        // Nomes para todos os usuários com atividade
        $allUsernames = array_keys($userCounts);
        $users = DB::table('users')
            ->whereIn('username', $allUsernames)
            ->get(['username', 'name', 'role', 'photo'])
            ->keyBy('username');

        $topAnnotators = [];
        $allAnnotators = [];
        foreach ($userCounts as $username => $count) {
            $u = $users->get($username);
            $displayName = $u ? $this->fullName($u->name) : $username;
            $role = $u ? trim((string) ($u->role ?? '')) : '';
            $fullLabel = $role !== '' ? $displayName.' - '.$role : $displayName;

            $allAnnotators[] = [
                'username' => $username,
                'name' => $fullLabel,
                'count' => $count,
            ];

            if (count($topAnnotators) < 20) {
                $rawPhoto = $u?->photo;
                $photo = null;
                if ($rawPhoto && strlen($rawPhoto) > 100) {
                    $decoded = base64_decode($rawPhoto, true);
                    if ($decoded !== false && strlen($decoded) > 50) {
                        $photo = $rawPhoto;
                    }
                }
                $topAnnotators[] = [
                    'username' => $username,
                    'name' => $fullLabel,
                    'count' => $count,
                    'tier' => match (true) {
                        $count >= 100 => 'ouro',
                        $count >= 50 => 'prata',
                        $count >= 20 => 'bronze',
                        default => 'participante',
                    },
                    'photo' => $photo,
                ];
            }
        }

        return [$topAnnotators, $shiftStats, $allAnnotators];
    }

    private function buildRecentShiftTimelines(int $days = 14): array
    {
        $shiftLabels = ['M' => 'Manhã', 'T' => 'Tarde', 'N' => 'Noite'];
        $shiftColors = ['M' => 'amber', 'T' => 'orange', 'N' => 'indigo'];

        $since = now()->subDays($days)->startOfDay();

        $rows = DB::table('chat_messages as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->where('cm.created_at', '>=', $since)
            ->orderBy('cm.created_at')
            ->get(['cm.user_id', 'u.name as user_name', 'cm.nr_atendimento', 'cm.created_at', 'cm.content', 'cm.sector_name']);

        // Agrupar por (user, shift, shift_date)
        $grouped = [];
        foreach ($rows as $row) {
            $t = strtotime($row->created_at);
            $hour = (int) date('H', $t);
            $totalMin = $hour * 60 + (int) date('i', $t);
            $shift = ShiftService::shiftFromMinutes($totalMin);
            $shiftDate = ShiftService::shiftDateFromTimestamp($t);
            $key = $row->user_id.'_'.$shift.'_'.$shiftDate;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'user_id' => $row->user_id,
                    'name' => $row->user_name,
                    'sector_name' => $row->sector_name ?? '—',
                    'shift' => $shift,
                    'shift_date' => $shiftDate,
                    'started_at_ts' => $t,
                    'messages' => [],
                ];
            }
            // Pega setor da primeira mensagem que tiver sector_name não-nulo
            if (($grouped[$key]['sector_name'] === '—' || $grouped[$key]['sector_name'] === null) && $row->sector_name) {
                $grouped[$key]['sector_name'] = $row->sector_name;
            }
            $grouped[$key]['messages'][] = ['ts' => $t, 'hour' => $hour, 'time' => date('H:i', $t), 'chars' => mb_strlen($row->content), 'preview' => mb_substr(trim($row->content), 0, 80)];
        }

        $result = [];
        foreach ($grouped as $item) {
            $msgs = $item['messages'];
            $shift = $item['shift'];
            $shiftDate = $item['shift_date'];

            // Eixo completo do turno (com grace 30min — noite inclui hora 7, manhã inclui 13, tarde inclui 19)
            $axisHours = match ($shift) {
                'M' => range(7, 13),
                'T' => range(13, 19),
                default => [19, 20, 21, 22, 23, 0, 1, 2, 3, 4, 5, 6, 7],
            };
            $dist = array_fill_keys($axisHours, 0);
            foreach ($msgs as $m) {
                if (array_key_exists($m['hour'], $dist)) {
                    $dist[$m['hour']]++;
                }
            }
            $maxDist = max($dist) ?: 1;

            // Gap analysis 45min → tempo ativo
            $activeMin = 0;
            $prevTs = null;
            $blockStart = null;
            $blockEnd = null;
            foreach ($msgs as $m) {
                $ts = $m['ts'];
                // Mensagens de noite antes de 07:30 pertencem ao dia lógico anterior → ajustar timestamp
                $msgTotalMin = $m['hour'] * 60 + (int) substr($m['time'], 3, 2);
                if ($shift === 'N' && $msgTotalMin < ShiftService::SHIFT_M_START) {
                    $ts = strtotime('+1 day', strtotime($shiftDate.' '.$m['time'].':00'));
                }
                if ($prevTs === null) {
                    $blockStart = $blockEnd = $ts;
                } elseif ($ts - $prevTs > 2700) {
                    $activeMin += (int) round(($blockEnd - $blockStart) / 60);
                    $blockStart = $blockEnd = $ts;
                } else {
                    $blockEnd = $ts;
                }
                $prevTs = $ts;
            }
            if ($blockStart !== null) {
                $activeMin += (int) round(($blockEnd - $blockStart) / 60);
            }

            $result[] = [
                'name' => $item['name'],
                'sector_name' => $item['sector_name'],
                'shift' => $shift,
                'shift_label' => $shiftLabels[$shift] ?? $shift,
                'shift_color' => $shiftColors[$shift] ?? 'gray',
                'shift_date' => Carbon::parse($shiftDate)->format('d/m'),
                'first_msg' => $msgs[0]['time'],
                'last_msg' => end($msgs)['time'],
                'message_count' => count($msgs),
                'active_min' => $activeMin < 180 ? $activeMin : null,
                'dist' => array_values($dist),
                'axis_hours' => array_map(fn ($h) => str_pad($h, 2, '0', STR_PAD_LEFT), $axisHours),
                'max_dist' => $maxDist,
                'messages' => $msgs,
                'started_at_ts' => $item['started_at_ts'],
            ];
        }

        // Ordenar por data desc, hora início desc
        usort($result, fn ($a, $b) => $b['started_at_ts'] <=> $a['started_at_ts']);

        return $result;
    }

    /**
     * @return array{total: int, finished: int, avg_duration_min: float|null, avg_beds: float|null, recent: list<array<string,mixed>>}
     */
    private function buildHandoverMetrics(): array
    {
        $shiftLabels = ['M' => 'Manhã', 'T' => 'Tarde', 'N' => 'Noite'];

        // Fonte 1: activity_log (formato novo)
        $logStart = DB::table('handover_activity_log')->min('occurred_at');
        $logCutoff = $logStart ? Carbon::parse($logStart)->startOfDay() : now();

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
                DB::raw('TIMESTAMPDIFF(SECOND, MIN(hal.occurred_at), MAX(hal.occurred_at)) as duration_seconds'),
                DB::raw('MIN(hal.occurred_at) as started_at'),
                DB::raw('"M" as shift_key'),
            ])
            ->groupBy('hal.user_id', 'hal.shift', 'hal.shift_date', 'u.name')
            ->having(DB::raw('SUM(CASE WHEN hal.event = "chat_post" THEN 1 ELSE 0 END)'), '>=', 1)
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($s) => (object) array_merge((array) $s, ['shift_key' => $s->shift]));

        // Fonte 2: chat_messages (formato histórico, antes do log)
        $chatSessions = DB::table('chat_messages as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->select([
                'cm.user_id',
                'u.name as user_name',
                DB::raw('MAX(NULLIF(cm.sector_id, 0)) as sector_id'),
                DB::raw('MAX(cm.sector_name) as sector_name'),
                DB::raw(ShiftService::shiftSqlExpr('cm.created_at').' as shift'),
                DB::raw(ShiftService::shiftDateSqlExpr('cm.created_at').' as shift_date'),
                DB::raw('COUNT(DISTINCT cm.nr_atendimento) as beds_visited'),
                DB::raw('TIMESTAMPDIFF(SECOND, MIN(cm.created_at), MAX(cm.created_at)) as duration_seconds'),
                DB::raw('MIN(cm.created_at) as started_at'),
            ])
            // Usar shift_date (não created_at) no corte — turno noite passa da meia-noite
            ->whereRaw(ShiftService::shiftDateSqlExpr('cm.created_at').' < ?', [$logCutoff->toDateString()])
            ->groupBy('cm.user_id', 'shift', 'shift_date', 'u.name')
            ->having('beds_visited', '>=', 1)
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($s) => (object) array_merge((array) $s, ['shift_key' => $s->shift]));

        $sessions = $logSessions->merge($chatSessions)->sortByDesc('started_at');

        $total = $sessions->count();

        if ($total === 0) {
            return ['total' => 0, 'finished' => 0, 'avg_duration_min' => null, 'avg_beds' => null, 'recent' => []];
        }

        // Sessões > 3h = enfermeira anotando ao longo do turno, não passagem concentrada
        $compact = $sessions->filter(fn ($s) => $s->duration_seconds > 0 && $s->duration_seconds <= 10800);

        $recent = $sessions->take(5)->map(fn ($s) => [
            'user_name' => $s->user_name,
            'shift' => $shiftLabels[$s->shift] ?? $s->shift,
            'sector_name' => $s->sector_name ?? '—',
            'beds_visited' => (int) $s->beds_visited,
            'duration' => $s->duration_seconds > 10800 ? '—' : $this->formatDuration($s->duration_seconds),
            'started_at' => Carbon::parse($s->started_at)->format('d/m/Y H:i'),
        ])->values()->all();

        $avgDuration = $compact->avg('duration_seconds');
        $avgBeds = $sessions->avg('beds_visited');

        return [
            'total' => $total,
            'finished' => $total,
            'avg_duration_min' => $avgDuration !== null ? round($avgDuration / 60) : null,
            'avg_beds' => $avgBeds !== null ? round($avgBeds, 1) : null,
            'recent' => $recent,
        ];
    }

    /**
     * @return array{total_active:int, last_7d:int, last_30d:int, nurses:int, top_roles:Collection, recent_access:Collection}
     */
    private function buildUserMetrics(): array
    {
        $totalActive = DB::table('users')->where('status', 'A')->count();

        $last7 = DB::table('users')
            ->where('status', 'A')
            ->where('last_access_at', '>=', now()->subDays(7))
            ->count();

        $last30 = DB::table('users')
            ->where('status', 'A')
            ->where('last_access_at', '>=', now()->subDays(30))
            ->count();

        $nurses = DB::table('users')
            ->where('status', 'A')
            ->where(function ($q) {
                $q->where('is_nurse', true)
                    ->orWhere('role', 'like', '%nfermeiro%')
                    ->orWhere('role', 'like', '%nfermagem%');
            })
            ->count();

        $topRoles = DB::table('users')
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->where('status', 'A')
            ->selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $recentAccess = DB::table('users')
            ->whereNotNull('last_access_at')
            ->where('status', 'A')
            ->orderByDesc('last_access_at')
            ->limit(8)
            ->get(['name', 'username', 'role', 'photo', 'last_access_at']);

        // ── Leitos configurados por plantonista ───────────────────────────────
        $bedsConfigured = DB::table('nurse_handover_beds as b')
            ->join('users as u', 'u.id', '=', 'b.user_id')
            ->select([
                'b.user_id',
                'u.name as user_name',
                DB::raw('COUNT(*) as bed_count'),
                DB::raw('GROUP_CONCAT(DISTINCT b.bed_code ORDER BY b.bed_code SEPARATOR ", ") as bed_list'),
            ])
            ->groupBy('b.user_id', 'u.name')
            ->orderByDesc('bed_count')
            ->get();

        // Plantonistas que usaram o sistema (chat_messages) mas não têm leitos
        $usersWithBedIds = $bedsConfigured->pluck('user_id')->all();
        $withoutBeds = DB::table('chat_messages')
            ->join('users as u', 'u.id', '=', 'chat_messages.user_id')
            ->whereNotIn('chat_messages.user_id', $usersWithBedIds ?: [0])
            ->whereNotNull('chat_messages.user_id')
            ->selectRaw('chat_messages.user_id, u.name as user_name, COUNT(*) as msg_count')
            ->groupBy('chat_messages.user_id', 'u.name')
            ->orderByDesc('msg_count')
            ->get();

        // ── Relatório de pendências: uso ──────────────────────────────────────
        $pendingStats = DB::table('pending_events_access_logs as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->selectRaw("
                SUM(CASE WHEN p.event = 'view'    THEN 1 ELSE 0 END) AS total_views,
                SUM(CASE WHEN p.event = 'export'  THEN 1 ELSE 0 END) AS total_exports,
                SUM(CASE WHEN p.event = 'refresh' THEN 1 ELSE 0 END) AS total_refreshes,
                SUM(CASE WHEN p.event = 'view'    AND p.occurred_at >= ? THEN 1 ELSE 0 END) AS views_7d,
                SUM(CASE WHEN p.event = 'view'    AND p.occurred_at >= ? THEN 1 ELSE 0 END) AS views_30d,
                COUNT(DISTINCT p.user_id) AS unique_users
            ", [now()->subDays(7), now()->subDays(30)])
            ->first();

        $pendingTopUsers = DB::table('pending_events_access_logs as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('p.event', 'view')
            ->selectRaw('u.name, COUNT(*) as cnt')
            ->groupBy('u.name')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        // ── Insights do handover_activity_log ────────────────────────────────
        $auditInsights = DB::table('handover_activity_log')
            ->selectRaw("
                COUNT(DISTINCT CONCAT(user_id,'_',shift,'_',shift_date)) AS total_sessions,
                SUM(CASE WHEN event = 'modal_open' THEN 1 ELSE 0 END)  AS total_opens,
                SUM(CASE WHEN event = 'chat_post'  THEN 1 ELSE 0 END)  AS total_posts,
                COUNT(DISTINCT nr_atendimento)                          AS unique_patients,
                COUNT(DISTINCT user_id)                                 AS unique_users
            ")
            ->first();

        $auditCoverageRate = ($auditInsights->total_opens > 0)
            ? (int) round($auditInsights->total_posts / $auditInsights->total_opens * 100)
            : null;

        return [
            'total_active' => $totalActive,
            'last_7d' => $last7,
            'last_30d' => $last30,
            'nurses' => $nurses,
            'top_roles' => $topRoles,
            'recent_access' => $recentAccess,
            'beds_configured' => $bedsConfigured,
            'beds_without' => $withoutBeds,
            'pending_stats' => $pendingStats,
            'pending_top_users' => $pendingTopUsers,
            'audit_insights' => [
                'total_sessions' => (int) ($auditInsights->total_sessions ?? 0),
                'total_opens' => (int) ($auditInsights->total_opens ?? 0),
                'total_posts' => (int) ($auditInsights->total_posts ?? 0),
                'unique_patients' => (int) ($auditInsights->unique_patients ?? 0),
                'unique_users' => (int) ($auditInsights->unique_users ?? 0),
                'coverage_rate' => $auditCoverageRate,
            ],
        ];
    }

    /**
     * @return array{total_configured_users:int, total_sectors:int, total_hospitals:int, top_sectors:Collection, top_hospitals:Collection}
     */
    private function buildSectorPanorama(): array
    {
        $totalConfiguredUsers = DB::table('user_sector_preferences')
            ->distinct('user_id')
            ->count('user_id');

        $totalSectors = DB::table('user_sector_preferences')
            ->distinct('sector_code')
            ->count('sector_code');

        $totalHospitals = DB::table('user_sector_preferences')
            ->distinct('hospital_code')
            ->count('hospital_code');

        $topSectors = DB::table('user_sector_preferences')
            ->selectRaw('sector_name, hospital_name, COUNT(DISTINCT user_id) as user_count')
            ->groupBy('sector_name', 'hospital_name')
            ->orderByDesc('user_count')
            ->limit(15)
            ->get();

        $topHospitals = DB::table('user_sector_preferences')
            ->selectRaw('hospital_name, COUNT(DISTINCT user_id) as user_count, COUNT(DISTINCT sector_code) as sector_count')
            ->groupBy('hospital_name')
            ->orderByDesc('user_count')
            ->get();

        return [
            'total_configured_users' => $totalConfiguredUsers,
            'total_sectors' => $totalSectors,
            'total_hospitals' => $totalHospitals,
            'top_sectors' => $topSectors,
            'top_hospitals' => $topHospitals,
        ];
    }

    /**
     * @return array{total:int, by_rating:array<string,int>, recent:Collection}
     */
    private function buildFeedbackStats(): array
    {
        $total = FeedbackSubmission::count();

        if ($total === 0) {
            return ['total' => 0, 'by_rating' => [], 'recent' => collect()];
        }

        $byRating = FeedbackSubmission::selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $recent = FeedbackSubmission::with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return ['total' => $total, 'by_rating' => $byRating, 'recent' => $recent];
    }
}
