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

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.closeModal()"></div>

    {{-- Camada de centralização --}}
    <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
    <div class="relative bg-white flex flex-col overflow-hidden shadow-2xl font-montserrat
                w-full h-full
                sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                lg:w-[85vw] lg:h-[90vh] lg:max-w-2xl">

        {{-- Cabeçalho (fechar apenas pelo botão do rodapé) --}}
        <div class="shrink-0 bg-[#004D9D] text-white px-4 py-3">
            <p class="font-bold text-base leading-tight"><i class="fas fa-clipboard-check mr-1"></i>Round Unidade</p>
            <p class="text-[11px] text-white/80 truncate">
                {{ $hospitalName ?: '—' }}@if($sectorLabel) · {{ $sectorLabel }}@endif · preenchimento por unidade
            </p>
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

                {{-- Eixo 1 — Ocupação e fluxo --}}
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-1.5">Eixo 1 · Ocupação e fluxo</p>
                    <div class="space-y-1">
                        @foreach($eixo1 as $key => $label)
                            <div class="flex items-center justify-between gap-2 bg-slate-50 rounded-lg px-2.5 py-1.5">
                                <span class="text-xs text-gray-700">{{ $label }}</span>
                                @if($canEditSafety)
                                    <input type="number" min="0" step="1" onkeydown="if(['-','e','E','+'].includes(event.key)) event.preventDefault()" wire:model="safety.{{ $key }}"
                                           class="w-20 text-sm text-right rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
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
                                    <input type="number" min="0" step="1" onkeydown="if(['-','e','E','+'].includes(event.key)) event.preventDefault()" wire:model="safety.{{ $key }}"
                                           class="w-20 text-sm text-right rounded-lg border-gray-300 focus:ring-[#004D9D] focus:border-[#004D9D]">
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
        <div class="shrink-0 border-t border-gray-100 px-4 py-3 flex items-center justify-between gap-2">
            <p class="text-xs text-red-600 font-medium min-w-0 flex-1">
                @if($errorMsg)<i class="fas fa-triangle-exclamation mr-1"></i>{{ $errorMsg }}@endif
            </p>
            <div class="flex gap-2 shrink-0">
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
</div>
