<?php

namespace App\Services;

use Carbon\Carbon;

class ShiftService
{

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
