{{-- resources/views/livewire/partials/cpoe-content.blade.php --}}

@php
    $hasProcedures = isset($patientDetails->cpoe_procedures) &&
                     is_array($patientDetails->cpoe_procedures) &&
                     isset($patientDetails->cpoe_procedures['total_count']);

    $hasMedications = isset($patientDetails->cpoe_medications) &&
                      is_array($patientDetails->cpoe_medications) &&
                      isset($patientDetails->cpoe_medications['total_count']);

    $hasNutrition = isset($patientDetails->cpoe_nutrition) &&
                    is_array($patientDetails->cpoe_nutrition) &&
                    isset($patientDetails->cpoe_nutrition['total_count']);

    $hasRecommendations = isset($patientDetails->cpoe_recommendations) &&
                         is_array($patientDetails->cpoe_recommendations) &&
                         isset($patientDetails->cpoe_recommendations['total_count']) &&
                         isset($patientDetails->cpoe_recommendations['items']);

    $hasInterventions = isset($patientDetails->cpoe_interventions) &&
                       is_array($patientDetails->cpoe_interventions) &&
                       isset($patientDetails->cpoe_interventions['total_count']) &&
                       isset($patientDetails->cpoe_interventions['items']);
@endphp

    <!-- Tabs CPOE -->
<div class="border-b border-gray-200 mb-4">
    <nav class="flex space-x-1 overflow-x-auto pb-2 scrollbar-hide">
        <!-- Exames -->
        <button @click.prevent="activeCpoeCategory = 'cpoe-exames'"
                :class="activeCpoeCategory === 'cpoe-exames' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>Procedimentos</span>
                @if($hasProcedures && $patientDetails->cpoe_procedures['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->cpoe_procedures['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Medicamentos -->
        <button @click.prevent="activeCpoeCategory = 'cpoe-medicamentos'"
                :class="activeCpoeCategory === 'cpoe-medicamentos' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <span>Medicamentos</span>
                @if($hasMedications && $patientDetails->cpoe_medications['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->cpoe_medications['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Nutrição -->
        <button @click.prevent="activeCpoeCategory = 'cpoe-nutricao'"
                :class="activeCpoeCategory === 'cpoe-nutricao' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                </svg>
                <span>Nutrição</span>
                @if($hasNutrition && $patientDetails->cpoe_nutrition['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->cpoe_nutrition['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Recomendações -->
        <button @click.prevent="activeCpoeCategory = 'cpoe-recomendacoes'"
                :class="activeCpoeCategory === 'cpoe-recomendacoes' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Recomendações</span>
                @if($hasRecommendations && $patientDetails->cpoe_recommendations['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->cpoe_recommendations['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Intervenções -->
        <button @click.prevent="activeCpoeCategory = 'cpoe-intervencoes'"
                :class="activeCpoeCategory === 'cpoe-intervencoes' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Intervenções</span>
                @if($hasInterventions && $patientDetails->cpoe_interventions['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->cpoe_interventions['total_count'] }}
                    </span>
                @endif
            </div>
        </button>
    </nav>
</div>

<!-- CPOE Content -->
<div class="min-h-[400px]">
    <!-- ==================== EXAMES ==================== -->
    <div x-show="activeCpoeCategory === 'cpoe-exames'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">
        @if($hasProcedures && $patientDetails->cpoe_procedures['total_count'] > 0)
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

    <!-- ==================== MEDICAMENTOS ==================== -->
    <div x-show="activeCpoeCategory === 'cpoe-medicamentos'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">
        @if($hasMedications && $patientDetails->cpoe_medications['total_count'] > 0)
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
                                                    <div class="text-xs text-gray-600 mb-2">
                                                        <span class="font-mono bg-white px-1.5 py-0.5 rounded border mr-2">{{ $medication['horario'] }}</span>
                                                        <span class="font-medium">{{ $medication['dose'] }}</span>
                                                    </div>
                                                    @if(!empty($medication['labels']))
                                                        <div class="flex flex-wrap gap-1 mb-1">
                                                            @foreach($medication['labels'] as $label)
                                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                                    {{ $label }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif
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
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                        <div class="bg-white p-2 rounded border">
                                                            <div class="font-medium text-gray-600 mb-1">Administração</div>
                                                            <div class="space-y-1">
                                                                <div><span class="font-medium">Via:</span> {{ $medication['via_aplicacao'] }}</div>
                                                                <div><span class="font-medium">Dispensar:</span> {{ $medication['dispensar'] }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="bg-white p-2 rounded border">
                                                            <div class="font-medium text-gray-600 mb-1">Prescrição</div>
                                                            <div class="space-y-1">
                                                                <div><span class="font-medium">Nº:</span> {{ $medication['nr_prescricao'] }}</div>
                                                                @if($medication['dt_prescricao'])
                                                                    <div><span class="font-medium">Data:</span> {{ \Carbon\Carbon::parse($medication['dt_prescricao'])->format('d/m/Y H:i') }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
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

    <!-- ==================== NUTRIÇÃO ==================== -->
    <div x-show="activeCpoeCategory === 'cpoe-nutricao'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">
        @if($hasNutrition && $patientDetails->cpoe_nutrition['total_count'] > 0)
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
                                        <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm {{ ($prescription['has_details'] ?? false) ? 'cursor-pointer' : '' }}"
                                             @if(($prescription['has_details'] ?? false))
                                                 @click="selectedNutrition = selectedNutrition === '{{ $shift }}_{{ $index }}' ? null : '{{ $shift }}_{{ $index }}'"
                                            @endif>
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words">
                                                        {{ $prescription['prescricao'] ?? 'Prescrição nutricional' }}
                                                        @if(($prescription['is_jejum'] ?? false))
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200 ml-2">
                                                                JEJUM
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-xs text-gray-600">
                                                        @if(($prescription['periodo_completo'] ?? null))
                                                            <span class="font-mono bg-white px-1.5 py-0.5 rounded border">{{ $prescription['periodo_completo'] }}</span>
                                                        @endif
                                                        @if(($prescription['tipo_nutricao'] ?? null))
                                                            <span class="ml-2 font-medium">Tipo: {{ $prescription['tipo_nutricao'] }}</span>
                                                        @endif
                                                        @if(($prescription['volume'] ?? null))
                                                            <span class="ml-2 font-medium">{{ $prescription['volume'] }}ml</span>
                                                        @endif
                                                        @if(($prescription['kcal_total'] ?? null))
                                                            <span class="ml-2 font-medium">{{ $prescription['kcal_total'] }}kcal</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        @if(($prescription['is_enteral'] ?? false))
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                                Enteral
                                                            </span>
                                                        @endif
                                                        @if(($prescription['is_especial'] ?? false))
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                                                                Especial
                                                            </span>
                                                        @endif
                                                        @if(($prescription['alergias_alimentares'] ?? null))
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                                                Alergias
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="flex flex-col items-end space-y-1 ml-2">
                                                    @if(($prescription['is_active'] ?? false))
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
                                                    @if(($prescription['has_details'] ?? false))
                                                        <svg class="w-3 h-3 text-gray-400 transform transition-transform"
                                                             :class="selectedNutrition === '{{ $shift }}_{{ $index }}' ? 'rotate-180' : ''"
                                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                        </svg>
                                                    @endif
                                                </div>
                                            </div>
                                            <!-- Detalhes expandidos -->
                                            @if(($prescription['has_details'] ?? false))
                                                <div x-show="selectedNutrition === '{{ $shift }}_{{ $index }}'"
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 max-h-0"
                                                     x-transition:enter-end="opacity-100 max-h-96"
                                                     x-transition:leave="transition ease-in duration-150"
                                                     x-transition:leave-start="opacity-100 max-h-96"
                                                     x-transition:leave-end="opacity-0 max-h-0"
                                                     class="overflow-hidden mt-3 pt-3 border-t border-gray-200">
                                                    <div class="space-y-3">
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                            <div class="bg-white p-2 rounded border">
                                                                <div class="font-medium text-gray-600 mb-1">Prescrição</div>
                                                                <div class="space-y-1">
                                                                    <div><span class="font-medium">Nº:</span> {{ $prescription['nr_sequencia'] ?? 'N/A' }}</div>
                                                                    @if(($prescription['nome_nutricionista'] ?? null))
                                                                        <div><span class="font-medium">Nutricionista:</span> {{ $prescription['nome_nutricionista'] }}</div>
                                                                    @endif
                                                                    @if(($prescription['dt_liberacao'] ?? null))
                                                                        <div><span class="font-medium">Liberação:</span> {{ \Carbon\Carbon::parse($prescription['dt_liberacao'])->format('d/m/Y H:i') }}</div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <div class="bg-white p-2 rounded border">
                                                                <div class="font-medium text-gray-600 mb-1">Período & Valores</div>
                                                                <div class="space-y-1">
                                                                    @if(($prescription['horarios'] ?? null))
                                                                        <div><span class="font-medium">Horários:</span> {{ $prescription['horarios'] }}</div>
                                                                    @elseif(($prescription['data_inicio'] ?? null))
                                                                        <div><span class="font-medium">Início:</span> {{ $prescription['data_inicio'] }}{{ ($prescription['horario_inicio'] ?? null) ? ' às ' . $prescription['horario_inicio'] : '' }}</div>
                                                                    @endif
                                                                    @if(($prescription['data_fim'] ?? null))
                                                                        <div><span class="font-medium">Validade:</span> até {{ $prescription['data_fim'] }}</div>
                                                                    @endif
                                                                    @if(($prescription['volume_total'] ?? null))
                                                                        <div><span class="font-medium">Volume Total:</span> {{ $prescription['volume_total'] }}ml</div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @if(($prescription['is_jejum'] ?? false) && (($prescription['tipo_jejum'] ?? null) || ($prescription['objetivo_jejum'] ?? null)))
                                                            <div class="bg-orange-50 p-2 rounded border border-orange-200">
                                                                <div class="font-medium text-orange-800 text-xs mb-1">Informações do Jejum</div>
                                                                <div class="text-xs text-orange-700 space-y-1">
                                                                    @if(($prescription['tipo_jejum'] ?? null))
                                                                        <div><span class="font-medium">Tipo:</span> {{ $prescription['tipo_jejum'] }}</div>
                                                                    @endif
                                                                    @if(($prescription['objetivo_jejum'] ?? null))
                                                                        <div><span class="font-medium">Objetivo:</span> {{ $prescription['objetivo_jejum'] }}</div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif
                                                        @if(($prescription['observacoes'] ?? null) || ($prescription['alergias_alimentares'] ?? null))
                                                            <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                                <div class="font-medium text-blue-800 text-xs mb-1">Observações e Alertas</div>
                                                                <div class="text-xs text-blue-700 space-y-1">
                                                                    @if(($prescription['observacoes'] ?? null))
                                                                        <div><span class="font-medium">Observações:</span> {{ $prescription['observacoes'] }}</div>
                                                                    @endif
                                                                    @if(($prescription['alergias_alimentares'] ?? null))
                                                                        <div class="text-red-700"><span class="font-medium">Alergias:</span> {{ $prescription['alergias_alimentares'] }}</div>
                                                                    @endif
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

    <!-- ==================== RECOMENDAÇÕES ==================== -->
    <div x-show="activeCpoeCategory === 'cpoe-recomendacoes'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">
        @if($hasRecommendations && $patientDetails->cpoe_recommendations['total_count'] > 0)
            @php $recommendations = $patientDetails->cpoe_recommendations['items'] ?? []; @endphp
            <div class="text-sm text-gray-600 mb-3 flex items-center justify-between">
                <span>{{ $patientDetails->cpoe_recommendations['total_count'] }} recomendação(ões) ativa(s)</span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ date('d/m/Y') }}</span>
            </div>
            <div class="space-y-2" x-data="{ selectedRecommendation: null }">
                @foreach($recommendations as $index => $recommendation)
                    <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm {{ $recommendation['has_details'] ? 'cursor-pointer hover:bg-gray-50' : '' }} transition-colors"
                         @if($recommendation['has_details'])
                             @click="selectedRecommendation = selectedRecommendation === {{ $index }} ? null : {{ $index }}"
                        @endif>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                {{-- Tipo + texto da recomendação --}}
                                <div class="text-xs font-medium text-gray-800 leading-snug break-words mb-1">
                                    @if($recommendation['tipo_recomendacao'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 border border-indigo-200 mr-1.5 mb-0.5">
                                            {{ $recommendation['tipo_recomendacao'] }}
                                        </span>
                                    @endif
                                    {{ Str::limit($recommendation['recomendacao'], 120) }}
                                </div>
                                {{-- Metadados: horários, profissional, validade --}}
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    @if($recommendation['horarios'])
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $recommendation['horarios'] }}</span>
                                    @elseif($recommendation['data_inicio'])
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">desde {{ $recommendation['data_inicio'] }}</span>
                                    @endif
                                    @if($recommendation['data_fim'])
                                        <span class="text-amber-600 font-medium">até {{ $recommendation['data_fim'] }}</span>
                                    @endif
                                    @if($recommendation['nome_profissional'])
                                        <span class="text-gray-400">{{ Str::limit($recommendation['nome_profissional'], 25) }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($recommendation['has_details'])
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-0.5 transform transition-transform"
                                     :class="selectedRecommendation === {{ $index }} ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            @endif
                        </div>
                        {{-- Painel expandido --}}
                        @if($recommendation['has_details'])
                            <div x-show="selectedRecommendation === {{ $index }}"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 pt-3 border-t border-gray-200 space-y-2">
                                <div class="bg-indigo-50 p-2 rounded border border-indigo-200">
                                    <div class="font-medium text-indigo-800 text-xs mb-1">Recomendação completa</div>
                                    <div class="text-xs text-indigo-700 break-words leading-relaxed">{{ $recommendation['recomendacao'] }}</div>
                                </div>
                                @if($recommendation['observacoes'])
                                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                        <div class="font-medium text-blue-800 text-xs mb-1">Observações</div>
                                        <div class="text-xs text-blue-700 break-words leading-relaxed">{{ $recommendation['observacoes'] }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 bg-gray-50 rounded border">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-gray-600 text-sm font-medium">Nenhuma recomendação ativa</p>
                <p class="text-gray-500 text-xs">para o dia {{ date('d/m/Y') }}</p>
            </div>
        @endif
    </div>

    <!-- ==================== INTERVENÇÕES ==================== -->
    <div x-show="activeCpoeCategory === 'cpoe-intervencoes'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">
        @if($hasInterventions && $patientDetails->cpoe_interventions['total_count'] > 0)
            @php $interventions = $patientDetails->cpoe_interventions['items'] ?? []; @endphp
            <div class="text-sm text-gray-600 mb-3 flex items-center justify-between">
                <span>{{ $patientDetails->cpoe_interventions['total_count'] }} intervenção(ões) ativa(s)</span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ date('d/m/Y') }}</span>
            </div>
            <div class="space-y-2" x-data="{ selectedIntervention: null }">
                @foreach($interventions as $index => $intervention)
                    <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm {{ $intervention['has_details'] ? 'cursor-pointer hover:bg-gray-50' : '' }} transition-colors"
                         @if($intervention['has_details'])
                             @click="selectedIntervention = selectedIntervention === {{ $index }} ? null : {{ $index }}"
                        @endif>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                {{-- Nome do procedimento --}}
                                <div class="text-xs font-medium text-gray-800 leading-snug break-words mb-1">
                                    {{ $intervention['procedimento'] }}
                                </div>
                                {{-- Labels (urgente, se necessário, ACM, lado) --}}
                                @if(!empty($intervention['labels']))
                                    <div class="flex flex-wrap gap-1 mb-1">
                                        @foreach($intervention['labels'] as $label)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium border
                                                {{ str_contains(strtolower($label), 'urgente') ? 'bg-red-100 text-red-800 border-red-200' :
                                                   (str_contains(strtolower($label), 'lado') ? 'bg-blue-100 text-blue-800 border-blue-200' :
                                                    'bg-gray-100 text-gray-800 border-gray-200') }}">
                                                {{ $label }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                {{-- Metadados: horários, profissional, validade --}}
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    @if($intervention['horarios'])
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $intervention['horarios'] }}</span>
                                    @elseif($intervention['data_inicio'])
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">desde {{ $intervention['data_inicio'] }}</span>
                                    @endif
                                    @if($intervention['data_fim'])
                                        <span class="text-amber-600 font-medium">até {{ $intervention['data_fim'] }}</span>
                                    @endif
                                    @if($intervention['nome_profissional'])
                                        <span class="text-gray-400">{{ Str::limit($intervention['nome_profissional'], 25) }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($intervention['has_details'])
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-0.5 transform transition-transform"
                                     :class="selectedIntervention === {{ $index }} ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            @endif
                        </div>
                        {{-- Painel expandido --}}
                        @if($intervention['has_details'])
                            <div x-show="selectedIntervention === {{ $index }}"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 pt-3 border-t border-gray-200 space-y-2">
                                @if($intervention['observacoes'])
                                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                        <div class="font-medium text-blue-800 text-xs mb-1">Observações</div>
                                        <div class="text-xs text-blue-700 break-words leading-relaxed">{{ $intervention['observacoes'] }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 bg-gray-50 rounded border">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                </svg>
                <p class="text-gray-600 text-sm font-medium">Nenhuma intervenção ativa</p>
                <p class="text-gray-500 text-xs">para o dia {{ date('d/m/Y') }}</p>
            </div>
        @endif
    </div>
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
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    [x-show][style*="display: none"] {
        display: none !important;
        pointer-events: none !important;
    }
</style>
