<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Grava e lê agregados analíticos de mensagens de chat.
 *
 * Fluxo de escrita: CleanupOldChatData chama record() antes de mover mensagens para o blob de archive.
 * Fluxo de leitura: MetricsController e HandoverSessionService mesclam dados de chat_analytics_daily com queries live.
 *
 * Sem sector_id no archive blob → linhas do archive ficam com sector_id = NULL.
 * Queries com sectorFilter ignoram linhas NULL automaticamente via ->when($sectorId, ...).
 */
class ChatAnalyticsService
{
    /**
     * Scoring-based multi-keyword classification.
     * Conta matches de cada categoria; ganha a de maior pontuação.
     * Termos mais específicos recebem peso 2 (prefixo "2:").
     *
     * @var array<string, list<string>>
     */
    private const CONTENT_KEYWORDS = [
        'pendência' => [
            '2:aguardando resultado', '2:aguardando laudo', '2:aguardando avaliação',
            '2:não realizado', '2:não coletado', '2:a solicitar', '2:a coletar',
            '2:sem resultado', '2:sem retorno', '2:pendências',
            'aguardando', 'pendente', 'aguarda', 'aguardar', 'falta', 'ainda não',
            'a verificar', 'a checar', 'a confirmar', 'a comunicar', 'a fazer',
            'necessita', 'precisa ser', 'não foi', 'solicitar',
        ],
        'risco' => [
            '2:risco de queda', '2:desconforto respiratório', '2:piora clínica',
            '2:rebaixamento de consciência', '2:rebaixou nível', '2:nível de consciência',
            '2:dor intensa', '2:dessaturação', '2:dessatura',
            'queda', 'confuso', 'confusa', 'agitado', 'agitada', 'desorientado',
            'desorientada', 'contido', 'contida', 'contenção', 'risco', 'alerta',
            'hipotensão', 'hipertensão', 'taquicardia', 'bradicardia', 'febre',
            'febril', 'instável', 'instavel', 'crítico', 'critico', 'piora',
            'deteriorou', 'deterioração', 'rebaixamento', 'crise', 'convulsão',
            'convulsionando', 'sepse', 'choque', 'sangramento', 'hemorragia',
            'dispneia', 'rebaixou',
        ],
        'alta/evolução' => [
            '2:previsão de alta', '2:em processo de alta', '2:alta prevista',
            '2:alta médica', '2:alta hospitalar', '2:alta concedida',
            '2:aguardando alta', '2:liberado para alta', '2:liberada para alta',
            'de alta', 'transferência', 'transferido', 'transferida', 'transferir',
            'liberado', 'liberada',
        ],
        'conduta/procedimento' => [
            '2:curativo realizado', '2:sondagem realizada', '2:cateter inserido',
            '2:acesso obtido', '2:bolsa trocada', '2:higiene realizada',
            '2:banho realizado', '2:mobilização realizada',
            'realizado', 'realizada', 'feito', 'feita', 'administrado', 'administrada',
            'curativo', 'coletado', 'coletada', 'instalado', 'instalada',
            'retirado', 'retirada', 'trocado', 'trocada', 'passado', 'passada',
            'puncionado', 'puncionada', 'medicado', 'medicada', 'aplicado', 'aplicada',
            'aspirado', 'aspiração', 'drenagem', 'sondado', 'sondagem',
            'fixado', 'fixada', 'posicionado', 'decúbito', 'higiene', 'mobilização',
        ],
        'condição clínica' => [
            '2:sem intercorrências', '2:sem queixas', '2:estável hemodinâmico',
            '2:dor controlada', '2:glasgow', '2:spo2', '2:salinizado',
            'estável', 'estavel', 'consciente', 'orientado', 'orientada', 'sonolento',
            'sonolenta', 'diurese', 'evacuou', 'dieta', 'aceitou', 'recusou',
            'dor', 'confortável', 'confortavel', 'dormindo', 'repouso',
            'oxigênio', 'oxigenio', 'respirando', 'deambulando', 'deambula',
            'hidratado', 'hidratação', 'venóclise', 'ventilação', 'saturação',
            'pressão arterial', 'pulso', 'temperatura', 'tax', 'sinais vitais',
        ],
    ];

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Registra analytics para um conjunto de mensagens de chat_messages.
     * Agrupa por (shift_date, sector_id, user_id, shift) e faz upsert.
     *
     * @param  Collection<int, object>  $messages  linhas de chat_messages com campos:
     *                                             created_at, user_id, content, sector_id, sector_name
     */
    public function record(Collection $messages): void
    {
        if ($messages->isEmpty()) {
            return;
        }

        $groups = [];

        foreach ($messages as $m) {
            $dt = Carbon::parse($m->created_at);
            $minutes = $dt->hour * 60 + $dt->minute;
            $shift = ShiftService::shiftFromMinutes($minutes);
            $shiftDate = ShiftService::shiftDateFromTimestamp($dt->timestamp);

            $groupKey = $shiftDate.'|'.($m->sector_id ?? '').'|'.($m->user_id ?? '').'|'.$shift;

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'date' => $shiftDate,
                    'sector_id' => $m->sector_id ?? null,
                    'sector_name' => $m->sector_name ?? null,
                    'user_id' => $m->user_id ?? null,
                    'shift' => $shift,
                    'message_count' => 0,
                    'total_chars' => 0,
                    'char_buckets' => ['curtas' => 0, 'adequadas' => 0, 'longas' => 0],
                    'content_tags' => ['pendência' => 0, 'risco' => 0, 'conduta/procedimento' => 0, 'alta/evolução' => 0, 'condição clínica' => 0],
                    'hour_counts' => [],
                ];
            }

            $len = mb_strlen($m->content ?? '');
            $hourKey = (string) $dt->hour;

            $groups[$groupKey]['message_count']++;
            $groups[$groupKey]['total_chars'] += $len;
            $groups[$groupKey]['char_buckets'][$len < 60 ? 'curtas' : ($len <= 200 ? 'adequadas' : 'longas')]++;
            $groups[$groupKey]['hour_counts'][$hourKey] = ($groups[$groupKey]['hour_counts'][$hourKey] ?? 0) + 1;
            $groups[$groupKey]['content_tags'][self::classifyContent($m->content ?? '')]++;
        }

        foreach ($groups as $data) {
            $this->upsertRow($data);
        }
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Retorna [hour => count] de mensagens arquivadas.
     * Panorama::heatmap() mescla isso com a query live de chat_messages.
     *
     * @return array<int, int>
     */
    public function getHourCounts(?Carbon $since, ?int $sectorId): array
    {
        $rows = DB::table('chat_analytics_daily')
            ->when($since, fn ($q) => $q->where('date', '>=', $since->toDateString()))
            ->when($sectorId, fn ($q) => $this->applySectorFilter($q, $sectorId))
            ->whereNotNull('hour_counts')
            ->select('hour_counts')
            ->get();

        $merged = [];
        foreach ($rows as $row) {
            $counts = json_decode($row->hour_counts, true) ?? [];
            foreach ($counts as $h => $c) {
                $merged[(int) $h] = ($merged[(int) $h] ?? 0) + (int) $c;
            }
        }

        return $merged;
    }

    /**
     * Retorna dados de distribuição de caracteres agregados do archive.
     *
     * @return array{curtas:int, adequadas:int, longas:int, total_chars:int, message_count:int}
     */
    public function getCharBuckets(?Carbon $since, ?int $sectorId): array
    {
        $rows = DB::table('chat_analytics_daily')
            ->when($since, fn ($q) => $q->where('date', '>=', $since->toDateString()))
            ->when($sectorId, fn ($q) => $this->applySectorFilter($q, $sectorId))
            ->selectRaw('SUM(message_count) as total_msgs, SUM(total_chars) as total_ch, char_buckets')
            ->groupBy('char_buckets')
            ->get();

        $result = ['curtas' => 0, 'adequadas' => 0, 'longas' => 0, 'total_chars' => 0, 'message_count' => 0];

        foreach ($rows as $row) {
            $buckets = json_decode($row->char_buckets ?? '{}', true) ?? [];
            $result['curtas'] += (int) ($buckets['curtas'] ?? 0);
            $result['adequadas'] += (int) ($buckets['adequadas'] ?? 0);
            $result['longas'] += (int) ($buckets['longas'] ?? 0);
            $result['total_chars'] += (int) $row->total_ch;
            $result['message_count'] += (int) $row->total_msgs;
        }

        return $result;
    }

    /**
     * Retorna [tag => count] de mensagens arquivadas.
     * HandoverSessionService::getContentClassification() mescla com dados live.
     *
     * @return array<string, int>
     */
    public function getContentTags(?Carbon $since, ?int $sectorId): array
    {
        $rows = DB::table('chat_analytics_daily')
            ->when($since, fn ($q) => $q->where('date', '>=', $since->toDateString()))
            ->when($sectorId, fn ($q) => $this->applySectorFilter($q, $sectorId))
            ->whereNotNull('content_tags')
            ->select('content_tags')
            ->get();

        $merged = [];
        foreach ($rows as $row) {
            $tags = json_decode($row->content_tags, true) ?? [];
            foreach ($tags as $tag => $count) {
                $merged[$tag] = ($merged[$tag] ?? 0) + (int) $count;
            }
        }

        return $merged;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Filtra analytics por setor incluindo linhas históricas (sector_id = NULL).
     * Linhas de arquivo não têm sector_id; usam sector_name para correlação.
     * Sem este OR, filtros de setor excluem silenciosamente todo o histórico arquivado.
     */
    private function applySectorFilter(Builder $q, int $sectorId): Builder
    {
        $sectorName = DB::table('chat_messages')
            ->where('sector_id', $sectorId)
            ->whereNotNull('sector_name')
            ->value('sector_name');

        return $q->where(function ($sub) use ($sectorId, $sectorName) {
            $sub->where('sector_id', $sectorId);
            if ($sectorName) {
                $sub->orWhere('sector_name', $sectorName);
            }
        });
    }

    /**
     * Classifica mensagem por scoring de keywords.
     * Conta matches de todos os termos de cada categoria (termos "2:" valem 2 pontos).
     * Ganha a categoria com maior pontuação; empate → condição clínica.
     */
    public static function classifyContent(string $content): string
    {
        $lower = mb_strtolower($content);
        $scores = [];

        foreach (self::CONTENT_KEYWORDS as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                $weight = 1;
                if (str_starts_with($kw, '2:')) {
                    $weight = 2;
                    $kw = substr($kw, 2);
                }
                if (str_contains($lower, $kw)) {
                    $score += $weight;
                }
            }
            $scores[$category] = $score;
        }

        $best = array_filter($scores, fn ($s) => $s > 0);
        if (empty($best)) {
            return 'condição clínica';
        }

        arsort($best);

        return array_key_first($best);
    }

    /**
     * Upsert de uma linha de analytics. Se existir, mescla JSON em PHP; se não, insere.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertRow(array $data): void
    {
        $existing = DB::table('chat_analytics_daily')
            ->where('date', $data['date'])
            ->where('shift', $data['shift'])
            ->where('sector_id', $data['sector_id'])
            ->where('user_id', $data['user_id'])
            ->first();

        if ($existing) {
            $existingHours = json_decode($existing->hour_counts ?? '{}', true) ?: [];
            $existingBuckets = json_decode($existing->char_buckets ?? '{}', true) ?: ['curtas' => 0, 'adequadas' => 0, 'longas' => 0];
            $existingTags = json_decode($existing->content_tags ?? '{}', true) ?: [];

            foreach ($data['hour_counts'] as $h => $c) {
                $existingHours[$h] = ($existingHours[$h] ?? 0) + $c;
            }
            foreach ($data['char_buckets'] as $b => $c) {
                $existingBuckets[$b] = ($existingBuckets[$b] ?? 0) + $c;
            }
            foreach ($data['content_tags'] as $t => $c) {
                $existingTags[$t] = ($existingTags[$t] ?? 0) + $c;
            }

            DB::table('chat_analytics_daily')
                ->where('id', $existing->id)
                ->update([
                    'message_count' => $existing->message_count + $data['message_count'],
                    'total_chars' => $existing->total_chars + $data['total_chars'],
                    'hour_counts' => json_encode($existingHours),
                    'char_buckets' => json_encode($existingBuckets),
                    'content_tags' => json_encode($existingTags),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('chat_analytics_daily')->insert([
                'date' => $data['date'],
                'sector_id' => $data['sector_id'],
                'sector_name' => $data['sector_name'],
                'user_id' => $data['user_id'],
                'shift' => $data['shift'],
                'message_count' => $data['message_count'],
                'total_chars' => $data['total_chars'],
                'hour_counts' => json_encode($data['hour_counts']),
                'char_buckets' => json_encode($data['char_buckets']),
                'content_tags' => json_encode($data['content_tags']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
