<div class="hidden lg:block">
    <div class="flex flex-wrap items-end gap-2 xl:gap-3">

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Hospital:</label>
            <select wire:model="selectedHospital" wire:change="changeHospital($event.target.value)"
                class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                @foreach($hospitals as $hospital)
                    <option value="{{ $hospital['hospital_id'] }}">{{ $hospital['hospital_name'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Setor:</label>
            <select wire:model="selectedSector" wire:change="changeSector($event.target.value)"
                class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                @foreach($sectors as $sector)
                    <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Criticidade:</label>
            <select x-model="mewsFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                <option value="all">Todos MEWS</option>
                <option value="critical">CRÍTICOS (≥5)</option>
                <option value="warning">ALERTA (3-4)</option>
                <option value="normal">NORMAIS (0-2)</option>
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Isolamento:</label>
            <select x-model="isolationFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                <option value="all">Todos</option>
                <option value="with_isolation">Com isolamento</option>
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Leitos:</label>
            <select x-model="bedsFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full">
                <option value="all">Todos leitos</option>
                <option value="only_occupied">Só ocupados</option>
                <option value="only_empty">Só vagos</option>
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Passagem:</label>
            <select x-model="handoverFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="all">Todas</option>
                <option value="done">Com anotação</option>
                <option value="not_done">Sem anotação</option>
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Alta:</label>
            <select x-model="dischargeFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="all">Todos</option>
                <option value="today">Com alta/previsão</option>
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Antimicrobiano:</label>
            <select x-model="antibioticFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="all">Todos</option>
                <option value="active">Com antimicrobiano</option>
            </select>
        </div>

        <div class="flex flex-col min-w-0 flex-1">
            <label class="text-white text-xs xl:text-sm font-medium mb-0.5 xl:mb-1">Internação:</label>
            <select x-model="internmentFilter" @change="applyFilters()" class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 xl:py-2 xl:px-3 text-xs xl:text-sm focus:ring-2 focus:ring-[#0071B9]/40 w-full disabled:opacity-60 disabled:cursor-not-allowed">
                <option value="all">Todos</option>
                <option value="gt3">Mais de 3 dias</option>
                <option value="gt7">Mais de 7 dias</option>
                <option value="gt14">Mais de 14 dias</option>
            </select>
        </div>

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

        <div class="flex flex-col justify-end flex-shrink-0">
            <button @click="resetFilters()"
                    x-show="hasActiveFilters()"
                    x-cloak
                    class="flex items-center gap-1.5 px-3 py-2 bg-amber-500 hover:bg-amber-400 rounded-lg text-white text-sm font-medium transition-colors">
                <i class="fas fa-rotate-left"></i>
                Limpar
            </button>
        </div>

        @if($canStartHandover)
        <div class="flex flex-col justify-end flex-shrink-0">
            <button wire:click="startHandover"
                    wire:loading.attr="disabled"
                    wire:target="startHandover"
                    :disabled="isInitialLoading"
                    class="inline-flex items-center gap-1.5 px-3 xl:px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 disabled:cursor-not-allowed rounded-lg text-white text-xs xl:text-sm font-semibold transition-colors shadow-md whitespace-nowrap">
                <i class="fas fa-play text-[11px] xl:text-xs"></i>
                <span>Iniciar Passagem</span>
            </button>
        </div>
        @endif
    </div>
</div>
