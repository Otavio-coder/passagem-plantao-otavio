<div
    x-data="{ showModal: @entangle('showModal'), toast: false, toastMsg: '', timer: null }"
    @huddle-round-saved.window="toastMsg = ($event.detail && $event.detail.message) ? $event.detail.message : 'Salvo!'; toast = true; clearTimeout(timer); timer = setTimeout(() => toast = false, 3500)"
>
    {{-- Toast de confirmação: fica fora do modal para continuar visível depois que ele fecha --}}
    <div x-show="toast" x-transition x-cloak
         class="fixed bottom-5 right-5 z-[9999] flex items-center gap-2 bg-green-600 text-white px-4 py-3 rounded-xl shadow-2xl font-montserrat text-sm font-medium"
         style="display: none;">
        <i class="fas fa-circle-check"></i>
        <span x-text="toastMsg"></span>
    </div>

    {{-- Modal --}}
    <div
        x-show="showModal"
        x-cloak
        x-effect="document.body.style.overflow = showModal ? 'hidden' : ''"
        class="fixed inset-0 z-[9998]"
        @keydown.escape.window="$wire.closeModal()"
        style="display: none;"
    >
    @php
        $canEditSafety = $huddleAvailable && auth()->user()?->can('conduzir huddle') && ! $locked;
    @endphp

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeModal()"></div>

    {{-- Camada de centralização --}}
    <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
    <div class="relative bg-white flex flex-col overflow-hidden shadow-2xl font-montserrat
                w-full h-full
                sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                lg:w-[85vw] lg:h-[90vh] lg:max-w-2xl">

        {{-- Cabeçalho --}}
        <div class="shrink-0 bg-[#004D9D] text-white px-4 py-3 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="font-bold text-base leading-tight"><i class="fas fa-clipboard-check mr-1"></i>Round Unidade</p>
                <p class="text-[11px] text-white/80 truncate">
                    {{ $hospitalName ?: '—' }}@if($sectorLabel) · {{ $sectorLabel }}@endif · preenchimento por unidade
                </p>
            </div>
            <button type="button" wire:click="closeModal" class="p-2 rounded-lg hover:bg-white/20" title="Fechar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Conteúdo rolável --}}
        <div class="flex-1 overflow-y-auto">

            @unless($huddleAvailable)
                <div class="px-4 pt-3">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <p class="font-semibold text-amber-800"><i class="fas fa-triangle-exclamation mr-1"></i>Huddle indisponível hoje</p>
                        <p class="text-[11px] text-amber-700 mt-0.5">{{ $huddleBlockedReason ?? 'A rotina do Huddle não ocorre neste dia.' }}</p>
                    </div>
                </div>
            @endunless

            @if($locked)
                <div class="px-4 pt-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                        <p class="font-semibold text-slate-700"><i class="fas fa-lock mr-1"></i>Round Unidade já preenchido hoje</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Este registro não pode ser alterado. Exibindo somente leitura.</p>
                    </div>
                </div>
            @endif

            <div class="p-4 space-y-4">

                {{-- Renderiza as perguntas agrupadas por eixo (lidas do MySQL) --}}
                @forelse($questionsByAxis as $axisNumber => $questions)
                    @php $axisLabel = $questions->first()->axis_label ?? "Eixo {$axisNumber}"; @endphp
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Eixo {{ $axisNumber }} · {{ $axisLabel }}</p>
                        <div class="space-y-1">
                            @foreach($questions as $question)
                                @php
                                    $key = $question->field_key;
                                    $val = $safety[$key] ?? null;
                                @endphp

                                @if($question->field_type === 'number')
                                    {{-- Campo numérico --}}
                                    <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                        <span class="text-xs text-gray-700">{{ $question->label }}</span>
                                        @if($canEditSafety)
                                            <input type="number" min="0" wire:model="safety.{{ $key }}"
                                                   class="w-20 text-sm text-right rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                        @else
                                            <strong class="text-sm text-gray-800">{{ $val ?? '—' }}</strong>
                                        @endif
                                    </div>

                                @elseif($question->field_type === 'boolean')
                                    {{-- Campo Sim/Não --}}
                                    <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                        <span class="text-xs text-gray-700">{{ $question->label }}</span>
                                        @if($canEditSafety)
                                            <div class="flex gap-1 shrink-0">
                                                <button type="button" wire:click="$set('safety.{{ $key }}', true)" wire:loading.attr="disabled"
                                                        class="px-2.5 py-1 rounded-lg text-xs font-medium border {{ $val === true ? 'bg-red-500 text-white border-red-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Sim</button>
                                                <button type="button" wire:click="$set('safety.{{ $key }}', false)" wire:loading.attr="disabled"
                                                        class="px-2.5 py-1 rounded-lg text-xs font-medium border {{ $val === false ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Não</button>
                                            </div>
                                        @else
                                            <strong class="text-sm {{ $val === true ? 'text-red-600' : 'text-gray-800' }}">{{ is_null($val) ? '—' : ($val ? 'Sim' : 'Não') }}</strong>
                                        @endif
                                    </div>

                                @elseif($question->field_type === 'select')
                                    {{-- Campo de seleção (ex: Classificação da unidade) --}}
                                    <p class="text-xs font-medium text-gray-700 mb-1">{{ $question->label }}</p>
                                    @if($canEditSafety)
                                        <div class="flex gap-1.5 mb-3">
                                            @foreach($question->options ?? [] as $option)
                                                @php
                                                    $optVal = $option['value'];
                                                    $optLabel = $option['label'];
                                                    $active = $val === $optVal;
                                                    $activeCls = match($optVal) {
                                                        'verde' => 'bg-green-500 text-white border-green-500',
                                                        'amarelo' => 'bg-amber-400 text-amber-950 border-amber-400',
                                                        'vermelho' => 'bg-red-500 text-white border-red-500',
                                                        default => 'bg-[#004D9D] text-white border-[#004D9D]',
                                                    };
                                                @endphp
                                                <button type="button" wire:click="$set('safety.{{ $key }}', '{{ $optVal }}')" wire:loading.attr="disabled"
                                                        class="flex-1 px-2 py-1.5 rounded-lg text-xs font-semibold border {{ $active ? $activeCls : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">{{ $optLabel }}</button>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-800 mb-3">{{ ucfirst($val ?? '—') }}</p>
                                    @endif

                                @elseif($question->field_type === 'text')
                                    {{-- Campo de texto livre --}}
                                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ $question->label }}</label>
                                    @if($canEditSafety)
                                        <textarea wire:model="safety.{{ $key }}" rows="2"
                                                  class="w-full text-sm rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D] mb-3"></textarea>
                                    @else
                                        <p class="text-sm text-gray-700 whitespace-pre-line mb-3">{{ $val ?: '—' }}</p>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <p class="font-semibold text-amber-800"><i class="fas fa-triangle-exclamation mr-1"></i>Nenhuma pergunta cadastrada</p>
                        <p class="text-[11px] text-amber-700 mt-0.5">Execute o seeder para popular as perguntas do Round Unidade.</p>
                    </div>
                @endforelse

                {{-- Auditoria --}}
                @if($filledByLogin || $filledAt)
                    <div class="pt-2 border-t border-gray-100 text-[11px] text-gray-400 flex items-center gap-1.5">
                        <i class="fas fa-clock-rotate-left"></i>
                        <span>Última atualização por
                            <strong class="text-gray-600">{{ $filledByLogin ?? '—' }}</strong>@if($filledAt) em {{ $filledAt }}@endif
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Rodapé --}}
        <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex justify-end gap-2">
            <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">Fechar</button>
            @if($canEditSafety)
                <button type="button" wire:click="saveSafetyAssessment" wire:loading.attr="disabled" wire:target="saveSafetyAssessment"
                        class="px-4 py-2 rounded-lg bg-[#004D9D] text-white hover:bg-[#003a78] disabled:opacity-60 text-sm font-medium">
                    <span wire:loading.remove wire:target="saveSafetyAssessment">Salvar Round Unidade</span>
                    <span wire:loading wire:target="saveSafetyAssessment">Salvando…</span>
                </button>
            @endif
        </div>
    </div>
    </div>
    </div>
</div>
