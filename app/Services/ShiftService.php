<?php

namespace App\Services;

use Carbon\Carbon;

class ShiftService
{
    // Minutos desde meia-noite nos pontos de transição de turno (30min de tolerância no fim)
    // Noite → Manhã: 07:30 (450 min)
    // Manhã → Tarde: 13:30 (810 min)
    // Tarde → Noite: 19:30 (1170 min)
    public const SHIFT_M_START = 450;

    public const SHIFT_T_START = 810;

    public const SHIFT_N_START = 1170;

    /** Expressão SQL CASE para classificar turno a partir de uma coluna datetime. */
    public static function shiftSqlExpr(string $col): string
    {
        $m = self::SHIFT_M_START;
        $t = self::SHIFT_T_START;
        $n = self::SHIFT_N_START;

        return "CASE WHEN (HOUR({$col})*60+MINUTE({$col})) >= {$m} AND (HOUR({$col})*60+MINUTE({$col})) < {$t} THEN 'M'"
            ." WHEN (HOUR({$col})*60+MINUTE({$col})) >= {$t} AND (HOUR({$col})*60+MINUTE({$col})) < {$n} THEN 'T'"
            ." ELSE 'N' END";
    }

    /** Expressão SQL CASE para calcular a data lógica do turno (noite cruza meia-noite). */
    public static function shiftDateSqlExpr(string $col): string
    {
        $m = self::SHIFT_M_START;

        return "CASE WHEN (HOUR({$col})*60+MINUTE({$col})) < {$m} THEN DATE({$col} - INTERVAL 1 DAY) ELSE DATE({$col}) END";
    }

    /** Classifica turno (M/T/N) a partir de minutos desde meia-noite. */
    public static function shiftFromMinutes(int $minutes): string
    {
        if ($minutes >= self::SHIFT_M_START && $minutes < self::SHIFT_T_START) {
            return 'M';
        }
        if ($minutes >= self::SHIFT_T_START && $minutes < self::SHIFT_N_START) {
            return 'T';
        }

        return 'N';
    }

    /** Data lógica do turno a partir de um Unix timestamp. */
    public static function shiftDateFromTimestamp(int $ts): string
    {
        $minutes = (int) date('H', $ts) * 60 + (int) date('i', $ts);

        return $minutes < self::SHIFT_M_START
            ? date('Y-m-d', strtotime('-1 day', $ts))
            : date('Y-m-d', $ts);
    }

    /**
     * Retorna o turno atual como 'M', 'T' ou 'N'
     */
    public static function getCurrentShift($time = null): string
    {
        try {
            $dt = $time ? Carbon::parse($time) : now();
        } catch (\Exception $e) {
            $dt = now();
        }

        return self::shiftFromMinutes($dt->hour * 60 + $dt->minute);
    }

    /**
     * Retorna a data correta para a sessão de chat baseada no turno.
     * Para turno da noite (19h-07h), se entre 00:00 e 07:00, usa o dia anterior.
     */
    public static function getShiftDateForSession($dateTime = null): string
    {
        $dt = $dateTime ? Carbon::parse($dateTime) : now();

        return self::shiftDateFromTimestamp($dt->timestamp);
    }

    /**
     * Retorna informações completas do turno: shift e date.
     * Corrige a lógica do turno da noite que começa em um dia e termina no próximo.
     *
     * @return array ['shift' => string, 'date' => string]
     */
    public static function getShiftInfo($dateTime = null, int $graceMinutes = 0): array
    {
        $dt = $dateTime ? Carbon::parse($dateTime) : now();
        if ($graceMinutes > 0) {
            $dt = $dt->copy()->subMinutes($graceMinutes);
        }

        $minutes = $dt->hour * 60 + $dt->minute;
        $shift = self::shiftFromMinutes($minutes);
        $date = self::shiftDateFromTimestamp($dt->timestamp);

        return [
            'shift' => match ($shift) {
                'M' => 'morning',
                'T' => 'afternoon',
                default => 'night',
            },
            'date' => $date,
        ];
    }

    /**
     * Retorna o turno (M/T/N) a partir de um timestamp d/m/Y H:i
     */
    public static function getShiftFromTimestamp($timestamp): ?string
    {
        try {
            $dt = Carbon::createFromFormat('d/m/Y H:i', $timestamp);
        } catch (\Exception $e) {
            return null;
        }

        return self::shiftFromMinutes($dt->hour * 60 + $dt->minute);
    }

    /**
     * Retorna [Carbon $start, Carbon $end] da janela do turno atual.
     * morning:   07:00 – 12:59:59
     * afternoon: 13:00 – 18:59:59
     * night:     19:00 – 06:59:59 (cruza meia-noite)
     */
    public static function getShiftWindow($now = null): array
    {
        $dt = $now ? Carbon::parse($now) : now();
        $shift = self::shiftFromMinutes($dt->hour * 60 + $dt->minute);
        $today = $dt->toDateString();

        return match ($shift) {
            'M' => [
                Carbon::parse("{$today} 07:30:00"),
                Carbon::parse("{$today} 13:29:59"),
            ],
            'T' => [
                Carbon::parse("{$today} 13:30:00"),
                Carbon::parse("{$today} 19:29:59"),
            ],
            default => $dt->hour >= 19 || ($dt->hour === 19 && $dt->minute >= 30)
                ? [Carbon::parse("{$today} 19:30:00"), Carbon::parse($dt->copy()->addDay()->toDateString().' 07:29:59')]
                : [Carbon::parse($dt->copy()->subDay()->toDateString().' 19:30:00'), Carbon::parse("{$today} 07:29:59")],
        };
    }

    /**
     * Returns [Carbon $start, Carbon $end] considering a grace period before shift change.
     * Within `graceMinutes` before a shift starts, returns the upcoming shift's window.
     * E.g. at 06:30 with grace=30, returns the morning window (07:00–12:59) instead of night.
     */
    public static function getShiftWindowWithGrace(int $graceMinutes = 30, $now = null): array
    {
        $dt = $now ? Carbon::parse($now) : now();
        $shifted = $dt->copy()->addMinutes($graceMinutes);

        return self::getShiftWindow($shifted);
    }

    /**
     * Returns the current shift string ('M', 'T', or 'N') considering a grace period.
     * Within `graceMinutes` before a shift starts, returns the upcoming shift.
     */
    public static function getCurrentShiftWithGrace(int $graceMinutes = 30, $time = null): string
    {
        $dt = $time ? Carbon::parse($time) : now();
        $shifted = $dt->copy()->addMinutes($graceMinutes);

        return self::getCurrentShift($shifted);
    }

    /**
     * Returns ['shift' => 'M'|'T'|'N', 'date' => 'Y-m-d'] for activity log grouping.
     * Activity within 60 min before a shift start is attributed to the incoming shift,
     * matching the real-world pattern of nurses arriving early to do handover prep.
     */
    public static function getShiftContextForLog(Carbon $ts, int $toleranceMinutes = 60): array
    {
        // Aplica tolerância antecipando o timestamp (ex: 60min antes → classifica no turno entrante)
        $effective = $ts->copy()->addMinutes($toleranceMinutes);
        $shift = self::shiftFromMinutes($effective->hour * 60 + $effective->minute);
        $date = self::shiftDateFromTimestamp($ts->timestamp);

        return ['shift' => $shift, 'date' => $date];
    }

    public static function getShiftLabel(string $shift): string
    {
        return match ($shift) {
            'morning' => 'Manhã (07-13h)',
            'afternoon' => 'Tarde (13-19h)',
            'night' => 'Noite (19-07h)',
            default => 'Indefinido'
        };
    }

    public static function getShiftColors(string $shift): array
    {
        return match ($shift) {
            'morning' => [
                'headerBg' => 'from-amber-400 via-orange-400 to-red-400',
                'accentColor' => 'orange-500',
                'lightAccent' => 'orange-100',
                'darkAccent' => 'orange-600',
                'shadowColor' => 'shadow-orange-200/50',
            ],
            'afternoon' => [
                'headerBg' => 'from-sky-400 via-blue-400 to-cyan-400',
                'accentColor' => 'sky-500',
                'lightAccent' => 'sky-100',
                'darkAccent' => 'sky-600',
                'shadowColor' => 'shadow-sky-200/50',
            ],
            'night' => [
                'headerBg' => 'from-indigo-500 via-purple-500 to-violet-600',
                'accentColor' => 'indigo-500',
                'lightAccent' => 'indigo-100',
                'darkAccent' => 'indigo-600',
                'shadowColor' => 'shadow-indigo-200/50',
            ],
            default => [
                'headerBg' => 'from-gray-400 to-gray-500',
                'accentColor' => 'gray-500',
                'lightAccent' => 'gray-100',
                'darkAccent' => 'gray-600',
                'shadowColor' => 'shadow-gray-200/50',
            ]
        };
    }
}
