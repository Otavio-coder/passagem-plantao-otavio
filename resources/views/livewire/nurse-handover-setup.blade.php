<div class="space-y-3" x-data="{ search: '' }" @nurse-beds-saved.window="window.location.reload()">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-800">
                <i class="fas fa-bed text-[#004D9D] mr-1"></i> Meus Leitos — Passagem de Plantão
            </h3>
            <p class="text-xs text-gray-500 mt-0.5">Selecione os leitos que você cobre durante a passagem.</p>
        </div>
        <button wire:click="save" type="button"
                class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] rounded-lg transition-colors shadow-sm">
            <i class="fas fa-save"></i>
            <span class="hidden sm:inline">Salvar</span>
        </button>
    </div>

    @if($saved)
        <div class="flex items-center gap-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
            <i class="fas fa-check-circle"></i> Leitos salvos com sucesso.
        </div>
    @endif

    {{-- Chips: leitos selecionados --}}
    @if(!empty($chips))
        <div>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">
                {{ count($chips) }} leito(s) selecionado(s)
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach($chips as $chip)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#004D9D] text-white text-[10px] font-medium rounded-full">
                        {{ $chip['label'] }}
                        <button wire:click="toggleBed({{ $chip['sector_id'] }}, '{{ $chip['bed'] }}')"
                                type="button" class="hover:text-white/70 ml-0.5 leading-none">
                            <i class="fas fa-times text-[8px]"></i>
                        </button>
                    </span>
                @endforeach
            </div>
        </div>
        <hr class="border-gray-200">
    @endif

    {{-- Busca --}}
    <div class="relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input type="text" x-model="search" placeholder="Buscar hospital, setor ou leito..."
               class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D]">
    </div>

    @if(empty($hospitals))
        <p class="text-xs text-gray-400 italic">Nenhum setor configurado em "Meus Setores".</p>
    @endif

    {{-- Cascata: Hospital → Setor → Leitos --}}
    <div class="space-y-2">
        @foreach($hospitals as $hospital)
            <div x-data="{ open: false }"
                 x-show="!search || '{{ strtolower($hospital['hospital_name']) }}'.includes(search.toLowerCase()) ||
                    @foreach($hospital['sectors'] as $si => $sector)
                        '{{ strtolower($sector['sector_name']) }}'.includes(search.toLowerCase()){{ !$loop->last ? ' ||' : '' }}
                    @endforeach
                 "
                 class="border border-gray-200 rounded-xl overflow-hidden">

                {{-- Hospital header --}}
                <button type="button" @click="open = !open; if(search) open = true"
                        class="w-full flex items-center justify-between px-3 py-2.5 bg-gray-50 hover:bg-gray-100 transition-colors text-left">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fas fa-chevron-right text-gray-400 text-[10px] transition-transform duration-200 flex-shrink-0"
                           :class="open ? 'rotate-90' : ''"></i>
                        <span class="text-xs font-bold text-gray-700">{{ $hospital['hospital_name'] }}</span>
                        @php $totalSelected = collect($hospital['sectors'])->sum(fn($s) => count($selectedBeds[$s['sector_id']] ?? [])); @endphp
                        @if($totalSelected > 0)
                            <span class="flex-shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-[#004D9D]/10 text-[#004D9D]">
                                {{ $totalSelected }}
                            </span>
                        @endif
                    </div>
                </button>

                {{-- Sectors --}}
                <div x-show="open || search"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="border-t border-gray-100 divide-y divide-gray-100">

                    @foreach($hospital['sectors'] as $sector)
                        @php
                            $sectorId = $sector['sector_id'];
                            $selectedCount = count($selectedBeds[$sectorId] ?? []);
                            $totalBeds = count($sector['beds']);
                        @endphp
                        <div x-data="{ sOpen: false }"
                             x-show="!search || '{{ strtolower($sector['sector_name']) }}'.includes(search.toLowerCase()) || '{{ strtolower($hospital['hospital_name']) }}'.includes(search.toLowerCase())"
                             class="bg-white">

                            {{-- Sector row --}}
                            <div class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 transition-colors">
                                <button type="button" @click="sOpen = !sOpen; if(search) sOpen = true"
                                        class="flex items-center gap-2 flex-1 min-w-0 text-left">
                                    <i class="fas fa-chevron-right text-gray-300 text-[9px] transition-transform duration-150 flex-shrink-0"
                                       :class="sOpen ? 'rotate-90' : ''"></i>
                                    <span class="text-xs text-gray-600 truncate">{{ $sector['sector_name'] }}</span>
                                    <span class="flex-shrink-0 text-[10px] {{ $selectedCount > 0 ? 'text-[#004D9D] font-semibold' : 'text-gray-400' }}">
                                        {{ $selectedCount }}/{{ $totalBeds }}
                                    </span>
                                </button>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <button wire:click="selectAllBeds({{ $sectorId }})" type="button"
                                            class="text-[10px] text-[#004D9D] hover:underline">Todos</button>
                                    <span class="text-gray-200 text-[10px]">|</span>
                                    <button wire:click="clearSectorBeds({{ $sectorId }})" type="button"
                                            class="text-[10px] text-gray-400 hover:underline">Limpar</button>
                                </div>
                            </div>

                            {{-- Beds grid --}}
                            <div x-show="sOpen || search"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 class="px-3 pb-2.5">
                                @if(empty($sector['beds']))
                                    <p class="text-[10px] text-gray-400 italic">Sem leitos.</p>
                                @else
                                    <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-1">
                                        @foreach($sector['beds'] as $bed)
                                            @php $isSelected = in_array($bed, $selectedBeds[$sectorId] ?? [], true); @endphp
                                            <button wire:click="toggleBed({{ $sectorId }}, '{{ $bed }}')"
                                                    type="button"
                                                    x-show="!search || '{{ strtolower($bed) }}'.includes(search.toLowerCase()) || '{{ strtolower($sector['sector_name']) }}'.includes(search.toLowerCase()) || '{{ strtolower($hospital['hospital_name']) }}'.includes(search.toLowerCase())"
                                                    class="py-1 rounded-md text-[10px] font-semibold border transition-colors truncate
                                                        {{ $isSelected
                                                            ? 'bg-[#004D9D] text-white border-[#004D9D]'
                                                            : 'bg-white text-gray-500 border-gray-200 hover:border-[#004D9D] hover:text-[#004D9D]' }}">
                                                {{ $bed }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

</div>
