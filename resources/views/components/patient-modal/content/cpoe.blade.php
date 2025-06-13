@props([
    'patientDetails' => null
])

<div x-show="activeTab === 'tab-3'" class="p-3 sm:p-6">
    <!-- CPOE Header -->
    <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg sm:text-xl font-semibold text-gray-800">CPOE - {{ date('d/m/Y') }}</h3>
            <div class="text-sm text-gray-500">
                Prescrições e Procedimentos do Dia
            </div>
        </div>
        
        <!-- CPOE Categories Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex space-x-1 overflow-x-auto pb-2" style="scrollbar-width: thin;">
                <!-- Category 1: Exames e Procedimentos -->
                <button @click="activeCpoeCategory = 'cpoe-exames'"
                        :class="activeCpoeCategory === 'cpoe-exames' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                        class="flex-shrink-0 px-3 py-2 text-xs font-medium rounded-lg border-b-2 whitespace-nowrap">
                    <div class="flex items-center space-x-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <span>Exames e Procedimentos</span>
                    </div>
                </button>
                
                <!-- Category 2: Soluções e Medicamentos -->
                <button @click="activeCpoeCategory = 'cpoe-medicamentos'"
                        :class="activeCpoeCategory === 'cpoe-medicamentos' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                        class="flex-shrink-0 px-3 py-2 text-xs font-medium rounded-lg border-b-2 whitespace-nowrap">
                    <div class="flex items-center space-x-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span>Soluções e Medicamentos</span>
                    </div>
                </button>
                
                <!-- Category 3: Nutrição -->
                <button @click="activeCpoeCategory = 'cpoe-nutricao'"
                        :class="activeCpoeCategory === 'cpoe-nutricao' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                        class="flex-shrink-0 px-3 py-2 text-xs font-medium rounded-lg border-b-2 whitespace-nowrap">
                    <div class="flex items-center space-x-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                        </svg>
                        <span>Nutrição</span>
                    </div>
                </button>
            </nav>
        </div>
        
        <!-- CPOE Category Contents -->
        <div class="cpoe-category-contents">
            
            <!-- Category 1: Exames e Procedimentos -->
            <div x-show="activeCpoeCategory === 'cpoe-exames'">
                <div class="mb-4">
                    <h4 class="text-md font-semibold text-gray-800 mb-2">Exames e Procedimentos - {{ date('d/m/Y') }}</h4>
                    @if($patientDetails && isset($patientDetails->cpoe_procedures) && $patientDetails->cpoe_procedures['total_count'] > 0)
                        <div class="text-sm text-gray-600 mb-4">
                            Total de {{ $patientDetails->cpoe_procedures['total_count'] }} procedimento(s) agendado(s)
                        </div>
                        
                        <!-- Kanban por Turnos -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach(['MANHÃ', 'TARDE', 'NOITE'] as $shift)
                                @php
                                    $shiftData = $patientDetails->cpoe_procedures['shifts'][$shift] ?? ['count' => 0, 'procedures' => []];
                                    $shiftColors = [
                                        'MANHÃ' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-800'],
                                        'TARDE' => ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'text' => 'text-orange-800'],
                                        'NOITE' => ['bg' => 'bg-purple-50', 'border' => 'border-purple-200', 'text' => 'text-purple-800']
                                    ];
                                    $colors = $shiftColors[$shift];
                                @endphp
                                
                                <div class="kanban-column {{ $colors['bg'] }} {{ $colors['border'] }} border rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="font-semibold {{ $colors['text'] }} text-sm">
                                            {{ $shift }}
                                            @if($shift === 'MANHÃ')
                                                <span class="text-xs opacity-75">(06h-14h)</span>
                                            @elseif($shift === 'TARDE')
                                                <span class="text-xs opacity-75">(14h-22h)</span>
                                            @else
                                                <span class="text-xs opacity-75">(22h-06h)</span>
                                            @endif
                                        </h5>
                                        <span class="bg-white {{ $colors['text'] }} text-xs font-medium px-2 py-1 rounded-full border">
                                            {{ $shiftData['count'] }}
                                        </span>
                                    </div>
                                    
                                    @if($shiftData['count'] > 0)
                                        <div class="max-h-96 overflow-y-auto space-y-2 pr-1">
                                            @foreach($shiftData['procedures'] as $procedure)
                                                <div class="bg-white p-3 rounded-lg border {{ $procedure['is_completed'] ? 'opacity-75 border-green-300 bg-green-50' : 'border-gray-200' }} shadow-sm">
                                                    <div class="flex justify-between items-start mb-2">
                                                        <div class="font-medium text-gray-800 text-sm">
                                                            {{ $procedure['procedimento'] }}
                                                        </div>
                                                        @if($procedure['is_completed'])
                                                            <div class="ml-2 flex-shrink-0" title="Concluído em: {{ $procedure['dt_baixa'] }}">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    
                                                    <div class="text-gray-600 text-xs space-y-1">
                                                        <div class="flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            {{ $procedure['horario'] }}
                                                        </div>
                                                        @if($procedure['horarios'])
                                                            <div class="flex items-center">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                </svg>
                                                                {{ $procedure['horarios'] }}
                                                            </div>
                                                        @endif
                                                        @if($procedure['nr_prescricao'])
                                                            <div class="flex items-center">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                                </svg>
                                                                Prescrição: {{ $procedure['nr_prescricao'] }}
                                                            </div>
                                                        @endif
                                                        @if($procedure['is_completed'])
                                                            <div class="text-green-700 font-medium bg-green-100 px-2 py-1 rounded mt-2">
                                                                ✓ Concluído: {{ $procedure['dt_baixa'] }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-gray-500 text-xs text-center py-8 bg-white rounded border-2 border-dashed border-gray-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                            Nenhum procedimento
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p class="text-gray-600 font-medium">Nenhum exame ou procedimento agendado</p>
                            <p class="text-gray-500 text-sm">para o dia de hoje</p>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Category 2: Soluções e Medicamentos -->
            <div x-show="activeCpoeCategory === 'cpoe-medicamentos'">
                <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                    <p class="text-gray-600 font-medium">Soluções e Medicamentos</p>
                    <p class="text-gray-500 text-sm">🚧 Em desenvolvimento</p>
                </div>
            </div>
            
            <!-- Category 3: Nutrição -->
            <div x-show="activeCpoeCategory === 'cpoe-nutricao'">
                <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                    </svg>
                    <p class="text-gray-600 font-medium">Nutrição</p>
                    <p class="text-gray-500 text-sm">🚧 Em desenvolvimento</p>
                </div>
            </div>
        </div>
    </div>
</div>