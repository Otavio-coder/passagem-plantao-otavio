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
            <x-healthicons-o-inpatient class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4" />
            <p class="text-gray-700 text-base sm:text-lg">Leito Vago</p>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-2">
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
                            <p class="text-sm font-semibold text-gray-800">{{ $patientDetails->pe_data ?? 'Não realizado' }}</p>
                        </div>
                        <div class="bg-gray-50 p-2 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-0.5">Hemocultura:</label>
                            <p class="text-sm font-semibold {{ ($patientDetails->hemocultura_pendente ?? false) ? 'text-purple-700' : 'text-gray-800' }}">
                                @if($patientDetails->hemocultura_pendente ?? false)
                                    Pendente
                                    @if(!empty($patientDetails->ultima_hemocultura ?? null))
                                        <span class="text-xs font-normal text-purple-500">(últ. {{ $patientDetails->ultima_hemocultura }})</span>
                                    @endif
                                @elseif(!empty($patientDetails->ultima_hemocultura ?? null))
                                    {{ $patientDetails->ultima_hemocultura }}
                                @else
                                    Não coletada
                                @endif
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
                    <x-ui.scales-display :data="$patientDetails" />
                </div>
            </div>
        </div>
    @else
        <!-- Error State -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <x-heroicon-o-exclamation-triangle class="w-12 h-12 sm:w-16 sm:h-16 text-red-500 mb-4" />
            <p class="text-gray-700 text-base sm:text-lg">Erro ao carregar detalhes do paciente</p>
            <p class="text-gray-500 mt-2 text-sm">Por favor, tente novamente</p>
        </div>
    @endif
</div>
