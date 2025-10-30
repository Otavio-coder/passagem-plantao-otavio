<?php

namespace App\Http\Controllers;

use App\Models\System\Chat\ChatAuditoria;
use App\Models\System\Chat\ChatMensagem;
use App\Models\System\Chat\ChatSessao;
use App\Models\System\User;
use App\Repositories\MySQL\Chat\ChatRepository;
use App\Services\TasyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatAuditoriaController extends Controller
{
    /**
     * Exibe avaliações do turno organizadas por leito (refatorado)
     * Usa TasyService para obter pacientes do setor e ChatRepository para mensagens.
     * Rota: /sbar/avaliacoes-turno
     */
    public function avaliacoesTurno(Request $request)
    {
        try {
            $sectorId = $request->get('sector_id');

            if (!$sectorId) {
                return redirect()->route('sbar.report')
                    ->with('warning', 'Selecione um setor para visualizar as avaliações.');
            }

            $tasy = new TasyService();
            $patients = $tasy->getSectorPatientsForSbar((int)$sectorId);

            if (empty($patients)) {
                return redirect()->route('sbar.report')
                    ->with('error', 'Nenhum paciente encontrado para o setor selecionado.');
            }

            $currentShift = $this->getCurrentShift();
            $shifts = $this->getShiftsForAvaliacoes($currentShift);

            $beds = [];

            foreach ($patients as $patient) {
                $nrAtendimento = $patient['nr_atendimento'] ?? null;
                if (!$nrAtendimento) {
                    continue;
                }

                $leito = $patient['cd_unidade_basica'] ?? 'N/A';

                foreach ($shifts as $shift) {
                    $date = $shift['date'];
                    $shiftId = $shift['shift'];

                    // Load session with messages, message user and message auditorias with user
                    $session = ChatSessao::with(['messages.usuario', 'messages.auditorias.usuario'])
                        ->where('nr_atendimento', $nrAtendimento)
                        ->where('turno_id', $shiftId)
                        ->whereDate('data_sessao', $date)
                        ->first();

                    $messages = $session ? $session->messages : collect();

                    if ($messages->isEmpty()) {
                        continue;
                    }

                    if (!isset($beds[$leito])) {
                        $beds[$leito] = [
                            'leito' => $leito,
                            'nome_paciente' => $patient['nm_pessoa_fisica'] ?? 'N/A',
                            'prontuario' => $patient['nr_prontuario'] ?? 'N/A',
                            'atendimento' => $nrAtendimento,
                            'total_mensagens' => 0,
                            'mensagens' => []
                        ];
                    }

                    foreach ($messages as $message) {
                        $beds[$leito]['mensagens'][] = [
                            'content' => $this->formatMessageContent($message->mensagem ?? ''),
                            'user_name' => $message->usuario?->name ?? 'Usuário',
                            'user_photo' => $this->getUserPhoto($message->usuario),
                            'user_initials' => $this->getUserInitials($message->usuario?->name ?? 'U'),
                            'timestamp' => \Carbon\Carbon::parse($message->dt_criacao)->format('d/m/Y H:i'),
                            'turno' => $this->getShiftLabel($message->turno_id ?? $shiftId),
                            'is_pinned' => $message->is_fixed ?? false,
                            'auditoria' => $message->auditorias
                                ->sortByDesc('dt_acao')
                                ->values()
                                ->map(function($a) {
                                    return [
                                        'acao' => $a->acao,
                                        'usuario' => $a->usuario?->name ?? 'N/A',
                                        'data' => $a->dt_acao?->format('d/m/Y H:i')
                                    ];
                                })->toArray(),
                        ];

                        $beds[$leito]['total_mensagens']++;
                    }
                }
            }

            if (!empty($beds)) {
                uksort($beds, function($a, $b) {
                    return strnatcmp($a, $b);
                });
            }

            return view('sbar.avaliacoes-turno', [
                'sectorName' => $patients[0]['ds_setor_atendimento'] ?? 'Setor',
                'beds' => array_values($beds),
            ]);

        } catch (\Exception $e) {
            Log::error('[AvaliacoesTurno] Erro ao carregar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('sbar.report')
                ->with('error', 'Erro ao carregar avaliações.');
        }
    }

    /**
     * Busca dados básicos do paciente
     */
    private function getBasicPatientData($nrAtendimento)
    {
        try {
            $data = \DB::table('atendimento_paciente as ap')
                ->join('pessoa_fisica as pf', 'ap.cd_pessoa_fisica', '=', 'pf.cd_pessoa_fisica')
                ->leftJoin('leito as l', 'ap.cd_leito', '=', 'l.cd_leito')
                ->where('ap.nr_atendimento', $nrAtendimento)
                ->select([
                    'pf.nm_pessoa_fisica',
                    'pf.nr_prontuario',
                    'l.cd_unidade_basica',
                    'ap.cd_setor_atendimento'
                ])
                ->first();

            if (!$data) {
                return null;
            }

            return [
                'nm_pessoa_fisica' => $data->nm_pessoa_fisica ?? 'N/A',
                'nr_prontuario' => $data->nr_prontuario ?? 'N/A',
                'cd_unidade_basica' => $data->cd_unidade_basica ?? 'N/A',
                'cd_setor_atendimento' => $data->cd_setor_atendimento
            ];

        } catch (\Exception $e) {
            \Log::error('[AvaliacoesTurno] Erro ao buscar paciente', [
                'nr_atendimento' => $nrAtendimento,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Formata conteúdo da mensagem
     */
    private function formatMessageContent($text)
    {
        if (empty($text)) return '';

        // Tenta descriptografar
        try {
            if (str_starts_with($text, 'eyJpdiI6') || str_contains($text, ':')) {
                $text = \Illuminate\Support\Facades\Crypt::decryptString($text);
            }
        } catch (\Exception $e) {
            // Usa texto original se falhar
        }

        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Markdown simples
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = nl2br($text);

        return $text;
    }

    /**
     * Obtém foto do usuário
     */
    private function getUserPhoto($user)
    {
        if (!$user) return null;

        try {
            if (method_exists($user, 'getUserPhoto')) {
                return $user->getUserPhoto();
            }

            if (isset($user->photo) && !empty($user->photo)) {
                if (strpos($user->photo, 'data:image') === 0) {
                    return preg_replace('/^data:image\/\w+;base64,/', '', $user->photo);
                }
                return $user->photo;
            }
        } catch (\Exception $e) {
            // Ignora erro
        }

        return null;
    }

    /**
     * Obtém iniciais do usuário
     */
    private function getUserInitials($name)
    {
        if (empty($name)) return 'U';

        $words = explode(' ', $name);
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }

    /**
     * Retorna turnos para busca (atual + anterior)
     */
    private function getShiftsForAvaliacoes($currentShift)
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $shiftsOrder = ['manha', 'tarde', 'noite'];
        $currentIndex = array_search($currentShift, $shiftsOrder);

        $shifts = [];

        // Turno atual
        $shifts[] = [
            'date' => $today,
            'shift' => $currentShift
        ];

        // Turno anterior
        if ($currentIndex > 0) {
            $previousShift = $shiftsOrder[$currentIndex - 1];
            $shifts[] = [
                'date' => $today,
                'shift' => $previousShift
            ];
        } else {
            $shifts[] = [
                'date' => $yesterday,
                'shift' => 'noite'
            ];
        }

        return $shifts;
    }

    /**
     * Retorna turno atual
     */
    private function getCurrentShift()
    {
        $hour = now()->hour;

        if ($hour >= 7 && $hour < 13) {
            return 'manha';
        } elseif ($hour >= 13 && $hour < 19) {
            return 'tarde';
        } else {
            return 'noite';
        }
    }

    /**
     * Retorna label do turno
     */
    private function getShiftLabel($shift)
    {
        return match($shift) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            default => ucfirst($shift)
        };
    }
}
