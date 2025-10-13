{{-- resources/views/livewire/partials/sbar-legend.blade.php --}}
<div class="mt-6 mb-4 p-3 sm:p-4 bg-white rounded-xl shadow-lg border border-gray-100 font-montserrat">
    <h2 class="text-base sm:text-lg font-bold text-gray-800 mb-4 font-montserrat">Legenda do Sistema SBAR</h2>
    
    <!-- Critérios das Escalas de Risco -->
    <div class="mb-6">
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

    <!-- Alertas, Indicadores e Pendências -->
    <div class="mt-6 pt-4 border-t border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Alertas de Risco Clínico -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <span class="w-1 h-4 bg-red-500 rounded"></span>
                    Alertas de Risco Clínico
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <button type="button" class="alert-icon w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver alergias">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </button>
                        <div>
                            <span class="text-sm font-medium text-gray-800">Alergias Registradas</span>
                            <p class="text-xs text-gray-600">Clique no ícone para ver detalhes</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <button type="button" class="alert-icon w-7 h-7 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-pulse" aria-label="Ver isolamento">
                            <img src="{{ asset('images/icons/patient-isolated.svg') }}" class="h-5 w-5" alt="Isolamento" />
                        </button>
                        <div>
                            <span class="text-sm font-medium text-gray-800">Precauções de Isolamento</span>
                            <p class="text-xs text-gray-600">Clique no ícone para ver motivos</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="alert-icon w-7 h-7 bg-white rounded-full flex items-center justify-center shadow-lg animate-pulse border border-purple-200">
                            <svg class="w-5 h-5 flex-shrink-0 text-purple-500" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M40 8H8V40H40V8ZM8 6C6.89543 6 6 6.89543 6 8V40C6 41.1046 6.89543 42 8 42H40C41.1046 42 42 41.1046 42 40V8C42 6.89543 41.1046 6 40 6H8Z" fill="currentColor"/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-800">Procedimentos Cirúrgicos</span>
                            <p class="text-xs text-gray-600">Clique no ícone para ver cirurgias</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visualização das Escalas -->
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
                        <span class="text-xs text-gray-600"><span class="font-bold">Bolinha piscante</span>: Score aumentou desde a última avaliação</span>
                    </div>
                </div>
            </div>
    
            <!-- Tipos de Pendências -->
            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <span class="w-1 h-4 bg-purple-500 rounded"></span>
                    Pendências das Próximas Horas
                </h3>
                <div class="space-y-3">
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <img src="{{ asset('images/icons/physician-arrow-up.svg') }}" class="w-4 h-4 flex-shrink-0" alt="Alta médica" />
                        <span class="text-xs text-gray-600">[ALTA] / [PREV. ALTA] - Alta médica liberada ou prevista</span>
                    </div>
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0 text-red-500" viewBox="0 0 24 24" fill="none">
                            <path d="M1.5 12.5L5.57574 16.5757C5.81005 16.8101 6.18995 16.8101 6.42426 16.5757L9 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                            <path d="M16 7L12 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                            <path d="M7 12L11.5757 16.5757C11.8101 16.8101 12.1899 16.8101 12.4243 16.5757L22 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        </svg>
                        <span class="text-xs text-gray-600">[Proc] - Procedimentos prescritos (próx. 12h)</span>
                    </div>
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 flex-shrink-0 text-purple-950" viewBox="0 0 48 48" fill="none">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M40 8H8V40H40V8ZM8 6C6.89543 6 6 6.89543 6 8V40C6 41.1046 6.89543 42 8 42H40C41.1046 42 42 41.1046 42 40V8C42 6.89543 41.1046 6 40 6H8Z" fill="currentColor"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                        </svg>
                        <span class="text-xs text-gray-600">[Cir] - Cirurgias agendadas (próx. 12h)</span>
                    </div>
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 rounded-full bg-yellow-500 text-black flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15.2501 6.5C16.4927 6.5 17.5001 5.49264 17.5001 4.25C17.5001 3.00736 16.4927 2 15.2501 2C14.0074 2 13.0001 3.00736 13.0001 4.25C13.0001 5.49264 14.0074 6.5 15.2501 6.5Z" fill="currentColor"/>
                            <path d="M12.3827 6.49876C10.8875 6.28944 7.47101 6.89609 6.06373 10.6488C5.86981 11.166 6.13181 11.7424 6.64893 11.9363C7.16605 12.1302 7.74247 11.8682 7.93639 11.3511C8.5197 9.7956 9.57155 9.03454 10.5097 8.69638L9.34067 11.7021C9.32145 11.7515 9.30642 11.8015 9.29542 11.8518C9.20171 12.1529 9.25147 12.4933 9.45894 12.7616L13.0211 17.3687L13.252 21.0623C13.2864 21.6135 13.7612 22.0325 14.3124 21.998C14.8636 21.9636 15.2826 21.4888 15.2481 20.9376L14.9789 16.6312L12.8861 13.9244L14.2594 11.2629L14.3519 11.3973C14.8887 12.1774 15.8991 12.4741 16.7725 12.1081L18.8866 11.2222C19.3959 11.0087 19.6358 10.4228 19.4224 9.91341C19.2089 9.40404 18.6229 9.16415 18.1136 9.3776L15.9995 10.2635L14.393 7.92894C14.0375 7.31458 13.4664 6.81797 12.7317 6.5684C12.6163 6.52917 12.4991 6.50636 12.3827 6.49876Z" fill="currentColor"/>
                        </svg>
                        <span class="text-xs text-gray-600">[Exame] - Exames agendados (próx. 48h)</span>
                    </div>
                    <div class="flex items-center space-x-3 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg width="24" height="24" class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.9999 6.95459C12.5522 6.95459 12.9999 7.40231 12.9999 7.95459V11.0004H16.0456C16.5979 11.0004 17.0456 11.4481 17.0456 12.0004C17.0456 12.5526 16.5979 13.0004 16.0456 13.0004H12.9999V16.0459C12.9999 16.5982 12.5522 17.0459 11.9999 17.0459C11.4476 17.0459 10.9999 16.5982 10.9999 16.0459V13.0004H7.95435C7.40206 13.0004 6.95435 12.5526 6.95435 12.0004C6.95435 11.4481 7.40206 11.0004 7.95435 11.0004H10.9999V7.95459C10.9999 7.40231 11.4476 6.95459 11.9999 6.95459Z" fill="currentColor"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12Z" fill="currentColor"/>
                        </svg>
                        <span class="text-xs text-gray-600">[Rec] - Recomendações e Intervenções</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>