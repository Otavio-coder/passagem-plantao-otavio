<?php

namespace App\Services\Handover\AiAnalysis;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HandoverAnalysisDatasetService
{
    /**
     * Padrões de enriquecimento clínico aplicados antes da chamada à IA.
     */
    private const ENRICHMENT = [
        'pending_actions'   => '/aguard(a|ando|ou)|pendente|a solicitar|a coletar|necessita|falta(?! de)|sem result/ui',
        'exams'             => '/tomografia|raio[\s\-]?x|ressonância|ressonancia|laborat[oó]rio|cultura|ecografia|ultrassom|hemograma|coagulograma|gasometria|sorologias?/ui',
        'referrals'         => '/parecer|interconsulta|avalia[cç][aã]o m[eé]dica|solicitar especialidade/ui',
        'discharges'        => '/alta (prevista|prov[áa]vel|programada|m[eé]dica)|previs[aã]o de alta|liberado para alta|em processo de alta/ui',
        'recommendations'   => '/monitorar|observar|reavaliar|acompanhar|orientar|manter|verificar|checar|controlar|vigiar/ui',
        'pain'              => '/\bdor\b|doloroso|dolorosa|analgésico|analgesia|escala de dor|EVA\b/ui',
        'fall_risk'         => '/risco de queda|hist[oó]rico de queda|grade|grades levantadas/ui',
        'isolation'         => '/isolamento|isolado|isolada|precau[cç][aã]o|contato direto|contato indireto/ui',
        'wound_care'        => '/curativo|curativo|ferida|les[aã]o|[uú]lcera|lesão por press[aã]o|LPP\b/ui',
        'devices'           => '/cateter|sonda|dreno|acesso venoso|oxig[eê]nio|ventila[cç][aã]o|CPAP|VM\b|TOT\b|CVC\b|FAV\b/ui',
        'generic_messages'  => '/sem intercorrên|sem intercorren|estável\b|estavel\b|sem altera[cç][aã]o|mantém quadro|sem novidades|inalterado/ui',
    ];

    /**
     * Prepara dataset estruturado e enriquecido para uso no prompt de IA.
     * Não chama IA — apenas organiza dados.
     *
     * @return array{
     *   total_messages: int,
     *   total_patients: int,
     *   total_professionals: int,
     *   period: array{start: string, end: string, days: int},
     *   messages_by_sector: list<array{name: string, messages: int, patients: int, professionals: int}>,
     *   messages_by_shift: array{M: int, T: int, N: int},
     *   messages_by_professional: list<array{name: string, messages: int, sessions: int, shifts: string}>,
     *   daily_distribution: list<array{date: string, count: int}>,
     *   enrichment: array<string, array{count: int, pct: float, samples: list<string>}>,
     *   top_terms: array<string, int>,
     *   avg_message_length: int,
     *   message_samples: list<string>
     * }
     */
    public function prepare(Carbon $periodStart, Carbon $periodEnd, ?int $sectorId = null): array
    {
        $messages = DB::table('chat_messages')
            ->where('chat_messages.created_at', '>=', $periodStart->startOfDay())
            ->where('chat_messages.created_at', '<=', $periodEnd->endOfDay())
            ->when($sectorId !== null, fn ($q) => $q->where('sector_id', $sectorId))
            ->join('users', 'users.id', '=', 'chat_messages.user_id')
            ->select([
                'chat_messages.id',
                'chat_messages.content',
                'chat_messages.nr_atendimento',
                'chat_messages.sector_name',
                'chat_messages.sector_id',
                'chat_messages.user_id',
                'chat_messages.created_at',
                'users.name as user_name',
            ])
            ->orderBy('chat_messages.created_at')
            ->get();

        if ($messages->isEmpty()) {
            return $this->emptyDataset($periodStart, $periodEnd);
        }

        $totalMessages = $messages->count();
        $totalPatients = $messages->whereNotNull('nr_atendimento')->pluck('nr_atendimento')->unique()->count();
        $totalProfessionals = $messages->pluck('user_id')->unique()->count();

        // ── Distribuição por setor ─────────────────────────────────────────────
        $bySector = $messages->whereNotNull('sector_name')
            ->groupBy('sector_name')
            ->map(fn ($g) => [
                'name' => $g->first()->sector_name,
                'messages' => $g->count(),
                'patients' => $g->whereNotNull('nr_atendimento')->pluck('nr_atendimento')->unique()->count(),
                'professionals' => $g->pluck('user_id')->unique()->count(),
            ])
            ->sortByDesc('messages')
            ->values()
            ->all();

        // ── Distribuição por turno ─────────────────────────────────────────────
        $byShift = ['M' => 0, 'T' => 0, 'N' => 0];
        foreach ($messages as $msg) {
            $hour = (int) Carbon::parse($msg->created_at)->format('H');
            $minutes = $hour * 60 + (int) Carbon::parse($msg->created_at)->format('i');
            $byShift[$this->shiftFromMinutes($minutes)]++;
        }

        // ── Distribuição por profissional (agregada, sem ranking) ──────────────
        $byProfessional = $messages->groupBy('user_id')
            ->map(function ($g) {
                $shiftsUsed = $g->map(function ($m) {
                    $hour = (int) Carbon::parse($m->created_at)->format('H');
                    $min = $hour * 60 + (int) Carbon::parse($m->created_at)->format('i');

                    return $this->shiftFromMinutes($min);
                })->unique()->sort()->implode('/');

                $sessionCount = $g->groupBy(function ($m) {
                    $hour = (int) Carbon::parse($m->created_at)->format('H');
                    $min = $hour * 60 + (int) Carbon::parse($m->created_at)->format('i');
                    $shift = $this->shiftFromMinutes($min);
                    $date = $min < 7 * 60
                        ? Carbon::parse($m->created_at)->subDay()->toDateString()
                        : Carbon::parse($m->created_at)->toDateString();

                    return $shift.'-'.$date;
                })->count();

                return [
                    'name' => $g->first()->user_name,
                    'messages' => $g->count(),
                    'sessions' => $sessionCount,
                    'shifts' => $shiftsUsed ?: 'N/D',
                ];
            })
            ->sortByDesc('messages')
            ->values()
            ->all();

        // ── Distribuição diária ────────────────────────────────────────────────
        $dailyDist = $messages->groupBy(fn ($m) => Carbon::parse($m->created_at)->toDateString())
            ->map(fn ($g, $date) => ['date' => $date, 'count' => $g->count()])
            ->values()
            ->all();

        // ── Enriquecimento por categorias ──────────────────────────────────────
        $enrichment = $this->enrichMessages($messages->pluck('content')->all());

        // ── Estatísticas de tamanho ────────────────────────────────────────────
        $lengths = $messages->map(fn ($m) => mb_strlen($m->content))->all();
        $avgLength = count($lengths) > 0 ? (int) round(array_sum($lengths) / count($lengths)) : 0;

        // ── Amostras representativas ───────────────────────────────────────────
        $samples = $this->selectRepresentativeSamples($messages->pluck('content')->all(), 50);

        // ── Top terms (reutiliza lógica do HandoverSessionService) ─────────────
        $topTerms = $this->extractTopTerms($messages->pluck('content')->all());

        return [
            'total_messages' => $totalMessages,
            'total_patients' => $totalPatients,
            'total_professionals' => $totalProfessionals,
            'period' => [
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
                'days' => (int) $periodStart->diffInDays($periodEnd) + 1,
            ],
            'messages_by_sector' => $bySector,
            'messages_by_shift' => $byShift,
            'messages_by_professional' => $byProfessional,
            'daily_distribution' => $dailyDist,
            'enrichment' => $enrichment,
            'top_terms' => $topTerms,
            'avg_message_length' => $avgLength,
            'message_samples' => $samples,
        ];
    }

    // ── Enriquecimento ─────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $contents
     * @return array<string, array{count: int, pct: float, samples: list<string>}>
     */
    private function enrichMessages(array $contents): array
    {
        $total = count($contents);
        if ($total === 0) {
            return [];
        }

        $result = [];
        foreach (self::ENRICHMENT as $category => $pattern) {
            $matched = [];
            foreach ($contents as $content) {
                if (preg_match($pattern, $content)) {
                    $matched[] = $content;
                }
            }
            $count = count($matched);
            if ($count === 0) {
                continue;
            }
            // Seleciona até 3 samples únicos e curtos
            $samples = collect($matched)
                ->filter(fn ($m) => mb_strlen($m) < 200)
                ->unique()
                ->take(3)
                ->values()
                ->all();

            $result[$category] = [
                'count' => $count,
                'pct' => round($count / $total * 100, 1),
                'samples' => $samples,
            ];
        }

        return $result;
    }

    /**
     * Seleciona amostras representativas: varia por comprimento e setor.
     *
     * @param  list<string>  $contents
     * @return list<string>
     */
    private function selectRepresentativeSamples(array $contents, int $limit): array
    {
        if (empty($contents)) {
            return [];
        }

        // Filtra mensagens muito curtas ou muito genéricas
        $filtered = array_filter($contents, fn ($c) => mb_strlen($c) >= 20);

        // Embaralha e pega amostras únicas
        shuffle($filtered);

        return array_values(array_unique(array_slice($filtered, 0, $limit)));
    }

    /**
     * Extrai termos mais frequentes (unigrams + bigrams), normalizado PT-BR.
     *
     * @param  list<string>  $contents
     * @return array<string, int>
     */
    private function extractTopTerms(array $contents): array
    {
        $stopwords = ['de', 'do', 'da', 'dos', 'das', 'em', 'na', 'no', 'nas', 'nos', 'com', 'para', 'por',
            'que', 'e', 'a', 'o', 'as', 'os', 'se', 'um', 'uma', 'ao', 'pelo', 'pela', 'foi', 'está',
            'sem', 'mais', 'após', 'até', 'ou', 'como', 'seu', 'sua'];

        $freq = [];
        foreach ($contents as $content) {
            $words = preg_split('/[\s\/\-\+\(\),;:.!?]+/u', mb_strtolower($content)) ?: [];
            $words = array_filter($words, fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, $stopwords) && ! is_numeric($w));
            $words = array_values($words);

            foreach ($words as $i => $w) {
                $freq[$w] = ($freq[$w] ?? 0) + 1;
                if (isset($words[$i + 1])) {
                    $bigram = $w.' '.$words[$i + 1];
                    $freq[$bigram] = ($freq[$bigram] ?? 0) + 1;
                }
            }
        }

        arsort($freq);

        return array_slice($freq, 0, 25, true);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function shiftFromMinutes(int $minutes): string
    {
        return match (true) {
            $minutes >= 7 * 60 && $minutes < 13 * 60 => 'M',
            $minutes >= 13 * 60 && $minutes < 19 * 60 => 'T',
            default => 'N',
        };
    }

    private function emptyDataset(Carbon $start, Carbon $end): array
    {
        return [
            'total_messages' => 0,
            'total_patients' => 0,
            'total_professionals' => 0,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'days' => (int) $start->diffInDays($end) + 1],
            'messages_by_sector' => [],
            'messages_by_shift' => ['M' => 0, 'T' => 0, 'N' => 0],
            'messages_by_professional' => [],
            'daily_distribution' => [],
            'enrichment' => [],
            'top_terms' => [],
            'avg_message_length' => 0,
            'message_samples' => [],
        ];
    }
}
