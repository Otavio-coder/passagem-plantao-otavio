{{-- resources/views/livewire/shift-evaluations-modal.blade.php --}}
<div>
    @if($isOpen)
        <div
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
            x-data="{
                show: @entangle('isOpen'),
                openBed: null,
                search: '',
                get filteredBeds() {
                    const beds = $wire.beds || [];
                    if (!this.search.trim()) return beds;
                    const s = this.search.toLowerCase();
                    return beds.filter(b =>
                        b.leito.toLowerCase().includes(s) ||
                        b.nome_paciente.toLowerCase().includes(s) ||
                        b.prontuario.toLowerCase().includes(s)
                    );
                }
            }"
            x-init="
                $watch('$wire.beds', (beds) => {
                    if (openBed === null && beds.length > 0) {
                        const pinnedIdx = beds.findIndex(b => b.has_pinned);
                        openBed = pinnedIdx >= 0 ? pinnedIdx : 0;
                    }
                })
            "
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.close()"></div>

            <div
                class="relative w-full sm:max-w-2xl bg-white rounded-t-2xl sm:rounded-xl shadow-2xl flex flex-col"
                style="height: 88vh; max-height: 88vh; min-height: 200px;"
                @click.stop
            >
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] rounded-t-2xl sm:rounded-t-xl flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-white leading-tight">Avaliações do Turno</h2>
                            <p class="text-white/65 text-xs leading-tight truncate">
                                {{ $sectorName }}
                                @if($shiftWindowLabel)
                                    · {{ $shiftWindowLabel }}
                                @endif
                            </p>
                        </div>
                        @if($totalMessages > 0)
                            <span class="px-2 py-0.5 bg-white text-[#004D9D] text-xs font-bold rounded-full flex-shrink-0">
                                {{ $totalMessages }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button wire:click="loadEvaluations" wire:loading.attr="disabled" title="Atualizar"
                                class="p-1.5 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="loadEvaluations" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <a href="{{ route('sbar.evaluations.shift', ['sector_id' => $sectorId ?? 0]) }}"
                           target="_blank" title="Abrir em nova aba"
                           class="p-1.5 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        <button wire:click="close" title="Fechar" class="p-1.5 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Search --}}
                @if(!$loading && count($beds) > 0)
                    <div class="px-3 py-2 border-b border-gray-100 flex-shrink-0 bg-gray-50">
                        <div class="relative">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                type="text"
                                x-model="search"
                                placeholder="Leito, nome ou prontuário..."
                                class="w-full pl-8 pr-7 py-1.5 text-xs border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#0071B9]/40 focus:border-[#0071B9] bg-white transition-colors"
                            />
                            <template x-if="search">
                                <button @click="search = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                @endif

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0">
                    @if($loading)
                        <div class="flex flex-col items-center justify-center h-full">
                            <div class="w-7 h-7 border-2 border-t-[#004D9D] border-gray-200 rounded-full animate-spin mb-2"></div>
                            <p class="text-xs text-gray-400">Carregando avaliações...</p>
                        </div>

                    @elseif(count($beds) === 0)
                        <div class="flex flex-col items-center justify-center h-full text-center px-6">
                            <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-[#0071B9]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-700">Sem avaliações</p>
                            <p class="text-xs text-gray-400 mt-1">Nenhuma mensagem no período <strong>{{ $shiftWindowLabel }}</strong>.</p>
                        </div>

                    @else
                        <div class="divide-y divide-gray-100">
                            <template x-for="(bed, index) in filteredBeds" :key="bed.atendimento">
                                <div>
                                    {{-- Bed accordion header --}}
                                    <button
                                        @click="openBed = openBed === index ? null : index"
                                        class="w-full px-3 py-2.5 flex items-center gap-2.5 text-left hover:bg-gray-50 transition-colors"
                                    >
                                        {{-- Leito badge --}}
                                        <div class="w-9 h-9 flex-shrink-0 bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold text-[11px] shadow-sm">
                                            <span x-text="bed.leito"></span>
                                        </div>

                                        {{-- Patient info --}}
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="font-semibold text-gray-900 text-sm truncate" x-text="bed.nome_paciente"></span>
                                                <template x-if="bed.has_pinned">
                                                    <svg class="w-3 h-3 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                                    </svg>
                                                </template>
                                            </div>
                                            <div class="text-[11px] text-gray-400 leading-tight" x-text="'Pront. ' + bed.prontuario"></div>
                                        </div>

                                        {{-- Message count --}}
                                        <span class="flex-shrink-0 bg-[#E8F4FD] text-[#004D9D] text-xs font-semibold px-2 py-0.5 rounded-full"
                                              x-text="bed.total_mensagens + (bed.total_mensagens === 1 ? ' msg' : ' msgs')"></span>

                                        {{-- Chevron --}}
                                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0"
                                             :class="{ 'rotate-180': openBed === index }"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>

                                    {{-- Messages --}}
                                    <div
                                        x-show="openBed === index"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="border-t border-gray-100"
                                    >
                                        <div class="px-3 py-2 bg-gray-50/70 space-y-2">
                                            <template x-for="msg in bed.mensagens" :key="msg.id">
                                                <div class="bg-white rounded-lg shadow-sm overflow-hidden"
                                                     :class="msg.is_pinned ? 'border-l-[3px] border-l-amber-400' : 'border border-gray-100'">

                                                    {{-- Message meta --}}
                                                    <div class="flex items-center gap-2 px-3 py-1.5 border-b border-gray-50">
                                                        {{-- Initials avatar --}}
                                                        <div class="w-5 h-5 rounded-full bg-gradient-to-br from-[#004D9D] to-[#0071B9] flex items-center justify-center text-white text-[9px] font-bold flex-shrink-0"
                                                             x-text="msg.user_initials"></div>

                                                        {{-- Author --}}
                                                        <span class="text-xs font-medium text-gray-800 truncate flex-1 min-w-0" x-text="msg.user_name"></span>

                                                        {{-- Shift badge --}}
                                                        <span class="px-1.5 py-0.5 bg-[#E8F4FD] text-[#004D9D] text-[10px] rounded font-medium flex-shrink-0"
                                                              x-text="msg.turno"></span>

                                                        {{-- Timestamp --}}
                                                        <span class="text-[10px] text-gray-400 flex-shrink-0 tabular-nums" x-text="msg.timestamp"></span>

                                                        {{-- Pin indicator --}}
                                                        <template x-if="msg.is_pinned">
                                                            <svg class="w-3 h-3 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                                            </svg>
                                                        </template>
                                                    </div>

                                                    {{-- Content --}}
                                                    <div class="px-3 py-2 text-xs text-gray-700 leading-relaxed" x-html="msg.content"></div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- Empty search --}}
                            <template x-if="filteredBeds.length === 0 && search">
                                <div class="flex flex-col items-center justify-center py-10 text-center px-6">
                                    <p class="text-sm text-gray-400">Nenhum resultado para "<span x-text="search"></span>"</p>
                                    <button @click="search = ''" class="mt-2 text-xs text-[#0071B9] hover:underline">Limpar busca</button>
                                </div>
                            </template>
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                @if(!$loading && count($beds) > 0)
                    <div class="px-4 py-2 border-t border-gray-100 bg-gray-50 rounded-b-2xl sm:rounded-b-xl flex-shrink-0 flex items-center justify-between gap-2">
                        <p class="text-[10px] text-gray-400 truncate">{{ $shiftWindowLabel }}</p>
                        <span class="text-[10px] text-gray-400 flex-shrink-0">{{ count($beds) }} leitos · {{ $totalMessages }} msgs</span>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
