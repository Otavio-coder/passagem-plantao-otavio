<?php

namespace Database\Seeders;

use App\Models\System\Chat\ChatMensagem;
use App\Models\System\Chat\ChatSessao;
use App\Models\System\User;
use App\Services\ChatAuditoriaService;
use Illuminate\Database\Seeder;

class ChatMensagemDemoSeeder extends Seeder
{
    public function run()
    {
        // Paciente original
        $nrAtendimento = 20967476;
        $turnos = ['manha', 'tarde', 'noite'];
        $dias = 10;

        $usuarios = User::where('status', 'A')->take(3)->get();
        if ($usuarios->count() < 3) {
            $usuarios = User::take(3)->get();
        }

        $mensagens = [
            'manha' => [
                "**Situação:** *Paciente acordou estável, sem intercorrências.*",
                "- *Itens monitorados:* \n- Sinais vitais \n- Diurese preservada",
                "**Recomendação:** *Manter hidratação e vigilância.*"
            ],
            'tarde' => [
                "**Background:** *Paciente realizou fisioterapia, aceitou dieta.*",
                "- *Dispositivos em uso:* \n- Sonda vesical \n- Cateter venoso periférico",
                "**Observação:** *Sem intercorrências relevantes.*"
            ],
            'noite' => [
                "**Avaliação:** *Paciente descansando, ambiente tranquilo.*",
                "- *Lista de recomendações:* \n- Troca de decúbito realizada \n- Monitorar dor",
                "**FIXADA:** *Reforçar cuidados noturnos e prevenção de lesão por pressão.*"
            ]
        ];

        $now = now();
        $currentHour = $now->hour;

        // Função para saber se o turno já iniciou no dia atual
        function turnoJaIniciou($turno, $hour) {
            return match($turno) {
                'manha' => $hour >= 7,
                'tarde' => $hour >= 13,
                'noite' => $hour >= 19 || $hour < 7,
                default => false
            };
        }

        for ($i = 0; $i < $dias; $i++) {
            $dataSessao = $now->copy()->subDays($i)->toDateString();
            foreach ($turnos as $tIdx => $turno) {
                $isHoje = ($i === 0);
                if ($isHoje && !turnoJaIniciou($turno, $currentHour)) {
                    continue;
                }

                $usuario = $usuarios[$tIdx];
                $sessao = ChatSessao::firstOrCreate([
                    'nr_atendimento' => $nrAtendimento,
                    'turno_id' => $turno,
                    'data_sessao' => $dataSessao,
                ], [
                    'encerrada' => false,
                    'inicio' => $now->copy()->subDays($i)->startOfDay(),
                ]);

                foreach ($mensagens[$turno] as $mIdx => $texto) {
                    $msg = ChatMensagem::create([
                        'sessao_id' => $sessao->id,
                        'nr_atendimento' => $nrAtendimento,
                        'turno_id' => $turno,
                        'usuario_id' => $usuario->id,
                        'mensagem' => $texto,
                        'dt_criacao' => $now->copy()->subDays($i)->addHours($tIdx * 8 + $mIdx * 2),
                        'dt_edicao' => $now->copy()->subDays($i)->addHours($tIdx * 8 + $mIdx * 2),
                        'is_fixed' => $turno === 'noite' && $mIdx === 2,
                        'fixed_by' => ($turno === 'noite' && $mIdx === 2) ? $usuario->id : null,
                        'fixed_at' => ($turno === 'noite' && $mIdx === 2) ? $now->copy()->subDays($i) : null,
                        'expiracao' => $now->copy()->addDays(30),
                    ]);
                    $sessao->increment('total_mensagens');

                    ChatAuditoriaService::registrarEnvioMensagem($msg);

                    if ($mIdx === 1) {
                        $estadoAnterior = $msg->replicate();
                        $msg->mensagem = $texto . "\n- *Observação adicional de plantão*";
                        $msg->dt_edicao = $msg->dt_edicao->copy()->addMinutes(10);
                        $msg->save();
                        ChatAuditoriaService::registrarEdicaoMensagem($msg, $estadoAnterior);
                    }

                    if ($msg->is_fixed) {
                        ChatAuditoriaService::registrarFixacaoMensagem($msg, true);
                    }
                }
            }
        }

        // Novo paciente internado 21537947 entrando no terceiro dia
        $nrAtendimentoNovo = 21537947;
        $diasNovo = 3;

        $mensagensNovo = [
            'manha' => [
                "**Situação:** *Paciente acordou com dor leve, aceitou café da manhã.*",
                "- *Monitoramento:* \n- Sinais vitais estáveis \n- Sem febre",
                "**Recomendação:** *Avaliar analgesia e manter dieta leve.*"
            ],
            'tarde' => [
                "**Background:** *Paciente realizou exames laboratoriais, sem alterações relevantes.*",
                "- *Dispositivos:* \n- Cateter venoso periférico \n- Sonda vesical retirada",
                "**Observação:** *Paciente colaborativo, iniciou fisioterapia.*"
            ],
            'noite' => [
                "**Avaliação:** *Paciente repousando, relatou melhora da dor.*",
                "- *Cuidados noturnos:* \n- Troca de decúbito realizada \n- Monitorar sono",
                "**FIXADA:** *Reforçar orientação sobre sinais de alerta e garantir repouso adequado.*"
            ]
        ];

        for ($i = 0; $i < $diasNovo; $i++) {
            $dataSessao = $now->copy()->subDays($i)->toDateString();
            foreach ($turnos as $tIdx => $turno) {
                $isHoje = ($i === 0);
                if ($isHoje && !turnoJaIniciou($turno, $currentHour)) {
                    continue;
                }

                $usuario = $usuarios[$tIdx];
                $sessao = ChatSessao::firstOrCreate([
                    'nr_atendimento' => $nrAtendimentoNovo,
                    'turno_id' => $turno,
                    'data_sessao' => $dataSessao,
                ], [
                    'encerrada' => false,
                    'inicio' => $now->copy()->subDays($i)->startOfDay(),
                ]);

                foreach ($mensagensNovo[$turno] as $mIdx => $texto) {
                    $msg = ChatMensagem::create([
                        'sessao_id' => $sessao->id,
                        'nr_atendimento' => $nrAtendimentoNovo,
                        'turno_id' => $turno,
                        'usuario_id' => $usuario->id,
                        'mensagem' => $texto,
                        'dt_criacao' => $now->copy()->subDays($i)->addHours($tIdx * 8 + $mIdx * 2),
                        'dt_edicao' => $now->copy()->subDays($i)->addHours($tIdx * 8 + $mIdx * 2),
                        'is_fixed' => $turno === 'noite' && $mIdx === 2,
                        'fixed_by' => ($turno === 'noite' && $mIdx === 2) ? $usuario->id : null,
                        'fixed_at' => ($turno === 'noite' && $mIdx === 2) ? $now->copy()->subDays($i) : null,
                        'expiracao' => $now->copy()->addDays(30),
                    ]);
                    $sessao->increment('total_mensagens');

                    ChatAuditoriaService::registrarEnvioMensagem($msg);

                    if ($mIdx === 1) {
                        $estadoAnterior = $msg->replicate();
                        $msg->mensagem = $texto . "\n- *Paciente orientado sobre fisioterapia noturna*";
                        $msg->dt_edicao = $msg->dt_edicao->copy()->addMinutes(10);
                        $msg->save();
                        ChatAuditoriaService::registrarEdicaoMensagem($msg, $estadoAnterior);
                    }

                    if ($msg->is_fixed) {
                        ChatAuditoriaService::registrarFixacaoMensagem($msg, true);
                    }
                }
            }
        }
    }
}
