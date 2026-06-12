<div class="space-y-8">

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- NÍVEL 1 — VISÃO INSTITUCIONAL (inclui filtros no header) --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div class="overflow-hidden">

        {{-- ── Header com filtros ── --}}
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 mr-1">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">Panorama</h2>
            </div>
            <span class="text-[10px] font-semibold text-gray-400">Período:</span>
            <button type="button" wire:click="setPeriod(null)"
                    class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all {{ $period === null ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]/40 hover:text-[#004D9D]' }}">
                Tudo
            </button>
            @foreach([7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 180 => '6 meses'] as $val => $lbl)
            <button type="button" wire:click="setPeriod({{ $val }})"
                    class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all {{ $period === $val ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]/40 hover:text-[#004D9D]' }}">
                {{ $lbl }}
            </button>
            @endforeach

            @if($sectors->isNotEmpty())
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-semibold text-gray-400">Setor:</span>
                <select wire:change="setSector($event.target.value || null)"
                        class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                    <option value="">Todos</option>
                    @foreach($sectors as $id => $name)
                    <option value="{{ $id }}" {{ $sectorFilter == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <span class="ml-auto text-[10px] text-gray-400">
                <span wire:loading wire:target="setPeriod,setSector" class="text-[#004D9D]">
                    <svg class="animate-spin mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1em;height:1em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Carregando...
                </span>
                <span wire:loading.remove>
                    {{ $sessions->count() }} sessões · {{ $institutionalStats['active_nurses'] }} plantonistas
                </span>
            </span>
        </div>

        @if($sessions->isEmpty())
        <div class="p-12 text-center">
            <i class="fas fa-chart-bar text-gray-200 text-3xl mb-3 block"></i>
            <p class="font-semibold text-gray-500">Nenhuma passagem registrada no período</p>
            <p class="text-xs text-gray-400 mt-1">Dados aparecem conforme os enfermeiros usam o SBAR.</p>
        </div>
        @else

        {{-- ── Conteúdo ── --}}
        <div class="p-5 md:p-7">
        <div class="flex items-start gap-2 mb-5">
            <div class="w-1 h-4 rounded-full bg-[#004D9D] mt-1 flex-shrink-0"></div>
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Adoção do sistema</h2>
                <span class="text-xs text-gray-400">sessões, plantonistas e anotações no período selecionado</span>
            </div>
        </div>

        {{-- KPIs principais --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 mb-4">
            @php $is = $institutionalStats; @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400">Sessões</p>
                <p class="text-2xl font-bold text-[#004D9D] tabular-nums">{{ number_format($is['total_sessions']) }}</p>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ $is['active_nurses'] }} plantonistas</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400">Pacientes cobertos</p>
                <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ number_format($is['unique_patients']) }}</p>
                <p class="text-[10px] text-gray-400 mt-0.5">com ≥ 1 passagem</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400">Total de anotações</p>
                <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ number_format($is['total_messages']) }}</p>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ $is['avg_msgs_per_patient'] ?? '—' }} por paciente</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400">Pacientes/sessão</p>
                <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ $is['avg_patients_per_session'] ?? '—' }}</p>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ $is['avg_msgs_per_session'] ?? '—' }} anot./sessão</p>
            </div>
            @php $ct = $continuityStats; @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3"
                 title="Pacientes que receberam anotações em 2 ou mais turnos distintos">
                <p class="text-[10px] text-gray-400">Continuidade entre turnos</p>
                @php $cr = $ct['rate'] ?? null; @endphp
                <p class="text-2xl font-bold tabular-nums {{ $cr >= 60 ? 'text-emerald-600' : ($cr >= 30 ? 'text-amber-600' : 'text-red-500') }}">
                    {{ $cr !== null ? $cr.'%' : '—' }}
                </p>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ $ct['continuous_patients'] ?? 0 }}/{{ $ct['total_patients'] ?? 0 }} pacientes</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400">Duração média</p>
                <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ $is['avg_duration_min'] ? $is['avg_duration_min'].'min' : '—' }}</p>
                <p class="text-[10px] text-gray-400 mt-0.5">por sessão</p>
            </div>
        </div>

        {{-- ── Calendar heatmap — atividade diária ── --}}
        @if(!empty($annotationsByDay))
        @php
            // Determina start date e range de meses para exibição
            if ($period === null) {
                $calRangeMonths = 12;
                $calStart = now()->subMonths(11)->startOfMonth()->toDateString();
            } else {
                $calRangeMonths = match(true) {
                    $period === 7   => 1,
                    $period === 30  => 2,
                    $period === 90  => 4,
                    $period === 180 => 7,
                    default         => 2,
                };
                $calStart = now()->subDays($period)->startOfMonth()->toDateString();
            }
            $calMax = max(array_column($annotationsByDay, 'value'));
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mt-3">
            @if($aiActivityAnalysis)
            {{-- AI analysis panel --}}
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Atividade diária</p>
                    <p class="text-[10px] text-gray-400">GPT-4o-mini · análise crítica</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button wire:click="clearActivityAnalysis"
                            class="text-[11px] font-semibold text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition-colors">
                        Fechar
                    </button>
                    <button wire:click="analyzeActivityWithAi"
                            wire:loading.attr="disabled"
                            wire:target="analyzeActivityWithAi"
                            class="text-[11px] font-semibold text-[#0071B9] border border-[#0071B9]/30 rounded-lg px-2.5 py-1.5 hover:bg-[#0071B9]/5 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="analyzeActivityWithAi">Reanalisar</span>
                        <svg wire:loading wire:target="analyzeActivityWithAi" class="animate-spin inline" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </button>
                </div>
            </div>
            <div wire:loading wire:target="analyzeActivityWithAi" class="flex items-center justify-center py-8">
                <div class="text-center">
                    <svg class="animate-spin text-[#004D9D] mb-2 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1.5em;height:1.5em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    <p class="text-xs text-gray-500">Analisando com IA...</p>
                </div>
            </div>
            <div wire:loading.remove wire:target="analyzeActivityWithAi"
                 class="prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($aiActivityAnalysis ?? '') !!}</div>
            @else
            {{-- Chart --}}
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Atividade diária</p>
                    <p class="text-xs text-gray-400">total de anotações escritas por dia — intensidade = volume; cada quadrado = 1 dia</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 text-[10px] text-gray-400">
                        <span>menos</span>
                        <div class="flex gap-0.5">
                            @foreach(['#FFF9C4','#FFCA28','#FF8F00','#E64A19','#B71C1C'] as $swatch)
                            <div class="w-3 h-3 rounded-sm" style="background:{{ $swatch }}"></div>
                            @endforeach
                        </div>
                        <span>mais</span>
                    </div>
                    <button wire:click="analyzeActivityWithAi"
                            wire:loading.attr="disabled"
                            wire:target="analyzeActivityWithAi"
                            class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                        <span wire:loading.remove wire:target="analyzeActivityWithAi">Análise IA</span>
                        <span wire:loading wire:target="analyzeActivityWithAi">Analisando...</span>
                    </button>
                </div>
            </div>
            <div
                wire:ignore
                x-data="(function() {
                    let _cal = null;
                    return {
                        container: null,
                        data: @js($annotationsByDay),
                        start: '{{ $calStart }}',
                        range: {{ $calRangeMonths }},
                        maxVal: {{ max(1, $calMax) }},
                        paint() {
                            if (!window.CalHeatmap || !this.container) return;
                            if (_cal) { try { _cal.destroy(); } catch(e) {} _cal = null; }
                            this.container.innerHTML = '';
                            if (!this.data.length) return;
                            const _startDate = (function(s){ const p=s.split('-').map(Number); return new Date(p[0],p[1]-1,p[2]); })(this.start);
                            const _data = this.data.map(function(d){ const p=d.date.split('-').map(Number); return {ts: new Date(p[0],p[1]-1,p[2],12).getTime(), value: d.value}; });
                            const _vals = _data.map(d=>d.value).filter(v=>v>0);
                            const _domainMin = _vals.length ? Math.min(..._vals) : 1;
                            const _domainMax = _vals.length ? Math.max(..._vals) : 3;
                            const _monthTotals = {};
                            _data.forEach(function(d){ const dt=new Date(d.ts); const k=dt.getUTCFullYear()+'-'+String(dt.getUTCMonth()+1).padStart(2,'0'); _monthTotals[k]=(_monthTotals[k]||0)+d.value; });
                            _cal = new CalHeatmap();
                            _cal.paint({
                                itemSelector: this.container,
                                data: { source: _data, x: 'ts', y: 'value', groupY: 'sum' },
                                date: { start: _startDate },
                                range: this.range,
                                domain: {
                                    type: 'month',
                                    gutter: 6,
                                    label: {
                                        text: (ts) => { const d=new Date(ts); const k=d.getUTCFullYear()+'-'+String(d.getUTCMonth()+1).padStart(2,'0'); const lbl=d.toLocaleDateString('pt-BR',{month:'short',timeZone:'UTC'}); const n=_monthTotals[k]||0; return n>0?lbl+' ('+n+')':lbl; },
                                        position: 'top',
                                        textAlign: 'start',
                                    },
                                },
                                subDomain: { type: 'day', radius: 2, width: 13, height: 13, gutter: 2 },
                                scale: {
                                    color: {
                                        type: 'linear',
                                        range: ['#FFF9C4', '#B71C1C'],
                                        domain: [_domainMin, _domainMax],
                                    },
                                },
                            });
                        }
                    };
                })()"
                x-init="container = $el; paint()"
                x-on:cal-heatmap-update.window="
                    data = $event.detail.data;
                    start = $event.detail.start;
                    range = $event.detail.range;
                    maxVal = $event.detail.maxVal;
                    paint();
                "
                class="overflow-x-auto flex justify-center">
            </div>
            @endif
        </div>
        @endif

        {{-- Distribuição por turno + Classificação de conteúdo --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">

            {{-- Turnos --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                @if($aiShiftAnalysis)
                {{-- AI analysis panel --}}
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Sessões por turno</p>
                        <p class="text-[10px] text-gray-400">GPT-4o-mini · análise crítica</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="clearShiftAnalysis"
                                class="text-[11px] font-semibold text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition-colors">
                            Fechar
                        </button>
                        <button wire:click="analyzeShiftStatsWithAi"
                                wire:loading.attr="disabled"
                                wire:target="analyzeShiftStatsWithAi"
                                class="text-[11px] font-semibold text-[#0071B9] border border-[#0071B9]/30 rounded-lg px-2.5 py-1.5 hover:bg-[#0071B9]/5 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="analyzeShiftStatsWithAi">Reanalisar</span>
                            <svg wire:loading wire:target="analyzeShiftStatsWithAi" class="animate-spin inline" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </button>
                    </div>
                </div>
                <div wire:loading wire:target="analyzeShiftStatsWithAi" class="flex items-center justify-center py-8">
                    <div class="text-center">
                        <svg class="animate-spin text-[#004D9D] mb-2 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1.5em;height:1.5em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <p class="text-xs text-gray-500">Analisando com IA...</p>
                    </div>
                </div>
                <div wire:loading.remove wire:target="analyzeShiftStatsWithAi"
                     class="prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($aiShiftAnalysis ?? '') !!}</div>
                @else
                {{-- Chart --}}
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-700">Sessões por turno</p>
                        <p class="text-[10px] text-gray-400">distribuição de sessões entre manhã (7–13h), tarde (13–19h) e noite (19–7h)</p>
                    </div>
                    <button wire:click="analyzeShiftStatsWithAi"
                            wire:loading.attr="disabled"
                            wire:target="analyzeShiftStatsWithAi"
                            class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                        <span wire:loading.remove wire:target="analyzeShiftStatsWithAi">Análise IA</span>
                        <span wire:loading wire:target="analyzeShiftStatsWithAi">Analisando...</span>
                    </button>
                </div>
                <div class="space-y-2.5">
                    @foreach([['M','Manhã','#D97706',$shiftStats['M'],$shiftStats['pct_M']],['T','Tarde','#EA580C',$shiftStats['T'],$shiftStats['pct_T']],['N','Noite','#4F46E5',$shiftStats['N'],$shiftStats['pct_N']]] as [$key,$lbl,$color,$cnt,$pct])
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="font-semibold" style="color:{{ $color }}">{{ $lbl }}</span>
                            <span class="text-gray-600 tabular-nums">{{ $cnt }} <span class="text-gray-400">({{ $pct }}%)</span></span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ $pct }}%; background:{{ $color }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Heatmap --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                @if($aiHourlyAnalysis)
                {{-- AI analysis panel --}}
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Volume por hora</p>
                        <p class="text-[10px] text-gray-400">GPT-4o-mini · análise crítica</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="clearHourlyAnalysis"
                                class="text-[11px] font-semibold text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition-colors">
                            Fechar
                        </button>
                        <button wire:click="analyzeHourlyWithAi"
                                wire:loading.attr="disabled"
                                wire:target="analyzeHourlyWithAi"
                                class="text-[11px] font-semibold text-[#0071B9] border border-[#0071B9]/30 rounded-lg px-2.5 py-1.5 hover:bg-[#0071B9]/5 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="analyzeHourlyWithAi">Reanalisar</span>
                            <svg wire:loading wire:target="analyzeHourlyWithAi" class="animate-spin inline" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </button>
                    </div>
                </div>
                <div wire:loading wire:target="analyzeHourlyWithAi" class="flex items-center justify-center py-8">
                    <div class="text-center">
                        <svg class="animate-spin text-[#004D9D] mb-2 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1.5em;height:1.5em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <p class="text-xs text-gray-500">Analisando com IA...</p>
                    </div>
                </div>
                <div wire:loading.remove wire:target="analyzeHourlyWithAi"
                     class="prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($aiHourlyAnalysis ?? '') !!}</div>
                @else
                {{-- Chart --}}
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-xs font-semibold text-gray-700 mb-1">Volume por hora</p>
                        <p class="text-[10px] text-gray-400">volume de anotações escritas por hora do dia — mescla chat_messages ativo + histórico arquivado</p>
                    </div>
                    <button wire:click="analyzeHourlyWithAi"
                            wire:loading.attr="disabled"
                            wire:target="analyzeHourlyWithAi"
                            class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                        <span wire:loading.remove wire:target="analyzeHourlyWithAi">Análise IA</span>
                        <span wire:loading wire:target="analyzeHourlyWithAi">Analisando...</span>
                    </button>
                </div>
                <div class="space-y-2">
                    @foreach($heatmap as $row)
                    <div>
                        <span class="text-[10px] font-bold block mb-1" style="color:{{ $row['color'] }}">{{ $row['label'] }}</span>
                        <div class="flex gap-0.5">
                            @foreach($row['cells'] as $cell)
                            @php $opacity = $cell['pct'] > 0 ? max(15, $cell['pct']) : 0; @endphp
                            <div class="flex-1 flex flex-col items-center gap-0.5" title="{{ $cell['hour'] }}h: {{ $cell['count'] }} anot.">
                                <div class="w-full rounded-sm" style="height:22px; background:{{ $row['color'] }}; opacity:{{ $cell['pct'] > 0 ? ($opacity/100) : '0.06' }}"></div>
                                <span class="text-[7px] font-mono text-gray-300">{{ $cell['hour'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Classificação de conteúdo (regex, sem IA) --}}
            @if(!empty($contentClassification))
            @php
                $catColors = [
                    'pendência'            => '#004D9D',
                    'risco'                => '#0071B9',
                    'conduta/procedimento' => '#073772',
                    'alta/evolução'        => '#3B82F6',
                    'condição clínica'     => '#93C5FD',
                ];
                $cumDeg = 0;
                $segments = [];
                foreach ($contentClassification as $cat => $data) {
                    $deg = $data['pct'] * 3.6;
                    $color = $catColors[$cat] ?? '#9CA3AF';
                    $end = $cumDeg + $deg;
                    $segments[] = "{$color} {$cumDeg}deg {$end}deg";
                    $cumDeg = $end;
                }
                $pieCss = 'conic-gradient(' . implode(', ', $segments) . ')';
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                @if($aiContentAnalysis)
                {{-- AI analysis panel --}}
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Tipo de conteúdo registrado</p>
                        <p class="text-[10px] text-gray-400">GPT-4o-mini · análise crítica</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="clearContentAnalysis"
                                class="text-[11px] font-semibold text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition-colors">
                            Fechar
                        </button>
                        <button wire:click="analyzeContentWithAi"
                                wire:loading.attr="disabled"
                                wire:target="analyzeContentWithAi"
                                class="text-[11px] font-semibold text-[#0071B9] border border-[#0071B9]/30 rounded-lg px-2.5 py-1.5 hover:bg-[#0071B9]/5 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="analyzeContentWithAi">Reanalisar</span>
                            <svg wire:loading wire:target="analyzeContentWithAi" class="animate-spin inline" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </button>
                    </div>
                </div>
                <div wire:loading wire:target="analyzeContentWithAi" class="flex items-center justify-center py-8">
                    <div class="text-center">
                        <svg class="animate-spin text-[#004D9D] mb-2 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1.5em;height:1.5em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <p class="text-xs text-gray-500">Analisando com IA...</p>
                    </div>
                </div>
                <div wire:loading.remove wire:target="analyzeContentWithAi"
                     class="prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($aiContentAnalysis ?? '') !!}</div>
                @else
                {{-- Chart --}}
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-0.5">Tipo de conteúdo registrado</p>
                        <p class="text-xs text-gray-400">scoring por keywords clínicas em {{ number_format($contentClassification ? array_sum(array_column($contentClassification, 'count')) : 0) }} anotações — cada mensagem recebe a categoria de maior pontuação</p>
                    </div>
                    <button wire:click="analyzeContentWithAi"
                            wire:loading.attr="disabled"
                            wire:target="analyzeContentWithAi"
                            class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60 ml-2">
                        <span wire:loading.remove wire:target="analyzeContentWithAi">Análise IA</span>
                        <span wire:loading wire:target="analyzeContentWithAi">Analisando...</span>
                    </button>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex-shrink-0 relative w-28 h-28">
                        <div class="w-28 h-28 rounded-full" style="background: {{ $pieCss }};"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-16 h-16 bg-white rounded-full shadow-sm"></div>
                        </div>
                    </div>
                    <div class="flex-1 space-y-1.5">
                        @foreach($contentClassification as $cat => $data)
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-sm flex-shrink-0" style="background:{{ $catColors[$cat] ?? '#9CA3AF' }}"></span>
                            <span class="text-[11px] text-gray-600 capitalize flex-1 truncate">{{ $cat }}</span>
                            <span class="text-[11px] font-semibold text-gray-700 tabular-nums">{{ $data['count'] }} ({{ $data['pct'] }}%)</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- ── Análise de conteúdo ── --}}
        @if(!empty($topTerms) || !empty($charDistribution))
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

            {{-- CARD 1: Termos — nuvem de bolhas SVG + Análise IA --}}
            @if(!empty($topTerms))
            @php $termsForJs = array_slice($topTerms, 0, 50, true); @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex flex-col">

                @if($aiTermsAnalysis)
                {{-- ── Estado: análise pronta ── --}}
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Termos mais registrados</p>
                        <p class="text-[10px] text-gray-400">GPT-4o-mini · análise crítica</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="clearAiAnalysis"
                                class="text-[11px] font-semibold text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition-colors">
                            Fechar
                        </button>
                        <button wire:click="analyzeTermsWithAi"
                                wire:loading.attr="disabled"
                                wire:target="analyzeTermsWithAi"
                                class="text-[11px] font-semibold text-[#0071B9] border border-[#0071B9]/30 rounded-lg px-2.5 py-1.5 hover:bg-[#0071B9]/5 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="analyzeTermsWithAi">Reanalisar</span>
                            <svg wire:loading wire:target="analyzeTermsWithAi" class="animate-spin inline" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </button>
                    </div>
                </div>
                <div wire:loading wire:target="analyzeTermsWithAi" class="flex-1 flex items-center justify-center py-8">
                    <svg class="animate-spin text-[#004D9D]" style="width:1.25rem;height:1.25rem" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </div>
                <div wire:loading.remove wire:target="analyzeTermsWithAi"
                     class="flex-1 prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($aiTermsAnalysis ?? '') !!}</div>

                @else
                {{-- ── Estado: nuvem de bolhas + botão IA ── --}}
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Termos mais registrados</p>
                        <p class="text-xs text-gray-400">top 50 termos — tamanho = nº de mensagens que contêm o termo; posição = TF-IDF (termos distintivos têm prioridade sobre frequentes genéricos)</p>
                    </div>
                    <button wire:click="analyzeTermsWithAi"
                            wire:loading.attr="disabled"
                            wire:target="analyzeTermsWithAi"
                            class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                        <span wire:loading.remove wire:target="analyzeTermsWithAi">Análise IA</span>
                        <span wire:loading wire:target="analyzeTermsWithAi">Analisando...</span>
                    </button>
                </div>

                <div wire:loading wire:target="analyzeTermsWithAi" class="flex-1 flex items-center justify-center py-8">
                    <svg class="animate-spin text-[#004D9D]" style="width:1.5rem;height:1.5rem" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </div>

                {{-- Nuvem de bolhas — Matter.js physics + SVG render --}}
                <script>
                window.d3BubbleCloud = function(terms) {
                    return {
                        _engine: null,
                        _runner: null,
                        _raf:    null,
                        destroy() {
                            if (this._runner)  window.Matter && Matter.Runner.stop(this._runner);
                            if (this._raf)     cancelAnimationFrame(this._raf);
                        },
                        async init() {
                            await this.$nextTick();
                            const load = (src) => new Promise((res, rej) => {
                                const s = document.createElement('script');
                                s.src = src; s.onload = res; s.onerror = rej;
                                document.head.appendChild(s);
                            });
                            if (!window.d3)     await load('https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js');
                            if (!window.Matter) await load('https://cdn.jsdelivr.net/npm/matter-js@0.19.0/build/matter.min.js');
                            this._draw();
                        },
                        _draw() {
                            const wrap = this.$refs.bubbleWrap;
                            if (!wrap) return;
                            wrap.innerHTML = '';

                            const d3     = window.d3;
                            const Matter = window.Matter;
                            const { Engine, Runner, Bodies, Body, World, Events } = Matter;

                            const W = wrap.getBoundingClientRect().width || 600;
                            const H = 560;

                            const entries = Object.entries(terms);
                            const maxC = Math.max(...entries.map(([, c]) => c));
                            const minC = Math.min(...entries.map(([, c]) => c));

                            const rScale = d3.scaleLog().domain([Math.max(1, minC), maxC]).range([14, 58]).clamp(true);
                            const fillScale = d3.scaleLinear()
                                .domain([0, 0.25, 0.55, 1])
                                .range(['#93C5FD', '#3B82F6', '#0071B9', '#1E3A8A']);

                            // maiores primeiro
                            const sorted = [...entries].sort(([, a], [, b]) => b - a);
                            const bubbles = sorted.map(([term, count]) => ({
                                term, count,
                                r:    rScale(count),
                                fill: fillScale(count / maxC),
                                dark: (count / maxC) >= 0.12,
                            }));

                            // largura do container proporcional à área total das bolhas
                            const totalArea = bubbles.reduce((s, b) => s + Math.PI * b.r * b.r, 0);
                            const CW = Math.min(W - 20, Math.max(320, Math.sqrt(totalArea) * 1.6));
                            const CX = (W - CW) / 2; // centrado no chart

                            // ── Matter.js engine ──────────────────────────────────────────
                            const engine = Engine.create();
                            engine.gravity.y = 1.2;

                            const WALL = 60;
                            const wallOpts = { isStatic: true, friction: 1, restitution: 0, render: { visible: false } };
                            World.add(engine.world, [
                                Bodies.rectangle(CX + CW / 2, H + WALL / 2,       CW + WALL * 2, WALL, wallOpts), // piso
                                Bodies.rectangle(CX - WALL / 2,       H / 2, WALL, H * 3,         wallOpts), // esq
                                Bodies.rectangle(CX + CW + WALL / 2,  H / 2, WALL, H * 3,         wallOpts), // dir
                            ]);

                            // adiciona bolhas escalonadas do centro para fora, maiores primeiro
                            const bodyMap = new Map();
                            bubbles.forEach((b, idx) => {
                                const body = Bodies.circle(
                                    CX + CW / 2 + (Math.random() - 0.5) * 40,
                                    -(b.r * 2) - idx * 10,
                                    b.r,
                                    { restitution: 0.05, friction: 0.8, frictionStatic: 1.0, density: b.r * 0.001 }
                                );
                                bodyMap.set(b.term, body);
                                World.add(engine.world, body);
                            });

                            // ── SVG ───────────────────────────────────────────────────────
                            const tip = d3.select(wrap).append('div')
                                .style('position','absolute').style('z-index','50')
                                .style('background','#0f172a').style('color','#f1f5f9')
                                .style('border-radius','6px').style('padding','6px 12px')
                                .style('font-size','11px').style('font-family','system-ui,sans-serif')
                                .style('pointer-events','none').style('opacity','0')
                                .style('transition','opacity .12s').style('white-space','nowrap')
                                .style('box-shadow','0 4px 16px rgba(0,0,0,.35)');

                            const svg = d3.select(wrap).append('svg')
                                .attr('width', W).attr('height', H)
                                .style('display','block').style('overflow','hidden');

                            // borda do container físico
                            svg.append('rect')
                                .attr('x', CX).attr('y', 0)
                                .attr('width', CW).attr('height', H)
                                .attr('rx', 8).attr('fill','none')
                                .attr('stroke','#cbd5e1').attr('stroke-width', 1);

                            const nodeG = svg.selectAll('g.bnode')
                                .data(bubbles, d => d.term)
                                .join('g').classed('bnode', true)
                                .style('cursor','default')
                                .on('mouseover', function(event, d) {
                                    d3.select(this).select('circle').attr('stroke','#fff').attr('stroke-width', 2.5);
                                    tip.style('opacity','1')
                                        .html(`<strong style="font-size:12px">${d.term}</strong>&ensp;<span style="color:#94a3b8">${d.count.toLocaleString()} msgs</span>`);
                                })
                                .on('mousemove', function(event) {
                                    const b = wrap.getBoundingClientRect();
                                    tip.style('left',(event.clientX - b.left + 14)+'px')
                                       .style('top', (event.clientY - b.top  - 44)+'px');
                                })
                                .on('mouseout', function(event, d) {
                                    d3.select(this).select('circle')
                                        .attr('stroke', d3.color(d.fill).darker(0.8).formatHex())
                                        .attr('stroke-width', 1.5);
                                    tip.style('opacity','0');
                                });

                            nodeG.append('circle')
                                .attr('r', d => d.r)
                                .attr('fill', d => d.fill)
                                .attr('stroke', d => d3.color(d.fill).darker(0.8).formatHex())
                                .attr('stroke-width', 1.5);

                            nodeG.each(function(d) {
                                const g      = d3.select(this);
                                const fs     = Math.max(5, Math.min(11, d.r * 0.30));
                                const fsc    = Math.max(4.5, fs * 0.76);
                                const tc     = d.dark ? '#fff' : '#1e3a8a';
                                const words  = d.term.split(' ');
                                const half   = Math.ceil(words.length / 2);
                                const lines  = words.length > 1
                                    ? [words.slice(0, half).join(' '), words.slice(half).join(' ')]
                                    : [d.term];
                                const showFreq = d.r > 20;
                                const totalH   = lines.length * fs * 1.2 + (showFreq ? fsc * 1.3 : 0);
                                const startY   = -(totalH / 2) + fs * 0.8;

                                const txt = g.append('text')
                                    .attr('text-anchor','middle')
                                    .attr('font-family','system-ui,-apple-system,sans-serif')
                                    .attr('font-size', fs).attr('font-weight','700')
                                    .attr('fill', tc)
                                    .style('pointer-events','none').style('user-select','none');

                                lines.forEach((line, i) =>
                                    txt.append('tspan').attr('x', 0).attr('y', startY + i * fs * 1.2).text(line)
                                );
                                if (showFreq) {
                                    txt.append('tspan')
                                        .attr('x', 0).attr('y', startY + lines.length * fs * 1.2)
                                        .attr('font-size', fsc).attr('font-weight','400').attr('fill-opacity','0.70')
                                        .text(d.count.toLocaleString() + ' msgs');
                                }
                            });

                            // ── loop: Matter step + sync SVG ──────────────────────────────
                            const self    = this;
                            const runner  = Runner.create();
                            Runner.run(runner, engine);
                            this._engine  = engine;
                            this._runner  = runner;

                            function render() {
                                nodeG.attr('transform', function(d) {
                                    const body = bodyMap.get(d.term);
                                    if (!body) return '';
                                    return `translate(${body.position.x},${body.position.y})`;
                                });
                                self._raf = requestAnimationFrame(render);
                            }
                            self._raf = requestAnimationFrame(render);
                        }
                    };
                };
                </script>

                <div wire:loading.remove wire:target="analyzeTermsWithAi"
                     class="flex-1"
                     x-data="window.d3BubbleCloud(@js($termsForJs))"
                     x-init="init()">
                    <div x-ref="bubbleWrap"
                         style="position:relative;width:100%;height:560px"></div>
                </div>

                @endif
            </div>
            @endif

            {{-- CARD 2: Distribuição de escrita — donut CSS + legenda --}}
            @if(!empty($charDistribution))
            @php
                $cd = $charDistribution;
                $pCur = $cd['buckets']['curtas']['pct'];
                $pAde = $cd['buckets']['adequadas']['pct'];
                $pLon = $cd['buckets']['longas']['pct'];
                // Ângulos acumulados para conic-gradient
                $aCur = $pCur * 3.6;        // graus
                $aAde = ($pCur + $pAde) * 3.6;
                // Cores Santa Casa
                $colCur = '#7FA8E9'; // azul claro
                $colAde = '#004D9D'; // azul Santa Casa
                $colLon = '#062047'; // azul escuro
                $donut = "conic-gradient({$colCur} 0deg {$aCur}deg, {$colAde} {$aCur}deg {$aAde}deg, {$colLon} {$aAde}deg 360deg)";
                $avgLabelColor = match($cd['size_label']) {
                    'notas curtas'     => '#F97316',
                    'tamanho adequado' => '#004D9D',
                    default            => '#062047',
                };
                $bucketMeta = [
                    'curtas'    => ['label' => 'Curtas',    'sub' => '< 60 ch',    'color' => $colCur],
                    'adequadas' => ['label' => 'Adequadas', 'sub' => '60–200 ch',  'color' => $colAde],
                    'longas'    => ['label' => 'Longas',    'sub' => '> 200 ch',   'color' => $colLon],
                ];
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex flex-col">
                @if($aiCharDistributionAnalysis)
                {{-- AI analysis panel --}}
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Distribuição de escrita</p>
                        <p class="text-[10px] text-gray-400">GPT-4o-mini · análise crítica</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button wire:click="clearCharDistributionAnalysis"
                                class="text-[11px] font-semibold text-gray-500 border border-gray-200 rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition-colors">
                            Fechar
                        </button>
                        <button wire:click="analyzeCharDistributionWithAi"
                                wire:loading.attr="disabled"
                                wire:target="analyzeCharDistributionWithAi"
                                class="text-[11px] font-semibold text-[#0071B9] border border-[#0071B9]/30 rounded-lg px-2.5 py-1.5 hover:bg-[#0071B9]/5 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="analyzeCharDistributionWithAi">Reanalisar</span>
                            <svg wire:loading wire:target="analyzeCharDistributionWithAi" class="animate-spin inline" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        </button>
                    </div>
                </div>
                <div wire:loading wire:target="analyzeCharDistributionWithAi" class="flex items-center justify-center py-8">
                    <div class="text-center">
                        <svg class="animate-spin text-[#004D9D] mb-2 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1.5em;height:1.5em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <p class="text-xs text-gray-500">Analisando com IA...</p>
                    </div>
                </div>
                <div wire:loading.remove wire:target="analyzeCharDistributionWithAi"
                     class="prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($aiCharDistributionAnalysis ?? '') !!}</div>
                @else
                {{-- Chart --}}
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Distribuição de escrita</p>
                        <p class="text-xs text-gray-400">{{ number_format($cd['total']) }} anotações — distribuição por extensão em caracteres (curta &lt;60, adequada 60–200, longa &gt;200)</p>
                    </div>
                    <button wire:click="analyzeCharDistributionWithAi"
                            wire:loading.attr="disabled"
                            wire:target="analyzeCharDistributionWithAi"
                            class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                        <span wire:loading.remove wire:target="analyzeCharDistributionWithAi">Análise IA</span>
                        <span wire:loading wire:target="analyzeCharDistributionWithAi">Analisando...</span>
                    </button>
                </div>

                {{-- Donut centralizado grande --}}
                <div class="flex justify-center mb-5">
                    <div class="relative w-56 h-56">
                        <div class="w-56 h-56 rounded-full" style="background: {{ $donut }};"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-36 h-36 bg-white rounded-full flex flex-col items-center justify-center shadow-sm">
                                <span class="text-3xl font-bold text-gray-800 tabular-nums leading-none">{{ $cd['avg'] }}</span>
                                <span class="text-xs text-gray-400 leading-none mt-1">chars/msg</span>
                                <span class="text-[11px] font-semibold mt-2 px-2 text-center leading-tight" style="color:{{ $avgLabelColor }}">{{ $cd['size_label'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Buckets: barra larga + contagem --}}
                <div class="space-y-3 mb-4">
                    @foreach($bucketMeta as $key => $meta)
                    @php $b = $cd['buckets'][$key]; @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-sm flex-shrink-0" style="background:{{ $meta['color'] }}"></span>
                                <span class="text-sm font-semibold text-gray-700">{{ $meta['label'] }}</span>
                                <span class="text-xs text-gray-400">{{ $meta['sub'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 tabular-nums">{{ number_format($b['count'] ?? 0) }} msgs</span>
                                <span class="text-sm font-bold tabular-nums w-10 text-right" style="color:{{ $meta['color'] }}">{{ $b['pct'] }}%</span>
                            </div>
                        </div>
                        <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ $b['pct'] }}%;background:{{ $meta['color'] }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- KPIs rodapé --}}
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <div class="bg-gray-50 rounded-lg px-3 py-2.5 text-center">
                        <p class="text-[10px] text-gray-400 mb-0.5">Total msgs</p>
                        <p class="text-base font-bold text-gray-700 tabular-nums">{{ number_format($cd['total']) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg px-3 py-2.5 text-center">
                        <p class="text-[10px] text-gray-400 mb-0.5">Total chars</p>
                        <p class="text-base font-bold text-gray-700 tabular-nums">{{ number_format($cd['total_chars'] ?? 0) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg px-3 py-2.5 text-center">
                        <p class="text-[10px] text-gray-400 mb-0.5">Média/msg</p>
                        <p class="text-base font-bold text-gray-700 tabular-nums">{{ $cd['avg'] }}</p>
                    </div>
                </div>

                {{-- Stacked bar --}}
                <div class="h-3 rounded-full overflow-hidden flex">
                    <div style="width:{{ $pCur }}%; background:{{ $colCur }}" class="h-full" title="Curtas {{ $pCur }}%"></div>
                    <div style="width:{{ $pAde }}%; background:{{ $colAde }}" class="h-full" title="Adequadas {{ $pAde }}%"></div>
                    <div style="width:{{ $pLon }}%; background:{{ $colLon }}" class="h-full" title="Longas {{ $pLon }}%"></div>
                </div>
                <div class="flex justify-between text-[10px] text-gray-400 mt-1.5">
                    <span>curtas {{ $pCur }}%</span>
                    <span>adequadas {{ $pAde }}%</span>
                    <span>longas {{ $pLon }}%</span>
                </div>
                @endif
            </div>
            @endif

        </div>
        @endif
        </div>{{-- /p-5 md:p-7 --}}

        {{-- ════════════════════════════════════════════════════════════════════════ --}}
        {{-- NÍVEL 2 — ANÁLISE SETORIAL --}}
        {{-- ════════════════════════════════════════════════════════════════════════ --}}
        @if($sectorStats->isNotEmpty())
        <div class="border-t border-gray-100 p-5 md:p-7">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">Por unidade assistencial</h2>
                <span class="text-xs text-gray-400">sessões, cobertura de leitos e turnos documentados por unidade</span>
                <button type="button" wire:click="toggleGeneralAnalysis"
                        class="ml-auto flex-shrink-0 flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors
                               {{ $generalAnalysisOpen ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-[#004D9D]/5 text-[#004D9D] border-[#004D9D]/20 hover:bg-[#004D9D]/10' }}">
                    <span>Análise geral</span>
                    <svg class="w-3 h-3 transition-transform {{ $generalAnalysisOpen ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>

            {{-- ── Painel inline Análise Geral ─────────────────────────────────── --}}
            @if($generalAnalysisOpen)
            <div class="mb-5 rounded-xl border border-[#004D9D]/15 bg-[#004D9D]/3 overflow-hidden"
                 x-data x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">

                {{-- Controles --}}
                <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-[#004D9D]/10">
                    <span class="text-[11px] font-semibold text-gray-500">Período:</span>
                    @foreach([7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 180 => '6 meses', 365 => '1 ano'] as $days => $label)
                    <button type="button" wire:click="setGeneralAnalysisDays({{ $days }})"
                            class="text-[11px] font-semibold px-2.5 py-1 rounded-md transition-colors
                                   {{ $generalAnalysisDays === $days ? 'bg-[#004D9D] text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-[#004D9D]/40 hover:text-[#004D9D]' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                    <div class="ml-auto flex items-center gap-2">
                        @if($generalAnalysis && $generalAnalysis->isCompleted())
                        <span class="text-[10px] text-gray-400">
                            gerada {{ $generalAnalysis->generated_at?->format('d/m H:i') }}
                            · {{ number_format($generalAnalysis->total_messages) }} msg
                        </span>
                        <button wire:click="generateGeneralAnalysis"
                                wire:loading.attr="disabled"
                                wire:target="generateGeneralAnalysis"
                                class="text-[11px] font-semibold px-2.5 py-1 rounded-md border border-[#004D9D]/30 text-[#004D9D] hover:bg-[#004D9D]/5 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="generateGeneralAnalysis">Regenerar</span>
                            <span wire:loading wire:target="generateGeneralAnalysis">Analisando...</span>
                        </button>
                        @else
                        <button wire:click="generateGeneralAnalysis"
                                wire:loading.attr="disabled"
                                wire:target="generateGeneralAnalysis"
                                class="text-[11px] font-semibold px-3 py-1.5 rounded-md bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-50 flex items-center gap-1.5">
                            <svg wire:loading wire:target="generateGeneralAnalysis" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <span wire:loading.remove wire:target="generateGeneralAnalysis">Gerar análise</span>
                            <span wire:loading wire:target="generateGeneralAnalysis">Analisando...</span>
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Resultado --}}
                <div class="px-4 py-3">
                    @if(!$generalAnalysis)
                    <p class="text-xs text-gray-400 py-2">Selecione o período e gere a análise.</p>

                    @elseif($generalAnalysis->isProcessing())
                    <div class="flex items-center gap-2 py-2">
                        <svg class="animate-spin w-4 h-4 text-[#004D9D]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span class="text-xs text-gray-500">Processando análise...</span>
                    </div>

                    @elseif($generalAnalysis->isFailed())
                    <p class="text-xs text-red-500 py-2">
                        <i class="fas fa-triangle-exclamation mr-1"></i>
                        {{ Str::limit($generalAnalysis->error_message, 120) }}
                    </p>

                    @elseif($generalAnalysis->isCompleted())
                    <div class="prose prose-sm prose-gray max-w-none text-xs leading-relaxed">
                        {!! Str::markdown($generalAnalysis->analysis_text ?? '') !!}
                    </div>
                    @endif
                </div>
            </div>
            @endif

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($sectorStats as $sector)
            @php
                $cov       = $sector['shift_coverage_pct'] ?? null;
                $inactive  = $sector['days_inactive'] ?? null;
                $inactiveColor = $inactive === null ? 'text-gray-400'
                    : ($inactive <= 2 ? 'text-emerald-600' : ($inactive <= 7 ? 'text-amber-500' : 'text-red-500'));
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

                {{-- Header --}}
                <div class="px-4 pt-3 pb-2 border-b border-gray-100">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 truncate">{{ $sector['sector_name'] }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">
                                {{ $sector['nurses_count'] }} plantonistas
                                @if($sector['last_session_at'])
                                · último registro
                                <span class="{{ $inactiveColor }} font-semibold">
                                    @if($inactive === 0) hoje
                                    @elseif($inactive === 1) ontem
                                    @else há {{ $inactive }} dias
                                    @endif
                                </span>
                                @endif
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xl font-bold text-[#004D9D] tabular-nums">{{ $sector['sessions'] }}</p>
                            <p class="text-[10px] text-gray-400">sessões</p>
                        </div>
                    </div>
                </div>

                {{-- KPIs --}}
                <div class="grid grid-cols-4 divide-x divide-gray-50 border-b border-gray-100">
                    <div class="px-2 py-2 text-center" title="Média de pacientes visitados por sessão">
                        <p class="text-xs font-bold text-gray-800 tabular-nums">{{ $sector['avg_beds'] ?? '—' }}</p>
                        <p class="text-[9px] text-gray-400 leading-tight mt-0.5">pac./sessão</p>
                    </div>
                    <div class="px-2 py-2 text-center" title="Média de anotações escritas por sessão">
                        <p class="text-xs font-bold text-gray-800 tabular-nums">{{ $sector['avg_msgs_per_session'] ?? '—' }}</p>
                        <p class="text-[9px] text-gray-400 leading-tight mt-0.5">anot./sessão</p>
                    </div>
                    <div class="px-2 py-2 text-center" title="Extensão média das anotações em caracteres">
                        <p class="text-xs font-bold text-gray-800 tabular-nums">{{ $sector['avg_chars'] ? $sector['avg_chars'].'ch' : '—' }}</p>
                        <p class="text-[9px] text-gray-400 leading-tight mt-0.5">chars/msg</p>
                    </div>
                    <div class="px-2 py-2 text-center" title="% de turnos (M/T/N) com pelo menos 1 anotação desde o início do uso do setor">
                        <p class="text-xs font-bold tabular-nums {{ $cov >= 70 ? 'text-emerald-600' : ($cov >= 40 ? 'text-amber-500' : ($cov !== null ? 'text-red-500' : 'text-gray-400')) }}">
                            {{ $cov !== null ? $cov.'%' : '—' }}
                        </p>
                        <p class="text-[9px] text-gray-400 leading-tight mt-0.5">cobertura</p>
                    </div>
                </div>

                {{-- Distribuição de turnos --}}
                <div class="px-3 py-2.5 space-y-1.5">
                    @foreach([['M','Manhã','#D97706',$sector['pct_M'],$sector['shift_M']],['T','Tarde','#EA580C',$sector['pct_T'],$sector['shift_T']],['N','Noite','#4F46E5',$sector['pct_N'],$sector['shift_N']]] as [$k,$l,$c,$pct,$cnt])
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] w-9 text-gray-500 font-medium">{{ $l }}</span>
                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ $pct }}%; background:{{ $c }}"></div>
                        </div>
                        <span class="text-[9px] text-gray-400 tabular-nums w-14 text-right">{{ $cnt }} ({{ $pct }}%)</span>
                    </div>
                    @endforeach
                </div>

                {{-- Ação IA --}}
                @if($sector['sector_id'])
                <div class="px-3 pb-3">
                    <button type="button"
                            wire:click="openSectorAnalysis({{ $sector['sector_id'] }}, '{{ addslashes($sector['sector_name']) }}')"
                            class="w-full text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#004D9D]/5 text-[#004D9D] border border-[#004D9D]/20 hover:bg-[#004D9D]/10 transition-colors">
                        Análise IA deste setor
                    </button>
                </div>
                @endif

            </div>
            @endforeach
            </div>
        </div>{{-- /NÍVEL 2 --}}
        @endif

        {{-- ════════════════════════════════════════════════════════════════════════ --}}
        {{-- NÍVEL 3 — ANÁLISE POR PLANTONISTA --}}
        {{-- ════════════════════════════════════════════════════════════════════════ --}}
        <div class="border-t border-gray-100 p-5 md:p-7">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">Por plantonista</h2>
                <span class="text-xs text-gray-400">frequência, extensão e horário das anotações por enfermeiro no período</span>
            </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
             x-data="{
                nurses: @js($nurseStats->values()->all()),
                sortBy: 'sessions',
                sortDir: 'desc',
                page: 0,
                perPage: 15,
                search: '',
                get sorted() {
                    let arr = [...this.nurses].filter(n => !this.search || n.name.toLowerCase().includes(this.search.toLowerCase()));
                    arr.sort((a,b)=>{
                        const av = a[this.sortBy]??-Infinity, bv = b[this.sortBy]??-Infinity;
                        return this.sortDir==='desc' ? bv-av : av-bv;
                    });
                    return arr;
                },
                get paged() { return this.sorted.slice(this.page*this.perPage,(this.page+1)*this.perPage); },
                get totalPages() { return Math.ceil(this.sorted.length/this.perPage)||1; },
                setSort(col) { this.sortBy===col ? this.sortDir=(this.sortDir==='desc'?'asc':'desc') : (this.sortBy=col,this.sortDir='desc'); this.page=0; },
                shiftColor(k) { return k==='M'?'#D97706':k==='T'?'#EA580C':'#4F46E5'; },
             }">

            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold text-gray-700 flex-shrink-0">Plantonistas</p>
                <div class="relative flex-1 min-w-[160px] max-w-xs">
                    <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                    <input type="text" x-model="search" @input="page=0" placeholder="Buscar nome..."
                           class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                </div>
                <div class="flex items-center gap-1 ml-auto">
                    <span class="text-xs text-gray-400">Ordenar:</span>
                    @foreach(['sessions'=>'Sessões','total_messages'=>'Anotações','avg_beds'=>'Leitos/sess','avg_chars'=>'Chars/nota'] as $col=>$lbl)
                    <button type="button" @click="setSort('{{ $col }}')"
                            :class="sortBy==='{{ $col }}' ? 'bg-[#004D9D] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                            class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                        {{ $lbl }}
                        <i class="fas text-[10px]" :class="sortBy==='{{ $col }}' ? (sortDir==='desc'?'fa-arrow-down':'fa-arrow-up') : 'fa-arrow-down opacity-0'"></i>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Cabeçalho --}}
            <div class="hidden md:grid grid-cols-12 gap-2 px-4 py-2 bg-gray-50 text-[10px] font-semibold text-gray-400 border-b border-gray-100">
                <div class="col-span-1 text-right">#</div>
                <div class="col-span-3">Nome · Setor</div>
                <div class="col-span-2 text-center">Sessões</div>
                <div class="col-span-1 text-center">Anot.</div>
                <div class="col-span-2 text-center">Leitos/sess</div>
                <div class="col-span-1 text-center">Chars</div>
                <div class="col-span-1 text-center">Turno</div>
                <div class="col-span-1 text-center">IA</div>
            </div>

            <div class="divide-y divide-gray-50">
                <template x-for="(nurse, idx) in paged" :key="nurse.user_id">
                    <div class="grid grid-cols-12 gap-2 px-4 py-2.5 hover:bg-gray-50/60 transition-colors items-center">

                        {{-- Posição --}}
                        <div class="col-span-1 text-right cursor-pointer"
                             @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">
                            <span class="text-xs font-bold tabular-nums"
                                  :class="page*perPage+idx===0?'text-amber-400':page*perPage+idx===1?'text-gray-400':page*perPage+idx===2?'text-amber-700':'text-gray-200'"
                                  x-text="page*perPage+idx+1"></span>
                        </div>

                        {{-- Nome + setor --}}
                        <div class="col-span-3 min-w-0 cursor-pointer"
                             @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">
                            <p class="text-xs font-semibold text-gray-800 truncate" x-text="nurse.name"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-text="nurse.sectors || '—'"></p>
                        </div>

                        {{-- Sessões --}}
                        <div class="col-span-2 text-center cursor-pointer"
                             @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">
                            <p class="text-sm font-bold text-[#004D9D] tabular-nums" x-text="nurse.sessions"></p>
                            <p class="text-[9px] text-gray-400 hidden md:block"
                               x-text="nurse.sessions_per_week ? nurse.sessions_per_week+'/sem' : ''"></p>
                        </div>

                        {{-- Total anotações --}}
                        <div class="col-span-1 text-center hidden md:block cursor-pointer"
                             @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">
                            <p class="text-xs font-semibold text-gray-700 tabular-nums" x-text="nurse.total_messages"></p>
                            <p class="text-[9px] text-gray-400" x-text="nurse.avg_messages+'⌀'"></p>
                        </div>

                        {{-- Leitos/sessão --}}
                        <div class="col-span-2 text-center hidden md:block cursor-pointer"
                             @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">
                            <p class="text-xs font-semibold text-gray-700 tabular-nums"
                               :title="nurse.avg_beds == null && nurse.all_archive ? 'Sessões históricas — rastreamento de leitos não disponível neste período' : ''"
                               x-text="nurse.avg_beds || '—'"></p>
                            <p class="text-[9px] text-gray-400">leitos</p>
                        </div>

                        {{-- Chars/nota --}}
                        <div class="col-span-1 text-center hidden md:block cursor-pointer"
                             @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">
                            <p class="text-xs font-semibold tabular-nums"
                               :class="nurse.size_label==='notas curtas'?'text-amber-600':nurse.size_label==='notas longas'?'text-orange-600':'text-emerald-700'"
                               :title="nurse.avg_chars === 0 && nurse.all_archive ? 'Sessões históricas — estatísticas de escrita não disponíveis neste período' : ''"
                               x-text="nurse.avg_chars > 0 ? nurse.avg_chars+'ch' : '—'"></p>
                        </div>

                        {{-- Turno dominante --}}
                        <div class="col-span-1 hidden md:flex justify-center items-center cursor-pointer"
                             @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                  :style="`color:${shiftColor(nurse.dominant_shift)}; background:${shiftColor(nurse.dominant_shift)}20`"
                                  x-text="nurse.dominant_shift || '—'"></span>
                        </div>

                        {{-- Botão IA --}}
                        <div class="col-span-1 hidden md:flex justify-center">
                            <button type="button"
                                    @click.stop="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id); setTimeout(() => $wire.analyzeNurseWithAi(nurse.user_id), 800)"
                                    class="text-[10px] font-semibold px-2 py-1 rounded bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors"
                                    title="Analisar com IA">
                                IA
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-4 py-2.5 border-t border-gray-100 flex items-center justify-between" x-show="totalPages > 1">
                <button @click="page=Math.max(0,page-1)" :disabled="page===0"
                        class="text-[10px] font-semibold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-chevron-left text-[8px] mr-1"></i>Anterior
                </button>
                <span class="text-[10px] text-gray-400" x-text="(page+1)+' / '+totalPages+' · '+sorted.length+' plantonistas'"></span>
                <button @click="page=Math.min(totalPages-1,page+1)" :disabled="page>=totalPages-1"
                        class="text-[10px] font-semibold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                    Próxima<i class="fas fa-chevron-right text-[8px] ml-1"></i>
                </button>
            </div>
        </div>{{-- /NÍVEL 3 --}}

        @endif {{-- /sessions->isEmpty --}}
    </div>{{-- /outer container --}}

    {{-- ══ Modal de Plantonista ══════════════════════════════════════════════════ --}}
    @if($nurseModalOpen)
    <div class="fixed inset-0 z-50 flex items-start justify-center pt-4 px-3 pb-4 md:items-center md:p-6"
         x-data="{ open: true }"
         x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="open=false; $wire.closeNurseModal()">

        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="open=false; $wire.closeNurseModal()"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden" @click.stop>

            <div class="bg-[#004D9D] px-5 py-4 flex items-center gap-3 flex-shrink-0">
                <div class="flex-1 min-w-0">
                    @if($nurseDetailLoading || !$nurseDetail)
                    <div class="flex items-center gap-2 text-white/80 text-sm">
                        <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1em;height:1em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Carregando...
                    </div>
                    @else
                    <h2 class="text-base font-bold text-white truncate">{{ $nurseDetail['name'] }}</h2>
                    <p class="text-xs text-white/70 mt-0.5">Últimos {{ $nurseDetail['period_days'] ?? 'todos os' }} dias</p>
                    @endif
                </div>
                <button wire:click="closeNurseModal"
                        class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center hover:bg-white/25 transition-colors flex-shrink-0">
                    <i class="fas fa-times text-white text-sm"></i>
                </button>
            </div>

            @if($nurseDetail)
            <div class="overflow-y-auto flex-1 p-4 space-y-4">

                {{-- KPIs individuais --}}
                <div class="grid grid-cols-4 gap-2">
                    <div class="bg-[#004D9D]/5 rounded-xl p-3 text-center border border-[#004D9D]/10">
                        <p class="text-2xl font-bold text-[#004D9D] tabular-nums">{{ $nurseDetail['summary']['total_sessions'] ?? '—' }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">sessões</p>
                        <p class="text-[10px] text-[#004D9D] font-semibold mt-0.5">{{ $nurseDetail['summary']['sessions_per_week'] ?? '—' }}/sem</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200"
                         @if(($nurseDetail['summary']['avg_beds'] ?? null) === null && ($nurseDetail['summary']['all_archive'] ?? false))
                         title="Sessões históricas — rastreamento de leitos não disponível neste período"
                         @endif>
                        <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ $nurseDetail['summary']['avg_beds'] ?? '—' }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">leitos/sessão</p>
                        @if(($nurseDetail['summary']['avg_beds'] ?? null) === null && ($nurseDetail['summary']['all_archive'] ?? false))
                        <p class="text-[9px] text-gray-400 mt-0.5">histórico</p>
                        @endif
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200">
                        <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ $nurseDetail['summary']['avg_messages_per_session'] ?? '—' }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">anot./sessão</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200"
                         title="Média de caracteres por nota. Ideal: 60–200ch">
                        @php
                            $avgCh = $nurseDetail['summary']['avg_chars'] ?? 0;
                            $tl = $nurseDetail['summary']['size_label'] ?? null;
                            $chClass = $tl === 'notas curtas' ? 'text-amber-600' : ($tl === 'notas longas' ? 'text-orange-600' : 'text-emerald-700');
                            $approxLines = $avgCh > 0 ? '~'.max(1, round($avgCh / 80)).'L' : null;
                            $isAllArchive = $nurseDetail['summary']['all_archive'] ?? false;
                        @endphp
                        <p class="text-2xl font-bold tabular-nums {{ $chClass }}">{{ $avgCh > 0 ? $avgCh.'ch' : '—' }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">por nota{{ $approxLines ? ' · '.$approxLines : '' }}</p>
                        @if($tl && $tl !== 'tamanho adequado')<p class="text-[10px] font-bold {{ $chClass }}">{{ $tl }}</p>@endif
                        @if($avgCh === 0 && $isAllArchive)<p class="text-[9px] text-gray-400 mt-0.5">histórico</p>@endif
                    </div>
                </div>

                {{-- Stats resumidas: turnos --}}
                @php $sum = $nurseDetail['summary']; @endphp
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3">
                        <p class="text-[10px] font-semibold text-gray-500 mb-2">Turnos</p>
                        <div class="flex gap-2">
                            @foreach([['M','Manhã','#D97706'],['T','Tarde','#EA580C'],['N','Noite','#4F46E5']] as [$key,$label,$color])
                            <div class="flex-1 text-center rounded-lg py-2" style="background:{{ $color }}15">
                                <p class="text-base font-bold tabular-nums" style="color:{{ $color }}">{{ $nurseDetail['shift_distribution'][$key] ?? 0 }}</p>
                                <p class="text-[9px] text-gray-400">{{ $label }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Análise com IA --}}
                @if($nurseAiAnalysis)
                <div class="bg-white rounded-xl border border-[#0071B9]/20 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                            
                            Análise com IA
                        </p>
                        <button wire:click="clearNurseAiAnalysis"
                                class="flex items-center gap-1 text-[10px] text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-chart-bar text-[9px]"></i>
                            Fechar análise
                        </button>
                    </div>
                    <div wire:loading wire:target="analyzeNurseWithAi" class="px-4 py-6 text-center">
                        <svg class="animate-spin text-[#004D9D] text-xl mb-2 block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" style="width:1em;height:1em;display:inline-block"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <p class="text-xs text-gray-500">Analisando padrões de documentação...</p>
                    </div>
                    <div wire:loading.remove wire:target="analyzeNurseWithAi" class="px-4 py-3">
                        <div class="prose prose-sm prose-gray max-w-none text-xs">{!! Str::markdown($nurseAiAnalysis ?? '') !!}</div>
                    </div>
                </div>
                @elseif($nurseDetail)
                <div class="flex justify-end">
                    <button wire:click="analyzeNurseWithAi({{ $nurseDetail['user_id'] }})"
                            wire:loading.attr="disabled"
                            wire:target="analyzeNurseWithAi"
                            class="flex items-center gap-2 text-xs font-semibold px-3 py-2 rounded-lg bg-[#004D9D] text-white hover:bg-[#003d7a] transition-colors shadow-sm disabled:opacity-60">
                        
                        <svg wire:loading wire:target="analyzeNurseWithAi" class="animate-spin" style="width:10px;height:10px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span wire:loading.remove wire:target="analyzeNurseWithAi">Analisar com IA</span>
                        <span wire:loading wire:target="analyzeNurseWithAi">Analisando...</span>
                    </button>
                </div>
                @endif

                {{-- Passagens recentes --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden"
                     x-data="{ page: 0, perPage: 15, sessions: @js($nurseDetail['recent_sessions'] ?? []), get paged() { return this.sessions.slice(this.page*this.perPage,(this.page+1)*this.perPage); }, get total() { return Math.ceil(this.sessions.length/this.perPage)||1; } }"
                     x-init="$wire.$watch('nurseDetail', val => { this.sessions = val?.recent_sessions || []; this.page = 0; })">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">Passagens</p>
                        <span class="text-[10px] text-gray-400">{{ count($nurseDetail['recent_sessions'] ?? []) }} registradas</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-[10px] text-gray-500">
                                    <th class="px-3 py-2 text-left font-semibold">Data / Turno</th>
                                    <th class="px-3 py-2 text-left font-semibold">Setor</th>
                                    <th class="px-3 py-2 text-right font-semibold">Leitos</th>
                                    <th class="px-3 py-2 text-right font-semibold">Anot.</th>
                                    <th class="px-3 py-2 text-right font-semibold hidden sm:table-cell">Duração</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <template x-for="(s, i) in paged" :key="i">
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="px-3 py-2.5">
                                            <p class="font-semibold text-gray-700 tabular-nums" x-text="s.started_at_formatted"></p>
                                            <p class="text-[10px] text-gray-400" x-text="s.shift_label"></p>
                                        </td>
                                        <td class="px-3 py-2.5 text-gray-500 max-w-[100px] truncate" x-text="s.sector_name"></td>
                                        <td class="px-3 py-2.5 text-right font-semibold text-gray-700 tabular-nums" x-text="s.beds_visited ?? '—'"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums"
                                            :class="s.messages_written===0?'text-gray-300':'text-gray-600'"
                                            x-text="s.messages_written||'—'"></td>
                                        <td class="px-3 py-2.5 text-right text-gray-400 tabular-nums hidden sm:table-cell"
                                            x-text="s.duration_min ? (s.duration_estimated ? '~' : '') + Math.round(s.duration_min) + 'min' : '—'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-2 border-t border-gray-100 flex items-center justify-between" x-show="total > 1">
                        <button @click="page=Math.max(0,page-1)" :disabled="page===0"
                                class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50">
                            <i class="fas fa-chevron-left text-[8px] mr-1"></i>Anterior
                        </button>
                        <span class="text-[10px] text-gray-400" x-text="(page+1)+' / '+total"></span>
                        <button @click="page=Math.min(total-1,page+1)" :disabled="page>=total-1"
                                class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50">
                            Próxima<i class="fas fa-chevron-right text-[8px] ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ══ Modal: Análise Setorial com IA — componente Livewire dedicado ═══════ --}}
    @livewire('handover-sector-analysis', [], key('handover-sector-analysis'))

    {{-- ── Modal: IA desativada ─────────────────────────────────────────────── --}}
    <div x-data="{ open: false }"
         x-on:show-ai-disabled.window="open = true"
         x-show="open"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] flex items-center justify-center px-4"
         @click.self="open = false"
         style="display:none">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="open = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center" @click.stop>
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                
            </div>
            <h3 class="text-base font-bold text-gray-900 mb-1">Análise de IA desativada</h3>
            <p class="text-sm text-gray-500 mb-1">Esta funcionalidade está desabilitada no ambiente atual.</p>
            <p class="text-xs text-gray-400 mb-5">Configure <code class="bg-gray-100 px-1 py-0.5 rounded">PRISM_WRITING_ANALYSIS=true</code> no <code class="bg-gray-100 px-1 py-0.5 rounded">.env</code>.</p>
            <button @click="open = false" class="px-4 py-2 text-sm font-semibold text-white bg-[#004D9D] rounded-lg hover:bg-[#003d7a] transition-colors">
                Entendido
            </button>
        </div>
    </div>

</div>
