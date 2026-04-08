<?php

namespace App\Support;

use Carbon\Carbon;

class PendingEventPresentation
{
    private const ALWAYS_NEAR_TYPES = ['antibiotico', 'alta', 'alta_medica', 'aviso'];

    private const FRONT_NEAR_TYPES = ['procedimento', 'exame', 'proc_exame'];

    private const GROUP_ORDER = ['alta', 'alta_medica', 'aviso', 'exame', 'procedimento', 'cirurgia', 'hemoterapia', 'quimioterapia', 'antibiotico', 'previsao_alta', 'outros'];

    private const GROUP_LABELS = [
        'exame' => 'Exames/Laboratório',
        'procedimento' => 'Procedimentos',
        'cirurgia' => 'Cirurgias Agendadas',
        'hemoterapia' => 'Hemoterapia',
        'quimioterapia' => 'Quimioterapia',
        'antibiotico' => 'Antimicrobianos Ativos',
        'aviso' => 'Avisos',
        'alta' => 'Alta Efetivada',
        'alta_medica' => 'Alta Médica',
        'previsao_alta' => 'Previsão de Alta',
    ];

    /**
     * @return array{events: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>, first_event: array<string, mixed>|null}
     */
    public static function buildPendingModalData(array $pendingEvents): array
    {
        $events = self::withNearFlag($pendingEvents);
        $groups = [];

        foreach ($events as $event) {
            $type = self::normalizeGroupType((string) ($event['tipo'] ?? 'outros'));
            if (! isset($groups[$type])) {
                $groups[$type] = [
                    'type' => $type,
                    'label' => self::GROUP_LABELS[$type] ?? ucfirst($type),
                    'style' => self::groupStyle($type),
                    'events' => [],
                ];
            }

            $groups[$type]['events'][] = $event;
        }

        uksort($groups, function (string $a, string $b): int {
            $indexA = array_search($a, self::GROUP_ORDER, true);
            $indexB = array_search($b, self::GROUP_ORDER, true);

            return ($indexA === false ? 99 : $indexA) <=> ($indexB === false ? 99 : $indexB);
        });

        return [
            'events' => $events,
            'groups' => array_values($groups),
            'first_event' => self::resolveFirstEvent($events),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    public static function withNearFlag(array $events): array
    {
        return array_map(function (array $event): array {
            $event['is_near'] = self::isNear($event);

            return $event;
        }, $events);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function isNear(array $event, ?Carbon $today = null): bool
    {
        if (in_array((string) ($event['tipo'] ?? ''), self::ALWAYS_NEAR_TYPES, true)) {
            return true;
        }

        $dtEvent = $event['dt_evento'] ?? null;
        if (empty($dtEvent)) {
            return true;
        }

        try {
            $baseDate = $today ?? Carbon::today();
            $eventDate = Carbon::parse((string) $dtEvent)->startOfDay();

            return abs($eventDate->diffInDays($baseDate)) <= 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{border_header: string, bg_header: string, text_header: string, border_card: string, bg_card: string}
     */
    public static function groupStyle(string $groupType): array
    {
        return match ($groupType) {
            'aviso', 'alta', 'obito', 'alta_medica' => [
                'border_header' => 'border-gray-300',
                'bg_header' => 'bg-[#E8E8E8]',
                'text_header' => 'text-gray-700',
                'border_card' => 'border-gray-200',
                'bg_card' => 'bg-[#E8E8E8]/80',
            ],
            'previsao_alta' => [
                'border_header' => 'border-gray-300',
                'bg_header' => 'bg-[#E8E8E8]',
                'text_header' => 'text-gray-600',
                'border_card' => 'border-gray-200',
                'bg_card' => 'bg-[#E8E8E8]/80',
            ],
            'cirurgia' => [
                'border_header' => 'border-[#7712C7]/30',
                'bg_header' => 'bg-[#7712C7]/10',
                'text_header' => 'text-[#7712C7]',
                'border_card' => 'border-[#7712C7]/20',
                'bg_card' => 'bg-[#7712C7]/5',
            ],
            'hemoterapia' => [
                'border_header' => 'border-[#7712C7]/30',
                'bg_header' => 'bg-[#7712C7]/10',
                'text_header' => 'text-[#7712C7]',
                'border_card' => 'border-[#7712C7]/20',
                'bg_card' => 'bg-[#7712C7]/5',
            ],
            'quimioterapia' => [
                'border_header' => 'border-[#0A4700]/30',
                'bg_header' => 'bg-[#0A4700]/10',
                'text_header' => 'text-[#0A4700]',
                'border_card' => 'border-[#0A4700]/20',
                'bg_card' => 'bg-[#0A4700]/5',
            ],
            'antibiotico' => [
                'border_header' => 'border-[#BDAD02]/50',
                'bg_header' => 'bg-[#BDAD02]/10',
                'text_header' => 'text-[#5C5300]',
                'border_card' => 'border-[#BDAD02]/30',
                'bg_card' => 'bg-[#BDAD02]/5',
            ],
            'exame' => [
                'border_header' => 'border-blue-200',
                'bg_header' => 'bg-blue-50/60',
                'text_header' => 'text-blue-700',
                'border_card' => 'border-blue-200',
                'bg_card' => 'bg-blue-50/40',
            ],
            'procedimento' => [
                'border_header' => 'border-indigo-200',
                'bg_header' => 'bg-indigo-50/60',
                'text_header' => 'text-indigo-700',
                'border_card' => 'border-indigo-200',
                'bg_card' => 'bg-indigo-50/40',
            ],
            default => [
                'border_header' => 'border-gray-200',
                'bg_header' => 'bg-white/30',
                'text_header' => 'text-[#062047]',
                'border_card' => 'border-gray-200',
                'bg_card' => 'bg-gray-50/50',
            ],
        };
    }

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
     * Retorna a explicação humana de por que o evento ainda está pendente,
     * com contexto suficiente para orientar enfermeiros sobre a ação necessária.
     *
     * @param  array<string, mixed>  $event
     */
    public static function motivoPendente(array $event): string
    {
        // Flags diagnósticas do PrescriptionPendingHandler — têm prioridade absoluta
        if ($event['foi_executado_sem_baixa'] ?? false) {
            return 'Realizado — prescrição não baixada no sistema';
        }

        if ($event['exame_coletado_em_prescricao_mais_nova'] ?? false) {
            return 'Exame realizado em solicitação mais recente';
        }

        $tipo = PendingEventTypeClassifier::fromPendingEvent($event);
        $urgente = (bool) ($event['urgente'] ?? false);

        // ds_status_laudo (obter_status_laudo Oracle) tem precedência sobre status_laudo
        $statusLaudo = trim((string) ($event['ds_status_laudo'] ?? ''));
        $statusExec = trim((string) ($event['status_laudo'] ?? ''));
        $status = $statusLaudo !== '' ? $statusLaudo : $statusExec;

        return match ($tipo) {
            PendingEventTypeClassifier::EXAM => self::motivoExame($status, $urgente, $event),
            PendingEventTypeClassifier::PROCEDURE => self::motivoProcedimento($urgente),
            PendingEventTypeClassifier::SURGERY => self::motivoCirurgia($event),
            PendingEventTypeClassifier::HEMOTHERAPY => self::motivoHemoterapia($event, $urgente),
            PendingEventTypeClassifier::CHEMOTHERAPY => self::motivoQuimioterapia($event),
            PendingEventTypeClassifier::ANTIBIOTIC => self::motivoAntibiotico($event),
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

    // ── Helpers privados por tipo ─────────────────────────────────────────────

    private static function motivoExame(string $status, bool $urgente, array $event): string
    {
        $statusNorm = mb_strtolower($status);

        if (in_array($statusNorm, ['coletado'], true) || ! empty($event['dt_coleta'])) {
            return 'Aguardando laudo';
        }

        if (in_array($statusNorm, ['em análise', 'em analise'], true)) {
            return 'Material em análise — aguardando laudo';
        }

        return $urgente ? 'Urgente — aguardando coleta' : 'Aguardando coleta';
    }

    private static function motivoProcedimento(bool $urgente): string
    {
        return $urgente ? 'Urgente — aguardando execução' : 'Aguardando execução';
    }

    private static function motivoCirurgia(array $event): string
    {
        $carater = trim((string) ($event['carater'] ?? $event['carater_cirurgia'] ?? ''));
        $statusLabel = trim((string) ($event['status_laudo'] ?? ''));
        $urgente = (bool) ($event['urgente'] ?? false);

        $tipo = $carater !== '' ? strtolower($carater) : null;

        if ($urgente) {
            $tipoLabel = $tipo ?? 'urgência';

            return match ($statusLabel) {
                'Confirmada' => "Cirurgia de {$tipoLabel} — confirmada",
                'Paciente em sala' => "Cirurgia de {$tipoLabel} — paciente em sala",
                'Em preparo' => "Cirurgia de {$tipoLabel} — em preparo",
                default => "Cirurgia de {$tipoLabel} — aguardando realização",
            };
        }

        return match ($statusLabel) {
            'Confirmada' => $tipo !== null ? "Cirurgia {$tipo} confirmada" : 'Cirurgia confirmada',
            'Paciente em sala' => 'Paciente em sala — cirurgia em andamento',
            'Em preparo' => 'Cirurgia em preparo',
            'Aguardando remarcação' => 'Cirurgia aguardando remarcação',
            'Pré-agenda' => 'Cirurgia em pré-agenda',
            default => $tipo !== null ? "Cirurgia {$tipo} — aguardando realização" : 'Aguardando cirurgia',
        };
    }

    private static function motivoHemoterapia(array $event, bool $urgente): string
    {
        $tipoCode = trim((string) ($event['ie_tipo_hemoterap'] ?? ''));
        $tipoLabel = trim((string) ($event['tipo_label'] ?? self::HEMOTHERAPY_TYPES[$tipoCode] ?? ''));

        $produto = ($tipoLabel !== '' && mb_strtolower($tipoLabel) !== 'hemocomponente')
            ? $tipoLabel
            : 'hemocomponente';

        $base = "Aguardando transfusão de {$produto}";

        return $urgente ? 'Urgente — '.lcfirst($base) : $base;
    }

    private static function motivoQuimioterapia(array $event): string
    {
        $ciclo = trim((string) ($event['ciclo'] ?? ''));

        return $ciclo !== ''
            ? "Sessão de quimioterapia agendada — Ciclo {$ciclo}"
            : 'Sessão de quimioterapia agendada';
    }

    private static function motivoAntibiotico(array $event): string
    {
        $hora = '';
        if (! empty($event['dt_evento'])) {
            try {
                $hora = Carbon::parse((string) $event['dt_evento'])->format('H:i');
            } catch (\Throwable) {
            }
        }

        $acao = match (trim((string) ($event['status_laudo'] ?? ''))) {
            'Reaprazado' => 'reaprazada',
            'Recusado' => 'recusada',
            'Desfeito' => 'desfeita',
            default => 'não administrada',
        };

        $base = $hora !== '' ? "Dose das {$hora} {$acao}" : "Dose {$acao}";

        $complement = trim((string) ($event['ds_complemento'] ?? ''));

        return $complement !== '' ? "{$base} — {$complement}" : $base;
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>|null
     */
    private static function resolveFirstEvent(array $events): ?array
    {
        $todayStart = now()->startOfDay();
        $tomorrowEnd = $todayStart->copy()->addDay()->endOfDay();

        foreach ($events as $event) {
            $dtEvent = $event['dt_evento'] ?? null;
            if (empty($dtEvent)) {
                continue;
            }

            try {
                $parsed = Carbon::parse((string) $dtEvent);
                if ($parsed->lt($todayStart)) {
                    continue;
                }

                $type = (string) ($event['tipo'] ?? '');
                if (in_array($type, self::FRONT_NEAR_TYPES, true) && $parsed->gt($tomorrowEnd)) {
                    continue;
                }

                return $event;
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private static function normalizeGroupType(string $type): string
    {
        return $type === 'proc_exame' ? 'exame' : $type;
    }
}
