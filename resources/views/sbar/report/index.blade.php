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
                <div class="relative" x-data="sbarFilters()" x-init="init()">

                    {{-- Main content container --}}
                    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                        {{-- Header with controls and filters --}}
                        <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 top-0 z-50 shadow-lg font-montserrat">
                            <div class="flex flex-col space-y-3 sm:space-y-2 font-montserrat">

                                {{-- Title row --}}
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 font-montserrat">
                                    <div class="flex items-center justify-center lg:justify-start gap-2 lg:flex-1 lg:min-w-0">
                                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white font-montserrat text-center lg:text-left">SBAR - Passagem de Plantão</h1>
                                        <button 
                                            onclick="document.getElementById('sbar-legend').scrollIntoView({ behavior: 'smooth' }); setTimeout(() => document.querySelector('#sbar-legend button').click(), 300);"
                                            class="px-2 sm:px-3 py-1.5 sm:py-2 text-white text-lg sm:text-sm font-bold rounded hover:bg-white/20 transition-colors flex-shrink-0"
                                            title="Legenda e orientações"
                                        >
                                            <span class="hidden sm:inline">Legenda e orientações</span>
                                            <span class="sm:hidden">?</span>
                                        </button>
                                    </div>

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
                                                        <i class="fas fa-exclamation-triangle leading-none sm:mr-1.5"></i>
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

                                                {{-- Expandable mobile filter panel --}}
                                                <div x-show="filtersOpen" x-cloak
                                                     class="mt-2 bg-white/10 rounded-lg p-3 space-y-2">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Hospital</label>
                                                            <select wire:model="selectedHospital" wire:change="changeHospital($event.target.value)"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                @foreach($hospitals as $hospital)
                                                                    <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Setor</label>
                                                            <select wire:model="selectedSector" wire:change="changeSector($event.target.value)"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                @foreach($sectors as $sector)
                                                                    <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Criticidade</label>
                                                            <select x-model="mewsFilter" @change="applyFilters()"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                <option value="all">Todos MEWS</option>
                                                                <option value="critical">CRÍTICOS (≥5)</option>
                                                                <option value="warning">ALERTA (3-4)</option>
                                                                <option value="normal">NORMAIS (0-2)</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Cirurgia</label>
                                                            <select x-model="surgicalFilter" @change="applyFilters()"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                <option value="all">Todas</option>
                                                                <option value="with_surgery">Com cirurgia</option>
                                                                <option value="without_surgery">Sem cirurgia</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Pendência</label>
                                                            <select x-model="pendingTypeFilter" @change="applyFilters()"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                <option value="all">Todas</option>
                                                                <option value="hemoterapia">Hemoterapia</option>
                                                                <option value="cirurgia">Cirurgia</option>
                                                                <option value="antibiotico">Antibiótico</option>
                                                                <option value="quimioterapia">Quimioterapia</option>
                                                                <option value="exame">Exame</option>
                                                                <option value="procedimento">Procedimento</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Isolamento</label>
                                                            <select x-model="isolationFilter" @change="applyFilters()"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                <option value="all">Todos</option>
                                                                <option value="with_isolation">Com isolamento</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Multidisciplinar</label>
                                                            <select x-model="multiFilter" @change="applyFilters()"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                <option value="all">Todos</option>
                                                                <option value="fisioterapia">Fisioterapia</option>
                                                                <option value="psicologia">Psicologia</option>
                                                                <option value="nutricao">Nutrição</option>
                                                                <option value="fonoaudiologia">Fonoaudiologia</option>
                                                                <option value="servico_social">Serviço Social</option>
                                                                <option value="acessos_vasculares">Ac. Vasculares</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Leitos</label>
                                                            <select x-model="bedsFilter" @change="applyFilters()"
                                                                    class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                <option value="all">Todos leitos</option>
                                                                <option value="only_occupied">Só ocupados</option>
                                                                <option value="only_empty">Só vagos</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="text-white text-xs font-medium block mb-1">Ordenar por</label>
                                                            <div class="flex gap-1">
                                                                <select x-model="orderBy" @change="applyFilters()"
                                                                        class="flex-1 bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                    <option value="bed">Leito</option>
                                                                    <option value="mews">MEWS</option>
                                                                    <option value="internment">Internação</option>
                                                                    <option value="age">Idade</option>
                                                                    <option value="name">Nome</option>
                                                                </select>
                                                                <button @click="orderDir = orderDir === 'asc' ? 'desc' : 'asc'; applyFilters()"
                                                                        class="px-2 bg-white/20 border border-white/40 rounded-lg text-white text-xs font-bold">
                                                                    <span x-text="orderDir === 'asc' ? '↑' : '↓'"></span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-end">
                                                            <button x-show="hasActiveFilters()" @click="resetFilters()"
                                                                    class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 bg-amber-500 hover:bg-amber-400 rounded-lg text-white text-xs font-medium">
                                                                <i class="fas fa-rotate-left text-xs"></i>
                                                                Limpar filtros
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Desktop filters --}}
                                            <div class="hidden lg:block">
                                                @php
                                                    $selCls = "bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full";
                                                @endphp
                                                <div class="flex flex-wrap items-end gap-3">

                                                    {{-- Hospital --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Hospital:</label>
                                                        <select wire:model="selectedHospital" wire:change="changeHospital($event.target.value)"
                                                                class="{{ $selCls }}">
                                                            @foreach($hospitals as $hospital)
                                                                <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Setor --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Setor:</label>
                                                        <select wire:model="selectedSector" wire:change="changeSector($event.target.value)"
                                                                class="{{ $selCls }}">
                                                            @foreach($sectors as $sector)
                                                                <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Criticidade --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Criticidade:</label>
                                                        <select x-model="mewsFilter" @change="applyFilters()" class="{{ $selCls }}">
                                                            <option value="all">Todos MEWS</option>
                                                            <option value="critical">CRÍTICOS (≥5)</option>
                                                            <option value="warning">ALERTA (3-4)</option>
                                                            <option value="normal">NORMAIS (0-2)</option>
                                                        </select>
                                                    </div>

                                                    {{-- Cirurgia --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Cirurgia:</label>
                                                        <select x-model="surgicalFilter" @change="applyFilters()" class="{{ $selCls }}">
                                                            <option value="all">Todas Cirurgias</option>
                                                            <option value="with_surgery">COM CIRURGIAS</option>
                                                            <option value="without_surgery">SEM CIRURGIAS</option>
                                                        </select>
                                                    </div>

                                                    {{-- Pendência --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Pendência:</label>
                                                        <select x-model="pendingTypeFilter" @change="applyFilters()" class="{{ $selCls }}">
                                                            <option value="all">Todas</option>
                                                            <option value="hemoterapia">Hemoterapia</option>
                                                            <option value="cirurgia">Cirurgia</option>
                                                            <option value="antibiotico">Antibiótico</option>
                                                            <option value="quimioterapia">Quimioterapia</option>
                                                            <option value="exame">Exame</option>
                                                            <option value="procedimento">Procedimento</option>
                                                        </select>
                                                    </div>

                                                    {{-- Isolamento --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Isolamento:</label>
                                                        <select x-model="isolationFilter" @change="applyFilters()" class="{{ $selCls }}">
                                                            <option value="all">Todos</option>
                                                            <option value="with_isolation">Com isolamento</option>
                                                        </select>
                                                    </div>

                                                    {{-- Multidisciplinar --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Multidisciplinar:</label>
                                                        <select x-model="multiFilter" @change="applyFilters()" class="{{ $selCls }}">
                                                            <option value="all">Todos</option>
                                                            <option value="fisioterapia">Fisioterapia</option>
                                                            <option value="psicologia">Psicologia</option>
                                                            <option value="nutricao">Nutrição</option>
                                                            <option value="fonoaudiologia">Fonoaudiologia</option>
                                                            <option value="servico_social">Serv. Social</option>
                                                            <option value="acessos_vasculares">Ac. Vasculares</option>
                                                        </select>
                                                    </div>

                                                    {{-- Leitos --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Leitos:</label>
                                                        <select x-model="bedsFilter" @change="applyFilters()" class="{{ $selCls }}">
                                                            <option value="all">Todos leitos</option>
                                                            <option value="only_occupied">Só ocupados</option>
                                                            <option value="only_empty">Só vagos</option>
                                                        </select>
                                                    </div>

                                                    {{-- Ordenar --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-sm font-medium mb-1">Ordenar:</label>
                                                        <div class="flex gap-1">
                                                            <select x-model="orderBy" @change="applyFilters()"
                                                                    class="flex-1 bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-3 text-sm focus:ring-2 focus:ring-[#0071B9]/40">
                                                                <option value="bed">Leito</option>
                                                                <option value="mews">MEWS</option>
                                                                <option value="internment">Internação</option>
                                                                <option value="age">Idade</option>
                                                                <option value="name">Nome</option>
                                                            </select>
                                                            <button @click="orderDir = orderDir === 'asc' ? 'desc' : 'asc'; applyFilters()"
                                                                    :title="orderDir === 'asc' ? 'Crescente' : 'Decrescente'"
                                                                    class="px-3 py-2 bg-white/20 hover:bg-white/30 border border-white/40 rounded-lg text-white text-sm font-bold transition-colors self-end">
                                                                <i :class="orderDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down'" class="fas"></i>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    {{-- Reset --}}
                                                    <div class="flex flex-col justify-end flex-shrink-0">
                                                        <button @click="resetFilters()"
                                                                x-show="hasActiveFilters()"
                                                                x-cloak
                                                                class="flex items-center gap-1.5 px-3 py-2 bg-amber-500 hover:bg-amber-400 rounded-lg text-white text-sm font-medium transition-colors">
                                                            <i class="fas fa-rotate-left"></i>
                                                            Limpar
                                                        </button>
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
                                        @php
                                            $cardPendingTypes = collect($patient['pending_events']['events'] ?? [])
                                                ->pluck('tipo')
                                                ->map(function ($type) {
                                                    if ($type === 'proc_exame') {
                                                        return 'exame';
                                                    }

                                                    return $type;
                                                })
                                                ->unique()
                                                ->filter()
                                                ->implode(',');
                                            $cardMultiTeams = collect($patient['multidisciplinary'] ?? [])
                                                ->filter()->keys()->implode(',');
                                        @endphp
                                        <div wire:key="patient-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}"
                                             class="relative patient-card"
                                             data-pid="{{ $index }}"
                                             data-has-patient="{{ ($patient['has_patient'] ?? false) ? '1' : '0' }}"
                                             data-mews="{{ $patient['mews_score'] ?? ($patient['pews_score'] ?? '') }}"
                                             data-has-surgery="{{ ($patient['has_surgery'] ?? false) ? '1' : '0' }}"
                                             data-has-isolation="{{ ($patient['has_isolation'] ?? false) ? '1' : '0' }}"
                                             data-pending-types="{{ $cardPendingTypes }}"
                                             data-multi="{{ $cardMultiTeams }}"
                                             data-bed="{{ $patient['cd_unidade_basica'] ?? '' }}"
                                             data-bed-seq="{{ $patient['bed_sequence'] ?? 0 }}"
                                             data-internment="{{ $patient['internment_days'] ?? -1 }}"
                                             data-age="{{ $patient['age'] ?? 0 }}"
                                             data-name="{{ strtolower($patient['nm_pessoa_fisica'] ?? 'zzz') }}">
                                            @include('sbar.patient.index', [
                                                'patient' => $patient,
                                                'currentHospitalName' => $currentHospitalName,
                                                'currentShiftName' => $currentShiftName,
                                            ])
                                        </div>
                                    @endforeach
                                </div>

                                {{-- No results after client-side filter --}}
                                <div x-show="visibleCount === 0 && totalCount > 0"
                                     x-cloak
                                     class="mt-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg flex items-center gap-2">
                                    <svg class="w-5 h-5 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Nenhum paciente encontrado com os filtros aplicados.
                                    <button @click="resetFilters()" class="ml-auto text-sm font-medium underline">Limpar filtros</button>
                                </div>

                                {{-- Batch-warm therapeutic plan cache so modal opens are instant --}}
                                @php
                                    $visibleAttendances = collect($patients)
                                        ->pluck('nr_atendimento')
                                        ->filter()
                                        ->values()
                                        ->all();
                                @endphp
                                @if(!empty($visibleAttendances))
                                <script>
                                (function() {
                                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

                                    // 1. Warm prescriptions cache for visible patients
                                    fetch('/patient-care/prescriptions/warm', {
                                        method:  'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({ attendance_numbers: @json($visibleAttendances) }),
                                    }).catch(() => {});

                                    // 2. Warm other sectors so switching is instant (~5ms cache hit)
                                    @php
                                        $otherSectorIds = collect($sectors)
                                            ->pluck('cd_setor_atendimento')
                                            ->filter(fn($id) => $id != $selectedSector)
                                            ->values()
                                            ->all();
                                    @endphp
                                    @if(!empty($otherSectorIds))
                                    fetch('/sectors/warm', {
                                        method:  'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({ sector_ids: @json($otherSectorIds) }),
                                    }).catch(() => {});
                                    @endif
                                })();
                                </script>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Legenda --}}
                    @include('sbar.report.partials.legend')

                    {{-- Modais --}}
                    @livewire('patient-modal', [], key('patient-modal'))
                    @livewire('expired-scales-modal', ['sectorId' => $selectedSector ?? 0], key('expired-scales-modal'))
                    @livewire('shift-evaluations-modal', [], key('shift-evaluations-modal'))

                </div>
            @endif
        </div>
    </div>
</div>

@script
<script>
window.sbarFilters = function () {
    return {
        mewsFilter:        'all',
        surgicalFilter:    'all',
        isolationFilter:   'all',
        pendingTypeFilter: 'all',
        multiFilter:       'all',
        bedsFilter:        'all',
        orderBy:           'bed',
        orderDir:          'asc',
        visibleCount:      0,
        totalCount:        0,
        cards:             [],

        init() {
            this.$nextTick(() => { this.buildCards(); this.applyFilters(); });

            // Re-apply after Livewire re-renders patient list
            window.addEventListener('sbar:patients-loaded', () => {
                this.$nextTick(() => { this.buildCards(); this.applyFilters(); });
            });
        },

        buildCards() {
            this.cards = Array.from(
                document.querySelectorAll('#patientCardsContainer [data-pid]')
            ).map(el => ({
                el,
                index:       parseInt(el.dataset.pid),
                hasPatient:  el.dataset.hasPatient === '1',
                mews:        el.dataset.mews === '' ? null : parseFloat(el.dataset.mews),
                hasSurgery:  el.dataset.hasSurgery === '1',
                hasIsolation: el.dataset.hasIsolation === '1',
                pendingTypes: el.dataset.pendingTypes ? el.dataset.pendingTypes.split(',').filter(Boolean) : [],
                multi:        el.dataset.multi ? el.dataset.multi.split(',').filter(Boolean) : [],
                bed:          el.dataset.bed || '',
                bedSeq:       parseInt(el.dataset.bedSeq) || 0,
                internment:   parseFloat(el.dataset.internment) || -1,
                age:          parseInt(el.dataset.age) || 0,
                name:         el.dataset.name || 'zzz',
            }));
            this.totalCount = this.cards.length;
        },

        applyFilters() {
            const visible = this.cards.filter(c => {
                if (!c.hasPatient) return this.bedsFilter !== 'only_occupied';
                if (this.bedsFilter === 'only_empty') return false;

                if (this.mewsFilter !== 'all') {
                    const s = c.mews;
                    if (this.mewsFilter === 'critical' && (s === null || s < 5))        return false;
                    if (this.mewsFilter === 'warning'  && (s === null || s < 3 || s > 4)) return false;
                    if (this.mewsFilter === 'normal'   && s !== null && s > 2)           return false;
                }
                if (this.surgicalFilter === 'with_surgery'    && !c.hasSurgery)  return false;
                if (this.surgicalFilter === 'without_surgery' && c.hasSurgery)   return false;
                if (this.isolationFilter === 'with_isolation' && !c.hasIsolation) return false;
                if (this.pendingTypeFilter !== 'all') {
                    const normalizedPending = c.pendingTypes.map((t) => t === 'proc_exame' ? 'exame' : t);
                    if (!normalizedPending.includes(this.pendingTypeFilter)) {
                        return false;
                    }
                }
                if (this.multiFilter !== 'all' && !c.multi.includes(this.multiFilter)) return false;

                return true;
            });

            visible.sort((a, b) => {
                // empty beds always at end
                if (!a.hasPatient && !b.hasPatient) {
                    const ka = a.bed + '-' + String(a.bedSeq).padStart(3, '0');
                    const kb = b.bed + '-' + String(b.bedSeq).padStart(3, '0');
                    return ka.localeCompare(kb);
                }
                if (!a.hasPatient) return 1;
                if (!b.hasPatient) return -1;

                let ka, kb;
                switch (this.orderBy) {
                    case 'mews':
                        ka = a.mews ?? -1; kb = b.mews ?? -1;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    case 'name':
                        return this.orderDir === 'asc'
                            ? a.name.localeCompare(b.name)
                            : b.name.localeCompare(a.name);
                    case 'internment':
                        ka = a.internment; kb = b.internment;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    case 'age':
                        ka = a.age; kb = b.age;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    default: // bed
                        ka = a.bed + '-' + String(a.bedSeq).padStart(3, '0');
                        kb = b.bed + '-' + String(b.bedSeq).padStart(3, '0');
                        return this.orderDir === 'asc' ? ka.localeCompare(kb) : kb.localeCompare(ka);
                }
            });

            const visibleSet = new Set(visible.map(c => c.index));
            this.cards.forEach(c => {
                c.el.style.display = visibleSet.has(c.index) ? '' : 'none';
                c.el.style.order   = '';
            });
            visible.forEach((c, i) => { c.el.style.order = i + 1; });

            this.visibleCount = visible.length;
        },

        resetFilters() {
            this.mewsFilter        = 'all';
            this.surgicalFilter    = 'all';
            this.isolationFilter   = 'all';
            this.pendingTypeFilter = 'all';
            this.multiFilter       = 'all';
            this.bedsFilter        = 'all';
            this.orderBy           = 'bed';
            this.orderDir          = 'asc';
            this.applyFilters();
        },

        hasActiveFilters() {
            return this.mewsFilter !== 'all'
                || this.surgicalFilter !== 'all'
                || this.isolationFilter !== 'all'
                || this.pendingTypeFilter !== 'all'
                || this.multiFilter !== 'all'
                || this.bedsFilter !== 'all'
                || this.orderBy !== 'bed'
                || this.orderDir !== 'asc';
        },
    };
};
</script>
@endscript
