<?php

namespace App\Support;

class PendingEventTypeClassifier
{
    public const EXAM = 'exame';

    public const PROCEDURE = 'procedimento';

    public const SURGERY = 'cirurgia';

    public const HEMOTHERAPY = 'hemoterapia';

    public const CHEMOTHERAPY = 'quimioterapia';

    public const ANTIBIOTIC = 'antibiotico';

    /**
     * @param  array<string, mixed>  $event
     */
    public static function fromPendingEvent(array $event): string
    {
        $tipo = mb_strtolower(trim((string) ($event['tipo'] ?? '')));

        if (in_array($tipo, [self::HEMOTHERAPY, self::CHEMOTHERAPY, self::ANTIBIOTIC], true)) {
            return $tipo;
        }

        if ($tipo === self::SURGERY) {
            return self::SURGERY;
        }
        if ($tipo === self::EXAM || $tipo === 'proc_exame') {
            return self::EXAM;
        }
        if ($tipo === self::PROCEDURE) {
            return self::PROCEDURE;
        }

        $subtipo = mb_strtolower(trim((string) ($event['ds_subtipo'] ?? '')));
        if (str_contains($subtipo, 'exame') || str_contains($subtipo, 'laborat')) {
            return self::EXAM;
        }

        return self::PROCEDURE;
    }

    /**
     * @param  array<string, mixed>  $procedure
     */
    public static function fromTherapeuticProcedure(array $procedure): string
    {
        $raw = mb_strtolower(trim((string) ($procedure['event_type'] ?? '')));
        if ($raw === self::EXAM || $raw === self::PROCEDURE || $raw === self::SURGERY) {
            return $raw;
        }

        $type = mb_strtolower(trim((string) ($procedure['type'] ?? '')));
        if (str_contains($type, 'exame') || str_contains($type, 'laborat')) {
            return self::EXAM;
        }

        $name = mb_strtolower(trim((string) ($procedure['name'] ?? '')));
        if (str_contains($name, 'ecg') || str_contains($name, 'eletrocardiograma')) {
            return self::PROCEDURE;
        }

        return self::PROCEDURE;
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::EXAM => 'Exame/Laboratório',
            self::SURGERY => 'Cirurgia',
            self::HEMOTHERAPY => 'Hemoterapia',
            self::CHEMOTHERAPY => 'Quimioterapia',
            self::ANTIBIOTIC => 'Antimicrobiano',
            default => 'Procedimento',
        };
    }
}
