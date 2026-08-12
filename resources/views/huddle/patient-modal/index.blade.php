<div
    x-data="{ showModal: @entangle('showModal') }"
    x-show="showModal"
    x-cloak
    x-effect="document.body.style.overflow = showModal ? 'hidden' : ''"
    class="fixed inset-0 z-[9998]"
    @keydown.escape.window="$wire.closeModal()"
    style="display: none;"
>
    @php
        $p = $currentPatient;
        $tasyPrevAlta = $p['discharge_info']['dt_previsto_alta_formatted'] ?? null;
        $mews = $p['mews_score'] ?? ($p['pews_score'] ?? null);
        $within72h = $p['huddle_discharge_within_72h'] ?? false;
    @endphp

    {{-- Backdrop (mesmo padrão do modal do SBAR — z alto vence a navbar) --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeModal()"></div>

    {{-- Camada de centralização --}}
    <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
    {{-- Altura DEFINIDA (como o SBAR) para o scroll interno funcionar: cabeçalho e
         rodapé fixos, meio rolável. --}}
    <div class="relative bg-white flex flex-col overflow-hidden shadow-2xl font-montserrat
                w-full h-full
                sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                lg:w-[85vw] lg:h-[90vh] lg:max-w-3xl">

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

            {{-- Bloqueio de fim de semana / feriado: a rotina não roda nesses dias.
                 A visualização é liberada, mas o preenchimento fica desabilitado. --}}
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

                {{-- Gate 72h automático (sem pergunta manual) --}}
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

                {{-- Checklist (apenas para pacientes do Huddle) --}}
                @if($within72h)
                    @foreach($checklistItems as $item)
                        @php
                            $code = $item->value;
                            $state = $checklist[$code] ?? ['answer' => null, 'signal' => null, 'responsible' => null, 'due_at' => null, 'notes' => null];
                            $answer = $state['answer'] ?? null;
                            $signal = $state['signal'] ?? null;
                            $tasyLabel = $tasyLabels[$code] ?? null;
                        @endphp
                        <div class="rounded-xl border {{ $signal === 'red' ? 'border-red-200' : ($signal === 'green' ? 'border-green-200' : 'border-gray-200') }} p-3">
                            <div class="flex items-start justify-between gap-2">
                                {{-- Label do Oracle quando disponível, fallback para enum --}}
                                <p class="text-sm font-medium text-gray-800">{{ $tasyLabel ?? $item->label() }}</p>
                                @if($signal)
                                    <span class="shrink-0 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full text-white {{ $signal === 'red' ? 'bg-red-500' : 'bg-green-500' }}">
                                        {{ $signal === 'red' ? 'Red' : 'Green' }}
                                    </span>
                                @endif
                            </div>
                            @if($tasyLabel)
                                <p class="text-[10px] text-gray-400 mt-0.5"><i class="fas fa-database mr-0.5"></i>Tasy #{{ \App\Enums\Huddle\TasyEvaluationItemMap::fromChecklistItem($item)->value }}</p>
                            @endif

                            @if($huddleAvailable && auth()->user()?->can('conduzir huddle'))
                                <div class="mt-2 flex gap-2" wire:key="ans-{{ $code }}">
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
                                </div>

                                @if($signal === 'red')
                                    <p class="mt-2 text-[11px] text-red-600"><i class="fas fa-circle-exclamation mr-0.5"></i>{{ $item->redAction() }}</p>
                                    @if($item->requiresFollowUp())
                                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <input type="text" wire:model="checklist.{{ $code }}.responsible" placeholder="Responsável (opcional)"
                                                   class="text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                            <input type="date" wire:model="checklist.{{ $code }}.due_at"
                                                   class="text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                        </div>
                                    @endif
                                @endif

                                {{-- Recomendação (texto livre) — visível após responder --}}
                                @if($answer)
                                    <div class="mt-2">
                                        <label class="block text-[11px] font-medium text-gray-500 mb-1">
                                            <i class="fas fa-pen-to-square mr-0.5"></i>Recomendação
                                        </label>
                                        <textarea wire:model="checklist.{{ $code }}.notes" rows="2"
                                                  placeholder="Recomendação ou observação sobre este item..."
                                                  class="w-full text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D] placeholder:text-gray-400"></textarea>
                                    </div>
                                    <div class="mt-1.5 flex items-center gap-2">
                                        <button type="button" wire:click="saveItemDetails('{{ $code }}')"
                                                wire:loading.attr="disabled" wire:target="saveItemDetails('{{ $code }}')"
                                                class="text-xs px-3 py-1 rounded-lg bg-[#004D9D] text-white hover:bg-[#003a78] disabled:opacity-60">
                                            <span wire:loading.remove wire:target="saveItemDetails('{{ $code }}')">Salvar</span>
                                            <span wire:loading wire:target="saveItemDetails('{{ $code }}')"><i class="fas fa-spinner fa-spin"></i></span>
                                        </button>
                                    </div>
                                @endif
                            @else
                                <p class="mt-1 text-xs text-gray-500">Resposta: <strong>{{ $answer ? ucfirst($answer) : '—' }}</strong></p>
                                @if(! empty($state['notes'] ?? null))
                                    <p class="mt-1 text-xs text-gray-600"><span class="text-gray-400">Recomendação:</span> {{ $state['notes'] }}</p>
                                @endif
                            @endif

                            {{-- Auditoria do item: login e data/hora de quem respondeu --}}
                            @if(! empty($state['answered_by_login'] ?? null))
                                <p class="mt-2 text-[10px] text-gray-400 flex items-center gap-1">
                                    <i class="fas fa-user-check"></i>
                                    <span>{{ $state['answered_by_login'] }}@if(! empty($state['answered_at'] ?? null)) · {{ $state['answered_at'] }}@endif</span>
                                </p>
                            @endif
                        </div>
                    @endforeach

                @endif

                {{-- Comentários obrigatórios quando o dia é vermelho — sempre ao final do
                     formulário, independente da janela de 72h. --}}
                @if($dayColor === 'red')
                    <div class="rounded-xl border border-red-200 bg-red-50 p-3" wire:key="comments-{{ $p['nr_atendimento'] ?? 'x' }}">
                        <label class="block text-sm font-semibold text-red-800 mb-1">Comentários <span class="text-red-500">*</span></label>
                        <p class="text-[11px] text-red-600 mb-2">Paciente em situação <strong>red</strong> — o registro dos comentários do dia é obrigatório.</p>
                        @if($huddleAvailable && auth()->user()?->can('conduzir huddle'))
                            <textarea wire:model="comments" rows="3"
                                      placeholder="Descreva o motivo do dia vermelho e as condutas definidas..."
                                      class="w-full text-sm rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]"></textarea>
                            <button type="button" wire:click="saveComments" wire:loading.attr="disabled" wire:target="saveComments"
                                    class="mt-2 text-sm px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003a78] disabled:opacity-60">Salvar comentários</button>
                        @else
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $comments ?: '—' }}</p>
                        @endif
                    </div>
                @endif

                {{-- Auditoria do dia: login e data/hora da última atualização --}}
                @if($filledByLogin || $filledAt)
                    <div class="pt-2 border-t border-gray-100 text-[11px] text-gray-400 flex items-center gap-1.5">
                        <i class="fas fa-clock-rotate-left"></i>
                        <span>Última atualização por
                            <strong class="text-gray-600">{{ $filledByLogin ?? '—' }}</strong>@if($filledByName) ({{ $filledByName }})@endif@if($filledAt) em {{ $filledAt }}@endif
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Rodapé fixo ─────────────────────────────────────────────────── --}}
        <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex justify-end">
            <button type="button" wire:click="closeModal" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">Fechar</button>
        </div>
    </div>
    </div>
</div>
