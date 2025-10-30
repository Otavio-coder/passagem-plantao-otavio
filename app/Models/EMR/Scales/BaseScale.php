<?php
// File: `app/Models/EMR/Scales/BaseScale.php`
namespace App\Models\EMR\Scales;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

abstract class BaseScale extends Model
{
    protected $connection = 'tasy';
    public $timestamps = false;
    protected $guarded = [];

    protected static $dateColumn = 'dt_avaliacao';
    protected static $valueColumn = 'qt_pontuacao';

    public static function extractScore($value)
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (int) $value;
        if (preg_match('/(\d+)/', (string)$value, $m)) return (int) $m[1];
        return null;
    }

    public static function formatTimestamp($value)
    {
        if (!$value) return null;
        try {
            if (is_string($value) && preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $value)) {
                return $value;
            }
            $dt = Carbon::parse($value);
            return $dt->format('d/m/Y H:i');
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getDefaultPeriodForScale(string $scaleCode): string
    {
        $map = [
            'Pw' => 'turno', // MEWS
            'Bd' => 'turno', // PEWS
            'Br' => '24h',   // Braden
            'Ms' => '24h',   // Morse
            'Dr' => 'turno', // Dor
            'Tv' => '24h',   // TEV
        ];
        return $map[$scaleCode] ?? 'turno';
    }

    public static function getShiftFromTimestamp(?string $timestamp): ?string
    {
        if (!$timestamp) return null;
        try {
            $dt = Carbon::createFromFormat('d/m/Y H:i', $timestamp);
        } catch (\Exception $e) {
            try {
                $dt = new Carbon($timestamp);
            } catch (\Exception $e) {
                return null;
            }
        }

        $minutes = $dt->hour * 60 + $dt->minute;

        /**
         * INTERVALOS DEFINIDOS
         * - Manhã: 07:15 → 13:14  (435 - 794)
         * - Tarde: 13:15 → 19:14  (795 - 1154)
         * - Noite: 19:15 → 07:14  (1155-1439 e 0-434)
         */
        if ($minutes >= 435 && $minutes <= 794) return 'M';
        if ($minutes >= 795 && $minutes <= 1154) return 'T';
        if (($minutes >= 1155 && $minutes <= 1439) || ($minutes >= 0 && $minutes <= 434)) return 'N';

        return null;
    }

    /**
     *  Verifica se houve aumento no score
     */
    public static function hasIncreased($current, $previous)
    {
        $c = self::extractScore($current);
        $p = self::extractScore($previous);

        if ($c === null || $p === null) return false;

        return $c > $p;
    }

    public function scopeLatestSince($query, int $attendance, ?string $since)
    {
        $dateCol = static::$dateColumn;
        $q = $query->where('nr_atendimento', $attendance)
            ->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao');

        if ($since) {
            try {
                $dt = Carbon::parse($since);
                $q->where($dateCol, '>=', $dt);
            } catch (\Exception $e) {
                // ignore parse failure
            }
        }

        return $q->orderByDesc($dateCol)->limit(1);
    }

    public function scopeLatestBefore($query, int $attendance, ?string $before)
    {
        $dateCol = static::$dateColumn;
        $q = $query->where('nr_atendimento', $attendance)
            ->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao');

        if ($before) {
            try {
                $dt = Carbon::parse($before);
                $q->where($dateCol, '<', $dt);
            } catch (\Exception $e) {
                // ignore parse failure
            }
        }

        return $q->orderByDesc($dateCol)->limit(1);
    }

    public static function needsAssessment($lastAssessment, $period, $isNewPatient = false)
    {
        if (!$lastAssessment) {
            return true;
        }

        try {
            $lastTime = self::parseTimestamp((string)$lastAssessment);
            if (!$lastTime) return true;

            if ($period === 'turno') {
                $lastShift = self::getShiftFromTimestamp($lastTime->format('d/m/Y H:i'));
                $currentShift = self::getShiftFromTimestamp(now()->format('d/m/Y H:i'));
                return $lastShift !== $currentShift;
            }

            if ($period === '24h') {
                return $lastTime->diffInHours(now()) >= 24;
            }

            return true;
        } catch (\Exception) {
            return true;
        }
    }

    public static function stylingFallback()
    {
        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
    }

    /**
     * Parse timestamp brasileiro para Carbon
     */
    private static function parseTimestamp(?string $timestamp): ?Carbon
    {
        if (!$timestamp) return null;
        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/', $timestamp)) {
                return Carbon::createFromFormat('d/m/Y H:i', $timestamp);
            }
            return Carbon::parse($timestamp);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * ✅ CORRIGIDO: Removida toda validação de período para o previous
     * Agora aceita qualquer previous que foi passado pelo Repository
     */
    public static function buildStructure(
        $currentModel,
        $previousModel,
        string $period = 'turno',
        bool $isNewPatient = false
    ) {
        $valueCol = static::$valueColumn;
        $dateCol = static::$dateColumn;

        // Extrai valores dos models
        $currentValue = is_object($currentModel) ? ($currentModel->{$valueCol} ?? null) : null;
        $currentTs = is_object($currentModel) && !empty($currentModel->{$dateCol})
            ? static::formatTimestamp($currentModel->{$dateCol})
            : null;

        $previousValue = is_object($previousModel) ? ($previousModel->{$valueCol} ?? null) : null;
        $previousTs = is_object($previousModel) && !empty($previousModel->{$dateCol})
            ? static::formatTimestamp($previousModel->{$dateCol})
            : null;

        // Seleciona o valor atual (fallback para previous se current não existe)
        $picked = ['value' => null, 'timestamp' => null, 'is_fallback' => false];

        if ($currentValue !== null && $currentValue !== '') {
            $picked = ['value' => $currentValue, 'timestamp' => $currentTs, 'is_fallback' => false];
        } elseif ($previousValue !== null && $previousValue !== '') {
            $picked = ['value' => $previousValue, 'timestamp' => $previousTs, 'is_fallback' => true];
        }

        $score = static::extractScore($picked['value']);
        $timestamp = $picked['timestamp'] ?? null;
        $shift = static::getShiftFromTimestamp($timestamp);

        // ✅ CORRIGIDO: Remove validação isPreviousValidForComparison
        // Aceita o previous que veio do Repository sem validação adicional
        $previous_score = ($previousValue !== null && $previousValue !== '')
            ? static::extractScore($previousValue)
            : null;

        $previous_timestamp = $previousTs;

        // ✅ INCREASED: só true quando current e previous existem E score aumentou
        $increased = false;
        if (
            $currentValue !== null &&
            $currentValue !== '' &&
            $previousValue !== null &&
            $previousValue !== ''
        ) {
            $currentScore = static::extractScore($currentValue);
            $previousScore = static::extractScore($previousValue);

            if ($currentScore !== null && $previousScore !== null) {
                $increased = $currentScore > $previousScore;
            }
        }

        $needs = static::needsAssessment($timestamp, $period, $isNewPatient);

        $classification = method_exists(static::class, 'classificationFromScore')
            ? static::classificationFromScore($picked['value'])
            : null;

        $styling = method_exists(static::class, 'stylingFromScore')
            ? static::stylingFromScore($picked['value'])
            : null;

        if (!$styling) $styling = static::stylingFallback();

        return [
            'score' => $score,
            'timestamp' => $timestamp,
            'previous_score' => $previous_score,
            'previous_timestamp' => $previous_timestamp,
            'classification' => $classification,
            'styling' => $styling,
            'shift' => $shift,
            'period' => $period,
            'increased' => $increased,
            'needs_assessment' => $needs,
            'is_fallback' => $picked['is_fallback']
        ];
    }

    /**
     * Fetch the two most recent records for attendance
     */
    public static function fetchLastTwoStructureForAttendance(
        int $attendance,
        string $period = 'turno',
        bool $isNewPatient = false
    ): array {
        $dateCol = static::$dateColumn;

        $rows = static::query()
            ->where('nr_atendimento', $attendance)
            ->whereNotNull('dt_liberacao')
            ->whereNull('dt_inativacao')
            ->whereNotNull(static::$valueColumn)
            ->orderByDesc($dateCol)
            ->limit(2)
            ->get();

        $current = $rows->get(0) ?? null;
        $previous = $rows->get(1) ?? null;

        return static::buildStructure($current, $previous, $period, $isNewPatient);
    }

    /**
     * Convenience wrapper
     */
    public static function forAttendanceLatestTwo(
        int $attendance,
        string $period = 'turno',
        bool $isNewPatient = false
    ): array {
        return static::fetchLastTwoStructureForAttendance($attendance, $period, $isNewPatient);
    }
}
