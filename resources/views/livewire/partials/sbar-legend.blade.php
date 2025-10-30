{{-- resources/views/livewire/partials/sbar-legend.blade.php --}}
<div class="mt-6 mb-4 font-montserrat" x-data="{ legendOpen: false }">
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
            <div class="mb-6 pt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <span class="w-1 h-4 bg-blue-500 rounded"></span>
                    Critérios das Escalas de Risco
                </h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 text-xs">
                    @php
                        $scales = [
                            'MEWS' => ['desc' => 'Alerta precoce (Adulto)', 'criteria' => [['color' => 'red', 'label' => '≥5: Crítico'], ['color' => 'orange', 'label' => '4: Alto'], ['color' => 'yellow', 'label' => '3: Alerta'], ['color' => 'gray', 'label' => '0-2: Normal']]],
                            'PEWS' => ['desc' => 'Alerta precoce (Pediátrico)', 'criteria' => [['color' => 'red', 'label' => '≥4: Alto'], ['color' => 'yellow', 'label' => '2-3: Moderado'], ['color' => 'gray', 'label' => '0-1: Normal']]],
                            'Braden' => ['desc' => 'Risco de Lesão por Pressão', 'criteria' => [['color' => 'red', 'label' => '≤12: Alto'], ['color' => 'yellow', 'label' => '13-14: Moderado'], ['color' => 'gray', 'label' => '15-18: Leve'], ['color' => 'green', 'label' => '19-23: Sem risco']]],
                            'Morse' => ['desc' => 'Risco de Queda', 'criteria' => [['color' => 'red', 'label' => '≥45: Alto'], ['color' => 'yellow', 'label' => '25-44: Moderado'], ['color' => 'gray', 'label' => '<25: Baixo']]],
                            'Dor' => ['desc' => 'Nível de Dor', 'criteria' => [['color' => 'red', 'label' => '≥7: Intensa'], ['color' => 'yellow', 'label' => '4-6: Moderada'], ['color' => 'gray', 'label' => '1-3: Leve'], ['color' => 'green', 'label' => '0: Sem dor']]],
                            'TEV' => ['desc' => 'Risco de Tromboembolismo', 'criteria' => [['color' => 'red', 'label' => '≥7: Alto'], ['color' => 'yellow', 'label' => '3-6: Moderado'], ['color' => 'gray', 'label' => '0-2: Baixo']]],
                        ];
                        $colors = [
                            'red' => 'bg-red-100 border-red-300',
                            'orange' => 'bg-orange-100 border-orange-300',
                            'yellow' => 'bg-yellow-100 border-yellow-300',
                            'gray' => 'bg-gray-100 border-gray-300',
                            'green' => 'bg-green-100 border-green-300',
                        ];
                    @endphp

                    @foreach ($scales as $name => $scale)
                        <div class="bg-white p-3 rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-200 transition-all duration-200">
                            <h4 class="font-bold text-gray-800 text-sm mb-1">{{ $name }}</h4>
                            <p class="text-[10px] text-gray-500 mb-2">{{ $scale['desc'] }}</p>
                            <div class="space-y-1.5">
                                @foreach ($scale['criteria'] as $criterion)
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 {{ $colors[$criterion['color']] }} rounded-sm flex-shrink-0"></div>
                                        <span class="text-gray-700">{{ $criterion['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 bg-red-500 rounded"></span>
                            Alertas de Risco Clínico
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="alergy-icon w-10 h-10 bg-red-500 text-white rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver alergias">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </button>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Alergias Registradas</span>
                                    <p class="text-xs text-gray-600">Clique no ícone para ver detalhes</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="isolation-icon w-10 h-10 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver isolamento">
                                    <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="h-6 w-6" alt="Isolamento" />
                                </button>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Precauções de Isolamento</span>
                                    <p class="text-xs text-gray-600">Clique no ícone para ver motivos</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <button type="button" class="surgery-icon w-10 h-10 bg-purple-700 rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver isolamento">
                                    <img src="{{ asset('images/icons/patient-card/surgery-procedure.svg') }}" class="w-6 h-6  flex-shrink-0" alt="Cirurgia" />
                                </button>
                                <div>
                                    <span class="text-sm font-medium text-gray-800">Procedimentos Cirúrgicos</span>
                                    <p class="text-xs text-gray-600">Clique no ícone para ver cirurgias</p>
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

                        <h4 class="text-xs font-semibold text-gray-600 mt-5 mb-2">Turnos de Avaliação</h4>
                        <div class="space-y-1.5 text-xs text-gray-700 pl-2">
                            <p><span class="font-semibold text-gray-800 w-6 inline-block">(M)</span> Manhã - 07:00 às 13:00</p>
                            <p><span class="font-semibold text-gray-800 w-6 inline-block">(T)</span> Tarde - 13:00 às 19:00</p>
                            <p><span class="font-semibold text-gray-800 w-6 inline-block">(N)</span> Noite - 19:00 às 07:00</p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <span class="w-1 h-4 bg-purple-500 rounded"></span>
                            Pendências das Próximas Horas
                        </h3>
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <img src="{{ asset('images/icons/patient-card/alta.svg') }}" class="w-8 h-8 flex-shrink-0" alt="Alta" />
                                <span class="text-xs text-gray-600">[ALTA] / [PREV. ALTA] - Alta médica liberada ou prevista</span>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <img src="{{ asset('images/icons/patient-card/outpatient-department.svg') }}" class="w-8 h-8  flex-shrink-0" alt="Procedimento" />
                                <span class="text-xs text-gray-600">[Proc] - Procedimentos prescritos </span>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <img src="{{ asset('images/icons/patient-card/tac.svg') }}" class="w-8 h-8  flex-shrink-0" alt="Exame" />
                                <span class="text-xs text-gray-600">[Exame] - Exames agendados</span>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <img src="{{ asset('images/icons/patient-card/alert-circle.svg') }}" class="w-8 h-8  flex-shrink-0" alt="Recomendações" />
                                <span class="text-xs text-gray-600">[Rec] - Recomendações</span>
                            </div>

                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <img src="{{ asset('images/icons/patient-card/hemot.svg') }}" class="w-8 h-8  flex-shrink-0" alt="Hemoterapia" />
                                <span class="text-xs text-gray-600">[Hemo] - Hemoterapia</span>
                            </div>
                            <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                                <img src="{{ asset('images/icons/patient-card/blood-drop.svg') }}" class="w-8 h-8  flex-shrink-0" alt="Quimioterapia" />
                                <span class="text-xs text-gray-600">[Quimio] - Quimioterapia</span>
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
