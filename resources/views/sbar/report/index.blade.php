{{-- File: resources/views/livewire/sbar-report.blade.php --}}
<div class="w-full my-2 text-[#004D9D] relative font-montserrat">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">
            
            {{-- Mensagem de erro quando não tem setores configurados --}}
            @if(isset($errorMessage) && $errorMessage && strpos($errorMessage, 'setores de acesso') !== false)
                <div class="flex items-center justify-center min-h-[60vh]">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 max-w-md text-center">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-amber-600" />
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Acesso Bloqueado</h2>
                        <p class="text-gray-600 mb-6">{{ $errorMessage }}</p>
                        <a href="{{ route('user.preferences.index') }}" 
                           class="inline-flex items-center px-6 py-3 bg-[#004D9D] text-white font-semibold rounded-lg hover:bg-[#003d7a] transition-colors shadow-sm">
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5 mr-2" />
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
                                            onclick="window.dispatchEvent(new CustomEvent('open-sbar-legend'));"
                                            class="px-2 sm:px-3 py-1.5 sm:py-2 text-white text-lg sm:text-sm font-bold rounded hover:bg-white/20 transition-colors flex-shrink-0"
                                            title="Legenda e orientações"
                                        >
                                            <span class="hidden sm:inline">Legenda e orientações</span>
                                            <span class="sm:hidden">?</span>
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-center lg:justify-end gap-2 flex-shrink-0 font-montserrat">
                                        @if($lastRefresh)
                                            <span class="hidden xl:block text-white/80 text-xs font-montserrat mr-1">
                                                Última atualização: {{ $lastRefresh }}
                                            </span>
                                        @endif

                                        {{-- Action buttons — ficam na linha do título em lg+ para liberar espaço dos filtros --}}
                                        <div class="hidden lg:flex items-center gap-2 flex-shrink-0">
                                            <button wire:click="refreshData"
                                                    wire:loading.attr="disabled"
                                                    wire:target="refreshData"
                                                    :disabled="isInitialLoading"
                                                    class="inline-flex items-center px-2.5 h-8 xl:px-3 xl:h-9 min-w-[110px] xl:min-w-[126px] rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs xl:text-sm font-medium leading-none">
                                                <span class="inline-flex items-center gap-1.5" :class="isInitialLoading ? 'inline-flex' : 'hidden'">
                                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5 xl:h-4 xl:w-4 animate-spin" />
                                                    <span>Carregando...</span>
                                                </span>
                                                <span x-show="!isInitialLoading" x-cloak wire:loading.remove wire:target="refreshData" class="inline-flex items-center gap-1.5">
                                                    <x-iconoir-reload-window class="text-white h-3.5 w-3.5 xl:h-4 xl:w-4" />
                                                    <span>Atualizar</span>
                                                </span>
                                                <span x-show="!isInitialLoading" x-cloak wire:loading.inline-flex wire:target="refreshData" class="items-center gap-1.5">
                                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5 xl:h-4 xl:w-4 animate-spin" />
                                                    <span>Atualizando...</span>
                                                </span>
                                            </button>

                                            <button @click="$dispatch('openExpiredScalesModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                    :disabled="isInitialLoading"
                                                    wire:loading.attr="disabled"
                                                    wire:target="changeHospital,changeSector,refreshData"
                                                    class="inline-flex items-center px-2.5 py-1.5 xl:px-3 xl:py-2 rounded-lg text-white bg-orange-500 hover:bg-orange-600 shadow-md text-xs xl:text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-orange-500">
                                                <i class="fas fa-exclamation-triangle xl:mr-1.5"></i>
                                                <span class="hidden xl:inline">Escalas</span>
                                            </button>

                                            <button
                                                @click="$dispatch('openEvaluationsModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                :disabled="isInitialLoading"
                                                wire:loading.attr="disabled"
                                                wire:target="changeHospital,changeSector,refreshData"
                                                class="inline-flex items-center px-2.5 py-1.5 xl:px-3 xl:py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs xl:text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-[#0071B9]">
                                                <x-iconoir-chat-lines class="text-white h-3.5 w-3.5 xl:h-4 xl:w-4 xl:mr-1.5" />
                                                <span class="hidden xl:inline">Avaliações</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Filters + Actions --}}
                                <div class="w-full">
                                    <fieldset
                                        class="w-full border-0 p-0 m-0 min-w-0"
                                        disabled
                                        :disabled="isInitialLoading"
                                        wire:loading.attr="disabled"
                                        wire:target="changeHospital,changeSector,refreshData"
                                    >
                                    <div class="flex flex-col gap-3 sm:gap-4">

                                        {{-- Filters container --}}
                                        <div class="w-full min-w-0">
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
                                                        :disabled="isInitialLoading"
                                                        wire:loading.attr="disabled"
                                                        wire:target="changeHospital,changeSector,refreshData"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-orange-500 hover:bg-orange-600 shadow-md text-xs sm:text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-orange-500">
                                                        <i class="fas fa-exclamation-triangle leading-none sm:mr-1.5"></i>
                                                        <span class="hidden sm:inline">Escalas</span>
                                                    </button>

                                                    <button
                                                        @click="$dispatch('openEvaluationsModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                        :disabled="isInitialLoading"
                                                        wire:loading.attr="disabled"
                                                        wire:target="changeHospital,changeSector,refreshData"
                                                        class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs sm:text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-[#0071B9]">
                                                        <x-iconoir-chat-lines class="text-white h-4 w-4 sm:mr-1.5" />
                                                        <span class="hidden sm:inline">Aval.</span>
                                                    </button>

                                                    <button wire:click="refreshData"
                                                            wire:loading.attr="disabled"
                                                            wire:target="refreshData"
                                                            :disabled="isInitialLoading"
                                                            class="flex-shrink-0 inline-flex items-center justify-center gap-1.5 px-3 h-9 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs sm:text-sm font-medium leading-none">
                                                        <span class="inline-flex items-center gap-1.5" :class="isInitialLoading ? 'inline-flex' : 'hidden'">
                                                            <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                                                            <span class="hidden sm:inline">Carregando...</span>
                                                        </span>
                                                        <span x-show="!isInitialLoading" x-cloak wire:loading.remove wire:target="refreshData" class="inline-flex items-center gap-1.5">
                                                            <x-iconoir-reload-window class="text-white h-4 w-4" />
                                                            <span class="hidden sm:inline">Atualizar</span>
                                                        </span>
                                                        <span x-show="!isInitialLoading" x-cloak wire:loading.inline-flex wire:target="refreshData" class="items-center gap-1.5">
                                                            <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
                                                            <span class="hidden sm:inline">Atualizando...</span>
                                                        </span>
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
                                                                    :disabled="isInitialLoading"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="changeHospital,changeSector,refreshData"
                                                                        class="flex-1 bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                                                                    <option value="bed">Leito</option>
                                                                    <option value="mews">MEWS</option>
                                                                    <option value="internment">Internação</option>
                                                                    <option value="age">Idade</option>
                                                                    <option value="name">Nome</option>
                                                                </select>
                                                                <button @click="orderDir = orderDir === 'asc' ? 'desc' : 'asc'; applyFilters()"
                                                                        :disabled="isInitialLoading"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="changeHospital,changeSector,refreshData"
                                                                        class="h-9 px-2 inline-flex items-center justify-center bg-white/20 border border-white/40 rounded-lg text-white text-xs font-bold leading-none">
                                                                    <span x-show="isInitialLoading" class="inline-flex items-center justify-center">
                                                                        <x-heroicon-o-arrow-path class="h-3 w-3 animate-spin" />
                                                                    </span>
                                                                    <span x-show="!isInitialLoading" wire:loading.remove wire:target="changeHospital,changeSector,refreshData" x-text="orderDir === 'asc' ? '↑' : '↓'"></span>
                                                                    <span x-show="!isInitialLoading" wire:loading.inline-flex wire:target="changeHospital,changeSector,refreshData" class="items-center justify-center">
                                                                        <x-heroicon-o-arrow-path class="h-3 w-3 animate-spin" />
                                                                    </span>
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
                                                <div class="flex flex-wrap items-end gap-2 xl:gap-3">

                                                    {{-- Hospital --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Hospital:</label>
                                                        <select wire:model="selectedHospital" wire:change="changeHospital($event.target.value)"
                                                            class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                                                            @foreach($hospitals as $hospital)
                                                                <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Setor --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Setor:</label>
                                                        <select wire:model="selectedSector" wire:change="changeSector($event.target.value)"
                                                            class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                                                            @foreach($sectors as $sector)
                                                                <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    {{-- Criticidade --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Criticidade:</label>
                                                        <select x-model="mewsFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                                                            <option value="all">Todos MEWS</option>
                                                            <option value="critical">CRÍTICOS (≥5)</option>
                                                            <option value="warning">ALERTA (3-4)</option>
                                                            <option value="normal">NORMAIS (0-2)</option>
                                                        </select>
                                                    </div>

                                                    {{-- Cirurgia --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Cirurgia:</label>
                                                        <select x-model="surgicalFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                                                            <option value="all">Todas Cirurgias</option>
                                                            <option value="with_surgery">COM CIRURGIAS</option>
                                                            <option value="without_surgery">SEM CIRURGIAS</option>
                                                        </select>
                                                    </div>

                                                    {{-- Pendência --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Pendência:</label>
                                                        <select x-model="pendingTypeFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
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
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Isolamento:</label>
                                                        <select x-model="isolationFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                                                            <option value="all">Todos</option>
                                                            <option value="with_isolation">Com isolamento</option>
                                                        </select>
                                                    </div>

                                                    {{-- Multidisciplinar --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Multidisciplinar:</label>
                                                        <select x-model="multiFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
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
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Leitos:</label>
                                                        <select x-model="bedsFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                                                            <option value="all">Todos leitos</option>
                                                            <option value="only_occupied">Só ocupados</option>
                                                            <option value="only_empty">Só vagos</option>
                                                        </select>
                                                    </div>

                                                    {{-- Ordenar --}}
                                                    <div class="flex flex-col min-w-0 flex-1">
                                                        <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Ordenar:</label>
                                                        <div class="flex gap-1">
                                                            <select x-model="orderBy" @change="applyFilters()"
                                                                    :disabled="isInitialLoading"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="changeHospital,changeSector,refreshData"
                                                                    class="flex-1 bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40">
                                                                <option value="bed">Leito</option>
                                                                <option value="mews">MEWS</option>
                                                                <option value="internment">Internação</option>
                                                                <option value="age">Idade</option>
                                                                <option value="name">Nome</option>
                                                            </select>
                                                            <button @click="orderDir = orderDir === 'asc' ? 'desc' : 'asc'; applyFilters()"
                                                                    :title="orderDir === 'asc' ? 'Crescente' : 'Decrescente'"
                                                                    :disabled="isInitialLoading"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="changeHospital,changeSector,refreshData"
                                                                    class="h-9 px-3 inline-flex items-center justify-center bg-white/20 hover:bg-white/30 border border-white/40 rounded-lg text-white text-sm font-bold transition-colors self-end leading-none disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-white/20">
                                                                <span x-show="isInitialLoading" class="inline-flex items-center justify-center">
                                                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5 animate-spin" />
                                                                </span>
                                                                <i x-show="!isInitialLoading" wire:loading.remove wire:target="changeHospital,changeSector,refreshData" :class="orderDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down'" class="fas"></i>
                                                                <span x-show="!isInitialLoading" wire:loading.inline-flex wire:target="changeHospital,changeSector,refreshData" class="items-center justify-center">
                                                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5 animate-spin" />
                                                                </span>
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

                                    </div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>

                        {{-- Patients container --}}
                        <div id="patientsContainer" class="p-2 sm:p-3 lg:p-4 bg-white min-h-[60vh]">
                            @if(isset($errorMessage) && $errorMessage)
                                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                                    <div class="flex items-center">
                                        <x-heroicon-o-exclamation-triangle class="w-6 h-6 mr-2 text-red-500" />
                                        <strong>Erro:</strong>
                                        <span class="ml-2">{{ $errorMessage }}</span>
                                    </div>
                                </div>
                            @elseif(empty($patients))
                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg">
                                    <div class="flex items-center">
                                        <x-heroicon-o-information-circle class="w-6 h-6 mr-2 text-yellow-600" />
                                        Nenhum paciente encontrado para o filtro aplicado.
                                    </div>
                                </div>
                            @else
                                {{-- Grid de pacientes --}}
                                @php
                                    $modalPatients = collect($patients)
                                        ->filter(fn ($item) => ($item['has_patient'] ?? false) && !empty($item['nr_atendimento']))
                                        ->map(fn ($item) => [
                                            'nr_atendimento' => (int) $item['nr_atendimento'],
                                            'nm_pessoa_fisica' => $item['nm_pessoa_fisica'] ?? null,
                                            'nm_social' => $item['nm_social'] ?? null,
                                            'cd_unidade_basica' => $item['cd_unidade_basica'] ?? null,
                                            'ds_setor_atendimento' => $item['ds_setor_atendimento'] ?? null,
                                            'ds_prescricao' => $item['ds_prescricao'] ?? null,
                                        ])
                                        ->values()
                                        ->all();
                                @endphp
                                <div id="patientCardsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                                    @foreach($patients as $index => $patient)
                                        <div wire:key="patient-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}"
                                             class="relative patient-card"
                                             data-pid="{{ $index }}"
                                             data-has-patient="{{ ($patient['has_patient'] ?? false) ? '1' : '0' }}"
                                             data-mews="{{ $patient['mews_score'] ?? ($patient['pews_score'] ?? '') }}"
                                             data-has-surgery="{{ ($patient['has_surgery'] ?? false) ? '1' : '0' }}"
                                             data-has-isolation="{{ ($patient['has_isolation'] ?? false) ? '1' : '0' }}"
                                             data-pending-types="{{ $patient['pending_type_filter'] ?? '' }}"
                                             data-multi="{{ $patient['multi_team_filter'] ?? '' }}"
                                             data-bed="{{ $patient['cd_unidade_basica'] ?? '' }}"
                                             data-bed-seq="{{ $patient['bed_sequence'] ?? 0 }}"
                                             data-bed-order="{{ $patient['bed_display_order'] ?? $patient['bed_sequence'] ?? 0 }}"
                                             data-internment="{{ $patient['internment_days'] ?? -1 }}"
                                             data-age="{{ $patient['age'] ?? 0 }}"
                                             data-name="{{ strtolower($patient['nm_pessoa_fisica'] ?? 'zzz') }}">
                                            @include('sbar.patient.index', [
                                                'patient' => $patient,
                                                'currentHospitalName' => $currentHospitalName,
                                                'currentShiftName' => $currentShiftName,
                                                'modalPatients' => $modalPatients,
                                            ])
                                        </div>
                                    @endforeach
                                </div>

                                {{-- No results after client-side filter --}}
                                <div x-show="visibleCount === 0 && totalCount > 0"
                                     x-cloak
                                     class="mt-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg flex items-center gap-2">
                                    <x-heroicon-o-information-circle class="w-5 h-5 text-yellow-600 flex-shrink-0" />
                                    Nenhum paciente encontrado com os filtros aplicados.
                                    <button @click="resetFilters()" class="ml-auto text-sm font-medium underline">Limpar filtros</button>
                                </div>

                                {{-- Batch-warm therapeutic plan cache so modal opens are instant --}}
                                @if(!empty(collect($patients)->pluck('nr_atendimento')->filter()->values()->all()))
                                <script>
                                (function() {
                                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

                                    // 1. Warm prescriptions cache for visible patients
                                    fetch('/patient-care/prescriptions/warm', {
                                        method:  'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({ attendance_numbers: @json(collect($patients)->pluck('nr_atendimento')->filter()->values()->all()) }),
                                    }).catch(() => {});

                                    // 2. Warm other sectors so switching is instant (~5ms cache hit)
                                    @if(!empty(collect($sectors)->pluck('cd_setor_atendimento')->filter(fn($id) => $id != $selectedSector)->values()->all()))
                                    fetch('/sectors/warm', {
                                        method:  'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({ sector_ids: @json(collect($sectors)->pluck('cd_setor_atendimento')->filter(fn($id) => $id != $selectedSector)->values()->all()) }),
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
        isInitialLoading:  true,

        init() {
            this.$nextTick(() => {
                this.buildCards();
                this.applyFilters();
                this.isInitialLoading = false;
            });

            // Re-apply after Livewire re-renders patient list (preserves sort/filter state)
            window.addEventListener('sbar:patients-loaded', () => {
                this.$nextTick(() => { this.buildCards(); this.applyFilters(); });
            });

            // Also re-apply whenever any Livewire component updates, since morphdom may
            // remove the CSS `order` properties that Alpine sets via applyFilters().
            document.addEventListener('livewire:updated', () => {
                if (document.getElementById('patientCardsContainer')) {
                    this.$nextTick(() => { this.buildCards(); this.applyFilters(); });
                }
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
                bedOrder:     parseInt(el.dataset.bedOrder) || parseInt(el.dataset.bedSeq) || 0,
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
                // empty beds: always sorted by their physical display order, not pushed to end
                if (!a.hasPatient && !b.hasPatient) {
                    return a.bedOrder - b.bedOrder;
                }

                let ka, kb;
                switch (this.orderBy) {
                    case 'mews':
                        // empty beds at end when sorting by clinical criteria
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        ka = a.mews ?? -1; kb = b.mews ?? -1;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    case 'name':
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        return this.orderDir === 'asc'
                            ? a.name.localeCompare(b.name)
                            : b.name.localeCompare(a.name);
                    case 'internment':
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        ka = a.internment; kb = b.internment;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    case 'age':
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        ka = a.age; kb = b.age;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    default: // bed — interleave empty beds in their physical position
                        const diff = a.bedOrder - b.bedOrder;
                        return this.orderDir === 'asc' ? diff : -diff;
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
