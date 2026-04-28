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
     * Determine o tipo de um evento de pendência já construído.
     *
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

        // Reclassifica por ds_grupo_lab quando tipo ainda é genérico
        if ($tipo === self::PROCEDURE) {
            return self::fromGrupoLab((string) ($event['ds_grupo_lab'] ?? ''));
        }

        $subtipo = mb_strtolower(trim((string) ($event['ds_subtipo'] ?? '')));
        if (str_contains($subtipo, 'exame') || str_contains($subtipo, 'laborat')) {
            return self::EXAM;
        }

        return self::PROCEDURE;
    }

    /**
     * Classifica uma linha de prescr_procedimento usando apenas as informações
     * disponíveis em tempo de construção do evento (sem depender de string).
     */
    public static function fromPrescriptionRow(bool $isExam, ?string $grupoLab): string
    {
        if ($isExam) {
            return self::EXAM;
        }

        return self::fromGrupoLab((string) ($grupoLab ?? ''));
    }

    /**
     * Mapeia ds_grupo_lab / ds_classificacao do Tasy para um tipo canônico.
     */
    private static function fromGrupoLab(string $grupoLab): string
    {
        $g = mb_strtolower(trim($grupoLab));

        if ($g === '') {
            return self::PROCEDURE;
        }
        if (str_contains($g, 'hemoterapia')) {
            return self::HEMOTHERAPY;
        }
        if (str_contains($g, 'quimioterapia')) {
            return self::CHEMOTHERAPY;
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
