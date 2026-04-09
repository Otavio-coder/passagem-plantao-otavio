<?php

namespace App\View\Presenters;

use Carbon\Carbon;

class PatientCardPresenter
{
    public static function shiftDisplayName(?string $shift): string
    {
        return match ($shift) {
            'morning' => 'manhã',
            'afternoon' => 'tarde',
            'night' => 'noite',
            default => 'turno atual',
        };
    }

    public static function cleanSurgeryDescription(?string $description): string
    {
        $value = trim((string) ($description ?? 'Procedimento'));

        return trim((string) (preg_replace('/\s*\(\s*Cirurgia\s+agenda\s+para\s+[^\)]*\)\s*$/iu', '', $value) ?? $value));
    }

    /**
     * @param  array<int, array<string, mixed>>  $surgeries
     * @return array<int, array<string, mixed>>
     */
    public static function buildSurgeryItems(array $surgeries): array
    {
        return array_values(array_map(function (array $surgery): array {
            $surgery['display_description'] = self::cleanSurgeryDescription(
                (string) ($surgery['descricao_padronizada'] ?? $surgery['procedimento'] ?? 'Procedimento')
            );

            return $surgery;
        }, $surgeries));
    }

    /**
     * @param  array<string, mixed>|null  $dischargeInfo
     * @return array{show: bool, type: string, label: string, bg: string, icon: string}
     */
    public static function buildDischargeDisplay(?array $dischargeInfo): array
    {
        $type = (string) ($dischargeInfo['tipo'] ?? '');

        if (! in_array($type, ['alta', 'alta_medica'], true)) {
            return [
                'show' => false,
                'type' => $type,
                'label' => '',
                'bg' => 'bg-gray-100',
                'icon' => 'alta.svg',
            ];
        }

        return [
            'show' => true,
            'type' => $type,
            'label' => $type === 'alta' ? 'Alta Efetivada' : 'Alta Médica',
            'bg' => 'bg-gray-100',
            'icon' => 'alta.svg',
        ];
    }

    /**
     * @return array{prefix: string, label: string}
     */
    public static function buildEwsDisplay(array $patient): array
    {
        $isPediatricPatient = (bool) ($patient['is_pediatric'] ?? false);

        return [
            'prefix' => $isPediatricPatient ? 'pews' : 'mews',
            'label' => $isPediatricPatient ? 'PEWS' : 'MEWS',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $requests
     * @return array<int, array<string, mixed>>
     */
    public static function buildMultidisciplinaryRequests(array $requests): array
    {
        return array_values(array_map(function (array $request): array {
            $teamName = mb_strtolower((string) ($request['ds_equipe_destino'] ?? ''));

            $icon = match (true) {
                str_contains($teamName, 'fisio') => 'fisioterapia.svg',
                str_contains($teamName, 'psico') => 'psicologia.svg',
                str_contains($teamName, 'nutri') => 'nutricao.svg',
                str_contains($teamName, 'fono') => 'fonoaudiologia.svg',
                str_contains($teamName, 'social') => 'servico-social.svg',
                str_contains($teamName, 'acesso'),
                str_contains($teamName, 'picc') => 'catheter-svgrepo-com.svg',
                default => null,
            };

            $statusCode = (string) ($request['ie_status'] ?? '');

            $request['team_icon'] = $icon;
            $request['status_badge_class'] = $statusCode === 'R'
                ? 'bg-green-500 text-white'
                : 'bg-amber-500 text-white';
            $request['card_class'] = $statusCode === 'R'
                ? 'bg-green-50 border-green-200'
                : 'bg-amber-50 border-amber-200';
            $request['dt_registro_formatted'] = self::formatDateTime($request['dt_registro'] ?? null);
            $request['dt_liberacao_formatted'] = self::formatDateTime($request['dt_liberacao'] ?? null);
            $request['dt_resposta_formatted'] = self::formatDateTime($request['dt_resposta'] ?? null);

            return $request;
        }, $requests));
    }

    /**
     * @param  array<int, array<string, mixed>>  $pendingEvents
     * @return array{all_count: int, near_count: int}
     */
    public static function buildPendingModalMeta(array $pendingEvents): array
    {
        $allCount = count($pendingEvents);
        $nearCount = count(array_filter($pendingEvents, static fn (array $event): bool => (bool) ($event['is_near'] ?? false)));

        return [
            'all_count' => $allCount,
            'near_count' => $nearCount,
        ];
    }

    public static function buildAllergyItems(array $patient): array
    {
        $items = $patient['alergias_items'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(function (array $item): array {
            $severity = trim((string) ($item['grav'] ?? ''));
            $textClass = 'text-gray-500';
            $bgClass = 'bg-gray-100';

            if ($severity !== '') {
                $lower = mb_strtolower($severity);
                if (str_contains($lower, 'grave') || str_contains($lower, 'severa')) {
                    $textClass = 'text-red-700 font-semibold';
                    $bgClass = 'bg-red-100';
                } elseif (str_contains($lower, 'moderada')) {
                    $textClass = 'text-yellow-700';
                    $bgClass = 'bg-yellow-100';
                }
            }

            return [
                'med' => $item['med'] ?? null,
                'grav' => $item['grav'] ?? null,
                'text' => $item['text'] ?? null,
                'severity_text_class' => $textClass,
                'severity_bg_class' => $bgClass,
            ];
        }, $items));
    }

    public static function buildIsolationItems(?string $rawValue): array
    {
        $raw = trim(strip_tags((string) ($rawValue ?? '')));
        if ($raw === '' || mb_strtolower($raw) === 'não') {
            return [];
        }

        $parts = preg_split('/[;|\r\n]+/', $raw) ?: [];
        $items = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $part, $matches)) {
                $items[] = [
                    'label' => trim($matches[1]),
                    'value' => trim($matches[2]),
                ];
            } else {
                $items[] = ['text' => $part];
            }
        }

        return $items;
    }

    public static function buildSectorFallbackLabel(array $patient): string
    {
        $sectorName = trim((string) ($patient['ds_setor_atendimento'] ?? ''));
        if ($sectorName !== '') {
            return $sectorName;
        }

        $sectorCode = $patient['cd_setor_atendimento'] ?? null;

        return $sectorCode ? 'Setor '.$sectorCode : 'Setor não informado';
    }

    private static function formatDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
