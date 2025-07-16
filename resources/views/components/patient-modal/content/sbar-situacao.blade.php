@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null
])

<div x-show="activeTab === 'tab-s'" class="p-3 sm:p-6">
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
        <!-- Situação - O que está acontecendo no momento? -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
            <h4 class="text-lg sm:text-xl font-bold text-gray-800 border-b border-gray-200 pb-3 mb-6 flex items-center">
                <span class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-[#007D44] text-white mr-3 text-sm sm:text-base font-bold">S</span>
                <div>
                    <span class="text-base sm:text-lg">SITUAÇÃO</span>
                    <p class="text-xs text-gray-500 font-normal mt-1">O que está acontecendo no momento?</p>
                </div>
            </h4>
            
            <div class="space-y-8">
                <!-- Identificação do Paciente -->
                <div>
                    <h5 class="text-sm font-bold text-gray-800 mb-4 border-l-4 border-blue-500 pl-3 bg-blue-50 py-2 rounded-r">
                        Identificação do Paciente
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2 bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nome Completo:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->nm_pessoa_fisica ?? 'Não informado' }}</p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Data de Nascimento:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                @if($patientDetails->dt_nascimento)
                                    {{ \Carbon\Carbon::parse($patientDetails->dt_nascimento)->format('d/m/Y') }}
                                @else
                                    Não informado
                                @endif
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Idade (Anos, Meses, Dias):</label>
                            <p class="text-sm font-semibold text-gray-800">
                                @if($patientDetails->idade_anos !== null)
                                    {{ $patientDetails->idade_anos }}a
                                    @if($patientDetails->idade_meses > 0) {{ $patientDetails->idade_meses }}m @endif
                                    @if($patientDetails->idade_dias > 0) {{ $patientDetails->idade_dias }}d @endif
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Sexo:</label>
                            <p class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                                @if($patientDetails->sexo === 'F')
                                    <span class="inline-flex items-center justify-center text-pink-600" style="font-size:1.75rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <line x1="12" y1="12" x2="12" y2="20" stroke="currentColor" stroke-width="2"/>
                                            <line x1="9" y1="17" x2="15" y2="17" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs text-gray-500 font-normal">Feminino</span>
                                @elseif($patientDetails->sexo === 'M')
                                    <span class="inline-flex items-center justify-center text-blue-600" style="font-size:1.75rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <line x1="16" y1="8" x2="20" y2="4" stroke="currentColor" stroke-width="2"/>
                                            <line x1="20" y1="4" x2="20" y2="8" stroke="currentColor" stroke-width="2"/>
                                            <line x1="20" y1="4" x2="16" y2="4" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs text-gray-500 font-normal">Masculino</span>
                                @else
                                    <span class="text-xs text-gray-500 font-normal">Não informado</span>
                                @endif
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tempo de Internação:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                @if($patientDetails->tempo_internacao_dias === null)
                                    <span class="text-gray-500">N/A</span>
                                @elseif(is_numeric($patientDetails->tempo_internacao_dias) && $patientDetails->tempo_internacao_dias >= 0 && $patientDetails->tempo_internacao_dias < 1)
                                    <span class="text-green-600 font-bold">
                                        Recém-chegado (hoje)
                                    </span>
                                @else
                                    @php $days = ceil($patientDetails->tempo_internacao_dias); @endphp
                                    {{ $days }} dia{{ $days != 1 ? 's' : '' }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <!-- Médico Responsável e Convênio lado a lado -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Médico Responsável:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->medico_responsavel ?? 'Não informado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Convênio:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->convenio ?? 'Não informado' }}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Avaliações e Status Clínico -->
                <div>
                    <h5 class="text-sm font-bold text-gray-800 mb-4 border-l-4 border-green-500 pl-3 bg-green-50 py-2 rounded-r">
                        Avaliações e Status Clínico
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Medida de Bloqueio:</label>
                            <p class="text-sm font-bold {{ $patientDetails->medida_bloqueio === 'Sim' ? 'text-red-700' : 'text-green-700' }}">
                                {{ $patientDetails->medida_bloqueio ?? 'Não informado' }}
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Avaliação ENF.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->avaliacao_enf ?? 'Não realizada' }}</p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Plano Educ.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->plano_educ ?? 'Não realizado' }}</p>
                        </div>
                    </div>
                    <!-- PE, Diag. ENF. e Queda lado a lado -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">PE:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->pe_data ?? 'Não realizado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Diag. ENF.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->diag ?? 'Não informado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Queda:</label>
                            <p class="text-sm font-bold {{ $patientDetails->ds_queda !== 'Não' ? 'text-red-700' : 'text-green-700' }}">
                                {{ $patientDetails->ds_queda ?? 'Não avaliado' }}
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Escalas de Avaliação -->
                <div>
                    <h5 class="text-sm font-bold text-gray-800 mb-4 border-l-4 border-purple-500 pl-3 bg-purple-50 py-2 rounded-r">
                        Escalas de Avaliação
                    </h5>
                    
                    <!-- Legenda de Risco -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg border">
                        <div class="grid grid-cols-3 gap-3 text-xs">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-red-100 border border-red-300 rounded"></div>
                                <span class="text-gray-700 font-medium">Alto Risco</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-yellow-100 border border-yellow-300 rounded"></div>
                                <span class="text-gray-700 font-medium">Risco Moderado</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 bg-gray-50 border border-gray-300 rounded"></div>
                                <span class="text-gray-700 font-medium">Normal/Baixo Risco</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @php
                            $isPediatricPatient = isset($patientDetails->idade_anos) && intval($patientDetails->idade_anos) < 16;
                            $isAdultPatient = isset($patientDetails->idade_anos) && intval($patientDetails->idade_anos) >= 16;
                            
                            // Helper functions for risk styling
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
                        
                        <!-- MEWS Scale (para pacientes adultos) -->
                        @if(!$isPediatricPatient)
                            @php
                                $mewsScore = null;
                                if (isset($patientDetails->ds_mews)) {
                                    preg_match('/MEWS:\s*(\d+)/', $patientDetails->ds_mews, $matches);
                                    $mewsScore = isset($matches[1]) ? intval($matches[1]) : null;
                                }
                                $mewsStyling = getMewsRiskStyling($mewsScore);
                            @endphp
                            <div class="{{ $mewsStyling['bg'] }} p-4 rounded-lg border {{ $mewsStyling['border'] }}">
                                <label class="block text-xs font-medium text-gray-600 mb-1">MEWS (Modified Early Warning Score):</label>
                                <p class="text-sm font-bold {{ $mewsStyling['text'] }}">
                                    {{ $patientDetails->ds_mews ?? 'Não avaliado' }}
                                </p>
                            </div>
                        @endif
                        
                        <!-- PEWS Scale (para pacientes pediátricos) -->
                        @if($isPediatricPatient)
                            @php $pewsStyling = getPewsRiskStyling($patientDetails->ds_pews ?? ''); @endphp
                            <div class="{{ $pewsStyling['bg'] }} p-4 rounded-lg border {{ $pewsStyling['border'] }}">
                                <label class="block text-xs font-medium text-gray-600 mb-1">PEWS (Pediatric Early Warning Score):</label>
                                <p class="text-sm font-bold {{ $pewsStyling['text'] }}">
                                    {{ $patientDetails->ds_pews ?? 'Não avaliado' }}
                                </p>
                            </div>
                        @endif
                        
                        <!-- Braden Scale -->
                        @php $bradenStyling = getBradenRiskStyling($patientDetails->ds_braden ?? ''); @endphp
                        <div class="{{ $bradenStyling['bg'] }} p-4 rounded-lg border {{ $bradenStyling['border'] }}">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Braden (Escala de Braden):</label>
                            <p class="text-sm font-bold {{ $bradenStyling['text'] }}">
                                {{ $patientDetails->ds_braden ?? 'Não avaliado' }}
                            </p>
                        </div>
                        
                        <!-- Morse Scale -->
                        @php $morseStyling = getMorseRiskStyling($patientDetails->ds_morse ?? ''); @endphp
                        <div class="{{ $morseStyling['bg'] }} p-4 rounded-lg border {{ $morseStyling['border'] }}">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Morse (Risco de Queda):</label>
                            <p class="text-sm font-bold {{ $morseStyling['text'] }}">
                                {{ $patientDetails->ds_morse ?? 'Não avaliado' }}
                            </p>
                        </div>
                        
                        <!-- Pain Scale (Escala de Dor) -->
                        @php $painStyling = getPainRiskStyling($patientDetails->ds_dor ?? ''); @endphp
                        <div class="{{ $painStyling['bg'] }} p-4 rounded-lg border {{ $painStyling['border'] }}">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dor (Pain Scale):</label>
                            <p class="text-sm font-bold {{ $painStyling['text'] }}">
                                {{ $patientDetails->ds_dor ?? 'Não avaliado' }}
                            </p>
                        </div>
                        
                        <!-- TEV Scale -->
                        @php $tevStyling = getTevRiskStyling($patientDetails->ds_tev ?? ''); @endphp
                        <div class="{{ $tevStyling['bg'] }} p-4 rounded-lg border {{ $tevStyling['border'] }}">
                            <label class="block text-xs font-medium text-gray-600 mb-1">TEV (Tromboembolismo Venoso):</label>
                            <p class="text-sm font-bold {{ $tevStyling['text'] }}">
                                {{ $patientDetails->ds_tev ?? 'Não avaliado' }}
                            </p>
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
            
            <button 
                wire:click="showPatientDetails('{{ $currentPatient['nr_atendimento'] ?? '' }}')"
                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
            >
                Tentar novamente
            </button>
        </div>
    @endif
</div>