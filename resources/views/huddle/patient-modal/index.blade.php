<div
    x-data="{ showModal: @entangle('showModal') }"
    x-show="showModal"
    x-cloak
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
    <div class="absolute inset-0 flex items-center justify-center p-2 sm:p-4">
    {{-- Coluna com altura limitada: cabeçalho e rodapé fixos, meio rolável --}}
    <div class="relative w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden bg-white rounded-2xl shadow-2xl font-montserrat">

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
                        <p class="font-semibold text-gray-700">Sem previsão de alta nas próximas 72h — discutir em Round</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">Previsão do Tasy: <strong>{{ $tasyPrevAlta ?? 'não registrada' }}</strong></p>
                    </div>
                @endif

                {{-- Checklist (apenas para pacientes do Huddle) --}}
                @if($within72h)
                    @foreach($checklistItems as $item)
                        @php
                            $code = $item->value;
                            $state = $checklist[$code] ?? ['answer' => null, 'signal' => null, 'responsible' => null, 'due_at' => null];
                            $answer = $state['answer'] ?? null;
                            $signal = $state['signal'] ?? null;
                        @endphp
                        <div class="rounded-xl border border-gray-200 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-gray-800">{{ $item->label() }}</p>
                                @if($signal)
                                    <span class="shrink-0 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full text-white {{ $signal === 'red' ? 'bg-red-500' : 'bg-green-500' }}">
                                        {{ $signal === 'red' ? 'Red' : 'Green' }}
                                    </span>
                                @endif
                            </div>

                            @can('conduzir huddle')
                                <div class="mt-2 flex gap-2">
                                    <button wire:click="answerItem('{{ $code }}', 'sim')"
                                            class="px-3 py-1 rounded-lg text-xs font-medium border {{ $answer === 'sim' ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Sim</button>
                                    <button wire:click="answerItem('{{ $code }}', 'nao')"
                                            class="px-3 py-1 rounded-lg text-xs font-medium border {{ $answer === 'nao' ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">Não</button>
                                </div>

                                @if($signal === 'red')
                                    <p class="mt-2 text-[11px] text-red-600">{{ $item->redAction() }}</p>
                                    @if($item->requiresFollowUp())
                                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <input type="text" wire:model="checklist.{{ $code }}.responsible" placeholder="Responsável (opcional)"
                                                   class="text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                            <input type="date" wire:model="checklist.{{ $code }}.due_at"
                                                   class="text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                            <button wire:click="saveItemDetails('{{ $code }}')"
                                                    class="sm:col-span-2 justify-self-start text-xs px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">Salvar responsável/prazo</button>
                                        </div>
                                    @endif
                                @endif
                            @else
                                <p class="mt-1 text-xs text-gray-500">Resposta: <strong>{{ $answer ? ucfirst($answer) : '—' }}</strong></p>
                            @endcan
                        </div>
                    @endforeach

                    {{-- DPA local --}}
                    <div class="rounded-xl border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-800 mb-2">Previsão de alta (DPA) no Huddle</p>
                        @can('conduzir huddle')
                            <div class="flex flex-wrap items-end gap-2">
                                <input type="date" wire:model="expectedDischarge"
                                       class="text-sm rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                <button wire:click="saveDischarge" class="text-sm px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003a78]">Salvar</button>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">Grava apenas no Huddle — não altera o Tasy.</p>
                        @else
                            <p class="text-sm">{{ $expectedDischarge ?? 'não definida' }}</p>
                        @endcan
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Rodapé fixo ─────────────────────────────────────────────────── --}}
        <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex justify-end">
            <button wire:click="closeModal" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium">Fechar</button>
        </div>
    </div>
    </div>
</div>
