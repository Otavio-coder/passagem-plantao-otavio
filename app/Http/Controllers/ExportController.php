<?php

namespace App\Http\Controllers;

use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private const VALID_SORT_FIELDS = ['leito', 'nome_paciente', 'setor', 'dt_entrada', 'dt_alta', 'internment_days', 'total_mensagens'];

    private const ALLOWED_PER_PAGE = [10, 20, 50, 100];

    public function showShiftEvaluations(Request $request): View
    {
        $sectorId = $request->get('sector_id');
        if (! $sectorId) {
            abort(400, 'Setor não especificado');
        }

        $search = $request->get('search', '');
        $sortBy = $request->get('sort_by', 'leito');
        $sortDir = $request->get('sort_dir', 'asc');
        $perPage = (int) $request->get('per_page', 20);
        $page = max(1, (int) $request->get('page', 1));
        $dateFrom = $request->get('date_from', Carbon::today()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::today()->format('Y-m-d'));

        if (! in_array($sortBy, self::VALID_SORT_FIELDS)) {
            $sortBy = 'leito';
        }
        if (! in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'asc';
        }
        if (! in_array($perPage, self::ALLOWED_PER_PAGE)) {
            $perPage = 20;
        }

        try {
            $tasy = new TasyService;
            $patients = $tasy->getSectorPatientsForSbar((int) $sectorId);

            $basePayload = [
                'sectorId' => $sectorId,
                'sectorName' => 'Setor desconhecido',
                'beds' => [],
                'totalBeds' => 0,
                'search' => $search,
                'sortBy' => $sortBy,
                'sortDir' => $sortDir,
                'perPage' => $perPage,
                'allowedPerPage' => self::ALLOWED_PER_PAGE,
                'currentPage' => 1,
                'totalPages' => 1,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
            ];

            if (empty($patients)) {
                return view('sbar.report.filters.evaluations.page', $basePayload);
            }

            $sectorName = $patients[0]['ds_setor_atendimento'] ?? 'Setor';
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));
            $fromDate = Carbon::parse($dateFrom)->startOfDay();
            $toDate = Carbon::parse($dateTo)->endOfDay();

            $rawMessages = DB::table('chat_messages as cm')
                ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                ->leftJoin('chat_message_pins as cmp', function ($join) {
                    $join->on('cmp.message_id', '=', 'cm.id')
                        ->whereNull('cmp.unpinned_at');
                })
                ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                ->whereBetween('cm.created_at', [$fromDate, $toDate])
                ->select([
                    'cm.id',
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'u.name as user_name',
                    'u.photo as user_photo',
                    DB::raw('CASE WHEN cmp.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned'),
                ])
                ->orderBy('cm.created_at', 'desc')
                ->get();

            $messagesByAttendance = $rawMessages->groupBy('nr_atendimento');
            $patientsMap = collect($patients)->keyBy('nr_atendimento');
            $beds = [];

            foreach ($messagesByAttendance as $nrAtendimento => $messages) {
                $patient = $patientsMap->get($nrAtendimento);
                if (! $patient) {
                    continue;
                }

                if ($search) {
                    $sl = strtolower($search);
                    if (! str_contains(strtolower($patient['nm_pessoa_fisica'] ?? ''), $sl) &&
                        ! str_contains(strtolower($patient['cd_unidade_basica'] ?? ''), $sl) &&
                        ! str_contains(strtolower($patient['nr_prontuario'] ?? ''), $sl) &&
                        ! str_contains(strtolower($patient['ds_setor_atendimento'] ?? ''), $sl)) {
                        continue;
                    }
                }

                $formattedMessages = [];
                foreach ($messages as $message) {
                    $dt = Carbon::parse($message->created_at);
                    $shiftInfo = getShiftInfo($dt);

                    $formattedMessages[] = [
                        'id' => $message->id,
                        'content' => nl2br(e($message->content)),
                        'user_name' => $message->user_name ?? 'Desconhecido',
                        'user_photo' => $message->user_photo,
                        'user_initials' => $this->getInitials($message->user_name ?? 'U'),
                        'timestamp' => $dt->format('d/m/Y H:i'),
                        'turno' => $this->getShiftLabel($shiftInfo['shift']),
                        'is_pinned' => (bool) $message->is_pinned,
                    ];
                }

                $dtEntrada = $patient['dt_entrada'] ?? null;
                $dischargeInfo = $patient['discharge_info'] ?? null;

                $beds[] = [
                    'leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                    'nome_paciente' => $patient['nm_pessoa_fisica'] ?? 'Desconhecido',
                    'prontuario' => $patient['nr_prontuario'] ?? 'N/A',
                    'atendimento' => $nrAtendimento,
                    'setor' => $patient['ds_setor_atendimento'] ?? 'N/A',
                    'dt_entrada' => $dtEntrada,
                    'dt_entrada_formatted' => $dtEntrada ? Carbon::parse($dtEntrada)->format('d/m/Y') : null,
                    'dt_alta' => $dischargeInfo['dt_alta_medico'] ?? null,
                    'dt_alta_formatted' => $dischargeInfo['dt_alta_medico_formatted'] ?? null,
                    'internment_days' => $patient['internment_days'] ?? null,
                    'mensagens' => $formattedMessages,
                    'total_mensagens' => count($formattedMessages),
                ];
            }

            usort($beds, function ($a, $b) use ($sortBy, $sortDir) {
                if (in_array($sortBy, ['dt_entrada', 'dt_alta'])) {
                    $cmp = ($a[$sortBy] ? strtotime($a[$sortBy]) : 0) <=> ($b[$sortBy] ? strtotime($b[$sortBy]) : 0);
                } elseif (in_array($sortBy, ['internment_days', 'total_mensagens'])) {
                    $cmp = ($a[$sortBy] ?? 0) <=> ($b[$sortBy] ?? 0);
                } else {
                    $cmp = strnatcasecmp($a[$sortBy] ?? '', $b[$sortBy] ?? '');
                }

                return $sortDir === 'desc' ? -$cmp : $cmp;
            });

            $totalBeds = count($beds);
            $totalPages = max(1, (int) ceil($totalBeds / $perPage));
            $page = min($page, $totalPages);
            $beds = array_values(array_slice($beds, ($page - 1) * $perPage, $perPage));

            return view('sbar.report.filters.evaluations.page', array_merge($basePayload, [
                'sectorName' => $sectorName,
                'beds' => $beds,
                'totalBeds' => $totalBeds,
                'currentPage' => $page,
                'totalPages' => $totalPages,
            ]));

        } catch (\Exception $e) {
            Log::error('[ShiftEvaluations] Error loading evaluations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Erro ao carregar avaliações do turno');
        }
    }

    public function exportShiftEvaluations(Request $request): StreamedResponse
    {
        $sectorId = $request->get('sector_id');
        if (! $sectorId) {
            abort(400, 'Setor não especificado');
        }

        $dateFrom = $request->get('date_from', Carbon::today()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::today()->format('Y-m-d'));

        try {
            $tasy = new TasyService;
            $patients = $tasy->getSectorPatientsForSbar((int) $sectorId);

            if (empty($patients)) {
                abort(404, 'Nenhum paciente encontrado');
            }

            $sectorName = $patients[0]['ds_setor_atendimento'] ?? 'Setor';
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));
            $fromDate = Carbon::parse($dateFrom)->startOfDay();
            $toDate = Carbon::parse($dateTo)->endOfDay();

            $rawMessages = DB::table('chat_messages as cm')
                ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                ->leftJoin('chat_message_pins as cmp', function ($join) {
                    $join->on('cmp.message_id', '=', 'cm.id')
                        ->whereNull('cmp.unpinned_at');
                })
                ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                ->whereBetween('cm.created_at', [$fromDate, $toDate])
                ->select([
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'u.name as user_name',
                    DB::raw('CASE WHEN cmp.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned'),
                ])
                ->orderBy('cm.created_at', 'asc')
                ->get()
                ->groupBy('nr_atendimento');

            $patientsMap = collect($patients)->keyBy('nr_atendimento');
            $exportData = [];

            foreach ($rawMessages as $nrAtendimento => $messages) {
                $patient = $patientsMap->get($nrAtendimento);
                if (! $patient) {
                    continue;
                }

                foreach ($messages as $message) {
                    $dt = Carbon::parse($message->created_at);
                    $turno = $this->getShiftLabel(getShiftInfo($dt)['shift']);

                    $exportData[] = [
                        'Leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                        'Paciente' => $patient['nm_pessoa_fisica'] ?? 'N/A',
                        'Prontuário' => $patient['nr_prontuario'] ?? 'N/A',
                        'Atendimento' => $nrAtendimento,
                        'Data' => $dt->format('d/m/Y'),
                        'Turno' => $turno,
                        'Horário' => $dt->format('H:i'),
                        'Autor' => $message->user_name ?? 'Desconhecido',
                        'Mensagem' => strip_tags($message->content ?? ''),
                        'Fixada' => $message->is_pinned ? 'Sim' : 'Não',
                    ];
                }
            }

            usort($exportData, function ($a, $b) {
                $cmp = strnatcmp($a['Leito'], $b['Leito']);

                return $cmp !== 0 ? $cmp : strcmp($a['Data'].$a['Horário'], $b['Data'].$b['Horário']);
            });

            $safeSector = preg_replace('/[^a-zA-Z0-9]/', '_', $sectorName);
            $filename = "avaliacoes_{$safeSector}_{$dateFrom}_{$dateTo}_".date('His').'.csv';

            return new StreamedResponse(
                function () use ($exportData) {
                    $handle = fopen('php://output', 'w');
                    fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
                    if (! empty($exportData)) {
                        fputcsv($handle, array_keys($exportData[0]), ';');
                    }
                    foreach ($exportData as $row) {
                        fputcsv($handle, $row, ';');
                    }
                    fclose($handle);
                },
                200,
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]
            );

        } catch (\Exception $e) {
            Log::error('[ExportEvaluations] Export error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(500, 'Erro ao exportar dados');
        }
    }

    private function getShiftLabel(string $turnoId): string
    {
        return match ($turnoId) {
            'morning' => 'Manhã',
            'afternoon' => 'Tarde',
            'night' => 'Noite',
            default => ucfirst($turnoId),
        };
    }

    private function getInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr($parts[count($parts) - 1], 0, 1));
        }

        return strtoupper(substr($parts[0] ?? 'U', 0, 2)) ?: 'U';
    }
}
