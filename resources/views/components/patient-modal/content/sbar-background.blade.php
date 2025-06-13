@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null
])

<div x-show="activeTab === 'tab-b'" class="p-3 sm:p-6">
    @if($loadingPatient)
        <div class="flex flex-col items-center justify-center py-12 sm:py-20">
            <div class="w-12 h-12 sm:w-16 sm:h-16 border-t-4 border-r-4 border-[#0071B9] border-solid rounded-full animate-spin mb-4"></div>
            <p class="text-gray-700 text-lg sm:text-xl">Carregando detalhes do paciente...</p>
        </div>
    @elseif($currentPatient && !$currentPatient['has_patient'])
        <!-- Empty Bed -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-gray-700 text-base sm:text-lg">Leito Vazio</p>
            <p class="text-gray-500 mt-2 text-sm sm:text-base">Este leito não possui paciente internado no momento.</p>
        </div>
    @elseif($patientDetails)
        <!-- Background - Contexto clínico relevante -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
            <h4 class="text-lg sm:text-xl font-bold text-gray-800 border-b border-gray-200 pb-3 mb-6 flex items-center">
                <span class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-[#007D44] text-white mr-3 text-sm sm:text-base font-bold">B</span>
                <div>
                    <span class="text-base sm:text-lg">BACKGROUND</span>
                    <p class="text-xs text-gray-500 font-normal mt-1">Qual o contexto clínico relevante?</p>
                </div>
            </h4>
            
            <div class="space-y-8">
                <!-- Diagnóstico e Comorbidades -->
                <div>
                    <h5 class="text-sm font-bold text-gray-800 mb-4 border-l-4 border-red-500 pl-3 bg-red-50 py-2 rounded-r">
                        Diagnóstico e Comorbidades
                    </h5>
                    <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                        <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">
                            @php
                                $diagnosticos = $patientDetails->diagnosticos_comorbidades ?? 'Não informado';
                                // Organizar melhor o texto dos diagnósticos
                                $diagnosticos = str_replace(['>', '<'], ['• ', ''], $diagnosticos);
                                $diagnosticos = str_replace(['|', ' - '], ["\n• ", ' → '], $diagnosticos);
                            @endphp
                            {{ $diagnosticos }}
                        </div>
                    </div>
                </div>
                
                <!-- Dispositivos -->
                <div>
                    <h5 class="text-sm font-bold text-gray-800 mb-4 border-l-4 border-orange-500 pl-3 bg-orange-50 py-2 rounded-r">
                        Dispositivos em Uso
                    </h5>
                    <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                        @php
                            $dispositivos = $patientDetails->dispositivos ?? 'Nenhum dispositivo';
                            $dispositivosList = array_filter(explode('|', $dispositivos));
                        @endphp
                        
                        @if(count($dispositivosList) > 1)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($dispositivosList as $dispositivo)
                                    <div class="flex items-center space-x-2 text-sm text-gray-800">
                                        <span class="inline-block w-2 h-2 bg-orange-400 rounded-full"></span>
                                        <span class="font-medium">{{ trim($dispositivo) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-800 font-medium">{{ $dispositivos }}</p>
                        @endif
                    </div>
                </div>
                
                <!-- Alergias - PADRONIZADO -->
                <div>
                    <h5 class="text-sm font-bold text-gray-800 mb-4 border-l-4 border-red-500 pl-3 bg-red-50 py-2 rounded-r flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Alergias Conhecidas
                    </h5>
                    <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                        @php
                            $alergias = $patientDetails->alergias_detalhadas ?? 'Nenhuma alergia registrada';
                            // Remover "- Não informado/desconhecido" como você sugeriu
                            $alergias = preg_replace('/\s*-\s*(Não informado|desconhecido|N\/A)[^;]*/i', '', $alergias);
                            $alergias = preg_replace('/;\s*;/', ';', $alergias); // Remove ; duplos
                            $alergias = trim($alergias, '; '); // Remove ; no início/fim
                            
                            $alergiasList = array_filter(array_map('trim', explode(';', $alergias)));
                        @endphp
                        
                        @if(count($alergiasList) > 1 && !in_array(strtolower($alergias), ['nenhuma alergia registrada', 'sem alergias registradas']))
                            <div class="space-y-2">
                                @foreach($alergiasList as $alergia)
                                    @if(!empty(trim($alergia)))
                                        <div class="flex items-start space-x-2 text-sm text-gray-800">
                                            <span class="inline-block w-2 h-2 bg-red-400 rounded-full mt-1.5 flex-shrink-0"></span>
                                            <span class="font-medium">{{ trim($alergia) }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-800 font-medium">{{ $alergias ?: 'Nenhuma alergia registrada' }}</p>
                        @endif
                    </div>
                </div>
                
                <!-- Informações Complementares -->
                <div>
                    <h5 class="text-sm font-bold text-gray-800 mb-4 border-l-4 border-indigo-500 pl-3 bg-indigo-50 py-2 rounded-r">
                        Informações Complementares
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tempo de Internação:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                @if($patientDetails->tempo_internacao_dias === null)
                                    <span class="text-gray-500">N/A</span>
                                @elseif($patientDetails->tempo_internacao_dias == 0)
                                    <span class="text-green-600 font-bold">Recém-chegado (hoje)</span>
                                @else
                                    {{ $patientDetails->tempo_internacao_dias }} dia{{ $patientDetails->tempo_internacao_dias > 1 ? 's' : '' }}
                                @endif
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Histórico de Quedas:</label>
                            <p class="text-sm font-bold {{ $patientDetails->ds_queda !== 'Não' ? 'text-red-700' : 'text-green-700' }}">
                                {{ $patientDetails->ds_queda ?? 'Não avaliado' }}
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Medida de Bloqueio:
                            </label>
                            <p class="text-sm font-bold {{ $patientDetails->medida_bloqueio === 'Sim' ? 'text-yellow-700' : 'text-green-700' }}">
                                {{ $patientDetails->medida_bloqueio ?? 'Não informado' }}
                                @if($patientDetails->medida_bloqueio === 'Sim' && $patientDetails->motivos_isolamento)
                                    <span class="block text-xs text-yellow-600 mt-1 font-normal">
                                        {{ $patientDetails->motivos_isolamento }}
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- ...existing error state... -->
    @endif
</div>
