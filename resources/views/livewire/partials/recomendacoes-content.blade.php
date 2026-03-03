{{-- resources/views/livewire/partials/recomendacoes-content.blade.php --}}

@php
    $hasProcedimentos = isset($patientDetails->procedimentos) &&
                        is_array($patientDetails->procedimentos) &&
                        isset($patientDetails->procedimentos['total_count']);

    $hasMedicamentos  = isset($patientDetails->medicamentos) &&
                        is_array($patientDetails->medicamentos) &&
                        isset($patientDetails->medicamentos['total_count']);

    $hasNutricao      = isset($patientDetails->nutricao) &&
                        is_array($patientDetails->nutricao) &&
                        isset($patientDetails->nutricao['total_count']);

    $hasRecomendacoes = isset($patientDetails->recomendacoes) &&
                        is_array($patientDetails->recomendacoes) &&
                        isset($patientDetails->recomendacoes['total_count']) &&
                        isset($patientDetails->recomendacoes['items']);

    $hasIntervencoes  = isset($patientDetails->intervencoes) &&
                        is_array($patientDetails->intervencoes) &&
                        isset($patientDetails->intervencoes['total_count']) &&
                        isset($patientDetails->intervencoes['items']);
@endphp

<!-- Abas de Recomendações -->
<div class="border-b border-gray-200 mb-4">
    <nav class="flex space-x-1 overflow-x-auto pb-2 scrollbar-hide">
        <!-- Procedimentos e Exames -->
        <button @click.prevent="activeRecomendacaoTab = 'tab-proc'"
                :class="activeRecomendacaoTab === 'tab-proc' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>Exames e Procedimentos</span>
                @if($hasProcedimentos && $patientDetails->procedimentos['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->procedimentos['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Medicamentos -->
        <button @click.prevent="activeRecomendacaoTab = 'tab-med'"
                :class="activeRecomendacaoTab === 'tab-med' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <span>Medicamentos</span>
                @if($hasMedicamentos && $patientDetails->medicamentos['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->medicamentos['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Nutrição -->
        <button @click.prevent="activeRecomendacaoTab = 'tab-nut'"
                :class="activeRecomendacaoTab === 'tab-nut' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                </svg>
                <span>Nutrição</span>
                @if($hasNutricao && $patientDetails->nutricao['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->nutricao['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Recomendações -->
        <button @click.prevent="activeRecomendacaoTab = 'tab-rec'"
                :class="activeRecomendacaoTab === 'tab-rec' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Recomendações</span>
                @if($hasRecomendacoes && $patientDetails->recomendacoes['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->recomendacoes['total_count'] }}
                    </span>
                @endif
            </div>
        </button>

        <!-- Intervenções -->
        <button @click.prevent="activeRecomendacaoTab = 'tab-int'"
                :class="activeRecomendacaoTab === 'tab-int' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-1.5">
                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Intervenções</span>
                @if($hasIntervencoes && $patientDetails->intervencoes['total_count'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-500 text-white rounded-full">
                        {{ $patientDetails->intervencoes['total_count'] }}
                    </span>
                @endif
            </div>
        </button>
    </nav>
</div>

<div class="min-h-[400px]">

    {{-- ==================== PROCEDIMENTOS E EXAMES ==================== --}}
    <div x-show="activeRecomendacaoTab === 'tab-proc'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">

        @if($hasProcedimentos && $patientDetails->procedimentos['total_count'] > 0)
            @php $procedimentos = $patientDetails->procedimentos['items'] ?? []; @endphp
            <div class="text-sm text-gray-600 mb-3 flex items-center justify-between">
                <span>{{ $patientDetails->procedimentos['total_count'] }} procedimento(s) / exame(s)</span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ date('d/m/Y') }}</span>
            </div>
            <div class="space-y-2">
                @php $mostrandoRealizados = false; @endphp
                @foreach($procedimentos as $proc)
                    @php
                        $jaRealizado = $proc['realizado'] ?? false;
                        $exibirSepProc = !$mostrandoRealizados && $jaRealizado;
                        if ($jaRealizado) $mostrandoRealizados = true;
                    @endphp
                    @if($exibirSepProc)
                        <div class="flex items-center gap-2 py-1">
                            <div class="flex-1 border-t border-green-200"></div>
                            <span class="text-xs font-medium text-green-500 px-2">Realizados</span>
                            <div class="flex-1 border-t border-green-200"></div>
                        </div>
                    @endif
                    @php
                        $origemLabel = $proc['origem'] === 'agendamento' ? 'Agendamento' : 'Prescrição';
                        $origemColor = $proc['origem'] === 'agendamento'
                            ? 'bg-purple-100 text-purple-800 border-purple-200'
                            : 'bg-blue-100 text-blue-800 border-blue-200';
                        $statusColor = $proc['realizado']
                            ? 'bg-green-100 text-green-700 border-green-200'
                            : 'bg-amber-100 text-amber-700 border-amber-200';
                        $statusDot   = $proc['realizado'] ? 'bg-green-500' : 'bg-amber-500';
                    @endphp
                    <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium text-gray-800 leading-snug break-words mb-1.5">
                                    {{ $proc['descricao'] ?? 'Procedimento não identificado' }}
                                </div>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    @if($proc['dt_prevista'] ?? null)
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $proc['dt_prevista'] }}</span>
                                    @endif
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium border {{ $origemColor }}">
                                        {{ $origemLabel }}
                                    </span>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {{ $statusColor }} shrink-0">
                                <span class="w-1.5 h-1.5 {{ $statusDot }} rounded-full mr-1"></span>
                                {{ $proc['status'] ?? 'Pendente' }}
                            </span>
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

    {{-- ==================== MEDICAMENTOS ==================== --}}
    {{--
        Lista plana: uma linha por ordem médica.
        Sem repetição por horário — cada ordem aparece uma única vez.
        Forma de administração: H=Conforme Horário | N=Se Necessário | A=A Critério Médico
        Status: ativo (verde) | administrado (azul) | suspenso (vermelho)
    --}}
    <div x-show="activeRecomendacaoTab === 'tab-med'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">

        @if($hasMedicamentos && $patientDetails->medicamentos['total_count'] > 0)
            @php $medicamentos = $patientDetails->medicamentos['items'] ?? []; @endphp

            <div class="text-sm text-gray-600 mb-3 flex items-center justify-between">
                <span>{{ $patientDetails->medicamentos['total_count'] }} medicamento(s) prescrito(s)</span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ date('d/m/Y') }}</span>
            </div>

            <div class="space-y-2" x-data="{ expandedMed: null }">
                @php
                    $mostrandoAdministrados = false;
                    $mostrandoSuspensos = false;
                @endphp
                @foreach($medicamentos as $index => $med)
                    @php
                        $status = $med['status'] ?? 'ativo';
                        $exibirSepAdm  = !$mostrandoAdministrados && !$mostrandoSuspensos && $status === 'administrado';
                        $exibirSepSusp = !$mostrandoSuspensos && $status === 'suspenso';
                        if ($status === 'administrado') $mostrandoAdministrados = true;
                        if ($status === 'suspenso') $mostrandoSuspensos = true;
                    @endphp
                    @if($exibirSepAdm)
                        <div class="flex items-center gap-2 py-1">
                            <div class="flex-1 border-t border-blue-200"></div>
                            <span class="text-xs font-medium text-blue-400 px-2">Administrados</span>
                            <div class="flex-1 border-t border-blue-200"></div>
                        </div>
                    @endif
                    @if($exibirSepSusp)
                        <div class="flex items-center gap-2 py-1">
                            <div class="flex-1 border-t border-red-200"></div>
                            <span class="text-xs font-medium text-red-400 px-2">Suspensos</span>
                            <div class="flex-1 border-t border-red-200"></div>
                        </div>
                    @endif
                    @php
                        $statusColors = match($status) {
                            'ativo'        => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'border' => 'border-green-200', 'dot' => 'bg-green-500', 'label' => 'Ativo'],
                            'administrado' => ['bg' => 'bg-blue-100',  'text' => 'text-blue-700',  'border' => 'border-blue-200',  'dot' => 'bg-blue-500',  'label' => 'Administrado'],
                            'suspenso'     => ['bg' => 'bg-red-100',   'text' => 'text-red-700',   'border' => 'border-red-200',   'dot' => 'bg-red-500',   'label' => 'Suspenso'],
                            default        => ['bg' => 'bg-gray-100',  'text' => 'text-gray-700',  'border' => 'border-gray-200',  'dot' => 'bg-gray-500',  'label' => ucfirst($status)],
                        };
                        $totalDoses    = $med['total_doses_hoje'] ?? 0;
                        $dosesChecadas = $med['doses_checadas'] ?? 0;
                        $adminColors = match($med['ie_administracao'] ?? '') {
                            'P' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                            'H' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                            'N' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                            'A' => 'bg-orange-100 text-orange-800 border-orange-200',
                            default => 'bg-gray-100 text-gray-700 border-gray-200',
                        };
                        $cardBg = $status === 'suspenso' ? 'bg-red-50/40' : ($status === 'administrado' ? 'bg-gray-50' : 'bg-white');
                    @endphp

                    <div class="rounded-lg border border-gray-200 p-3 shadow-sm transition-colors {{ $cardBg }} {{ ($med['has_details'] ?? false) ? 'cursor-pointer hover:bg-gray-50' : '' }}"
                         @if($med['has_details'] ?? false)
                             @click="expandedMed = expandedMed === {{ $index }} ? null : {{ $index }}"
                         @endif>

                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                {{-- Nome do medicamento --}}
                                <div class="text-xs font-semibold text-gray-800 leading-snug break-words mb-1.5">
                                    {{ $med['nome'] ?? 'Medicamento não identificado' }}
                                </div>

                                {{-- Dose + Via + Horários --}}
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600 mb-1.5">
                                    @if($med['dose'] ?? null)
                                        <span class="font-semibold text-gray-800">{{ $med['dose'] }}</span>
                                    @endif
                                    @if($med['via_aplicacao'] ?? null)
                                        <span class="text-gray-500">• {{ $med['via_aplicacao'] }}</span>
                                    @endif
                                    @if($med['horarios'] ?? null)
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $med['horarios'] }}</span>
                                    @endif
                                </div>

                                {{-- Validade + Forma de administração + Doses hoje --}}
                                <div class="flex flex-wrap items-center gap-1 text-xs">
                                    @if($med['dt_inicio'] ?? null)
                                        <span class="text-gray-500">
                                            {{ $med['dt_inicio'] }}
                                            @if($med['dt_fim'] ?? null) – {{ $med['dt_fim'] }} @endif
                                        </span>
                                    @endif
                                    @if($med['ie_administracao'] ?? null)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium border {{ $adminColors }}">
                                            {{ $med['administracao'] }}
                                        </span>
                                    @endif
                                    @if($totalDoses > 0)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium border
                                            {{ $dosesChecadas >= $totalDoses
                                                ? 'bg-blue-50 text-blue-700 border-blue-200'
                                                : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                                            {{ $dosesChecadas }}/{{ $totalDoses }} doses hoje
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Status badge + chevron --}}
                            <div class="flex flex-col items-end space-y-1 ml-1 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColors['bg'] }} {{ $statusColors['text'] }} border {{ $statusColors['border'] }}">
                                    <span class="w-1.5 h-1.5 {{ $statusColors['dot'] }} rounded-full mr-1"></span>
                                    {{ $statusColors['label'] }}
                                </span>
                                @if($med['has_details'] ?? false)
                                    <svg class="w-3 h-3 text-gray-400 transform transition-transform"
                                         :class="expandedMed === {{ $index }} ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>

                        {{-- Painel expandido: justificativa / observação / prescritor --}}
                        @if($med['has_details'] ?? false)
                            <div x-show="expandedMed === {{ $index }}"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 max-h-0"
                                 x-transition:enter-end="opacity-100 max-h-96"
                                 x-transition:leave="transition ease-in duration-150"
                                 x-transition:leave-start="opacity-100 max-h-96"
                                 x-transition:leave-end="opacity-0 max-h-0"
                                 class="overflow-hidden mt-3 pt-3 border-t border-gray-200 space-y-2">
                                @if($med['justificativa'] ?? null)
                                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                        <div class="font-semibold text-blue-800 text-xs mb-0.5">Justificativa de Uso</div>
                                        <div class="text-xs text-blue-700 leading-snug">{{ $med['justificativa'] }}</div>
                                    </div>
                                @endif
                                @if($med['observacao'] ?? null)
                                    <div class="bg-amber-50 p-2 rounded border border-amber-200">
                                        <div class="font-semibold text-amber-800 text-xs mb-0.5">Observação</div>
                                        <div class="text-xs text-amber-700 leading-snug">{{ $med['observacao'] }}</div>
                                    </div>
                                @endif
                                @if($med['nome_prescritor'] ?? null)
                                    <div class="text-xs text-gray-500 pt-1">
                                        <span class="font-medium">Prescritor:</span> {{ $med['nome_prescritor'] }}
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <p class="text-gray-600 text-sm font-medium">Nenhum medicamento prescrito</p>
                <p class="text-gray-500 text-xs">para o dia {{ date('d/m/Y') }}</p>
            </div>
        @endif
    </div>

    {{-- ==================== NUTRIÇÃO ==================== --}}
    <div x-show="activeRecomendacaoTab === 'tab-nut'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">

        @if($hasNutricao && $patientDetails->nutricao['total_count'] > 0)
            <div class="text-sm text-gray-600 mb-3 flex items-center justify-between">
                <span>{{ $patientDetails->nutricao['total_count'] }} prescrição(ões) nutricional(is)</span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ date('d/m/Y') }}</span>
            </div>
            <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3" x-data="{ selectedNutrition: null }">
                @foreach(['MANHÃ', 'TARDE', 'NOITE'] as $turno)
                    @php
                        $turnoData = $patientDetails->nutricao['shifts'][$turno] ?? ['count' => 0, 'prescriptions' => []];
                    @endphp
                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide">{{ $turno }}</h6>
                                <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                    {{ $turnoData['count'] }}
                                </span>
                            </div>
                        </div>
                        <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                            @if($turnoData['count'] > 0)
                                <div class="space-y-2">
                                    @foreach($turnoData['prescriptions'] as $index => $nut)
                                        <div class="bg-gray-50 rounded-lg border p-3 {{ ($nut['has_details'] ?? false) ? 'cursor-pointer hover:bg-gray-100' : '' }} transition-colors shadow-sm"
                                             @if($nut['has_details'] ?? false)
                                                 @click="selectedNutrition = selectedNutrition === '{{ $turno }}_{{ $index }}' ? null : '{{ $turno }}_{{ $index }}'"
                                             @endif>
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words flex flex-wrap items-center gap-1">
                                                        {{ $nut['prescricao'] ?? 'Prescrição nutricional' }}
                                                        @if($nut['is_jejum'] ?? false)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">JEJUM</span>
                                                        @endif
                                                        @if($nut['is_enteral'] ?? false)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">Enteral</span>
                                                        @endif
                                                        @if($nut['is_especial'] ?? false)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">Especial</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600">
                                                        @if($nut['horarios'] ?? null)
                                                            <span class="font-mono bg-white px-1.5 py-0.5 rounded border">{{ $nut['horarios'] }}</span>
                                                        @endif
                                                        @if($nut['tipo_nutricao'] ?? null)
                                                            <span class="font-medium">{{ $nut['tipo_nutricao'] }}</span>
                                                        @endif
                                                        @if($nut['volume'] ?? null)
                                                            <span>{{ $nut['volume'] }}ml</span>
                                                        @endif
                                                        @if($nut['kcal_total'] ?? null)
                                                            <span>{{ $nut['kcal_total'] }}kcal</span>
                                                        @endif
                                                    </div>
                                                    @if($nut['alergias_alimentares'] ?? null)
                                                        <div class="mt-1">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                                                Alergias: {{ $nut['alergias_alimentares'] }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if($nut['has_details'] ?? false)
                                                    <svg class="w-3 h-3 text-gray-400 flex-shrink-0 ml-2 mt-0.5 transform transition-transform"
                                                         :class="selectedNutrition === '{{ $turno }}_{{ $index }}' ? 'rotate-180' : ''"
                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                @endif
                                            </div>

                                            @if($nut['has_details'] ?? false)
                                                <div x-show="selectedNutrition === '{{ $turno }}_{{ $index }}'"
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 max-h-0"
                                                     x-transition:enter-end="opacity-100 max-h-96"
                                                     x-transition:leave="transition ease-in duration-150"
                                                     x-transition:leave-start="opacity-100 max-h-96"
                                                     x-transition:leave-end="opacity-0 max-h-0"
                                                     class="overflow-hidden mt-3 pt-3 border-t border-gray-200 space-y-2 text-xs">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        @if($nut['dt_inicio'] ?? null)
                                                            <div><span class="font-medium text-gray-600">Início:</span> {{ $nut['dt_inicio'] }}</div>
                                                        @endif
                                                        @if($nut['dt_fim'] ?? null)
                                                            <div><span class="font-medium text-gray-600">Validade:</span> {{ $nut['dt_fim'] }}</div>
                                                        @endif
                                                        @if($nut['volume_total'] ?? null)
                                                            <div><span class="font-medium text-gray-600">Vol. Total:</span> {{ $nut['volume_total'] }}ml</div>
                                                        @endif
                                                        @if($nut['nome_nutricionista'] ?? null)
                                                            <div class="col-span-2"><span class="font-medium text-gray-600">Nutricionista:</span> {{ $nut['nome_nutricionista'] }}</div>
                                                        @endif
                                                    </div>
                                                    @if(($nut['is_jejum'] ?? false) && (($nut['tipo_jejum'] ?? null) || ($nut['objetivo_jejum'] ?? null)))
                                                        <div class="bg-orange-50 p-2 rounded border border-orange-200">
                                                            <div class="font-medium text-orange-800 mb-0.5">Jejum</div>
                                                            @if($nut['tipo_jejum'] ?? null)<div>Tipo: {{ $nut['tipo_jejum'] }}</div>@endif
                                                            @if($nut['objetivo_jejum'] ?? null)<div>Objetivo: {{ $nut['objetivo_jejum'] }}</div>@endif
                                                        </div>
                                                    @endif
                                                    @if($nut['observacoes'] ?? null)
                                                        <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                            <div class="font-medium text-blue-800 mb-0.5">Observações</div>
                                                            <div class="text-blue-700">{{ $nut['observacoes'] }}</div>
                                                        </div>
                                                    @endif
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
                                    <div class="text-gray-500 text-sm">Sem prescrição nutricional</div>
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

    {{-- ==================== RECOMENDAÇÕES ==================== --}}
    {{--
        Orientações e informações do médico — não são prescrições diretas.
        Podem conter periodicidade e observações sobre medicamentos.
    --}}
    <div x-show="activeRecomendacaoTab === 'tab-rec'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">

        @if($hasRecomendacoes && $patientDetails->recomendacoes['total_count'] > 0)
            @php $recomendacoes = $patientDetails->recomendacoes['items'] ?? []; @endphp
            <div class="text-sm text-gray-600 mb-3 flex items-center justify-between">
                <span>{{ $patientDetails->recomendacoes['total_count'] }} recomendação(ões) ativa(s)</span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ date('d/m/Y') }}</span>
            </div>
            <div class="space-y-2" x-data="{ expandedRec: null }">
                @foreach($recomendacoes as $index => $rec)
                    <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm {{ ($rec['has_details'] ?? false) ? 'cursor-pointer hover:bg-gray-50' : '' }} transition-colors"
                         @if($rec['has_details'] ?? false)
                             @click="expandedRec = expandedRec === {{ $index }} ? null : {{ $index }}"
                         @endif>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium text-gray-800 leading-snug break-words mb-1">
                                    @if($rec['tipo_recomendacao'] ?? null)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 border border-indigo-200 mr-1.5 mb-0.5">
                                            {{ $rec['tipo_recomendacao'] }}
                                        </span>
                                    @endif
                                    {{ Str::limit($rec['recomendacao'], 150) }}
                                </div>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    @if($rec['horarios'] ?? null)
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $rec['horarios'] }}</span>
                                    @elseif($rec['dt_inicio'] ?? null)
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">desde {{ $rec['dt_inicio'] }}</span>
                                    @endif
                                    @if($rec['dt_fim'] ?? null)
                                        <span class="text-amber-600 font-medium">até {{ $rec['dt_fim'] }}</span>
                                    @endif
                                    @if($rec['nome_profissional'] ?? null)
                                        <span class="text-gray-400">{{ Str::limit($rec['nome_profissional'], 30) }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($rec['has_details'] ?? false)
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-0.5 transform transition-transform"
                                     :class="expandedRec === {{ $index }} ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            @endif
                        </div>
                        @if($rec['has_details'] ?? false)
                            <div x-show="expandedRec === {{ $index }}"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 pt-3 border-t border-gray-200 space-y-2">
                                <div class="bg-indigo-50 p-2 rounded border border-indigo-200">
                                    <div class="font-medium text-indigo-800 text-xs mb-1">Recomendação completa</div>
                                    <div class="text-xs text-indigo-700 break-words leading-relaxed">{{ $rec['recomendacao'] }}</div>
                                </div>
                                @if($rec['observacoes'] ?? null)
                                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                        <div class="font-medium text-blue-800 text-xs mb-1">Observações</div>
                                        <div class="text-xs text-blue-700 break-words leading-relaxed">{{ $rec['observacoes'] }}</div>
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

    {{-- ==================== INTERVENÇÕES ==================== --}}
    {{--
        Cuidados e procedimentos de suporte de enfermagem — listagem paginada.
        Não são prescrições de medicamentos.
    --}}
    <div x-show="activeRecomendacaoTab === 'tab-int'"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         style="display: none;">

        @if($hasIntervencoes && $patientDetails->intervencoes['total_count'] > 0)
            @php $intervencoes = $patientDetails->intervencoes['items'] ?? []; @endphp
            <div class="text-sm text-gray-600 mb-3 flex items-center justify-between">
                <span>{{ $patientDetails->intervencoes['total_count'] }} intervenção(ões) ativa(s)</span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded">{{ date('d/m/Y') }}</span>
            </div>
            <div class="space-y-2" x-data="{ expandedInt: null }">
                @foreach($intervencoes as $index => $int)
                    <div class="bg-white rounded-lg border border-gray-200 p-3 shadow-sm {{ ($int['has_details'] ?? false) ? 'cursor-pointer hover:bg-gray-50' : '' }} transition-colors"
                         @if($int['has_details'] ?? false)
                             @click="expandedInt = expandedInt === {{ $index }} ? null : {{ $index }}"
                         @endif>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium text-gray-800 leading-snug break-words mb-1">
                                    {{ $int['procedimento'] ?? 'Procedimento não especificado' }}
                                </div>
                                @if(!empty($int['labels'] ?? []))
                                    <div class="flex flex-wrap gap-1 mb-1">
                                        @foreach($int['labels'] as $label)
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium border
                                                {{ str_contains(strtolower($label), 'urgente') ? 'bg-red-100 text-red-800 border-red-200' :
                                                   (str_contains(strtolower($label), 'lado') ? 'bg-blue-100 text-blue-800 border-blue-200' :
                                                    'bg-gray-100 text-gray-800 border-gray-200') }}">
                                                {{ $label }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                    @if($int['horarios'] ?? null)
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ $int['horarios'] }}</span>
                                    @elseif($int['dt_inicio'] ?? null)
                                        <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">desde {{ $int['dt_inicio'] }}</span>
                                    @endif
                                    @if($int['dt_fim'] ?? null)
                                        <span class="text-amber-600 font-medium">até {{ $int['dt_fim'] }}</span>
                                    @endif
                                    @if($int['nome_profissional'] ?? null)
                                        <span class="text-gray-400">{{ Str::limit($int['nome_profissional'], 30) }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($int['has_details'] ?? false)
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0 mt-0.5 transform transition-transform"
                                     :class="expandedInt === {{ $index }} ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            @endif
                        </div>
                        @if($int['has_details'] ?? false)
                            <div x-show="expandedInt === {{ $index }}"
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="mt-3 pt-3 border-t border-gray-200 space-y-2">
                                @if($int['observacoes'] ?? null)
                                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                        <div class="font-medium text-blue-800 text-xs mb-1">Observações</div>
                                        <div class="text-xs text-blue-700 break-words leading-relaxed">{{ $int['observacoes'] }}</div>
                                    </div>
                                @endif
                                @if($int['nome_prescritor'] ?? null)
                                    <div class="text-xs text-gray-500 pt-1">
                                        <span class="font-medium">Prescritor:</span> {{ $int['nome_prescritor'] }}
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
    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    [x-show][style*="display: none"] { display: none !important; pointer-events: none !important; }
</style>
