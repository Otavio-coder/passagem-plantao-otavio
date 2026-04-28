<?php

namespace App\Repositories\EMR;

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

        $minutes = $dt->hour * 60 + $dt->minute;

        if ($minutes >= 435 && $minutes <= 794) {
            return 'M';
        }
        if ($minutes >= 795 && $minutes <= 1154) {
            return 'T';
        }

        return 'N';
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
            return $this->getShiftFromTimestamp($timestamp) !== $this->getShiftFromTimestamp(now()->format('d/m/Y H:i'));
        }

        if ($period === '24h') {
            return $lastTime->diffInHours(now()) >= 24;
        }

        return true;
    }
}
