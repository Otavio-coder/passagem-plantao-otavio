<?php
// File: resources/views/livewire/sbar-report.blade.php
?>
<div class="w-full my-2 text-[#004D9D] relative font-montserrat">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">
            
            {{-- Mensagem de erro quando não tem setores configurados --}}
            @if(isset($errorMessage) && $errorMessage && strpos($errorMessage, 'setores de acesso') !== false)
                <div class="flex items-center justify-center min-h-[60vh]">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 max-w-md text-center">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Acesso Bloqueado</h2>
                        <p class="text-gray-600 mb-6">{{ $errorMessage }}</p>
                        <a href="{{ route('user.preferences.index') }}" 
                           class="inline-flex items-center px-6 py-3 bg-[#004D9D] text-white font-semibold rounded-lg hover:bg-[#003d7a] transition-colors shadow-sm">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Configurar Meus Setores
                        </a>
                    </div>
                </div>
            @else
                {{-- Conteúdo normal do SBAR --}}
                <div class="relative" x-data="{ loading: false }" @sbar:loading.window="loading = $event.detail.show">

                    {{-- Main content container --}}
                    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                        {{-- Header with controls and filters --}}
                        <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 top-0 z-50 shadow-lg font-montserrat">
                            <div class="flex flex-col space-y-3 sm:space-y-2 font-montserrat">

                                {{-- Title row --}}
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 font-montserrat">
                                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white font-montserrat text-center lg:text-left lg:flex-1 lg:min-w-0">Sistema SBAR - Passagem de Plantão</h1>

                                    <div class="flex items-center justify-center lg:justify-end gap-1 flex-shrink-0 font-montserrat">
                                        @if($lastRefresh)
                                            <span class="hidden sm:block text-white/80 text-xs font-montserrat mr-2">
                                                Última atualização: {{ $lastRefresh }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Filters + Actions --}}
                                <div class="w-full">
                                    <div class="flex flex-col lg:flex-row lg:items-end gap-3 sm:gap-4 lg:gap-6">

                                        {{-- Filters container --}}
                                        <div class="flex-1 min-w-0">
                                            {{-- Mobile filters --}}
                                            <div class="lg:hidden" x-data="{ filtersOpen: false }">
                                                <div class="flex items-center justify-between sm:justify-center gap-2">
                                                    <button @click="filtersOpen = !filtersOpen"
                                                            class="flex-1 sm:flex-initial flex items-center justify-center px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/30 rounded-lg text-white text-xs sm:text-sm font-medium">
                                                        <x-iconoir-filter-list class="text-white h-4 w-4 sm:h-5 sm:w-5 mr-1.5" />
                                                        Filtros
                                                    </button>

                                                    <button
                                                        @click="$dispatch('openExpiredScalesModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-orange-500 hover:bg-orange-600 shadow-md text-xs sm:text-sm font-medium">
                                                        <i class="fas fa-exclamation-triangle h-4 w-4 sm:mr-1.5"></i>
                                                        <span class="hidden sm:inline">Escalas</span>
                                                    </button>

                                                    <button
                                                        @click="$dispatch('openEvaluationsModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs sm:text-sm font-medium">
                                                        <x-iconoir-chat-lines class="text-white h-4 w-4 sm:mr-1.5" />
                                                        <span class="hidden sm:inline">Aval.</span>
                                                    </button>

                                                    <button wire:click="refreshData"
                                                            wire:loading.attr="disabled"
                                                            class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs sm:text-sm font-medium">
                                                        <x-iconoir-reload-window class="text-white h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Desktop filters --}}
                                            <div class="hidden lg:block">
                                                <div class="flex flex-wrap items-end gap-3">
                                                    {{-- Hospital --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Hospital:</label>
                                                        <select wire:model="selectedHospital"
                                                                wire:change="changeHospital($event.target.value)"
                                                                class="bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-[#0071B9]/40">
                                                            @foreach($hospitals as $hospital)
                                                                <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Sector --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Setor:</label>
                                                        <select wire:model="selectedSector"
                                                                wire:change="changeSector($event.target.value)"
                                                                class="bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-[#0071B9]/40">
                                                            @foreach($sectors as $sector)
                                                                <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Criticidade --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Criticidade:</label>
                                                        <select wire:model.live="mewsFilter"
                                                                class="bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-[#0071B9]/40">
                                                            <option value="all">Todos MEWS</option>
                                                            <option value="critical">CRÍTICOS (≥5)</option>
                                                            <option value="warning">ALERTA (3-4)</option>
                                                            <option value="normal">NORMAIS (0-2)</option>
                                                        </select>
                                                    </div>

                                                    {{-- Cirurgias --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Cirurgias:</label>
                                                        <select wire:model.live="surgicalFilter"
                                                                class="bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-[#0071B9]/40">
                                                            <option value="all">Todas Cirurgias</option>
                                                            <option value="with_surgery">COM CIRURGIAS</option>
                                                            <option value="without_surgery">SEM CIRURGIAS</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Desktop actions --}}
                                        <div class="hidden lg:flex items-center gap-2.5 flex-shrink-0">
                                            <button wire:click="refreshData"
                                                    class="inline-flex items-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-sm font-medium">
                                                <x-iconoir-reload-window class="text-white h-4 w-4 mr-1.5" />
                                                Atualizar
                                            </button>

                                            <button @click="$dispatch('openExpiredScalesModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                    class="inline-flex items-center px-3 py-2 rounded-lg text-white bg-orange-500 hover:bg-orange-600 shadow-md text-sm font-medium">
                                                <i class="fas fa-exclamation-triangle mr-1.5"></i>
                                                Escalas
                                            </button>

                                            <button
                                                @click="$dispatch('openEvaluationsModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                class="inline-flex items-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-sm font-medium">
                                                <x-iconoir-chat-lines class="text-white h-4 w-4 mr-1.5" />
                                                Avaliações
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Patients container --}}
                        <div id="patientsContainer" class="p-2 sm:p-3 lg:p-4 bg-white min-h-[60vh]">
                            @if(isset($errorMessage) && $errorMessage)
                                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <strong>Erro:</strong>
                                        <span class="ml-2">{{ $errorMessage }}</span>
                                    </div>
                                </div>
                            @elseif(empty($patients))
                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg">
                                    <div class="flex items-center">
                                        <svg class="w-6 h-6 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Nenhum paciente encontrado para o filtro aplicado.
                                    </div>
                                </div>
                            @else
                                {{-- Grid de pacientes --}}
                                <div id="patientCardsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                                    @foreach($patients as $index => $patient)
                                        <div wire:key="patient-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}"
                                             class="relative patient-card">
                                            @include('livewire.partials.patient-card', ['patient' => $patient, 'currentHospitalName' => $currentHospitalName])
                                        </div>
                                    @endforeach
                                </div>

                                @if($hasMore)
                                    <div class="mt-6 flex justify-center">
                                        <button wire:click="loadMore"
                                                class="inline-flex items-center px-6 py-3 bg-[#004D9D] hover:bg-[#003D7A] text-white rounded-lg shadow-md font-medium">
                                            Carregar mais pacientes
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Legenda --}}
                    @include('livewire.partials.sbar-legend')

                    {{-- Modais --}}
                    @livewire('patient-modal', [], key('patient-modal'))
                    @livewire('expired-scales-modal', ['sectorId' => $selectedSector ?? 0], key('expired-scales-modal'))
                    @livewire('shift-evaluations-modal', [], key('shift-evaluations-modal'))

                </div>
            @endif
        </div>
    </div>
</div>
