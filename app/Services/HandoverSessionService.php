<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HandoverSessionService
{
    private const SHIFT_LABELS = ['M' => 'Manhã', 'T' => 'Tarde', 'N' => 'Noite'];

    private const MAX_SESSION_DURATION_SECONDS = 10800; // 3h — acima disso: anotação contínua, não passagem concentrada

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
     * Retorna distribuição percentual das categorias.
     */
    public function getContentClassification(?Carbon $since, ?int $sectorId = null): array
    {
        $messages = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->pluck('content');

        if ($messages->isEmpty()) {
            return [];
        }

        $counts = ['pendência' => 0, 'risco' => 0, 'conduta/procedimento' => 0, 'alta/evolução' => 0, 'condição clínica' => 0];

        foreach ($messages as $m) {
            $lower = mb_strtolower($m);

            if (preg_match('/aguardando|pendente|aguarda|a solicitar|a coletar|a fazer|sem resultado|não colet/u', $lower)) {
                $counts['pendência']++;
            } elseif (preg_match('/queda|confuso|confusa|agitad|dor intensa|dessatura|risco|cuidado|atenção|alerta/u', $lower)) {
                $counts['risco']++;
            } elseif (preg_match('/alta|previsão de alta|em processo de alta|liberado|de alta/u', $lower)) {
                $counts['alta/evolução']++;
            } elseif (preg_match('/realizado|feito|administrad|curativo|coletado|instalad|retirad|trocad|passado|feita/u', $lower)) {
                $counts['conduta/procedimento']++;
            } else {
                $counts['condição clínica']++;
            }
        }

        $total = $messages->count();
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
     * Extrai pendências frequentes por regex: "aguardando X", "pendente X", etc.
     * Retorna as mais comuns com contagem.
     */
    public function getTopPendings(?Carbon $since, ?int $sectorId = null): array
    {
        $messages = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->pluck('content');

        $patterns = [];
        foreach ($messages as $m) {
            $lower = mb_strtolower($m);
            preg_match_all(
                '/(?:aguardando|aguarda|pendente|a aguardar|sem |falta )\s*([^\n.,;]{5,50})/ui',
                $lower,
                $matches
            );
            foreach ($matches[1] as $match) {
                $clean = trim(preg_replace('/\s+/', ' ', $match));
                if (mb_strlen($clean) >= 5) {
                    $patterns[$clean] = ($patterns[$clean] ?? 0) + 1;
                }
            }
        }

        arsort($patterns);

        return array_slice($patterns, 0, 15, true);
    }

    /**
     * Termos clínicos mais frequentes (bigramas + unigramas relevantes).
     */
    public function getTopTerms(?Carbon $since, ?int $sectorId = null): array
    {
        $stopwords = ['de', 'do', 'da', 'dos', 'das', 'em', 'na', 'no', 'nas', 'nos', 'com', 'para', 'por', 'que', 'e', 'a', 'o', 'as', 'os',
            'se', 'um', 'uma', 'ao', 'à', 'pelo', 'pela', 'foi', 'está', 'sem', 'mais', 'após', 'até', 'ou', 'como', 'seu', 'sua'];

        $messages = DB::table('chat_messages')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->when($sectorId, fn ($q) => $q->where('sector_id', $sectorId))
            ->pluck('content');

        $freq = [];
        foreach ($messages as $m) {
            $words = preg_split('/[\s\/\-\+\(\),;:.!?]+/u', mb_strtolower($m));
            $words = array_filter($words, fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, $stopwords) && ! is_numeric($w));
            $words = array_values($words);

            foreach ($words as $i => $w) {
                $freq[$w] = ($freq[$w] ?? 0) + 1;
                // bigrama
                if (isset($words[$i + 1])) {
                    $bigram = $w.' '.$words[$i + 1];
                    $freq[$bigram] = ($freq[$bigram] ?? 0) + 1;
                }
            }
        }

        arsort($freq);

        return array_slice($freq, 0, 20, true);
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
                DB::raw('0 as duration_seconds'),
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

        return [
            'user_id' => (int) $s->user_id,
            'user_name' => $s->user_name,
            'sector_id' => $s->sector_id,
            'sector_name' => $s->sector_name ?? '—',
            'shift' => $s->shift,
            'shift_label' => self::SHIFT_LABELS[$s->shift] ?? $s->shift,
            'shift_date' => $s->shift_date,
            'beds_visited' => (int) $s->beds_visited,
            'messages_written' => (int) $s->messages_written,
            'started_at' => $s->started_at,
            'started_at_formatted' => $startedAt->format('d/m/Y H:i'),
            'duration_min' => ($durationSeconds > 0 && $durationSeconds <= self::MAX_SESSION_DURATION_SECONDS)
                ? round($durationSeconds / 60, 1)
                : null,
            'source' => $s->source ?? 'chat',
        ];
    }
}
