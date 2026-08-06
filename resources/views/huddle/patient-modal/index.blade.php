<div
    x-data="{ showModal: @entangle('showModal') }"
    x-show="showModal"
    x-cloak
    x-effect="document.body.style.overflow = showModal ? 'hidden' : ''"
    class="fixed inset-0 z-[9998]"
    @keydown.escape.window="$wire.closeModal()"
    @huddle-scroll-safety.window="setTimeout(() => document.getElementById('huddle-safety-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150)"
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
                                    <p class="mt-2 text-[11px] text-red-600">{{ $item->redAction() }}</p>
                                    @if($item->requiresFollowUp())
                                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            <input type="text" wire:model="checklist.{{ $code }}.responsible" placeholder="Responsável (opcional)"
                                                   class="text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                            <input type="date" wire:model="checklist.{{ $code }}.due_at"
                                                   class="text-xs rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                            <button type="button" wire:click="saveItemDetails('{{ $code }}')"
                                                    wire:loading.attr="disabled" wire:target="saveItemDetails"
                                                    class="sm:col-span-2 justify-self-start text-xs px-3 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 disabled:opacity-60">Salvar responsável/prazo</button>
                                        </div>
                                    @endif
                                @endif
                            @else
                                <p class="mt-1 text-xs text-gray-500">Resposta: <strong>{{ $answer ? ucfirst($answer) : '—' }}</strong></p>
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

                {{-- ── Card do Huddle de Segurança (Eixos 1-4) ─────────────────── --}}
                @php
                    $canEditSafety = $huddleAvailable && auth()->user()?->can('conduzir huddle');
                    $eixo1 = [
                        'expected_discharges' => 'Altas Previstas',
                        'expected_admissions' => 'Admissões Previstas',
                        'blocked_beds_isolation' => 'Leitos Bloqueados por isolamento',
                        'blocked_beds_maintenance' => 'Leitos Bloqueados por manutenção',
                    ];
                    $eixo2bool = [
                        'critical_patient_no_bed' => 'Paciente Grave sem leito?',
                        'critical_medication_failure' => 'Falha de Medicação Crítica?',
                        'adverse_event_24h' => 'Evento adverso (24h)',
                        'physical_chemical_restraint' => 'Contenção Física e/ou Química',
                        'barrier_breach' => 'Quebra de barreira?',
                    ];
                    $eixo2num = ['pressure_injuries' => 'LPP', 'falls' => 'Quedas'];
                    $eixo3bool = [
                        'staff_shortage' => 'Déficit de Equipe',
                        'critical_exam_delay' => 'Atraso de Exame Crítico',
                    ];
                @endphp

                <div id="huddle-safety-card" class="rounded-xl border border-[#004D9D]/20 bg-white overflow-hidden scroll-mt-2">
                    <div class="bg-[#004D9D]/5 px-3 py-2 border-b border-[#004D9D]/10">
                        <p class="text-sm font-bold text-[#004D9D]"><i class="fas fa-clipboard-check mr-1"></i>Huddle de Segurança</p>
                    </div>

                    <div class="p-3 space-y-4">

                        {{-- Eixo 1 — Ocupação e fluxo --}}
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Eixo 1 · Ocupação e fluxo</p>
                            <div class="space-y-1">
                                @foreach($eixo1 as $key => $label)
                                    <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                        <span class="text-xs text-gray-700">{{ $label }}</span>
                                        @if($canEditSafety)
                                            <input type="number" min="0" wire:model="safety.{{ $key }}"
                                                   class="w-16 text-sm text-right rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                        @else
                                            <strong class="text-sm text-gray-800">{{ $safety[$key] ?? '—' }}</strong>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Eixo 2 — Risco clínico e segurança --}}
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Eixo 2 · Risco clínico e segurança</p>
                            <div class="space-y-1">
                                @foreach($eixo2bool as $key => $label)
                                    @php $val = $safety[$key] ?? null; @endphp
                                    <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                        <span class="text-xs text-gray-700">{{ $label }}</span>
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
                                @endforeach
                                @foreach($eixo2num as $key => $label)
                                    <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                        <span class="text-xs text-gray-700">{{ $label }}</span>
                                        @if($canEditSafety)
                                            <input type="number" min="0" wire:model="safety.{{ $key }}"
                                                   class="w-16 text-sm text-right rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
                                        @else
                                            <strong class="text-sm text-gray-800">{{ $safety[$key] ?? '—' }}</strong>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Eixo 3 — Condições operacionais --}}
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Eixo 3 · Condições operacionais</p>
                            <div class="space-y-1">
                                @foreach($eixo3bool as $key => $label)
                                    @php $val = $safety[$key] ?? null; @endphp
                                    <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                        <span class="text-xs text-gray-700">{{ $label }}</span>
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
                                @endforeach
                            </div>
                        </div>

                        {{-- Eixo 4 — Classificação --}}
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Eixo 4 · Classificação</p>

                            <p class="text-xs font-medium text-gray-700 mb-1">Classificação da unidade</p>
                            @if($canEditSafety)
                                <div class="flex gap-1.5 mb-3">
                                    @foreach(['verde' => 'Verde', 'amarelo' => 'Amarelo', 'vermelho' => 'Vermelho'] as $val => $lbl)
                                        @php
                                            $active = ($safety['unit_classification'] ?? null) === $val;
                                            $activeCls = $val === 'verde' ? 'bg-green-500 text-white border-green-500' : ($val === 'amarelo' ? 'bg-amber-400 text-amber-950 border-amber-400' : 'bg-red-500 text-white border-red-500');
                                        @endphp
                                        <button type="button" wire:click="$set('safety.unit_classification', '{{ $val }}')" wire:loading.attr="disabled"
                                                class="flex-1 px-2 py-1.5 rounded-lg text-xs font-semibold border {{ $active ? $activeCls : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">{{ $lbl }}</button>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-gray-800 mb-3">{{ ucfirst($safety['unit_classification'] ?? '—') }}</p>
                            @endif

                            <label class="block text-xs font-medium text-gray-700 mb-1">Justificativa</label>
                            @if($canEditSafety)
                                <textarea wire:model="safety.justification" rows="2"
                                          class="w-full text-sm rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D] mb-3"></textarea>
                            @else
                                <p class="text-sm text-gray-700 whitespace-pre-line mb-3">{{ $safety['justification'] ?: '—' }}</p>
                            @endif

                            <label class="block text-xs font-medium text-gray-700 mb-1">Medidas Imediatas</label>
                            @if($canEditSafety)
                                <textarea wire:model="safety.immediate_measures" rows="2"
                                          class="w-full text-sm rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]"></textarea>
                            @else
                                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $safety['immediate_measures'] ?: '—' }}</p>
                            @endif
                        </div>

                        @if($canEditSafety)
                            <button type="button" wire:click="saveSafetyAssessment" wire:loading.attr="disabled" wire:target="saveSafetyAssessment"
                                    class="w-full text-sm px-3 py-2 rounded-lg bg-[#004D9D] text-white hover:bg-[#003a78] disabled:opacity-60 font-medium">
                                Salvar Huddle de Segurança
                            </button>
                        @endif
                    </div>
                </div>

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
