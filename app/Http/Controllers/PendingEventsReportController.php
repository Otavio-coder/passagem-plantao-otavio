<?php

namespace App\Http\Controllers;

use App\Models\NurseHandoverBed;
use App\Models\System\UserSectorPreference;
use App\Services\PatientData\PatientDataLoader;
use App\Services\UserDisplayNameResolver;
use App\Support\PendingEventHelper;
use App\Support\PendingEventTypeClassifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
        $selection = $this->resolveSelection($request);

        if ($selection === null) {
            return view('pending.index', [
                'hospitals' => collect(),
                'sectors' => collect(),
                'sectorsForFilter' => [],
                'selectedHospitals' => [],
                'selectedSectors' => [],
                'sectorName' => null,
                'errorMessage' => 'Nenhum setor configurado no sistema. Solicite ao administrador.',
            ]);
        }

        ['allSectors' => $allSectors, 'selectedHospitals' => $selectedHospitals, 'selectedSectors' => $selectedSectors] = $selection;

        $hospitals = $allSectors
            ->map(fn ($p) => ['hospital_id' => (int) $p->hospital_code, 'hospital_name' => $p->hospital_name])
            ->unique('hospital_id')->sortBy('hospital_name')->values();

        $sectors = $allSectors
            ->filter(fn ($p) => in_array((int) $p->hospital_code, $selectedHospitals, true))
            ->map(fn ($p) => ['sector_code' => (int) $p->sector_code, 'sector_name' => $p->sector_name, 'hospital_id' => (int) $p->hospital_code])
            ->unique('sector_code')->sortBy('sector_name')->values();

        $sectorName = count($selectedSectors) === 1
            ? ($sectors->firstWhere('sector_code', $selectedSectors[0])['sector_name'] ?? null)
            : count($selectedSectors).' setores selecionados';

        $sectorsForFilter = $sectors
            ->map(fn ($s) => ['code' => (int) $s['sector_code'], 'name' => (string) $s['sector_name']])
            ->values()->all();

        return view('pending.index', [
            'hospitals' => $hospitals,
            'sectors' => $sectors,
            'sectorsForFilter' => $sectorsForFilter,
            'selectedHospitals' => $selectedHospitals,
            'selectedSectors' => $selectedSectors,
            'sectorName' => $sectorName,
            'errorMessage' => null,
        ]);
    }

    public function export(Request $request): Response
    {
        $user = Auth::user();

        $selection = $this->resolveSelection($request);
        $allSectors = $selection['allSectors'] ?? collect();
        $selectedHospitals = $selection['selectedHospitals'] ?? [];
        $selectedSectors = $selection['selectedSectors'] ?? [];

        $rows = collect();

        $onlyAssignedBeds = (bool) $user->only_assigned_beds;

        foreach ($selectedSectors as $sectorId) {
            $patients = $this->loadSectorPatients($sectorId, $user, $onlyAssignedBeds);
            $sectorLabel = (! empty($patients)
                ? ($patients[0]['ds_prescricao'] ?? $patients[0]['ds_setor_atendimento'] ?? null)
                : null) ?? (string) $sectorId;
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
            'Status', 'Critério', 'SCOLA', 'Resultado Bacteriológico',
            'Pendência', 'Nr. Prescrição', 'Prescritor',
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
            $row['status_execucao'] ?? '',
            $row['criterio_pendencia'] ?? '',
            $row['scola_status'] ?? '',
            $row['scola_resultado'] ?? '',
            $row['item'] ?? '',
            $row['nr_prescricao'] ?? '',
            $row['nm_prescritor'] ?? '',
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

    public function jsonData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $selectedSectors = $this->resolveAuthorizedSectorIds($request, $user);

        if (empty($selectedSectors)) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0, 'tipo_labels' => [], 'categorias' => [], 'classificacoes' => []],
            ]);
        }

        $rows = collect();
        $onlyAssignedBeds = (bool) $user->only_assigned_beds;

        foreach ($selectedSectors as $sectorId) {
            try {
                $patients = $this->loadSectorPatients($sectorId, $user, $onlyAssignedBeds);
            } catch (\Throwable $e) {
                Log::error('PendingEventsReportController: failed to load sector', [
                    'sector_id' => $sectorId,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
            $sectorLabel = (! empty($patients)
                ? ($patients[0]['ds_prescricao'] ?? $patients[0]['ds_setor_atendimento'] ?? null)
                : null) ?? (string) $sectorId;
            $patients = array_map(fn (array $p) => array_merge($p, ['_setor_label' => $sectorLabel]), $patients);
            $rows = $rows->merge($this->buildRows(collect($patients)));
        }

        $rows = $rows->sortByDesc('sort_ts')->values();

        $data = $rows->map(function (array $row): array {
            $motCat = $row['motivo_categoria'] ?? '';

            return [
                'paciente' => $row['paciente'] ?? '-',
                'ugb' => $row['ugb'] ?? '-',
                'atendimento' => $row['atendimento'] ?? '-',
                'tipo_label' => $row['tipo_label'] ?? '-',
                'classificacao' => $row['classificacao'] ?? '',
                'motivo_categoria' => $motCat,
                'badge_cls' => $this->buildBadgeCls($motCat),
                'motivo_pendente' => $row['motivo_pendente'] ?? '',
                'is_exam' => in_array($row['tipo_evento'] ?? '', ['exame', 'proc_exame'], true),
                'scola_status' => $row['scola_status'] ?? '',
                'scola_resultado' => $row['scola_resultado'] ?? '',
                'scola_integration_issue' => (bool) ($row['scola_integration_issue'] ?? false),
                'item' => $row['item'] ?? '',
                'nr_prescricao' => $row['nr_prescricao'] ?? '',
                'nm_prescritor' => $row['nm_prescritor'] ?? '',
                'status_execucao' => $row['status_execucao'] ?? '',
                'criterio_pendencia' => $row['criterio_pendencia'] ?? '',
                'data_solicitacao' => $row['data_solicitacao'] ?? '',
                'data_solicitacao_sort' => $row['data_solicitacao_sort'] ?? 0,
                'data_coleta' => $row['data_coleta'] ?? '',
                'data_coleta_sort' => $row['data_coleta_sort'] ?? 0,
                'data_resultado' => $row['data_resultado'] ?? '',
                'data_resultado_sort' => $row['data_resultado_sort'] ?? 0,
                'tempo_pendente' => $row['tempo_pendente'] ?? '-',
                'tempo_pendente_sort' => $row['tempo_pendente_sort'] ?? 0,
                'setor_origem' => $row['setor_origem'] ?? '-',
                'prev_alta' => $row['prev_alta'] ?? '',
                'tipo_evento' => $row['tipo_evento'] ?? '',
                'is_overdue' => (bool) ($row['is_overdue'] ?? false),
                'is_oculto' => (bool) ($row['is_oculto'] ?? false),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => count($data),
                'tipo_labels' => $rows->pluck('tipo_label', 'tipo_evento')->filter()->unique()->sort()->all(),
                'categorias' => $rows->pluck('motivo_categoria')->filter()->unique()->sort()->values()->all(),
                'classificacoes' => $rows->pluck('classificacao')->filter()->unique()->sort()->values()->all(),
            ],
        ]);
    }

    /**
     * Resolves hospital and sector selection from request for the authenticated user.
     * Returns null when the user has no configured sectors.
     *
     * @return array{allSectors: Collection, selectedHospitals: int[], selectedSectors: int[]}|null
     */
    private function resolveSelection(Request $request): ?array
    {
        $user = Auth::user();

        $allSectors = UserSectorPreference::query()
            ->select(['hospital_code', 'hospital_name', 'sector_code', 'sector_name'])
            ->where('user_id', $user->id)
            ->distinct()
            ->get();

        if ($allSectors->isEmpty()) {
            return null;
        }

        $allHospitalIds = $allSectors->map(fn ($p) => (int) $p->hospital_code)->unique()->values();

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

        $allowedSectorCodes = $allSectors
            ->filter(fn ($p) => in_array((int) $p->hospital_code, $selectedHospitals, true))
            ->map(fn ($p) => (int) $p->sector_code)
            ->unique()->values();

        // Aceita sector_ids[] (multi) ou sector_id (legado single)
        $rawIds = $request->input('sector_ids', []);
        if (empty($rawIds) && $request->has('sector_id')) {
            $rawIds = [$request->integer('sector_id')];
        }
        $selectedSectors = collect($rawIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allowedSectorCodes->contains($id))
            ->unique()->values()->all();
        if (empty($selectedSectors) && $allowedSectorCodes->isNotEmpty()) {
            $selectedSectors = [$allowedSectorCodes->first()];
        }

        return compact('allSectors', 'selectedHospitals', 'selectedSectors');
    }

    private function resolveAuthorizedSectorIds(Request $request, $user): array
    {
        $allowed = UserSectorPreference::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('sector_code')
            ->map(fn ($c) => (int) $c);

        $rawIds = $request->input('sector_ids', []);
        if (empty($rawIds) && $request->has('sector_id')) {
            $rawIds = [$request->integer('sector_id')];
        }

        return collect($rawIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allowed->contains($id))
            ->unique()
            ->values()
            ->all();
    }

    private function loadSectorPatients(int $sectorId, $user, bool $onlyAssignedBeds): array
    {
        $userCached = $onlyAssignedBeds
            ? Cache::get("sector_patients_{$sectorId}_{$user->id}")
            : null;

        if ($userCached !== null) {
            return $userCached;
        }

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

        return $patients;
    }

    private function buildBadgeCls(string $motCat): string
    {
        if (str_starts_with($motCat, 'Urgente')) {
            return 'badge-urgente';
        }
        if ($motCat === 'Antimicrobiano pendente') {
            return 'badge-antimicrobiano';
        }
        if (str_contains($motCat, 'laudo') || str_contains($motCat, 'Laudo')
            || str_contains($motCat, 'conferência') || str_contains($motCat, 'revisão')
            || str_contains($motCat, 'digitação') || str_contains($motCat, 'Envelopado')
            || $motCat === 'Executado' || $motCat === 'Exame concluído') {
            return 'badge-laudo';
        }
        if (str_starts_with($motCat, 'Em ') || str_contains($motCat, 'preparo')
            || str_contains($motCat, 'andamento') || str_contains($motCat, 'recuperação')
            || str_contains($motCat, 'avaliação') || str_contains($motCat, 'complemento')) {
            return 'badge-execucao';
        }
        if (str_contains($motCat, 'coleta') || $motCat === 'Prescrito'
            || $motCat === 'Previsto' || $motCat === 'Chegada setor'
            || str_contains($motCat, 'aprovação')) {
            return 'badge-aguard-coleta';
        }

        return 'badge-outros';
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
                $motivo = PendingEventHelper::motivoPendente($event);
                $rows->push(array_merge($base, [
                    'tipo_evento' => $normalizedType,
                    'tipo_label' => PendingEventTypeClassifier::label($normalizedType),
                    'item' => $this->normalizeItemLabel($event),
                    'classificacao' => PendingEventHelper::classificationLabel($event, $normalizedType),
                    'data_solicitacao' => $this->shortDate($event['dt_solicitacao'] ?? null),
                    'data_solicitacao_sort' => $this->parseDateToTs($event['dt_solicitacao'] ?? null) ?? 0,
                    'data_coleta' => $this->shortDate($event['dt_coleta'] ?? null),
                    'data_coleta_sort' => $this->parseDateToTs($event['dt_coleta'] ?? null) ?? 0,
                    'data_resultado' => $this->shortDate($event['scola_data_liberado'] ?? ($event['scola_data_resultado'] ?? ($event['dt_resultado'] ?? null))),
                    'data_resultado_sort' => $this->parseDateToTs($event['scola_data_liberado'] ?? ($event['scola_data_resultado'] ?? ($event['dt_resultado'] ?? null))) ?? 0,
                    'tempo_pendente' => $this->resolvePendingDuration(
                        $event['tempo_pendente'] ?? null,
                        $event['dt_solicitacao'] ?? ($event['dt_evento'] ?? null)
                    ),
                    'tempo_pendente_sort' => $sortTs > 0 ? (time() - $sortTs) : 0,
                    'status_execucao' => trim((string) ($event['status_laudo'] ?? $event['ds_status_agenda_label'] ?? '')),
                    'motivo_pendente' => $motivo,
                    'motivo_categoria' => $this->categorizarMotivo($motivo, $normalizedType, $event),
                    'criterio_pendencia' => $this->buildCriterioPendencia($normalizedType, $event, $isOverdue),
                    'nr_prescricao' => $event['nr_prescricao'] ?? null,
                    'nm_prescritor' => trim((string) ($event['nm_prescritor'] ?? '')),
                    'scola_status' => $event['scola_status'] ?? null,
                    'scola_resultado' => $event['scola_resultado'] ?? null,
                    'scola_integration_issue' => $event['scola_integration_issue'] ?? false,
                    'sort_ts' => $sortTs,
                    'is_overdue' => $isOverdue,
                    'is_oculto' => (bool) ($event['is_oculto'] ?? false),
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
                    'data_coleta' => null,
                    'data_coleta_sort' => 0,
                    'data_resultado' => null,
                    'data_resultado_sort' => 0,
                    'tempo_pendente' => $this->formatPendingDuration($req['dt_registro'] ?? null),
                    'tempo_pendente_sort' => $sortTs > 0 ? (time() - $sortTs) : 0,
                    'status_execucao' => '',
                    'motivo_pendente' => 'Aguardando resposta',
                    'motivo_categoria' => 'Aguardando resposta',
                    'criterio_pendencia' => 'Resposta da equipe não registrada',
                    'badge_cls' => 'badge-outros',
                    'nr_prescricao' => null,
                    'nm_prescritor' => '',
                    'scola_status' => null,
                    'scola_resultado' => null,
                    'scola_integration_issue' => false,
                    'is_exam' => false,
                    'is_overdue' => false,
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

    /** @param array<string, mixed> $event */
    private function categorizarMotivo(string $motivo, string $tipo, array $event): string
    {
        if ($tipo === 'antibiotico') {
            return 'Antimicrobiano pendente';
        }
        if ($tipo === 'consultoria') {
            return 'Aguardando resposta';
        }

        // Procedimentos de prescrição: usa dados reais do Tasy sem listas de códigos hardcoded.
        // Fontes: foi_executado_sem_baixa (procedimento_paciente), dt_coleta, status_laudo
        // (domínio 1226), dt_evento (dt_prev_execucao da prescr_procedimento).
        if ($tipo === 'procedimento') {
            $statusLabel = trim((string) ($event['status_laudo'] ?? ''));
            $urgente = (bool) ($event['urgente'] ?? false);

            // procedimento_paciente registra execução, mas baixa administrativa não foi feita
            if (! empty($event['foi_executado_sem_baixa'])) {
                return 'Executado — baixa pendente';
            }

            // dt_coleta preenchido: executado (usa label Tasy se disponível)
            if (! empty($event['dt_coleta'])) {
                return $statusLabel !== '' ? $statusLabel : 'Executado — baixa pendente';
            }

            // Label Tasy real e informativo (≠ "Prescrito" que é só o estado padrão de prescrição):
            // pode ser "Chegada setor", "Em preparo", "Em exame", "Aguardando aprovação", etc.
            if ($statusLabel !== '' && $statusLabel !== 'Prescrito') {
                return $urgente ? 'Urgente — '.lcfirst($statusLabel) : $statusLabel;
            }

            // "Prescrito" ou sem label: verifica dt_prev_execucao para distinguir atrasado vs pendente
            $dtEvento = $event['dt_evento'] ?? null;
            if ($dtEvento && strtotime($dtEvento) < time()) {
                return $urgente ? 'Urgente — em atraso' : 'Em atraso';
            }

            return $urgente ? 'Urgente — aguardando execução' : 'Aguardando execução';
        }

        // Exames e proc_exame: usa label real do domínio 1226 (status_laudo = ds_status_execucao_label).
        // Para eventos de agenda (proc_exame via agenda_paciente), usa label do domínio 83.
        if (in_array($tipo, ['exame', 'proc_exame'], true)) {
            $urgente = (bool) ($event['urgente'] ?? false);

            if (($event['_fonte'] ?? '') === 'agenda') {
                $agendaLabel = trim((string) ($event['ds_status_agenda_label'] ?? ''));

                return $agendaLabel !== '' ? $agendaLabel : 'Aguardando coleta';
            }

            // dt_coleta set: pós-coleta — usa label Tasy como categoria
            if (! empty($event['dt_coleta'])) {
                $statusLabel = trim((string) ($event['status_laudo'] ?? ''));

                return $statusLabel !== '' ? $statusLabel : 'Aguardando laudo';
            }

            // Pré-coleta / em andamento: usa label Tasy diretamente
            $statusLabel = trim((string) ($event['status_laudo'] ?? ''));
            if ($statusLabel !== '') {
                return $urgente ? 'Urgente — '.lcfirst($statusLabel) : $statusLabel;
            }

            return $urgente ? 'Urgente — aguardando coleta' : 'Aguardando coleta';
        }

        // Cirurgia: label do domínio 83 (ie_status_agenda)
        if ($tipo === 'cirurgia') {
            $agendaLabel = trim((string) ($event['ds_status_agenda_label'] ?? ''));
            $urgente = (bool) ($event['urgente'] ?? false);
            if ($agendaLabel !== '') {
                return $urgente ? 'Urgente — '.lcfirst($agendaLabel) : $agendaLabel;
            }

            return $urgente ? 'Urgente — aguardando cirurgia' : 'Aguardando cirurgia';
        }

        // Quimioterapia: label do domínio 83
        if ($tipo === 'quimioterapia') {
            $agendaLabel = trim((string) ($event['ds_status_agenda_label'] ?? ''));

            return $agendaLabel !== '' ? $agendaLabel : 'Quimioterapia agendada';
        }

        // Hemoterapia: distingue urgente vs normal
        if ($tipo === 'hemoterapia') {
            return (bool) ($event['urgente'] ?? false)
                ? 'Urgente — aguardando transfusão'
                : 'Aguardando transfusão';
        }

        return 'Outros';
    }

    private function shortDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        try {
            // PatientPendingEventsService: d/m/Y H:i (4-digit year)
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $date)) {
                $fmt = str_contains($date, ':') ? 'd/m/Y H:i' : 'd/m/Y';

                return Carbon::createFromFormat($fmt, $date)->format('d/m H:i');
            }
            // ScolaExamStatusService: d/m/y H:i (2-digit year)
            if (preg_match('/^\d{2}\/\d{2}\/\d{2}[\s]/', $date)) {
                return Carbon::createFromFormat('d/m/y H:i', $date)->format('d/m H:i');
            }

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
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $date)) {
                $fmt = str_contains($date, ':') ? 'd/m/Y H:i' : 'd/m/Y';

                return Carbon::createFromFormat($fmt, $date)->timestamp;
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{2}[\s]/', $date)) {
                return Carbon::createFromFormat('d/m/y H:i', $date)->timestamp;
            }

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

    private function buildCriterioPendencia(string $tipo, array $event, bool $isOverdue): string
    {
        return match ($tipo) {
            'antibiotico' => 'Administração não confirmada no horário',
            'consultoria' => 'Resposta da equipe não registrada',
            'hemoterapia' => ($event['urgente'] ?? false)
                ? 'Hemocomponente urgente — transfusão não confirmada'
                : 'Transfusão não confirmada',
            'quimioterapia' => 'Sessão de quimioterapia agendada em aberto',
            'cirurgia' => 'Cirurgia agendada — sem confirmação de realização',
            'procedimento' => $this->criterioProcedimento($event),
            'exame' => $this->criterioExame($event),
            default => 'Pendente de resolução',
        };
    }

    private function criterioProcedimento(array $event): string
    {
        if (! empty($event['foi_executado_sem_baixa']) || ! empty($event['dt_coleta'])) {
            return 'Data de baixa não preenchida no Tasy';
        }

        $statusLabel = trim((string) ($event['status_laudo'] ?? ''));

        if ($statusLabel !== '' && $statusLabel !== 'Prescrito') {
            return 'Status "'.$statusLabel.'" — sem baixa registrada';
        }

        // Só marca expirado quando há data prevista explícita (dt_prev_execucao) que passou.
        // Fallback para dt_solicitacao causaria falso-positivo: toda prescrição sem data
        // planejada seria exibida como "expirada" pelo simples fato de ter sido criada no passado.
        $dtEvento = $event['dt_evento'] ?? null;
        $plannedTs = $dtEvento ? $this->parseDateToTs($dtEvento) : null;
        if ($plannedTs !== null && $plannedTs < time()) {
            return 'Prazo de execução expirado — sem registro de realização';
        }

        return 'Execução não registrada no Tasy';
    }

    private function criterioExame(array $event): string
    {
        if (($event['_fonte'] ?? '') === 'agenda') {
            $agendaLabel = trim((string) ($event['ds_status_agenda_label'] ?? ''));

            return 'Exame agendado'.($agendaLabel !== '' ? ' — '.lcfirst($agendaLabel) : '');
        }

        if (! empty($event['exame_coletado_em_prescricao_mais_nova'])) {
            return 'Coletado em prescrição mais recente — item original pendente';
        }

        if (! empty($event['prescricao_mais_nova_pendente_info'])) {
            return 'Prescrição mais recente aguardando coleta';
        }

        if (! empty($event['foi_executado_sem_baixa'])) {
            return 'Data de baixa não preenchida no Tasy';
        }

        if (! empty($event['is_oculto'])) {
            return 'Exame de beira-leito — status não atualizado automaticamente no Tasy';
        }

        if (! empty($event['dt_coleta'])) {
            $statusLabel = trim((string) ($event['status_laudo'] ?? ''));

            return $statusLabel !== ''
                ? 'Coleta registrada — '.lcfirst($statusLabel)
                : 'Coleta registrada — laudo não disponível no Tasy';
        }

        // Pré-coleta: calcula dias de atraso quando a data prevista já passou
        $dtEvento = $event['dt_evento'] ?? null;
        $ts = $dtEvento ? @strtotime($dtEvento) : 0;
        $diasAtraso = ($ts && $ts < time()) ? (int) round((time() - $ts) / 86400) : 0;

        $statusLabel = trim((string) ($event['status_laudo'] ?? ''));

        if ($diasAtraso >= 1) {
            $sufixo = $statusLabel !== '' ? ' (status: '.$statusLabel.')' : '';

            return 'Coleta não registrada — previsto há '.$diasAtraso.' dia'.($diasAtraso !== 1 ? 's' : '').$sufixo;
        }

        return $statusLabel !== ''
            ? 'Coleta não registrada — status: '.$statusLabel
            : 'Coleta não registrada';
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
