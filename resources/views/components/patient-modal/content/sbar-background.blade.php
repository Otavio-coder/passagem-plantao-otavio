@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null
])

<div x-show="activeTab === 'tab-b'" class="p-2 sm:p-3 lg:p-6">
    @if($loadingPatient)
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 lg:py-20">
            <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center" style="top: 50%;">
                <i class="fas fa-spinner fa-3x animate-spin"></i>
            </span>
            <p class="text-gray-700 text-sm sm:text-lg lg:text-xl">Carregando detalhes do paciente...</p>
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
        <div class="bg-white rounded-lg sm:rounded-xl p-3 sm:p-4 lg:p-6 shadow-sm border border-gray-100">
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
                        @php
                            $diagnosticos = $patientDetails->diagnosticos_comorbidades ?? 'Não informado';
                            $diagnosticosList = array_filter(array_map('trim', preg_split('/\|/', $diagnosticos)));
                        @endphp

                        @if(count($diagnosticosList) > 1)
                            <div class="space-y-2">
                                @foreach($diagnosticosList as $diag)
                                    <div class="flex items-center space-x-2 text-sm text-gray-800">
                                        <span class="inline-block w-2 h-2 bg-red-400 rounded-full"></span>
                                        <span class="font-medium">{{ $diag }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-800 font-medium">{{ $diagnosticos }}</p>
                        @endif
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
                            $alergias = preg_replace('/\s*-\s*(Não informado|desconhecido|N\/A)[^;]*/i', '', $alergias);
                            $alergias = preg_replace('/;\s*;/', ';', $alergias);
                            $alergias = trim($alergias, '; ');
                            $alergiasList = array_filter(array_map('trim', explode(';', $alergias)));
                        @endphp

                        @if(count($alergiasList) > 1 && !in_array(strtolower($alergias), ['nenhuma alergia registrada', 'sem alergias registradas']))
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                @foreach($alergiasList as $alergia)
                                    @if(!empty(trim($alergia)))
                                        <div class="flex items-center space-x-2 text-sm text-gray-800">
                                            <span class="inline-block w-2 h-2 bg-red-400 rounded-full"></span>
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
            </div>
        </div>
    @else
        <!-- Error State -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-red-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-gray-700 text-base sm:text-lg">Erro ao carregar detalhes do paciente</p>
            
            <button 
                wire:click="showPatientDetails('{{ $currentPatient['nr_atendimento'] ?? '' }}')"
                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
            >
                Tentar novamente
            </button>
        </div>
    @endif
</div>
