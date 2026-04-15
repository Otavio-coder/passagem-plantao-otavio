@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null,
    'clinicalData' => [],
])

<div x-show="activeTab === 'tab-b'" class="p-2 sm:p-3">
    @if($loadingPatient)
        <div class="flex flex-col items-center justify-center py-12 sm:py-20">
            <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center">
                <i class="fas fa-spinner fa-3x animate-spin"></i>
            </span>
            <p class="text-gray-700 text-lg sm:text-xl">Carregando detalhes do paciente...</p>
        </div>
    @elseif($currentPatient && !$currentPatient['has_patient'])
        <!-- Empty Bed -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <x-healthicons-o-inpatient class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4" />
            <p class="text-gray-700 text-base sm:text-lg">Leito Vago</p>
            <p class="text-gray-500 mt-2 text-sm sm:text-base">Este leito não possui paciente internado no momento.</p>
        </div>
    @elseif($patientDetails)
        <!-- Background - Contexto clínico relevante -->
        <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100">
            <h4 class="text-sm font-bold text-gray-800 border-b border-gray-200 pb-2 mb-3 flex items-center">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#007D44] text-white mr-2 text-xs font-bold flex-shrink-0">B</span>
                <div>
                    <span class="text-sm">BACKGROUND</span>
                    <p class="text-[10px] text-gray-500 font-normal mt-0.5">Qual o contexto clínico relevante?</p>
                </div>
            </h4>

            <div class="space-y-2">
                <!-- Diagnóstico e Comorbidades -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-red-500 pl-2 bg-red-50 py-1 rounded-r">
                        Diagnóstico e Comorbidades
                    </h5>
                    <div class="bg-gray-50 p-2 rounded-lg border">
                        @if(!empty($clinicalData['cid_history'] ?? []))
                            <div class="space-y-1">
                                @foreach($clinicalData['cid_history'] as $cid)
                                    @php $isInativo = ($cid['situacao'] ?? '') !== 'Ativo'; @endphp
                                    <div class="flex items-start gap-2 text-xs {{ $isInativo ? 'opacity-50' : '' }}">
                                        <span class="inline-block w-1.5 h-1.5 flex-shrink-0 mt-1 rounded-full {{ $isInativo ? 'bg-gray-400' : 'bg-red-400' }}"></span>
                                        <div class="flex-1 min-w-0 leading-tight">
                                            <span class="font-mono font-semibold text-indigo-700">{{ $cid['cd_cid'] ?? '' }}</span>
                                            <span class="text-gray-800 {{ $isInativo ? 'line-through decoration-gray-400' : '' }}"> {{ $cid['ds_cid'] ?? '' }}</span>
                                            @if(($cid['classificacao'] ?? '') === 'Principal')
                                                <span class="ml-1 text-[10px] text-blue-600 font-medium">(Principal)</span>
                                            @elseif(($cid['classificacao'] ?? '') === 'Secundário')
                                                <span class="ml-1 text-[10px] text-gray-400">(Secundário)</span>
                                            @endif
                                            @if($isInativo && !empty($cid['dt_inativacao']))
                                                <span class="ml-1 text-[10px] text-gray-400">· inativado {{ $cid['dt_inativacao'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif(count($clinicalData['diagnosticos_list'] ?? []) > 1)
                            <div class="space-y-1">
                                @foreach(($clinicalData['diagnosticos_list'] ?? []) as $diag)
                                    <div class="flex items-center space-x-2 text-xs text-gray-800">
                                        <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-red-400 rounded-full"></span>
                                        <span class="font-medium">{{ $diag }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-800 font-medium">{{ $clinicalData['diagnosticos'] ?? 'Não informado' }}</p>
                        @endif
                    </div>
                </div>

                <!-- Dispositivos -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-orange-500 pl-2 bg-orange-50 py-1 rounded-r">
                        Dispositivos em Uso
                    </h5>
                    <div class="bg-gray-50 p-2 rounded-lg border">
                        @if(count($clinicalData['dispositivos_list'] ?? []) > 1)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                                @foreach(($clinicalData['dispositivos_list'] ?? []) as $dispositivo)
                                    <div class="flex items-center space-x-2 text-xs text-gray-800">
                                        <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-orange-400 rounded-full"></span>
                                        <span class="font-medium">{{ trim($dispositivo) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-800 font-medium">{{ $clinicalData['dispositivos'] ?? 'Nenhum dispositivo' }}</p>
                        @endif
                    </div>
                </div>

                <!-- Alergias -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-red-500 pl-2 bg-red-50 py-1 rounded-r flex items-center">
                        <x-heroicon-o-exclamation-triangle class="h-3 w-3 mr-1.5 text-red-600 flex-shrink-0" />
                        Alergias Conhecidas
                    </h5>
                    <div class="bg-gray-50 p-2 rounded-lg border">
                        @if(!empty($clinicalData['alergias_items'] ?? []))
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                @foreach(($clinicalData['alergias_items'] ?? []) as $alergia)
                                    @if(!empty($alergia['med'] ?? null))
                                        <div class="flex items-center space-x-1.5 text-xs text-gray-800">
                                            <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-red-400 rounded-full"></span>
                                            <span class="font-medium">{{ $alergia['med'] }}</span>
                                            @if(!empty($alergia['grav']))
                                                <span class="text-gray-500">{{ $alergia['grav'] }}</span>
                                            @endif
                                        </div>
                                    @elseif(!empty($alergia['text'] ?? null))
                                        <div class="flex items-center space-x-1.5 text-xs text-gray-800">
                                            <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-red-400 rounded-full"></span>
                                            <span class="font-medium">{{ $alergia['text'] }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-800 font-medium">{{ $clinicalData['alergias'] ?? 'Nenhuma alergia registrada' }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Error State -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <x-heroicon-o-exclamation-triangle class="w-12 h-12 sm:w-16 sm:h-16 text-red-500 mb-4" />
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
