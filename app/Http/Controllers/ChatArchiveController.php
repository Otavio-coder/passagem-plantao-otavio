<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatArchiveController extends Controller
{
    // ── Index: carrega stats + ranking; tabela via DataTables AJAX ───────────

    public function index()
    {
        // ── Métricas globais (arquivo)
        $stats = DB::table('chat_messages_archive')->selectRaw("
            COUNT(*)                                                        AS total,
            SUM(message_count)                                              AS total_msgs,
            SUM(CASE WHEN message_count >= 3 THEN 1 ELSE 0 END)            AS consistent,
            ROUND(AVG(message_count), 1)                                    AS avg_per_attendance,
            MIN(first_message_at)                                           AS oldest,
            MAX(last_message_at)                                            AS newest
        ")->first();

        // ── Adiciona mensagens ativas ao total para consistência com o ranking
        // Conta apenas atendimentos que NÃO estão no arquivo (evita duplicação)
        $activeStats = DB::table('chat_messages')->selectRaw("
            COUNT(*)                       AS total_msgs,
            MIN(created_at)                AS oldest,
            MAX(created_at)                AS newest
        ")->first();
        $activeOnlyAttendances = DB::table('chat_messages')
            ->distinct()
            ->whereNotIn('nr_atendimento', function ($q) {
                $q->select('nr_atendimento')->from('chat_messages_archive');
            })
            ->count('nr_atendimento');

        if ($stats) {
            $stats->total      += $activeOnlyAttendances;
            $stats->total_msgs += (int) ($activeStats->total_msgs ?? 0);
            if ($activeStats->oldest && (!$stats->oldest || $activeStats->oldest < $stats->oldest)) {
                $stats->oldest = $activeStats->oldest;
            }
            if ($activeStats->newest && (!$stats->newest || $activeStats->newest > $stats->newest)) {
                $stats->newest = $activeStats->newest;
            }
        }

        $coveragePct = ($stats && $stats->total > 0)
            ? (int) round(($stats->consistent / $stats->total) * 100)
            : 0;

        // ── Série temporal: últimos 6 meses
        $seriesRaw = DB::table('chat_messages_archive')
            ->selectRaw("DATE_FORMAT(first_message_at, '%Y-%m') as month, COUNT(*) as attendances, SUM(message_count) as messages")
            ->where('first_message_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Inclui mensagens ativas na série temporal
        $activeSeriesRaw = DB::table('chat_messages')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as messages")
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $ptMonths = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        $months   = [];
        for ($i = 5; $i >= 0; $i--) {
            $dt  = now()->subMonths($i);
            $key = $dt->format('Y-m');
            $months[$key] = [
                'label'       => $ptMonths[(int) $dt->format('n') - 1] . '/' . $dt->format('y'),
                'attendances' => (int) ($seriesRaw->get($key)?->attendances ?? 0),
                'messages'    => (int) ($seriesRaw->get($key)?->messages ?? 0)
                              + (int) ($activeSeriesRaw->get($key)?->messages ?? 0),
            ];
        }

        [$topAnnotators, $shiftStats] = $this->computeStats();

        return view('chat.archive', compact(
            'stats', 'coveragePct', 'topAnnotators', 'shiftStats', 'months'
        ));
    }

    // ── DataTables client-side: retorna TODOS os registros de uma vez ────────

    public function clientData()
    {
        $archiveRows = DB::table('chat_messages_archive')
            ->select('nr_atendimento', 'message_count', 'last_message_at')
            ->get();

        $activeRows = DB::table('chat_messages')
            ->selectRaw('nr_atendimento, COUNT(*) AS message_count, MAX(created_at) AS last_message_at')
            ->whereNotIn('nr_atendimento', DB::table('chat_messages_archive')->pluck('nr_atendimento'))
            ->groupBy('nr_atendimento')
            ->get();

        $all = $archiveRows->merge($activeRows);

        // Enriquece com Oracle em lotes de 500 para não explodir o IN clause
        $nrs       = $all->pluck('nr_atendimento')->map(fn($v) => (int) $v)->toArray();
        $oracle    = [];
        foreach (array_chunk($nrs, 500) as $chunk) {
            $oracle += $this->fetchPatientData($chunk);
        }

        $data = $all->map(function ($row) use ($oracle) {
            $o  = $oracle[(int) $row->nr_atendimento] ?? null;
            $ts = $row->last_message_at;
            return [
                'nr_atendimento'  => (string) $row->nr_atendimento,
                'patient_name'    => $o['name']          ?? '',
                'sector_name'     => $o['sector_name']   ?? '',
                'dt_entrada'      => $o['dt_entrada']    ?? '',
                'dt_alta'         => $o['dt_alta']       ?? '',
                'still_admitted'  => $o ? $o['still_admitted'] : null,
                'admission_days'  => $o['admission_days'] ?? null,
                'message_count'   => (int) $row->message_count,
                'last_message_at' => $ts ? Carbon::parse($ts)->format('d/m/Y') : '',
                'last_message_raw'=> $ts ? Carbon::parse($ts)->format('Y-m-d') : '',
            ];
        })->values()->all();

        return response()->json(['data' => $data]);
    }

    // ── DataTables server-side AJAX ──────────────────────────────────────────

    public function datatables(Request $request)
    {
        $draw   = (int) $request->input('draw', 1);
        $start  = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 25)));

        // UNION: registros arquivados + registros ativos não arquivados
        $archiveSql = "SELECT nr_atendimento, message_count, last_message_at FROM chat_messages_archive";
        $activeSql  = "SELECT nr_atendimento, COUNT(*) AS message_count, MAX(created_at) AS last_message_at
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
            $query->where('last_message_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->input('to')) {
            $query->where('last_message_at', '<=', $to . ' 23:59:59');
        }
        if ($sector = trim($request->input('sector', ''))) {
            $sectorNrs = $this->getAttendancesForSector($sector);
            if (!empty($sectorNrs)) {
                $query->whereIn('nr_atendimento', $sectorNrs);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $recordsFiltered = $query->count();
        $recordsTotal    = DB::table(DB::raw("({$unionSql}) AS total_count"))->count();

        // ── Ordenação (apenas colunas MySQL disponíveis)
        $sortableMap = [
            0 => 'nr_atendimento',
            5 => 'admission_days',   // placeholder — PHP sort
            6 => 'message_count',
            7 => 'last_message_at',
        ];
        $orderColIdx = (int) $request->input('order.0.column', 7);
        $orderDir    = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderByCol  = $sortableMap[$orderColIdx] ?? 'last_message_at';

        // Colunas Oracle (1=patient_name,2=sector_name,3=dt_entrada,4=dt_alta) → fallback
        if (!isset($sortableMap[$orderColIdx])) {
            $orderByCol = 'last_message_at';
        }

        $query->orderBy($orderByCol, $orderDir);

        $rows = $query->skip($start)->take($length)->get();

        // ── Enriquece apenas as linhas visíveis com Oracle
        $nrs        = $rows->pluck('nr_atendimento')->toArray();
        $oracleData = $this->fetchPatientData($nrs);

        $data = $rows->map(function ($row) use ($oracleData) {
            $o = $oracleData[(int) $row->nr_atendimento] ?? null;
            return [
                'nr_atendimento' => $row->nr_atendimento,
                'patient_name'   => $o['name']           ?? null,
                'sector_name'    => $o['sector_name']     ?? null,
                'dt_entrada'     => $o['dt_entrada']      ?? null,
                'dt_alta'        => $o['dt_alta']         ?? null,
                'still_admitted' => $o ? $o['still_admitted'] : null,
                'admission_days' => $o['admission_days']  ?? null,
                'message_count'  => $row->message_count,
                'last_message_at'=> $row->last_message_at
                    ? Carbon::parse($row->last_message_at)->format('d/m/Y')
                    : null,
                'DT_RowId'       => 'row_' . $row->nr_atendimento,
            ];
        })->values()->all();

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    // ── Modal: mensagens de um atendimento ───────────────────────────────────

    public function show(Request $request, string $nr)
    {
        $archive = DB::table('chat_messages_archive')
            ->where('nr_atendimento', $nr)
            ->first();

        // Fallback: mensagens ativas em chat_messages
        if (!$archive) {
            $activeMessages = DB::table('chat_messages')
                ->where('nr_atendimento', $nr)
                ->orderBy('created_at')
                ->get(['created_at', 'user_id', 'content']);

            if ($activeMessages->isEmpty()) {
                if ($request->expectsJson()) {
                    return response()->json(['messages' => [], 'nr' => $nr, 'patient_name' => null, 'total' => 0]);
                }
                abort(404);
            }

            $messages = $activeMessages->map(fn($m) => [
                'ts'    => strtotime($m->created_at),
                'date'  => date('d/m/Y H:i', strtotime($m->created_at)),
                'user'  => $m->user_id,
                'turno' => '—',
                'text'  => $m->content ?? '',
            ])->values()->all();

            if ($request->expectsJson()) {
                return response()->json([
                    'messages'     => $messages,
                    'nr'           => $nr,
                    'patient_name' => $this->resolvePatientName($nr),
                    'total'        => count($messages),
                    'users'        => $this->resolveUserPhotos(array_column($messages, 'user')),
                ]);
            }

            return view('chat.archive-show', ['archive' => null, 'messages' => $messages, 'nr' => $nr]);
        }

        $messages = [];
        if (!empty($archive->payload)) {
            $decoded = base64_decode($archive->payload, true);
            $json    = $decoded !== false ? @gzuncompress($decoded) : false;
            if ($json === false) {
                // Tenta sem gzuncompress (payload pode ser JSON puro em base64)
                $json = $decoded;
            }
            $raw   = is_string($json) ? (json_decode($json, true) ?? []) : [];
            $label = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'];
            $messages = array_map(fn($m) => [
                'ts'    => $m['ts'] ?? 0,
                'date'  => isset($m['ts']) ? date('d/m/Y H:i', $m['ts']) : '—',
                'user'  => $m['u'] ?? '—',
                'turno' => $label[$m['t'] ?? ''] ?? ($m['t'] ?? '—'),
                'text'  => $m['m'] ?? '',
            ], $raw);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'messages'     => array_values($messages),
                'nr'           => $nr,
                'patient_name' => $this->resolvePatientName($nr),
                'total'        => count($messages),
                'users'        => $this->resolveUserPhotos(array_column($messages, 'user')),
            ]);
        }

        return view('chat.archive-show', compact('archive', 'messages', 'nr'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveUserPhotos(array $usernames): array
    {
        $unique = array_values(array_unique(array_filter($usernames)));
        if (empty($unique)) return [];

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
                "SELECT pf.nm_pessoa_fisica FROM tasy.atendimento_paciente atp
                 LEFT JOIN tasy.pessoa_fisica pf ON atp.cd_pessoa_fisica = pf.cd_pessoa_fisica
                 WHERE atp.nr_atendimento = ?",
                [(int) $nr]
            );
            return $this->fullName($row[0]->nm_pessoa_fisica ?? null);
        } catch (\Exception) {
            return null;
        }
    }

    private function getAttendancesForSector(string $search): array
    {
        try {
            $rows = DB::connection('tasy')->select("
                SELECT DISTINCT ua.nr_atendimento
                FROM tasy.unidade_atendimento ua
                JOIN tasy.setor_atendimento sa ON ua.cd_setor_atendimento = sa.cd_setor_atendimento
                WHERE UPPER(sa.ds_setor_atendimento) LIKE UPPER(:search)
            ", ['search' => '%' . $search . '%']);
            return array_map(fn($r) => (int) $r->nr_atendimento, $rows);
        } catch (\Exception $e) {
            Log::warning('ChatArchive getAttendancesForSector: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchPatientData(array $nrs): array
    {
        if (empty($nrs)) return [];

        try {
            $placeholders = implode(',', array_map('intval', $nrs));
            $rows = DB::connection('tasy')->select("
                SELECT
                    ap.nr_atendimento,
                    pf.nm_pessoa_fisica,
                    ap.dt_entrada,
                    ap.dt_alta,
                    tasy.obter_ds_setor_atendimento(
                        tasy.Obter_Setor_Atendimento(ap.nr_atendimento)
                    ) AS ds_setor
                FROM tasy.atendimento_paciente ap
                LEFT JOIN tasy.pessoa_fisica pf
                       ON ap.cd_pessoa_fisica = pf.cd_pessoa_fisica
                WHERE ap.nr_atendimento IN ({$placeholders})
            ");

            $result = [];
            foreach ($rows as $row) {
                $entrada = $row->dt_entrada ? Carbon::parse($row->dt_entrada) : null;
                $alta    = $row->dt_alta    ? Carbon::parse($row->dt_alta)    : null;

                $result[(int) $row->nr_atendimento] = [
                    'name'           => $this->fullName($row->nm_pessoa_fisica),
                    'dt_entrada'     => $entrada ? $entrada->format('d/m/Y') : null,
                    'dt_alta'        => $alta    ? $alta->format('d/m/Y')    : null,
                    'still_admitted' => is_null($row->dt_alta),
                    'admission_days' => $entrada ? (int) $entrada->diffInDays($alta ?? now()) : null,
                    'sector_name'    => $row->ds_setor ?? null,
                ];
            }
            return $result;
        } catch (\Exception $e) {
            Log::error('ChatArchive fetchPatientData: ' . $e->getMessage(), ['count' => count($nrs)]);
            return [];
        }
    }

    private function fullName(?string $full): ?string
    {
        if (!$full) return null;
        $parts = preg_split('/\s+/', trim($full));
        if (count($parts) === 1) return ucfirst(strtolower($parts[0]));
        return ucfirst(strtolower($parts[0])) . ' ' . ucfirst(strtolower(end($parts)));
    }

    private function computeStats(): array
    {
        $rows = DB::table('chat_messages_archive')
            ->pluck('payload');

        $userCounts = [];
        $shiftStats = ['manha' => 0, 'tarde' => 0, 'noite' => 0];

        foreach ($rows as $payload) {
            $json = @gzuncompress(base64_decode($payload));
            if (!$json) continue;
            foreach (json_decode($json, true) ?? [] as $m) {
                $u = $m['u'] ?? null;
                if ($u) $userCounts[$u] = ($userCounts[$u] ?? 0) + 1;
                $t = $m['t'] ?? null;
                if ($t && isset($shiftStats[$t])) $shiftStats[$t]++;
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

        $activeShifts = DB::table('chat_messages')
            ->selectRaw("
                CASE
                    WHEN HOUR(created_at) >= 7 AND HOUR(created_at) < 13 THEN 'manha'
                    WHEN HOUR(created_at) >= 13 AND HOUR(created_at) < 19 THEN 'tarde'
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
        $top20 = array_slice($userCounts, 0, 20, true);

        $users = DB::table('users')
            ->whereIn('username', array_keys($top20))
            ->get(['username', 'name', 'photo'])
            ->keyBy('username');

        $topAnnotators = [];
        foreach ($top20 as $username => $count) {
            $u        = $users->get($username);
            $rawPhoto = $u?->photo;
            $photo    = null;
            if ($rawPhoto && strlen($rawPhoto) > 100) {
                $decoded = base64_decode($rawPhoto, true);
                if ($decoded !== false && strlen($decoded) > 50) {
                    $photo = $rawPhoto;
                }
            }
            $topAnnotators[] = [
                'username' => $username,
                'name'     => $u ? $this->fullName($u->name) : $username,
                'count'    => $count,
                'tier'     => match(true) {
                    $count >= 100 => 'ouro',
                    $count >= 50  => 'prata',
                    $count >= 20  => 'bronze',
                    default       => 'participante',
                },
                'photo'    => $photo,
            ];
        }

        return [$topAnnotators, $shiftStats];
    }
}
