@extends('layouts.app')

@section('content')
<div class="text-[#004D9D]"
     x-data="metricsApp(@js($nurseStats ?? collect()), @js($sectorStats ?? collect()), @js($period ?? 30))">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-[#004D9D]/90 px-4 py-3 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.dashboard') }}"
               class="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0 hover:bg-white/25 transition-colors">
                <i class="fas fa-arrow-left text-white text-sm"></i>
            </a>
            <div>
                <h1 class="text-base font-bold text-white leading-tight">Passagem de Plantão — Análise</h1>
                <p class="text-[10px] text-white/60 mt-0.5">Padrões inferidos da atividade real no sistema</p>
            </div>
        </div>
        <div class="text-[10px] text-white/50">{{ now()->format('d/m/Y') }}</div>
    </div>

    {{-- ── Filtros ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white border-b border-gray-200 px-4 py-3 shadow-sm">
        <form method="GET" action="{{ route('handover.metrics') }}" class="flex flex-wrap items-center gap-3">
            <span class="text-[10px] font-semibold text-gray-500">Período:</span>
            @foreach([7=>'7 dias',30=>'30 dias',90=>'90 dias',180=>'6 meses'] as $val => $lbl)
            <button type="submit" name="period" value="{{ $val }}"
                    class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all {{ ($period==$val) ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]/40 hover:text-[#004D9D]' }}">
                {{ $lbl }}
            </button>
            @endforeach

            @if(isset($sectors) && $sectors->isNotEmpty())
            <div class="flex items-center gap-2 ml-2">
                <span class="text-[10px] font-semibold text-gray-500">Setor:</span>
                <select name="sector" onchange="this.form.submit()"
                        class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                    <option value="">Todos</option>
                    @foreach($sectors as $id => $name)
                    <option value="{{ $id }}" {{ $sectorFilter !== null && $sectorFilter !== '' && $sectorFilter == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                @if($sectorFilter)
                <a href="{{ route('handover.metrics', ['period' => $period]) }}" class="text-[10px] text-gray-400 hover:text-[#004D9D] transition-colors">
                    <i class="fas fa-times"></i> limpar
                </a>
                @endif
            </div>
            @endif
        </form>
    </div>

    <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4 py-4 space-y-4">

    @if($empty ?? true)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
        <i class="fas fa-chart-bar text-gray-200 text-3xl mb-3 block"></i>
        <p class="font-semibold text-gray-500">Nenhuma passagem registrada no período</p>
        <p class="text-xs text-gray-400 mt-1">Os dados aparecem conforme os enfermeiros usam o SBAR e escrevem anotações.</p>
    </div>
    @else

    {{-- ── Por setor ────────────────────────────────────────────────────────── --}}
    @if($sectorStats->isNotEmpty())
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach($sectorStats as $sector)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-gradient-to-r from-[#004D9D]/5 to-transparent flex items-center justify-between border-b border-gray-100">
                <div>
                    <p class="text-sm font-bold text-gray-900">{{ $sector['sector_name'] }}</p>
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $sector['nurses_count'] }} plantonistas · {{ $sector['avg_beds'] }} leitos/sessão</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold text-[#004D9D] tabular-nums">{{ $sector['sessions'] }}</p>
                    <p class="text-[10px] text-gray-400">sessões</p>
                </div>
            </div>
            <div class="px-4 py-3">
                <p class="text-[10px] text-gray-400 mb-2">Distribuição por turno</p>
                <div class="space-y-1.5">
                    @foreach([['M','Manhã','#D97706',$sector['pct_M'],$sector['shift_M']],['T','Tarde','#EA580C',$sector['pct_T'],$sector['shift_T']],['N','Noite','#4F46E5',$sector['pct_N'],$sector['shift_N']]] as [$key,$lbl,$color,$pct,$cnt])
                    <div>
                        <div class="flex justify-between text-[10px] mb-0.5">
                            <span class="text-gray-500">{{ $lbl }}</span>
                            <span class="font-semibold text-gray-700">{{ $cnt }} <span class="text-gray-400 font-normal">({{ $pct }}%)</span></span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ $pct }}%; background:{{ $color }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @if(!empty($sector['nurses']))
                <div class="flex flex-wrap gap-1 mt-3">
                    @foreach(array_slice($sector['nurses'], 0, 5) as $n)
                    <span class="text-[9px] font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ $n }}</span>
                    @endforeach
                    @if(count($sector['nurses']) > 5)
                    <span class="text-[9px] text-gray-400 px-1 py-0.5">+{{ count($sector['nurses']) - 5 }}</span>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── Mapa de calor: distribuição de mensagens por hora ───────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-800">Distribuição de anotações por horário</p>
            <p class="text-[10px] text-gray-400">volume de mensagens escritas em cada hora do turno — período de {{ $period }} dias</p>
        </div>
        <div class="px-4 py-4 space-y-4">
            @foreach($heatmap as $row)
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-[10px] font-bold w-10" style="color:{{ $row['color'] }}">{{ $row['label'] }}</span>
                    <div class="flex gap-0.5 flex-1">
                        @foreach($row['cells'] as $cell)
                        @php $opacity = $cell['pct'] > 0 ? max(15, $cell['pct']) : 0; @endphp
                        <div class="flex-1 flex flex-col items-center gap-0.5 group relative">
                            <div class="w-full rounded-sm transition-all"
                                 style="height:32px; background:{{ $row['color'] }}; opacity:{{ $cell['pct'] > 0 ? ($opacity/100) : '0.06' }}"
                                 title="{{ $cell['hour'] }}h: {{ $cell['count'] }} msgs"></div>
                            @if($cell['count'] > 0)
                            <span class="text-[7px] font-semibold tabular-nums" style="color:{{ $row['color'] }}">{{ $cell['count'] }}</span>
                            @else
                            <span class="text-[7px] text-gray-200">·</span>
                            @endif
                            <span class="text-[7px] text-gray-300">{{ $cell['hour'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Plantonistas ─────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2 justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-800">Plantonistas</p>
                <p class="text-[10px] text-gray-400">
                    {{ $nurseStats->count() }} ativos · clique para ver perfil
                </p>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="text-[9px] text-gray-400">Ordenar:</span>
                @foreach(['sessions'=>'Sessões','avg_messages'=>'Msgs/sess','avg_beds'=>'Leitos'] as $col=>$lbl)
                <button type="button" @click="setSort('{{ $col }}')"
                        :class="sortBy==='{{ $col }}' ? 'bg-[#004D9D] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                        class="text-[9px] font-bold px-2 py-1 rounded-md transition-colors flex items-center gap-0.5">
                    {{ $lbl }}
                    <i class="fas text-[7px]" :class="sortBy==='{{ $col }}' ? (sortDir==='desc'?'fa-arrow-down':'fa-arrow-up') : 'fa-arrow-down opacity-30'"></i>
                </button>
                @endforeach
            </div>
        </div>
        <div class="divide-y divide-gray-50">
            <template x-for="(nurse, idx) in pagedNurses" :key="nurse.user_id">
                <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50/60 transition-colors cursor-pointer group"
                     @click="openNurseModal(nurse.user_id)">
                    <span class="text-xs font-bold text-gray-200 w-6 text-right flex-shrink-0 tabular-nums"
                          x-text="nursePage * nursePerPage + idx + 1"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 truncate" x-text="nurse.name"></p>
                        <p class="text-[10px] text-gray-400 truncate" x-text="nurse.sectors || '—'"></p>
                    </div>
                    <div class="flex items-center gap-4 flex-shrink-0">
                        <div class="text-center hidden sm:block">
                            <p class="text-sm font-bold text-[#004D9D] tabular-nums" x-text="Math.round(nurse.sessions_per_week * 10) / 10 + '/sem'"></p>
                            <p class="text-[9px] text-gray-400">sessões</p>
                        </div>
                        <div class="text-center hidden md:block">
                            <p class="text-sm font-bold text-gray-700 tabular-nums" x-text="Math.round(nurse.avg_messages) || '—'"></p>
                            <p class="text-[9px] text-gray-400">msgs/sess</p>
                        </div>
                        <div class="text-center hidden lg:block">
                            <p class="text-sm font-bold text-gray-700 tabular-nums" x-text="Math.round(nurse.avg_beds) || '—'"></p>
                            <p class="text-[9px] text-gray-400">leitos</p>
                        </div>
                        <template x-if="nurse.shift_pct && Math.min(nurse.shift_pct.M, nurse.shift_pct.T, nurse.shift_pct.N) > 5">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 hidden sm:inline-block">multi-turno</span>
                        </template>
                        <template x-if="nurse.verbosity_label && !(nurse.shift_pct && Math.min(nurse.shift_pct.M, nurse.shift_pct.T, nurse.shift_pct.N) > 5)">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full hidden sm:inline-block"
                                  :class="{'bg-amber-50 text-amber-700':nurse.verbosity_label==='lacônico','bg-emerald-50 text-emerald-700':nurse.verbosity_label==='normal','bg-blue-50 text-blue-700':nurse.verbosity_label==='detalhado'}"
                                  x-text="nurse.verbosity_label"></span>
                        </template>
                        <span class="text-[9px] font-semibold text-gray-300 group-hover:text-[#004D9D] transition-colors">
                            <i class="fas fa-chevron-right text-[8px]"></i>
                        </span>
                    </div>
                </div>
            </template>
        </div>
        {{-- Paginação da tabela --}}
        <div class="px-4 py-2.5 border-t border-gray-100 flex items-center justify-between" x-show="nurseTotalPages > 1">
            <button @click="nursePage = Math.max(0, nursePage - 1)"
                    :disabled="nursePage === 0"
                    class="text-[10px] font-semibold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                <i class="fas fa-chevron-left text-[8px] mr-1"></i>Anterior
            </button>
            <span class="text-[10px] text-gray-400"
                  x-text="(nursePage + 1) + ' / ' + nurseTotalPages + ' · ' + sortedNurses.length + ' plantonistas'"></span>
            <button @click="nursePage = Math.min(nurseTotalPages - 1, nursePage + 1)"
                    :disabled="nursePage >= nurseTotalPages - 1"
                    class="text-[10px] font-semibold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                Próxima<i class="fas fa-chevron-right text-[8px] ml-1"></i>
            </button>
        </div>
    </div>

    @endif {{-- /!empty --}}
    </div>{{-- /px-4 --}}

    {{-- ══ Modal de perfil ══════════════════════════════════════════════════════ --}}
    <div x-show="nurseModal.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-3 pb-4 md:items-center md:p-6"
         @click.self="nurseModal.open = false"
         style="display:none">

        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="nurseModal.open = false"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden" @click.stop>

            <div class="bg-[#004D9D] px-5 py-4 flex items-center gap-3 flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div x-show="!nurseModal.loading && nurseModal.data">
                        <h2 class="text-base font-bold text-white truncate" x-text="nurseModal.data?.name || ''"></h2>
                        <p class="text-xs text-white/70 mt-0.5" x-text="nurseModal.data ? 'Últimos ' + nurseModal.data.period_days + ' dias' : ''"></p>
                    </div>
                    <div x-show="nurseModal.loading" class="flex items-center gap-2 text-white/80 text-sm">
                        <i class="fas fa-circle-notch fa-spin"></i> Carregando...
                    </div>
                </div>
                <button @click="nurseModal.open = false"
                        class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center hover:bg-white/25 transition-colors flex-shrink-0">
                    <i class="fas fa-times text-white text-sm"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 p-4 space-y-4" x-show="!nurseModal.loading && nurseModal.data">

                {{-- KPIs --}}
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-[#004D9D]/5 rounded-xl p-3 text-center border border-[#004D9D]/10">
                        <p class="text-2xl font-bold text-[#004D9D] tabular-nums" x-text="nurseModal.data?.summary?.total_sessions ?? '—'"></p>
                        <p class="text-[10px] text-gray-500 mt-0.5">sessões</p>
                        <p class="text-[10px] text-[#004D9D] font-semibold tabular-nums mt-0.5"
                           x-text="(nurseModal.data?.summary?.sessions_per_week ?? '—') + '/sem'"></p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200">
                        <p class="text-2xl font-bold text-gray-800 tabular-nums"
                           x-text="nurseModal.data?.summary?.avg_beds ?? '—'"></p>
                        <p class="text-[10px] text-gray-500 mt-0.5">leitos/sessão</p>
                        <p class="text-[10px] text-gray-400 tabular-nums mt-0.5"
                           x-text="nurseModal.data?.summary?.avg_messages_per_session ? nurseModal.data.summary.avg_messages_per_session + ' msgs/sess' : '—'"></p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200">
                        <p class="text-2xl font-bold tabular-nums"
                           :class="nurseModal.data?.summary?.verbosity_label==='lacônico'?'text-amber-600':nurseModal.data?.summary?.verbosity_label==='detalhado'?'text-blue-600':'text-emerald-600'"
                           x-text="nurseModal.data?.summary?.avg_chars_per_message ? nurseModal.data.summary.avg_chars_per_message + 'ch' : '—'"></p>
                        <p class="text-[10px] text-gray-500 mt-0.5">chars/msg</p>
                        <p class="text-[10px] font-semibold mt-0.5"
                           :class="nurseModal.data?.summary?.verbosity_label==='lacônico'?'text-amber-600':nurseModal.data?.summary?.verbosity_label==='detalhado'?'text-blue-600':'text-emerald-600'"
                           x-text="nurseModal.data?.summary?.verbosity_label"></p>
                    </div>
                </div>

                {{-- Análise de escrita --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800">Análise de escrita</p>
                    </div>
                    <div class="px-4 py-3">
                        <template x-if="nurseModal.data?.summary?.total_messages > 0">
                            <div class="space-y-3">
                                {{-- Barras de distribuição por tamanho --}}
                                <div class="space-y-1.5">
                                    <template x-for="vItem in [
                                        { label: 'Lacônicas (< 60 ch)', key: 'count_laconic', color: 'bg-amber-400' },
                                        { label: 'Normais (60–200 ch)', key: 'count_normal', color: 'bg-emerald-500' },
                                        { label: 'Detalhadas (> 200 ch)', key: 'count_verbose', color: 'bg-blue-500' }
                                    ]" :key="vItem.key">
                                        <div>
                                            <div class="flex justify-between text-[10px] mb-0.5">
                                                <span class="text-gray-500" x-text="vItem.label"></span>
                                                <span class="font-semibold text-gray-700 tabular-nums"
                                                      x-text="nurseModal.data.summary[vItem.key] + ' (' + Math.round(nurseModal.data.summary[vItem.key] / nurseModal.data.summary.total_messages * 100) + '%)'"></span>
                                            </div>
                                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full" :class="vItem.color"
                                                     :style="'width: ' + Math.round(nurseModal.data.summary[vItem.key] / nurseModal.data.summary.total_messages * 100) + '%'"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Métricas qualitativas --}}
                                <div class="grid grid-cols-3 gap-2 pt-1 border-t border-gray-100">
                                    {{-- Consistência --}}
                                    <div class="text-center">
                                        <p class="text-[9px] text-gray-400 mb-0.5">Consistência</p>
                                        <p class="text-xs font-bold tabular-nums"
                                           :class="nurseModal.data.summary.consistency_label === 'consistente' ? 'text-emerald-600' : nurseModal.data.summary.consistency_label === 'variável' ? 'text-amber-500' : 'text-red-500'"
                                           x-text="nurseModal.data.summary.consistency_label ?? '—'"></p>
                                        <p class="text-[9px] text-gray-300 tabular-nums"
                                           x-text="nurseModal.data.summary.consistency_cv != null ? 'CV ' + nurseModal.data.summary.consistency_cv : ''"
                                           title="Coeficiente de variação do tamanho das mensagens. < 0.5 = consistente, 0.5–1.0 = variável, > 1.0 = irregular"></p>
                                    </div>
                                    {{-- Riqueza clínica --}}
                                    <div class="text-center">
                                        <p class="text-[9px] text-gray-400 mb-0.5" title="% de mensagens que contêm números — proxy para menção de valores clínicos (sinais vitais, exames, doses)">Riqueza clínica</p>
                                        <p class="text-xs font-bold tabular-nums"
                                           :class="nurseModal.data.summary.clinical_richness_pct >= 60 ? 'text-emerald-600' : nurseModal.data.summary.clinical_richness_pct >= 30 ? 'text-amber-500' : 'text-gray-400'"
                                           x-text="nurseModal.data.summary.clinical_richness_pct + '%'"></p>
                                        <p class="text-[9px] text-gray-300">msgs c/ valores</p>
                                    </div>
                                    {{-- Percentil entre pares --}}
                                    <div class="text-center">
                                        <p class="text-[9px] text-gray-400 mb-0.5" title="Percentil de tamanho médio de mensagem comparado com os demais plantonistas no período">Entre pares</p>
                                        <p class="text-xs font-bold tabular-nums"
                                           :class="nurseModal.data.summary.peer_percentile >= 70 ? 'text-blue-600' : nurseModal.data.summary.peer_percentile >= 40 ? 'text-gray-600' : 'text-gray-400'"
                                           x-text="nurseModal.data.summary.peer_percentile != null ? 'P' + nurseModal.data.summary.peer_percentile : '—'"></p>
                                        <p class="text-[9px] text-gray-300">percentil</p>
                                    </div>
                                </div>

                                {{-- Comparação com média global --}}
                                <template x-if="nurseModal.data.summary.global_avg_chars > 0">
                                    <p class="text-[10px] text-gray-400 pt-1 border-t border-gray-100"
                                       x-text="'Média global: ' + nurseModal.data.summary.global_avg_chars + ' ch/msg — escreve ' + (nurseModal.data.summary.avg_chars_per_message > nurseModal.data.summary.global_avg_chars ? Math.round((nurseModal.data.summary.avg_chars_per_message / nurseModal.data.summary.global_avg_chars - 1) * 100) + '% mais' : Math.round((1 - nurseModal.data.summary.avg_chars_per_message / nurseModal.data.summary.global_avg_chars) * 100) + '% menos') + ' que a média.'"></p>
                                </template>
                            </div>
                        </template>
                        <template x-if="!nurseModal.data?.summary?.total_messages">
                            <p class="text-xs text-gray-400 text-center py-3">Sem anotações no período.</p>
                        </template>
                    </div>
                </div>

                {{-- Distribuição por turno --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-800">Distribuição por turno</p>
                    </div>
                    <div class="px-4 py-3">
                        <template x-if="nurseModal.data?.shift_distribution">
                            <div class="flex gap-2">
                                <template x-for="(count, key) in nurseModal.data.shift_distribution" :key="key">
                                    <div class="flex-1 text-center bg-[#004D9D]/5 rounded-lg py-3">
                                        <p class="text-lg font-bold text-[#004D9D] tabular-nums" x-text="count"></p>
                                        <p class="text-[10px] text-gray-400" x-text="key==='M'?'Manhã':key==='T'?'Tarde':'Noite'"></p>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Passagens recentes com paginação --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">Passagens</p>
                        <span class="text-[10px] text-gray-400"
                              x-text="(nurseModal.data?.recent_sessions?.length || 0) + ' registradas'"></span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-[10px] text-gray-500">
                                    <th class="px-3 py-2 text-left font-semibold">Data / Turno</th>
                                    <th class="px-3 py-2 text-left font-semibold">Setor</th>
                                    <th class="px-3 py-2 text-right font-semibold">Leitos</th>
                                    <th class="px-3 py-2 text-right font-semibold">Msgs</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="(s, i) in pagedSessions" :key="i">
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="px-3 py-2.5">
                                            <p class="font-semibold text-gray-700 tabular-nums" x-text="s.started_at"></p>
                                            <p class="text-[10px] text-gray-400" x-text="s.shift"></p>
                                        </td>
                                        <td class="px-3 py-2.5 text-gray-500 max-w-[110px] truncate" x-text="s.sector_name"></td>
                                        <td class="px-3 py-2.5 text-right font-semibold text-gray-700 tabular-nums" x-text="s.beds_visited"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums"
                                            :class="s.messages_written===0?'text-gray-300':'text-gray-600'"
                                            x-text="s.messages_written||'—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-2 border-t border-gray-100 flex items-center justify-between"
                         x-show="sessionTotalPages > 1">
                        <button @click="sessionPage = Math.max(0, sessionPage - 1)"
                                :disabled="sessionPage === 0"
                                class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-chevron-left text-[8px] mr-1"></i>Anterior
                        </button>
                        <span class="text-[10px] text-gray-400"
                              x-text="(sessionPage + 1) + ' / ' + sessionTotalPages"></span>
                        <button @click="sessionPage = Math.min(sessionTotalPages - 1, sessionPage + 1)"
                                :disabled="sessionPage >= sessionTotalPages - 1"
                                class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                            Próxima<i class="fas fa-chevron-right text-[8px] ml-1"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('metricsApp', (nurseStats, sectorStats, period) => ({
        nurseStats: nurseStats,
        sectorStats: sectorStats,
        period: period,
        sortBy: 'sessions',
        sortDir: 'desc',
        nursePage: 0,
        nursePerPage: 10,
        nurseModal: { open: false, loading: false, data: null },
        sessionPage: 0,
        sessionPerPage: 15,

        get sortedNurses() {
            return [...this.nurseStats].sort((a, b) => {
                const av = a[this.sortBy] ?? (this.sortDir==='desc' ? -Infinity : Infinity);
                const bv = b[this.sortBy] ?? (this.sortDir==='desc' ? -Infinity : Infinity);
                return this.sortDir === 'desc' ? bv - av : av - bv;
            });
        },
        get pagedNurses() {
            return this.sortedNurses.slice(this.nursePage * this.nursePerPage, (this.nursePage + 1) * this.nursePerPage);
        },
        get nurseTotalPages() {
            return Math.ceil(this.sortedNurses.length / this.nursePerPage);
        },
        get pagedSessions() {
            const all = this.nurseModal.data?.recent_sessions || [];
            return all.slice(this.sessionPage * this.sessionPerPage, (this.sessionPage + 1) * this.sessionPerPage);
        },
        get sessionTotalPages() {
            return Math.ceil((this.nurseModal.data?.recent_sessions?.length || 0) / this.sessionPerPage);
        },

        setSort(col) {
            if (this.sortBy === col) { this.sortDir = this.sortDir==='desc'?'asc':'desc'; }
            else { this.sortBy = col; this.sortDir = 'desc'; }
            this.nursePage = 0;
        },

        async openNurseModal(userId) {
            this.nurseModal.open = true;
            this.nurseModal.loading = true;
            this.nurseModal.data = null;
            this.sessionPage = 0;
            try {
                const r = await fetch(`/administracao/panorama/passagens/metricas/enfermeiro/${userId}?period=${this.period}`,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                if (!r.ok) throw new Error();
                this.nurseModal.data = await r.json();
            } catch { this.nurseModal.data = null; }
            finally { this.nurseModal.loading = false; }
        },
    }));
});
</script>
@endsection
