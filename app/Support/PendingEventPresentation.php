<?php

namespace App\Support;

class PendingEventPresentation
{
    private const HEMOTHERAPY_TYPES = [
        '0' => 'Hemocomponente',
        '1' => 'Concentrado de Hemácias',
        '2' => 'Concentrado de Plaquetas',
        '3' => 'Plasma Fresco Congelado',
        '4' => 'Crioprecipitado',
        '5' => 'Concentrado de Granulócitos',
    ];

    /**
     * @param  array<string, mixed>  $event
     */
    public static function executionSectorLabel(array $event): string
    {
        foreach ([
            'setor_execucao',
            'setor_desc_raw',
            'setor_desc',
            'ds_setor_execucao',
            'local',
            'setor',
            'ds_local',
            'cd_setor_atendimento',
        ] as $key) {
            $value = trim((string) ($event[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '-';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function surgeryDescription(array $event): string
    {
        $parts = [];

        $descricao = trim((string) ($event['descricao'] ?? $event['descricao_padronizada'] ?? 'Cirurgia'));
        $descricao = trim((string) (preg_replace('/\s*\(\s*Cirurgia[^\)]*\)\s*$/iu', '', $descricao) ?? $descricao));
        if ($descricao !== '') {
            $parts[] = $descricao;
        }

        $local = trim((string) ($event['local'] ?? ''));
        if ($local !== '' && mb_strtolower($local) !== mb_strtolower($descricao)) {
            $parts[] = $local;
        }

        $sala = trim((string) ($event['sala'] ?? ''));
        if ($sala !== '') {
            $parts[] = 'Sala: '.$sala;
        }

        return implode(' - ', array_filter($parts, static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function classificationLabel(array $event, string $normalizedType): ?string
    {
        if ($normalizedType === PendingEventTypeClassifier::SURGERY) {
            return null;
        }

        $labGroup = trim((string) ($event['ds_grupo_lab'] ?? ''));

        return $labGroup !== '' ? $labGroup : null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function surgeryDiagnosticLabel(array $event): string
    {
        $status = trim((string) ($event['status_laudo'] ?? ''));
        $statusCode = strtoupper(trim((string) ($event['status_agenda_codigo'] ?? '')));

        if ($statusCode !== '') {
            return 'Status agenda: '.$statusCode.($status !== '' ? ' - '.$status : '');
        }

        return $status !== '' ? 'Status: '.$status : '';
    }

    /**
     * Retorna a explicação humana de por que o evento ainda está pendente.
     *
     * @param  array<string, mixed>  $event
     */
    public static function motivoPendente(array $event): string
    {
        // Flags vindas do PrescriptionPendingHandler
        if ($event['foi_executado_sem_baixa'] ?? false) {
            return 'Realizado — prescrição não baixada no sistema';
        }

        if ($event['exame_coletado_em_prescricao_mais_nova'] ?? false) {
            return 'Exame realizado em solicitação mais recente';
        }

        $tipo = PendingEventTypeClassifier::fromPendingEvent($event);
        $urgente = (bool) ($event['urgente'] ?? false);

        // ds_status_laudo tem precedência sobre status_laudo para exames
        $statusLaudo = trim((string) ($event['ds_status_laudo'] ?? ''));
        $statusExec = trim((string) ($event['status_laudo'] ?? ''));
        $status = $statusLaudo !== '' ? $statusLaudo : $statusExec;

        return match ($tipo) {
            PendingEventTypeClassifier::EXAM => match ($status) {
                'Coletado' => 'Aguardando laudo',
                default => $urgente ? 'Urgente — aguardando coleta' : 'Aguardando coleta',
            },
            PendingEventTypeClassifier::PROCEDURE => 'Aguardando execução',
            PendingEventTypeClassifier::SURGERY => 'Aguardando cirurgia',
            PendingEventTypeClassifier::HEMOTHERAPY => $urgente ? 'Urgente — aguardando transfusão' : 'Aguardando transfusão',
            PendingEventTypeClassifier::CHEMOTHERAPY => 'Aguardando quimioterapia',
            PendingEventTypeClassifier::ANTIBIOTIC => 'Antimicrobiano em uso',
            default => 'Aguardando',
        };
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function hemotherapyDescription(array $event): string
    {
        $parts = ['Hemoterapia'];

        $tipoCode = trim((string) ($event['ie_tipo_hemoterap'] ?? ''));
        $tipoLabel = trim((string) ($event['tipo_label'] ?? self::HEMOTHERAPY_TYPES[$tipoCode] ?? ''));
        if ($tipoLabel !== '') {
            $parts[] = $tipoLabel;
        }

        $prescrito = trim((string) ($event['ds_procedimento_prescrito'] ?? ''));
        if ($prescrito !== '' && mb_strtolower($prescrito) !== mb_strtolower($tipoLabel)) {
            $parts[] = $prescrito;
        }

        $volume = trim((string) ($event['qt_vol_hemocomp'] ?? ''));
        if ($volume !== '') {
            $parts[] = $volume.' mL';
        }

        $via = trim((string) ($event['via_aplicacao'] ?? $event['ie_via_aplicacao'] ?? ''));
        if ($via !== '') {
            $parts[] = $via;
        }

        $horarios = trim((string) ($event['ds_horarios'] ?? ''));
        if ($horarios !== '') {
            $parts[] = $horarios;
        }

        $obsProc = trim((string) ($event['ds_observacao_proc'] ?? ''));
        if ($obsProc !== '') {
            $parts[] = $obsProc;
        }

        $obs = trim((string) ($event['ds_observacao'] ?? ''));
        if ($obs !== '') {
            $parts[] = $obs;
        }

        return implode(' - ', array_filter($parts, static fn (string $value): bool => $value !== ''));
    }
}
