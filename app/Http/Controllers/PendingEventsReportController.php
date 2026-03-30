<?php

namespace App\Http\Controllers;

use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PendingEventsReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $preferences = $user->sectorPreferences()
            ->get(['hospital_code', 'hospital_name', 'sector_code', 'sector_name']);

        if ($preferences->isEmpty()) {
            return view('pendencias.index', [
                'hospitals'       => collect(),
                'sectors'         => collect(),
                'rows'            => collect(),
                'selectedHospital'=> null,
                'selectedSector'  => null,
                'sectorName'      => null,
                'totalRows'       => 0,
                'errorMessage'    => 'Nenhum setor configurado para o seu usuário. Configure em Minhas Preferências.',
            ]);
        }

        $hospitals = $preferences
            ->map(fn ($p) => [
                'hospital_id'   => (int) $p->hospital_code,
                'hospital_name' => $p->hospital_name,
            ])
            ->unique('hospital_id')
            ->sortBy('hospital_name')
            ->values();

        $selectedHospital = (int) $request->integer('hospital_id', (int) ($hospitals->first()['hospital_id'] ?? 0));
        if (!$hospitals->pluck('hospital_id')->contains($selectedHospital)) {
            $selectedHospital = (int) ($hospitals->first()['hospital_id'] ?? 0);
        }

        $sectors = $preferences
            ->filter(fn ($p) => (int) $p->hospital_code === $selectedHospital)
            ->map(fn ($p) => [
                'sector_code' => (int) $p->sector_code,
                'sector_name' => $p->sector_name,
                'hospital_id' => (int) $p->hospital_code,
            ])
            ->unique('sector_code')
            ->sortBy('sector_name')
            ->values();

        $selectedSector = (int) $request->integer('sector_id', (int) ($sectors->first()['sector_code'] ?? 0));
        if (!$sectors->pluck('sector_code')->contains($selectedSector)) {
            $selectedSector = (int) ($sectors->first()['sector_code'] ?? 0);
        }

        $rows = collect();
        $sectorName = null;

        if ($selectedSector > 0) {
            $tasy = new TasyService();
            $patients = $tasy->getSectorPatientsForSbar($selectedSector);

            $sectorName = $patients[0]['ds_setor_atendimento'] ?? ($sectors->firstWhere('sector_code', $selectedSector)['sector_name'] ?? null);

            $rows = $this->buildRows(collect($patients))->sortByDesc('sort_ts')->values();
        }

        return view('pendencias.index', [
            'hospitals'        => $hospitals,
            'sectors'          => $sectors,
            'rows'             => $rows,
            'selectedHospital' => $selectedHospital,
            'selectedSector'   => $selectedSector,
            'sectorName'       => $sectorName,
            'totalRows'        => $rows->count(),
            'errorMessage'     => null,
        ]);
    }

    private function buildRows(Collection $patients): Collection
    {
        $rows = collect();

        foreach ($patients as $patient) {
            if (!($patient['has_patient'] ?? false)) {
                continue;
            }

            $base = [
                'atendimento' => $patient['nr_atendimento'] ?? '-',
                'paciente'    => $patient['nm_pessoa_fisica'] ?? '-',
                'ugb'         => $patient['cd_unidade_basica'] ?? '-',
            ];

            foreach (($patient['pending_events'] ?? []) as $event) {
                $tipo = (string) ($event['tipo'] ?? '');
                if (in_array($tipo, ['alta', 'alta_medica', 'previsao_alta'], true)) {
                    continue;
                }

                $statusExecucao = trim((string) ($event['status_laudo'] ?? ''));
                $statusLaudoExame = trim((string) ($event['ds_status_laudo'] ?? ''));

                $status = ($statusExecucao !== '')
                    ? $statusExecucao
                    : (($event['urgente'] ?? false) ? 'Urgente' : 'Pendente');

                // Para exames, prioriza o status do laudo quando disponível.
                if (in_array($tipo, ['proc_exame', 'exame'], true) && $statusLaudoExame !== '') {
                    $status = $statusLaudoExame;
                }

                $sortTs = $this->parseDateToTs($event['dt_evento'] ?? null)
                    ?? $this->parseDateToTs($event['dt_solicitacao'] ?? null)
                    ?? 0;

                $rows->push(array_merge($base, [
                    'item'             => $this->normalizeItemLabel($event),
                    'classificacao'    => $event['ds_grupo_lab'] ?? null,
                    'data_solicitacao' => $event['dt_solicitacao'] ?? '-',
                    'data_agendamento' => $event['dt_evento_formatted'] ?? '-',
                    'tempo_pendente'   => $this->resolveTempoPendente(
                        $event['tempo_pendente'] ?? null,
                        $event['dt_solicitacao'] ?? ($event['dt_evento'] ?? null)
                    ),
                    'status'           => $status,
                    'laudo'            => $statusLaudoExame !== '' ? $statusLaudoExame : ($event['ds_complemento'] ?? '-'),
                    'sort_ts'          => $sortTs,
                ]));
            }

            foreach (($patient['multidisciplinary_requests'] ?? []) as $req) {
                $status = (string) ($req['ds_status'] ?? $req['ie_status'] ?? 'Aberto');
                if (mb_strtolower($status) === 'respondido') {
                    continue;
                }

                $sortTs = $this->parseDateToTs($req['dt_liberacao'] ?? null)
                    ?? $this->parseDateToTs($req['dt_registro'] ?? null)
                    ?? 0;

                $rows->push(array_merge($base, [
                    'item'             => 'Consultoria - ' . ($req['ds_equipe_destino'] ?? 'Equipe não informada'),
                    'classificacao'    => null,
                    'data_solicitacao' => !empty($req['dt_registro']) ? Carbon::parse($req['dt_registro'])->format('d/m/Y H:i') : '-',
                    'data_agendamento' => '-',
                    'tempo_pendente'   => $this->formatTempoPendente($req['dt_registro'] ?? null),
                    'status'           => $status,
                    'laudo'            => !empty($req['ds_parecer']) ? $this->truncate((string) $req['ds_parecer'], 120) : '-',
                    'sort_ts'          => $sortTs,
                ]));
            }
        }

        return $rows;
    }

    private function normalizeItemLabel(array $event): string
    {
        $tipo     = (string) ($event['tipo'] ?? '');
        $subtipo  = trim((string) ($event['ds_subtipo'] ?? ''));
        $descricao = trim((string) ($event['descricao'] ?? 'Sem descrição'));

        $prefix = match ($tipo) {
            'proc_exame', 'exame' => 'Exame/Laboratório',
            'cirurgia'            => 'Procedimento/Cirurgia',
            'quimioterapia'       => 'Quimioterapia',
            'hemoterapia'         => 'Hemoterapia',
            'antibiotico'         => 'Antimicrobiano',
            default               => 'Pendência',
        };

        // Evita repetições consecutivas (ex: Quimioterapia - Quimioterapia - Quimioterapia - ...)
        $parts = [$prefix];
        if ($subtipo !== '' && mb_strtolower($subtipo) !== mb_strtolower($prefix)) {
            $parts[] = $subtipo;
        }
        // Só adiciona descrição se for diferente do prefixo e do subtipo
        if (
            $descricao !== '' &&
            mb_strtolower($descricao) !== mb_strtolower($prefix) &&
            ($subtipo === '' || mb_strtolower($descricao) !== mb_strtolower($subtipo))
        ) {
            $parts[] = $this->truncate($descricao, 120);
        }
        return implode(' - ', $parts);
    }

    private function parseDateToTs(?string $date): ?int
    {
        if (empty($date)) return null;
        try {
            return Carbon::parse($date)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatTempoPendente(?string $date): string
    {
        if (empty($date)) return '-';
        try {
            $start = Carbon::parse($date);
            $now   = now();

            if ($start->greaterThan($now)) {
                $diffMinutes = (int) $now->diffInMinutes($start);

                if ($diffMinutes < 60) {
                    return 'em ' . $diffMinutes . 'min';
                }

                $diffHours = intdiv($diffMinutes, 60);
                return $diffHours < 24 ? 'em ' . $diffHours . 'h' : 'em ' . intdiv($diffHours, 24) . 'd';
            }

            $diffMinutes = (int) $start->diffInMinutes($now);

            if ($diffMinutes < 60) {
                return $diffMinutes . 'min em aberto';
            }

            $diffHours = intdiv($diffMinutes, 60);
            return $diffHours < 24 ? $diffHours . 'h em aberto' : intdiv($diffHours, 24) . 'd em aberto';
        } catch (\Throwable) {
            return '-';
        }
    }

    private function resolveTempoPendente(?string $tempoOriginal, ?string $referenceDate): string
    {
        $tempoOriginal = trim((string) $tempoOriginal);

        if ($tempoOriginal === '') {
            return $this->formatTempoPendente($referenceDate);
        }

        if (preg_match('/^0\s*h\b/i', $tempoOriginal) === 1) {
            return $this->formatTempoPendente($referenceDate);
        }

        return $tempoOriginal;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max - 1) . '…';
    }
}
