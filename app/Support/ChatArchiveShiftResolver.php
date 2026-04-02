<?php

namespace App\Support;

use Carbon\Carbon;

class ChatArchiveShiftResolver
{
    public static function inferTurno(string $datetime): string
    {
        $hour = (int) Carbon::parse($datetime)->format('H');

        if ($hour >= 7 && $hour < 13) {
            return 'manha';
        }

        if ($hour >= 13 && $hour < 19) {
            return 'tarde';
        }

        return 'noite';
    }

    public static function label(mixed $turno, mixed $timestamp = null): string
    {
        $map = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'];
        $normalized = mb_strtolower(trim((string) $turno));

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if ($timestamp !== null && $timestamp !== '') {
            $datetime = is_numeric($timestamp)
                ? date('Y-m-d H:i:s', (int) $timestamp)
                : (string) $timestamp;

            return match (self::inferTurno($datetime)) {
                'manha' => 'Manhã',
                'tarde' => 'Tarde',
                default => 'Noite',
            };
        }

        return '—';
    }
}
