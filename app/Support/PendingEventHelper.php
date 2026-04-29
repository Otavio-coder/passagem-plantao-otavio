<?php

namespace App\Support;

use App\Services\UserDisplayNameResolver;
use Carbon\Carbon;

class PendingEventHelper
{
    private const DISCHARGE_TYPES = ['alta', 'alta_medica', 'previsao_alta'];

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

    private const HEMOTHERAPY_TYPES = [
        '0' => 'Hemocomponente',
        '1' => 'Concentrado de Hemácias',
        '2' => 'Concentrado de Plaquetas',
        '3' => 'Plasma Fresco Congelado',
        '4' => 'Crioprecipitado',
        '5' => 'Concentrado de Granulócitos',
    ];

    /**
     * @var array<int, string>
     */
    private static array $userDisplayCache = [];

    /**
     * @return array{events: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>, first_event: array<string, mixed>|null}
     */
    public static function buildPendingModalData(array $pendingEvents): array
    {
        $events = self::withNearFlag($pendingEvents);
        $groups = [];

        foreach ($events as $event) {
            $event['icone'] = self::resolveEventIcon($event);
            $event = self::enrichUserDisplay($event);
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
            $event['icone'] = self::resolveEventIcon($event);
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

    /**
     * Estilos do card de resumo do paciente para o primeiro evento pendente.
     *
     * @return array{icon: string, card_bg: string, card_style: string, description_class: string, time_class: string, pulse_color: string, show_pulse: bool}
     */
    public static function firstEventCardStyle(?array $firstEvent): array
    {
        $event = $firstEvent ?? [];
        $type = (string) ($event['tipo'] ?? 'outros');
        $urgent = (bool) ($event['urgente'] ?? false);

        [$cardBg, $descriptionClass, $timeClass, $pulseColor] = match (true) {
            in_array($type, ['alta', 'aviso', 'obito'], true) => ['bg-[#E8E8E8] border border-gray-300', 'text-gray-700 font-bold', 'text-gray-600 font-semibold', 'bg-gray-400'],
            $type === 'alta_medica' => ['bg-[#E8E8E8] border border-gray-300', 'text-gray-700 font-bold', 'text-gray-600 font-semibold', 'bg-gray-400'],
            $type === 'previsao_alta' => ['bg-[#E8E8E8] border border-gray-300', 'text-gray-600 font-semibold', 'text-gray-500 font-medium', 'bg-gray-400'],
            $type === 'cirurgia' && $urgent => ['bg-[#7712C7]/10 border border-[#7712C7]', 'text-[#7712C7] font-bold', 'text-[#7712C7] font-semibold', 'bg-[#7712C7]'],
            $type === 'cirurgia' => ['bg-[#7712C7]/10 border border-[#7712C7]/50', 'text-[#7712C7] font-semibold', 'text-[#7712C7]/80 font-medium', 'bg-[#7712C7]/70'],
            $type === 'hemoterapia' => ['bg-[#7712C7]/10 border border-[#7712C7]/30', 'text-[#7712C7] font-semibold', 'text-[#7712C7]/80 font-medium', 'bg-[#7712C7]'],
            $type === 'quimioterapia' => ['bg-[#0A4700]/10 border border-[#0A4700]/40', 'text-[#0A4700] font-semibold', 'text-[#0A4700]/80 font-medium', 'bg-[#0A4700]'],
            $type === 'antibiotico' => ['bg-[#BDAD02]/10 border border-[#BDAD02]/60', 'text-[#5C5300] font-semibold', 'text-[#5C5300]/80 font-medium', 'bg-[#BDAD02]'],
            in_array($type, ['proc_exame', 'exame'], true) => ['bg-blue-50/60 border border-blue-200', 'text-blue-700 font-semibold', 'text-blue-600 font-medium', 'bg-blue-400'],
            $type === 'procedimento' => ['bg-indigo-50/60 border border-indigo-200', 'text-indigo-700 font-semibold', 'text-indigo-600 font-medium', 'bg-indigo-400'],
            $urgent => ['bg-[#7712C7]/10 border border-[#7712C7]/30', 'text-[#7712C7] font-bold', 'text-[#7712C7]/80 font-semibold', 'bg-[#7712C7]'],
            default => ['bg-white/30 border border-white/50', 'text-[#062047] font-semibold', 'text-[#004D9D] font-medium', 'bg-gray-400'],
        };

        $cardStyle = match (true) {
            in_array($type, ['alta', 'aviso', 'obito', 'alta_medica', 'previsao_alta'], true) => 'background-color: #E8E8E8; border-color: #D1D5DB;',
            $type === 'cirurgia' && $urgent => 'background-color: rgba(119, 18, 199, 0.12); border-color: rgba(119, 18, 199, 1);',
            $type === 'cirurgia' => 'background-color: rgba(119, 18, 199, 0.10); border-color: rgba(119, 18, 199, 0.5);',
            $type === 'hemoterapia' => 'background-color: rgba(119, 18, 199, 0.10); border-color: rgba(119, 18, 199, 0.3);',
            $type === 'quimioterapia' => 'background-color: rgba(10, 71, 0, 0.10); border-color: rgba(10, 71, 0, 0.4);',
            $type === 'antibiotico' => 'background-color: rgba(189, 173, 2, 0.10); border-color: rgba(189, 173, 2, 0.6);',
            in_array($type, ['proc_exame', 'exame'], true) => 'background-color: rgba(239, 246, 255, 0.9); border-color: #BFDBFE;',
            $type === 'procedimento' => 'background-color: rgba(238, 242, 255, 0.9); border-color: #C7D2FE;',
            $urgent => 'background-color: rgba(119, 18, 199, 0.10); border-color: rgba(119, 18, 199, 0.3);',
            default => 'background-color: rgba(255, 255, 255, 0.30); border-color: rgba(255, 255, 255, 0.50);',
        };

        return [
            'icon' => self::resolveEventIcon($event),
            'card_bg' => $cardBg,
            'card_style' => $cardStyle,
            'description_class' => $descriptionClass,
            'time_class' => $timeClass,
            'pulse_color' => $pulseColor,
            'show_pulse' => $urgent || in_array($type, ['alta', 'aviso'], true),
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function resolveEventIcon(array $event): string
    {
        if (self::isDischargeType((string) ($event['tipo'] ?? ''))) {
            return 'alta.svg';
        }

        return (string) ($event['icone'] ?? 'alert-circle.svg');
    }

    private static function isDischargeType(string $type): bool
    {
        return in_array($type, self::DISCHARGE_TYPES, true);
    }

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
    public static function userDisplayLabel(array $event, string $fallbackKey = 'nm_prescritor'): string
    {
        $fallbackName = trim((string) ($event[$fallbackKey] ?? ''));
        $userId = (int) ($event[$fallbackKey.'_user_id'] ?? 0);

        if ($userId > 0) {
            if (isset(self::$userDisplayCache[$userId])) {
                return self::$userDisplayCache[$userId];
            }

            $resolver = app(UserDisplayNameResolver::class);
            $display = $resolver->fromUserId($userId, $fallbackName !== '' ? $fallbackName : null);

            return self::$userDisplayCache[$userId] = $display;
        }

        $resolver = app(UserDisplayNameResolver::class);

        return $resolver->fromName($fallbackName);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function enrichUserDisplay(array $event, string $sourceKey = 'nm_prescritor', string $targetKey = 'nm_prescritor_display'): array
    {
        $event[$targetKey] = self::userDisplayLabel($event, $sourceKey);

        return $event;
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
        // Executado no sistema mas sem baixa administrativa.
        // Para exames de laboratório, SCOLA é a fonte de verdade: dt_coleta e dt_resultado
        // de prescr_procedimento são datas programadas/administrativas e não garantem execução real.
        if ($event['foi_executado_sem_baixa'] ?? false) {
            $scolaStatus = mb_strtolower(trim((string) ($event['scola_status'] ?? '')));

            if ($scolaStatus !== '') {
                // SCOLA disponível: usa como fonte de verdade, ignora dt_coleta/dt_resultado do TASY
                if (str_contains($scolaStatus, 'laudo liberado') && str_contains($scolaStatus, 'aguardando integração')) {
                    return 'Laudo liberado no Scola — aguardando integração com TASY';
                }

                if (str_contains($scolaStatus, 'laudo liberado')) {
                    return 'Laudo disponível — baixa não realizada';
                }

                if (str_contains($scolaStatus, 'nova coleta')) {
                    return 'Nova coleta necessária';
                }

                // Nova lógica: coleta no SCOLA mas não no Tasy
                if (($event['scola_colheita_sem_tasy'] ?? false) === true) {
                    return 'Coletado no SCOLA — coleta não registrada no Tasy, aguardando resultado';
                }

                if (str_contains($scolaStatus, 'coletado')) {
                    return 'Coletado — aguardando resultado';
                }

                // SCOLA sem coleta (solicitado ou outro): usa ie_status_execucao do domínio 1226.
                $code = trim((string) ($event['ie_status_execucao'] ?? ''));
                $urgente = (bool) ($event['urgente'] ?? false);
                if (in_array($code, ['20', '22', '25', '26', '28', '29', '30', '35', '45'], true)) {
                    return 'Aguardando laudo';
                }
                if (in_array($code, ['14', '15', '17', '18', '19'], true)) {
                    return 'Em execução — aguardando resultado';
                }

                return $urgente ? 'Urgente — aguardando coleta' : 'Aguardando coleta';
            } elseif (! empty($event['dt_coleta'])) {
                // Sem SCOLA, dt_coleta do TASY é o melhor indicador disponível
                return 'Coletado — aguardando resultado';
            }

            // Sem evidência de coleta: cai na lógica de domínio abaixo (ie_status_execucao)
        }

        $tipo = PendingEventTypeClassifier::fromPendingEvent($event);
        $urgente = (bool) ($event['urgente'] ?? false);

        // ds_status_laudo (obter_status_laudo Oracle) tem precedência sobre status_laudo
        $statusLaudo = trim((string) ($event['ds_status_laudo'] ?? ''));
        $statusExec = trim((string) ($event['status_laudo'] ?? ''));
        $status = $statusLaudo !== '' ? $statusLaudo : $statusExec;

        // Eventos provenientes de agenda_paciente usam o status da agenda como motivo.
        if (($event['_fonte'] ?? '') === 'agenda' && ! in_array($tipo, [PendingEventTypeClassifier::SURGERY], true)) {
            $statusBase = self::motivoAgenda($event);
        } else {
            $statusBase = match ($tipo) {
                PendingEventTypeClassifier::EXAM => self::motivoExame($status, $urgente, $event),
                PendingEventTypeClassifier::PROCEDURE => self::motivoProcedimento($urgente, $event),
                PendingEventTypeClassifier::SURGERY => self::motivoCirurgia($event),
                PendingEventTypeClassifier::HEMOTHERAPY => self::motivoHemoterapia($event, $urgente),
                PendingEventTypeClassifier::CHEMOTHERAPY => self::motivoQuimioterapia($event),
                PendingEventTypeClassifier::ANTIBIOTIC => self::motivoAntibiotico($event),
                default => 'Aguardando',
            };
        }

        // Contexto de solicitação mais nova: usa scola_status quando disponível para evitar
        // contradição entre "Aguardando coleta" (TASY desta prescrição) e laudo já disponível.
        if ($event['exame_coletado_em_prescricao_mais_nova'] ?? false) {
            $scolaStatus = mb_strtolower(trim((string) ($event['scola_status'] ?? '')));

            if (str_contains($scolaStatus, 'laudo liberado')) {
                return 'Laudo disponível em solicitação mais recente';
            }

            if (str_contains($scolaStatus, 'coletado')) {
                return 'Coletado em solicitação mais recente — aguardando resultado';
            }

            return "{$statusBase} · há solicitação mais recente";
        }

        if ($event['proc_realizado_em_nova_prescricao'] ?? false) {
            return "{$statusBase} · realizado em solicitação mais recente";
        }

        $novaInfo = trim((string) ($event['prescricao_mais_nova_pendente_info'] ?? ''));
        if ($novaInfo !== '') {
            return "{$statusBase} · há solicitação mais recente: {$novaInfo}";
        }

        return $statusBase;
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

    /**
     * Motivo para itens oriundos de agenda_paciente (exceto cirurgias).
     *
     * @param  array<string, mixed>  $event
     */
    private static function motivoAgenda(array $event): string
    {
        $statusLabel = trim((string) ($event['status_laudo'] ?? ''));
        $statusCode = strtoupper(trim((string) ($event['ie_status_agenda'] ?? '')));
        $descricao = trim((string) ($event['descricao'] ?? ''));

        return match ($statusCode) {
            'F' => 'Falta justificada — '.(mb_strlen($descricao) > 0 ? mb_strtolower($descricao) : 'sessão não realizada'),
            'FI' => 'Falta injustificada — sessão não realizada',
            'AG' => 'Aguardando atendimento',
            'AT' => 'Em atendimento',
            'EM' => 'Em preparo',
            'RE' => 'Em recuperação',
            default => $statusLabel !== '' ? "Agendado: {$statusLabel}" : 'Agendado — aguardando realização',
        };
    }

    private static function motivoExame(string $status, bool $urgente, array $event): string
    {
        // Para exames de laboratório, o SCOLA é fonte primária do estado real da coleta.
        // O ie_status_execucao do TASY pode mostrar "Em exame" (dom. 1226 cód. 15) mesmo quando
        // o LIMS está apenas no passo administrativo "Mapa de Trabalho impresso" (dom. 1030 cód. 15),
        // antes de qualquer coleta física.
        $scolaStatus = mb_strtolower(trim((string) ($event['scola_status'] ?? '')));
        if ($scolaStatus !== '') {
            if (str_contains($scolaStatus, 'laudo liberado')) {
                return 'Laudo disponível — baixa não realizada';
            }
            if (str_contains($scolaStatus, 'nova coleta')) {
                return 'Nova coleta necessária';
            }
            if (($event['scola_colheita_sem_tasy'] ?? false)) {
                return 'Coletado no SCOLA — coleta não registrada no Tasy, aguardando resultado';
            }
            if (str_contains($scolaStatus, 'coletado')) {
                return 'Coletado — aguardando resultado';
            }

            // 'Solicitado (aguardando coleta)' ou outro: SCOLA ainda não registrou coleta
            return $urgente ? 'Urgente — aguardando coleta' : 'Aguardando coleta';
        }

        // Sem integração SCOLA: usa domínio 1226 do TASY como fallback.
        // 14=Preparo, 15=Em exame, 17=Avaliação Médico, 18=Em Complemento, 19=Exame concluído,
        // 20=Executado, 22=Aguardando Laudo, 25=Laudo ditado, 26=Laudo em digitação,
        // 28=Pré laudo, 29=Liberado por interfaceamento, 30=Laudo sem liberação, 35=Aguarda 2ª aprovação
        $code = trim((string) ($event['ie_status_execucao'] ?? ''));

        if (! empty($event['dt_coleta']) || in_array($code, ['20', '22', '25', '26', '28', '29', '30', '35', '45'], true)) {
            return 'Aguardando laudo';
        }

        if (in_array($code, ['14', '15', '17', '18', '19'], true)) {
            return 'Em execução — aguardando resultado';
        }

        return $urgente ? 'Urgente — aguardando coleta' : 'Aguardando coleta';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function motivoProcedimento(bool $urgente, array $event = []): string
    {
        $code = trim((string) ($event['ie_status_execucao'] ?? ''));

        // Mapeia códigos do domínio 1226 para mensagens orientadas à ação
        $motivo = match ($code) {
            '11' => 'Paciente chegou ao setor — aguardando execução',
            '13' => 'Previsto — aguardando execução',
            '14' => 'Em preparo — execução não registrada',
            '15' => 'Em exame',
            '17' => 'Em avaliação médica',
            '18' => 'Em complemento',
            '19' => 'Concluído — aguardando baixa',
            '20' => 'Executado — aguardando baixa',
            '22' => 'Aguardando laudo',
            'ASA' => 'Aguardando aprovação para execução',
            default => $urgente ? 'Urgente — aguardando execução' : 'Aguardando execução',
        };

        return ($urgente && $code === '') ? 'Urgente — aguardando execução' : $motivo;
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
        $statusLabel = trim((string) ($event['status_laudo'] ?? ''));
        $statusCode = strtoupper(trim((string) ($event['ie_status_agenda'] ?? '')));

        $statusSuffix = match ($statusCode) {
            'F' => ' — falta justificada',
            'FI' => ' — falta injustificada',
            'AT' => ' — em atendimento',
            'EM' => ' — em preparo',
            default => $statusLabel !== '' ? ': '.lcfirst($statusLabel) : ' agendada',
        };

        $base = 'Sessão de quimioterapia'.$statusSuffix;

        return $ciclo !== '' ? "{$base} — Ciclo {$ciclo}" : $base;
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
    /**
     * Seleciona o evento mais relevante para exibição no card do paciente.
     *
     * Prioridade (ordem decrescente):
     *   1. Urgente ou tipo crítico (alta, alta_medica, aviso, obito)
     *   2. Em atraso — dt_evento anterior a hoje (mais urgentes primeiro)
     *   3. Hoje — dt_evento no dia corrente
     *   4. Always-near sem data — antibióticos e afins sem dt_evento
     *   5. Amanhã — dt_evento amanhã
     *   6. Futuro — qualquer evento além de amanhã
     *   7. Sem data — eventos restantes sem dt_evento
     *
     * Retorna null somente se não há nenhum evento.
     */
    private static function resolveFirstEvent(array $events): ?array
    {
        if (empty($events)) {
            return null;
        }

        $todayStart = Carbon::today();
        $todayEnd = $todayStart->copy()->endOfDay();
        $tomorrowEnd = $todayStart->copy()->addDay()->endOfDay();

        $urgent = [];
        $overdue = [];
        $today = [];
        $alwaysNearNoDate = [];
        $tomorrow = [];
        $future = [];
        $futureFrontOnly = []; // exames/procedimentos além de amanhã: menor prioridade que outros tipos futuros
        $noDate = [];

        $criticalTypes = ['alta', 'alta_medica', 'aviso', 'obito'];

        foreach ($events as $event) {
            $type = (string) ($event['tipo'] ?? '');
            $isUrgent = (bool) ($event['urgente'] ?? false);

            if ($isUrgent || in_array($type, $criticalTypes, true)) {
                $urgent[] = $event;

                continue;
            }

            $dtEvent = $event['dt_evento'] ?? null;

            if (empty($dtEvent)) {
                if (in_array($type, self::ALWAYS_NEAR_TYPES, true)) {
                    $alwaysNearNoDate[] = $event;
                } else {
                    $noDate[] = $event;
                }

                continue;
            }

            try {
                $parsed = Carbon::parse((string) $dtEvent);

                if ($parsed->lt($todayStart)) {
                    $overdue[] = $event;
                } elseif ($parsed->lte($todayEnd)) {
                    $today[] = $event;
                } elseif ($parsed->lte($tomorrowEnd)) {
                    $tomorrow[] = $event;
                } elseif (in_array($type, self::FRONT_NEAR_TYPES, true)) {
                    // Exames/procedimentos distantes têm menor prioridade que cirurgias, quimio etc.
                    $futureFrontOnly[] = $event;
                } else {
                    $future[] = $event;
                }
            } catch (\Throwable) {
                $noDate[] = $event;
            }
        }

        foreach ([$urgent, $overdue, $today, $alwaysNearNoDate, $tomorrow, $future, $futureFrontOnly, $noDate] as $bucket) {
            if (! empty($bucket)) {
                return $bucket[0];
            }
        }

        return null;
    }

    private static function normalizeGroupType(string $type): string
    {
        return $type === 'proc_exame' ? 'exame' : $type;
    }
}
