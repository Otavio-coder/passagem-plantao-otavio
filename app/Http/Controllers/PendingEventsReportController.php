<?php

namespace App\Http\Controllers;

use App\Models\NurseHandoverBed;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
                'selectedHospitals' => [],
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

        $allHospitalIds = $hospitals->pluck('hospital_id');

        // Aceita hospital_ids[] (multi) ou hospital_id (legado single)
        $rawHospitalIds = $request->input('hospital_ids', []);
        if (empty($rawHospitalIds) && $request->has('hospital_id')) {
            $rawHospitalIds = [$request->integer('hospital_id')];
        }
        $selectedHospitals = collect($rawHospitalIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allHospitalIds->contains($id))
            ->unique()->values()->all();
        if (empty($selectedHospitals)) {
            $selectedHospitals = [$allHospitalIds->first()];
        }

        $sectors = $allSectors
            ->filter(fn ($p) => in_array((int) $p->hospital_code, $selectedHospitals, true))
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

        $onlyAssignedBeds = (bool) $user->only_assigned_beds;

        foreach ($selectedSectors as $sectorId) {
            $userCached = $onlyAssignedBeds
                ? Cache::get("sector_patients_{$sectorId}_{$user->id}")
                : null;

            if ($userCached !== null) {
                $patients = $userCached;
            } else {
                $patients = PatientDataLoader::forSector($sectorId)
                    ->include('demographics', 'pending_events', 'multidisciplinary')
                    ->get();

                if ($onlyAssignedBeds) {
                    $assignedBeds = NurseHandoverBed::where('user_id', $user->id)
                        ->where('sector_id', $sectorId)
                        ->pluck('bed_code')
                        ->toArray();

                    if (! empty($assignedBeds)) {
                        $patients = array_values(array_filter(
                            $patients,
                            fn (array $p) => in_array($p['cd_unidade_basica'] ?? '', $assignedBeds, true)
                        ));
                    }
                }
            }

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

        $this->logAccess('view', ['sector_ids' => $selectedSectors, 'hospital_ids' => $selectedHospitals]);

        return view('pendencias.index', [
            'hospitals' => $hospitals,
            'sectors' => $sectors,
            'sectorsForFilter' => $sectorsForFilter,
            'rows' => $rows,
            'selectedHospitals' => $selectedHospitals,
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

        $allHospitalIds = $allSectors->map(fn ($p) => (int) $p->hospital_code)->unique()->values();

        $rawHospitalIds = $request->input('hospital_ids', []);
        if (empty($rawHospitalIds) && $request->has('hospital_id')) {
            $rawHospitalIds = [$request->integer('hospital_id')];
        }
        $selectedHospitals = collect($rawHospitalIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allHospitalIds->contains($id))
            ->unique()->values()->all();
        if (empty($selectedHospitals)) {
            $selectedHospitals = [$allHospitalIds->first()];
        }

        $allowedSectors = $allSectors
            ->filter(fn ($p) => in_array((int) $p->hospital_code, $selectedHospitals, true))
            ->map(fn ($p) => (int) $p->sector_code)
            ->unique()->values();

        $rawIds = $request->input('sector_ids', []);
        if (empty($rawIds) && $request->has('sector_id')) {
            $rawIds = [$request->integer('sector_id')];
        }

        $selectedSectors = collect($rawIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allowedSectors->contains($id))
            ->unique()
            ->values()
            ->all();

        if (empty($selectedSectors) && $allowedSectors->isNotEmpty()) {
            $selectedSectors = [$allowedSectors->first()];
        }

        $rows = collect();

        $onlyAssignedBeds = (bool) $user->only_assigned_beds;

        foreach ($selectedSectors as $sectorId) {
            $userCached = $onlyAssignedBeds
                ? Cache::get("sector_patients_{$sectorId}_{$user->id}")
                : null;

            if ($userCached !== null) {
                $patients = $userCached;
            } else {
                $patients = PatientDataLoader::forSector($sectorId)
                    ->include('demographics', 'pending_events', 'multidisciplinary')
                    ->get();

                if ($onlyAssignedBeds) {
                    $assignedBeds = NurseHandoverBed::where('user_id', $user->id)
                        ->where('sector_id', $sectorId)
                        ->pluck('bed_code')
                        ->toArray();

                    if (! empty($assignedBeds)) {
                        $patients = array_values(array_filter(
                            $patients,
                            fn (array $p) => in_array($p['cd_unidade_basica'] ?? '', $assignedBeds, true)
                        ));
                    }
                }
            }

            $sectorLabel = (! empty($patients) ? ($patients[0]['ds_prescricao'] ?? $patients[0]['ds_setor_atendimento'] ?? null) : null) ?? (string) $sectorId;

            $patients = array_map(fn (array $p) => array_merge($p, ['_setor_label' => $sectorLabel]), $patients);

            $rows = $rows->merge($this->buildRows(collect($patients)));
        }

        $rows = $rows->sortByDesc('sort_ts')->values();

        $hospitalNames = $allSectors
            ->filter(fn ($p) => in_array((int) $p->hospital_code, $selectedHospitals, true))
            ->mapWithKeys(fn ($p) => [(int) $p->hospital_code => $p->hospital_name])
            ->unique()
            ->all();

        $sectorNames = $allSectors
            ->filter(fn ($p) => in_array((int) $p->sector_code, $selectedSectors, true))
            ->mapWithKeys(fn ($p) => [(int) $p->sector_code => $p->sector_name])
            ->unique()
            ->all();

        $exportFilename = 'pendencias_'.now()->format('Ymd_Hi').'.csv';

        Log::channel('audit')->info('report.pending_events.export', [
            'category' => 'report_export',
            'user_id' => $user->id,
            'user' => $user->name,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'hospitals' => $hospitalNames,
            'sectors' => $sectorNames,
            'row_count' => $rows->count(),
            'filename' => $exportFilename,
            'occurred_at' => now()->toIso8601String(),
        ]);

        $filename = $exportFilename;

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Paciente', 'Leito', 'Atendimento', 'Unidade', 'Prev. Alta', 'Tipo', 'Classificação',
            'Pendência', 'Nr. Prescrição', 'Prescritor',
            'Status (Tasy)', 'Status (SCOLA)', 'Resultado Bacteriológico',
            'Data Prescrição', 'Data Coleta', 'Resultado (Tasy)',
            'Em Aberto', 'Vencido',
        ];

        $csvRows = $rows->map(fn (array $row): array => [
            $row['paciente'] ?? '',
            $row['ugb'] ?? '',
            $row['atendimento'] ?? '',
            $row['setor_origem'] ?? '',
            $row['prev_alta'] ?? '',
            $row['tipo_label'] ?? '',
            $row['classificacao'] ?? '',
            $row['item'] ?? '',
            $row['nr_prescricao'] ?? '',
            $row['nm_prescritor'] ?? '',
            $row['motivo_pendente'] ?? '',
            $row['scola_status'] ?? '',
            $row['scola_resultado'] ?? '',
            $row['data_solicitacao'] ?? '',
            $row['data_coleta'] ?? '',
            $row['data_resultado'] ?? '',
            $row['tempo_pendente'] ?? '',
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

        $this->logAccess('export');

        return response($output, 200, $headers);
    }

    public function refresh(Request $request): RedirectResponse
    {
        $this->logAccess('refresh');

        $sectorIds = array_map('intval', (array) $request->input('sector_ids', []));
        if (empty($sectorIds) && $request->has('sector_id')) {
            $sectorIds = [(int) $request->integer('sector_id')];
        }

        foreach (array_filter($sectorIds) as $sectorId) {
            PatientDataLoader::forSector($sectorId)->clearCache();
        }

        $hospitalIds = array_map('intval', (array) $request->input('hospital_ids', []));
        if (empty($hospitalIds) && $request->has('hospital_id')) {
            $hospitalIds = [(int) $request->integer('hospital_id')];
        }

        $params = [];
        foreach (array_filter($hospitalIds) as $hid) {
            $params['hospital_ids'][] = $hid;
        }
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

            $dischargeInfo = $patient['discharge_info'] ?? null;
            $prevAlta = null;
            if (is_array($dischargeInfo) && ($dischargeInfo['tipo'] ?? '') === 'previsao_alta') {
                $prevAlta = $dischargeInfo['dt_previsto_alta_formatted'] ?? $this->shortDate($dischargeInfo['dt_previsto_alta'] ?? null);
            }

            $base['prev_alta'] = $prevAlta;

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
                    'item' => $this->normalizeItemLabel($event),
                    'classificacao' => PendingEventHelper::classificationLabel($event, $normalizedType),
                    'data_solicitacao' => $this->shortDate($event['dt_solicitacao'] ?? null),
                    'data_solicitacao_sort' => $this->parseDateToTs($event['dt_solicitacao'] ?? null) ?? 0,
                    'data_coleta' => $this->shortDate($event['dt_coleta'] ?? null),
                    'data_coleta_sort' => $this->parseDateToTs($event['dt_coleta'] ?? null) ?? 0,
                    'data_resultado' => $this->shortDate($event['dt_resultado'] ?? null),
                    'data_resultado_sort' => $this->parseDateToTs($event['dt_resultado'] ?? null) ?? 0,
                    'tempo_pendente' => $this->resolvePendingDuration(
                        $event['tempo_pendente'] ?? null,
                        $event['dt_solicitacao'] ?? ($event['dt_evento'] ?? null)
                    ),
                    'tempo_pendente_sort' => $sortTs > 0 ? (time() - $sortTs) : 0,
                    'status_execucao' => trim((string) ($event['status_laudo'] ?? '')),
                    'motivo_pendente' => $motivo,
                    'motivo_categoria' => $this->categorizarMotivo($motivo, $normalizedType, $event),
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
                    'data_solicitacao' => ! empty($req['dt_registro']) ? Carbon::parse($req['dt_registro'])->format('d/m/Y H:i') : '-',
                    'data_prev_execucao' => '-',
                    'tempo_pendente' => $this->formatPendingDuration($req['dt_registro'] ?? null),
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

    /** @param array<string, mixed> $event */
    private function categorizarMotivo(string $motivo, string $tipo, array $event): string
    {
        if ($tipo === 'antibiotico') {
            return 'Antimicrobiano pendente';
        }
        if ($tipo === 'consultoria') {
            return 'Aguardando resposta';
        }

        // Scola-enriched: usa campos estruturados em vez de parsear $motivo
        if (isset($event['scola_status']) && $event['scola_status'] !== '') {
            if (! empty($event['scola_data_liberado']) && empty($event['scola_data_exportado'])) {
                return 'Laudo liberado (SCOLA)';
            }
            if (! empty($event['scola_data_liberado'])) {
                return 'Laudo disponível';
            }
            if (! empty($event['scola_rejected'])) {
                return 'Amostra rejeitada pelo lab';
            }
            if (! empty($event['scola_nova_coleta'])) {
                return 'Nova coleta necessária';
            }
            if (! empty($event['scola_data_colheita'])) {
                return trim((string) ($event['scola_resultado'] ?? '')) !== ''
                    ? 'Resultado disponível'
                    : 'Coletado — aguardando resultado';
            }

            return (bool) ($event['urgente'] ?? false)
                ? 'Urgente — aguardando coleta'
                : 'Aguardando coleta';
        }

        // Usa ie_status_execucao (domínio 1226) para exames e procedimentos
        if (in_array($tipo, ['exame', 'proc_exame', 'procedimento'], true)) {
            $code = trim((string) ($event['ie_status_execucao'] ?? ''));
            $isProcedimento = $tipo === 'procedimento';

            if (! $isProcedimento && ! empty($event['dt_coleta'])) {
                return trim((string) ($event['scola_resultado'] ?? '')) !== ''
                    ? 'Resultado disponível'
                    : 'Aguardando laudo';
            }
            if (in_array($code, ['20', '22', '25', '26', '28', '29', '30', '35', '45'], true)) {
                return 'Aguardando laudo';
            }
            // Procedimentos: código 14 = em preparo, 15 = em exame (na sala)
            if ($isProcedimento) {
                if ($code === '14') {
                    return 'Em preparo';
                }
                if (in_array($code, ['15', '17', '18', '19'], true)) {
                    return 'Em exame';
                }
            } else {
                if (in_array($code, ['14', '15', '17', '18', '19'], true)) {
                    return 'Em execução';
                }
                if (in_array($code, ['10', '11', '12'], true)) {
                    return 'Em preparo';
                }
                if (($event['_fonte'] ?? '') === 'agenda') {
                    return 'Aguardando coleta';
                }
            }

            return (bool) ($event['urgente'] ?? false)
                ? 'Urgente — aguardando execução'
                : 'Aguardando execução';
        }

        // Demais tipos: mapeamento por $motivo (strings produzidas por funções determinísticas)
        if (str_starts_with($motivo, 'Concluído') || str_starts_with($motivo, 'Executado — aguardando baixa')) {
            return 'Executado — baixa pendente';
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
        if (str_starts_with($motivo, 'Aguardando atendimento') || str_starts_with($motivo, 'Em atendimento') || str_starts_with($motivo, 'Em recuperação')) {
            return 'Em atendimento';
        }
        if (str_starts_with($motivo, 'Aguardando aprovação')) {
            return 'Aguardando aprovação';
        }
        if (str_starts_with($motivo, 'Paciente chegou') || str_starts_with($motivo, 'Previsto — aguardando')) {
            return 'Aguardando execução';
        }
        if (str_starts_with($motivo, 'Aguardando execução') || str_starts_with($motivo, 'Agendado')) {
            return 'Aguardando execução';
        }
        if (str_starts_with($motivo, 'Em execução') || str_starts_with($motivo, 'Em avaliação') || str_starts_with($motivo, 'Em complemento')) {
            return 'Em execução';
        }
        if (str_starts_with($motivo, 'Urgente')) {
            return 'Urgente — aguardando coleta';
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

    private function formatPendingDuration(?string $date): string
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

    private function resolvePendingDuration(?string $tempoOriginal, ?string $referenceDate): string
    {
        $tempoOriginal = trim((string) $tempoOriginal);

        if ($tempoOriginal === '') {
            return $this->formatPendingDuration($referenceDate);
        }

        if (preg_match('/^0\s*h\b/i', $tempoOriginal) === 1) {
            return $this->formatPendingDuration($referenceDate);
        }

        return $tempoOriginal;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) <= $max ? $value : mb_substr($value, 0, $max - 1).'…';
    }

    private function logAccess(string $event, array $context = []): void
    {
        try {
            DB::table('pending_events_access_logs')->insert([
                'user_id' => Auth::id(),
                'event' => $event,
                'context' => $context ? json_encode($context) : null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable) {
            // não bloqueia a requisição se o log falhar
        }
    }
}
