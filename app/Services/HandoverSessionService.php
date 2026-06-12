<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HandoverSessionService
{
    private const SHIFT_LABELS = ['M' => 'Manhã', 'T' => 'Tarde', 'N' => 'Noite'];

    private const MAX_SESSION_DURATION_SECONDS = 28800; // 8h — cobre plantões noturnos de documentação contínua

    /**
     * Retorna sessões unificadas de ambas as fontes (handover_activity_log + chat_messages).
     * Sessão = (user, shift, shift_date) com ≥ 1 mensagem escrita.
     */
    public function getSessions(?Carbon $since, ?int $sectorId = null): Collection
    {
        $logCutoff = $this->logCutoff();

        $logSessions = $this->queryLogSessions($since, $sectorId);
        $chatSessions = $this->queryChatSessions($since, $logCutoff, $sectorId);
        $archiveSessions = $this->queryArchiveSessions($since, $logCutoff, $sectorId);

        return $logSessions->merge($chatSessions)->merge($archiveSessions)
            ->sortByDesc('started_at')
            ->values()
            ->map(fn ($s) => $this->normalizeSession($s));
    }

    /**
     * Data de corte a partir da qual o activity_log é a fonte primária.
     */
    public function logCutoff(): Carbon
    {
        $logStart = DB::table('handover_activity_log')->min('occurred_at');

        return $logStart ? Carbon::parse($logStart)->startOfDay() : now();
    }

    private function queryLogSessions(?Carbon $since, ?int $sectorId): Collection
    {
        return DB::table('handover_activity_log as hal')
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
                DB::raw('"log" as source'),
            ])
            ->when($since, fn ($q) => $q->where('hal.occurred_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('hal.sector_id', $sectorId))
            ->groupBy('hal.user_id', 'hal.shift', 'hal.shift_date', 'u.name')
            ->having(DB::raw('SUM(CASE WHEN hal.event = "chat_post" THEN 1 ELSE 0 END)'), '>=', 1)
            ->get();
    }

    private function queryChatSessions(?Carbon $since, Carbon $logCutoff, ?int $sectorId): Collection
    {
        return DB::table('chat_messages as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->select([
                'cm.user_id',
                'u.name as user_name',
                DB::raw('MAX(NULLIF(cm.sector_id, 0)) as sector_id'),
                DB::raw('MAX(cm.sector_name) as sector_name'),
                DB::raw(ShiftService::shiftSqlExpr('cm.created_at').' as shift'),
                DB::raw(ShiftService::shiftDateSqlExpr('cm.created_at').' as shift_date'),
                DB::raw('COUNT(DISTINCT cm.nr_atendimento) as beds_visited'),
                DB::raw('MIN(cm.created_at) as started_at'),
                DB::raw('TIMESTAMPDIFF(SECOND, MIN(cm.created_at), MAX(cm.created_at)) as duration_seconds'),
                DB::raw('COUNT(*) as messages_written'),
                DB::raw('"chat" as source'),
            ])
            ->when($since, fn ($q) => $q->where('cm.created_at', '>=', $since))
            ->whereRaw(ShiftService::shiftDateSqlExpr('cm.created_at').' < ?', [$logCutoff->toDateString()])
            ->when($sectorId, fn ($q) => $q->where('cm.sector_id', $sectorId))
            ->groupBy('cm.user_id', 'shift', 'shift_date', 'u.name')
            ->having('messages_written', '>=', 1)
            ->get();
    }

    /**
     * Taxa de continuidade: pacientes que receberam anotações em ≥2 turnos distintos
     * sobre o total de pacientes anotados. Proxy para transferência contínua do cuidado.
     */
    public function getContinuityStats(?Carbon $since, ?int $sectorId = null): array
    {
        // Combina chat_messages (recente) + nurse_archive_sessions (histórico)
        // para contar turnos distintos por paciente.
        // Archive não tem nr_atendimento por sessão — usa proxy: plantonistas únicos por (setor, shift, data)
        // A continuidade real de PACIENTE só vem de chat_messages.

        $base = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->whereNotNull('nr_atendimento')
            ->selectRaw(
                'nr_atendimento, '.
                'COUNT(DISTINCT '.ShiftService::shiftSqlExpr('created_at').') as shift_count, '.
                'COUNT(*) as msg_count, '.
                'COUNT(DISTINCT user_id) as nurse_count'
            )
            ->groupBy('nr_atendimento')
            ->get();

        $total = $base->count();
        if ($total === 0) {
            return ['rate' => null, 'total_patients' => 0, 'continuous_patients' => 0, 'avg_shifts_per_patient' => null];
        }

        $continuous = $base->where('shift_count', '>=', 2)->count();
        $avgShifts = round($base->avg('shift_count'), 1);

        // Complemento do archive: total de sessões históricas (proxy de atividade, não de paciente)
        $archiveSessions = DB::table('nurse_archive_sessions')
            ->when($since, fn ($q) => $q->where('shift_date', '>=', $since->toDateString()))
            ->when(! $sectorId, fn ($q) => $q) // archive sem sector_id confiável
            ->count();

        return [
            'rate' => (int) round($continuous / $total * 100),
            'total_patients' => $total,
            'continuous_patients' => $continuous,
            'avg_shifts_per_patient' => $avgShifts,
            'archive_sessions' => $archiveSessions, // contexto histórico adicional
        ];
    }

    /**
     * Classifica mensagens em categorias clínicas via regex (sem custo de IA).
     * Mescla dados live (chat_messages) com histórico arquivado (chat_analytics_daily).
     */
    public function getContentClassification(?Carbon $since, ?int $sectorId = null): array
    {
        $messages = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->pluck('content');

        $counts = ['pendência' => 0, 'risco' => 0, 'conduta/procedimento' => 0, 'alta/evolução' => 0, 'condição clínica' => 0];

        foreach ($messages as $m) {
            $counts[ChatAnalyticsService::classifyContent($m)]++;
        }

        // Mescla dados do archive
        $archiveTags = app(ChatAnalyticsService::class)->getContentTags($since, $sectorId);
        foreach ($archiveTags as $tag => $count) {
            if (array_key_exists($tag, $counts)) {
                $counts[$tag] += $count;
            }
        }

        $total = array_sum($counts);
        if ($total === 0) {
            return [];
        }

        $result = [];
        foreach ($counts as $cat => $cnt) {
            if ($cnt > 0) {
                $result[$cat] = ['count' => $cnt, 'pct' => (int) round($cnt / $total * 100)];
            }
        }

        arsort($result);

        return $result;
    }

    /**
     * Extrai pendências frequentes: "aguardando X", "pendente X", "a coletar X", etc.
     * Agrupa variantes pelo stem PT-BR; exibe a forma original mais frequente.
     */
    public function getTopPendings(?Carbon $since, ?int $sectorId = null): array
    {
        $messages = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->pluck('content');

        // stem → ['display' => most-frequent original phrase, 'count' => total]
        $groups = [];
        foreach ($messages as $m) {
            $lower = mb_strtolower($m);
            preg_match_all(
                '/(?:aguardando|aguarda|pendente|a aguardar|a coletar|a solicitar|para coletar|para fazer|a realizar)\s+([^\n.,;]{5,60})/ui',
                $lower,
                $matches
            );
            foreach ($matches[1] as $match) {
                $clean = trim(preg_replace('/\s+/', ' ', $match));
                // Remove trailing filler words
                $clean = (string) preg_replace('/\s+(para|com|e|de|do|da|o|a)$/u', '', $clean);
                if (mb_strlen($clean) < 5 || mb_strlen($clean) > 55) {
                    continue;
                }
                $stem = $this->stemPortuguese($clean);
                if (! isset($groups[$stem])) {
                    $groups[$stem] = ['display' => $clean, 'count' => 0, 'freq' => []];
                }
                $groups[$stem]['count']++;
                $groups[$stem]['freq'][$clean] = ($groups[$stem]['freq'][$clean] ?? 0) + 1;
                // Keep the most-seen original form as display label
                arsort($groups[$stem]['freq']);
                $groups[$stem]['display'] = array_key_first($groups[$stem]['freq']);
            }
        }

        // Convert to display => count, then Levenshtein-group and sort
        $flat = [];
        foreach ($groups as $data) {
            $flat[$data['display']] = $data['count'];
        }

        arsort($flat);
        $flat = $this->levenshteinGroup($flat);
        arsort($flat);

        return array_slice($flat, 0, 15, true);
    }

    /**
     * Média de caracteres por mensagem para um usuário. Retorna 0 se sem dados.
     */
    public function getAvgCharsForUser(int $userId, ?Carbon $since, ?int $sectorId = null): int
    {
        $live = DB::table('chat_messages')
            ->where('user_id', $userId)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->selectRaw('SUM(CHAR_LENGTH(content)) as total_chars, COUNT(*) as msg_count')
            ->first();

        $analytics = DB::table('chat_analytics_daily')
            ->where('user_id', $userId)
            ->when($since, fn ($q) => $q->where('date', '>=', $since->toDateString()))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->selectRaw('SUM(total_chars) as total_chars, SUM(message_count) as msg_count')
            ->first();

        $totalChars = (int) ($live->total_chars ?? 0) + (int) ($analytics->total_chars ?? 0);
        $totalMsgs = (int) ($live->msg_count ?? 0) + (int) ($analytics->msg_count ?? 0);

        return $totalMsgs > 0 ? (int) round($totalChars / $totalMsgs) : 0;
    }

    /**
     * Classifica média de caracteres em label descritivo.
     */
    public function messageSizeLabel(int $avgChars): string
    {
        return match (true) {
            $avgChars < 60 => 'notas curtas',
            $avgChars <= 200 => 'tamanho adequado',
            default => 'notas longas',
        };
    }

    /**
     * Termos clínicos mais relevantes via TF-IDF (unigramas + bigramas).
     * TF-IDF penaliza termos ubíquos ("dieta", "leito") que aparecem em quase toda mensagem,
     * surfaçando termos clinicamente distintivos no período/setor/usuário filtrado.
     */
    public function getTopTerms(?Carbon $since, ?int $sectorId = null, ?int $userId = null): array
    {
        $stopwords = array_flip([
            'de', 'do', 'da', 'dos', 'das', 'em', 'na', 'no', 'nas', 'nos', 'com', 'para', 'por',
            'que', 'e', 'a', 'o', 'as', 'os', 'se', 'um', 'uma', 'ao', 'pelo', 'pela',
            'foi', 'esta', 'sem', 'mais', 'apos', 'ate', 'ou', 'como', 'seu', 'sua', 'seus', 'suas',
            'este', 'esse', 'isso', 'nao', 'sim', 'mas', 'pois', 'quando', 'onde', 'qual',
            'sendo', 'tendo', 'fazer', 'feito', 'feita', 'tambem', 'sempre', 'ainda',
            'paciente', 'pelo', 'pela',
        ]);

        // ── Coleta documentos (uma mensagem = um documento) ───────────────────
        $documents = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->pluck('content')
            ->map(fn ($c) => (string) $c)
            ->all();

        if (! $userId) {
            $sectorName = $sectorId
                ? DB::table('chat_messages')->where('sector_id', $sectorId)->whereNotNull('sector_name')->value('sector_name')
                : null;

            $archiveRows = DB::table('chat_messages_archive')
                ->when($since, fn ($q) => $q->where('last_message_at', '>=', $since))
                ->when($sectorName, fn ($q) => $q->where('sector_name', $sectorName))
                ->orderByDesc('last_message_at')
                ->limit(300)
                ->pluck('payload');

            foreach ($archiveRows as $payload) {
                try {
                    $decoded = json_decode((string) gzuncompress((string) base64_decode($payload)), true);
                    $msgs = is_array($decoded['messages'] ?? null) ? $decoded['messages'] : (is_array($decoded) ? $decoded : []);
                    foreach ($msgs as $msg) {
                        if (! empty($msg['m'])) {
                            $documents[] = (string) $msg['m'];
                        }
                    }
                } catch (\Throwable) {
                    // skip corrupt blobs
                }
            }
        }

        if (empty($documents)) {
            return [];
        }

        $N = count($documents);

        // ── Tokeniza cada documento em termos normalizados ────────────────────
        $tokenizedDocs = [];

        foreach ($documents as $msg) {
            $words = preg_split('/[\s\/\\\\\-\+\(\),;:.!?\[\]#@*]+/u', mb_strtolower($msg));
            $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 4
                && ! isset($stopwords[$w])
                && ! is_numeric($w)
                && ! preg_match('/^\d/', $w)
            ));

            $terms = [];
            foreach ($words as $i => $w) {
                $terms[] = $this->normalizeForGrouping($w).'|'.$w; // normalized|original
                if (isset($words[$i + 1])) {
                    $n1 = $this->normalizeForGrouping($w);
                    $n2 = $this->normalizeForGrouping($words[$i + 1]);
                    $terms[] = $n1.' '.$n2.'|'.$w.' '.$words[$i + 1];
                }
            }

            $tokenizedDocs[] = $terms;
        }

        // ── DF: quantos documentos contêm cada termo normalizado ──────────────
        $df = [];
        foreach ($tokenizedDocs as $docTerms) {
            $seen = [];
            foreach ($docTerms as $t) {
                $norm = explode('|', $t, 2)[0];
                if (! isset($seen[$norm])) {
                    $seen[$norm] = true;
                    $df[$norm] = ($df[$norm] ?? 0) + 1;
                }
            }
        }

        // ── TF-IDF por documento, acumula score e rastreia forma original ─────
        $scores = [];
        $forms = [];

        foreach ($tokenizedDocs as $docTerms) {
            $total = count($docTerms);
            if ($total === 0) {
                continue;
            }

            // TF dentro do documento
            $tf = [];
            foreach ($docTerms as $t) {
                [$norm, $orig] = explode('|', $t, 2);
                $tf[$norm]['count'] = ($tf[$norm]['count'] ?? 0) + 1;
                $tf[$norm]['orig'][$orig] = ($tf[$norm]['orig'][$orig] ?? 0) + 1;
            }

            foreach ($tf as $norm => $data) {
                $termTf = $data['count'] / $total;
                // IDF com suavização +1 para evitar log(0)
                $termIdf = log($N / (($df[$norm] ?? 1) + 1)) + 1;
                $tfidf = $termTf * $termIdf;

                $scores[$norm] = ($scores[$norm] ?? 0.0) + $tfidf;
                foreach ($data['orig'] as $orig => $cnt) {
                    $forms[$norm][$orig] = ($forms[$norm][$orig] ?? 0) + $cnt;
                }
            }
        }

        // ── Monta resultado: display + df (para sizing) + tfidf (para ranking) ──
        $result = [];
        foreach ($scores as $norm => $tfidf) {
            if (! isset($forms[$norm])) {
                continue;
            }
            arsort($forms[$norm]);
            $display = array_key_first($forms[$norm]);
            $result[$display] = [
                'tfidf' => $tfidf,
                'df' => $df[$norm] ?? 0, // nº de mensagens que contêm o termo
            ];
        }

        // Ordena por TF-IDF descendente
        uasort($result, fn ($a, $b) => $b['tfidf'] <=> $a['tfidf']);

        // ── Suprime unigramas cobertos por bigramas de alta pontuação ─────────
        // Pega os top 80 antes de filtrar para ter margem de supressão
        $candidates = array_slice($result, 0, 80, true);

        $bigramWords = [];
        foreach (array_keys($candidates) as $term) {
            if (str_contains($term, ' ')) {
                foreach (explode(' ', $term) as $word) {
                    $bigramWords[$word] = true;
                }
            }
        }

        $final = [];
        foreach ($candidates as $term => $data) {
            // Suprime unigrama se algum bigrama do top já cobre esta palavra
            if (! str_contains($term, ' ') && isset($bigramWords[$term])) {
                continue;
            }
            $final[$term] = $data['df']; // bubble size = nº de mensagens
        }

        return array_slice($final, 0, 50, true);
    }

    /**
     * Distribuição de tamanho de anotações: curtas / adequadas / longas.
     * Mescla dados live (chat_messages) com histórico arquivado (chat_analytics_daily).
     */
    public function getCharDistribution(?Carbon $since, ?int $sectorId = null): array
    {
        $rows = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->selectRaw('CHAR_LENGTH(content) as len')
            ->get()
            ->pluck('len');

        $buckets = ['curtas' => 0, 'adequadas' => 0, 'longas' => 0];
        $totalChars = 0;

        foreach ($rows as $len) {
            $totalChars += $len;
            if ($len < 60) {
                $buckets['curtas']++;
            } elseif ($len <= 200) {
                $buckets['adequadas']++;
            } else {
                $buckets['longas']++;
            }
        }

        // Mescla dados do archive
        $archiveData = app(ChatAnalyticsService::class)->getCharBuckets($since, $sectorId);
        $buckets['curtas'] += $archiveData['curtas'];
        $buckets['adequadas'] += $archiveData['adequadas'];
        $buckets['longas'] += $archiveData['longas'];
        $totalChars += $archiveData['total_chars'];
        $total = $rows->count() + $archiveData['message_count'];

        if ($total === 0) {
            return [];
        }

        $avg = (int) round($totalChars / $total);

        return [
            'total' => $total,
            'total_chars' => $totalChars,
            'avg' => $avg,
            'size_label' => $this->messageSizeLabel($avg),
            'buckets' => array_map(
                fn ($cnt) => ['count' => $cnt, 'pct' => (int) round($cnt / $total * 100)],
                $buckets
            ),
        ];
    }

    /**
     * Normalização para agrupamento: remove acentos + unifica plurais simples.
     * NÃO remove sufixos verbais — preserva "medicamento", "avaliação", "procedimento".
     * Usada apenas como chave de agrupamento; a exibição usa a forma original.
     */
    private function normalizeForGrouping(string $word): string
    {
        $map = ['ã' => 'a', 'â' => 'a', 'á' => 'a', 'à' => 'a', 'ê' => 'e', 'é' => 'e',
            'í' => 'i', 'ô' => 'o', 'ó' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c'];
        $n = strtr($word, $map);

        // Unifica plurais: -ões/-oes → -ao, -ães/-aes → -ao
        $n = (string) preg_replace('/o[e]?s$/', 'ao', $n);
        $n = (string) preg_replace('/a[e]?s$/', 'ao', $n);

        // Plural simples -s (palavras > 5 chars, não terminadas em -ss)
        if (mb_strlen($n) > 5 && str_ends_with($n, 's') && ! str_ends_with($n, 'ss')) {
            $n = mb_substr($n, 0, -1);
        }

        return $n;
    }

    /**
     * Agrupa chaves próximas (Levenshtein ≤ 2) somando suas contagens.
     * Opera apenas nos top 80 termos para manter O(n²) limitado (~3160 comparações).
     */
    private function levenshteinGroup(array $freq): array
    {
        arsort($freq);

        // Separa candidatos (top 80, count ≥ 2) dos demais — os demais passam direto
        $candidates = array_slice(array_filter($freq, fn ($c) => $c >= 2), 0, 80, true);
        $rest = array_diff_key($freq, $candidates);

        $keys = array_keys($candidates);
        $total = count($keys);
        $merged = [];
        $skip = [];

        for ($i = 0; $i < $total; $i++) {
            $key = $keys[$i];
            if (isset($skip[$key])) {
                continue;
            }
            $merged[$key] = $candidates[$key];

            $keyLen = mb_strlen($key);
            for ($j = $i + 1; $j < $total; $j++) {
                $other = $keys[$j];
                if (isset($skip[$other])) {
                    continue;
                }
                if (abs($keyLen - mb_strlen($other)) > 3) {
                    continue;
                }
                if (levenshtein($key, $other) <= 2) {
                    $merged[$key] += $candidates[$other];
                    $skip[$other] = true;
                }
            }
        }

        return array_merge($merged, $rest);
    }

    private function queryArchiveSessions(?Carbon $since, Carbon $logCutoff, ?int $sectorId): Collection
    {
        // Sessões do archive: apenas antes do logCutoff e, se since for null, tudo histórico.
        // Sem sector_id no archive — sectorFilter não se aplica.
        if ($sectorId !== null) {
            return collect(); // archive não tem sector_id confiável
        }

        return DB::table('nurse_archive_sessions as nas')
            ->join('users as u', 'u.id', '=', 'nas.user_id')
            ->select([
                'nas.user_id',
                'u.name as user_name',
                DB::raw('NULL as sector_id'),
                'nas.sector_name',
                'nas.shift',
                'nas.shift_date',
                'nas.beds_visited',
                'nas.started_at',
                // Estimativa reversa: 3 min/mensagem, mínimo 5 min, máximo 90 min
                DB::raw('LEAST(5400, GREATEST(300, nas.message_count * 180)) as duration_seconds'),
                'nas.message_count as messages_written',
                DB::raw('"archive" as source'),
            ])
            ->when($since, fn ($q) => $q->where('nas.shift_date', '>=', $since->toDateString()))
            ->where('nas.shift_date', '<', $logCutoff->toDateString())
            ->get();
    }

    private function normalizeSession(array|object $s): array
    {
        $s = (object) $s;
        $startedAt = Carbon::parse($s->started_at);
        $durationSeconds = (int) ($s->duration_seconds ?? 0);
        $source = $s->source ?? 'chat';
        $bedsVisited = (int) $s->beds_visited;

        return [
            'user_id' => (int) $s->user_id,
            'user_name' => $s->user_name,
            'sector_id' => $s->sector_id,
            'sector_name' => $s->sector_name ?? '—',
            'shift' => $s->shift,
            'shift_label' => self::SHIFT_LABELS[$s->shift] ?? $s->shift,
            'shift_date' => $s->shift_date,
            'beds_visited' => $bedsVisited > 0 ? $bedsVisited : null,
            'messages_written' => (int) $s->messages_written,
            'started_at' => $s->started_at,
            'started_at_formatted' => $startedAt->format('d/m/Y H:i'),
            'duration_min' => ($durationSeconds > 0 && $durationSeconds <= self::MAX_SESSION_DURATION_SECONDS)
                ? round($durationSeconds / 60, 1)
                : null,
            'duration_estimated' => $source === 'archive',
            'source' => $source,
        ];
    }
}
