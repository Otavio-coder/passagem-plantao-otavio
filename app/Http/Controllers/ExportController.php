<?php

namespace App\Http\Controllers;

use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Exporta mensagens do chat de um setor para CSV (UTF-8 BOM para Excel)
     */
    public function exportAvaliacoesTurno(Request $request): StreamedResponse
    {
        try {
            $sectorId = $request->get('sector_id');

            if (!$sectorId) {
                abort(400, 'Setor não especificado');
            }

            $tasy     = new TasyService();
            $patients = $tasy->getSectorPatientsForSbar((int) $sectorId);

            if (empty($patients)) {
                abort(404, 'Nenhum paciente encontrado');
            }

            $sectorName        = $patients[0]['ds_setor_atendimento'] ?? 'Setor';
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));
            $since             = Carbon::now()->subDays(2)->startOfDay();

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
            $exportData  = [];

            foreach ($rawMessages as $nrAtendimento => $messages) {
                $patient = $patientsMap->get($nrAtendimento);
                if (!$patient) continue;

                foreach ($messages as $message) {
                    $dt    = Carbon::parse($message->created_at);
                    $turno = $this->getShiftLabel(getShiftInfo($dt)['shift']);

                    $exportData[] = [
                        'Leito'       => $patient['cd_unidade_basica'] ?? 'N/A',
                        'Paciente'    => $patient['nm_pessoa_fisica'] ?? 'N/A',
                        'Prontuário'  => $patient['nr_prontuario'] ?? 'N/A',
                        'Atendimento' => $nrAtendimento,
                        'Data'        => $dt->format('d/m/Y'),
                        'Turno'       => $turno,
                        'Horário'     => $dt->format('H:i'),
                        'Autor'       => $message->user_name ?? 'Desconhecido',
                        'Mensagem'    => strip_tags($message->content ?? ''),
                        'Fixada'      => $message->is_pinned ? 'Sim' : 'Não',
                        'Editada'     => !empty($message->updated_at) ? 'Sim' : 'Não',
                    ];
                }
            }

            // Natural sort by bed then by datetime
            usort($exportData, function ($a, $b) {
                $cmp = strnatcmp($a['Leito'], $b['Leito']);
                if ($cmp !== 0) return $cmp;
                return strcmp($a['Data'] . $a['Horário'], $b['Data'] . $b['Horário']);
            });

            $filename = 'avaliacoes_turno_' . preg_replace('/[^a-zA-Z0-9]/', '_', $sectorName)
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
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Cache-Control'       => 'no-cache, no-store, must-revalidate',
                    'Pragma'              => 'no-cache',
                    'Expires'             => '0',
                ]
            );

        } catch (\Exception $e) {
            Log::error('[ExportAvaliacoes] Erro na exportação', [
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
}
