<div class="flex flex-col h-full" x-data="{ search: '' }" @nurse-beds-saved.window="window.location.reload()">

    {{-- Scrollable content --}}
    <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0">

        {{-- Header info --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-800">
                <i class="fas fa-bed text-[#004D9D] mr-1"></i> Meus Leitos — Passagem de Plantão
            </h3>
            <p class="text-xs text-gray-500 mt-0.5">Selecione os leitos que você cobre durante a passagem.</p>
        </div>

        @if($saved)
            <div class="flex items-center gap-2 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                <i class="fas fa-check-circle"></i> Leitos salvos com sucesso.
            </div>
        @endif

        {{-- Carregar apenas meus leitos --}}
        <div class="rounded-xl border {{ $onlyAssignedBeds ? 'border-[#004D9D]/30 bg-blue-50' : 'border-gray-200 bg-white' }} p-3 transition-colors duration-200">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold {{ $onlyAssignedBeds ? 'text-[#004D9D]' : 'text-gray-700' }}">
                        Carregar apenas meus leitos
                    </p>
                    <p class="text-[11px] text-gray-400 mt-0.5 leading-relaxed">
                        Quando ativo, o SBAR mostra somente os leitos configurados abaixo.
                    </p>
                </div>
                <button wire:click="toggleOnlyAssignedBeds"
                        type="button"
                        role="switch"
                        aria-checked="{{ $onlyAssignedBeds ? 'true' : 'false' }}"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-[#004D9D] focus-visible:ring-offset-2
                            {{ $onlyAssignedBeds ? 'bg-[#004D9D] border-[#004D9D]' : 'bg-gray-200 border-gray-200' }}">
                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                        {{ $onlyAssignedBeds ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>
        </div>

        {{-- Chips: leitos selecionados --}}
        @if(!empty($chips))
            <div>
                <p class="text-xs text-gray-500 font-medium mb-2">
                    {{ count($chips) }} leito(s) selecionado(s)
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach($chips as $chip)
                        <span class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1.5 bg-[#004D9D] text-white text-xs font-medium rounded-full">
                            {{ $chip['label'] }}
                            <button wire:click="toggleBed({{ $chip['sector_id'] }}, '{{ $chip['bed'] }}')"
                                    type="button"
                                    class="w-5 h-5 flex items-center justify-center rounded-full hover:bg-white/20 transition-colors flex-shrink-0"
                                    title="Remover leito">
                                <i class="fas fa-times text-[10px]"></i>
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
                   class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D]">
        </div>

        @if(empty($hospitals))
            <p class="text-sm text-gray-400 italic">Nenhum setor configurado em "Meus Setores".</p>
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
                            class="w-full flex items-center justify-between px-4 py-4 bg-gray-50 hover:bg-gray-100 transition-colors text-left">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="fas fa-chevron-right text-gray-400 text-xs transition-transform duration-200 flex-shrink-0"
                               :class="open ? 'rotate-90' : ''"></i>
                            <span class="text-sm font-bold text-gray-700">{{ $hospital['hospital_name'] }}</span>
                            @php $totalSelected = collect($hospital['sectors'])->sum(fn($s) => count($selectedBeds[$s['sector_id']] ?? [])); @endphp
                            @if($totalSelected > 0)
                                <span class="flex-shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-[#004D9D]/10 text-[#004D9D]">
                                    {{ $totalSelected }} selecionado(s)
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
                                <div class="flex items-center gap-2 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                                    <button type="button" @click="sOpen = !sOpen; if(search) sOpen = true"
                                            class="flex items-center gap-2 flex-1 min-w-0 text-left">
                                        <i class="fas fa-chevron-right text-gray-300 text-xs transition-transform duration-150 flex-shrink-0"
                                           :class="sOpen ? 'rotate-90' : ''"></i>
                                        <span class="text-sm text-gray-600 truncate">{{ $sector['sector_name'] }}</span>
                                        <span class="flex-shrink-0 text-xs {{ $selectedCount > 0 ? 'text-[#004D9D] font-semibold' : 'text-gray-400' }}">
                                            {{ $selectedCount }}/{{ $totalBeds }}
                                        </span>
                                    </button>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <button wire:click="selectAllBeds({{ $sectorId }})" type="button"
                                                class="text-xs font-medium text-[#004D9D] px-3 py-2 rounded-lg bg-[#004D9D]/8 hover:bg-[#004D9D]/15 transition-colors">Todos</button>
                                        <button wire:click="clearSectorBeds({{ $sectorId }})" type="button"
                                                class="text-xs font-medium text-gray-500 px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors">Limpar</button>
                                    </div>
                                </div>

                                {{-- Beds grid --}}
                                <div x-show="sOpen || search"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     class="px-4 pb-4">
                                    @if(empty($sector['beds']))
                                        <p class="text-xs text-gray-400 italic">Sem leitos.</p>
                                    @else
                                        <div class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-7 gap-2">
                                            @foreach($sector['beds'] as $bed)
                                                @php $isSelected = in_array($bed, $selectedBeds[$sectorId] ?? [], true); @endphp
                                                <button wire:click="toggleBed({{ $sectorId }}, '{{ $bed }}')"
                                                        type="button"
                                                        x-show="!search || '{{ strtolower($bed) }}'.includes(search.toLowerCase()) || '{{ strtolower($sector['sector_name']) }}'.includes(search.toLowerCase()) || '{{ strtolower($hospital['hospital_name']) }}'.includes(search.toLowerCase())"
                                                        class="py-3.5 rounded-xl text-sm font-semibold border-2 transition-all truncate
                                                            {{ $isSelected
                                                                ? 'bg-[#004D9D] text-white border-[#004D9D] shadow-sm'
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

    {{-- Sticky save footer --}}
    <div class="flex-shrink-0 border-t border-gray-200 bg-white px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            @if($saved)
                <span class="flex items-center gap-1.5 text-xs text-emerald-600">
                    <i class="fas fa-check-circle"></i> Salvo
                </span>
            @else
                <span class="text-xs text-gray-400">Toque em Salvar para confirmar</span>
            @endif
            <button wire:click="save" type="button"
                    class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] rounded-xl transition-colors shadow-sm">
                <i class="fas fa-save"></i>
                Salvar leitos
            </button>
        </div>
    </div>

</div>
