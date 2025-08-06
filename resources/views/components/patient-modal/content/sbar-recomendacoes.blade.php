@props([
        'loadingPatient' => false,
        'currentPatient' => null,
        'patientDetails' => null
])

<div x-show="activeTab === 'tab-r'" class="p-2 sm:p-3 lg:p-6">
@if($loadingPatient)
    <div class="flex flex-col items-center justify-center py-12 sm:py-20">
        <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center" style="top: 50%;">
            <i class="fas fa-spinner fa-3x animate-spin"></i>
        </span>
        <p class="text-gray-700 text-lg sm:text-xl">Carregando detalhes do paciente...</p>
    </div>
@elseif($currentPatient && !$currentPatient['has_patient'])
    <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
        <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <p class="text-gray-700 text-base sm:text-lg">Leito Vazio</p>
        <p class="text-gray-500 mt-2 text-sm sm:text-base">Este leito não possui paciente internado no momento.</p>
    </div>
@elseif($patientDetails)
    <!-- Recomendações - O que você sugere ou precisa que seja feito? -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
            <h4 class="text-lg sm:text-xl font-bold text-gray-800 border-b border-gray-200 pb-3 mb-6 flex items-center">
                <span class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-[#28a745] text-white mr-3 text-sm sm:text-base font-bold">R</span>
                <div>
                    <span class="text-base sm:text-lg">RECOMENDAÇÕES</span>
                    <p class="text-xs text-gray-500 font-normal mt-1">Orientações e condutas necessárias</p>
                </div>
            </h4>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Exames Prioritários -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <h5 class="text-sm font-medium text-gray-800 mb-3 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Exames Prioritários
                    </h5>
                    <div class="text-sm text-gray-700 p-3 bg-gray-50 rounded border">
                        {{ $patientDetails->prioridade_exames ?? 'Nenhum exame prioritário identificado' }}
                    </div>
                </div>
                
                <!-- Antimicrobianos -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <h5 class="text-sm font-medium text-gray-800 mb-3 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        Antimicrobianos em Uso
                    </h5>
                    <div class="text-sm text-gray-700 p-3 bg-gray-50 rounded border">
                        {{ $patientDetails->materiais ?? 'Nenhum antimicrobiano prescrito' }}
                    </div>
                </div>
                
                <!-- Precauções - PADRONIZADO -->
                <div class="bg-white p-4 rounded-lg border border-gray-200">
                    <h5 class="text-sm font-medium text-gray-800 mb-3 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Precauções e Isolamento
                    </h5>
                    <div class="text-sm p-3 bg-gray-50 rounded border">
                        @if($patientDetails->medida_bloqueio === 'Sim')
                            <div class="text-yellow-800 font-medium">
                                <span class="inline-block w-2 h-2 bg-yellow-500 rounded-full mr-2"></span>
                                ISOLAMENTO ATIVO
                            </div>
                            @if($patientDetails->motivos_isolamento)
                                <div class="text-gray-700 mt-2 text-xs">{{ $patientDetails->motivos_isolamento }}</div>
                            @endif
                        @else
                            <div class="text-gray-700">
                                <span class="inline-block w-2 h-2 bg-gray-400 rounded-full mr-2"></span>
                                Sem precauções especiais
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Procedimentos Cirúrgicos - Full width on all screens -->
                <div class="bg-white p-4 rounded-lg border border-gray-200 lg:col-span-2">
                    <h5 class="text-sm font-medium text-gray-800 mb-3 flex items-center">
                        @svg('healthicons-o-surgical-sterilization', 'h-3 w-3 sm:h-4 sm:w-4 mr-2 text-purple-600')
                        Procedimentos Cirúrgicos
                    </h5>
                    <div class="text-sm text-gray-700 p-3 bg-gray-50 rounded border">
                        @if($patientDetails && isset($patientDetails->procedimentos_cirurgicos))
                            @if(is_array($patientDetails->procedimentos_cirurgicos) && !empty($patientDetails->procedimentos_cirurgicos))
                                <div class="space-y-3" x-data="{ expandedProcedures: {} }">
                                    @foreach($patientDetails->procedimentos_cirurgicos as $index => $procedure)
                                        @if(is_array($procedure) && isset($procedure['procedimento']))
                                            <div class="bg-white rounded-lg border-l-4 {{ ($procedure['status'] ?? '') === 'REALIZADA' ? 'border-green-500' : 'border-purple-500' }} shadow-sm">
                                                <div class="p-3">
                                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-2 space-y-2 sm:space-y-0">
                                                        <div class="flex-1">
                                                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ ($procedure['status'] ?? '') === 'REALIZADA' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800' }}">
                                                                    {{ $procedure['status'] ?? 'Status não informado' }}
                                                                </span>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ ($procedure['tipo_agendamento'] ?? '') === 'EMERGÊNCIA' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                                                                    {{ $procedure['tipo_agendamento'] ?? 'ELETIVA' }}
                                                                </span>
                                                            </div>
                                                            <h6 class="font-medium text-gray-900 text-sm leading-tight break-words">
                                                                {{ $procedure['procedimento'] ?? 'Procedimento não informado' }}
                                                            </h6>
                                                            <p class="text-xs text-gray-600 mt-1">
                                                                <span class="font-medium">Caráter:</span> {{ $procedure['carater_cirurgia'] ?? 'Não informado' }}
                                                            </p>
                                                        </div>
                                                        @if(($procedure['has_observacoes'] ?? false))
                                                            <button @click="expandedProcedures[{{ $index }}] = !expandedProcedures[{{ $index }}]"
                                                                    class="sm:ml-2 p-1 text-gray-400 hover:text-gray-600 transition-colors self-start"
                                                                    title="Ver detalhes">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                                </svg>
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                        <div>
                                                            <span class="font-medium text-gray-600">Data/Hora:</span>
                                                            <div class="text-gray-800 break-words">
                                                                @if(($procedure['status'] ?? '') === 'REALIZADA')
                                                                    {{ $procedure['data_cirurgia'] ?? $procedure['data_agenda'] ?? 'Não informada' }}
                                                                    @if($procedure['hora_cirurgia'] ?? null)
                                                                        às {{ $procedure['hora_cirurgia'] }}
                                                                    @endif
                                                                @else
                                                                    {{ $procedure['data_agenda'] ?? 'Não informada' }}
                                                                    @if($procedure['hora_agenda'] ?? null)
                                                                        às {{ $procedure['hora_agenda'] }}
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <span class="font-medium text-gray-600">Duração:</span>
                                                            <div class="text-gray-800">{{ $procedure['duracao_formatada'] ?? 'Não informada' }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                @if(($procedure['has_observacoes'] ?? false))
                                                    <div x-show="expandedProcedures[{{ $index }}]" 
                                                         x-transition:enter="transition ease-out duration-200"
                                                         x-transition:enter-start="opacity-0 max-h-0"
                                                         x-transition:enter-end="opacity-100 max-h-96"
                                                         x-transition:leave="transition ease-in duration-150"
                                                         x-transition:leave-start="opacity-100 max-h-96"
                                                         x-transition:leave-end="opacity-0 max-h-0"
                                                         class="border-t border-gray-200 p-3 bg-gray-50 overflow-hidden">
                                                        <div class="space-y-2">
                                                            <h6 class="font-medium text-gray-800 text-xs uppercase tracking-wide">Detalhes da Cirurgia</h6>
                                                            <div class="bg-white p-2 rounded border text-xs leading-relaxed">
                                                                @php
                                                                    $observations = $procedure['observacoes'] ?? '';
                                                                    $parsedObs = [];
                                                                    if (!empty($observations)) {
                                                                        $parts = explode('|', $observations);
                                                                        foreach ($parts as $part) {
                                                                            $part = trim($part);
                                                                            if (strpos($part, ':') !== false) {
                                                                                list($key, $value) = explode(':', $part, 2);
                                                                                $parsedObs[trim($key)] = trim($value);
                                                                            }
                                                                        }
                                                                    }
                                                                @endphp
                                                                @if(!empty($parsedObs))
                                                                    <div class="grid grid-cols-1 gap-1">
                                                                        @foreach($parsedObs as $key => $value)
                                                                            @if(!empty($value) && $value !== 'Não' && $value !== '')
                                                                                <div class="flex flex-col sm:flex-row">
                                                                                    <span class="font-medium text-gray-600 sm:w-32 flex-shrink-0">{{ $key }}:</span>
                                                                                    <span class="text-gray-800 flex-1 break-words">{{ $value }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <div class="text-gray-700 break-words">{{ $observations }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif(is_string($procedure))
                                            <div class="p-2 bg-white rounded border-l-4 {{ str_contains($procedure, 'REALIZADA') ? 'border-green-500' : 'border-purple-500' }}">
                                                <span class="text-xs font-medium {{ str_contains($procedure, 'REALIZADA') ? 'text-green-700' : 'text-purple-700' }} break-words">
                                                    {{ trim($procedure) }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @elseif(is_string($patientDetails->procedimentos_cirurgicos))
                                @if($patientDetails->procedimentos_cirurgicos === 'Nenhuma cirurgia programada ou realizada recentemente')
                                    <span class="text-gray-500">{{ $patientDetails->procedimentos_cirurgicos }}</span>
                                @else
                                    <div class="space-y-2">
                                        @foreach(explode("\n\n", $patientDetails->procedimentos_cirurgicos) as $procedure)
                                            @if(trim($procedure))
                                                <div class="p-2 bg-white rounded border-l-4 {{ str_contains($procedure, 'REALIZADA') ? 'border-green-500' : 'border-purple-500' }}">
                                                    <span class="text-xs font-medium {{ str_contains($procedure, 'REALIZADA') ? 'text-green-700' : 'text-purple-700' }} break-words">
                                                        {{ trim($procedure) }}
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-500">Nenhuma cirurgia programada ou realizada recentemente</span>
                            @endif
                        @else
                            <span class="text-gray-500">Dados não disponíveis</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CPOE Completo -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 space-y-2 sm:space-y-0">
                <h4 class="text-lg sm:text-xl font-semibold text-gray-800">Prescrições do Dia - {{ date('d/m/Y') }}</h4>
                <div class="text-sm text-gray-500 font-medium">
                    CPOE
                </div>
            </div>
            
            <div class="border-b border-gray-200 mb-4">
                <nav class="flex space-x-1 overflow-x-auto pb-2">
                    <button @click="activeCpoeCategory = 'cpoe-exames'"
                            :class="activeCpoeCategory === 'cpoe-exames' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                            class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                        <div class="flex items-center space-x-1 sm:space-x-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            <span class="hidden sm:inline">Exames e Procedimentos</span>
                            <span class="sm:hidden">Exames</span>
                        </div>
                    </button>
                    
                    <button @click="activeCpoeCategory = 'cpoe-medicamentos'"
                            :class="activeCpoeCategory === 'cpoe-medicamentos' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                            class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                        <div class="flex items-center space-x-1 sm:space-x-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <span>Medicamentos</span>
                        </div>
                    </button>
                    
                    <button @click="activeCpoeCategory = 'cpoe-nutricao'"
                            :class="activeCpoeCategory === 'cpoe-nutricao' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                            class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                        <div class="flex items-center space-x-1 sm:space-x-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                            </svg>
                            <span>Nutrição</span>
                        </div>
                    </button>
                </nav>
            </div>
            
            <div>
                <!-- Exames e Procedimentos -->
                <div x-show="activeCpoeCategory === 'cpoe-exames'">
                    @if($patientDetails && isset($patientDetails->cpoe_procedures) && $patientDetails->cpoe_procedures['total_count'] > 0)
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span>{{ $patientDetails->cpoe_procedures['total_count'] }} procedimento(s) agendado(s)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto">{{ date('d/m/Y') }}</span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3">
                            @foreach(['MANHÃ', 'TARDE', 'NOITE'] as $shift)
                                @php
                                    $shiftData = $patientDetails->cpoe_procedures['shifts'][$shift] ?? ['count' => 0, 'procedures' => []];
                                @endphp
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide">{{ $shift }}</h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                {{ $shiftData['count'] }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        @if($shiftData['count'] > 0)
                                            <div class="space-y-2">
                                                @foreach($shiftData['procedures'] as $procedure)
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm">
                                                        <div class="text-xs font-medium text-gray-800 mb-2 leading-tight break-words">
                                                            {{ $procedure['procedimento'] }}
                                                        </div>
                                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-600 space-y-1 sm:space-y-0">
                                                            <span class="font-mono bg-white px-2 py-1 rounded border self-start">{{ $procedure['horario'] }}</span>
                                                            @if($procedure['is_completed'])
                                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200 self-start sm:self-auto">
                                                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>
                                                                    Realizado
                                                                </span>
                                                            @else
                                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200 self-start sm:self-auto">
                                                                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-1.5"></span>
                                                                    Pendente
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhum procedimento</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhum exame ou procedimento agendado</p>
                            <p class="text-gray-500 text-xs">para o dia {{ date('d/m/Y') }}</p>
                        </div>
                    @endif
                </div>
                
                <!-- Medicamentos -->
                <div x-show="activeCpoeCategory === 'cpoe-medicamentos'">
                    @if($patientDetails && isset($patientDetails->cpoe_medications) && $patientDetails->cpoe_medications['total_count'] > 0)
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span>{{ $patientDetails->cpoe_medications['total_count'] }} medicamento(s) prescrito(s)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto">{{ date('d/m/Y') }}</span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3" x-data="{ selectedMedication: null }">
                            @foreach(['MANHÃ', 'TARDE', 'NOITE'] as $shift)
                                @php
                                    $shiftData = $patientDetails->cpoe_medications['shifts'][$shift] ?? ['count' => 0, 'medications' => []];
                                @endphp
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide">{{ $shift }}</h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                {{ $shiftData['count'] }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        @if($shiftData['count'] > 0)
                                            <div class="space-y-2">
                                                @foreach($shiftData['medications'] as $index => $medication)
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm cursor-pointer"
                                                         @click="selectedMedication = selectedMedication === '{{ $shift }}_{{ $index }}' ? null : '{{ $shift }}_{{ $index }}'">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words">
                                                                    {{ $medication['medicamento'] }}
                                                                </div>
                                                                <div class="text-xs text-gray-600">
                                                                    <span class="font-mono bg-white px-1.5 py-0.5 rounded border mr-2">{{ $medication['horario'] }}</span>
                                                                    <span class="font-medium">{{ $medication['dose'] }}</span>
                                                                </div>
                                                            </div>
                                                            <div class="flex flex-col items-end space-y-1 ml-2">
                                                                @if($medication['is_administered'])
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>
                                                                        Aplicado
                                                                    </span>
                                                                @elseif($medication['is_suspended'])
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 border border-red-200">
                                                                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>
                                                                        Suspenso
                                                                    </span>
                                                                @else
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200">
                                                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-1"></span>
                                                                        Pendente
                                                                    </span>
                                                                @endif
                                                                
                                                                <svg class="w-3 h-3 text-gray-400 transform transition-transform" 
                                                                     :class="selectedMedication === '{{ $shift }}_{{ $index }}' ? 'rotate-180' : ''" 
                                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Detalhes expandidos -->
                                                        <div x-show="selectedMedication === '{{ $shift }}_{{ $index }}'" 
                                                             x-transition:enter="transition ease-out duration-200"
                                                             x-transition:enter-start="opacity-0 max-h-0"
                                                             x-transition:enter-end="opacity-100 max-h-96"
                                                             x-transition:leave="transition ease-in duration-150"
                                                             x-transition:leave-start="opacity-100 max-h-96"
                                                             x-transition:leave-end="opacity-0 max-h-0"
                                                             class="overflow-hidden mt-3 pt-3 border-t border-gray-200">
                                                            <div class="space-y-3">
                                                                <!-- Informações detalhadas -->
                                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                                    <div class="bg-white p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 mb-1">Administração</div>
                                                                        <div class="space-y-1">
                                                                            <div><span class="font-medium">Via:</span> {{ $medication['via_aplicacao'] }}</div>
                                                                            <div><span class="font-medium">Dispensar:</span> {{ $medication['dispensar'] }}</div>
                                                                            @if($medication['devolucao'])
                                                                                <div class="text-orange-600"><span class="font-medium">Devolução:</span> {{ $medication['devolucao'] }}</div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="bg-white p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 mb-1">Prescrição</div>
                                                                        <div class="space-y-1">
                                                                            <div><span class="font-medium">Nº:</span> {{ $medication['nr_prescricao'] }}</div>
                                                                            @if($medication['funcao_prescritor'])
                                                                                <div><span class="font-medium">Prescritor:</span> {{ $medication['funcao_prescritor'] }}</div>
                                                                            @endif
                                                                            @if($medication['dt_prescricao'])
                                                                                <div><span class="font-medium">Data:</span> {{ \Carbon\Carbon::parse($medication['dt_prescricao'])->format('d/m/Y H:i') }}</div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                @if($medication['profissional'] || ($medication['evento'] && $medication['evento'] !== 'Sem Evento'))
                                                                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                                        <div class="font-medium text-blue-800 text-xs mb-1">Responsável/Eventos</div>
                                                                        <div class="text-xs text-blue-700 space-y-1">
                                                                            @if($medication['profissional'])
                                                                                <div><span class="font-medium">Responsável:</span> {{ $medication['profissional'] }}</div>
                                                                            @endif
                                                                            @if($medication['evento'] && $medication['evento'] !== 'Sem Evento')
                                                                                <div><span class="font-medium">Evento:</span> {{ $medication['evento'] }}</div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                                
                                                                @if($medication['resumo_formatado'])
                                                                    <div class="bg-gray-100 p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 text-xs mb-1">Resumo Formatado</div>
                                                                        <div class="text-xs text-gray-700 font-mono break-words leading-relaxed">
                                                                            {{ $medication['resumo_formatado'] }}
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhum medicamento</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhum medicamento prescrito</p>
                            <p class="text-gray-500 text-xs">para o dia {{ date('d/m/Y') }}</p>
                        </div>
                    @endif
                </div>
                
                <!-- Nutrição -->
                <div x-show="activeCpoeCategory === 'cpoe-nutricao'">
                    @if($patientDetails && isset($patientDetails->cpoe_nutrition) && $patientDetails->cpoe_nutrition['total_count'] > 0)
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span>{{ $patientDetails->cpoe_nutrition['total_count'] }} prescrição(ões) nutricional(is)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto">{{ date('d/m/Y') }}</span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3" x-data="{ selectedNutrition: null }">
                            @foreach(['MANHÃ', 'TARDE', 'NOITE'] as $shift)
                                @php
                                    $shiftData = $patientDetails->cpoe_nutrition['shifts'][$shift] ?? ['count' => 0, 'prescriptions' => []];
                                @endphp
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide">{{ $shift }}</h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                {{ $shiftData['count'] }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        @if($shiftData['count'] > 0)
                                            <div class="space-y-2">
                                                @foreach($shiftData['prescriptions'] as $index => $prescription)
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm {{ $prescription['has_details'] ? 'cursor-pointer' : '' }}"
                                                         @if($prescription['has_details'])
                                                             @click="selectedNutrition = selectedNutrition === '{{ $shift }}_{{ $index }}' ? null : '{{ $shift }}_{{ $index }}'"
                                                         @endif>
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words">
                                                                    {{ $prescription['prescricao'] }}
                                                                </div>
                                                                <div class="text-xs text-gray-600">
                                                                    @if($prescription['periodo_completo'])
                                                                        <span class="font-mono bg-white px-1.5 py-0.5 rounded border">{{ $prescription['periodo_completo'] }}</span>
                                                                    @endif
                                                                    @if($prescription['tipo_nutricao'])
                                                                        <span class="ml-2 font-medium">Tipo: {{ $prescription['tipo_nutricao'] }}</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="flex flex-col items-end space-y-1 ml-2">
                                                                @if($prescription['is_active'])
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>
                                                                        Ativo
                                                                    </span>
                                                                @else
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                                                        <span class="w-1.5 h-1.5 bg-gray-500 rounded-full mr-1"></span>
                                                                        Inativo
                                                                    </span>
                                                                @endif
                                                                
                                                                @if($prescription['has_details'])
                                                                    <svg class="w-3 h-3 text-gray-400 transform transition-transform" 
                                                                         :class="selectedNutrition === '{{ $shift }}_{{ $index }}' ? 'rotate-180' : ''" 
                                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                                    </svg>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Detalhes expandidos -->
                                                        @if($prescription['has_details'])
                                                            <div x-show="selectedNutrition === '{{ $shift }}_{{ $index }}'" 
                                                                 x-transition:enter="transition ease-out duration-200"
                                                                 x-transition:enter-start="opacity-0 max-h-0"
                                                                 x-transition:enter-end="opacity-100 max-h-96"
                                                                 x-transition:leave="transition ease-in duration-150"
                                                                 x-transition:leave-start="opacity-100 max-h-96"
                                                                 x-transition:leave-end="opacity-0 max-h-0"
                                                                 class="overflow-hidden mt-3 pt-3 border-t border-gray-200">
                                                                <div class="space-y-3">
                                                                    <!-- Informações da prescrição -->
                                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                                        <div class="bg-white p-2 rounded border">
                                                                            <div class="font-medium text-gray-600 mb-1">Prescrição</div>
                                                                            <div class="space-y-1">
                                                                                <div><span class="font-medium">Nº:</span> {{ $prescription['nr_prescricao'] }}</div>
                                                                                @if($prescription['funcao_prescritor'])
                                                                                    <div><span class="font-medium">Função:</span> {{ $prescription['funcao_prescritor'] }}</div>
                                                                                @endif
                                                                                @if($prescription['nome_prescritor'])
                                                                                    <div><span class="font-medium">Prescritor:</span> {{ $prescription['nome_prescritor'] }}</div>
                                                                                @endif
                                                                                @if($prescription['dt_prescricao'])
                                                                                    <div><span class="font-medium">Data:</span> {{ \Carbon\Carbon::parse($prescription['dt_prescricao'])->format('d/m/Y H:i') }}</div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <div class="bg-white p-2 rounded border">
                                                                            <div class="font-medium text-gray-600 mb-1">Período</div>
                                                                            <div class="space-y-1">
                                                                                @if($prescription['data_inicio'])
                                                                                    <div><span class="font-medium">Início:</span> {{ $prescription['data_inicio'] }}{{ $prescription['horario_inicio'] ? ' às ' . $prescription['horario_inicio'] : '' }}</div>
                                                                                @endif
                                                                                @if($prescription['data_fim'])
                                                                                    <div><span class="font-medium">Fim:</span> {{ $prescription['data_fim'] }}{{ $prescription['horario_fim'] ? ' às ' . $prescription['horario_fim'] : '' }}</div>
                                                                                @endif
                                                                                <div><span class="font-medium">Status:</span> Ativo</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    @if($prescription['observacoes'])
                                                                        <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                                            <div class="font-medium text-blue-800 text-xs mb-1">Observações</div>
                                                                            <div class="text-xs text-blue-700 break-words leading-relaxed">
                                                                                {{ $prescription['observacoes'] }}
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhuma prescrição nutricional</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhuma prescrição nutricional</p>
                            <p class="text-gray-500 text-xs">para o dia {{ date('d/m/Y') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@else
    <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
        <svg class="w-12 h-12 sm:w-16 sm:h-16 text-red-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p class="text-gray-700 text-base sm:text-lg">Erro ao carregar detalhes do paciente</p>
        <button 
            wire:click="showPatientDetails(
                '{{ is_array($currentPatient) ? ($currentPatient['nr_atendimento'] ?? '') : (is_object($currentPatient) && property_exists($currentPatient, 'nr_atendimento') ? $currentPatient->nr_atendimento : '') }}'
            )"
            class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
        >
            Tentar novamente
        </button>
    </div>
@endif
</div>

<style>
    .custom-scroll {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
    }
    .custom-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    .custom-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    .custom-scroll::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>