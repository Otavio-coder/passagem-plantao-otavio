<div
    x-data="{
        show: $wire.entangle('isOpen'),
        activeBed: null,
        search: '',
        sortBy: 'leito',
        sortDir: 'asc',

        init() {
            this.$watch('show', v => {
                document.body.style.overflow = v ? 'hidden' : '';
                if (!v) this.activeBed = null;
            });
        },

        setSort(field) {
            if (this.sortBy === field) {
                this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy  = field;
                this.sortDir = 'asc';
            }
        },

        get sortedBeds() {
            const beds = [...(this.$wire.beds || [])];
            const field = this.sortBy;
            const dir   = this.sortDir === 'asc' ? 1 : -1;
            beds.sort((a, b) => {
                const va = a[field] ?? '', vb = b[field] ?? '';
                if (field === 'total_mensagens' || field === 'internment_days') {
                    return ((Number(va) || 0) - (Number(vb) || 0)) * dir;
                }
                return String(va).localeCompare(String(vb), 'pt-BR', { numeric: true, sensitivity: 'base' }) * dir;
            });
            return beds;
        },

        get filteredBeds() {
            if (!this.search.trim()) return this.sortedBeds;
            const s = this.search.toLowerCase();
            return this.sortedBeds.filter(b =>
                b.leito.toLowerCase().includes(s) ||
                b.nome_paciente.toLowerCase().includes(s) ||
                b.prontuario.toLowerCase().includes(s)
            );
        },

        sortIcon(field) {
            if (this.sortBy !== field) return '↕';
            return this.sortDir === 'asc' ? '↑' : '↓';
        }
    }"
    x-show="show"
    x-cloak
    style="display:none"
>
    {{-- ─── Overlay ───────────────────────────────────────────────────── --}}
    <div
        class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
        x-show="show"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.close()"></div>

        {{-- ─── Panel ──────────────────────────────────────────────────── --}}
        <div
            class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl lg:w-[740px] lg:h-[86vh] shadow-2xl"
            @click.stop
            x-show="show"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
        >

            {{-- ─── Header ──────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                    </svg>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-white leading-tight">Avaliações do Turno</h2>
                        <p class="text-white/70 text-xs leading-tight truncate">{{ $sectorName }}</p>
                    </div>
                    @if($totalMessages > 0)
                        <span class="px-2.5 py-0.5 bg-white text-[#004D9D] text-xs font-bold rounded-full flex-shrink-0">{{ $totalMessages }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button wire:click="loadEvaluations" wire:loading.attr="disabled" title="Atualizar"
                            class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="loadEvaluations" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>
                    <a href="{{ route('sbar.evaluations.shift', ['sector_id' => $sectorId ?? 0]) }}"
                       target="_blank" title="Abrir histórico completo"
                       class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                    <button wire:click="close" title="Fechar"
                            class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ─── Filter Bar ──────────────────────────────────────────── --}}
            <div class="px-4 py-2.5 border-b border-gray-200 bg-gray-50 flex-shrink-0 flex flex-wrap items-center gap-2">
                {{-- Date From --}}
                <div class="flex items-center gap-1.5">
                    <label class="text-xs text-gray-500 whitespace-nowrap">De</label>
                    <input type="date" wire:model.live.debounce.400ms="dateFrom"
                           class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0071B9]/30 focus:border-[#0071B9] bg-white">
                </div>

                {{-- Date To --}}
                <div class="flex items-center gap-1.5">
                    <label class="text-xs text-gray-500 whitespace-nowrap">Até</label>
                    <input type="date" wire:model.live.debounce.400ms="dateTo"
                           class="px-2 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0071B9]/30 focus:border-[#0071B9] bg-white">
                </div>

                {{-- Search --}}
                <div class="relative flex-1 min-w-32">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" x-model="search" placeholder="Leito, nome, prontuário..."
                           class="w-full pl-8 pr-7 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0071B9]/30 focus:border-[#0071B9] bg-white">
                    <button x-show="search" @click="search = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- ─── Sort Bar ────────────────────────────────────────────── --}}
            <div class="px-4 py-1.5 border-b border-gray-100 bg-gray-50 flex-shrink-0 flex items-center gap-1 overflow-x-auto">
                <span class="text-[10px] text-gray-400 whitespace-nowrap mr-1">Ordenar:</span>
                @foreach([
                    ['leito',           'Leito'],
                    ['nome_paciente',   'Paciente'],
                    ['internment_days', 'Dias'],
                    ['total_mensagens', 'Anotações'],
                ] as [$field, $label])
                    <button
                        @click="setSort('{{ $field }}')"
                        :class="sortBy === '{{ $field }}'
                            ? 'bg-[#004D9D] text-white border-[#004D9D]'
                            : 'bg-white text-gray-600 border-gray-300 hover:border-[#0071B9] hover:text-[#0071B9]'"
                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-medium border rounded-full transition-colors whitespace-nowrap flex-shrink-0"
                    >
                        {{ $label }}
                        <span x-show="sortBy === '{{ $field }}'" class="font-bold" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                    </button>
                @endforeach
            </div>

            {{-- ─── Content (wire:ignore so Livewire doesn't morph Alpine DOM) ── --}}
            <div class="flex-1 overflow-y-auto min-h-0 relative" wire:ignore>

                {{-- Loading --}}
                <div x-show="$wire.loading" class="flex flex-col items-center justify-center h-full py-16">
                    <div class="w-10 h-10 border-4 border-[#004D9D]/20 border-t-[#004D9D] rounded-full animate-spin mb-3"></div>
                    <p class="text-sm text-gray-500">Carregando avaliações...</p>
                </div>

                {{-- Empty --}}
                <div x-show="!$wire.loading && filteredBeds.length === 0" class="flex flex-col items-center justify-center h-full text-center px-6 py-16">
                    <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center mb-3">
                        <svg class="w-7 h-7 text-[#0071B9]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <p class="text-base font-semibold text-gray-700">Sem anotações</p>
                    <p class="text-sm text-gray-400 mt-1">Nenhuma mensagem no período selecionado.</p>
                    <button x-show="search" @click="search = ''"
                            class="mt-3 text-xs text-[#0071B9] hover:underline">
                        Limpar busca
                    </button>
                </div>

                {{-- Beds list --}}
                <div x-show="!$wire.loading && filteredBeds.length > 0" class="divide-y divide-gray-100">
                    <template x-for="bed in filteredBeds" :key="bed.atendimento">
                        <button
                            @click="activeBed = bed"
                            class="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-gray-50 transition-colors"
                        >
                            {{-- Leito badge --}}
                            <div class="w-11 h-10 flex-shrink-0 bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold text-xs shadow-sm">
                                <span x-text="bed.leito"></span>
                            </div>

                            {{-- Patient info --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-semibold text-gray-900 text-sm truncate" x-text="bed.nome_paciente"></span>
                                    <svg x-show="bed.has_pinned" class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                    </svg>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-gray-400" x-text="'Pront. ' + bed.prontuario"></span>
                                    <span x-show="bed.internment_days !== null" class="text-[10px] text-gray-400">
                                        · <span x-text="bed.internment_days + 'd internado'"></span>
                                    </span>
                                    <span x-show="bed.dt_alta_formatted" class="text-[10px] text-amber-600 font-medium">
                                        · Alta: <span x-text="bed.dt_alta_formatted"></span>
                                    </span>
                                </div>
                            </div>

                            {{-- Message count + chevron --}}
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="bg-[#E8F4FD] text-[#004D9D] text-xs font-semibold px-2.5 py-1 rounded-full"
                                      x-text="bed.total_mensagens + (bed.total_mensagens === 1 ? ' msg' : ' msgs')"></span>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </button>
                    </template>
                </div>

                {{-- ─── Message Detail Sub-Panel ────────────────────────── --}}
                <div
                    class="absolute inset-0 bg-white flex flex-col transition-transform duration-250 ease-out"
                    :class="activeBed ? 'translate-x-0' : 'translate-x-full'"
                    x-show="activeBed !== null"
                    x-cloak
                    style="display:none"
                >
                    {{-- Sub-panel header --}}
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 flex-shrink-0 bg-white">
                        <button @click="activeBed = null"
                                class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="w-10 h-9 flex-shrink-0 bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold text-xs shadow-sm"
                             x-text="activeBed?.leito"></div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-gray-800 truncate text-sm" x-text="activeBed?.nome_paciente"></div>
                            <div class="text-xs text-gray-400 flex items-center gap-1.5">
                                <span x-text="'Pront. ' + (activeBed?.prontuario ?? '')"></span>
                                <span x-show="activeBed?.internment_days !== null">
                                    · <span x-text="activeBed?.internment_days + 'd internado'"></span>
                                </span>
                            </div>
                        </div>
                        <span class="bg-[#E8F4FD] text-[#004D9D] text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0"
                              x-text="(activeBed?.total_mensagens ?? 0) + ((activeBed?.total_mensagens ?? 0) === 1 ? ' msg' : ' msgs')"></span>
                    </div>

                    {{-- Messages list --}}
                    <div class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50">
                        <template x-for="msg in (activeBed?.mensagens ?? [])" :key="msg.id">
                            <div class="bg-white rounded-xl shadow-sm overflow-hidden"
                                 :class="msg.is_pinned ? 'border-l-[3px] border-l-amber-400' : 'border border-gray-200'">
                                {{-- Message header --}}
                                <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
                                    <template x-if="$wire.userPhotos[msg.user_name]">
                                        <img :src="'data:image/jpeg;base64,' + $wire.userPhotos[msg.user_name]"
                                             :alt="msg.user_name"
                                             class="w-7 h-7 rounded-full object-cover flex-shrink-0">
                                    </template>
                                    <template x-if="!$wire.userPhotos[msg.user_name]">
                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#004D9D] to-[#0071B9] flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                                             x-text="msg.user_initials"></div>
                                    </template>
                                    <span class="text-xs font-medium text-gray-800 truncate flex-1 min-w-0" x-text="msg.user_name"></span>
                                    <span class="px-1.5 py-0.5 bg-[#E8F4FD] text-[#004D9D] text-[10px] rounded font-medium flex-shrink-0" x-text="msg.turno"></span>
                                    <span class="text-[10px] text-gray-400 flex-shrink-0 tabular-nums" x-text="msg.timestamp"></span>
                                    <svg x-show="msg.is_pinned" class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                    </svg>
                                </div>
                                {{-- Message content --}}
                                <div class="px-3 py-2.5 text-sm text-gray-700 leading-relaxed" x-html="msg.content"></div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>{{-- end wire:ignore --}}

            {{-- ─── Footer ──────────────────────────────────────────────── --}}
            <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 flex-shrink-0 flex items-center justify-between"
                 x-show="!$wire.loading && $wire.beds.length > 0">
                <p class="text-xs text-gray-400 truncate">
                    <span x-text="filteredBeds.length + ' leito' + (filteredBeds.length !== 1 ? 's' : '')"></span>
                    <span x-show="search"> (filtrado)</span>
                </p>
                <span class="text-xs text-gray-500 font-medium"
                      x-text="$wire.totalMessages + (parseInt($wire.totalMessages) === 1 ? ' mensagem' : ' mensagens')"></span>
            </div>

        </div>{{-- end panel --}}
    </div>{{-- end overlay --}}
</div>
