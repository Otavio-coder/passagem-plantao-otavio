<?php

namespace App\Repositories\EMR;

use App\Services\ShiftService;
use App\Support\Scales\ScaleStyleHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientScalesRepository
{
    protected $connection = 'tasy';

    protected const CHUNK_SIZE = 200;

    private const SCALE_CONFIG = [
        'mews' => ['period' => 'turno', 'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
        'pews' => ['period' => 'turno', 'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
        'braden' => ['period' => '24h',   'val' => 'qt_ponto',      'dt' => 'dt_avaliacao'],
        'morse' => ['period' => '24h',   'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
        'pain' => ['period' => 'turno', 'val' => 'qt_escala_dor', 'dt' => 'dt_sinal_vital'],
        'vte' => ['period' => '24h',   'val' => 'qt_pontuacao',  'dt' => 'dt_avaliacao'],
    ];

    public function getPatientsScalesUnified(array $attendanceNumbers, array $isNewPatientMap = []): array
    {
        if (empty($attendanceNumbers)) {
            return [];
        }

        $result = [];

        foreach (array_chunk(array_values($attendanceNumbers), self::CHUNK_SIZE) as $chunk) {
            try {
                $p = implode(',', array_fill(0, count($chunk), '?'));

                $rows = DB::connection('tasy')->select("
                    SELECT scale_type, nr_atendimento, score_value, dt_ref, rn
                    FROM (
                        SELECT 'mews'   AS scale_type, nr_atendimento, qt_pontuacao  AS score_value, dt_avaliacao   AS dt_ref,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)   AS rn
                        FROM tasy.escala_mews
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                        UNION ALL
                        SELECT 'pews',   nr_atendimento, qt_pontuacao,  dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.escala_pews
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                        UNION ALL
                        SELECT 'braden', nr_atendimento, qt_ponto,      dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.atend_escala_braden
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_ponto IS NOT NULL
                        UNION ALL
                        SELECT 'morse',  nr_atendimento, qt_pontuacao,  dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.escala_morse
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                        UNION ALL
                        SELECT 'pain',   nr_atendimento, qt_escala_dor, dt_sinal_vital,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_sinal_vital DESC)
                        FROM tasy.atendimento_sinal_vital
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_escala_dor IS NOT NULL
                        UNION ALL
                        SELECT 'vte',    nr_atendimento, qt_pontuacao,  dt_avaliacao,
                               ROW_NUMBER() OVER (PARTITION BY nr_atendimento ORDER BY dt_avaliacao DESC)
                        FROM tasy.escala_tev
                        WHERE nr_atendimento IN ({$p}) AND dt_liberacao IS NOT NULL AND dt_inativacao IS NULL AND qt_pontuacao IS NOT NULL
                    )
                    WHERE rn <= 2
                ", array_merge($chunk, $chunk, $chunk, $chunk, $chunk, $chunk));

                $index = [];
                foreach ($rows as $row) {
                    $index[$row->nr_atendimento][$row->scale_type][$row->rn] = $row;
                }

                foreach ($chunk as $attendance) {
                    $isNew = (bool) ($isNewPatientMap[$attendance] ?? false);
                    $scaleData = [];

                    foreach (self::SCALE_CONFIG as $key => $cfg) {
                        $current = $this->toScaleObject($index[$attendance][$key][1] ?? null, $cfg['val'], $cfg['dt']);
                        $previous = $this->toScaleObject($index[$attendance][$key][2] ?? null, $cfg['val'], $cfg['dt']);
                        $scaleData[$key] = $this->buildScaleEntry($key, $current, $previous, $cfg['period'], $isNew);
                    }

                    $result[$attendance] = $scaleData;
                }
            } catch (\Throwable $e) {
                Log::error('PatientScalesRepository::getPatientsScalesUnified failed', [
                    'error' => $e->getMessage(),
                    'chunk_sample' => array_slice($chunk, 0, 5),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Busca sub-scores detalhados de cada escala para exibição no modal do paciente.
     * Chamado apenas para paciente individual (não em batch) — não tem cache próprio,
     * pois é coberto pelo cache de 10 min do TasyService::getSbarPatientDetails().
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDetailedScalesForPatient(int $attendanceNumber): array
    {
        $details = [];

        // MEWS: sub-scores de sinais vitais e nível de consciência
        try {
            $row = DB::connection($this->connection)->selectOne('
                SELECT em.qt_pa_sistolica, em.qt_pa_diastolica, em.qt_freq_cardiaca,
                       em.qt_freq_resp, em.qt_temp, em.ie_nivel_consciencia,
                       NVL(pf.nm_pessoa_fisica, em.nm_usuario) AS nm_avaliador
                FROM tasy.escala_mews em
                LEFT JOIN tasy.pessoa_fisica pf ON pf.cd_pessoa_fisica = em.cd_profissional
                WHERE em.nr_atendimento = :nr
                  AND em.dt_liberacao IS NOT NULL AND em.dt_inativacao IS NULL AND em.qt_pontuacao IS NOT NULL
                ORDER BY em.dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY
            ', ['nr' => $attendanceNumber]);

            if ($row) {
                $conscienciaMap = ['0' => 'Alerta', '1' => 'Confuso', '2' => 'Voz', '3' => 'Dor', '4' => 'Irresponsivo'];
                $details['mews'] = [
                    'pa' => ($row->qt_pa_sistolica && $row->qt_pa_diastolica) ? "{$row->qt_pa_sistolica}/{$row->qt_pa_diastolica} mmHg" : null,
                    'fc' => $row->qt_freq_cardiaca ? "{$row->qt_freq_cardiaca} bpm" : null,
                    'fr' => $row->qt_freq_resp ? "{$row->qt_freq_resp} irpm" : null,
                    'temp' => $row->qt_temp ? "{$row->qt_temp} °C" : null,
                    'consciencia' => $conscienciaMap[(string) ($row->ie_nivel_consciencia ?? '')] ?? null,
                    'avaliador' => $row->nm_avaliador,
                ];
            }
        } catch (\Throwable) {
        }

        // PEWS: consciência, cardiovascular, respiratório
        try {
            $row = DB::connection($this->connection)->selectOne('
                SELECT p.ie_consciencia, p.ie_cardiovascular, p.ie_respiratorio,
                       p.ie_vomito, p.ie_nebulizacao,
                       NVL(pf.nm_pessoa_fisica, p.nm_usuario) AS nm_avaliador
                FROM tasy.escala_pews p
                LEFT JOIN tasy.pessoa_fisica pf ON pf.cd_pessoa_fisica = p.cd_profissional
                WHERE p.nr_atendimento = :nr
                  AND p.dt_liberacao IS NOT NULL AND p.dt_inativacao IS NULL AND p.qt_pontuacao IS NOT NULL
                ORDER BY p.dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY
            ', ['nr' => $attendanceNumber]);

            if ($row) {
                $details['pews'] = [
                    'consciencia' => $row->ie_consciencia !== null ? "Consciência: {$row->ie_consciencia}" : null,
                    'cardiovascular' => $row->ie_cardiovascular !== null ? "Cardiovascular: {$row->ie_cardiovascular}" : null,
                    'respiratorio' => $row->ie_respiratorio !== null ? "Respiratório: {$row->ie_respiratorio}" : null,
                    'vomito' => ($row->ie_vomito === 'S') ? 'Vômito' : null,
                    'nebulizacao' => ($row->ie_nebulizacao === 'S') ? 'Nebulização contínua' : null,
                    'avaliador' => $row->nm_avaliador,
                ];
            }
        } catch (\Throwable) {
        }

        // Braden: 6 sub-scores (1-4 ou 1-3)
        try {
            $row = DB::connection($this->connection)->selectOne('
                SELECT ie_percepcao_sensorial, ie_umidade, ie_atividade_fisica,
                       ie_mobilidade, ie_nutricao, ie_friccao_cisalhamento,
                       NVL(u.nm_pessoa_fisica, b.nm_usuario) AS nm_avaliador
                FROM tasy.atend_escala_braden b
                LEFT JOIN (
                    SELECT u2.nm_usuario, pf2.nm_pessoa_fisica
                    FROM tasy.usuario u2
                    JOIN tasy.pessoa_fisica pf2 ON pf2.cd_pessoa_fisica = u2.cd_pessoa_fisica
                ) u ON u.nm_usuario = b.nm_usuario
                WHERE b.nr_atendimento = :nr
                  AND b.dt_liberacao IS NOT NULL AND b.dt_inativacao IS NULL AND b.qt_ponto IS NOT NULL
                ORDER BY b.dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY
            ', ['nr' => $attendanceNumber]);

            if ($row) {
                $details['braden'] = [
                    'percepcao_sensorial' => $row->ie_percepcao_sensorial,
                    'umidade' => $row->ie_umidade,
                    'atividade' => $row->ie_atividade_fisica,
                    'mobilidade' => $row->ie_mobilidade,
                    'nutricao' => $row->ie_nutricao,
                    'friccao_cisalhamento' => $row->ie_friccao_cisalhamento,
                    'avaliador' => $row->nm_avaliador,
                ];
            }
        } catch (\Throwable) {
        }

        // Morse: 6 sub-scores
        try {
            $row = DB::connection($this->connection)->selectOne('
                SELECT ie_hist_queda, ie_diag_secundario, ie_ajuda_deambular,
                       ie_uso_heparina, ie_modo_andar, ie_estado_mental,
                       NVL(pf.nm_pessoa_fisica, m.nm_usuario) AS nm_avaliador
                FROM tasy.escala_morse m
                LEFT JOIN tasy.pessoa_fisica pf ON pf.cd_pessoa_fisica = m.cd_profissional
                WHERE m.nr_atendimento = :nr
                  AND m.dt_liberacao IS NOT NULL AND m.dt_inativacao IS NULL AND m.qt_pontuacao IS NOT NULL
                ORDER BY m.dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY
            ', ['nr' => $attendanceNumber]);

            if ($row) {
                $deambMap = ['0' => 'Sem auxílio', '15' => 'Bengala/muleta/andador', '30' => 'Amparo móvel'];
                $andarMap = ['0' => 'Normal', '10' => 'Comprometida', '20' => 'Prejudicada'];
                $details['morse'] = [
                    'hist_queda' => $row->ie_hist_queda === 'S',
                    'diag_secundario' => $row->ie_diag_secundario === 'S',
                    'ajuda_deambular' => $deambMap[(string) ($row->ie_ajuda_deambular ?? '')] ?? $row->ie_ajuda_deambular,
                    'uso_heparina' => $row->ie_uso_heparina === 'S',
                    'modo_andar' => $andarMap[(string) ($row->ie_modo_andar ?? '')] ?? $row->ie_modo_andar,
                    'estado_mental' => $row->ie_estado_mental == 0 ? 'Orientado' : 'Superestima capacidade',
                    'avaliador' => $row->nm_avaliador,
                ];
            }
        } catch (\Throwable) {
        }

        // TEV: fatores de risco e recomendação
        try {
            $row = DB::connection($this->connection)->selectOne('
                SELECT ds_resultado, ie_clinico_cirurgico, ie_internacao_uti,
                       ie_cat_venoso_central, ie_cancer, ie_obesidade,
                       ie_mobilidade_reduzida, ie_insuf_arterial,
                       NVL(pf.nm_pessoa_fisica, t.nm_usuario) AS nm_avaliador
                FROM tasy.escala_tev t
                LEFT JOIN tasy.pessoa_fisica pf ON pf.cd_pessoa_fisica = t.cd_profissional
                WHERE t.nr_atendimento = :nr
                  AND t.dt_liberacao IS NOT NULL AND t.dt_inativacao IS NULL AND t.qt_pontuacao IS NOT NULL
                ORDER BY t.dt_avaliacao DESC FETCH FIRST 1 ROWS ONLY
            ', ['nr' => $attendanceNumber]);

            if ($row) {
                $fatores = array_values(array_filter([
                    ($row->ie_internacao_uti === 'S') ? 'Internação UTI' : null,
                    ($row->ie_cat_venoso_central === 'S') ? 'CVC' : null,
                    ($row->ie_cancer === 'S') ? 'Câncer' : null,
                    ($row->ie_mobilidade_reduzida === 'S') ? 'Mobilidade reduzida' : null,
                    ($row->ie_obesidade === 'S') ? 'Obesidade' : null,
                    ($row->ie_insuf_arterial === 'S') ? 'Insuf. arterial' : null,
                ]));

                $details['vte'] = [
                    'tipo' => $row->ie_clinico_cirurgico === 'C' ? 'Clínico' : ($row->ie_clinico_cirurgico === 'U' ? 'Cirúrgico' : null),
                    'fatores' => $fatores,
                    'recomendacao' => $row->ds_resultado ? trim((string) $row->ds_resultado) : null,
                    'avaliador' => $row->nm_avaliador,
                ];
            }
        } catch (\Throwable) {
        }

        return $details;
    }

    public function getPatientScalesUnified(int $attendanceNumber, bool $isNewPatient = false): ?array
    {
        if (! $attendanceNumber) {
            return null;
        }

        $map = $this->getPatientsScalesUnified(
            [$attendanceNumber],
            [$attendanceNumber => $isNewPatient]
        );

        return $map[$attendanceNumber] ?? null;
    }

    private function toScaleObject(?object $raw, string $valueCol, string $dateCol): ?object
    {
        if ($raw === null) {
            return null;
        }

        $obj = new \stdClass;
        $obj->{$valueCol} = $raw->score_value ?? null;
        $obj->{$dateCol} = $raw->dt_ref ?? null;

        return $obj;
    }

    private function buildScaleEntry(string $scale, ?object $current, ?object $previous, string $period, bool $isNew): array
    {
        $cfg = self::SCALE_CONFIG[$scale];
        $valueCol = $cfg['val'];
        $dateCol = $cfg['dt'];

        $currentValue = $current !== null ? ($current->{$valueCol} ?? null) : null;
        $currentTs = ($current !== null && ! empty($current->{$dateCol})) ? $this->formatTimestamp($current->{$dateCol}) : null;

        $previousValue = $previous !== null ? ($previous->{$valueCol} ?? null) : null;
        $previousTs = ($previous !== null && ! empty($previous->{$dateCol})) ? $this->formatTimestamp($previous->{$dateCol}) : null;

        if ($currentValue !== null && $currentValue !== '') {
            $pickedValue = $currentValue;
            $pickedTs = $currentTs;
            $isFallback = false;
        } elseif ($previousValue !== null && $previousValue !== '') {
            $pickedValue = $previousValue;
            $pickedTs = $previousTs;
            $isFallback = true;
        } else {
            $pickedValue = null;
            $pickedTs = null;
            $isFallback = false;
        }

        $score = $this->extractScore($pickedValue);
        $shift = $this->getShiftFromTimestamp($pickedTs);
        $previousScore = ($previousValue !== null && $previousValue !== '') ? $this->extractScore($previousValue) : null;

        $increased = false;
        if ($currentValue !== null && $currentValue !== '' && $previousValue !== null && $previousValue !== '') {
            $cs = $this->extractScore($currentValue);
            $ps = $this->extractScore($previousValue);
            if ($cs !== null && $ps !== null) {
                $increased = $cs > $ps;
            }
        }

        return [
            'score' => $score,
            'timestamp' => $pickedTs,
            'previous_score' => $previousScore,
            'previous_timestamp' => $previousTs,
            'classification' => $this->classify($scale, $score),
            'styling' => $this->style($scale, $score),
            'shift' => $shift,
            'period' => $period,
            'increased' => $increased,
            'needs_assessment' => $this->needsAssessment($pickedTs, $period),
            'is_fallback' => $isFallback,
        ];
    }

    private function classify(string $scale, ?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match ($scale) {
            'mews' => $score >= 5 ? 'Crítico' : ($score >= 3 ? 'Alerta' : 'Normal'),
            'pews' => $score >= 7 ? 'Crítico' : ($score >= 4 ? 'Alerta' : 'Normal'),
            'braden' => $score <= 12 ? 'Alto Risco' : ($score <= 14 ? 'Risco Moderado' : 'Baixo Risco'),
            'morse' => $score >= 45 ? 'Alto Risco' : ($score >= 25 ? 'Risco Moderado' : 'Baixo Risco'),
            'pain' => $score === 0 ? 'Sem Dor' : ($score >= 7 ? 'Dor Intensa' : ($score >= 4 ? 'Dor Moderada' : 'Dor Leve')),
            'vte' => $score >= 5 ? 'Alto Risco' : ($score >= 2 ? 'Risco Moderado' : 'Baixo Risco'),
            default => null,
        };
    }

    private function style(string $scale, ?int $score): array
    {
        $default = ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];

        if ($score === null) {
            return $default;
        }

        return match ($scale) {
            'mews' => ScaleStyleHelper::mewsRisk($score, false),
            'pews' => ScaleStyleHelper::pewsRisk($score, false),
            'braden' => ScaleStyleHelper::bradenRisk($score, false),
            'morse' => ScaleStyleHelper::morseRisk($score, false),
            'pain' => ScaleStyleHelper::painRisk($score, false),
            'vte' => ScaleStyleHelper::tevRisk($score, false),
            default => $default,
        };
    }

    private function extractScore(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (preg_match('/(\d+)/', (string) $value, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            if (is_string($value) && preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $value)) {
                return $value;
            }

            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Exception) {
            return null;
        }
    }

    private function getShiftFromTimestamp(?string $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }
        try {
            $dt = Carbon::createFromFormat('d/m/Y H:i', $timestamp);
        } catch (\Exception) {
            try {
                $dt = new Carbon($timestamp);
            } catch (\Exception) {
                return null;
            }
        }

        return ShiftService::shiftFromMinutes($dt->hour * 60 + $dt->minute);
    }

    private function needsAssessment(?string $timestamp, string $period): bool
    {
        if (! $timestamp) {
            return true;
        }

        try {
            $lastTime = preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $timestamp)
                ? Carbon::createFromFormat('d/m/Y H:i', $timestamp)
                : Carbon::parse($timestamp);
        } catch (\Exception) {
            return true;
        }

        if ($period === 'turno') {
            // Tolerância de 30 min nas bordas do turno: uma escala aferida até 30 min
            // antes do início do turno atual ainda é considerada válida para este turno.
            // Evita falsos alertas em trocas de turno (ex: aferição às 18:45 não deve
            // acionar pendência às 19:05 do turno seguinte).
            $shiftBoundaries = $this->getCurrentShiftBoundaries();
            $windowStart = $shiftBoundaries['start']->copy()->subMinutes(30);

            return $lastTime->lt($windowStart);
        }

        if ($period === '24h') {
            // Tolerância de 30 min para escalas diárias — mesma lógica dos chats de avaliação.
            return $lastTime->diffInMinutes(now()) >= (24 * 60 + 30);
        }

        return true;
    }

    /**
     * Retorna os timestamps de início e fim do turno atual.
     * Turnos: Manhã 07:15–13:14 | Tarde 13:15–19:14 | Noite 19:15–07:14
     */
    private function getCurrentShiftBoundaries(): array
    {
        $now = now();
        $minutes = $now->hour * 60 + $now->minute;

        if ($minutes >= 435 && $minutes <= 794) {
            // Manhã: 07:15 – 13:14
            return [
                'start' => $now->copy()->setTime(7, 15),
                'end' => $now->copy()->setTime(13, 14),
            ];
        }

        if ($minutes >= 795 && $minutes <= 1154) {
            // Tarde: 13:15 – 19:14
            return [
                'start' => $now->copy()->setTime(13, 15),
                'end' => $now->copy()->setTime(19, 14),
            ];
        }

        // Noite: 19:15 – 07:14 (cruza meia-noite)
        $start = ($minutes >= 1155)
            ? $now->copy()->setTime(19, 15)
            : $now->copy()->subDay()->setTime(19, 15);

        return [
            'start' => $start,
            'end' => $now->copy()->setTime(7, 14)->addDay(),
        ];
    }
}
