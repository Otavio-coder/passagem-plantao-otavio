{{--
    BETA: Consulta Rápida de Paciente
    Modal global — aberto via: window.dispatchEvent(new CustomEvent('open-patient-quick-search'))
--}}
<div>
    <span x-data @open-patient-quick-search.window="$wire.open()"></span>

    @if($isOpen)
    <div
        x-data
        x-init="$nextTick(() => $refs.searchInput?.focus())"
        class="fixed inset-0 z-[99990] flex items-start justify-center pt-16 px-3 pb-6"
        role="dialog"
        aria-modal="true"
        aria-label="Consulta rápida de paciente"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" wire:click="close" aria-hidden="true"></div>

        {{-- Painel --}}
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden"
             style="max-height: calc(100vh - 5rem)">

            {{-- Header --}}
            <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-[#004D9D] to-[#0066CC]">
                <i class="fas fa-search text-white/70 text-sm"></i>
                <p class="text-sm font-semibold text-white flex-1">
                    Consulta Rápida
                    <span class="ml-1.5 text-[10px] font-medium text-white/60 bg-white/10 rounded px-1.5 py-0.5">BETA</span>
                </p>
                <button wire:click="close" type="button" class="text-white/60 hover:text-white transition-colors p-1 rounded" aria-label="Fechar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Search --}}
            <div class="px-4 pt-3 pb-2 border-b border-gray-100">
                <div class="relative">
                    <input
                        x-ref="searchInput"
                        wire:model.live.debounce.350ms="search"
                        type="text"
                        placeholder="Nome do paciente (mín. 3 caracteres)..."
                        class="w-full pl-4 pr-9 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#004D9D]/20 focus:border-[#004D9D] outline-none transition-all"
                        autocomplete="off"
                        @keydown.escape.window="$wire.close()"
                    >
                    @if(strlen($search) > 0)
                    <button wire:click="$set('search', '')" type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    @endif
                </div>
                @if(strlen($search) > 0 && strlen($search) < 3)
                    <p class="text-[10px] text-gray-400 mt-1 px-1">Digite pelo menos 3 caracteres</p>
                @endif
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto min-h-0">

                {{-- Loading busca --}}
                <div wire:loading wire:target="updatedSearch" class="px-4 py-3 text-center">
                    <div class="inline-flex items-center gap-2 text-xs text-gray-400">
                        <svg class="animate-spin w-3.5 h-3.5 text-[#004D9D]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Buscando...
                    </div>
                </div>

                {{-- Resultados --}}
                @if(!empty($results))
                <div wire:loading.remove wire:target="updatedSearch">
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wide">
                        {{ count($results) }} resultado(s)
                    </p>
                    <div class="divide-y divide-gray-50">
                        @foreach($results as $r)
                        <button
                            wire:click="selectPatient({{ $r['nr_atendimento'] }}, {{ $r['cd_pessoa_fisica'] }})"
                            type="button"
                            class="w-full text-left px-4 py-2.5 hover:bg-blue-50 transition-colors flex items-start gap-3"
                        >
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#004D9D]/10 flex items-center justify-center mt-0.5">
                                <i class="fas fa-user text-[#004D9D] text-xs"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $r['nm_paciente'] }}</p>
                                <p class="text-[10px] text-gray-400">
                                    Pront. {{ $r['nr_prontuario'] ?? '—' }}
                                    @if($r['dt_nascimento'])
                                        · {{ \Carbon\Carbon::parse($r['dt_nascimento'])->age }} anos
                                    @endif
                                </p>
                                <p class="text-[10px] text-gray-500 truncate">
                                    @if($r['leito'])<span class="font-medium">Leito {{ $r['leito'] }}</span> · @endif
                                    {{ $r['setor'] ?? '—' }}
                                </p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-300 text-xs self-center flex-shrink-0"></i>
                        </button>
                        @endforeach
                    </div>
                </div>
                @elseif(strlen($search) >= 3 && empty($results) && !$patient)
                <div wire:loading.remove wire:target="updatedSearch" class="px-4 py-6 text-center text-sm text-gray-400">
                    <i class="fas fa-user-slash text-2xl text-gray-200 mb-2 block"></i>
                    Nenhum paciente internado para "<strong>{{ $search }}</strong>"
                </div>
                @endif

                {{-- Paciente selecionado --}}
                @if($patient)
                <div>
                    <div class="px-4 py-3 bg-blue-50 border-b border-blue-100 flex items-start gap-3">
                        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#004D9D]/15 flex items-center justify-center">
                            <i class="fas fa-user text-[#004D9D] text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $patient['nm_paciente'] }}</p>
                            <p class="text-[10px] text-gray-500">
                                Pront. {{ $patient['nr_prontuario'] ?? '—' }}
                                @if($patient['dt_nascimento']) · {{ \Carbon\Carbon::parse($patient['dt_nascimento'])->age }} anos @endif
                            </p>
                            <p class="text-[10px] text-gray-500 truncate">
                                @if($patient['leito'])<span class="font-medium">Leito {{ $patient['leito'] }}</span> · @endif
                                {{ $patient['setor'] ?? '—' }}
                            </p>
                        </div>
                        <button wire:click="$set('patient', null)" type="button"
                                class="text-gray-400 hover:text-gray-600 flex-shrink-0 p-1" title="Nova busca">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>

                    {{-- Loading pendências --}}
                    <div wire:loading wire:target="selectPatient" class="px-4 py-5 text-center text-xs text-gray-400">
                        <svg class="animate-spin w-4 h-4 text-[#004D9D] inline mr-1.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Carregando pendências...
                    </div>

                    @if($pending !== null)
                    @php $totalPending = count($pending['surgeries']) + count($pending['procedures']) + count($pending['labs']); @endphp

                    <div wire:loading.remove wire:target="selectPatient">
                        @if($totalPending === 0)
                        <div class="px-4 py-6 text-center">
                            <i class="fas fa-check-circle text-3xl text-emerald-400 mb-2 block"></i>
                            <p class="text-sm text-gray-500">Sem pendências nas próximas 24h</p>
                        </div>
                        @else

                        {{-- Cirurgias --}}
                        @if(!empty($pending['surgeries']))
                        <div class="border-t border-gray-100">
                            <div class="px-4 pt-3 pb-1 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"></span>
                                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                                    Cirurgias ({{ count($pending['surgeries']) }})
                                </p>
                            </div>
                            <div class="space-y-1.5 px-3 pb-3">
                                @foreach($pending['surgeries'] as $s)
                                <div class="px-3 py-2 bg-red-50 rounded-lg border border-red-100">
                                    <div class="flex items-start gap-2">
                                        @if($s['horario'])
                                            <span class="text-[11px] font-bold text-red-600 bg-red-100 px-1.5 py-0.5 rounded font-mono flex-shrink-0">{{ $s['horario'] }}</span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-medium text-gray-800 leading-snug">{{ $s['descricao'] }}</p>
                                            @if($s['local'])
                                                <p class="text-[10px] text-gray-500 mt-0.5">
                                                    <i class="fas fa-map-marker-alt text-gray-400 mr-0.5"></i>{{ $s['local'] }}
                                                </p>
                                            @endif
                                            <div class="flex flex-wrap gap-1 mt-0.5">
                                                @if($s['carater'])<span class="text-[9px] text-red-600 bg-red-100 px-1 py-px rounded">{{ $s['carater'] }}</span>@endif
                                                @if($s['status'])<span class="text-[9px] text-gray-500 bg-gray-100 px-1 py-px rounded">{{ $s['status'] }}</span>@endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Procedimentos --}}
                        @if(!empty($pending['procedures']))
                        <div class="border-t border-gray-100">
                            <div class="px-4 pt-3 pb-1 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                                    Procedimentos ({{ count($pending['procedures']) }})
                                </p>
                            </div>
                            <div class="space-y-1.5 px-3 pb-3">
                                @foreach($pending['procedures'] as $p)
                                <div class="px-3 py-2 bg-amber-50 rounded-lg border border-amber-100">
                                    <div class="flex items-start gap-2">
                                        @if($p['horario'])
                                            <span class="text-[11px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded font-mono flex-shrink-0">{{ $p['horario'] }}</span>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-medium text-gray-800 leading-snug">{{ $p['descricao'] }}</p>
                                            @if($p['local'])<p class="text-[10px] text-gray-500 mt-0.5"><i class="fas fa-map-marker-alt text-gray-400 mr-0.5"></i>{{ $p['local'] }}</p>@endif
                                            @if($p['status'])<span class="text-[9px] text-gray-500 bg-gray-100 px-1 py-px rounded mt-0.5 inline-block">{{ $p['status'] }}</span>@endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Labs --}}
                        @if(!empty($pending['labs']))
                        <div class="border-t border-gray-100">
                            <div class="px-4 pt-3 pb-1 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-sky-400 flex-shrink-0"></span>
                                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">
                                    Coletas pendentes ({{ count($pending['labs']) }})
                                </p>
                            </div>
                            <div class="pb-3 px-3">
                                @php $labsByGroup = collect($pending['labs'])->groupBy(fn($l) => $l['grupo'] ?? 'Outros'); @endphp
                                @foreach($labsByGroup as $grupo => $labs)
                                <div class="mb-1.5">
                                    @if($labsByGroup->count() > 1)
                                        <p class="text-[9px] font-semibold text-gray-400 uppercase tracking-wide mb-0.5 px-1">{{ $grupo }}</p>
                                    @endif
                                    <div class="bg-sky-50 rounded-lg border border-sky-100 divide-y divide-sky-100">
                                        @foreach($labs as $lab)
                                        <div class="flex items-center justify-between px-3 py-1.5">
                                            <p class="text-xs text-gray-700 flex-1 truncate">{{ $lab['nm_exame'] }}</p>
                                            @if($lab['coleta_prevista'])
                                                <span class="text-[10px] font-mono text-sky-600 flex-shrink-0 ml-2">{{ $lab['coleta_prevista'] }}</span>
                                            @else
                                                <span class="text-[10px] text-gray-400 flex-shrink-0 ml-2">sem horário</span>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @endif {{-- totalPending --}}
                    </div>
                    @endif {{-- pending --}}
                </div>
                @endif {{-- patient --}}

                {{-- Estado inicial --}}
                @if(empty($results) && !$patient && strlen($search) < 3)
                <div class="px-4 py-8 text-center text-xs text-gray-400 space-y-1.5">
                    <i class="fas fa-magnifying-glass text-3xl text-gray-200 block mb-3"></i>
                    <p class="font-medium text-gray-500">Busque pelo nome do paciente</p>
                    <p class="text-gray-300">Mostra cirurgias · procedimentos · coletas<br>pendentes nas próximas 24h</p>
                </div>
                @endif

            </div>

            {{-- Footer --}}
            <div class="px-4 py-1.5 border-t border-gray-100 bg-gray-50 text-[10px] text-gray-400 text-center">
                Apenas pacientes internados · Pendências próximas 24h
            </div>
        </div>
    </div>
    @endif
</div>
