<?php
// File: resources/views/livewire/sbar-report.blade.php
// CORREÇÕES APLICADAS
?>
<div class="w-full my-2 text-[#004D9D] relative font-montserrat">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">
            <div class="relative" x-data="sbarDashboard()" x-init="init()" @auto-refreshed.window="handleAutoRefresh()">

                {{-- Container principal do conteúdo --}}
                <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                    {{-- Cabeçalho com controles e filtros --}}
                    <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 top-0 z-50 shadow-lg font-montserrat">
                        <div class="flex flex-col space-y-3 sm:space-y-2 font-montserrat">

                            {{-- Linha do título centralizado e indicadores de refresh --}}
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 font-montserrat">
                                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white font-montserrat text-center lg:text-left lg:flex-1 lg:min-w-0">Sistema SBAR - Passagem de Plantão</h1>

                                <div class="flex items-center justify-center lg:justify-end gap-1 flex-shrink-0 font-montserrat">
                                    {{-- Timestamp da última atualização - apenas em desktop --}}
                                    @if($lastRefresh)
                                        <span class="hidden sm:block text-white/80 text-xs font-montserrat mr-2">
                                            Última atualização: {{ $lastRefresh }}
                                        </span>
                                    @endif

                                    <div x-show="autoRefreshEnabled" class="flex items-center gap-1 bg-green-500/20 px-1.5 py-0.5 rounded-full">
                                        <div class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></div>
                                        <span class="text-green-200 text-xs hidden sm:inline">Auto-refresh</span>
                                        <span x-text="nextRefreshIn + 's'" class="text-green-200 text-xs"></span>
                                    </div>

                                    <button @click="toggleAutoRefresh()"
                                            class="hidden sm:inline-flex items-center px-2 py-1 rounded-md text-xs focus:outline-none"
                                            :class="autoRefreshEnabled ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'">
                                        <span x-text="autoRefreshEnabled ? 'Auto-refresh ON' : 'Auto-refresh OFF'"></span>
                                    </button>
                                </div>
                            </div>

                            {{-- Filters + Actions - Mobile First --}}
                            <div class="w-full">
                                <div class="flex flex-col lg:flex-row lg:items-end gap-3 sm:gap-4 lg:gap-6">

                                    {{-- Filters container --}}
                                    <div class="flex-1 min-w-0">
                                        {{-- Mobile collapsible filters --}}
                                        <div class="lg:hidden" x-data="{ filtersOpen: false }">
                                            {{-- Mobile filter trigger bar --}}
                                            <div class="flex items-center justify-between sm:justify-center gap-2">
                                                {{-- Filtros button --}}
                                                <button @click="filtersOpen = !filtersOpen"
                                                        class="flex-1 sm:flex-initial flex items-center justify-center px-3 py-2 bg-white/10 hover:bg-white/20 active:bg-white/30 border border-white/30 rounded-lg text-white text-xs sm:text-sm font-medium transition-colors duration-150">
                                                    <x-iconoir-filter-list class="text-white h-4 w-4 sm:h-5 sm:w-5 mr-1.5 flex-shrink-0" />
                                                    Filtros
                                                    <svg class="h-4 w-4 ml-1.5 transition-transform duration-200" :class="{ 'rotate-180': filtersOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>

                                                {{-- Avaliações button --}}
                                                <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $selectedSector]) }}"
                                                   class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] active:bg-[#003D7A] shadow-md focus:outline-none focus:ring-2 focus:ring-[#0071B9]/40 text-xs sm:text-sm font-medium whitespace-nowrap font-montserrat transition-all duration-150"
                                                   title="Avaliações do Turno">
                                                    <x-iconoir-chat-lines class="text-white h-4 w-4 sm:h-5 sm:w-5 sm:mr-1.5 flex-shrink-0" />
                                                    <span class="hidden sm:inline">Aval.</span>
                                                </a>

                                                {{-- Mobile update button --}}
                                                <button wire:click="refreshData"
                                                        wire:loading.attr="disabled"
                                                        wire:target="refreshData"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] active:bg-[#003D7A] shadow-md focus:outline-none focus:ring-2 focus:ring-[#0071B9]/40 disabled:opacity-50 disabled:cursor-not-allowed text-xs sm:text-sm font-medium whitespace-nowrap font-montserrat transition-all duration-150"
                                                        title="Atualizar dados">
                                                    <span class="flex items-center font-montserrat" wire:loading.remove wire:target="refreshData">
                                                        <x-iconoir-reload-window class="text-white h-4 w-4 sm:h-5 sm:w-5 sm:mr-1.5 flex-shrink-0" />
                                                        <span class="hidden sm:inline">Atualizar</span>
                                                    </span>
                                                    <span wire:loading wire:target="refreshData" class="flex items-center">
                                                        <svg class="animate-spin h-4 w-4 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                    </span>
                                                </button>

                                                {{-- Clear filters button --}}
                                                <button wire:click="resetFilters"
                                                        wire:loading.attr="disabled"
                                                        wire:target="resetFilters"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 active:bg-gray-300 border border-gray-300 text-xs sm:text-sm font-medium transition-all duration-150"
                                                        title="Limpar filtros">
                                                    <x-heroicon-c-x-mark class="text-gray-700 h-4 w-4 sm:h-5 sm:w-5" />
                                                </button>
                                            </div>

                                            {{-- Mobile filter panel --}}
                                            <div x-show="filtersOpen"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 -translate-y-2"
                                                 x-transition:enter-end="opacity-100 translate-y-0"
                                                 x-transition:leave="transition ease-in duration-150"
                                                 x-transition:leave-start="opacity-100 translate-y-0"
                                                 x-transition:leave-end="opacity-0 -translate-y-2"
                                                 class="mt-2.5 p-3 sm:p-4 bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg space-y-3"
                                                 @click.away="filtersOpen = false">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                                    {{-- Hospital --}}
                                                    <div>
                                                        <label class="block text-white text-xs sm:text-sm font-medium mb-1.5">Hospital:</label>
                                                        <select wire:model="selectedHospital"
                                                                wire:change="changeHospital($event.target.value)"
                                                                wire:loading.attr="disabled"
                                                                wire:target="changeHospital"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-xs sm:text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                            @foreach($hospitals as $hospital)
                                                                <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Sector --}}
                                                    <div>
                                                        <label class="block text-white text-xs sm:text-sm font-medium mb-1.5">Setor:</label>
                                                        <select wire:model="selectedSector"
                                                                wire:change="changeSector($event.target.value)"
                                                                wire:loading.attr="disabled"
                                                                wire:target="changeSector"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-xs sm:text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                            @foreach($sectors as $sector)
                                                                <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Criticidade --}}
                                                    <div>
                                                        <label class="block text-white text-xs sm:text-sm font-medium mb-1.5">Criticidade:</label>
                                                        <select wire:model.live="mewsFilter"
                                                                wire:loading.attr="disabled"
                                                                wire:target="mewsFilter"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-xs sm:text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                            <option value="all">Todos MEWS</option>
                                                            <option value="critical">CRÍTICOS (≥5)</option>
                                                            <option value="warning">ALERTA (3-4)</option>
                                                            <option value="normal">NORMAIS (0-2)</option>
                                                        </select>
                                                    </div>

                                                    {{-- Cirurgias --}}
                                                    <div>
                                                        <label class="block text-white text-xs sm:text-sm font-medium mb-1.5">Cirurgias:</label>
                                                        <select wire:model.live="surgicalFilter"
                                                                wire:loading.attr="disabled"
                                                                wire:target="surgicalFilter"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-xs sm:text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                            <option value="all">Todas Cirurgias</option>
                                                            <option value="with_surgery">COM CIRURGIAS</option>
                                                            <option value="without_surgery">SEM CIRURGIAS</option>
                                                        </select>
                                                    </div>

                                                    {{-- Ordenação - Full width on mobile --}}
                                                    <div class="sm:col-span-2">
                                                        <label class="block text-white text-xs sm:text-sm font-medium mb-1.5">Ordenação:</label>
                                                        <div class="flex">
                                                            <select wire:model.live="orderBy"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="orderBy"
                                                                    class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-l-lg py-2 px-3 text-xs sm:text-sm flex-1 focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                                <option value="bed">Leito</option>
                                                                <option value="mews">MEWS</option>
                                                                <option value="name">Nome</option>
                                                                <option value="prontuario">Prontuário</option>
                                                                <option value="internment">Internação</option>
                                                                <option value="age">Idade</option>
                                                            </select>
                                                            <button wire:click="toggleOrderDirection"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="toggleOrderDirection"
                                                                    class="bg-white hover:bg-gray-50 active:bg-gray-100 border border-gray-300 border-l-0 rounded-r-lg px-3 py-2 transition-colors duration-150 flex-shrink-0">
                                                                <svg class="h-4 w-4 sm:h-5 sm:w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    @if($orderDirection === 'asc')
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6" />
                                                                    @else
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9" />
                                                                    @endif
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Desktop inline filters --}}
                                        <div class="hidden lg:block">
                                            <div class="flex flex-wrap xl:flex-nowrap items-end gap-3 xl:gap-4">
                                                {{-- Hospital --}}
                                                <div class="flex flex-col min-w-0 flex-1">
                                                    <label class="text-white text-sm font-medium mb-1.5">Hospital:</label>
                                                    <select wire:model="selectedHospital"
                                                            wire:change="changeHospital($event.target.value)"
                                                            wire:loading.attr="disabled"
                                                            wire:target="changeHospital"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                        @foreach($hospitals as $hospital)
                                                            <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                {{-- Sector --}}
                                                <div class="flex flex-col min-w-0 flex-1">
                                                    <label class="text-white text-sm font-medium mb-1.5">Setor:</label>
                                                    <select wire:model="selectedSector"
                                                            wire:change="changeSector($event.target.value)"
                                                            wire:loading.attr="disabled"
                                                            wire:target="changeSector"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                        @foreach($sectors as $sector)
                                                            <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                {{-- Criticidade --}}
                                                <div class="flex flex-col min-w-0 flex-1">
                                                    <label class="text-white text-sm font-medium mb-1.5">Criticidade:</label>
                                                    <select wire:model.live="mewsFilter"
                                                            wire:loading.attr="disabled"
                                                            wire:target="mewsFilter"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                        <option value="all">Todos MEWS</option>
                                                        <option value="critical">CRÍTICOS (≥5)</option>
                                                        <option value="warning">ALERTA (3-4)</option>
                                                        <option value="normal">NORMAIS (0-2)</option>
                                                    </select>
                                                </div>

                                                {{-- Cirurgias --}}
                                                <div class="flex flex-col min-w-0 flex-1">
                                                    <label class="text-white text-sm font-medium mb-1.5">Cirurgias:</label>
                                                    <select wire:model.live="surgicalFilter"
                                                            wire:loading.attr="disabled"
                                                            wire:target="surgicalFilter"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm w-full focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                        <option value="all">Todas Cirurgias</option>
                                                        <option value="with_surgery">COM CIRURGIAS</option>
                                                        <option value="without_surgery">SEM CIRURGIAS</option>
                                                    </select>
                                                </div>

                                                {{-- Ordenação --}}
                                                <div class="flex flex-col min-w-0 flex-1">
                                                    <label class="text-white text-sm font-medium mb-1.5">Ordenação:</label>
                                                    <div class="flex min-w-[160px]">
                                                        <select wire:model.live="orderBy"
                                                                wire:loading.attr="disabled"
                                                                wire:target="orderBy"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-l-lg py-2 px-3 text-sm flex-1 focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] transition-all duration-150">
                                                            <option value="bed">Leito</option>
                                                            <option value="mews">MEWS</option>
                                                            <option value="name">Nome</option>
                                                            <option value="prontuario">Prontuário</option>
                                                            <option value="internment">Internação</option>
                                                            <option value="age">Idade</option>
                                                        </select>

                                                        <button wire:click="toggleOrderDirection"
                                                                wire:loading.attr="disabled"
                                                                wire:target="toggleOrderDirection"
                                                                class="bg-white hover:bg-gray-50 active:bg-gray-100 border border-gray-300 border-l-0 rounded-r-lg px-3 py-2 transition-colors duration-150">
                                                            <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                @if($orderDirection === 'asc')
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6" />
                                                                @else
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9" />
                                                                @endif
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Desktop actions (Update + Avaliações + Clear) --}}
                                    <div class="hidden lg:flex items-center gap-2.5 flex-shrink-0">
                                        {{-- Botão de Atualizar --}}
                                        <button wire:click="refreshData"
                                                wire:loading.attr="disabled"
                                                wire:target="refreshData"
                                                class="inline-flex items-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] active:bg-[#003D7A] shadow-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-150">
                                            <span wire:loading.remove wire:target="refreshData" class="flex items-center">
                                                <x-iconoir-reload-window class="text-white h-4 w-4 mr-1.5 flex-shrink-0" />
                                                Atualizar
                                            </span>
                                            <span wire:loading wire:target="refreshData" class="flex items-center">
                                                <svg class="animate-spin h-4 w-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Atualizando...
                                            </span>
                                        </button>

                                        {{-- Botão de Avaliações --}}
                                        <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $selectedSector]) }}"
                                           class="inline-flex items-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] active:bg-[#003D7A] shadow-md focus:outline-none focus:ring-2 focus:ring-[#0071B9]/40 text-sm font-medium whitespace-nowrap font-montserrat transition-all duration-150">
                                            <x-iconoir-chat-lines class="text-white h-4 w-4 mr-1.5 flex-shrink-0" />
                                            Avaliações do Turno
                                        </a>

                                        {{-- Botão de Limpar --}}
                                        <button wire:click="resetFilters"
                                                wire:loading.attr="disabled"
                                                wire:target="resetFilters"
                                                class="inline-flex items-center px-3 py-2 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 active:bg-gray-300 border border-gray-300 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-150">
                                            <x-heroicon-c-x-mark class="text-gray-700 h-4 w-4 mr-1.5 flex-shrink-0" />
                                            Limpar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Patients container --}}
                    <div id="patientsContainer" class="p-2 sm:p-3 lg:p-4 bg-white">
                        @if($loading && $failedPatient)
                            <div class="bg-red-100 border border-red-300 p-4 rounded mb-4">
                                <strong>Paciente com erro:</strong>
                                <div>Nome: {{ $failedPatient['nm_pessoa_fisica'] ?? 'N/A' }}</div>
                                <div>Prontuário: {{ $failedPatient['nr_prontuario'] ?? 'N/A' }}</div>
                                <div>Atendimento: {{ $failedPatient['nr_atendimento'] ?? 'N/A' }}</div>
                            </div>
                        @endif

                        @if(isset($errorMessage) && $errorMessage)
                            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6 font-montserrat">
                                <div class="flex items-center font-montserrat">
                                    <svg class="w-6 h-6 mr-2 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <strong class="font-bold font-montserrat">Erro:</strong>
                                    <span class="ml-2 font-montserrat">{{ $errorMessage }}</span>
                                </div>
                            </div>
                        @elseif(empty($patients))
                            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg font-montserrat">
                                <div class="flex items-center font-montserrat">
                                    <svg class="w-6 h-6 mr-2 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Nenhum paciente encontrado para o filtro aplicado.
                                </div>
                            </div>
                        @else
                            {{-- Grid de pacientes --}}
                            <div id="patientCardsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-4 lg:gap-4 font-montserrat">
                                @foreach($patients as $index => $patient)
                                    <div wire:key="patient-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}"
                                         class="relative patient-card font-montserrat"
                                         x-data>
                                        @include('livewire.partials.patient-card', ['patient' => $patient, 'currentHospitalName' => $currentHospitalName])
                                    </div>
                                @endforeach
                            </div>

                            @if($hasMore)
                                <div class="mt-6 flex justify-center">
                                    <button wire:click="loadMore"
                                            wire:loading.attr="disabled"
                                            wire:target="loadMore"
                                            class="inline-flex items-center px-6 py-3 bg-[#004D9D] hover:bg-[#003D7A] text-white rounded-lg shadow-md font-medium transition-all duration-150 disabled:opacity-50">
                                        <span wire:loading.remove wire:target="loadMore">
                                            Carregar mais pacientes
                                        </span>
                                        <span wire:loading wire:target="loadMore" class="flex items-center">
                                            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Carregando...
                                        </span>
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Legenda de cores e indicadores --}}
                <div class="font-montserrat">
                    @include('livewire.partials.sbar-legend')
                </div>

                {{-- Modal de detalhes do paciente --}}
                <div class="font-montserrat">
                    @livewire('patient-modal', [], key('patient-modal'))
                </div>

            </div>
        </div>
    </div>
</div>
