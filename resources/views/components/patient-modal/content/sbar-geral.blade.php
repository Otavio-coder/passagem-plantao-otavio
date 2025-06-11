@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null
])

<div x-show="activeTab === 'tab-1'" class="p-3 sm:p-6">
    <!-- Livewire loading state -->
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
        <!-- Patient Details Content - 2x2 Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <!-- Situation Section (Top Left) -->
            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                <h4 class="text-base sm:text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 flex items-center">
                    <span class="inline-flex items-center justify-center h-6 w-6 sm:h-7 sm:w-7 rounded-full bg-[#007D44] text-white mr-2 text-xs sm:text-sm">S</span>
                    <span class="text-sm sm:text-base">SITUAÇÃO</span>
                </h4>
                
                <div class="space-y-3">
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Nome do Paciente:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->nm_pessoa_fisica ?? 'Não informado' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Diagnóstico SAE:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->diag ?? 'Não informado' }}</span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-gray-500 text-xs sm:text-sm">Idade:</span>
                            <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->idade_anos ?? 'N/A' }} anos</span>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-gray-500 text-xs sm:text-sm">Tempo Internação:</span>
                            <span class="block text-gray-800 font-medium text-sm sm:text-base">
                                @if($patientDetails->tempo_internacao_dias === null)
                                    <span class="text-gray-500">N/A</span>
                                @elseif($patientDetails->tempo_internacao_dias == 0)
                                    <span class="text-green-600 font-semibold">Hoje</span>
                                @else
                                    {{ $patientDetails->tempo_internacao_dias }} dia(s)
                                @endif
                            </span>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Convênio:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->convenio ?? 'Não informado' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Médico Responsável:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->medico_responsavel ?? 'Não informado' }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Background Section (Top Right) -->
            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                <h4 class="text-base sm:text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 flex items-center">
                    <span class="inline-flex items-center justify-center h-6 w-6 sm:h-7 sm:w-7 rounded-full bg-[#007D44] text-white mr-2 text-xs sm:text-sm">B</span>
                    <span class="text-sm sm:text-base">BACKGROUND</span>
                </h4>
                
                <div class="space-y-3">
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Alergias:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->alergias_detalhadas ?? 'Nenhuma alergia registrada' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Dispositivos:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->dispositivos ?? 'Nenhum dispositivo' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Isolamento:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->ds_isolamento ?? 'Não informado' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Precauções Ativas:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->precaucoes ?? 'Nenhuma precaução' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Histórico de queda:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->ds_queda ?? 'Sem registro' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Consultas Futuras:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->futureinquiries ?? 'Sem consultas agendadas' }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Assessment Section (Bottom Left) -->
            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                <h4 class="text-base sm:text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 flex items-center">
                    <span class="inline-flex items-center justify-center h-6 w-6 sm:h-7 sm:w-7 rounded-full bg-[#FFC107] text-white mr-2 text-xs sm:text-sm">A</span>
                    <span class="text-sm sm:text-base">AVALIAÇÃO</span>
                </h4>
                
                <div class="space-y-3">
                    @php
                        // Define age thresholds for scales
                        $isPediatricPatient = isset($patientDetails->idade_anos) && intval($patientDetails->idade_anos) < 16;
                        $isAdultPatient = isset($patientDetails->idade_anos) && intval($patientDetails->idade_anos) >= 16;
                    @endphp
                    
                    @if((!$isPediatricPatient || $isAdultPatient) && (isset($patientDetails->mews_score) || isset($patientDetails->ds_mews)))
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Escala MEWS:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">
                            @if(isset($patientDetails->mews_score) && $patientDetails->mews_score === null && isset($patientDetails->tempo_internacao_dias) && $patientDetails->tempo_internacao_dias === 0)
                                <span class="text-green-600">Pendente - Paciente recém-chegado</span>
                            @elseif(isset($patientDetails->mews_score) && ($patientDetails->mews_score === null || $patientDetails->mews_score == 0) && isset($patientDetails->tempo_internacao_dias) && $patientDetails->tempo_internacao_dias > 0)
                                <span class="text-red-600">MEWS não realizado</span>
                            @else
                                {{ $patientDetails->ds_mews ?? 'Não avaliado' }}
                            @endif
                        </span>
                    </div>
                    @endif
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Escala Braden:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->ds_braden ?? 'Não avaliado' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Escala Morse:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->ds_morse ?? 'Não avaliado' }}</span>
                    </div>
                    
                    @if($isPediatricPatient && isset($patientDetails->ds_pews) && $patientDetails->ds_pews)
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Escala PEWS:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->ds_pews ?? 'Não avaliado' }}</span>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Recommendation Section (Bottom Right) -->
            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                <h4 class="text-base sm:text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 flex items-center">
                    <span class="inline-flex items-center justify-center h-6 w-6 sm:h-7 sm:w-7 rounded-full bg-[#28a745] text-white mr-2 text-xs sm:text-sm">R</span>
                    <span class="text-sm sm:text-base">RECOMENDAÇÕES</span>
                </h4>
                
                <div class="space-y-3">
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Exames Prioritários:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->prioridade_exames ?? 'Nenhum exame prioritário' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Antimicrobianos:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->materiais ?? 'Nenhum antimicrobiano' }}</span>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                        <span class="block text-gray-500 text-xs sm:text-sm">Exames Agendados:</span>
                        <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->exams ?? 'Nenhum exame agendado' }}</span>
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
            
            <!-- Retry Button -->
            <button 
                wire:click="showPatientDetails('{{ $currentPatient['nr_atendimento'] ?? '' }}')"
                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
            >
                Tentar novamente
            </button>
        </div>
    @endif
</div>