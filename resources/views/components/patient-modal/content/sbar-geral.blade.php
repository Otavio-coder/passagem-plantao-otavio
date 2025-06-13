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
        <!-- Patient Details Content - NEW LAYOUT: 1x2 Grid -->
        <div class="space-y-6">
            <!-- Situation Section (Full Width Top) -->
            <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                <h4 class="text-base sm:text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 flex items-center">
                    <span class="inline-flex items-center justify-center h-6 w-6 sm:h-7 sm:w-7 rounded-full bg-[#007D44] text-white mr-2 text-xs sm:text-sm">S</span>
                    <span class="text-sm sm:text-base">SITUAÇÃO</span>
                    <span class="text-xs text-gray-500 ml-2">O que está acontecendo no momento?</span>
                </h4>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Patient Identification Grid -->
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700 mb-3 border-l-4 border-blue-500 pl-2">Identificação do Paciente</h5>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 sm:col-span-2">
                                <span class="block text-gray-500 text-xs">Nome Completo:</span>
                                <span class="block text-gray-800 font-medium text-sm">{{ $patientDetails->nm_pessoa_fisica ?? 'Não informado' }}</span>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <span class="block text-gray-500 text-xs">Data de Nascimento:</span>
                                <span class="block text-gray-800 font-medium text-sm">
                                    @if($patientDetails->dt_nascimento)
                                        {{ \Carbon\Carbon::parse($patientDetails->dt_nascimento)->format('d/m/Y') }}
                                    @else
                                        Não informado
                                    @endif
                                </span>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <span class="block text-gray-500 text-xs">Idade Detalhada:</span>
                                <span class="block text-gray-800 font-medium text-sm">
                                    @if($patientDetails->idade_anos !== null)
                                        {{ $patientDetails->idade_anos }}a
                                        @if($patientDetails->idade_meses > 0) {{ $patientDetails->idade_meses }}m @endif
                                        @if($patientDetails->idade_dias > 0) {{ $patientDetails->idade_dias }}d @endif
                                    @else
                                        N/A
                                    @endif
                                </span>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <span class="block text-gray-500 text-xs">Sexo:</span>
                                <span class="block text-gray-800 font-medium text-sm">
                                    @if($patientDetails->sexo === 'F')
                                        <span class="text-pink-600">♀</span> Feminino
                                    @elseif($patientDetails->sexo === 'M')
                                        <span class="text-blue-600">♂</span> Masculino
                                    @else
                                        Não informado
                                    @endif
                                </span>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <span class="block text-gray-500 text-xs">Tempo de Internação:</span>
                                <span class="block text-gray-800 font-medium text-sm">
                                    @if($patientDetails->tempo_internacao_dias === null)
                                        <span class="text-gray-500">N/A</span>
                                    @elseif($patientDetails->tempo_internacao_dias == 0)
                                        <span class="text-green-600 font-semibold">Hoje (recém-chegado)</span>
                                    @else
                                        {{ $patientDetails->tempo_internacao_dias }} dia(s)
                                    @endif
                                </span>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <span class="block text-gray-500 text-xs">Médico Responsável:</span>
                                <span class="block text-gray-800 font-medium text-sm">{{ $patientDetails->medico_responsavel ?? 'Não informado' }}</span>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                <span class="block text-gray-500 text-xs">Convênio:</span>
                                <span class="block text-gray-800 font-medium text-sm">{{ $patientDetails->convenio ?? 'Não informado' }}</span>
                            </div>
                        </div>
                        
                        <!-- Clinical Assessments Grid -->
                        <div class="mt-6">
                            <h5 class="text-sm font-semibold text-gray-700 mb-3 border-l-4 border-orange-500 pl-2">Avaliações Clínicas</h5>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                    <span class="block text-gray-500 text-xs">Medida de Bloqueio:</span>
                                    <span class="block font-medium text-sm {{ $patientDetails->medida_bloqueio === 'Sim' ? 'text-red-800' : 'text-gray-800' }}">
                                        {{ $patientDetails->medida_bloqueio ?? 'Não informado' }}
                                    </span>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                    <span class="block text-gray-500 text-xs">Avaliação ENF.:</span>
                                    <span class="block text-gray-800 font-medium text-sm">{{ $patientDetails->avaliacao_enf ?? 'Não realizada' }}</span>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                    <span class="block text-gray-500 text-xs">Plano Educ.:</span>
                                    <span class="block text-gray-800 font-medium text-sm">{{ $patientDetails->plano_educ ?? 'Não realizado' }}</span>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                    <span class="block text-gray-500 text-xs">PE:</span>
                                    <span class="block text-gray-800 font-medium text-sm">{{ $patientDetails->pe_data ?? 'Não realizado' }}</span>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100 sm:col-span-2">
                                    <span class="block text-gray-500 text-xs">Diagnóstico ENF/SAE:</span>
                                    <span class="block text-gray-800 font-medium text-sm">{{ $patientDetails->diag ?? 'Não informado' }}</span>
                                </div>
                                
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                                    <span class="block text-gray-500 text-xs">Queda:</span>
                                    <span class="block font-medium text-sm {{ $patientDetails->ds_queda !== 'Não' ? 'text-red-800' : 'text-gray-800' }}">
                                        {{ $patientDetails->ds_queda ?? 'Não avaliado' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Scales Section - Right Column -->
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700 mb-3 border-l-4 border-purple-500 pl-2">Escalas de Avaliação</h5>
                        
                        <!-- Micro-Legend for Risk Indicators -->
                        <div class="mb-3 p-2 bg-gray-50 rounded border border-gray-200">
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div class="flex items-center space-x-1">
                                    <div class="w-2 h-2 bg-red-100 border border-red-200 rounded"></div>
                                    <span class="text-gray-600">Alto Risco</span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <div class="w-2 h-2 bg-yellow-100 border border-yellow-200 rounded"></div>
                                    <span class="text-gray-600">Moderado</span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <div class="w-2 h-2 bg-gray-50 border border-gray-200 rounded"></div>
                                    <span class="text-gray-600">Normal</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Scales Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @php
                                $isPediatricPatient = isset($patientDetails->idade_anos) && intval($patientDetails->idade_anos) < 16;
                                $isAdultPatient = isset($patientDetails->idade_anos) && intval($patientDetails->idade_anos) >= 16;
                                
                                // Helper functions (same as before)
                                function getMewsRiskStyling($mewsScore) {
                                    if ($mewsScore === null) return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                    if ($mewsScore >= 5) return ['bg' => 'bg-red-100', 'border' => 'border-red-300', 'text' => 'text-red-900'];
                                    if ($mewsScore >= 3) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => 'text-yellow-900'];
                                    return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                }
                                
                                function getBradenRiskStyling($bradenText) {
                                    if (empty($bradenText) || str_contains(strtolower($bradenText), 'não avaliado')) {
                                        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                    }
                                    $text = strtolower($bradenText);
                                    if (str_contains($text, 'muito elevado') || str_contains($text, 'risco elevado')) {
                                        return ['bg' => 'bg-red-100', 'border' => 'border-red-300', 'text' => 'text-red-900'];
                                    }
                                    if (str_contains($text, 'moderado')) {
                                        return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => 'text-yellow-900'];
                                    }
                                    return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                }
                                
                                function getMorseRiskStyling($morseText) {
                                    if (empty($morseText) || str_contains(strtolower($morseText), 'não avaliado')) {
                                        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                    }
                                    $text = strtolower($morseText);
                                    if (str_contains($text, 'elevado')) {
                                        return ['bg' => 'bg-red-100', 'border' => 'border-red-300', 'text' => 'text-red-900'];
                                    }
                                    if (str_contains($text, 'médio')) {
                                        return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => 'text-yellow-900'];
                                    }
                                    return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                }
                                
                                function getPewsRiskStyling($pewsText) {
                                    if (empty($pewsText) || str_contains(strtolower($pewsText), 'não avaliado')) {
                                        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                    }
                                    preg_match('/PEWS:\s*(\d+)/', $pewsText, $matches);
                                    if (isset($matches[1])) {
                                        $score = intval($matches[1]);
                                        if ($score >= 5) return ['bg' => 'bg-red-100', 'border' => 'border-red-300', 'text' => 'text-red-900'];
                                        if ($score >= 3) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => 'text-yellow-900'];
                                    }
                                    return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                }
                                
                                function getPainRiskStyling($painText) {
                                    if (empty($painText) || str_contains(strtolower($painText), 'não avaliado')) {
                                        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                    }
                                    preg_match('/Dor:\s*([\d,\.]+)/', $painText, $matches);
                                    if (isset($matches[1])) {
                                        $score = floatval(str_replace(',', '.', $matches[1]));
                                        if ($score > 10) return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                        if ($score >= 7) return ['bg' => 'bg-red-100', 'border' => 'border-red-300', 'text' => 'text-red-900'];
                                        if ($score >= 4) return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => 'text-yellow-900'];
                                    }
                                    return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                }
                                
                                function getTevRiskStyling($tevText) {
                                    if (empty($tevText) || str_contains(strtolower($tevText), 'não avaliado')) {
                                        return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                    }
                                    $text = strtolower($tevText);
                                    if (str_contains($text, 'alto')) {
                                        return ['bg' => 'bg-red-100', 'border' => 'border-red-300', 'text' => 'text-red-900'];
                                    }
                                    if (str_contains($text, 'intermediário')) {
                                        return ['bg' => 'bg-yellow-100', 'border' => 'border-yellow-300', 'text' => 'text-yellow-900'];
                                    }
                                    return ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-800'];
                                }
                            @endphp
                            
                            <!-- MEWS Scale -->
                            @if(!$isPediatricPatient || $isAdultPatient)
                                @php
                                    $mewsScore = null;
                                    if (isset($patientDetails->ds_mews)) {
                                        preg_match('/MEWS:\s*(\d+)/', $patientDetails->ds_mews, $matches);
                                        $mewsScore = isset($matches[1]) ? intval($matches[1]) : null;
                                    }
                                    $mewsStyling = getMewsRiskStyling($mewsScore);
                                @endphp
                                <div class="{{ $mewsStyling['bg'] }} p-2 rounded border {{ $mewsStyling['border'] }}">
                                    <span class="block text-gray-500 text-xs font-medium mb-1">MEWS:</span>
                                    <span class="block {{ $mewsStyling['text'] }} font-medium text-xs">
                                        {{ $patientDetails->ds_mews ?? 'Não avaliado' }}
                                    </span>
                                </div>
                            @endif
                            
                            <!-- Other scales... -->
                            @php $bradenStyling = getBradenRiskStyling($patientDetails->ds_braden ?? ''); @endphp
                            <div class="{{ $bradenStyling['bg'] }} p-2 rounded border {{ $bradenStyling['border'] }}">
                                <span class="block text-gray-500 text-xs font-medium mb-1">Braden:</span>
                                <span class="block {{ $bradenStyling['text'] }} font-medium text-xs">
                                    {{ $patientDetails->ds_braden ?? 'Não avaliado' }}
                                </span>
                            </div>
                            
                            @php $morseStyling = getMorseRiskStyling($patientDetails->ds_morse ?? ''); @endphp
                            <div class="{{ $morseStyling['bg'] }} p-2 rounded border {{ $morseStyling['border'] }}">
                                <span class="block text-gray-500 text-xs font-medium mb-1">Morse:</span>
                                <span class="block {{ $morseStyling['text'] }} font-medium text-xs">
                                    {{ $patientDetails->ds_morse ?? 'Não avaliado' }}
                                </span>
                            </div>
                            
                            @if($isPediatricPatient && isset($patientDetails->ds_pews) && $patientDetails->ds_pews)
                                @php $pewsStyling = getPewsRiskStyling($patientDetails->ds_pews); @endphp
                                <div class="{{ $pewsStyling['bg'] }} p-2 rounded border {{ $pewsStyling['border'] }}">
                                    <span class="block text-gray-500 text-xs font-medium mb-1">PEWS:</span>
                                    <span class="block {{ $pewsStyling['text'] }} font-medium text-xs">
                                        {{ $patientDetails->ds_pews }}
                                    </span>
                                </div>
                            @endif
                            
                            @php $painStyling = getPainRiskStyling($patientDetails->ds_dor ?? ''); @endphp
                            <div class="{{ $painStyling['bg'] }} p-2 rounded border {{ $painStyling['border'] }}">
                                <span class="block text-gray-500 text-xs font-medium mb-1">Dor:</span>
                                <span class="block {{ $painStyling['text'] }} font-medium text-xs">
                                    {{ $patientDetails->ds_dor ?? 'Não avaliado' }}
                                </span>
                            </div>
                            
                            @php $tevStyling = getTevRiskStyling($patientDetails->ds_tev ?? ''); @endphp
                            <div class="{{ $tevStyling['bg'] }} p-2 rounded border {{ $tevStyling['border'] }}">
                                <span class="block text-gray-500 text-xs font-medium mb-1">TEV:</span>
                                <span class="block {{ $tevStyling['text'] }} font-medium text-xs">
                                    {{ $patientDetails->ds_tev ?? 'Não avaliado' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Row: Background and Recommendations Side by Side -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                <!-- Background Section (Bottom Left) -->
                <div class="bg-white rounded-xl p-4 sm:p-5 shadow-sm border border-gray-100">
                    <h4 class="text-base sm:text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 flex items-center">
                        <span class="inline-flex items-center justify-center h-6 w-6 sm:h-7 sm:w-7 rounded-full bg-[#007D44] text-white mr-2 text-xs sm:text-sm">B</span>
                        <span class="text-sm sm:text-base">BACKGROUND</span>
                        <span class="text-xs text-gray-500 ml-2">Contexto clínico relevante</span>
                    </h4>
                    
                    <div class="space-y-4">
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-gray-500 text-xs sm:text-sm font-medium mb-2">Diagnóstico e Comorbidades:</span>
                            <span class="block text-gray-800 font-medium text-sm sm:text-base">
                                {{ $patientDetails->diagnosticos_comorbidades ?? 'Não informado' }}
                            </span>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-gray-500 text-xs sm:text-sm font-medium mb-2">Dispositivos:</span>
                            <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->dispositivos ?? 'Nenhum dispositivo' }}</span>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                            <span class="block text-gray-500 text-xs sm:text-sm font-medium mb-2">Alergias:</span>
                            <span class="block text-gray-800 font-medium text-sm sm:text-base">{{ $patientDetails->alergias_detalhadas ?? 'Nenhuma alergia registrada' }}</span>
                        </div>
                        
                        @if($patientDetails->medida_bloqueio === 'Sim' && $patientDetails->motivos_isolamento)
                            <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                                <span class="block text-red-700 text-xs sm:text-sm font-medium mb-2">Motivos de Isolamento:</span>
                                <span class="block text-red-800 font-medium text-sm sm:text-base">{{ $patientDetails->motivos_isolamento }}</span>
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