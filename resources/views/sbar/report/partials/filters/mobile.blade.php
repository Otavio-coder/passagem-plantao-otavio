<div class="lg:hidden" x-data="{ filtersOpen: false }">
    <div class="flex items-center justify-between sm:justify-center gap-2">
        @if($canStartHandover)
        <button wire:click="startHandover"
                wire:loading.attr="disabled"
                wire:target="startHandover"
                :disabled="isInitialLoading"
                class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 px-3 py-2 min-w-[90px] rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 shadow-md text-xs sm:text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-emerald-600 transition-colors">
            <span wire:loading.remove wire:target="startHandover" class="inline-flex items-center gap-1.5">
                <i class="fas fa-play text-[10px] sm:text-xs leading-none"></i>
                <span>Passagem</span>
            </span>
            <span wire:loading wire:target="startHandover" class="inline-flex items-center gap-1.5">
                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span>...</span>
            </span>
        </button>
        @endif

        <button @click="filtersOpen = !filtersOpen"
                class="flex-1 sm:flex-initial flex items-center justify-center px-3 py-2 bg-white/10 hover:bg-white/20 border border-white/30 rounded-lg text-white text-xs sm:text-sm font-medium">
            <x-iconoir-filter-list class="text-white h-4 w-4 sm:h-5 sm:w-5 mr-1.5" />
            Filtros
        </button>

        <button
            @click="$dispatch('openSbarExpiredScalesModal', { sectorId: {{ $selectedSector ?? 0 }} })"
            :disabled="isInitialLoading"
            wire:loading.attr="disabled"
            wire:target="changeHospital,changeSector,refreshData"
            class="flex-shrink-0 inline-flex items-center justify-center px-3 py-2 rounded-lg text-white bg-orange-500 hover:bg-orange-600 shadow-md text-xs sm:text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-orange-500">
            <i class="fas fa-exclamation-triangle leading-none sm:mr-1.5"></i>
            <span class="hidden sm:inline">Escalas</span>
        </button>

        <button
            @click="$dispatch('openSbarEvaluationsModal', { sectorId: {{ $selectedSector ?? 0 }} })"
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
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-white text-xs font-medium block mb-1">Isolamento</label>
                <select x-model="isolationFilter" @change="applyFilters()"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs">
                    <option value="all">Todos</option>
                    <option value="with_isolation">Com isolamento</option>
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
                <label class="text-white text-xs font-medium block mb-1">Passagem</label>
                <select x-model="handoverFilter" @change="applyFilters()"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs disabled:opacity-60 disabled:cursor-not-allowed">
                    <option value="all">Todas</option>
                    <option value="done">Com anotação</option>
                    <option value="not_done">Sem anotação</option>
                </select>
            </div>
            <div>
                <label class="text-white text-xs font-medium block mb-1">Alta</label>
                <select x-model="dischargeFilter" @change="applyFilters()"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs disabled:opacity-60 disabled:cursor-not-allowed">
                    <option value="all">Todos</option>
                    <option value="today">Com alta/previsão</option>
                    <option value="previsao">Com previsão de alta</option>
                    <option value="no_previsao">Sem previsão de alta</option>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-white text-xs font-medium block mb-1">Antimicrobiano</label>
                <select x-model="antibioticFilter" @change="applyFilters()"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs disabled:opacity-60 disabled:cursor-not-allowed">
                    <option value="all">Todos</option>
                    <option value="active">Com antimicrobiano</option>
                </select>
            </div>
            <div>
                <label class="text-white text-xs font-medium block mb-1">Internação</label>
                <select x-model="internmentFilter" @change="applyFilters()"
                        class="w-full bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs disabled:opacity-60 disabled:cursor-not-allowed">
                    <option value="all">Todos</option>
                    <option value="gt3">+3 dias</option>
                    <option value="gt7">+7 dias</option>
                    <option value="gt14">+14 dias</option>
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
