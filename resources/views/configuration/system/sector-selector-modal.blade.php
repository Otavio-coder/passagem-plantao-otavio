<div x-data="{ show: @entangle('show'), expandedHospitals: {} }" x-init="$watch('show', v => { if(v) setTimeout(() => document.body.style.overflow = 'hidden', 50); else document.body.style.overflow = ''; })" x-show="show" x-cloak style="display: none;" wire:key="sector-selector-modal">
    <div class="fixed inset-0 z-[9998]" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.skipForNow()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl lg:w-[800px] lg:h-[85vh] shadow-2xl" @click.stop x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-white leading-tight">Configuração de Acesso</h2>
                            <p class="text-white/70 text-xs leading-tight">Selecione os setores que deseja acompanhar</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 bg-white/15 px-3 py-1.5 rounded-lg">
                        <span class="text-white font-bold text-lg">{{ $this->totalSelected }}</span>
                        <span class="text-white/70 text-xs">selec.</span>
                    </div>
                </div>

                {{-- Search Bar --}}
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex-shrink-0">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar setor ou hospital..." class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D] bg-white">
                        @if($search)
                            <button wire:click="$set('search', '')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0 bg-gray-50 p-3" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">
                    @if(empty($this->filteredSectors))
                        <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <p class="text-sm">Nenhum setor encontrado</p>
                            @if($search)
                                <button wire:click="$set('search', '')" class="mt-2 text-[#004D9D] hover:underline text-xs">Limpar busca</button>
                            @endif
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($this->hospitalSections as $hospital)
                                <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm" wire:key="hospital-{{ $hospital['code'] }}" style="border-top: 4px solid {{ $hospital['color'] }};">
                                    <button type="button" @click="expandedHospitals['{{ $hospital['code'] }}'] = !expandedHospitals['{{ $hospital['code'] }}']" class="w-full px-4 py-3 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-lg text-white flex items-center justify-center text-sm font-bold flex-shrink-0 shadow-sm" style="background: {{ $hospital['color'] }};">{{ $hospital['icon_label'] }}</div>
                                            <div class="text-left min-w-0">
                                                <h3 class="font-semibold text-gray-800 text-sm truncate">{{ $hospital['name'] }}</h3>
                                                <p class="text-xs text-gray-500">{{ $hospital['total_sectors'] }} setores</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            @if($hospital['selected_count'] > 0)
                                                <span class="text-white text-xs px-2 py-0.5 rounded-full shadow-sm" style="background: {{ $hospital['color'] }};">{{ $hospital['selected_count'] }}</span>
                                            @endif
                                            <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': expandedHospitals['{{ $hospital['code'] }}'] ?? {{ $hospital['is_expanded'] ? 'true' : 'false' }} }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </button>

                                    <div x-show="expandedHospitals['{{ $hospital['code'] }}'] ?? {{ $hospital['is_expanded'] ? 'true' : 'false' }}" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-[1000px]" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 max-h-[1000px]" x-transition:leave-end="opacity-0 max-h-0" class="overflow-hidden">
                                        <div class="p-3 border-t border-gray-100">
                                            <div class="flex justify-between items-center mb-3">
                                                <span class="text-xs text-gray-500">Clique para selecionar/deselecionar</span>
                                                <button type="button" wire:click="selectAllFromHospital('{{ $hospital['code'] }}')" class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors" style="{{ $hospital['all_selected'] ? 'background: #f3f4f6; color: #4b5563;' : 'background: ' . $hospital['color'] . '15; color: ' . $hospital['color'] . ';' }}">{{ $hospital['all_selected'] ? 'Desmarcar todos' : 'Selecionar todos' }}</button>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                @foreach($hospital['sectors'] as $sector)
                                                    <button type="button" wire:key="sector-{{ $sector['code'] }}" wire:click="toggleSector('{{ $sector['code'] }}')" class="flex items-center gap-2 p-2.5 rounded-lg border text-left transition-all {{ $sector['is_selected'] ? 'bg-blue-50' : 'bg-white hover:bg-gray-50' }}" style="border-color: {{ $sector['is_selected'] ? $hospital['color'] : '#e5e7eb' }};">
                                                        <div class="w-4 h-4 rounded border flex items-center justify-center flex-shrink-0" style="{{ $sector['is_selected'] ? 'background: ' . $hospital['color'] . '; border-color: ' . $hospital['color'] . ';' : 'border-color: #d1d5db; background: #fff;' }}">
                                                            @if($sector['is_selected'])
                                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                            @endif
                                                        </div>
                                                        <span class="text-sm text-gray-700 truncate">{{ $sector['name'] }}</span>
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
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-100 flex-shrink-0">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="text-sm text-gray-600">
                            <span class="font-bold text-[#004D9D] text-lg">{{ $this->totalSelected }}</span>
                            <span class="ml-1">setor(es) selecionado(s)</span>
                        </div>
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button wire:click="skipForNow" type="button" class="flex-1 sm:flex-none px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded-lg transition-colors">Depois</button>
                            <button wire:click="savePreferences" type="button" @disabled(empty($selectedSectors)) class="flex-1 sm:flex-none px-5 py-2 text-sm font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] disabled:bg-gray-300 disabled:cursor-not-allowed rounded-lg transition-colors shadow">Salvar</button>
                        </div>
                    </div>
                    @if(empty($selectedSectors))
                        <p class="text-xs text-amber-600 text-center mt-2">
                            <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Selecione pelo menos um setor
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
