<?php

namespace App\Http\Controllers;

use App\Models\System\UserSectorPreference;
use App\Services\PatientPendingEventsService;
use App\Services\TasyService;
use App\Support\PendingEventPresentation;
use App\Support\PendingEventTypeClassifier;
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

        $allSectors = UserSectorPreference::query()
            ->select(['hospital_code', 'hospital_name', 'sector_code', 'sector_name'])
            ->where('user_id', $user->id)
            ->distinct()
            ->get();

        if ($allSectors->isEmpty()) {
            return view('pendencias.index', [
                'hospitals' => collect(),
                'sectors' => collect(),
                'rows' => collect(),
                'selectedHospital' => null,
                'selectedSector' => null,
                'sectorName' => null,
                'totalRows' => 0,
                'errorMessage' => 'Nenhum setor configurado no sistema. Solicite ao administrador.',
            ]);
        }

        $hospitals = $allSectors
            ->map(fn ($p) => [
                'hospital_id' => (int) $p->hospital_code,
                'hospital_name' => $p->hospital_name,
            ])
            ->unique('hospital_id')
            ->sortBy('hospital_name')
            ->values();

        $selectedHospital = (int) $request->integer('hospital_id', (int) ($hospitals->first()['hospital_id'] ?? 0));
        if (! $hospitals->pluck('hospital_id')->contains($selectedHospital)) {
            $selectedHospital = (int) ($hospitals->first()['hospital_id'] ?? 0);
        }

        $sectors = $allSectors
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
        if (! $sectors->pluck('sector_code')->contains($selectedSector)) {
            $selectedSector = (int) ($sectors->first()['sector_code'] ?? 0);
        }

        $rows = collect();
        $sectorName = null;

        if ($selectedSector > 0) {
            $tasy = new TasyService;
            $patients = $tasy->getSectorPatientsForSbar($selectedSector);

            // Substitui pending_events com dados frescos (sem cache) para o relatório
            $freshPending = (new PatientPendingEventsService)->getFreshEventsForSector($selectedSector);
            $patients = array_map(function (array $patient) use ($freshPending) {
                $patient['pending_events'] = $freshPending[$patient['nr_atendimento']]['events'] ?? [];

                return $patient;
            }, $patients);

            $sectorName = $patients[0]['ds_setor_atendimento'] ?? ($sectors->firstWhere('sector_code', $selectedSector)['sector_name'] ?? null);

            $rows = $this->buildRows(collect($patients))->sortByDesc('sort_ts')->values();
        }

        return view('pendencias.index', [
            'hospitals' => $hospitals,
            'sectors' => $sectors,
            'rows' => $rows,
            'selectedHospital' => $selectedHospital,
            'selectedSector' => $selectedSector,
            'sectorName' => $sectorName,
            'totalRows' => $rows->count(),
            'errorMessage' => null,
        ]);
    }

    private function buildRows(Collection $patients): Collection
    {
        $rows = collect();

        foreach ($patients as $patient) {
            if (! ($patient['has_patient'] ?? false)) {
                continue;
            }

            $sector = $patient['ds_setor_atendimento'] ?? '-';
            $base = [
                'atendimento' => $patient['nr_atendimento'] ?? '-',
                'paciente' => $patient['nm_pessoa_fisica'] ?? '-',
                'ugb' => $patient['cd_unidade_basica'] ?? '-',
                'uga' => $sector,
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

                $normalizedType = PendingEventTypeClassifier::fromPendingEvent($event);

                $rows->push(array_merge($base, [
                    'tipo_evento' => $normalizedType,
                    'tipo_label' => PendingEventTypeClassifier::label($normalizedType),
                    'setor_execucao' => PendingEventPresentation::executionSectorLabel($event),
                    'item' => $this->normalizeItemLabel($event),
                    'classificacao' => PendingEventPresentation::classificationLabel($event, $normalizedType),
                    'data_solicitacao' => $event['dt_solicitacao'] ?? '-',
                    'data_prev_execucao' => $event['dt_evento_formatted'] ?? '-',
                    'tempo_pendente' => $this->resolveTempoPendente(
                        $event['tempo_pendente'] ?? null,
                        $event['dt_solicitacao'] ?? ($event['dt_evento'] ?? null)
                    ),
                    'tempo_pendente_sort' => $sortTs > 0 ? (time() - $sortTs) : 0,
                    'status' => $status,
                    'motivo_pendente' => $this->computeMotivoPendente($normalizedType, $event, $status),
                    'laudo' => $normalizedType === PendingEventTypeClassifier::SURGERY
                        ? '-'
                        : ($statusLaudoExame !== '' ? $statusLaudoExame : '-'),
                    'sort_ts' => $sortTs,
                ]));
            }

            foreach (($patient['multidisciplinary_requests'] ?? []) as $req) {
                $status = (string) ($req['ds_status'] ?? $req['ie_status'] ?? 'Aberto');
                $jaRespondido = in_array(mb_strtolower($status), ['respondido', 'liberado', 'cancelado'], true)
                    || ! empty($req['dt_resposta']); // ie_situacao pode continuar 'A' mesmo com resposta registrada no Tasy
                if ($jaRespondido) {
                    continue;
                }

                $sortTs = $this->parseDateToTs($req['dt_liberacao'] ?? null)
                    ?? $this->parseDateToTs($req['dt_registro'] ?? null)
                    ?? 0;

                $rows->push(array_merge($base, [
                    'tipo_evento' => 'consultoria',
                    'tipo_label' => 'Consultoria',
                    'item' => 'Consultoria - '.($req['ds_equipe_destino'] ?? 'Equipe não informada'),
                    'classificacao' => null,
                    'setor_execucao' => '-',
                    'data_solicitacao' => ! empty($req['dt_registro']) ? Carbon::parse($req['dt_registro'])->format('d/m/Y H:i') : '-',
                    'data_prev_execucao' => '-',
                    'tempo_pendente' => $this->formatTempoPendente($req['dt_registro'] ?? null),
                    'tempo_pendente_sort' => $sortTs > 0 ? (time() - $sortTs) : 0,
                    'status' => $status,
                    'motivo_pendente' => 'Aguardando resposta',
                    'laudo' => ! empty($req['ds_parecer']) ? $this->truncate((string) $req['ds_parecer'], 120) : '-',
                    'sort_ts' => $sortTs,
                ]));
            }
        }

        return $rows;
    }

    private function normalizeItemLabel(array $event): string
    {
        $type = PendingEventTypeClassifier::fromPendingEvent($event);
        $subtipo = trim((string) ($event['ds_subtipo'] ?? ''));
        $descricao = trim((string) ($event['descricao'] ?? 'Sem descrição'));

        if ($type === PendingEventTypeClassifier::SURGERY) {
            $base = PendingEventPresentation::surgeryDescription($event);
            $statusDetail = PendingEventPresentation::surgeryDiagnosticLabel($event);

            if ($statusDetail !== '') {
                return $this->truncate($base.' | '.$statusDetail, 120);
            }

            return $this->truncate($base, 120);
        }

        if ($type === PendingEventTypeClassifier::CHEMOTHERAPY) {
            $base = $descricao !== 'Sem descrição' ? $descricao : 'Quimioterapia';

            if ($subtipo !== '' && mb_strtolower($subtipo) !== mb_strtolower($base)) {
                return $this->truncate($base.' - '.$subtipo, 120);
            }

            return $this->truncate($base, 120);
        }

        if ($descricao !== 'Sem descrição') {
            return $this->truncate($descricao, 120);
        }

        if ($subtipo !== '') {
            return $this->truncate($subtipo, 120);
        }

        return 'Pendência';
    }

    private function computeMotivoPendente(string $normalizedType, array $event, string $status): string
    {
        return PendingEventPresentation::motivoPendente($event);
    }

    private function parseDateToTs(?string $date): ?int
    {
        if (empty($date)) {
            return null;
        }
        try {
            return Carbon::parse($date)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatTempoPendente(?string $date): string
    {
        if (empty($date)) {
            return '-';
        }
        try {
            $start = Carbon::parse($date);
            $now = now();

            if ($start->greaterThan($now)) {
                $diffMinutes = (int) $now->diffInMinutes($start);

                if ($diffMinutes < 60) {
                    return 'em '.$diffMinutes.'min';
                }

                $diffHours = intdiv($diffMinutes, 60);

                return $diffHours < 24 ? 'em '.$diffHours.'h' : 'em '.intdiv($diffHours, 24).'d';
            }

            $diffMinutes = (int) $start->diffInMinutes($now);

            if ($diffMinutes < 60) {
                return $diffMinutes.'min em aberto';
            }

            $diffHours = intdiv($diffMinutes, 60);

            return $diffHours < 24 ? $diffHours.'h em aberto' : intdiv($diffHours, 24).'d em aberto';
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
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max - 1).'…';
    }
}
