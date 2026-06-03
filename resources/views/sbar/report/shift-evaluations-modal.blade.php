<div
    x-data="{
        show: $wire.entangle('isOpen'),
        activeTab: 'current',
        search: '',

        get shifts() { return this.$wire.shiftsMeta || []; },
        get beds()   { return this.$wire.beds || []; },
        get photos() { return this.$wire.photos || {}; },

        photo(userId) { return userId ? (this.photos[userId] || '') : ''; },

        get filteredBeds() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.beds;
            return this.beds.filter(b =>
                b.leito.toLowerCase().includes(q) ||
                b.nome_paciente.toLowerCase().includes(q) ||
                (b.prontuario || '').toLowerCase().includes(q)
            );
        },

        msgs(bed)        { return bed.mensagens?.[this.activeTab] || []; },
        total(bed)       { return bed.totais?.[this.activeTab] || 0; },
        hasPinned(bed)   { return bed.has_pinned?.[this.activeTab] || false; },

        totalForTab(key) {
            return this.beds.reduce((s, b) => s + (b.totais?.[key] || 0), 0);
        },

        totalMsgs() { return this.totalForTab(this.activeTab); },

        shiftMeta(key) {
            return this.shifts.find(s => s.key === key) || {};
        },

        init() {
            this.$watch('show', v => {
                document.body.style.overflow = v ? 'hidden' : '';
                if (!v) { this.search = ''; this.activeTab = 'current'; }
            });
        }
    }"
    x-show="show"
    x-cloak
    @keydown.escape.window="show && $wire.close()"
    style="display:none"
>
    <div class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
        x-show="show"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.close()"></div>

        <div class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl lg:w-[780px] lg:h-[90vh] shadow-2xl"
            @click.stop
            x-show="show"
            x-transition:enter="transition ease-out duration-250"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            {{-- ── Header ────────────────────────────────────────────────── --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-white flex-shrink-0" />
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-white leading-tight">Avaliações do Turno</h2>
                        <p class="text-white/70 text-xs leading-tight truncate">{{ $sectorName }}</p>
                    </div>
                    <span x-show="totalMsgs() > 0"
                        class="px-2.5 py-0.5 bg-white text-[#004D9D] text-xs font-bold rounded-full flex-shrink-0"
                        x-text="totalMsgs() + (totalMsgs() === 1 ? ' msg' : ' msgs')"></span>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button wire:click="loadEvaluations" wire:loading.attr="disabled" title="Atualizar"
                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="loadEvaluations" />
                    </button>
                    <button wire:click="close" title="Fechar"
                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                    </button>
                </div>
            </div>

            {{-- ── Abas de turno (Alpine, sem round-trip) ─────────────────── --}}
            <div class="flex-shrink-0 border-b border-gray-200 bg-white">
                <div class="flex">
                    <template x-for="shift in shifts" :key="shift.key">
                        <button
                            @click="activeTab = shift.key"
                            class="flex-1 px-2 py-2.5 text-xs sm:text-sm font-medium transition-colors border-b-2 flex flex-col items-center gap-0.5"
                            :class="activeTab === shift.key
                                ? 'border-[#004D9D] text-[#004D9D] bg-blue-50/50'
                                : 'border-transparent text-gray-500 hover:text-gray-700'">
                            <span class="font-semibold" x-text="shift.label"></span>
                            <span class="text-[10px] opacity-60 leading-tight text-center" x-text="shift.date_label + ' · ' + shift.time_range"></span>
                            <span x-show="totalForTab(shift.key) > 0"
                                class="mt-0.5 px-1.5 py-0 rounded-full text-[10px] font-bold"
                                :class="activeTab === shift.key ? 'bg-[#004D9D] text-white' : 'bg-gray-200 text-gray-600'"
                                x-text="totalForTab(shift.key) + ' msg'"></span>
                        </button>
                    </template>
                </div>

                {{-- Busca --}}
                <div class="px-3 py-2">
                    <div class="relative">
                        <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                        <input type="search" x-model="search"
                            placeholder="Buscar por leito, paciente ou prontuário..."
                            class="w-full pl-9 pr-8 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0071B9]/30 focus:border-[#0071B9] bg-gray-50 placeholder-gray-400">
                        <button x-show="search" @click="search = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── Conteúdo ─────────────────────────────────────────────── --}}
            <div class="flex-1 overflow-y-auto min-h-0" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">

                {{-- Loading --}}
                @if($loading)
                    <div class="flex flex-col items-center justify-center h-full gap-3">
                        <div class="w-10 h-10 border-4 border-[#004D9D]/20 border-t-[#004D9D] rounded-full animate-spin"></div>
                        <p class="text-sm text-gray-500">Carregando avaliações...</p>
                    </div>

                {{-- Sem leitos --}}
                @elseif(empty($beds))
                    <div class="flex flex-col items-center justify-center h-full text-center px-6">
                        <p class="text-base font-semibold text-gray-700">Sem leitos disponíveis</p>
                    </div>

                @else
                    {{-- Sem resultado de busca --}}
                    <div x-show="filteredBeds.length === 0" class="flex flex-col items-center justify-center py-16 text-center px-6">
                        <p class="text-sm font-semibold text-gray-600">Nenhum resultado para "<span x-text="search"></span>"</p>
                        <button @click="search = ''" class="mt-2 text-xs text-[#0071B9] hover:underline">Limpar busca</button>
                    </div>

                    <div x-show="filteredBeds.length > 0" class="divide-y divide-gray-200">
                        <template x-for="bed in filteredBeds" :key="bed.leito + '-' + (bed.atendimento ?? 'empty')">
                            <div>
                                {{-- Cabeçalho do leito --}}
                                <div class="px-4 py-3 flex items-center gap-3 sticky top-0 z-10"
                                    :class="{
                                        'bg-gray-100': bed.status === 'empty',
                                        'bg-purple-50': bed.status === 'alta',
                                        'bg-amber-50': bed.status === 'alta_medica',
                                        'bg-gray-50': bed.status === 'occupied',
                                    }">

                                    <div class="w-12 h-11 flex-shrink-0 rounded-lg flex items-center justify-center font-bold text-sm shadow-sm"
                                        :class="{
                                            'bg-gray-300 text-gray-600': bed.status === 'empty',
                                            'bg-gradient-to-br from-purple-500 to-purple-700 text-white': bed.status === 'alta',
                                            'bg-gradient-to-br from-amber-400 to-orange-500 text-white': bed.status === 'alta_medica',
                                            'bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white': bed.status === 'occupied',
                                        }"
                                        x-text="bed.leito"></div>

                                    <div class="min-w-0 flex-1">
                                        <template x-if="bed.status === 'empty'">
                                            <span class="text-sm font-medium text-gray-400 italic">Leito Vago</span>
                                        </template>
                                        <template x-if="bed.status !== 'empty'">
                                            <div>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-semibold text-gray-900 text-sm leading-tight" x-text="bed.nome_paciente"></span>
                                                    <template x-if="hasPinned(bed)">
                                                        <i class="fas fa-star fa-xs text-amber-400 flex-shrink-0"></i>
                                                    </template>
                                                </div>
                                                <div class="flex items-center gap-2 flex-wrap mt-0.5 text-xs text-gray-400">
                                                    <span x-show="bed.prontuario" x-text="'Pront. ' + bed.prontuario"></span>
                                                    <span x-show="bed.internment_label">· <span x-text="bed.internment_label"></span></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                        <template x-if="bed.alta_label">
                                            <div class="text-right">
                                                <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full"
                                                    :class="bed.status === 'alta' ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'"
                                                    x-text="bed.alta_label"></span>
                                                <div x-show="bed.alta_formatted" class="text-[10px] text-gray-400 mt-0.5" x-text="bed.alta_formatted"></div>
                                            </div>
                                        </template>
                                        <template x-if="total(bed) > 0">
                                            <span class="bg-[#E8F4FD] text-[#004D9D] text-xs font-semibold px-2 py-0.5 rounded-full"
                                                x-text="total(bed) + (total(bed) === 1 ? ' msg' : ' msgs')"></span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Sem mensagem no turno --}}
                                <template x-if="bed.status !== 'empty' && total(bed) === 0">
                                    <div class="px-4 py-2.5 flex items-center gap-2 bg-white">
                                        <x-heroicon-o-chat-bubble-left-right class="w-4 h-4 text-gray-300 flex-shrink-0" />
                                        <span class="text-xs text-gray-400 italic">Sem anotação neste turno</span>
                                    </div>
                                </template>

                                {{-- Mensagens do turno ativo --}}
                                <template x-if="total(bed) > 0">
                                    <div class="px-4 py-3 space-y-2 bg-white">
                                        <template x-for="msg in msgs(bed)" :key="msg.id">
                                            <div class="rounded-xl overflow-hidden"
                                                :class="msg.is_pinned ? 'border-l-4 border-l-amber-400 bg-amber-50/40' : 'bg-gray-50'">

                                                {{-- Meta da mensagem --}}
                                                <div class="flex items-center gap-2 px-3 py-2">
                                                    <template x-if="photo(msg.user_id)">
                                                        <img :src="'data:image/jpeg;base64,' + photo(msg.user_id)"
                                                            :alt="msg.user_name"
                                                            class="w-7 h-7 rounded-full object-cover flex-shrink-0">
                                                    </template>
                                                    <template x-if="!photo(msg.user_id)">
                                                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#004D9D] to-[#0071B9] flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                                                            x-text="msg.user_initials"></div>
                                                    </template>
                                                    <span class="text-sm font-medium text-gray-800 flex-1 min-w-0 truncate" x-text="msg.user_name"></span>
                                                    {{-- Data/hora completa --}}
                                                    <span class="text-xs text-gray-400 tabular-nums flex-shrink-0" x-text="msg.datetime_label"></span>
                                                    <template x-if="msg.is_pinned">
                                                        <i class="fas fa-star fa-xs text-amber-400 flex-shrink-0"></i>
                                                    </template>
                                                </div>

                                                {{-- Conteúdo --}}
                                                <div class="px-3 pb-2 text-sm text-gray-800 leading-relaxed" x-html="msg.content"></div>

                                                {{-- Reação --}}
                                                <div class="px-3 pb-2 flex items-center gap-1.5">
                                                    <button
                                                        @click.stop="$wire.toggleReaction(msg.id)"
                                                        :class="msg.user_reacted
                                                            ? 'text-green-600 bg-green-50 border-green-200 hover:bg-green-100'
                                                            : 'text-gray-400 bg-white border-gray-200 hover:text-gray-600 hover:bg-gray-100'"
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-medium transition-colors"
                                                        :title="msg.user_reacted ? 'Remover confirmação' : 'Confirmar leitura'">
                                                        <x-heroicon-o-check class="w-3.5 h-3.5" />
                                                        <span x-show="msg.reactions_count > 0" x-text="msg.reactions_count"></span>
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                            </div>
                        </template>
                    </div>
                @endif
            </div>

            {{-- ── Footer ───────────────────────────────────────────────── --}}
            @if(!$loading && !empty($beds))
                <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 flex-shrink-0 flex items-center justify-between text-xs text-gray-500">
                    <span>
                        <span x-text="filteredBeds.length"></span> / {{ count($beds) }} leitos
                        <span x-show="search"> (filtrado)</span>
                    </span>
                    <span class="font-medium">
                        <span x-text="totalMsgs()"></span>
                        msg<span x-show="totalMsgs() !== 1">s</span>
                        · <span x-text="shiftMeta(activeTab).full_label || ''"></span>
                    </span>
                </div>
            @endif

        </div>
    </div>
</div>
