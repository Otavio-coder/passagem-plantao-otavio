<div>
@if($show)
<div 
    class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4 md:p-6 bg-black/60"
    wire:key="sector-selector-modal"
    x-data="{ show: false, expandedHospitals: {} }"
    x-init="setTimeout(() => show = true, 50)"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div 
        class="relative w-full max-w-4xl max-h-[90vh] sm:max-h-[85vh] bg-white rounded-xl sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
    >
        {{-- Header --}}
        <div class="flex-shrink-0 bg-[#004D9D] px-4 sm:px-6 py-3 sm:py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-white">Configuração de Acesso</h2>
                    <p class="text-xs sm:text-sm text-white/80 mt-0.5">Selecione os setores que deseja acompanhar</p>
                </div>
                <div class="flex items-center gap-1.5 sm:gap-2 bg-white/10 px-3 py-1.5 rounded-lg">
                    <span class="text-white font-bold text-lg sm:text-xl">{{ $this->totalSelected }}</span>
                    <span class="text-white/70 text-xs">selec.</span>
                </div>
            </div>
        </div>

        {{-- Search Bar --}}
        <div class="flex-shrink-0 px-4 sm:px-6 py-3 border-b border-gray-200 bg-gray-50">
            <div class="relative">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar setor ou hospital..."
                    class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D]"
                >
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                @if($search)
                    <button 
                        wire:click="$set('search', '')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Content - Scrollable --}}
        <div class="flex-1 overflow-y-auto p-3 sm:p-4 bg-white">
            @if(empty($this->filteredSectors))
                <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                    <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <p class="text-sm">Nenhum setor encontrado</p>
                    @if($search)
                        <button 
                            wire:click="$set('search', '')"
                            class="mt-2 text-[#004D9D] hover:underline text-xs"
                        >
                            Limpar busca
                        </button>
                    @endif
                </div>
            @else
                <div class="space-y-3">
                    @foreach($this->filteredSectors as $hospitalCode => $sectors)
                        @php
                            $selectedCount = collect($sectors)->filter(fn($s) => in_array($s['sector_code'], $selectedSectors))->count();
                            $hospitalSelectedCount = collect($sectors)->filter(fn($s) => in_array($s['sector_code'], $selectedSectors))->count();
                            $allSelected = $hospitalSelectedCount === count($sectors) && count($sectors) > 0;
                            $isExpanded = $selectedCount > 0 || $loop->first;
                        @endphp
                        
                        {{-- Hospital Card - Accordion --}}
                        <div class="border border-gray-200 rounded-xl overflow-hidden" wire:key="hospital-{{ $hospitalCode }}">
                            {{-- Hospital Header (Always Clickable) --}}
                            <button 
                                type="button"
                                @click="expandedHospitals['{{ $hospitalCode }}'] = !expandedHospitals['{{ $hospitalCode }}']"
                                class="w-full px-4 py-3 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-[#004D9D] text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                                        {{ substr($sectors[0]['hospital_name'], 0, 1) }}
                                    </div>
                                    <div class="text-left min-w-0">
                                        <h3 class="font-semibold text-gray-800 text-sm truncate">{{ $sectors[0]['hospital_name'] }}</h3>
                                        <p class="text-xs text-gray-500">{{ count($sectors) }} setores</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    @if($selectedCount > 0)
                                        <span class="bg-[#004D9D] text-white text-xs px-2 py-0.5 rounded-full">{{ $selectedCount }}</span>
                                    @endif
                                    <svg 
                                        class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                        :class="{ 'rotate-180': expandedHospitals['{{ $hospitalCode }}'] ?? {{ $isExpanded ? 'true' : 'false' }} }"
                                        fill="none" 
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </div>
                            </button>
                            
                            {{-- Sectors Content (Collapsible) --}}
                            <div 
                                x-show="expandedHospitals['{{ $hospitalCode }}'] ?? {{ $isExpanded ? 'true' : 'false' }}"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 max-h-0"
                                x-transition:enter-end="opacity-100 max-h-[1000px]"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 max-h-[1000px]"
                                x-transition:leave-end="opacity-0 max-h-0"
                                class="overflow-hidden"
                            >
                                <div class="p-3 border-t border-gray-100 bg-white">
                                    {{-- Select All Button --}}
                                    <div class="flex justify-between items-center mb-3">
                                        <span class="text-xs text-gray-500">Clique para selecionar/deselecionar</span>
                                        <button 
                                            type="button"
                                            wire:click="selectAllFromHospital('{{ $hospitalCode }}')"
                                            class="text-xs font-medium px-3 py-1.5 rounded-lg {{ $allSelected ? 'bg-gray-100 text-gray-600' : 'bg-[#004D9D]/10 text-[#004D9D]' }} hover:bg-opacity-80 transition-colors"
                                        >
                                            {{ $allSelected ? 'Desmarcar todos' : 'Selecionar todos' }}
                                        </button>
                                    </div>
                                    
                                    {{-- Sectors Grid --}}
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                        @foreach($sectors as $sector)
                                            @php
                                                $isSelected = in_array($sector['sector_code'], $selectedSectors);
                                            @endphp
                                            <button
                                                type="button"
                                                wire:key="sector-{{ $sector['sector_code'] }}"
                                                wire:click="toggleSector('{{ $sector['sector_code'] }}')"
                                                class="flex items-center gap-2 p-2.5 rounded-lg border text-left transition-all {{ $isSelected ? 'bg-blue-50 border-blue-400' : 'bg-white border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}"
                                            >
                                                <div class="w-4 h-4 rounded border flex items-center justify-center flex-shrink-0 {{ $isSelected ? 'bg-[#004D9D] border-[#004D9D]' : 'border-gray-300 bg-white' }}">
                                                    @if($isSelected)
                                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                                <span class="text-xs sm:text-sm text-gray-700 truncate">{{ $sector['sector_name'] }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="flex-shrink-0 px-4 sm:px-6 py-3 border-t border-gray-200 bg-gray-50">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-sm text-gray-600">
                    <span class="font-bold text-[#004D9D] text-lg">{{ $this->totalSelected }}</span>
                    <span class="ml-1">setor(es) selecionado(s)</span>
                </div>
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button 
                        wire:click="skipForNow"
                        type="button"
                        class="flex-1 sm:flex-none px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        Depois
                    </button>
                    <button 
                        wire:click="savePreferences"
                        type="button"
                        @disabled(empty($selectedSectors))
                        class="flex-1 sm:flex-none px-5 py-2 text-sm font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] disabled:bg-gray-300 disabled:cursor-not-allowed rounded-lg transition-colors shadow"
                    >
                        Salvar
                    </button>
                </div>
            </div>
            
            @if(empty($selectedSectors))
                <p class="text-xs text-amber-600 text-center mt-2">
                    <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Selecione pelo menos um setor
                </p>
            @endif
        </div>
    </div>
</div>
@endif
</div>
