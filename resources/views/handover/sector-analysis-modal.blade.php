<div>
    <div class="fixed inset-0 z-40 bg-white flex flex-col"
         x-data="{ open: @js($isOpen) }"
         x-show="open"
         x-init="$watch('$wire.isOpen', val => { open = val })"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         style="display:none">

        <div class="flex flex-col h-full overflow-hidden">

            {{-- Header --}}
            <div class="bg-[#004D9D] px-5 py-4 flex items-center gap-3 flex-shrink-0">
                <div class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center flex-shrink-0">
                    
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-base font-bold text-white truncate">{{ $sectorName }}</h2>
                    <p class="text-xs text-white/70">Análise de passagem de plantão com IA</p>
                </div>
                <button @click="open=false" wire:click="close"
                        class="flex items-center gap-1.5 text-sm font-semibold text-white/90 hover:text-white transition-colors flex-shrink-0">
                    <i class="fas fa-arrow-left text-sm"></i>
                    Voltar
                </button>
            </div>

            <div class="overflow-y-auto flex-1 p-4 space-y-4">

                {{-- Período --}}
                <div class="bg-gray-50 rounded-xl border border-gray-200 px-4 py-3">
                    <p class="text-xs font-semibold text-gray-600 mb-2.5">Período</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="text-[10px] font-semibold text-gray-500 block mb-1">De</label>
                            <input type="date" wire:model.blur="periodStart"
                                   max="{{ now()->toDateString() }}"
                                   class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-gray-500 block mb-1">Até</label>
                            <input type="date" wire:model.blur="periodEnd"
                                   max="{{ now()->toDateString() }}"
                                   class="text-xs border border-gray-200 rounded-lg px-2.5 py-2 text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                        </div>

                        {{-- Botão principal --}}
                        @if($analysis && $analysis->isCompleted())
                            @if($confirmRegenerate)
                            <div class="flex gap-2">
                                <button wire:click="generate"
                                        wire:loading.attr="disabled"
                                        wire:target="generate"
                                        class="text-xs font-semibold px-3 py-2 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors disabled:opacity-60">
                                    Confirmar
                                </button>
                                <button wire:click="cancelRegenerate"
                                        class="text-xs font-semibold px-3 py-2 rounded-lg bg-gray-200 text-gray-600 hover:bg-gray-300 transition-colors">
                                    Cancelar
                                </button>
                            </div>
                            @else
                            <button wire:click="requestRegenerate"
                                    class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg border border-[#004D9D]/30 text-[#004D9D] hover:bg-[#004D9D]/5 transition-colors">
                                <i class="fas fa-rotate text-[10px]"></i>
                                Regenerar
                            </button>
                            @endif
                        @else
                        <button wire:click="generate"
                                wire:loading.attr="disabled"
                                wire:target="generate"
                                class="flex items-center gap-2 text-xs font-semibold px-4 py-2 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                            
                            <svg wire:loading wire:target="generate" class="animate-spin" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span wire:loading.remove wire:target="generate">Gerar análise</span>
                            <span wire:loading wire:target="generate">Analisando...</span>
                        </button>
                        @endif
                    </div>
                    @error('periodStart')
                    <p class="text-[10px] text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Loading --}}
                <div wire:loading wire:target="generate"
                     class="py-8 text-center">
                    <svg class="animate-spin text-[#004D9D] mx-auto mb-2" style="width:2rem;height:2rem" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <p class="text-sm font-semibold text-gray-600">Analisando com IA...</p>
                    <p class="text-xs text-gray-400 mt-1">Coletando mensagens, enriquecendo dados e consultando GPT-4o-mini</p>
                </div>

                {{-- Resultado --}}
                <div wire:loading.remove wire:target="generate">

                    @if(!$analysis)
                    <div class="py-8 text-center text-gray-400">
                        
                        <p class="text-sm">Selecione o período e clique em "Gerar análise"</p>
                    </div>

                    @elseif($analysis->isFailed())
                    <div class="py-6 text-center">
                        <i class="fas fa-triangle-exclamation text-red-400 text-xl mb-2 block"></i>
                        <p class="text-sm font-semibold text-red-700">Erro ao gerar análise</p>
                        <p class="text-xs text-red-500 mt-1">{{ $analysis->error_message }}</p>
                    </div>

                    @elseif($analysis->isCompleted())
                    <div class="flex flex-wrap gap-3 mb-3">
                        <span class="text-[11px] text-gray-500">
                            <span class="font-semibold text-gray-700">{{ number_format($analysis->total_messages) }}</span> mensagens
                        </span>
                        <span class="text-[11px] text-gray-500">
                            <span class="font-semibold text-gray-700">{{ number_format($analysis->total_patients) }}</span> pacientes
                        </span>
                        <span class="text-[11px] text-gray-500">
                            <span class="font-semibold text-gray-700">{{ number_format($analysis->total_professionals) }}</span> profissionais
                        </span>
                        <span class="text-[10px] text-gray-400 ml-auto">
                            
                            {{ $analysis->generated_at?->format('d/m/Y H:i') }}
                        </span>
                    </div>
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                        <div class="prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($analysis->analysis_text ?? '') !!}</div>
                    </div>
                    @endif

                </div>

            </div>
        </div>
    </div>
</div>
