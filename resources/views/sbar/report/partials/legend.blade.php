{{-- resources/views/livewire/partials/sbar-legend.blade.php --}}
<div id="sbar-legend" class="mt-6 mb-4 font-montserrat" x-data="{ legendOpen: false }">
    <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
        {{-- Cabeçalho Clicável (Header) --}}
        <button
            @click="legendOpen = !legendOpen"
            class="w-full p-3 sm:p-4 flex items-center justify-between text-left focus:outline-none focus:bg-gray-50 transition-colors"
        >
            <h2 class="text-base sm:text-lg font-bold text-gray-800 font-montserrat">Legenda do Sistema SBAR</h2>
            {{-- Ícone de Seta (Chevron) --}}
            <svg
                class="h-6 w-6 text-gray-600 transition-transform duration-200 flex-shrink-0"
                :class="{ 'rotate-180': legendOpen }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        {{-- Conteúdo Colapsável (Body) --}}
        <div
            x-show="legendOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="px-3 sm:px-4 pb-3 sm:pb-4 border-t border-gray-200"
            x-cloak
        >
            <p class="mt-4 mb-2 text-sm sm:text-base text-gray-700 leading-7">
                Neste <span class="font-semibold text-gray-700">painel</span>, o <span class="font-semibold text-gray-700">sistema</span> monta os cards a partir dos leitos da unidade: para cada leito, ele busca o paciente internado naquele momento e renderiza um card com os principais dados clínicos e operacionais. Para ficar mais rápido, parte dessas informações fica em uma memória temporária (cache), que funciona como uma "cópia rápida" por pouco tempo. Quando algo parecer desatualizado, atualize a página para forçar nova leitura; se ainda assim não atualizar, aguarde alguns instantes e recarregue novamente para o sistema renovar os dados em cache.
            </p>
            <div class="mb-6 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <span class="w-1 h-4 bg-blue-500 rounded"></span>
                    Critérios das Escalas de Risco
                </h3>
                <p class="text-sm text-gray-600 leading-6 mb-3">
                    O sistema busca as 2 avaliações mais recentes de cada escala por paciente (score e classificação já vêm prontos do prontuário). Tudo fica em cache por 5 minutos; se parecer desatualizado, recarregue a página para renovar.
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 text-xs">
                    @foreach ([
                        'MEWS' => ['desc' => 'Alerta precoce (Adulto)', 'criteria' => [['class' => 'bg-red-100 border-red-300', 'label' => '≥5: Crítico'], ['class' => 'bg-orange-100 border-orange-300', 'label' => '4: Alto'], ['class' => 'bg-yellow-100 border-yellow-300', 'label' => '3: Alerta'], ['class' => 'bg-gray-100 border-gray-300', 'label' => '0-2: Normal']]],
                        'PEWS' => ['desc' => 'Alerta precoce (Pediátrico)', 'criteria' => [['class' => 'bg-red-100 border-red-300', 'label' => '≥4: Alto'], ['class' => 'bg-yellow-100 border-yellow-300', 'label' => '2-3: Moderado'], ['class' => 'bg-gray-100 border-gray-300', 'label' => '0-1: Normal']]],
                        'Braden' => ['desc' => 'Risco de Lesão por Pressão', 'criteria' => [['class' => 'bg-red-100 border-red-300', 'label' => '≤12: Alto'], ['class' => 'bg-yellow-100 border-yellow-300', 'label' => '13-14: Moderado'], ['class' => 'bg-gray-100 border-gray-300', 'label' => '15-18: Leve'], ['class' => 'bg-green-100 border-green-300', 'label' => '19-23: Sem risco']]],
                        'Morse' => ['desc' => 'Risco de Queda', 'criteria' => [['class' => 'bg-red-100 border-red-300', 'label' => '≥45: Alto'], ['class' => 'bg-yellow-100 border-yellow-300', 'label' => '25-44: Moderado'], ['class' => 'bg-gray-100 border-gray-300', 'label' => '<25: Baixo']]],
                        'Dor' => ['desc' => 'Nível de Dor', 'criteria' => [['class' => 'bg-red-100 border-red-300', 'label' => '≥7: Intensa'], ['class' => 'bg-yellow-100 border-yellow-300', 'label' => '4-6: Moderada'], ['class' => 'bg-gray-100 border-gray-300', 'label' => '1-3: Leve'], ['class' => 'bg-green-100 border-green-300', 'label' => '0: Sem dor']]],
                        'TEV' => ['desc' => 'Risco de Tromboembolismo', 'criteria' => [['class' => 'bg-red-100 border-red-300', 'label' => '≥7: Alto'], ['class' => 'bg-yellow-100 border-yellow-300', 'label' => '3-6: Moderado'], ['class' => 'bg-gray-100 border-gray-300', 'label' => '0-2: Baixo']]],
                    ] as $name => $scale)
                        <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-200 transition-all duration-200">
                            <h4 class="font-bold text-gray-800 text-sm mb-1">{{ $name }}</h4>
                            <p class="text-[10px] text-gray-500 mb-2">{{ $scale['desc'] }}</p>
                            <div class="space-y-1.5">
                                @foreach ($scale['criteria'] as $criterion)
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 {{ $criterion['class'] }} rounded-sm flex-shrink-0"></div>
                                        <span class="text-gray-700">{{ $criterion['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 bg-red-500 rounded"></span>
                            Alertas de Risco Clínico
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="alergy-icon w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver alergias">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </button>
                                <div>
                                    <span class="text-sm font-sm text-gray-800">Alergias Registradas</span>
                                    <p class="text-xs text-gray-600">(verifica no prontuário se há alergias cadastradas)</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="isolation-icon w-7 h-7 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver isolamento">
                                    <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="h-4 w-4" alt="Isolamento" />
                                </button>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Precauções de Isolamento</span>
                                    <p class="text-xs text-gray-600">(consulta se há isolamento ativo no momento)</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="surgery-icon w-7 h-7 bg-purple-700 rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver procedimentos cirúrgicos">
                                    <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="h-4 w-4 flex-shrink-0 filter brightness-0 invert" alt="Cirurgia" />
                                </button>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Procedimentos Cirúrgicos</span>
                                    <p class="text-xs text-gray-600">(busca cirurgias agendadas para os próximos 30 dias)</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center shadow-lg" aria-label="Alta efetivada">
                                    <img src="{{ asset('images/icons/patient-card/alta.svg') }}" class="w-4 h-4" alt="Alta" />
                                </button>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Alta Efetivada</span>
                                    <p class="text-xs text-gray-600">(identifica se o paciente já teve alta da unidade)</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="w-7 h-7 bg-gray-100 rounded-full flex items-center justify-center shadow-lg" aria-label="Alta médica">
                                    <img src="{{ asset('images/icons/patient-card/alta.svg') }}" class="w-4 h-4" alt="Alta Médica" />
                                </button>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Alta Médica</span>
                                    <p class="text-xs text-gray-600">(detecta autorização médica para alta ainda não efetivada)</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 bg-yellow-500 rounded"></span>
                            Indicadores das Escalas
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">
                                    MEWS: 3 (M)
                                </span>
                                <span class="text-xs text-gray-600">Escala preenchida no turno (M/T/N)</span>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-400 border border-gray-300 border-b-2 border-b-red-500 whitespace-nowrap font-semibold">
                                    Braden: 15
                                </span>
                                <span class="text-xs text-gray-600"><span class="font-bold text-red-600">Linha vermelha</span>: Avaliação pendente</span>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold relative">
                                    Morse: 50
                                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                </span>
                                <span class="text-xs text-gray-600"><span class="font-bold">Bolinha piscante</span>: Score aumentou</span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="relative inline-flex items-center bg-white text-gray-800 text-xs font-bold px-2 py-1 rounded-full shadow-sm border border-gray-200">
                                    Leito 10
                                    <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-orange-500"></span>
                                    </span>
                                </span>
                                <span class="text-xs text-gray-600"><span class="font-bold text-orange-500">Ponto laranja piscante</span>: Passagem pendente no turno atual</span>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <span class="relative inline-flex items-center bg-white text-gray-800 text-xs font-bold px-2 py-1 rounded-full shadow-sm border border-gray-200">
                                    Leito 10
                                    <span class="absolute -top-0.5 -right-0.5 inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                </span>
                                <span class="text-xs text-gray-600"><span class="font-bold text-green-600">Ponto verde</span>: Passagem registrada no turno atual</span>
                            </div>

                        <div class="mt-3">
                            <div class="space-y-3">
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">(M)</span>
                                    <span class="text-xs text-gray-600">Manhã: 07:00 às 12:59</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">(T)</span>
                                    <span class="text-xs text-gray-600">Tarde: 13:00 às 18:59</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">(N)</span>
                                    <span class="text-xs text-gray-600">Noite: 19:00 às 06:59 (dia seguinte)</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-orange-700 border border-orange-200 whitespace-nowrap font-semibold">Regra</span>
                                    <span class="text-xs text-gray-600">Tolerância de 30 min na virada para checkpoint e anotações.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 bg-purple-500 rounded"></span>
                            Eventos e Pendências
                        </h3>
                        <div class="space-y-2 text-xs">
                            <p class="text-[11px] text-gray-600 bg-purple-50 border border-purple-100 rounded px-2 py-1.5">
                                O card frontal prioriza pendências próximas. Para ver a lista completa, use <span class="font-semibold">Ver todas</span>.
                            </p>
                            {{-- Pendências no card --}}
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mt-3 mb-1">Ícones de Pendência</p>
                            <div class="space-y-2 text-xs">
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Procedimento</span> — Prescrição ativa (não cancelada, não bloqueada, com autorização) sem registro de execução finalizada</p>
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Exame</span> — Prescrição ativa sem coleta registrada no prontuário</p>
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Cirurgia</span> — Agendada para hoje até próx. 30 dias, não suspenso, não executado</p>
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Hemoterapia</span> — Programada para hoje até próx. 48h, não suspenso</p>
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Quimioterapia</span> — Sessão agendada para hoje até próx. 30 dias</p>
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Antimicrobiano</span> — Fármaco marcado como antimicrobiano, ativo no período (data início ≤ hoje e data fim ≥ hoje ou indefinido), não suspenso</p>
                            </div>
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mt-3 mb-1">Status de Alta</p>
                            <div class="space-y-2 text-xs">
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Alta Efetivada</span> — Paciente registrado com alta no prontuário</p>
                                <p class="text-[11px] text-gray-700"><span class="font-semibold">Alta Médica</span> — Médico autorizou alta mas paciente ainda internado (sem registro de alta efetivada)</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 bg-teal-500 rounded"></span>
                            Consultoria e Pareceres
                        </h3>
                        <div class="space-y-3">
                            <p class="text-xs text-gray-600 leading-6">
                                Solicitações de parecer/avaliação pendentes ou respondidas, com sigla da equipe consultora.
                            </p>
                            <div class="space-y-2">
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <img src="{{ asset('images/icons/patient-card/fisioterapia.svg') }}" class="w-4 h-4" alt="FIS" />
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">FIS</span>
                                    <span class="text-xs text-gray-600">Fisioterapia</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <img src="{{ asset('images/icons/patient-card/psicologia.svg') }}" class="w-4 h-4" alt="PSI" />
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">PSI</span>
                                    <span class="text-xs text-gray-600">Psicologia</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <img src="{{ asset('images/icons/patient-card/nutricao.svg') }}" class="w-4 h-4" alt="NUT" />
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">NUT</span>
                                    <span class="text-xs text-gray-600">Nutrição</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <img src="{{ asset('images/icons/patient-card/fonoaudiologia.svg') }}" class="w-4 h-4" alt="FON" />
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">FON</span>
                                    <span class="text-xs text-gray-600">Fonoaudiologia</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <img src="{{ asset('images/icons/patient-card/servico-social.svg') }}" class="w-4 h-4" alt="SS" />
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">SS</span>
                                    <span class="text-xs text-gray-600">Serviço Social</span>
                                </div>
                                <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                    <img src="{{ asset('images/icons/patient-card/catheter-svgrepo-com.svg') }}" class="w-4 h-4" alt="TAV" />
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-50 text-gray-800 border border-gray-300 whitespace-nowrap font-semibold">TAV</span>
                                    <span class="text-xs text-gray-600">Time de Acessos Vasculares</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    /* Opcional: Para esconder o conteúdo antes do Alpine.js carregar */
    [x-cloak] {
        display: none !important;
    }
</style>
