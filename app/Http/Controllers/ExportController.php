<?php

namespace App\Http\Controllers;

use App\Models\EMR\Core\Sector;
use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Display shift evaluations view
     */
    public function showShiftEvaluations(Request $request): View
    {
        $sectorId = $request->get('sector_id');
        $search = $request->get('search', '');
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;

        if (!$sectorId) {
            abort(400, 'Sector not specified');
        }

        try {
            $tasy = new TasyService();
            $patients = $tasy->getSectorPatientsForSbar((int) $sectorId);

            if (empty($patients)) {
                return view('sbar.shift-evaluations', [
                    'sectorId' => $sectorId,
                    'sectorName' => 'Unknown Sector',
                    'beds' => [],
                    'totalBeds' => 0,
                    'search' => $search,
                    'currentPage' => 1,
                    'totalPages' => 1,
                ]);
            }

            $sectorName = $patients[0]['ds_setor_atendimento'] ?? 'Sector';
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));
            $since = Carbon::now()->subDays(2)->startOfDay();

            // Load messages from the new chat_messages table
            $rawMessages = DB::table('chat_messages as cm')
                ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                ->leftJoin('chat_message_pins as cmp', function ($join) {
                    $join->on('cmp.message_id', '=', 'cm.id')
                        ->whereNull('cmp.unpinned_at');
                })
                ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                ->where('cm.created_at', '>=', $since)
                ->select([
                    'cm.id',
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'cm.updated_at',
                    'u.name as user_name',
                    'u.photo as user_photo',
                    DB::raw('CASE WHEN cmp.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned'),
                ])
                ->orderBy('cm.created_at', 'desc')
                ->get();

            // Group messages by attendance number
            $messagesByAttendance = $rawMessages->groupBy('nr_atendimento');

            // Build beds data structure
            $patientsMap = collect($patients)->keyBy('nr_atendimento');
            $beds = [];

            foreach ($messagesByAttendance as $nrAtendimento => $messages) {
                $patient = $patientsMap->get($nrAtendimento);
                if (!$patient) continue;

                // Filter by search if provided
                if ($search) {
                    $searchLower = strtolower($search);
                    $patientName = strtolower($patient['nm_pessoa_fisica'] ?? '');
                    $bedCode = strtolower($patient['cd_unidade_basica'] ?? '');
                    $medicalRecord = strtolower($patient['nr_prontuario'] ?? '');

                    if (!str_contains($patientName, $searchLower) &&
                        !str_contains($bedCode, $searchLower) &&
                        !str_contains($medicalRecord, $searchLower)) {
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
                        'user_name' => $message->user_name ?? 'Unknown',
                        'user_photo' => $message->user_photo,
                        'user_initials' => $this->getInitials($message->user_name ?? 'U'),
                        'timestamp' => $dt->format('d/m/Y H:i'),
                        'turno' => $this->getShiftLabel($shiftInfo['shift']),
                        'is_pinned' => (bool) $message->is_pinned,
                    ];
                }

                $beds[] = [
                    'leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                    'nome_paciente' => $patient['nm_pessoa_fisica'] ?? 'Unknown',
                    'prontuario' => $patient['nr_prontuario'] ?? 'N/A',
                    'atendimento' => $nrAtendimento,
                    'mensagens' => $formattedMessages,
                    'total_mensagens' => count($formattedMessages),
                ];
            }

            // Sort beds by bed code
            usort($beds, function ($a, $b) {
                return strnatcmp($a['leito'], $b['leito']);
            });

            $totalBeds = count($beds);
            $totalPages = (int) ceil($totalBeds / $perPage);
            $page = min($page, max(1, $totalPages));

            // Paginate
            $offset = ($page - 1) * $perPage;
            $beds = array_slice($beds, $offset, $perPage);

            return view('sbar.shift-evaluations', [
                'sectorId' => $sectorId,
                'sectorName' => $sectorName,
                'beds' => $beds,
                'totalBeds' => $totalBeds,
                'search' => $search,
                'currentPage' => $page,
                'totalPages' => $totalPages,
            ]);

        } catch (\Exception $e) {
            Log::error('[ShiftEvaluations] Error loading evaluations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500, 'Error loading shift evaluations');
        }
    }

    /**
     * Export shift evaluations to CSV
     */
    public function exportShiftEvaluations(Request $request): StreamedResponse
    {
        try {
            $sectorId = $request->get('sector_id');

            if (!$sectorId) {
                abort(400, 'Sector not specified');
            }

            $tasy = new TasyService();
            $patients = $tasy->getSectorPatientsForSbar((int) $sectorId);

            if (empty($patients)) {
                abort(404, 'No patients found');
            }

            $sectorName = $patients[0]['ds_setor_atendimento'] ?? 'Sector';
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));
            $since = Carbon::now()->subDays(2)->startOfDay();

            // Load messages from the new chat_messages table
            $rawMessages = DB::table('chat_messages as cm')
                ->leftJoin('users as u', 'cm.user_id', '=', 'u.id')
                ->leftJoin('chat_message_pins as cmp', function ($join) {
                    $join->on('cmp.message_id', '=', 'cm.id')
                        ->whereNull('cmp.unpinned_at');
                })
                ->whereIn('cm.nr_atendimento', $attendanceNumbers)
                ->where('cm.created_at', '>=', $since)
                ->select([
                    'cm.nr_atendimento',
                    'cm.content',
                    'cm.created_at',
                    'cm.updated_at',
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
                if (!$patient) continue;

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
                        'Autor' => $message->user_name ?? 'Unknown',
                        'Mensagem' => strip_tags($message->content ?? ''),
                        'Fixada' => $message->is_pinned ? 'Yes' : 'No',
                        'Editada' => !empty($message->updated_at) ? 'Yes' : 'No',
                    ];
                }
            }

            // Natural sort by bed then by datetime
            usort($exportData, function ($a, $b) {
                $cmp = strnatcmp($a['Leito'], $b['Leito']);
                if ($cmp !== 0) return $cmp;
                return strcmp($a['Data'] . $a['Horário'], $b['Data'] . $b['Horário']);
            });

            $filename = 'shift_evaluations_' . preg_replace('/[^a-zA-Z0-9]/', '_', $sectorName)
                . '_' . date('Y-m-d_His') . '.csv';

            return new StreamedResponse(
                function () use ($exportData) {
                    $handle = fopen('php://output', 'w');
                    fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

                    if (!empty($exportData)) {
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
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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
            abort(500, 'Error exporting data');
        }
    }

    /**
     * Legacy method alias for backward compatibility
     */
    public function exportAvaliacoesTurno(Request $request): StreamedResponse
    {
        return $this->exportShiftEvaluations($request);
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
        $initials = '';

        if (count($parts) >= 2) {
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
        } elseif (count($parts) === 1) {
            $initials = strtoupper(substr($parts[0], 0, 2));
        }

        return $initials ?: 'U';
    }
}
