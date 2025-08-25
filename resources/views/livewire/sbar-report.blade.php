{{-- resources/views/livewire/sbar-report.blade.php --}}
<div class="w-full px-3 my-2 text-[#004D9D] relative">
    <div class="py-12">
        <div class="max-w-screen-2xl mx-auto px-2 sm:px-4 lg:px-8 xl:px-12 2xl:px-16">
            <!-- Mantém Alpine dashboard -->
            <div
                class="relative"
                x-data="sbarDashboard()"
                x-init="init()"
                @modal-opened.window="modalOpen = true; document.body.classList.add('modal-open')"
                @modal-closed.window="modalOpen = false; document.body.classList.remove('modal-open')"
                @auto-refreshed.window="handleAutoRefresh()"
            >

                {{-- Spinner centralizado e acima de tudo --}}
                <div
                    wire:loading.delay.longer
                    class="fixed inset-0 z-[9998] flex items-center justify-center bg-[#004D9D]/20"
                    role="status"
                    aria-live="polite"
                >
                    <div class="flex flex-col items-center justify-center space-y-2 min-h-screen">
                        <div class="w-12 h-12 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin" aria-hidden="true"></div>
                        <span class="text-[#004D9D] font-medium">{{ $loadingMessage ?? 'Carregando...' }}</span>
                    </div>
                </div>

                <div class="font-montserrat relative">
                    <!-- Sistema de Aviso -->
                    <div x-data="{ showAlert: true }"
                         x-show="showAlert"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 transform -translate-y-2"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 transform translate-y-0"
                         x-transition:leave-end="opacity-0 transform -translate-y-2"
                         class="mb-4 sm:mb-6 bg-blue-50 border-l-4 border-blue-400 p-3 sm:p-4 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-start">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3 flex-1 sm:mr-8">
                                    <div class="text-xs sm:text-sm text-blue-800 leading-relaxed">
                                        <p class="mb-2 sm:mb-3">
                                            <strong>Sistema SBAR - Passagem de Plantão:</strong> Este sistema é um <strong>visualizador de informações</strong>
                                            que apresenta dados dos pacientes de forma otimizada. <strong>Não substitui o Tasy</strong> e <strong>não realiza
                                            inserções diretas de dados</strong> no sistema ERP hospitalar.
                                        </p>
                                        <p class="text-xs sm:text-sm">
                                            <strong>Objetivo:</strong> Organizar informações por hospital, setor e leito, digitalizar dados tradicionalmente registrados
                                            no SBAR em papel e oferecer interface para avaliações de enfermagem por turno, reduzindo o uso de papel.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0 mt-3 sm:mt-0 self-start sm:ml-4">
                                <button @click="showAlert = false"
                                        class="text-blue-400 hover:text-blue-600 transition-colors p-1">
                                    <svg class="h-4 w-4 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Conteúdo principal -->
                    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden">
                        <div class="bg-[#004D9D]/90 px-3 sm:px-4 lg:px-8 py-2 sm:py-3 lg:py-4 top-0 z-50 shadow-lg">
                            <div class="flex flex-col space-y-2 lg:space-y-3">
                                <div class="flex items-center justify-center space-x-3">
                                    <h1 class="text-sm sm:text-base lg:text-2xl font-bold text-white text-center">Sistema SBAR - Passagem de Plantão</h1>

                                    <div class="flex items-center space-x-2">
                                        @if($lastRefresh)
                                            <span class="text-white/80 text-xs">
                                                Última atualização: {{ $lastRefresh }}
                                            </span>
                                        @endif

                                        <div x-show="autoRefreshEnabled"
                                                class="flex items-center space-x-1 bg-green-500/20 px-2 py-1 rounded-full">
                                            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                            <span class="text-green-200 text-xs font-medium">Auto-refresh</span>
                                            <span x-text="nextRefreshIn + 's'" class="text-green-200 text-xs"></span>
                                        </div>

                                        <button @click="toggleAutoRefresh()"
                                                class="text-white/80 hover:text-white text-xs underline"
                                                x-text="autoRefreshEnabled ? 'Desabilitar' : 'Habilitar'"
                                                title="Toggle auto-refresh a cada 10 minutos">
                                        </button>

                                        <button
                                            @click="toggleDashboardMode()"
                                            class="hidden lg:inline-flex items-center px-2 py-1 rounded font-semibold transition
                                                text-xs
                                                focus:outline-none
                                                "
                                            :class="isDashboardMode
                                                ? 'bg-red-600 text-white hover:bg-red-700'
                                                : 'bg-[#0071B9] text-white hover:bg-[#004D9D]'"
                                            x-text="isDashboardMode ? 'Sair do Dashboard' : 'Dashboard'"
                                            title="Ativar/Desativar modo Dashboard (Auto-scroll automático)"
                                            type="button"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                            </svg>
                                            <span x-text="isDashboardMode ? 'Sair do Dashboard' : 'Dashboard'"></span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Filtros e botões (refatorado para responsividade desktop / telas grandes) --}}
                                <div class="w-full">
                                    <!-- Container que organiza filtros (expansível) e ações (fixas à direita em telas maiores) -->
                                    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3 lg:gap-4">

                                        <!-- Filtros: ocupam todo o espaço disponível; usa grid interno para alinhar -->
                                        <div class="flex-1 min-w-0">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2 xl:gap-4 items-end">
                                                {{-- Hospital selector --}}
                                                <div class="min-w-0">
                                                    <label class="block text-white text-xs mb-1 font-medium">Hospital:</label>
                                                    <select
                                                        wire:model="selectedHospital"
                                                        wire:change.debounce.200ms="changeHospital($event.target.value)"
                                                        class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-2 text-xs w-full min-w-0 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                                                    >
                                                        @foreach($hospitals as $hospital)
                                                            <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                {{-- Setor --}}
                                                <div class="min-w-0">
                                                    <label class="block text-white text-xs mb-1 font-medium">Setor:</label>
                                                    <select
                                                        wire:model="selectedSector"
                                                        wire:change.debounce.200ms="changeSelector($event.target.value)"
                                                        class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-2 text-xs w-full min-w-0 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                                                    >
                                                        @foreach($sectors as $sector)
                                                            <option value="{{ $sector['cd_setor_atendimento'] }}" class="text-gray-800 bg-white hover:bg-blue-50">
                                                                {{ $sector['ds_setor_atendimento'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                {{-- Criticidade (MEWS) --}}
                                                <div class="min-w-0">
                                                    <label class="block text-white text-xs mb-1 font-medium">Criticidade (MEWS):</label>
                                                    <select
                                                        wire:model="mewsFilter"
                                                        wire:change.debounce.150ms="applyMewsFilter($event.target.value)"
                                                        class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-2 text-xs w-full min-w-0 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                                                    >
                                                        <option value="all" class="text-gray-800 bg-white hover:bg-blue-50">Todos os níveis</option>
                                                        <option value="critical" class="text-red-700 bg-red-50 hover:bg-red-100">CRÍTICOS (≥5)</option>
                                                        <option value="warning" class="text-amber-700 bg-amber-50 hover:bg-amber-100">ALERTA (≥3)</option>
                                                        <option value="normal" class="text-green-700 bg-green-50 hover:bg-green-100">NORMAIS (&lt;3)</option>
                                                    </select>
                                                </div>

                                                {{-- Cirurgias --}}
                                                <div class="min-w-0">
                                                    <label class="block text-white text-xs mb-1 font-medium">Cirurgias:</label>
                                                    <select
                                                        wire:model="surgicalFilter"
                                                        wire:change.debounce.150ms="applySurgicalFilter($event.target.value)"
                                                        class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-2 text-xs w-full min-w-0 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                                                    >
                                                        <option value="all" class="text-gray-800 bg-white hover:bg-blue-50">Todos os pacientes</option>
                                                        <option value="with_surgery" class="text-purple-700 bg-purple-50 hover:bg-purple-100">COM CIRURGIAS</option>
                                                    </select>
                                                </div>

                                                {{-- Ordenar por (select + direction) - ocupa uma célula inteira --}}
                                                <div class="min-w-0">
                                                    <label class="block text-white text-xs mb-1 font-medium">Ordenar por:</label>
                                                    <div class="flex w-full min-w-0">
                                                        <select
                                                            wire:model="orderBy"
                                                            wire:change.debounce.150ms="applyOrderBy($event.target.value)"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-l py-0.5 px-2 text-xs flex-1 min-w-0 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                                                        >
                                                            <option value="leito">Número do Leito</option>
                                                            <option value="mews">Score MEWS</option>
                                                            <option value="name">Nome do Paciente</option>
                                                            <option value="prontuario">Número do Prontuário</option>
                                                            <option value="internment">Tempo de Internação</option>
                                                            <option value="age">Idade do Paciente</option>
                                                        </select>

                                                        <button
                                                            wire:click="toggleOrderDirection"
                                                            class="bg-white border border-gray-300 border-l-0 rounded-r px-2 py-0.5 flex-shrink-0 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:bg-gray-50 transition-colors"
                                                            title="{{ $orderDirection === 'asc' ? 'Ordem crescente' : 'Ordem decrescente' }}"
                                                            aria-label="Toggle order direction"
                                                        >
                                                            @if($orderDirection === 'asc')
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                                                </svg>
                                                            @else
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                                                </svg>
                                                            @endif
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Ações: ficam fixas à direita em telas >= lg, em telas pequenas ficam abaixo (stack) -->
                                        <div class="flex items-center gap-2 sm:gap-3 mt-0 lg:mt-0 lg:ml-6 flex-shrink-0">
                                            <button
                                                wire:click="refreshData"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center justify-center px-3 py-1.5 rounded-md text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md focus:outline-none focus:ring-2 focus:ring-[#0071B9]/40 disabled:opacity-50 text-xs sm:text-sm font-medium whitespace-nowrap flex-shrink-0"
                                                title="Atualizar"
                                                aria-label="Atualizar dados"
                                            >
                                                <span wire:loading.remove wire:target="refreshData" class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-4 sm:h-4 lg:w-5 lg:h-5 flex-shrink-0 sm:mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                    <span class="hidden sm:inline lg:inline text-xs lg:text-sm">Atualizar</span>
                                                </span>
                                                <span wire:loading wire:target="refreshData" class="flex items-center">
                                                    <div class="w-4 h-4 sm:w-4 sm:h-4 lg:w-5 lg:h-5 border-2 border-t-white border-white/30 rounded-full animate-spin flex-shrink-0 sm:mr-1.5"></div>
                                                    <span class="hidden sm:inline lg:inline text-xs lg:text-sm">Atualizando...</span>
                                                </span>
                                            </button>

                                            <button
                                                wire:click="resetFilters"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center justify-center px-3 py-1.5 rounded-md text-gray-700 bg-gray-100 border border-gray-300 hover:bg-gray-200 shadow-md focus:outline-none focus:ring-2 focus:ring-gray-400/40 disabled:opacity-50 text-xs sm:text-sm font-medium whitespace-nowrap flex-shrink-0"
                                                title="Limpar filtros"
                                                aria-label="Limpar filtros"
                                            >
                                                <span wire:loading.remove wire:target="resetFilters" class="flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 sm:w-4 sm:h-4 lg:w-5 lg:h-5 flex-shrink-0 sm:mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    <span class="hidden sm:inline lg:inline text-xs lg:text-sm">Limpar</span>
                                                </span>
                                                <span wire:loading wire:target="resetFilters" class="flex items-center">
                                                    <div class="w-4 h-4 sm:w-4 sm:h-4 lg:w-5 lg:h-5 border-2 border-t-gray-600 border-gray-600/30 rounded-full animate-spin flex-shrink-0 sm:mr-1.5"></div>
                                                    <span class="hidden sm:inline lg:inline text-xs lg:text-sm">Limpando...</span>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Patients container --}}
                        <div id="patientsContainer" class="p-3 sm:p-4 lg:p-6 xl:p-8 bg-white">
                            @if(isset($errorMessage) && $errorMessage)
                                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 mr-2 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <strong class="font-bold">Erro:</strong> <span class="ml-2">{{ $errorMessage }}</span>
                                    </div>
                                </div>
                            @elseif(empty($patients))
                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 mr-2 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Nenhum paciente encontrado para o filtro.
                                    </div>
                                </div>
                            @else
                                <div id="patientCardsContainer" class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
                                    @foreach($patients as $index => $patient)
                                        <div wire:key="patient-{{ $patient['nr_atendimento'] ?? $index }}" class="relative patient-card" x-data>
                                            <div class="cursor-pointer transform transition-all duration-300 hover:scale-105 relative"
                                                    wire:click="$dispatch('openModal', { attendanceNumber: {{ $patient['nr_atendimento'] ?? 0 }}, hospital: '{{ $currentHospitalName }}' })">
                                                <div class="rounded-xl shadow-lg h-64 sm:h-72 md:h-80 lg:h-80">
                                                    @if(!($patient['has_patient'] ?? false))
                                                        <div class="h-full bg-gradient-to-br from-gray-200 to-gray-300 p-3 sm:p-4 flex flex-col">
                                                            <div class="flex justify-between items-center mb-3">
                                                                <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                                                                    Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                                                                </span>
                                                            </div>

                                                            <div class="flex-grow flex items-center justify-center">
                                                                <div class="text-center">
                                                                    <svg class="mx-auto h-10 w-10 sm:h-12 sm:w-12 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                    </svg>
                                                                    <p class="text-gray-500 text-sm font-medium">Leito Vazio</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @else
                                                        <div class="h-full bg-gradient-to-br {{ $patient['gradient_class'] }} p-3 sm:p-4 flex flex-col {{ $patient['border_class'] }}">
                                                            {{-- Conteúdo completo do card do paciente mantido igual --}}
                                                            <div class="flex justify-between items-start mb-3">
                                                                <div class="flex items-center space-x-2 flex-shrink-0">
                                                                    <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                                                                        Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                                                                    </span>
                                                                </div>

                                                                <div class="flex items-center justify-center space-x-1 flex-1 px-2">
                                                                    @if($patient['has_allergy'] ?? false)
                                                                        <div class="relative group">
                                                                            <div class="bg-red-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente com alergias">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                                                </svg>
                                                                            </div>
                                                                        </div>
                                                                    @endif

                                                                    @if($patient['has_isolation'] ?? false)
                                                                        <div class="relative group">
                                                                            <div class="bg-yellow-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente em isolamento">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                                                </svg>
                                                                            </div>
                                                                        </div>
                                                                    @endif

                                                                    @if($patient['has_cpoe_pending'] ?? false)
                                                                        <div class="relative group z-10">
                                                                            <div class="bg-blue-500 text-white rounded-full p-1 shadow-lg animate-pulse flex items-center justify-center min-w-[20px] min-h-[20px]" title="CPOE pendente" @click.stop>
                                                                                <div class="flex items-center justify-center">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5 sm:h-3 sm:w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                                                    </svg>
                                                                                    <span class="text-xs font-bold ml-0.5">{{ $patient['cpoe_pending_count'] ?? 0 }}</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endif

                                                                    @if($patient['has_surgery'] ?? false)
                                                                        <div class="relative group">
                                                                            <div class="bg-purple-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente com cirurgias">
                                                                                 @svg('healthicons-o-surgical-sterilization', 'h-3 w-3 sm:h-4 sm:w-4')
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>

                                                                <div class="flex-shrink-0">
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $patient['text_color_class'] }} bg-white/70 whitespace-nowrap">
                                                                        {{ $patient['mews_display'] ?? 'MEWS: N/A' }}
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div class="mb-3 flex items-center">
                                                                <div class="ml-3 truncate flex-1 min-w-0">
                                                                    <p class="text-gray-600 text-xs truncate"><strong>{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</strong></p>
                                                                    <p class="text-gray-600 text-xs truncate">Atend: {{ $patient['nr_atendimento'] ?? 'N/A' }}</p>
                                                                    <p class="text-gray-600 text-xs truncate">Pront: {{ $patient['nr_prontuario'] ?? 'N/A' }}</p>
                                                                </div>
                                                            </div>

                                                            <div class="flex-grow">
                                                                <div class="space-y-1.5">
                                                                    <div class="text-xs text-gray-600">
                                                                        <span class="flex items-center gap-2 font-normal text-xs text-gray-600">
                                                                            @if(($patient['sexo'] ?? '') === 'F')
                                                                                <span class="inline-flex items-center justify-center rounded-full text-pink-600" style="width: 1.5em; height: 1.5em;">♀</span>
                                                                                <span class="opacity-70">Feminino</span>
                                                                            @elseif(($patient['sexo'] ?? '') === 'M')
                                                                                <span class="inline-flex items-center justify-center rounded-full text-blue-600" style="width: 1.5em; height: 1.5em;">♂</span>
                                                                                <span class="opacity-70">Masculino</span>
                                                                            @else
                                                                                <span class="opacity-70">Sexo: N/I</span>
                                                                            @endif
                                                                        </span>
                                                                    </div>

                                                                    <div class="text-xs text-gray-600">
                                                                        <span class="font-medium">Idade:</span>
                                                                        <span class="truncate">{{ $patient['age_detailed'] ?? $patient['age'] ?? 'N/A' }}</span>
                                                                        @if(!empty($patient['birth_date']))
                                                                            <span class="text-gray-500 block sm:inline">({{ $patient['birth_date'] }})</span>
                                                                        @endif
                                                                    </div>

                                                                    <div class="text-xs text-gray-600">
                                                                        <span class="font-medium">Internação:</span>
                                                                        @if($patient['is_new_patient'] ?? false)
                                                                            <span class="text-green-600 font-bold">Recém-chegado (hoje)</span>
                                                                        @elseif(!isset($patient['internment_days']) || $patient['internment_days'] === null)
                                                                            <span class="text-gray-500">N/A</span>
                                                                        @else
                                                                            @php $days = ceil($patient['internment_days']); @endphp
                                                                            <span class="text-gray-800">{{ $days }} dia{{ $days != 1 ? 's' : '' }}</span>
                                                                        @endif
                                                                    </div>

                                                                    @if(!empty($patient['medico_responsavel']))
                                                                        <div class="text-xs text-gray-600 truncate">
                                                                            <span class="font-medium">Médico:</span>
                                                                            <span class="truncate">{{ $patient['medico_responsavel'] }}</span>
                                                                        </div>
                                                                    @endif

                                                                    @if(!empty($patient['convenio']))
                                                                        <div class="text-xs text-gray-600 truncate">
                                                                            <span class="font-medium">Convênio:</span>
                                                                            <span class="truncate">{{ $patient['convenio'] }}</span>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <div class="mt-3 flex justify-between items-center">
                                                                <div class="flex space-x-1"></div>
                                                                <div class="text-xs text-gray-500">Clique para detalhes</div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Legenda --}}
                    <div class="mt-6 mb-16 lg:mb-6 p-3 sm:p-4 md:p-6 bg-white rounded-xl shadow-lg border border-gray-100">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-3 sm:mb-4">Legenda</h2>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                            <div>
                                <h3 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3 border-b border-gray-200 pb-1">Status do Leito</h3>
                                <ul class="space-y-2 sm:space-y-3">
                                    <li class="flex items-center">
                                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-md mr-2 sm:mr-3 border border-gray-200 flex-shrink-0"></span>
                                        <span class="text-sm sm:text-base text-gray-600">Leito Ocupado</span>
                                    </li>
                                    <li class="flex items-center">
                                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-gray-200 to-gray-300 rounded-md mr-2 sm:mr-3 border border-gray-200 flex-shrink-0"></span>
                                        <span class="text-sm sm:text-base text-gray-600">Leito Vazio</span>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <h3 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3 border-b border-gray-200 pb-1">Escala MEWS</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                                    <div class="flex items-center">
                                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-red-50 to-red-100 rounded-md mr-2 sm:mr-3 border-2 border-red-500 flex-shrink-0"></span>
                                        <span class="text-xs sm:text-sm text-gray-600">MEWS ≥ 5 (Crítico)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-amber-50 to-amber-100 rounded-md mr-2 sm:mr-3 border-2 border-amber-500 flex-shrink-0"></span>
                                        <span class="text-xs sm:text-sm text-gray-600">MEWS 3-4 (Alerta)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-md mr-2 sm:mr-3 border border-gray-200 flex-shrink-0"></span>
                                        <span class="text-xs sm:text-sm text-gray-600">MEWS 0-2 (Normal)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-green-50 to-green-100 rounded-md mr-2 sm:mr-3 border-2 border-green-400 flex-shrink-0"></span>
                                        <span class="text-xs sm:text-sm text-gray-600">Recém-chegado</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    {{-- Modal de paciente --}}
                    @livewire('patient-modal', [], key('patient-modal'))
                </div>
            </div>
        </div>
    </div>
</div>