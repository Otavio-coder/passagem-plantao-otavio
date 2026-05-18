<?php

namespace App\Http\Controllers;

use App\Models\System\UserSectorPreference;
use App\Services\PatientData\PatientDataLoader;
use App\Services\UserDisplayNameResolver;
use App\Support\PendingEventHelper;
use App\Support\PendingEventTypeClassifier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PendingEventsReportController extends Controller
{
    public function __construct(private readonly UserDisplayNameResolver $userDisplayNameResolver) {}

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
                'sectorsForFilter' => [],
                'rows' => collect(),
                'selectedHospital' => null,
                'selectedSectors' => [],
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

        $allowedSectorCodes = $sectors->pluck('sector_code')->map(fn ($c) => (int) $c);

        // Aceita sector_ids[] (multi) ou sector_id (legado single)
        $rawIds = $request->input('sector_ids', []);
        if (empty($rawIds) && $request->has('sector_id')) {
            $rawIds = [$request->integer('sector_id')];
        }

        $selectedSectors = collect($rawIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allowedSectorCodes->contains($id))
            ->unique()
            ->values()
            ->all();

        if (empty($selectedSectors) && $allowedSectorCodes->isNotEmpty()) {
            $selectedSectors = [$allowedSectorCodes->first()];
        }

        $rows = collect();

        foreach ($selectedSectors as $sectorId) {
            $patients = PatientDataLoader::forSector($sectorId)
                ->include('demographics', 'pending_events', 'multidisciplinary')
                ->get();

            $sectorLabel = (! empty($patients) ? ($patients[0]['ds_prescricao'] ?? $patients[0]['ds_setor_atendimento'] ?? null) : null)
                ?? $sectors->firstWhere('sector_code', $sectorId)['sector_name']
                ?? (string) $sectorId;

            $patients = array_map(fn (array $p) => array_merge($p, ['_setor_label' => $sectorLabel]), $patients);

            $rows = $rows->merge($this->buildRows(collect($patients)));
        }

        $rows = $rows->sortByDesc('sort_ts')->values();

        $sectorName = count($selectedSectors) === 1
            ? ($sectors->firstWhere('sector_code', $selectedSectors[0])['sector_name'] ?? null)
            : count($selectedSectors).' setores selecionados';

        // Pré-computa array simples para o Alpine.js — evita expressões complexas no Blade
        $sectorsForFilter = $sectors
            ->map(fn ($s) => ['code' => (int) $s['sector_code'], 'name' => (string) $s['sector_name']])
            ->values()
            ->all();

        $tipoLabels = $rows->pluck('tipo_label', 'tipo_evento')->filter()->unique()->sort()->all();
        $categorias = $rows->pluck('motivo_categoria')->filter()->unique()->sort()->values()->all();
        $classificacoes = $rows->pluck('classificacao')->filter()->unique()->sort()->values()->all();

        return view('pendencias.index', [
            'hospitals' => $hospitals,
            'sectors' => $sectors,
            'sectorsForFilter' => $sectorsForFilter,
            'rows' => $rows,
            'selectedHospital' => $selectedHospital,
            'selectedSectors' => $selectedSectors,
            'sectorName' => $sectorName,
            'totalRows' => $rows->count(),
            'tipoLabels' => $tipoLabels,
            'categorias' => $categorias,
            'classificacoes' => $classificacoes,
            'errorMessage' => null,
        ]);
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();

        $allSectors = UserSectorPreference::query()
            ->select(['hospital_code', 'hospital_name', 'sector_code', 'sector_name'])
            ->where('user_id', $user->id)
            ->distinct()
            ->get();

        $hospitals = $allSectors->map(fn ($p) => ['hospital_id' => (int) $p->hospital_code])->unique('hospital_id')->pluck('hospital_id');
        $selectedHospital = (int) $request->integer('hospital_id', (int) ($hospitals->first() ?? 0));

        $sectors = $allSectors
            ->filter(fn ($p) => (int) $p->hospital_code === $selectedHospital)
            ->map(fn ($p) => ['sector_code' => (int) $p->sector_code])
            ->unique('sector_code')
            ->pluck('sector_code');

        $rawIds = $request->input('sector_ids', []);
        if (empty($rawIds) && $request->has('sector_id')) {
            $rawIds = [$request->integer('sector_id')];
        }

        $selectedSectors = collect($rawIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $sectors->contains($id))
            ->unique()
            ->values()
            ->all();

        if (empty($selectedSectors) && $sectors->isNotEmpty()) {
            $selectedSectors = [$sectors->first()];
        }

        $rows = collect();

        foreach ($selectedSectors as $sectorId) {
            $patients = PatientDataLoader::forSector($sectorId)
                ->include('demographics', 'pending_events', 'multidisciplinary')
                ->get();

            $sectorLabel = (! empty($patients) ? ($patients[0]['ds_prescricao'] ?? $patients[0]['ds_setor_atendimento'] ?? null) : null) ?? (string) $sectorId;

            $patients = array_map(fn (array $p) => array_merge($p, ['_setor_label' => $sectorLabel]), $patients);

            $rows = $rows->merge($this->buildRows(collect($patients)));
        }

        $rows = $rows->sortByDesc('sort_ts')->values();

        $filename = 'pendencias_'.now()->format('Ymd_Hi').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Paciente', 'Leito', 'Atendimento', 'Unidade', 'Tipo', 'Classificação',
            'Pendência', 'Nr. Prescrição', 'Prescritor',
            'Status (Tasy)', 'Status (SCOLA)', 'Resultado Bacteriológico',
            'Data Prescrição', 'Lib. Médica', 'Lib. Prescrição', 'Prev. Execução', 'Data Coleta',
            'Em Aberto', 'Setor Execução', 'Vencido',
        ];

        $csvRows = $rows->map(fn (array $row): array => [
            $row['paciente'] ?? '',
            $row['ugb'] ?? '',
            $row['atendimento'] ?? '',
            $row['setor_origem'] ?? '',
            $row['tipo_label'] ?? '',
            $row['classificacao'] ?? '',
            $row['item'] ?? '',
            $row['nr_prescricao'] ?? '',
            $row['nm_prescritor'] ?? '',
            $row['motivo_pendente'] ?? '',
            $row['scola_status'] ?? '',
            $row['scola_resultado'] ?? '',
            $row['data_solicitacao'] ?? '',
            $row['data_lib_medica'] ?? '',
            $row['data_lib_prescricao'] ?? '',
            $row['data_prev_execucao'] ?? '',
            $row['data_coleta'] ?? '',
            $row['tempo_pendente'] ?? '',
            $row['setor_execucao'] ?? '',
            ($row['is_overdue'] ?? false) ? 'Sim' : 'Não',
        ])->all();

        $output = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns, ';');
        foreach ($csvRows as $csvRow) {
            fputcsv($handle, $csvRow, ';');
        }
        rewind($handle);
        $output .= stream_get_contents($handle);
        fclose($handle);

        return response($output, 200, $headers);
    }

    public function refresh(Request $request): RedirectResponse
    {
        $sectorIds = array_map('intval', (array) $request->input('sector_ids', []));
        if (empty($sectorIds) && $request->has('sector_id')) {
            $sectorIds = [(int) $request->integer('sector_id')];
        }

        foreach (array_filter($sectorIds) as $sectorId) {
            PatientDataLoader::forSector($sectorId)->clearCache();
        }

        $params = array_filter(['hospital_id' => $request->integer('hospital_id') ?: null]);
        foreach ($sectorIds as $id) {
            $params['sector_ids'][] = $id;
        }

        return redirect()->route('pending.report', $params);
    }

    private function buildRows(Collection $patients): Collection
    {
        $rows = collect();

        foreach ($patients as $patient) {
            if (! ($patient['has_patient'] ?? false)) {
                continue;
            }

            $sector = $patient['ds_prescricao'] ?? $patient['ds_setor_atendimento'] ?? '-';
            $base = [
                'atendimento' => $patient['nr_atendimento'] ?? '-',
                'paciente' => $patient['nm_pessoa_fisica'] ?? '-',
                'ugb' => $patient['cd_unidade_basica'] ?? '-',
                'uga' => $sector,
                'setor_origem' => $patient['_setor_label'] ?? $sector,
            ];

            foreach (($patient['pending_events'] ?? []) as $event) {
                $tipo = (string) ($event['tipo'] ?? '');
                if (in_array($tipo, ['alta', 'alta_medica', 'previsao_alta'], true)) {
                    continue;
                }

                $sortTs = $this->parseDateToTs($event['dt_evento'] ?? null)
                    ?? $this->parseDateToTs($event['dt_solicitacao'] ?? null)
                    ?? 0;

                $normalizedType = PendingEventTypeClassifier::fromPendingEvent($event);

                $isOverdue = $sortTs > 0 && $sortTs < time();
                $motivo = $this->computeMotivoPendente($normalizedType, $event);
                $rows->push(array_merge($base, [
                    'tipo_evento' => $normalizedType,
                    'tipo_label' => PendingEventTypeClassifier::label($normalizedType),
                    'setor_execucao' => PendingEventHelper::executionSectorLabel($event),
                    'item' => $this->normalizeItemLabel($event),
                    'classificacao' => PendingEventHelper::classificationLabel($event, $normalizedType),
                    'data_prev_execucao' => $this->shortDate($event['dt_evento'] ?? null),
                    'data_prev_execucao_sort' => $this->parseDateToTs($event['dt_evento'] ?? null) ?? 0,
                    'data_solicitacao' => $this->shortDate($event['dt_solicitacao'] ?? null),
                    'data_solicitacao_sort' => $this->parseDateToTs($event['dt_solicitacao'] ?? null) ?? 0,
                    'data_lib_prescricao' => $this->shortDate($event['dt_autorizacao'] ?? null),
                    'data_lib_prescricao_sort' => $this->parseDateToTs($event['dt_autorizacao'] ?? null) ?? 0,
                    'data_lib_medica' => in_array($normalizedType, ['exame', 'proc_exame']) ? $this->shortDate($event['dt_liberacao_medico'] ?? null) : null,
                    'data_lib_medica_sort' => in_array($normalizedType, ['exame', 'proc_exame']) ? ($this->parseDateToTs($event['dt_liberacao_medico'] ?? null) ?? 0) : 0,
                    'data_coleta' => $this->shortDate($event['dt_coleta'] ?? null),
                    'data_coleta_sort' => $this->parseDateToTs($event['dt_coleta'] ?? null) ?? 0,
                    'tempo_pendente' => $this->resolveTempoPendente(
                        $event['tempo_pendente'] ?? null,
                        $event['dt_solicitacao'] ?? ($event['dt_evento'] ?? null)
                    ),
                    'tempo_pendente_sort' => $sortTs > 0 ? (time() - $sortTs) : 0,
                    'status_execucao' => trim((string) ($event['status_laudo'] ?? '')),
                    'motivo_pendente' => $motivo,
                    'motivo_categoria' => $this->categorizarMotivo($motivo, $normalizedType),
                    'nr_prescricao' => $event['nr_prescricao'] ?? null,
                    'nm_prescritor' => trim((string) ($event['nm_prescritor'] ?? '')),
                    'scola_status' => $event['scola_status'] ?? null,
                    'scola_resultado' => $event['scola_resultado'] ?? null,
                    'scola_integration_issue' => $event['scola_integration_issue'] ?? false,
                    'sort_ts' => $sortTs,
                    'is_overdue' => $isOverdue,
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
                    'nm_responsavel_resposta_display' => $this->userDisplayNameResolver->fromName(
                        $req['nm_responsavel_resposta'] ?? null
                    ),
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
            $base = PendingEventHelper::surgeryDescription($event);
            $statusDetail = PendingEventHelper::surgeryDiagnosticLabel($event);

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

    private function computeMotivoPendente(string $normalizedType, array $event): string
    {
        return PendingEventHelper::motivoPendente($event);
    }

    private function categorizarMotivo(string $motivo, string $tipo): string
    {
        if ($tipo === 'antibiotico') {
            return 'Antimicrobiano pendente';
        }
        if ($tipo === 'consultoria') {
            return 'Aguardando resposta';
        }
        if (str_starts_with($motivo, 'Resultado disponível')) {
            return 'Resultado disponível';
        }
        if (str_contains($motivo, 'Laudo liberado no Scola')) {
            return 'Laudo liberado (SCOLA)';
        }
        if (str_contains($motivo, 'Laudo integrado no TASY')) {
            return 'Laudo integrado no TASY';
        }
        if (str_contains($motivo, 'Laudo disponível') || str_contains($motivo, 'Laudo disponível em solicitação')) {
            return 'Laudo disponível';
        }
        if (str_starts_with($motivo, 'Amostra rejeitada')) {
            return 'Amostra rejeitada pelo lab';
        }
        if (str_starts_with($motivo, 'Nova coleta')) {
            return 'Nova coleta necessária';
        }
        if (str_starts_with($motivo, 'Coletado')) {
            return 'Coletado — aguardando resultado';
        }
        if (str_starts_with($motivo, 'Aguardando coleta')) {
            return 'Aguardando coleta';
        }
        // Exames agendados via agenda_paciente → clinicamente equivale a "aguardando coleta"
        if (in_array($tipo, ['exame', 'proc_exame'], true)
            && (str_starts_with($motivo, 'Aguardando execução') || str_starts_with($motivo, 'Agendado'))
        ) {
            return 'Aguardando coleta';
        }
        if (str_starts_with($motivo, 'Aguardando laudo')) {
            return 'Aguardando laudo';
        }
        if (str_starts_with($motivo, 'Em execução') || str_starts_with($motivo, 'Em exame')) {
            return 'Em execução';
        }
        if (str_starts_with($motivo, 'Em preparo')) {
            return 'Em preparo';
        }
        if (str_starts_with($motivo, 'Urgente')) {
            return 'Urgente — aguardando coleta';
        }
        if (str_starts_with($motivo, 'Paciente chegou') || str_starts_with($motivo, 'Previsto — aguardando')) {
            return 'Aguardando execução';
        }
        if (str_starts_with($motivo, 'Em avaliação') || str_starts_with($motivo, 'Em complemento')) {
            return 'Em execução';
        }
        if (str_starts_with($motivo, 'Concluído') || str_starts_with($motivo, 'Executado — aguardando baixa')) {
            return 'Executado — baixa pendente';
        }
        if (str_starts_with($motivo, 'Aguardando atendimento') || str_starts_with($motivo, 'Em atendimento') || str_starts_with($motivo, 'Em recuperação')) {
            return 'Em atendimento';
        }
        if (str_starts_with($motivo, 'Aguardando aprovação')) {
            return 'Aguardando aprovação';
        }
        if (str_starts_with($motivo, 'Aguardando cirurgia') || str_starts_with($motivo, 'Cirurgia')) {
            return 'Aguardando cirurgia';
        }
        if (str_starts_with($motivo, 'Aguardando transfusão') || str_starts_with($motivo, 'Urgente — aguardando transfusão')) {
            return 'Aguardando transfusão';
        }
        if (str_starts_with($motivo, 'Sessão de quimioterapia')) {
            return 'Quimioterapia agendada';
        }
        if (str_starts_with($motivo, 'Aguardando execução') || str_starts_with($motivo, 'Agendado')) {
            return 'Aguardando execução';
        }

        return 'Outros';
    }

    private function shortDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        try {
            return Carbon::parse($date)->format('d/m H:i');
        } catch (\Throwable) {
            return null;
        }
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
