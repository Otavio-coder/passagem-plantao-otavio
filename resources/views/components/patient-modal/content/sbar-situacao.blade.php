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
    @elseif($currentPatient && (is_array($currentPatient) ? (isset($currentPatient['has_patient']) && !$currentPatient['has_patient']) : (property_exists($currentPatient, 'has_patient') && !$currentPatient->has_patient)))
        <!-- Empty Bed -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-gray-700 text-base sm:text-lg">Leito Vazio</p>
            <p class="text-gray-500 mt-2 text-sm sm:text-base">Este leito não possui paciente internado no momento.</p>
        </div>
    @elseif(isset($patientDetails) && $patientDetails)
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
                            <p class="text-sm font-semibold text-gray-800">{{ isset($patientDetails->nm_pessoa_fisica) ? $patientDetails->nm_pessoa_fisica : 'Não informado' }}</p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Data de Nascimento:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                @if(isset($patientDetails->birth_date) && $patientDetails->birth_date)
                                    {{ $patientDetails->birth_date }}
                                @else
                                    Não informado
                                @endif
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Idade (Anos, Meses, Dias):</label>
                            <p class="text-sm font-semibold text-gray-800">
                                @if(isset($patientDetails->age_detailed) && $patientDetails->age_detailed)
                                    {{ $patientDetails->age_detailed }}
                                @elseif(isset($patientDetails->age) && $patientDetails->age !== null)
                                    {{ $patientDetails->age }}a
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Sexo:</label>
                            <p class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                                @if(isset($patientDetails->sexo) && $patientDetails->sexo === 'F')
                                    <span class="inline-flex items-center justify-center text-pink-600" style="font-size:1.75rem;">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <line x1="12" y1="12" x2="12" y2="20" stroke="currentColor" stroke-width="2"/>
                                            <line x1="9" y1="17" x2="15" y2="17" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs text-gray-500 font-normal">Feminino</span>
                                @elseif(isset($patientDetails->sexo) && $patientDetails->sexo === 'M')
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
                                @if(!isset($patientDetails->internment_days) || $patientDetails->internment_days === null)
                                    <span class="text-gray-500">N/A</span>
                                @elseif(is_numeric($patientDetails->internment_days) && $patientDetails->internment_days >= 0 && $patientDetails->internment_days < 1)
                                    <span class="text-green-600 font-bold">
                                        Recém-chegado (hoje)
                                    </span>
                                @else
                                    @php $days = ceil($patientDetails->internment_days); @endphp
                                    {{ $days }} dia{{ $days != 1 ? 's' : '' }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <!-- Médico Responsável e Convênio lado a lado -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Médico Responsável:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ isset($patientDetails->medico_responsavel) ? $patientDetails->medico_responsavel : 'Não informado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Convênio:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ isset($patientDetails->convenio) ? $patientDetails->convenio : 'Não informado' }}</p>
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
                            <p class="text-sm font-bold {{ (isset($patientDetails->medida_bloqueio) && $patientDetails->medida_bloqueio === 'Sim') ? 'text-red-700' : 'text-green-700' }}">
                                {{ isset($patientDetails->medida_bloqueio) ? $patientDetails->medida_bloqueio : 'Não informado' }}
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Avaliação ENF.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ isset($patientDetails->avaliacao_enf) ? $patientDetails->avaliacao_enf : 'Não realizada' }}</p>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Plano Educ.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ isset($patientDetails->plano_educ) ? $patientDetails->plano_educ : 'Não realizado' }}</p>
                        </div>
                    </div>
                    <!-- PE, Diag. ENF. e Queda lado a lado -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">PE:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ isset($patientDetails->pe_data) ? $patientDetails->pe_data : 'Não realizado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Diag. ENF.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ isset($patientDetails->diag) ? $patientDetails->diag : 'Não informado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Queda:</label>
                            <p class="text-sm font-bold {{ (isset($patientDetails->ds_queda) && $patientDetails->ds_queda !== 'Não') ? 'text-red-700' : 'text-green-700' }}">
                                {{ isset($patientDetails->ds_queda) ? $patientDetails->ds_queda : 'Não avaliado' }}
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
                            $isPediatricPatient = isset($patientDetails->age) && intval($patientDetails->age) < 16;
                            $isAdultPatient = isset($patientDetails->age) && intval($patientDetails->age) >= 16;
                        @endphp
                        
                        <!-- MEWS Scale (para pacientes adultos) -->
                        @if(!$isPediatricPatient)
                            @php
                                $mewsStyling = function_exists('getMewsRiskStyling') ? getMewsRiskStyling($patientDetails->ds_mews ?? '') : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
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
                            @php
                                $pewsStyling = function_exists('getPewsRiskStyling') ? getPewsRiskStyling($patientDetails->ds_pews ?? '') : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
                            @endphp
                            <div class="{{ $pewsStyling['bg'] }} p-4 rounded-lg border {{ $pewsStyling['border'] }}">
                                <label class="block text-xs font-medium text-gray-600 mb-1">PEWS (Pediatric Early Warning Score):</label>
                                <p class="text-sm font-bold {{ $pewsStyling['text'] }}">
                                    {{ $patientDetails->ds_pews ?? 'Não avaliado' }}
                                </p>
                            </div>
                        @endif
                        
                        <!-- Braden Scale -->
                        @php
                            $bradenStyling = function_exists('getBradenRiskStyling') ? getBradenRiskStyling($patientDetails->ds_braden ?? '') : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
                        @endphp
                        <div class="{{ $bradenStyling['bg'] }} p-4 rounded-lg border {{ $bradenStyling['border'] }}">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Braden (Escala de Braden):</label>
                            <p class="text-sm font-bold {{ $bradenStyling['text'] }}">
                                {{ $patientDetails->ds_braden ?? 'Não avaliado' }}
                            </p>
                        </div>
                        
                        <!-- Morse Scale -->
                        @php
                            $morseStyling = function_exists('getMorseRiskStyling') ? getMorseRiskStyling($patientDetails->ds_morse ?? '') : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
                        @endphp
                        <div class="{{ $morseStyling['bg'] }} p-4 rounded-lg border {{ $morseStyling['border'] }}">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Morse (Risco de Queda):</label>
                            <p class="text-sm font-bold {{ $morseStyling['text'] }}">
                                {{ $patientDetails->ds_morse ?? 'Não avaliado' }}
                            </p>
                        </div>
                        
                        <!-- Pain Scale (Escala de Dor) -->
                        @php
                            $painStyling = function_exists('getPainRiskStyling') ? getPainRiskStyling($patientDetails->ds_dor ?? '') : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
                        @endphp
                        <div class="{{ $painStyling['bg'] }} p-4 rounded-lg border {{ $painStyling['border'] }}">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Dor (Pain Scale):</label>
                            <p class="text-sm font-bold {{ $painStyling['text'] }}">
                                {{ $patientDetails->ds_dor ?? 'Não avaliado' }}
                            </p>
                        </div>
                        
                        <!-- TEV Scale -->
                        @php
                            $tevStyling = function_exists('getTevRiskStyling') ? getTevRiskStyling($patientDetails->ds_tev ?? '') : ['bg'=>'bg-gray-50','border'=>'border-gray-200','text'=>'text-gray-800'];
                        @endphp
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