@php
    $hospitalColors = [
        '1' => '#8C3134', '8' => '#42909A', '2' => '#A96538', '6' => '#3E6B35',
        '4' => '#4586B4', '3' => '#9574A1', '25' => '#CE7D74', '7' => '#563174', '18' => '#073772',
    ];
@endphp

<div
    x-data="{
        show: @js($autoOpen ?? false),
        mandatory: @js($mandatory ?? false),
        search: '',
        selected: @js($initialSelected ?? []),
        expanded: {},
        saving: false,
        allSectors: @js($sectorsData ?? []),
        colors: @js($hospitalColors),

        get filtered() {
            const q = this.search.toLowerCase();
            if (!q) return this.allSectors;
            const result = {};
            Object.entries(this.allSectors).forEach(([code, sectors]) => {
                const match = sectors.filter(s =>
                    s.sector_name.toLowerCase().includes(q) ||
                    s.hospital_name.toLowerCase().includes(q)
                );
                if (match.length) result[code] = match;
            });
            return result;
        },

        get hospitalList() {
            return Object.entries(this.filtered).map(([code, sectors]) => {
                const selCount = sectors.filter(s => this.selected.includes(s.sector_code)).length;
                return {
                    code,
                    name: sectors[0]?.hospital_name ?? 'Hospital',
                    color: this.colors[code] ?? '#004D9D',
                    icon: (sectors[0]?.hospital_name ?? 'H').charAt(0),
                    sectors,
                    selectedCount: selCount,
                    allSelected: selCount === sectors.length && sectors.length > 0,
                    isExpanded: this.expanded[code] ?? (selCount > 0),
                };
            });
        },

        toggle(code) {
            const idx = this.selected.indexOf(code);
            if (idx >= 0) this.selected.splice(idx, 1);
            else this.selected.push(code);
        },

        toggleAll(hospitalCode, sectorCodes, allSelected) {
            if (allSelected) {
                this.selected = this.selected.filter(c => !sectorCodes.includes(c));
            } else {
                sectorCodes.forEach(c => { if (!this.selected.includes(c)) this.selected.push(c); });
            }
        },

        async save() {
            if (!this.selected.length || this.saving) return;
            this.saving = true;
            try {
                const res = await fetch('{{ route('sector-preferences.save') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ sectors: this.selected }),
                });
                if (res.ok) {
                    this.show = false;
                    showToast('Setores configurados com sucesso!', 'success');
                    window.dispatchEvent(new CustomEvent('sectors-configured'));
                }
            } finally {
                this.saving = false;
            }
        },

        init() {
            if (this.show) document.body.style.overflow = 'hidden';
            this.$watch('show', v => {
                document.body.style.overflow = v ? 'hidden' : '';
            });
        }
    }"
    x-init="init()"
    x-cloak
>
    <div class="fixed inset-0 z-[9998]"
        x-show="show"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        style="display:none">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="if (!mandatory) show = false"></div>

        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl lg:w-[800px] lg:h-[90vh] shadow-2xl"
                @click.stop
                x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <x-heroicon-o-building-office-2 class="w-5 h-5 text-white flex-shrink-0" />
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-white leading-tight" x-text="mandatory ? 'Configure seu acesso' : 'Selecione seus setores'"></h2>
                            <p class="text-white/70 text-xs leading-tight" x-text="mandatory ? 'Necessário para usar o sistema' : 'Escolha os setores que você acompanha no SBAR'"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <div class="flex items-center gap-1.5 bg-white/15 px-3 py-1.5 rounded-lg">
                            <span class="text-white font-bold text-lg" x-text="selected.length"></span>
                            <span class="text-white/70 text-xs">selec.</span>
                        </div>
                        <button x-show="!mandatory" @click="show = false"
                                class="p-1.5 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {{-- Busca --}}
                <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex-shrink-0">
                    <div class="relative">
                        <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        <input type="search" x-model="search"
                            placeholder="Buscar setor ou hospital..."
                            class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D] bg-white">
                        <button x-show="search" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {{-- Conteúdo --}}
                <div class="flex-1 overflow-y-auto min-h-0 bg-gray-50 p-3" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">

                    {{-- Sem resultado --}}
                    <template x-if="hospitalList.length === 0">
                        <div class="flex flex-col items-center justify-center h-48 text-gray-400 gap-3">
                            <x-heroicon-o-magnifying-glass class="w-10 h-10" />
                            <p class="text-sm">Nenhum setor encontrado</p>
                            <button x-show="search" @click="search = ''" class="text-[#004D9D] hover:underline text-xs">Limpar busca</button>
                        </div>
                    </template>

                    <div class="space-y-3">
                        <template x-for="hospital in hospitalList" :key="hospital.code">
                            <div class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm"
                                :style="'border-top: 4px solid ' + hospital.color">

                                {{-- Hospital header --}}
                                <button type="button"
                                    @click="expanded[hospital.code] = !hospital.isExpanded; hospital.isExpanded = !hospital.isExpanded"
                                    class="w-full px-4 py-3 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-lg text-white flex items-center justify-center text-sm font-bold flex-shrink-0 shadow-sm"
                                            :style="'background: ' + hospital.color"
                                            x-text="hospital.icon"></div>
                                        <div class="text-left min-w-0">
                                            <h3 class="font-semibold text-gray-800 text-sm truncate" x-text="hospital.name"></h3>
                                            <p class="text-xs text-gray-500" x-text="hospital.sectors.length + ' setores'"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <template x-if="hospital.selectedCount > 0">
                                            <span class="text-white text-xs px-2 py-0.5 rounded-full shadow-sm"
                                                :style="'background: ' + hospital.color"
                                                x-text="hospital.selectedCount"></span>
                                        </template>
                                        <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                            :class="{ 'rotate-180': hospital.isExpanded }" />
                                    </div>
                                </button>

                                {{-- Setores --}}
                                <div x-show="hospital.isExpanded"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                                    <div class="p-3 border-t border-gray-100">
                                        <div class="flex justify-between items-center mb-3">
                                            <span class="text-xs text-gray-500">Clique para selecionar/deselecionar</span>
                                            <button type="button"
                                                @click="toggleAll(hospital.code, hospital.sectors.map(s => s.sector_code), hospital.allSelected)"
                                                class="text-xs font-medium px-3 py-2 rounded-lg transition-colors"
                                                :style="hospital.allSelected
                                                    ? 'background: #f3f4f6; color: #4b5563'
                                                    : 'background: ' + hospital.color + '20; color: ' + hospital.color"
                                                x-text="hospital.allSelected ? 'Desmarcar todos' : 'Selecionar todos'"></button>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <template x-for="sector in hospital.sectors" :key="sector.sector_code">
                                                <button type="button"
                                                    @click="toggle(sector.sector_code)"
                                                    class="flex items-center gap-2 p-3.5 rounded-lg border text-left transition-all"
                                                    :class="selected.includes(sector.sector_code) ? 'bg-blue-50' : 'bg-white hover:bg-gray-50'"
                                                    :style="'border-color: ' + (selected.includes(sector.sector_code) ? hospital.color : '#e5e7eb')">
                                                    <div class="w-4 h-4 rounded border flex items-center justify-center flex-shrink-0 transition-all"
                                                        :style="selected.includes(sector.sector_code)
                                                            ? 'background: ' + hospital.color + '; border-color: ' + hospital.color
                                                            : 'border-color: #d1d5db; background: #fff'">
                                                        <template x-if="selected.includes(sector.sector_code)">
                                                            <x-heroicon-s-check class="w-3 h-3 text-white" />
                                                        </template>
                                                    </div>
                                                    <span class="text-sm text-gray-700 truncate" x-text="sector.sector_name"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-100 flex-shrink-0">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="text-sm text-gray-600">
                            <span class="font-bold text-[#004D9D] text-lg" x-text="selected.length"></span>
                            <span class="ml-1">setor(es) selecionado(s)</span>
                        </div>
                        <div class="flex items-center gap-3 w-full sm:w-auto">
                            <button x-show="!mandatory" type="button" @click="show = false"
                                class="flex-1 sm:flex-none px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded-lg transition-colors">
                                Depois
                            </button>
                            <button type="button" @click="save()"
                                :disabled="!selected.length || saving"
                                class="flex-1 sm:flex-none px-5 py-2 text-sm font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] disabled:bg-gray-300 disabled:cursor-not-allowed rounded-lg transition-colors shadow">
                                <span x-show="!saving" x-text="mandatory ? 'Confirmar e continuar' : 'Salvar'"></span>
                                <span x-show="saving">Salvando...</span>
                            </button>
                        </div>
                    </div>
                    <p x-show="!selected.length" class="text-xs text-amber-600 text-center mt-2">
                        <x-heroicon-o-exclamation-triangle class="w-3 h-3 inline mr-1" />
                        Selecione pelo menos um setor
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>
