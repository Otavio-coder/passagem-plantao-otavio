@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null
])

<div x-show="activeTab === 'tab-s'" class="p-2 sm:p-3">
    @if($loadingPatient)
        <div class="flex flex-col items-center justify-center py-12 sm:py-20">
            <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center">
                <i class="fas fa-spinner fa-3x animate-spin"></i>
            </span>
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
        <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100">
            <h4 class="text-sm font-bold text-gray-800 border-b border-gray-200 pb-2 mb-3 flex items-center">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#007D44] text-white mr-2 text-xs font-bold flex-shrink-0">S</span>
                <div>
                    <span class="text-sm">SITUAÇÃO</span>
                    <p class="text-[10px] text-gray-500 font-normal mt-0.5">O que está acontecendo no momento?</p>
                </div>
            </h4>

            <div class="space-y-2">
                <!-- Identificação do Paciente -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-blue-500 pl-2 bg-blue-50 py-1 rounded-r">
                        Identificação do Paciente
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                        <div class="lg:col-span-2 bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Nome Completo:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->nm_pessoa_fisica ?? 'Não informado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Data de Nascimento:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->birth_date ?? 'Não informado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Idade:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                {{ $patientDetails->age_detailed ?? ($patientDetails->age ?? 'N/A') }}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Sexo:</label>
                            <p class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                                @if(($patientDetails->sexo ?? '') === 'F')
                                    <x-iconoir-female class="text-pink-600 h-5 w-5" />
                                    <span class="text-xs text-gray-500 font-normal">F</span>
                                @elseif(($patientDetails->sexo ?? '') === 'M')
                                    <x-iconoir-male class="text-blue-600 h-5 w-5" />
                                    <span class="text-xs text-gray-500 font-normal">M</span>
                                @else
                                    <span class="text-xs text-gray-500 font-normal">N/A</span>
                                @endif
                            </p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Tempo Internação:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                @if(!isset($patientDetails->internment_days) || $patientDetails->internment_days === null)
                                    <span class="text-gray-500">N/A</span>
                                @elseif($patientDetails->internment_days < 1)
                                    <span class="text-green-600 font-bold">Hoje</span>
                                @else
                                    {{ ceil($patientDetails->internment_days) }}d
                                @endif
                            </p>
                        </div>
                        <div class="lg:col-span-2 bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Médico Responsável:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->medico_responsavel ?? 'Não informado' }}</p>
                        </div>
                        <div class="lg:col-span-2 bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Convênio:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->convenio ?? 'Não informado' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Avaliações e Status Clínico -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-green-500 pl-2 bg-green-50 py-1 rounded-r">
                        Avaliações e Status Clínico
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-2">
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Medida Bloqueio:</label>
                            <p class="text-sm font-medium {{ ($patientDetails->medida_bloqueio ?? 'Não') === 'Sim' ? 'text-red-700' : 'text-green-700' }}">
                                {{ $patientDetails->medida_bloqueio ?? 'Não' }}
                            </p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Avaliação ENF.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->avaliacao_enf ?? 'Não realizada' }}</p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Plano Educ.:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->plano_educ ?? 'Não realizado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">PE:</label>
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->pe_data ?? 'Não realizado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Queda:</label>
                            <p class="text-sm font-medium {{ ($patientDetails->ds_queda ?? 'Não') !== 'Não' ? 'text-red-700' : 'text-green-700' }}">
                                {{ $patientDetails->ds_queda ?? 'Não avaliado' }}
                            </p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-1.5 mt-1.5">
                        <div class="bg-gray-50 p-2 rounded-lg border max-h-[80px] overflow-y-auto">
                            <label class="block text-[10px] font-bold text-gray-600 mb-0.5">Diag. ENF.:</label>
                            <p class="text-xs text-gray-800">
                                {{ $patientDetails->diag ?? 'Sem diagnósticos de enfermagem' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Escalas de Avaliação -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-purple-500 pl-2 bg-purple-50 py-1 rounded-r">
                        Escalas de Avaliação
                    </h5>

                    <!-- Legenda de Risco -->
                    <div class="mb-2 p-1.5 bg-gray-50 rounded-lg border">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                            <div class="flex items-center space-x-1.5">
                                <div class="w-3 h-3 bg-red-100 border border-red-300 rounded"></div>
                                <span class="text-gray-700 font-bold">Alto Risco</span>
                            </div>
                            <div class="flex items-center space-x-1.5">
                                <div class="w-3 h-3 bg-yellow-100 border border-yellow-300 rounded"></div>
                                <span class="text-gray-700 font-bold">Risco Moderado</span>
                            </div>
                            <div class="flex items-center space-x-1.5">
                                <div class="w-3 h-3 bg-gray-50 border border-gray-300 rounded"></div>
                                <span class="text-gray-700 font-bold">Baixo Risco</span>
                            </div>
                            <div class="flex items-center space-x-1.5">
                                <div class="w-3 h-3 bg-green-50 border border-green-300 rounded"></div>
                                <span class="text-gray-700 font-bold">Sem Risco</span>
                            </div>
                        </div>
                    </div>

                    @php
                        $isPediatricPatient = isset($patientDetails->age) && intval($patientDetails->age) < 18;
                    @endphp

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                        {{-- MEWS (adultos) ou PEWS (pediátricos) --}}
                        @if(!$isPediatricPatient)
                            @php
                                $score = $patientDetails->mews_score ?? null;
                                $prevScore = $patientDetails->mews_previous_score ?? null;
                                $styling = $patientDetails->mews_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                $increased = $patientDetails->mews_increased ?? false;
                                $timestamp = $patientDetails->mews_timestamp ?? null;
                                $prevTimestamp = $patientDetails->mews_previous_timestamp ?? null;
                                $classification = $patientDetails->mews_classification ?? null;
                            @endphp
                            <x-ui.scale-card
                                title="MEWS"
                                subtitle="Modified Early Warning Score"
                                :score="$score"
                                :previousScore="$prevScore"
                                :styling="$styling"
                                :increased="$increased"
                                :timestamp="$timestamp"
                                :previousTimestamp="$prevTimestamp"
                                :classification="$classification"
                            />
                        @else
                            @php
                                $score = $patientDetails->pews_score ?? null;
                                $prevScore = $patientDetails->pews_previous_score ?? null;
                                $styling = $patientDetails->pews_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                $increased = $patientDetails->pews_increased ?? false;
                                $timestamp = $patientDetails->pews_timestamp ?? null;
                                $prevTimestamp = $patientDetails->pews_previous_timestamp ?? null;
                                $classification = $patientDetails->pews_classification ?? null;
                            @endphp
                            <x-ui.scale-card
                                title="PEWS"
                                subtitle="Pediatric Early Warning Score"
                                :score="$score"
                                :previousScore="$prevScore"
                                :styling="$styling"
                                :increased="$increased"
                                :timestamp="$timestamp"
                                :previousTimestamp="$prevTimestamp"
                                :classification="$classification"
                            />
                        @endif

                        {{-- BRADEN --}}
                        @php
                            $score = $patientDetails->braden_score ?? null;
                            $prevScore = $patientDetails->braden_previous_score ?? null;
                            $styling = $patientDetails->braden_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                            $increased = $patientDetails->braden_increased ?? false;
                            $timestamp = $patientDetails->braden_timestamp ?? null;
                            $prevTimestamp = $patientDetails->braden_previous_timestamp ?? null;
                            $classification = $patientDetails->braden_classification ?? null;
                        @endphp
                        <x-ui.scale-card
                            title="Braden"
                            subtitle="Escala de Braden"
                            :score="$score"
                            :previousScore="$prevScore"
                            :styling="$styling"
                            :increased="$increased"
                            :timestamp="$timestamp"
                            :previousTimestamp="$prevTimestamp"
                            :classification="$classification"
                        />

                        {{-- MORSE --}}
                        @php
                            $score = $patientDetails->morse_score ?? null;
                            $prevScore = $patientDetails->morse_previous_score ?? null;
                            $styling = $patientDetails->morse_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                            $increased = $patientDetails->morse_increased ?? false;
                            $timestamp = $patientDetails->morse_timestamp ?? null;
                            $prevTimestamp = $patientDetails->morse_previous_timestamp ?? null;
                            $classification = $patientDetails->morse_classification ?? null;
                        @endphp
                        <x-ui.scale-card
                            title="Morse"
                            subtitle="Risco de Queda"
                            :score="$score"
                            :previousScore="$prevScore"
                            :styling="$styling"
                            :increased="$increased"
                            :timestamp="$timestamp"
                            :previousTimestamp="$prevTimestamp"
                            :classification="$classification"
                        />

                        {{-- PAIN --}}
                        @php
                            $score = $patientDetails->pain_score ?? null;
                            $prevScore = $patientDetails->pain_previous_score ?? null;
                            $styling = $patientDetails->pain_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                            $increased = $patientDetails->pain_increased ?? false;
                            $timestamp = $patientDetails->pain_timestamp ?? null;
                            $prevTimestamp = $patientDetails->pain_previous_timestamp ?? null;
                            $classification = $patientDetails->pain_classification ?? null;
                        @endphp
                        <x-ui.scale-card
                            title="Dor"
                            subtitle="Pain Scale"
                            :score="$score"
                            :previousScore="$prevScore"
                            :styling="$styling"
                            :increased="$increased"
                            :timestamp="$timestamp"
                            :previousTimestamp="$prevTimestamp"
                            :classification="$classification"
                        />

                        {{-- VTE --}}
                        @php
                            $score = $patientDetails->vte_score ?? null;
                            $prevScore = $patientDetails->vte_previous_score ?? null;
                            $styling = $patientDetails->vte_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                            $increased = $patientDetails->vte_increased ?? false;
                            $timestamp = $patientDetails->vte_timestamp ?? null;
                            $prevTimestamp = $patientDetails->vte_previous_timestamp ?? null;
                            $classification = $patientDetails->vte_classification ?? null;
                        @endphp
                        <x-ui.scale-card
                            title="TEV"
                            subtitle="Tromboembolismo Venoso"
                            :score="$score"
                            :previousScore="$prevScore"
                            :styling="$styling"
                            :increased="$increased"
                            :timestamp="$timestamp"
                            :previousTimestamp="$prevTimestamp"
                            :classification="$classification"
                        />
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
            <p class="text-gray-500 mt-2 text-sm">Por favor, tente novamente</p>
        </div>
    @endif
</div>
