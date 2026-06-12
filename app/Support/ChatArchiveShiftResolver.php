<?php

namespace App\Support;

use App\Services\ShiftService;
use Carbon\Carbon;

class ChatArchiveShiftResolver
{
    public static function inferShift(string $datetime): string
    {
        $dt = Carbon::parse($datetime);

        return match (ShiftService::shiftFromMinutes($dt->hour * 60 + $dt->minute)) {
            'M' => 'manha',
            'T' => 'tarde',
            default => 'noite',
        };
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

            return match (self::inferShift($datetime)) {
                'manha' => 'Manhã',
                'tarde' => 'Tarde',
                default => 'Noite',
            };
        }

        return '—';
    }
}
