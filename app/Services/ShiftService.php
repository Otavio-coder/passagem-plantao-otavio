<?php

namespace App\Services;

use Carbon\Carbon;

class ShiftService
{
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

        $minutes = $dt->hour * 60 + $dt->minute;

        // Manhã: 07:15 - 13:14 (435 - 794)
        // Tarde: 13:15 - 19:14 (795 - 1154)
        // Noite: 19:15 - 07:14 (1155+ e 0 - 434)
        if ($minutes >= 435 && $minutes <= 794) return 'M';
        if ($minutes >= 795 && $minutes <= 1154) return 'T';
        return 'N';
    }

    /**
     * Retorna a data correta para a sessão de chat baseada no turno.
     * Para turno da noite (19h-07h), se entre 00:00 e 07:00, usa o dia anterior.
     */
    public static function getShiftDateForSession($dateTime = null): string
    {
        $dt = $dateTime ? Carbon::parse($dateTime) : now();

        if ($dt->hour >= 0 && $dt->hour < 7) {
            return $dt->copy()->subDay()->toDateString();
        }

        return $dt->toDateString();
    }

    /**
     * Retorna informações completas do turno: shift e date.
     * Corrige a lógica do turno da noite que começa em um dia e termina no próximo.
     *
     * @return array ['shift' => string, 'date' => string]
     */
    public static function getShiftInfo($dateTime = null): array
    {
        $dt = $dateTime ? Carbon::parse($dateTime) : now();
        $hour = $dt->hour;

        if ($hour >= 7 && $hour < 13) {
            return ['shift' => 'morning',   'date' => $dt->toDateString()];
        }

        if ($hour >= 13 && $hour < 19) {
            return ['shift' => 'afternoon', 'date' => $dt->toDateString()];
        }

        // Night shift — se entre 00:00 e 06:59, o turno começou ontem
        $date = ($hour >= 0 && $hour < 7)
            ? $dt->copy()->subDay()->toDateString()
            : $dt->toDateString();

        return ['shift' => 'night', 'date' => $date];
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

        $minutes = $dt->hour * 60 + $dt->minute;
        if ($minutes >= 435 && $minutes <= 794) return 'M';
        if ($minutes >= 795 && $minutes <= 1154) return 'T';
        if (($minutes >= 1155 && $minutes <= 1439) || ($minutes >= 0 && $minutes <= 434)) return 'N';
        return null;
    }

    public static function getShiftLabel(string $shift): string
    {
        return match($shift) {
            'morning' => 'Manhã (07-13h)',
            'afternoon' => 'Tarde (13-19h)',
            'night' => 'Noite (19-07h)',
            default => 'Indefinido'
        };
    }

    public static function getShiftColors(string $shift): array
    {
        return match($shift) {
            'morning' => [
                'headerBg' => 'from-amber-400 via-orange-400 to-red-400',
                'accentColor' => 'orange-500',
                'lightAccent' => 'orange-100',
                'darkAccent' => 'orange-600',
                'shadowColor' => 'shadow-orange-200/50'
            ],
            'afternoon' => [
                'headerBg' => 'from-sky-400 via-blue-400 to-cyan-400',
                'accentColor' => 'sky-500',
                'lightAccent' => 'sky-100',
                'darkAccent' => 'sky-600',
                'shadowColor' => 'shadow-sky-200/50'
            ],
            'night' => [
                'headerBg' => 'from-indigo-500 via-purple-500 to-violet-600',
                'accentColor' => 'indigo-500',
                'lightAccent' => 'indigo-100',
                'darkAccent' => 'indigo-600',
                'shadowColor' => 'shadow-indigo-200/50'
            ],
            default => [
                'headerBg' => 'from-gray-400 to-gray-500',
                'accentColor' => 'gray-500',
                'lightAccent' => 'gray-100',
                'darkAccent' => 'gray-600',
                'shadowColor' => 'shadow-gray-200/50'
            ]
        };
    }

}
