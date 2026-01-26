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
use Illuminate\Support\Facades\Cache;

class ChatAuditoriaController extends Controller
{
    private const ITEMS_PER_PAGE = 10;

    /**
     * Exibe avaliações do turno organizadas por leito (refatorado)
     * Usa TasyService para obter pacientes do setor e ChatRepository para mensagens.
     * Rota: /sbar/avaliacoes-turno
     */
    public function avaliacoesTurno(Request $request)
    {
        try {
            $sectorId = $request->get('sector_id');
            $search = $request->get('search', '');
            $page = max(1, (int)$request->get('page', 1));

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

            // Buscar todas as sessões relevantes de uma vez (otimização)
            // Mostra sessões dos últimos 2 dias para garantir que nada seja perdido
            $attendanceNumbers = array_filter(array_column($patients, 'nr_atendimento'));
            $today = now()->toDateString();
            $yesterday = now()->subDay()->toDateString();

            $sessionsQuery = ChatSessao::with(['messages' => function($q) {
                    $q->where('is_deleted', false)
                      ->orderBy('dt_criacao', 'desc')
                      ->with('usuario');
                }])
                ->whereIn('nr_atendimento', $attendanceNumbers)
                ->where(function($q) use ($today, $yesterday) {
                    $q->whereDate('data_sessao', $today)
                      ->orWhereDate('data_sessao', $yesterday);
                })
                ->where('total_mensagens', '>', 0);

            $sessions = $sessionsQuery->get()->groupBy('nr_atendimento');

            $beds = [];

            foreach ($patients as $patient) {
                $nrAtendimento = $patient['nr_atendimento'] ?? null;
                if (!$nrAtendimento) {
                    continue;
                }

                $leito = $patient['cd_unidade_basica'] ?? 'N/A';
                $patientSessions = $sessions->get($nrAtendimento, collect());

                if ($patientSessions->isEmpty()) {
                    continue;
                }

                // Filtro de busca
                if (!empty($search)) {
                    $searchLower = mb_strtolower($search);
                    $leitoLower = mb_strtolower($leito);
                    $nomeLower = mb_strtolower($patient['nm_pessoa_fisica'] ?? '');
                    $prontuario = (string)($patient['nr_prontuario'] ?? '');
                    $atendimento = (string)$nrAtendimento;

                    if (
                        strpos($leitoLower, $searchLower) === false &&
                        strpos($nomeLower, $searchLower) === false &&
                        strpos($prontuario, $search) === false &&
                        strpos($atendimento, $search) === false
                    ) {
                        continue;
                    }
                }

                foreach ($patientSessions as $session) {
                    $messages = $session->messages;
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
                            'turno' => $this->getShiftLabel($message->turno_id ?? $session->turno_id),
                            'is_pinned' => $message->is_fixed ?? false,
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

            // Paginação
            $allBeds = array_values($beds);
            $totalBeds = count($allBeds);
            $totalPages = max(1, ceil($totalBeds / self::ITEMS_PER_PAGE));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * self::ITEMS_PER_PAGE;
            $pagedBeds = array_slice($allBeds, $offset, self::ITEMS_PER_PAGE);

            return view('sbar.avaliacoes-turno', [
                'sectorName' => $patients[0]['ds_setor_atendimento'] ?? 'Setor',
                'sectorId' => $sectorId,
                'beds' => $pagedBeds,
                'totalBeds' => $totalBeds,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'search' => $search,
                'perPage' => self::ITEMS_PER_PAGE,
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
