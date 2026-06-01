<?php

namespace App\View\Components\PatientCard;

use Illuminate\View\Component;
use Illuminate\View\View;

class Demographics extends Component
{
    public function __construct(
        public array $patient,
        public bool $showAdminData = false,
        public bool $showScales = false,
    ) {}

    public function render(): View
    {
        return view('components.patient-card.demographics', $this->computeViewData());
    }

    private function computeViewData(): array
    {
        $patient = $this->patient;

        // ── Admin grid ─────────────────────────────────────────────────────────
        $prevAltaRaw = $patient['discharge_info']['dt_previsto_alta_formatted'] ?? null;
        $hasPrevAlta = in_array(($patient['discharge_display']['type'] ?? ''), ['previsao_alta', 'alta_medica'], true)
            && ! empty($prevAltaRaw);

        $avalEnf = $patient['avaliacao_enf'] ?? null;
        $avalEnfOk = $avalEnf && $avalEnf !== 'Não realizada';
        $avalEnfDisplay = $avalEnfOk ? (explode(' ', (string) $avalEnf)[0] ?? null) : null;

        $peData = $patient['pe_data'] ?? null;
        $peOk = $peData && $peData !== 'Não realizado';

        $hemocDate = $patient['ultima_hemocultura'] ?? null;
        $hemocPend = $patient['hemocultura_pendente'] ?? false;

        $medicoRaw = $patient['medico_responsavel'] ?? null;
        $medicoParts = $medicoRaw ? array_values(array_filter(explode(' ', $medicoRaw))) : [];
        $medicoAbrev = count($medicoParts) >= 2
            ? $medicoParts[0].' '.$medicoParts[count($medicoParts) - 1]
            : ($medicoRaw ?? null);

        // ── Hemocultura pipeline ───────────────────────────────────────────────
        $hemocEvents = collect($patient['pending_events'] ?? [])
            ->filter(fn ($e) => in_array($e['tipo'] ?? '', ['exame', 'proc_exame'], true)
                && str_contains(strtoupper($e['descricao'] ?? ''), 'HEMOCULTURA'))
            ->values()
            ->all();

        $hemocHistory = $patient['hemoc_history'] ?? [];
        $hemocActiveCount = count($hemocEvents);

        $activePrescricoes = collect($hemocEvents)
            ->pluck('nr_prescricao')->map('strval')->filter()->values()->all();

        $hemocHistoryFiltered = collect($hemocHistory)
            ->reject(fn ($h) => in_array((string) ($h['nr_prescricao'] ?? ''), $activePrescricoes, true))
            ->values()
            ->all();

        $sortKey = static function (string $s): string {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s)) {
                return str_replace(['-', ' ', ':'], '', $s);
            }
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2,4})/', $s, $m)) {
                $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

                return $year.$m[2].$m[1].preg_replace('/[^0-9]/', '', substr($s, 8));
            }

            return '0';
        };

        $rawItems = [];
        foreach ($hemocEvents as $ev) {
            $rawItems[] = ['type' => 'active', 'sort' => $sortKey((string) ($ev['dt_evento'] ?? $ev['dt_solicitacao'] ?? $ev['dt_coleta'] ?? '')), 'data' => $ev];
        }
        foreach ($hemocHistoryFiltered as $hist) {
            $rawItems[] = ['type' => 'history', 'sort' => $sortKey(($hist['dt_prescricao'] ?? '') ?: ($hist['date'] ?? '')), 'data' => $hist];
        }
        usort($rawItems, fn ($a, $b) => strcmp($b['sort'], $a['sort']));

        $hemocAllItems = array_map([$this, 'enrichHemocItem'], $rawItems);

        // ── Card preview ───────────────────────────────────────────────────────
        $hemocTemDados = $hemocDate !== null || $hemocActiveCount > 0 || ! empty($hemocHistory);
        $cardNrPrescricao = $hemocEvents[0]['nr_prescricao'] ?? $hemocHistory[0]['nr_prescricao'] ?? null;
        $cardDate = $hemocActiveCount > 0
            ? ($hemocEvents[0]['dt_solicitacao'] ?? $hemocEvents[0]['dt_evento_formatted'] ?? null)
            : $hemocDate;

        // ── Card badge ─────────────────────────────────────────────────────────
        $latestResult = $hemocHistory[0]['scola_resultado'] ?? null;
        $latestHasResult = $hemocHistory[0]['has_result'] ?? false;
        $latestTasyCode = $hemocHistory[0]['status_execucao'] ?? null;
        $latestScolaStatus = (empty($hemocHistory) && ! empty($hemocEvents))
            ? ($hemocEvents[0]['scola_status'] ?? null)
            : null;

        [$hemocCardBadge, $hemocCardBadgeStyle] = $this->resolveCardBadge(
            $latestResult, $latestHasResult, $latestTasyCode, $latestScolaStatus, $hemocPend, $hemocActiveCount
        );

        return [
            'patient' => $patient,
            'showAdminData' => $this->showAdminData,
            'showScales' => $this->showScales,
            'hasPrevAlta' => $hasPrevAlta,
            'prevAltaRaw' => $prevAltaRaw,
            'avalEnfOk' => $avalEnfOk,
            'avalEnfDisplay' => $avalEnfDisplay,
            'peOk' => $peOk,
            'peData' => $peData,
            'hemocDate' => $hemocDate,
            'medicoAbrev' => $medicoAbrev,
            'hemocTemDados' => $hemocTemDados,
            'hemocAllItems' => $hemocAllItems,
            'cardNrPrescricao' => $cardNrPrescricao,
            'cardDate' => $cardDate,
            'hemocCardBadge' => $hemocCardBadge,
            'hemocCardBadgeStyle' => $hemocCardBadgeStyle,
        ];
    }

    private function enrichHemocItem(array $item): array
    {
        if ($item['type'] === 'active') {
            $ev = $item['data'];
            $res = $ev['scola_resultado'] ?? null;
            $scola = $ev['scola_status'] ?? null;
            $isPos = str_starts_with($res ?? '', 'Positivo');

            if ($isPos) {
                $badge = $res;
                $badgeCls = 'bg-red-100 text-red-700';
            } elseif ($res === 'Negativo') {
                $badge = 'Negativo';
                $badgeCls = 'bg-emerald-100 text-emerald-700';
            } elseif ($res !== null) {
                $badge = $res;
                $badgeCls = 'bg-amber-100 text-amber-700';
            } elseif ($scola !== null && str_contains($scola, 'Laudo liberado')) {
                $badge = 'Laudo liberado';
                $badgeCls = 'bg-santacasa-100/20 text-santacasa-200';
            } elseif ($scola !== null) {
                $badge = $scola;
                $badgeCls = 'bg-gray-100 text-gray-600';
            } else {
                $badge = 'Aguardando coleta';
                $badgeCls = 'bg-gray-100 text-gray-500';
            }

            $code = (string) ($ev['ie_status_execucao'] ?? '');
            $tasyLabel = match ($code) {
                '10' => 'Solicitado', '15' => 'Em exame', '20' => 'Executado',
                default => $code ?: 'Pendente',
            };

            if ($res !== null) {
                $scolaLabel = $res;
            } elseif ($scola !== null && str_contains($scola, 'Laudo liberado')) {
                $scolaLabel = 'Laudo liberado (aguard. TASY)';
            } elseif ($scola === 'Resultado inserido (aguardando laudo)') {
                $scolaLabel = 'Resultado inserido, aguard. laudo';
            } elseif ($scola === 'Coletado (aguardando resultado)') {
                $scolaLabel = 'Coletado, aguard. resultado';
            } elseif ($scola === 'Nova coleta necessária') {
                $scolaLabel = 'Nova coleta necessária';
            } elseif ($scola === 'Solicitado (aguardando coleta)') {
                $scolaLabel = 'Aguardando coleta';
            } elseif ($scola !== null) {
                $scolaLabel = $scola;
            } else {
                $scolaLabel = 'Sem info SCOLA';
            }

            return array_merge($item, [
                'badge' => $badge,
                'badge_cls' => $badgeCls,
                'tasy_label' => $tasyLabel,
                'scola_label' => $scolaLabel,
                'display_name' => ucwords(strtolower($ev['descricao'] ?? 'Hemocultura')),
                'nr_prescricao' => $ev['nr_prescricao'] ?? null,
                'date' => $ev['dt_solicitacao'] ?? $ev['dt_coleta'] ?? null,
            ]);
        }

        $hist = $item['data'];
        $res = $hist['scola_resultado'] ?? null;
        $isPos = str_starts_with($res ?? '', 'Positivo');

        if ($isPos) {
            $badge = $res;
            $badgeCls = 'bg-red-100 text-red-700';
        } elseif ($res === 'Negativo') {
            $badge = 'Negativo';
            $badgeCls = 'bg-emerald-100 text-emerald-700';
        } elseif ($res !== null) {
            $badge = $res;
            $badgeCls = 'bg-santacasa-100/10 text-santacasa-200';
        } elseif ($hist['has_result'] ?? false) {
            $badge = 'Laudo integrado';
            $badgeCls = 'bg-santacasa-100/10 text-santacasa-200';
        } else {
            $badge = 'Realizada';
            $badgeCls = 'bg-gray-100 text-gray-600';
        }

        $code = (string) ($hist['status_execucao'] ?? '');
        $tasyLabel = match ($code) {
            '10' => 'Solicitado', '15' => 'Em exame', '20' => 'Executado',
            default => $code ?: '—',
        };
        $scolaLabel = $res ?? (($hist['has_result'] ?? false) ? 'Laudo integrado ao TASY' : 'Sem resultado SCOLA');

        return array_merge($item, [
            'badge' => $badge,
            'badge_cls' => $badgeCls,
            'tasy_label' => $tasyLabel,
            'scola_label' => $scolaLabel,
            'display_name' => ucwords(strtolower($hist['name'] ?: 'Hemocultura')),
            'nr_prescricao' => $hist['nr_prescricao'] ?? null,
            'date' => ($hist['dt_prescricao'] ?? '') ?: ($hist['date'] ?? ''),
        ]);
    }

    /** @return array{0: string|null, 1: string} */
    private function resolveCardBadge(
        ?string $latestResult,
        bool $latestHasResult,
        ?string $latestTasyCode,
        ?string $latestScolaStatus,
        bool $hemocPend,
        int $hemocActiveCount,
    ): array {
        if ($latestResult === 'Laudo integrado' || $latestHasResult) {
            return ['Laudo integrado', 'bg-santacasa-100/10 text-santacasa-200'];
        }
        if ($latestResult === 'Laudo liberado') {
            return ['Laudo liberado', 'bg-emerald-100 text-emerald-700'];
        }
        if ($latestResult === 'Resultado em análise') {
            return ['Resultado em análise', 'bg-amber-100 text-amber-700'];
        }
        if ($latestResult === 'Coletado') {
            return ['Coletado · aguard. laudo', 'bg-amber-100 text-amber-700'];
        }
        if ($latestScolaStatus !== null && str_contains($latestScolaStatus, 'Laudo liberado')) {
            return ['Laudo liberado', 'bg-emerald-100 text-emerald-700'];
        }
        if (in_array($latestScolaStatus, ['Coletado (aguardando resultado)', 'Resultado inserido (aguardando laudo)'], true)) {
            return ['Coletado · aguard. laudo', 'bg-amber-100 text-amber-700'];
        }
        if ($latestTasyCode === '20') {
            return ['Executado', 'bg-gray-100 text-gray-600'];
        }
        if ($hemocPend || $hemocActiveCount > 0) {
            return ['Aguard. coleta', 'bg-santacasa-100/10 text-santacasa-200'];
        }

        return [null, 'bg-gray-100 text-gray-600'];
    }
}
