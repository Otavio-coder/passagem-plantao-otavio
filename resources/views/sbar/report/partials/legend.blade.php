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
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
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
                                    <img src="{{ asset('images/icons/patient-card/surgery-procedure.svg') }}" class="h-4 w-4 flex-shrink-0" alt="Cirurgia" />
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
                            Eventos e Pendências
                        </h3>
                        <div class="space-y-2 text-xs">
                            {{-- Pendências no card --}}
                            <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mt-3 mb-1">Ícones de Pendência</p>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <img src="{{ asset('images/icons/patient-card/alta.svg') }}" class="w-5 h-5" alt="Alta" />
                                    <span class="text-gray-800">Alta Efetivada</span>
                                </div>
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <img src="{{ asset('images/icons/patient-card/alta.svg') }}" class="w-5 h-5" alt="Alta Médica" />
                                    <span class="text-gray-800">Alta Médica</span>
                                </div>
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <img src="{{ asset('images/icons/patient-card/outpatient-department.svg') }}" class="w-5 h-5" alt="Proc" />
                                    <span class="text-gray-800">Procedimento</span>
                                    <span class="text-[10px] text-gray-500">(pendente/execução)</span>
                                </div>
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <img src="{{ asset('images/icons/patient-card/tac.svg') }}" class="w-5 h-5" alt="Exame" />
                                    <span class="text-gray-800">Exame</span>
                                </div>
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="w-5 h-5" alt="Cirurgia" />
                                    <span class="text-gray-800">Cirurgia</span>
                                </div>
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-800">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M38 34.2618V10.3761C38 8.16691 36.2091 6.37605 34 6.37605H28L26.868 5.21169C25.2973 3.5961 22.7027 3.5961 21.132 5.21169L20 6.37605H14C11.7909 6.37605 10 8.16691 10 10.376V34.2618C10 36.4709 11.7909 38.2618 14 38.2618H18V40.2617H23V44.2617H25V40.2617H30V38.2618H34C36.2091 38.2618 38 36.4709 38 34.2618ZM26.566 7.77021C26.9426 8.15753 27.4598 8.37605 28 8.37605H34C35.1046 8.37605 36 9.27148 36 10.3761V27.8006C35.7222 27.5852 35.4135 27.3722 35.0748 27.1751C33.2501 26.1132 30.6105 25.5605 27.4918 27.4005C24.924 28.9155 22.5089 29.3254 19.8818 29.3813C18.711 29.4062 17.5141 29.3609 16.2308 29.3123C16.0558 29.3057 15.8789 29.299 15.7005 29.2924C14.5325 29.2493 13.3017 29.2116 12 29.2328V10.376C12 9.27148 12.8954 8.37605 14 8.37605H20C20.5402 8.37605 21.0574 8.15753 21.434 7.77021L22.566 6.60584C23.3514 5.79805 24.6486 5.79805 25.434 6.60584L26.566 7.77021Z" fill="currentColor"/>
                                    </svg>
                                    <span class="text-gray-800">Hemoterapia</span>
                                </div>
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-800">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M29.9591 19.5138V17.3761L29.9154 16.3804L29.9591 16.3786V8.8473C29.9591 7.86078 30.7292 7.06105 31.6792 7.06105H34.2594L34.7462 6.54109C35.4216 5.81964 36.5373 5.81964 37.2128 6.54109L37.6996 7.06105H40.2797C41.2297 7.06105 41.9999 7.86078 41.9999 8.8473V19.5138C41.9999 20.5003 41.2297 21.3 40.2797 21.3H38.5596V22.75H36.9832L36.9826 34C36.9826 36.2091 35.1857 38 32.9691 38C30.7524 38 28.9555 36.2091 28.9555 34V28.9501C28.9555 27.3408 27.6626 26.0325 26.0556 26.0006C25.9556 26.9004 25.1901 27.6002 24.2605 27.6002C23.2631 27.6002 22.4544 26.7943 22.4544 25.8002C22.4544 25.1461 22.4274 23.0403 22.056 21.1377C21.9092 20.3857 21.7253 19.7579 21.5181 19.2921V28.5H21.4959L21.4959 40.2C21.4959 41.1669 20.7294 41.961 19.7599 41.9986C18.7904 42.0361 17.9644 41.3036 17.8891 40.3396L16.9753 28.6397C16.9716 28.593 16.9698 28.5464 16.9698 28.5H15.0967C15.0967 28.5464 15.0949 28.593 15.0912 28.6397L14.1774 40.3396C14.1021 41.3036 13.2761 42.0361 12.3066 41.9986C11.3371 41.961 10.5706 41.1669 10.5706 40.2V28.5H10.5484V19.2926C10.3412 19.7583 10.1574 20.386 10.0107 21.1377C9.63923 23.0403 9.61222 25.1461 9.61222 25.8002C9.61222 26.7943 8.8036 27.6002 7.80611 27.6002C6.80862 27.6002 6 26.7943 6 25.8002C6 25.1042 6.02378 22.71 6.46492 20.4502C6.68239 19.3361 7.02891 18.1012 7.62267 17.1004C8.22104 16.0919 9.28644 15.0002 10.9548 15.0002H21.1118C22.7802 15.0002 23.8456 16.0919 24.444 17.1004C25.0377 18.1012 25.3843 19.3361 25.6017 20.4502C25.8447 21.6947 25.9611 22.9801 26.0166 24C28.75 24.0114 30.9623 26.2232 30.9623 28.9501V34C30.9623 35.1046 31.8607 36 32.9691 36C34.0774 36 34.9758 35.1046 34.9758 34L34.9764 22.75H33.3993V21.3H31.6792C30.7292 21.3 29.9591 20.5003 29.9591 19.5138ZM37.6996 9.06105C37.1432 9.06105 36.6119 8.83087 36.2323 8.42547L35.9795 8.15542L35.7266 8.42547C35.3471 8.83087 34.8157 9.06105 34.2594 9.06105H31.9659V16.3923L32.2325 16.4027C32.7867 16.4246 33.2817 16.4442 33.7654 16.4334C34.8268 16.4098 35.7524 16.2399 36.7413 15.6294C37.4801 15.1733 38.2033 14.9786 38.8885 15.0018C39.2997 15.0158 39.669 15.1072 39.9931 15.2392V9.06105H37.6996Z" fill="currentColor"/>
                                        <path d="M19.6898 9.6C19.6898 11.5882 18.0527 13.2 16.0333 13.2C14.0138 13.2 12.3767 11.5882 12.3767 9.6C12.3767 7.61177 14.0138 6 16.0333 6C18.0527 6 19.6898 7.61177 19.6898 9.6Z" fill="currentColor"/>
                                    </svg>
                                    <span class="text-gray-800">Quimioterapia</span>
                                </div>
                                <div class="flex items-center gap-2 p-1.5 rounded hover:bg-gray-100">
                                    <svg width="20" height="20" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="text-gray-800">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M42 14C42 18.4183 38.4183 22 34 22C29.5817 22 26 18.4183 26 14C26 9.58172 29.5817 6 34 6C38.4183 6 42 9.58172 42 14ZM38.2823 14.9847C38.1586 15.523 37.6219 15.859 37.0837 15.7352L30.468 14.2143C29.9298 14.0906 29.5938 13.5539 29.7175 13.0157C29.8413 12.4775 30.3779 12.1414 30.9161 12.2652L37.5318 13.7861C38.07 13.9098 38.4061 14.4465 38.2823 14.9847Z" fill="currentColor"/>
                                        <path d="M16.7782 9.24516C16.1307 7.71335 13.9653 7.55884 13.0955 8.98238L6.29281 20.1148C5.50809 21.399 6.38547 23.0385 7.9159 23.1477L19.7823 23.9944C21.3127 24.1036 22.4261 22.6062 21.842 21.2243L16.7782 9.24516Z" fill="currentColor"/>
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M35.3849 36.3592C38.3881 34.9588 39.6875 31.3889 38.287 28.3856C36.8866 25.3824 33.3167 24.0831 30.3135 25.4835L19.4378 30.5549C16.4345 31.9554 15.1352 35.5252 16.5356 38.5285C17.9361 41.5317 21.5059 42.8311 24.5092 41.4306L35.3849 36.3592ZM29.9426 36.6902L34.5396 34.5466C36.5418 33.613 37.408 31.233 36.4744 29.2309C35.5408 27.2287 33.1609 26.3625 31.1587 27.2961L26.5617 29.4397L29.9426 36.6902Z" fill="currentColor"/>
                                    </svg>
                                    <span class="text-gray-800">Antimicrobiano</span>
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
