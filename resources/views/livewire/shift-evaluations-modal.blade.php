<div x-data="{
    show: @entangle('isOpen'),
    openBed: null,
    search: '',
    init() {
        this.$watch('show', v => { document.body.style.overflow = v ? 'hidden' : ''; });
        this.$watch('$wire.beds', (beds) => {
            if (this.openBed === null && beds && beds.length > 0) {
                const pinnedIdx = beds.findIndex(b => b.has_pinned);
                this.openBed = pinnedIdx >= 0 ? pinnedIdx : 0;
            }
        });
    },
    get filteredBeds() {
        const beds = this.$wire.beds || [];
        if (!this.search.trim()) return beds;
        const s = this.search.toLowerCase();
        return beds.filter(b =>
            b.leito.toLowerCase().includes(s) ||
            b.nome_paciente.toLowerCase().includes(s) ||
            b.prontuario.toLowerCase().includes(s)
        );
    }
}" x-show="show" x-cloak style="display: none;">
    <div class="fixed inset-0 z-[9998]" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.close()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl lg:w-[700px] lg:h-[85vh] shadow-2xl" @click.stop x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-white leading-tight">Avaliações do Turno</h2>
                            <p class="text-white/70 text-xs leading-tight truncate">{{ $sectorName }} @if($shiftWindowLabel)· {{ $shiftWindowLabel }}@endif</p>
                        </div>
                        @if($totalMessages > 0)
                            <span class="px-2.5 py-0.5 bg-white text-[#004D9D] text-xs font-bold rounded-full flex-shrink-0">{{ $totalMessages }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button wire:click="loadEvaluations" wire:loading.attr="disabled" title="Atualizar" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="loadEvaluations" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <a href="{{ route('sbar.evaluations.shift', ['sector_id' => $sectorId ?? 0]) }}" target="_blank" title="Abrir em nova aba" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        <button wire:click="close" title="Fechar" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Search --}}
                @if(!$loading && count($beds) > 0)
                    <div class="px-4 py-3 border-b border-gray-200 flex-shrink-0 bg-gray-50">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text" x-model="search" placeholder="Buscar por leito, nome ou prontuário..." class="w-full pl-10 pr-9 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#0071B9]/40 focus:border-[#0071B9] bg-white transition-colors" />
                            <button x-show="search" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0 bg-gray-50" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">
                    @if($loading)
                        <div class="flex flex-col items-center justify-center h-full">
                            <div class="relative mb-4">
                                <div class="w-12 h-12 border-4 border-[#004D9D]/20 border-t-[#004D9D] rounded-full animate-spin"></div>
                            </div>
                            <p class="text-sm text-gray-500">Carregando avaliações...</p>
                        </div>
                    @elseif(count($beds) === 0)
                        <div class="flex flex-col items-center justify-center h-full text-center px-6">
                            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-[#0071B9]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <p class="text-lg font-semibold text-gray-700">Sem avaliações</p>
                            <p class="text-sm text-gray-400 mt-1">Nenhuma mensagem no período <strong>{{ $shiftWindowLabel }}</strong>.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-200">
                            <template x-for="(bed, index) in filteredBeds" :key="bed.atendimento">
                                <div class="bg-white hover:bg-gray-50 transition-colors">
                                    <button @click="openBed = openBed === index ? null : index" class="w-full px-4 py-3 flex items-center gap-3 text-left">
                                        <div class="w-10 h-10 flex-shrink-0 bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold text-sm shadow-sm">
                                            <span x-text="bed.leito"></span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="font-semibold text-gray-900 text-sm truncate" x-text="bed.nome_paciente"></span>
                                                <svg x-show="bed.has_pinned" class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                                </svg>
                                            </div>
                                            <div class="text-xs text-gray-400 leading-tight" x-text="'Pront. ' + bed.prontuario"></div>
                                        </div>
                                        <span class="flex-shrink-0 bg-[#E8F4FD] text-[#004D9D] text-xs font-semibold px-2.5 py-1 rounded-full" x-text="bed.total_mensagens + (bed.total_mensagens === 1 ? ' msg' : ' msgs')"></span>
                                        <svg class="w-5 h-5 text-gray-400 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': openBed === index }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    <div x-show="openBed === index" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="border-t border-gray-200">
                                        <div class="px-4 py-3 bg-gray-50 space-y-3">
                                            <template x-for="msg in bed.mensagens" :key="msg.id">
                                                <div class="bg-white rounded-lg shadow-sm overflow-hidden" :class="msg.is_pinned ? 'border-l-[3px] border-l-amber-400' : 'border border-gray-200'">
                                                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
                                                        <div class="w-6 h-6 rounded-full bg-gradient-to-br from-[#004D9D] to-[#0071B9] flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0" x-text="msg.user_initials"></div>
                                                        <span class="text-xs font-medium text-gray-800 truncate flex-1 min-w-0" x-text="msg.user_name"></span>
                                                        <span class="px-1.5 py-0.5 bg-[#E8F4FD] text-[#004D9D] text-[10px] rounded font-medium flex-shrink-0" x-text="msg.turno"></span>
                                                        <span class="text-[10px] text-gray-400 flex-shrink-0 tabular-nums" x-text="msg.timestamp"></span>
                                                        <svg x-show="msg.is_pinned" class="w-3.5 h-3.5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                                        </svg>
                                                    </div>
                                                    <div class="px-3 py-2.5 text-sm text-gray-700 leading-relaxed" x-html="msg.content"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div x-show="filteredBeds.length === 0 && search" class="flex flex-col items-center justify-center py-12 text-center px-6">
                                <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <p class="text-sm text-gray-500">Nenhum resultado para "<span x-text="search"></span>"</p>
                                <button @click="search = ''" class="mt-2 text-xs text-[#0071B9] hover:underline">Limpar busca</button>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                @if(!$loading && count($beds) > 0)
                    <div class="px-4 py-3 border-t border-gray-200 bg-gray-100 flex-shrink-0 flex items-center justify-between">
                        <p class="text-xs text-gray-500 truncate">{{ $shiftWindowLabel }}</p>
                        <span class="text-xs text-gray-500 font-medium">{{ count($beds) }} leitos · {{ $totalMessages }} mensagens</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
