<div
    x-data="{
        showModal: @entangle('showModal'),
        toast: false,
        toastMsg: '',
        timer: null,
        // ── Arrastar para o lado = navegar entre pacientes ──────────────────
        dragStartX: null,
        dragStartY: null,
        dragDeltaX: 0,
        dragging: false,
        onDragStart(e) {
            // Ignora o gesto se começou num elemento interativo (botão, campo, link).
            if (e.target.closest('button, textarea, input, a, select')) return;
            this.dragStartX = e.clientX;
            this.dragStartY = e.clientY;
            this.dragDeltaX = 0;
            this.dragging = true;
        },
        onDragMove(e) {
            if (! this.dragging || this.dragStartX === null) return;
            this.dragDeltaX = e.clientX - this.dragStartX;
        },
        onDragEnd(e) {
            if (! this.dragging || this.dragStartX === null) return;
            const deltaY = (e.clientY ?? this.dragStartY) - this.dragStartY;
            const isHorizontal = Math.abs(this.dragDeltaX) > Math.abs(deltaY);
            if (isHorizontal && Math.abs(this.dragDeltaX) > 70) {
                this.dragDeltaX < 0 ? $wire.goToNextPatient() : $wire.goToPreviousPatient();
            }
            this.dragging = false;
            this.dragStartX = null;
            this.dragDeltaX = 0;
        }
    }"
    @huddle-notes-saved.window="toastMsg = ($event.detail && $event.detail.message) ? $event.detail.message : 'Salvo!'; toast = true; clearTimeout(timer); timer = setTimeout(() => toast = false, 3500)"
    x-show="showModal"
    x-cloak
    x-effect="document.body.style.overflow = showModal ? 'hidden' : ''"
    class="fixed inset-0 z-[9998]"
    @keydown.escape.window="$wire.closeModal()"
    style="display: none;"
>
    {{-- Toast de confirmação --}}
    <div x-show="toast" x-transition x-cloak
         class="fixed bottom-5 right-5 z-[9999] flex items-center gap-2 bg-green-600 text-white px-4 py-3 rounded-xl shadow-2xl font-montserrat text-sm font-medium"
         style="display: none;">
        <i class="fas fa-circle-check"></i>
        <span x-text="toastMsg"></span>
    </div>

    @php
        $p = $currentPatient;
        $tasyPrevAlta = $p['discharge_info']['dt_previsto_alta_formatted'] ?? null;
        $mews = $p['mews_score'] ?? ($p['pews_score'] ?? null);
        $within72h = $p['huddle_discharge_within_72h'] ?? false;
        $canConduct = $huddleAvailable && auth()->user()?->can('conduzir huddle');
    @endphp

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeModal()"></div>

    {{-- Camada de centralização --}}
    <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
    <div class="relative bg-white flex flex-col overflow-hidden shadow-2xl font-montserrat touch-pan-y
                w-full h-full
                sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                lg:w-[85vw] lg:h-[90vh] lg:max-w-3xl"
        @pointerdown="onDragStart($event)"
        @pointermove="onDragMove($event)"
        @pointerup="onDragEnd($event)"
        @pointercancel="dragging = false; dragStartX = null; dragDeltaX = 0">

        {{-- ── Cabeçalho fixo: navegação + identificação ───────────────────── --}}
        <div class="shrink-0 bg-[#004D9D] text-white px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <button wire:click="goToPreviousPatient" @disabled(! $canGoPrevious)
                        class="p-2 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed" title="Anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>

                <div class="flex-1 text-center min-w-0">
                    <div class="flex items-center justify-center gap-2">
                        <span class="bg-white/90 text-[#004D9D] font-bold text-sm px-3 py-0.5 rounded-full">
                            Leito {{ $p['cd_unidade_basica'] ?? 'N/A' }}
                        </span>
                        @if($mews !== null)
                            <span class="bg-white/15 border border-white/30 text-white text-xs font-bold px-2 py-0.5 rounded-full">MEWS: {{ $mews }}</span>
                        @endif
                    </div>
                    <p class="mt-1 font-bold text-base truncate">{{ $p['nm_pessoa_fisica'] ?? 'Paciente' }}</p>
                </div>

                <button wire:click="goToNextPatient" @disabled(! $canGoNext)
                        class="p-2 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed" title="Próximo">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        {{-- ── Conteúdo rolável ────────────────────────────────────────────── --}}
        <div class="flex-1 overflow-y-auto">

            @unless($huddleAvailable)
                <div class="px-4 pt-3">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm">
                        <p class="font-semibold text-amber-800"><i class="fas fa-triangle-exclamation mr-1"></i>Huddle indisponível hoje</p>
                        <p class="text-[11px] text-amber-700 mt-0.5">{{ $huddleBlockedReason ?? 'A rotina do Huddle não ocorre neste dia.' }}</p>
                    </div>
                </div>
            @endunless

            {{-- Dados do paciente --}}
            <div class="px-4 py-3 border-b border-gray-100 grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-1 text-xs text-gray-700">
                <div><span class="text-gray-400">Atend.:</span> <strong>{{ $p['nr_atendimento'] ?? '—' }}</strong></div>
                <div><span class="text-gray-400">Idade:</span> <strong>{{ $p['age_label'] ?? '—' }}</strong></div>
                <div><span class="text-gray-400">Internação:</span> <strong>{{ $p['internment_days'] ?? '—' }} dia(s)</strong></div>
                <div><span class="text-gray-400">Convênio:</span> <strong>{{ $p['convenio'] ?? '—' }}</strong></div>
                <div class="col-span-2"><span class="text-gray-400">Médico:</span> <strong>{{ $p['medico_responsavel'] ?? '—' }}</strong></div>
                <div class="col-span-2 md:col-span-1">
                    <span class="text-gray-400">Prev. Alta (Tasy):</span>
                    <strong class="{{ $tasyPrevAlta ? 'text-[#004D9D]' : 'text-amber-600' }}">{{ $tasyPrevAlta ?? 'não registrada' }}</strong>
                </div>
            </div>

            {{-- Escalas --}}
            <div class="px-4 py-2 border-b border-gray-100 flex flex-wrap gap-2 text-[11px]">
                <span class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50"><strong>Braden:</strong> {{ $p['braden_score'] ?? '-' }}</span>
                <span class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50"><strong>Morse:</strong> {{ $p['morse_score'] ?? '-' }}</span>
                <span class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50"><strong>Dor:</strong> {{ $p['pain_score'] ?? '-' }}</span>
                <span class="px-2 py-0.5 rounded border border-gray-200 bg-gray-50"><strong>TEV:</strong> {{ $p['vte_score'] ?? '-' }}</span>
            </div>

            <div class="px-4 py-4 space-y-4">

                {{-- Gate 72h automático --}}
                @if($within72h)
                    <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm">
                        <p class="font-semibold text-green-800">Previsão de alta nas próximas 72h — incluído no Huddle</p>
                        <p class="text-[11px] text-green-700 mt-0.5">Previsão do Tasy: <strong>{{ $tasyPrevAlta ?? '—' }}</strong></p>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm">
                        <p class="font-semibold text-gray-700">Sem previsão de alta nas próximas 72h</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">Previsão do Tasy: <strong>{{ $tasyPrevAlta ?? 'não registrada' }}</strong></p>
                    </div>
                @endif

                {{-- ── Checklist SAFER ──────────────────────────────────────── --}}
                @if($within72h)
                    @foreach($checklistItems as $item)
                        @php
                            $code = $item->value;
                            $state = $checklist[$code] ?? ['answer' => null, 'signal' => null, 'notes' => null];
                            $answer = $state['answer'] ?? null;
                            $signal = $state['signal'] ?? null;
                            $tasyLabel = $tasyLabels[$code] ?? null;
                        @endphp
                        <div class="rounded-xl border {{ $signal === 'red' ? 'border-red-200' : ($signal === 'green' ? 'border-green-200' : 'border-gray-200') }} p-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-gray-800">{{ $tasyLabel ?? $item->label() }}</p>
                                @if($signal)
                                    <span class="shrink-0 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full text-white {{ $signal === 'red' ? 'bg-red-500' : 'bg-green-500' }}">
                                        {{ $signal === 'red' ? 'Red' : 'Green' }}
                                    </span>
                                @endif
                            </div>

                            @if($canConduct)
                                {{-- Botões Sim/Não — permanecem clicáveis após responder; clicar em outra
                                     opção altera a resposta. O rótulo "Editar" só deixa isso explícito. --}}
                                <div class="mt-2 flex items-center gap-2" wire:key="ans-{{ $code }}">
                                    <button type="button"
                                            wire:click="answerItem('{{ $code }}', 'sim')"
                                            wire:loading.attr="disabled"
                                            wire:target="answerItem"
                                            class="px-3 py-1 rounded-lg text-xs font-medium border transition-colors disabled:opacity-60 {{ $answer === 'sim' ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Sim</button>
                                    <button type="button"
                                            wire:click="answerItem('{{ $code }}', 'nao')"
                                            wire:loading.attr="disabled"
                                            wire:target="answerItem"
                                            class="px-3 py-1 rounded-lg text-xs font-medium border transition-colors disabled:opacity-60 {{ $answer === 'nao' ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Não</button>
                                    @if($answer)
                                        <span class="text-[10px] text-gray-400 inline-flex items-center gap-1" title="Clique em Sim/Não para alterar a resposta">
                                            <i class="fas fa-pen"></i>Editar
                                        </span>
                                    @endif
                                </div>

                                @if($signal === 'red')
                                    <p class="mt-2 text-[11px] text-red-600"><i class="fas fa-circle-exclamation mr-0.5"></i>{{ $item->redAction() }}</p>
                                @endif

                                {{-- Recomendação (apenas para itens vermelhos — green não requer) --}}
                                @if($answer && $signal === 'red')
                                    <div class="mt-2">
                                        <label class="block text-[11px] font-medium text-gray-500 mb-1">
                                            <i class="fas fa-pen-to-square mr-0.5"></i>Recomendação
                                        </label>
                                        <textarea wire:model="checklist.{{ $code }}.notes" rows="2"
                                                  placeholder="Recomendação ou observação sobre este item..."
                                                  class="w-full text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D] placeholder:text-gray-400"></textarea>
                                    </div>
                                @endif
                            @else
                                <p class="mt-1 text-xs text-gray-500">Resposta: <strong>{{ $answer ? ucfirst($answer) : '—' }}</strong></p>
                                @if($signal === 'red' && ! empty($state['notes'] ?? null))
                                    <p class="mt-1 text-xs text-gray-600"><span class="text-gray-400">Recomendação:</span> {{ $state['notes'] }}</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- ── Rodapé fixo ─────────────────────────────────────────────────── --}}
        @php
            $answeredCount = $within72h
                ? collect($checklistItems)->filter(fn ($item) => ! empty($checklist[$item->value]['answer'] ?? null))->count()
                : 0;
            $totalItems = count($checklistItems);
            $allAnswered = $within72h && $answeredCount === $totalItems;
        @endphp
        <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex items-center justify-between gap-2">
            {{-- Progresso --}}
            @if($canConduct && $within72h)
                <p class="text-[11px] {{ $allAnswered ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                    @if($allAnswered)
                        <i class="fas fa-check-circle mr-0.5"></i>Todas as questões respondidas
                    @else
                        <i class="fas fa-info-circle mr-0.5"></i>{{ $answeredCount }}/{{ $totalItems }} respondidas
                    @endif
                </p>
            @else
                <span></span>
            @endif

            <div class="flex gap-2">
                <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">Fechar</button>
                @if($canConduct && $within72h)
                    <button type="button" wire:click="saveAllNotes"
                            wire:loading.attr="disabled" wire:target="saveAllNotes"
                            @disabled(! $allAnswered)
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors
                                   {{ $allAnswered ? 'bg-[#004D9D] text-white hover:bg-[#003a78]' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}
                                   disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveAllNotes">Salvar</span>
                        <span wire:loading wire:target="saveAllNotes"><i class="fas fa-spinner fa-spin mr-1"></i>Salvando…</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
    </div>
</div>
