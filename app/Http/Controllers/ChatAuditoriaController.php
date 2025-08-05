<?php

namespace App\Http\Controllers;

use App\Models\ChatMensagem;
use App\Models\System\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatAuditoriaController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Definir período padrão (últimos 7 dias)
            $dataInicio = $request->input('data_inicio', now()->subDays(7)->format('Y-m-d'));
            $dataFim = $request->input('data_fim', now()->format('Y-m-d'));

            // Query base para mensagens
            $query = ChatMensagem::with(['usuario'])
                ->whereDate('dt_criacao', '>=', $dataInicio)
                ->whereDate('dt_criacao', '<=', $dataFim);

            // Filtros adicionais
            if ($request->filled('usuario_id')) {
                $query->where('usuario_id', $request->usuario_id);
            }

            if ($request->filled('nr_atendimento')) {
                $query->where('nr_atendimento', $request->nr_atendimento);
            }

            $messages = $query->orderBy('dt_criacao', 'desc')->get();

            // Agrupamentos
            $gruposAtendimento = $this->agruparPorAtendimento($messages);
            $gruposUsuarios = $this->agruparPorUsuarios($messages);
            $chatStats = $this->calcularEstatisticas($messages);

            // Usuários para filtro
            $usuarios = User::select('id', 'name')->orderBy('name')->get();

            return view('sbar.chat-auditoria', compact(
                'gruposAtendimento', 
                'gruposUsuarios', 
                'chatStats', 
                'usuarios'
            ));

        } catch (\Exception $e) {
            Log::error('Erro na auditoria do chat', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('sbar.chat-auditoria', [
                'gruposAtendimento' => collect([]),
                'gruposUsuarios' => collect([]),
                'chatStats' => [
                    'total_mensagens' => 0,
                    'mensagens_fixadas' => 0,
                    'usuarios_ativos' => 0,
                    'atendimentos_com_chat' => 0,
                ],
                'usuarios' => User::select('id', 'name')->orderBy('name')->get()
            ]);
        }
    }

    private function agruparPorAtendimento($messages)
    {
        return $messages->groupBy('nr_atendimento')->map(function($grupo, $nrAtendimento) {
            $usuarios = $grupo->pluck('usuario.name')->unique()->filter();
            $turnos = $grupo->pluck('turno_id')->unique()->filter();

            return [
                'total_mensagens' => $grupo->count(),
                'usuarios_envolvidos' => $usuarios->values(),
                'primeira_mensagem' => $grupo->min('dt_criacao'),
                'ultima_mensagem' => $grupo->max('dt_criacao'),
                'turnos' => $turnos->values(),
                'mensagens_fixadas' => $grupo->where('is_fixed', true)->count(),
            ];
        });
    }

    private function agruparPorUsuarios($messages)
    {
        return $messages->groupBy('usuario_id')->map(function($grupo) {
            $usuario = $grupo->first()->usuario;
            
            return [
                'usuario' => $usuario,
                'total_mensagens' => $grupo->count(),
                'atendimentos_distintos' => $grupo->pluck('nr_atendimento')->unique()->count(),
                'primeira_atividade' => $grupo->min('dt_criacao'),
                'ultima_atividade' => $grupo->max('dt_criacao'),
                'mensagens_fixadas' => $grupo->where('is_fixed', true)->count(),
            ];
        });
    }

    private function calcularEstatisticas($messages)
    {
        return [
            'total_mensagens' => $messages->count(),
            'mensagens_fixadas' => $messages->where('is_fixed', true)->count(),
            'usuarios_ativos' => $messages->pluck('usuario_id')->unique()->count(),
            'atendimentos_com_chat' => $messages->pluck('nr_atendimento')->unique()->count(),
        ];
    }
}