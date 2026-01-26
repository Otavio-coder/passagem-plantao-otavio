<?php

namespace App\Http\Controllers;

use App\Models\System\Chat\ChatMensagem;
use App\Models\System\Chat\ChatSessao;
use App\Services\TasyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Exporta as avaliações do turno para Excel (CSV com BOM para UTF-8)
     */
    public function exportAvaliacoesTurno(Request $request): StreamedResponse
    {
        try {
            $sectorId = $request->get('sector_id');

            if (!$sectorId) {
                abort(400, 'Setor não especificado');
            }

            $tasy = new TasyService();
            $patients = $tasy->getSectorPatientsForSbar((int)$sectorId);

            if (empty($patients)) {
                abort(404, 'Nenhum paciente encontrado');
            }

            $sectorName = $patients[0]['ds_setor_atendimento'] ?? 'Setor';

            // Buscar todas as sessões relevantes dos últimos 2 dias
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));
            $today = Carbon::now()->toDateString();
            $yesterday = Carbon::now()->subDay()->toDateString();

            $sessions = ChatSessao::with(['messages' => function($q) {
                    $q->where('is_deleted', false)
                      ->orderBy('dt_criacao', 'asc')
                      ->with('usuario');
                }])
                ->whereIn('nr_atendimento', $attendanceNumbers)
                ->where(function($q) use ($today, $yesterday) {
                    $q->whereDate('data_sessao', $today)
                      ->orWhereDate('data_sessao', $yesterday);
                })
                ->where('total_mensagens', '>', 0)
                ->get()
                ->groupBy('nr_atendimento');

            // Preparar dados para export
            $exportData = [];
            $patientsMap = collect($patients)->keyBy('nr_atendimento');

            foreach ($sessions as $nrAtendimento => $patientSessions) {
                $patient = $patientsMap->get($nrAtendimento);
                if (!$patient) continue;

                foreach ($patientSessions as $session) {
                    foreach ($session->messages as $message) {
                        $decryptedMessage = $this->safeDecrypt($message->mensagem);

                        $exportData[] = [
                            'Leito' => $patient['cd_unidade_basica'] ?? 'N/A',
                            'Paciente' => $patient['nm_pessoa_fisica'] ?? 'N/A',
                            'Prontuário' => $patient['nr_prontuario'] ?? 'N/A',
                            'Atendimento' => $nrAtendimento,
                            'Data' => Carbon::parse($session->data_sessao)->format('d/m/Y'),
                            'Turno' => $this->getShiftLabel($session->turno_id),
                            'Horário' => Carbon::parse($message->dt_criacao)->format('H:i'),
                            'Autor' => $message->usuario?->name ?? 'Desconhecido',
                            'Mensagem' => strip_tags($decryptedMessage),
                            'Fixada' => $message->is_fixed ? 'Sim' : 'Não',
                        ];
                    }
                }
            }

            // Ordenar por leito (natural sort) e depois por data/hora
            usort($exportData, function($a, $b) {
                $leitoCompare = strnatcmp($a['Leito'], $b['Leito']);
                if ($leitoCompare !== 0) return $leitoCompare;
                return strcmp($a['Data'] . $a['Horário'], $b['Data'] . $b['Horário']);
            });

            $filename = 'avaliacoes_turno_' . preg_replace('/[^a-zA-Z0-9]/', '_', $sectorName) . '_' . date('Y-m-d_His') . '.csv';

            return new StreamedResponse(
                function() use ($exportData) {
                    $handle = fopen('php://output', 'w');

                    // BOM para UTF-8 no Excel
                    fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

                    // Cabeçalho
                    if (!empty($exportData)) {
                        fputcsv($handle, array_keys($exportData[0]), ';');
                    }

                    // Dados
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
            Log::error('[ExportAvaliacoes] Erro na exportação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Erro ao exportar dados');
        }
    }

    private function safeDecrypt(string $content): string
    {
        try {
            return decrypt($content);
        } catch (\Exception $e) {
            return $content;
        }
    }

    private function getShiftLabel(string $turnoId): string
    {
        return match($turnoId) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            default => ucfirst($turnoId)
        };
    }
}
