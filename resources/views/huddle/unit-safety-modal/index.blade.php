<div
    x-data="{ showModal: @entangle('showModal'), toast: false, toastMsg: '', timer: null }"
    @huddle-round-saved.window="showModal = false; toastMsg = ($event.detail && $event.detail.message) ? $event.detail.message : 'Salvo!'; toast = true; clearTimeout(timer); timer = setTimeout(() => toast = false, 3500)"
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
        </div>

        {{-- ═══ Corpo + Rodapé com Alpine (edição) ou estático (somente-leitura) ═══ --}}
        @if($canEditSafety)
            {{-- ── MODO EDIÇÃO: Alpine local ────────────────────────────────── --}}
            <div x-data="{
                     form: @js($safety),
                     saving: false,
                     validationError: '',
                     get allAnswered() {
                         return Object.values(this.form).every(v => {
                             if (v === null || v === undefined) return false;
                             if (typeof v === 'string' && v.trim() === '') return false;
                             if (typeof v === 'number' && isNaN(v)) return false;
                             return true;
                         });
                     }
                 }"
                 @round-validation-error.window="validationError = ($event.detail && $event.detail.message) ? $event.detail.message : 'Erro de validação'; saving = false; setTimeout(() => validationError = '', 4000)"
                 class="flex flex-col flex-1 overflow-hidden">

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

                    {{-- Erro de validação --}}
                    <div x-show="validationError" x-transition x-cloak class="px-4 pt-3">
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm">
                            <p class="font-semibold text-red-700"><i class="fas fa-circle-exclamation mr-1"></i><span x-text="validationError"></span></p>
                        </div>
                    </div>

                    <div class="p-4 space-y-4">
                        @forelse($questionsByAxis as $axisNumber => $questions)
                            @php $axisLabel = $questions->first()->axis_label ?? "Eixo {$axisNumber}"; @endphp
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Eixo {{ $axisNumber }} · {{ $axisLabel }}</p>
                                <div class="space-y-1">
                                    @foreach($questions as $question)
                                        @php $key = $question->field_key; @endphp

                                        @if($question->field_type === 'number')
                                            <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                                <span class="text-xs text-gray-700">{{ $question->label }}</span>
                                                <input type="number" min="0"
                                                       x-model.number="form.{{ $key }}"
                                                       class="w-20 text-sm text-right rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                            </div>

                                        @elseif($question->field_type === 'boolean')
                                            <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                                <span class="text-xs text-gray-700">{{ $question->label }}</span>
                                                <div class="flex gap-1 shrink-0">
                                                    <button type="button" @click="form.{{ $key }} = true"
                                                            :class="form.{{ $key }} === true ? 'bg-red-500 text-white border-red-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-medium border transition-colors">Sim</button>
                                                    <button type="button" @click="form.{{ $key }} = false"
                                                            :class="form.{{ $key }} === false ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                            class="px-2.5 py-1 rounded-lg text-xs font-medium border transition-colors">Não</button>
                                                </div>
                                            </div>

                                        @elseif($question->field_type === 'select')
                                            <p class="text-xs font-medium text-gray-700 mb-1">{{ $question->label }}</p>
                                            <div class="flex gap-1.5 mb-3">
                                                @foreach($question->options ?? [] as $option)
                                                    @php
                                                        $optVal = $option['value'];
                                                        $optLabel = $option['label'];
                                                        $activeCls = match($optVal) {
                                                            'verde' => 'bg-green-500 text-white border-green-500',
                                                            'amarelo' => 'bg-amber-400 text-amber-950 border-amber-400',
                                                            'vermelho' => 'bg-red-500 text-white border-red-500',
                                                            default => 'bg-[#004D9D] text-white border-[#004D9D]',
                                                        };
                                                    @endphp
                                                    <button type="button" @click="form.{{ $key }} = '{{ $optVal }}'"
                                                            :class="form.{{ $key }} === '{{ $optVal }}' ? '{{ $activeCls }}' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                                            class="flex-1 px-2 py-1.5 rounded-lg text-xs font-semibold border transition-colors">{{ $optLabel }}</button>
                                                @endforeach
                                            </div>

                                        @elseif($question->field_type === 'text')
                                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ $question->label }}</label>
                                            <textarea x-model="form.{{ $key }}" rows="2"
                                                      class="w-full text-sm rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D] mb-3"></textarea>
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
                    </div>
                </div>

                {{-- Rodapé (edição) --}}
                <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex items-center justify-between gap-2">
                    {{-- Indicador de progresso --}}
                    <p class="text-[11px] text-gray-400" x-show="!allAnswered" x-cloak>
                        <i class="fas fa-info-circle mr-0.5"></i>Responda todas as questões para salvar
                    </p>
                    <p class="text-[11px] text-green-600 font-medium" x-show="allAnswered" x-cloak>
                        <i class="fas fa-check-circle mr-0.5"></i>Todas as questões respondidas
                    </p>

                    <div class="flex gap-2">
                        <button type="button" wire:click="closeModal"
                                class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">Fechar</button>
                        <button type="button"
                                :disabled="!allAnswered || saving"
                                @click="
                                    saving = true;
                                    validationError = '';
                                    let data = {};
                                    for (let k in form) { data[k] = form[k]; }
                                    $wire.saveSafetyAssessment(data)
                                        .then(() => {
                                            saving = false;
                                            if ($wire.locked) { showModal = false; }
                                        })
                                        .catch(e => {
                                            saving = false;
                                            validationError = e.message || 'Erro ao salvar';
                                            setTimeout(() => validationError = '', 8000);
                                        });
                                "
                                :class="allAnswered && !saving
                                    ? 'bg-[#004D9D] text-white hover:bg-[#003a78]'
                                    : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            <template x-if="saving">
                                <span><i class="fas fa-spinner fa-spin mr-1"></i>Salvando…</span>
                            </template>
                            <template x-if="!saving">
                                <span>Salvar Round Unidade</span>
                            </template>
                        </button>
                    </div>
                </div>
            </div>

        @else
            {{-- ── MODO SOMENTE-LEITURA ─────────────────────────────────── --}}
            <div class="flex flex-col flex-1 overflow-hidden">
                <div class="flex-1 overflow-y-auto">

                    @if($locked)
                        <div class="px-4 pt-3">
                            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm">
                                <p class="font-semibold text-green-700"><i class="fas fa-check-circle mr-1"></i>Round Unidade preenchido</p>
                                <p class="text-[11px] text-green-600 mt-0.5">Este registro não pode ser alterado. Exibindo somente leitura.</p>
                            </div>
                        </div>
                    @endif

                    @unless($huddleAvailable)
                        <div class="px-4 pt-3">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm">
                                <p class="font-semibold text-amber-800"><i class="fas fa-triangle-exclamation mr-1"></i>Huddle indisponível hoje</p>
                                <p class="text-[11px] text-amber-700 mt-0.5">{{ $huddleBlockedReason ?? 'A rotina do Huddle não ocorre neste dia.' }}</p>
                            </div>
                        </div>
                    @endunless

                    <div class="p-4 space-y-4">
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
                                            <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                                <span class="text-xs text-gray-700">{{ $question->label }}</span>
                                                <strong class="text-sm text-gray-800">{{ $val ?? '—' }}</strong>
                                            </div>

                                        @elseif($question->field_type === 'boolean')
                                            <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                                <span class="text-xs text-gray-700">{{ $question->label }}</span>
                                                <strong class="text-sm {{ $val === true ? 'text-red-600' : 'text-gray-800' }}">{{ is_null($val) ? '—' : ($val ? 'Sim' : 'Não') }}</strong>
                                            </div>

                                        @elseif($question->field_type === 'select')
                                            <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                                <span class="text-xs font-medium text-gray-700">{{ $question->label }}</span>
                                                @php
                                                    $colorCls = match($val) {
                                                        'verde' => 'text-green-700 bg-green-100',
                                                        'amarelo' => 'text-amber-800 bg-amber-100',
                                                        'vermelho' => 'text-red-700 bg-red-100',
                                                        default => 'text-gray-800 bg-gray-100',
                                                    };
                                                @endphp
                                                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full {{ $colorCls }}">{{ ucfirst($val ?? '—') }}</span>
                                            </div>

                                        @elseif($question->field_type === 'text')
                                            <div class="bg-slate-50 rounded-lg px-2.5 py-1.5">
                                                <span class="text-xs font-medium text-gray-700">{{ $question->label }}</span>
                                                <p class="text-sm text-gray-700 whitespace-pre-line mt-0.5">{{ $val ?: '—' }}</p>
                                            </div>
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
                                <span>Preenchido por
                                    <strong class="text-gray-600">{{ $filledByLogin ?? '—' }}</strong>@if($filledAt) em {{ $filledAt }}@endif
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Rodapé (somente-leitura) --}}
                <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex justify-end">
                    <button type="button" wire:click="closeModal"
                            class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">Fechar</button>
                </div>
            </div>
        @endif
    </div>
    </div>
    </div>
</div>
