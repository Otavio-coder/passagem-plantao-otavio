<?php

namespace App\Services;

use Carbon\Carbon;

class ShiftService
{
    public static function getCurrentShift($time = null): string
    {
        $hour = $time ? Carbon::parse($time)->hour : now()->hour;
        
        if ($hour >= 7 && $hour < 13) {
            return 'manha';
        } elseif ($hour >= 13 && $hour < 19) {
            return 'tarde';
        } else {
            return 'noite';
        }
    }
    
    public static function getShiftLabel(string $shift): string
    {
        return match($shift) {
            'manha' => 'Manhã (07-13h)',
            'tarde' => 'Tarde (13-19h)', 
            'noite' => 'Noite (19-07h)',
            default => 'Indefinido'
        };
    }
    
    public static function getShiftColors(string $shift): array
    {
        return match($shift) {
            'manha' => [
                'headerBg' => 'from-amber-400 via-orange-400 to-red-400',
                'accentColor' => 'orange-500',
                'lightAccent' => 'orange-100',
                'darkAccent' => 'orange-600',
                'shadowColor' => 'shadow-orange-200/50'
            ],
            'tarde' => [
                'headerBg' => 'from-sky-400 via-blue-400 to-cyan-400',
                'accentColor' => 'sky-500',
                'lightAccent' => 'sky-100',
                'darkAccent' => 'sky-600',
                'shadowColor' => 'shadow-sky-200/50'
            ],
            'noite' => [
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
    
    public static function getShiftTheme(string $shift): array
    {
        return match($shift) {
            'manha' => [
                'description' => 'Novo dia começando',
                'mood' => 'energético',
                'icon' => 'heroicon-o-sun',
                'pattern' => 'dots',
                'atmosphere' => 'bright'
            ],
            'tarde' => [
                'description' => 'Período produtivo',
                'mood' => 'focado',
                'icon' => 'heroicon-o-cloud-sun',
                'pattern' => 'squares',
                'atmosphere' => 'balanced'
            ],
            'noite' => [
                'description' => 'Cuidado noturno',
                'mood' => 'vigilante',
                'icon' => 'heroicon-o-moon',
                'pattern' => 'stars',
                'atmosphere' => 'calm'
            ],
            default => [
                'description' => 'Plantão ativo',
                'mood' => 'neutro',
                'icon' => 'heroicon-o-clock',
                'pattern' => 'none',
                'atmosphere' => 'standard'
            ]
            };
    }
}