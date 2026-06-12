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
                    <h2 class="text-base font-bold text-white">Análise Geral com IA</h2>
                    <p class="text-xs text-white/70">Análise consolidada das mensagens de passagem de plantão · GPT-4o-mini</p>
                </div>
                <button @click="open=false" wire:click="close"
                        class="flex items-center gap-1.5 text-sm font-semibold text-white/90 hover:text-white transition-colors flex-shrink-0">
                    <i class="fas fa-arrow-left text-sm"></i>
                    Voltar
                </button>
            </div>

            <div class="overflow-y-auto flex-1">
                <div class="grid grid-cols-1 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-100">

                    {{-- ── Painel lateral ── --}}
                    <div class="md:col-span-1 p-4 space-y-4">

                        {{-- Período --}}
                        <div class="space-y-3">
                            <p class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                <i class="fas fa-calendar-days text-[#0071B9] text-[10px]"></i>
                                Período
                            </p>
                            <div>
                                <label class="text-[10px] font-semibold text-gray-500 block mb-1">De</label>
                                <input type="date" wire:model.blur="periodStart"
                                       max="{{ now()->toDateString() }}"
                                       class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                                @error('periodStart')
                                <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="text-[10px] font-semibold text-gray-500 block mb-1">Até</label>
                                <input type="date" wire:model.blur="periodEnd"
                                       max="{{ now()->toDateString() }}"
                                       class="w-full text-xs border border-gray-200 rounded-lg px-2.5 py-2 text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                                @error('periodEnd')
                                <p class="text-[10px] text-red-500 mt-0.5">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Atalhos --}}
                        <div class="flex flex-wrap gap-1.5">
                            @foreach([7 => '7d', 30 => '30d', 90 => '90d', 180 => '180d'] as $days => $label)
                            <button type="button"
                                    wire:click="$set('periodStart', '{{ now()->subDays($days)->toDateString() }}')"
                                    class="text-[10px] font-semibold px-2 py-1 rounded-md border border-gray-200 text-gray-500 hover:border-[#004D9D]/40 hover:text-[#004D9D] transition-colors">
                                {{ $label }}
                            </button>
                            @endforeach
                        </div>

                        {{-- Botão gerar --}}
                        @if($analysis && $analysis->isCompleted())
                            @if($confirmRegenerate)
                            <div class="space-y-2">
                                <p class="text-[11px] text-gray-600">Gerar nova análise para este período?</p>
                                <div class="flex gap-2">
                                    <button wire:click="generateAnalysis"
                                            wire:loading.attr="disabled"
                                            wire:target="generateAnalysis"
                                            class="flex-1 text-xs font-semibold py-2 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors disabled:opacity-60">
                                        Confirmar
                                    </button>
                                    <button wire:click="cancelRegenerate"
                                            class="flex-1 text-xs font-semibold py-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                            @else
                            <button wire:click="requestRegenerate"
                                    class="w-full flex items-center justify-center gap-2 text-xs font-semibold py-2.5 rounded-lg border border-[#004D9D]/30 text-[#004D9D] hover:bg-[#004D9D]/5 transition-colors">
                                <i class="fas fa-rotate text-[10px]"></i>
                                Regenerar análise
                            </button>
                            @endif
                        @else
                        <button wire:click="generateAnalysis"
                                wire:loading.attr="disabled"
                                wire:target="generateAnalysis"
                                class="w-full flex items-center justify-center gap-2 text-xs font-semibold py-2.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                            
                            <svg wire:loading wire:target="generateAnalysis" class="animate-spin" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            <span wire:loading.remove wire:target="generateAnalysis">Gerar análise</span>
                            <span wire:loading wire:target="generateAnalysis">Analisando...</span>
                        </button>
                        @endif

                        @if($analysis && $analysis->isFailed())
                        <p class="text-[10px] text-red-500">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            {{ Str::limit($analysis->error_message, 80) }}
                        </p>
                        @endif

                        {{-- Histórico --}}
                        @if($recentAnalyses->isNotEmpty())
                        <div class="pt-2 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1.5">
                                <i class="fas fa-clock-rotate-left text-gray-400 text-[10px]"></i>
                                Análises anteriores
                            </p>
                            <div class="space-y-1">
                                @foreach($recentAnalyses as $prev)
                                <button type="button"
                                        wire:click="$set('currentAnalysisId', {{ $prev->id }})"
                                        class="w-full text-left px-2.5 py-2 rounded-lg border transition-colors {{ $currentAnalysisId === $prev->id ? 'bg-[#004D9D]/5 border-[#004D9D]/20' : 'border-transparent hover:bg-gray-50' }}">
                                    <p class="text-[11px] font-semibold text-gray-700 leading-tight">
                                        {{ \Carbon\Carbon::parse($prev->period_start)->format('d/m/y') }} – {{ \Carbon\Carbon::parse($prev->period_end)->format('d/m/y') }}
                                    </p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">
                                        {{ $prev->generated_at?->format('d/m/Y H:i') }} · {{ number_format($prev->total_messages) }} msg
                                    </p>
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @endif

                    </div>

                    {{-- ── Área principal ── --}}
                    <div class="md:col-span-3 p-4">

                        {{-- Loading --}}
                        <div wire:loading wire:target="generateAnalysis"
                             class="py-12 text-center">
                            <div class="w-14 h-14 rounded-full bg-[#004D9D]/10 flex items-center justify-center mx-auto mb-4">
                                <svg class="animate-spin text-[#004D9D] mx-auto mb-2" style="width:2rem;height:2rem" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-700">Preparando e analisando dados com IA...</p>
                            <p class="text-xs text-gray-400 mt-1">Isso pode levar até 30 segundos</p>
                            <div class="mt-4 space-y-1 text-[11px] text-gray-400">
                                <p>· Coletando mensagens do período</p>
                                <p>· Enriquecendo com classificações clínicas</p>
                                <p>· Consultando OpenAI GPT-4o-mini</p>
                            </div>
                        </div>

                        {{-- Resultado --}}
                        <div wire:loading.remove wire:target="generateAnalysis">

                            @if(!$analysis)
                            <div class="py-12 text-center text-gray-400">
                                
                                <p class="text-sm font-semibold text-gray-600">Nenhuma análise gerada</p>
                                <p class="text-xs text-gray-400 mt-1">Selecione o período e clique em "Gerar análise" para começar.</p>
                            </div>

                            @elseif($analysis->isProcessing())
                            <div class="py-12 text-center">
                                <svg class="animate-spin text-[#004D9D] mx-auto mb-2" style="width:2rem;height:2rem" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                <p class="text-sm font-semibold text-gray-600">Análise em processamento...</p>
                            </div>

                            @elseif($analysis->isFailed())
                            <div class="py-10 text-center">
                                <i class="fas fa-triangle-exclamation text-red-400 text-2xl mb-3 block"></i>
                                <p class="text-sm font-semibold text-red-700">Erro ao gerar análise</p>
                                <p class="text-xs text-red-500 mt-1">{{ $analysis->error_message }}</p>
                                <button wire:click="generateAnalysis"
                                        class="mt-4 px-4 py-2 text-xs font-semibold text-white bg-[#004D9D] rounded-lg hover:bg-[#003d7a] transition-colors">
                                    Tentar novamente
                                </button>
                            </div>

                            @elseif($analysis->isCompleted())
                            <div class="flex flex-wrap items-center gap-3 mb-3 px-1">
                                <div class="flex items-center gap-1.5 text-[11px] text-gray-500">
                                    <i class="fas fa-calendar-days text-gray-400 text-[10px]"></i>
                                    {{ \Carbon\Carbon::parse($analysis->period_start)->format('d/m/Y') }}
                                    –
                                    {{ \Carbon\Carbon::parse($analysis->period_end)->format('d/m/Y') }}
                                </div>
                                <div class="w-1 h-1 rounded-full bg-gray-300"></div>
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
    </div>
</div>

