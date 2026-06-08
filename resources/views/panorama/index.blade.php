<div class="space-y-6">

    {{-- ── Filtros ────────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2 mr-2">
            <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
            <h2 class="text-sm font-semibold text-gray-700">Passagem de Plantão</h2>
        </div>
        <span class="text-[10px] font-semibold text-gray-400 mr-1">Período:</span>
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
        <div class="flex items-center gap-2 ml-2">
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
                <i class="fas fa-circle-notch fa-spin mr-1"></i>Carregando...
            </span>
            <span wire:loading.remove>
                {{ $sessions->count() }} sessões · {{ $institutionalStats['active_nurses'] }} plantonistas
            </span>
        </span>
    </div>

    @if($sessions->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
        <i class="fas fa-chart-bar text-gray-200 text-3xl mb-3 block"></i>
        <p class="font-semibold text-gray-500">Nenhuma passagem registrada no período</p>
        <p class="text-xs text-gray-400 mt-1">Dados aparecem conforme os enfermeiros usam o SBAR.</p>
    </div>
    @else

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- NÍVEL 1 — VISÃO INSTITUCIONAL --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="flex items-start gap-2 mb-3">
            <div class="w-1 h-4 rounded-full bg-[#004D9D] mt-1 flex-shrink-0"></div>
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Adoção do sistema</h2>
                <span class="text-xs text-gray-400">quanto o sistema está sendo usado</span>
            </div>
        </div>

        {{-- KPIs principais --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 mb-3">
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

        {{-- Distribuição por turno + Classificação de conteúdo --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

            {{-- Turnos --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs font-semibold text-gray-700 mb-3">Sessões por turno</p>
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
            </div>

            {{-- Heatmap --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-xs font-semibold text-gray-700 mb-1">Volume por hora</p><p class="text-[10px] text-gray-400 mb-2">últimas anotações ativas</p>
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
            </div>

            {{-- Classificação de conteúdo (regex, sem IA) --}}
            @if(!empty($contentClassification))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-sm font-semibold text-gray-700 mb-0.5">Tipo de conteúdo registrado</p>
                <p class="text-xs text-gray-400 mb-3">classificação das anotações recentes por categoria clínica</p>
                <div class="space-y-2">
                    @php
                        $catColors = [
                            'pendência'            => '#EF4444',
                            'risco'                => '#F97316',
                            'conduta/procedimento' => '#3B82F6',
                            'alta/evolução'        => '#10B981',
                            'condição clínica'     => '#6B7280',
                        ];
                    @endphp
                    @foreach($contentClassification as $cat => $data)
                    <div>
                        <div class="flex justify-between text-[10px] mb-0.5">
                            <span class="text-gray-600 capitalize">{{ $cat }}</span>
                            <span class="font-semibold text-gray-700 tabular-nums">{{ $data['count'] }} ({{ $data['pct'] }}%)</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ $data['pct'] }}%; background:{{ $catColors[$cat] ?? '#9CA3AF' }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Análise de conteúdo: pendências + termos --}}
        @if(!empty($topPendings) || !empty($topTerms))
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">

            @if(!empty($topPendings))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-sm font-semibold text-gray-700 mb-0.5">Pendências mais registradas</p>
                <p class="text-xs text-gray-400 mb-3">itens que a equipe menciona como pendentes — anotações recentes</p>
                <div class="space-y-1.5">
                    @php $maxPending = max(array_values($topPendings)); @endphp
                    @foreach(array_slice($topPendings, 0, 10, true) as $pending => $count)
                    <div class="flex items-center gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs text-gray-700 truncate flex-1 mr-2">{{ $pending }}</span>
                                <span class="text-xs font-bold text-gray-500 tabular-nums flex-shrink-0">{{ $count }}×</span>
                            </div>
                            <div class="h-1 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-red-400 rounded-full" style="width:{{ round($count/$maxPending*100) }}%"></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @if(!empty($topTerms))
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-sm font-semibold text-gray-700 mb-0.5">Termos mais registrados</p>
                <p class="text-xs text-gray-400 mb-3">palavras e expressões mais frequentes — anotações recentes</p>
                @php
                    $termsSlice = array_slice($topTerms, 0, 15, true);
                    $maxTerm = max(array_values($termsSlice));
                @endphp
                <div class="space-y-1.5">
                    @foreach($termsSlice as $term => $count)
                    @php $pct = round($count / $maxTerm * 100); @endphp
                    <div class="flex items-center gap-2 group">
                        <span class="text-xs text-gray-700 w-36 flex-shrink-0 truncate text-right" title="{{ $term }}">{{ $term }}</span>
                        <div class="flex-1 h-5 bg-gray-100 rounded overflow-hidden relative">
                            <div class="h-full rounded transition-all"
                                 style="width:{{ $pct }}%; background:rgba(0,77,157,{{ round(0.2 + ($pct/100)*0.6, 2) }})"></div>
                            <span class="absolute right-2 top-0 h-full flex items-center text-[10px] font-semibold tabular-nums text-gray-500">{{ $count }}×</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- NÍVEL 2 — ANÁLISE SETORIAL --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    @if($sectorStats->isNotEmpty())
    <div>
        <div class="flex items-center gap-2 mb-3">
            <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
            <h2 class="text-sm font-semibold text-gray-700">Por unidade assistencial</h2>
            <span class="text-xs text-gray-400">cobertura e padrões de cada setor</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            @foreach($sectorStats as $sector)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <p class="text-sm font-bold text-gray-900">{{ $sector['sector_name'] }}</p>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xl font-bold text-[#004D9D] tabular-nums">{{ $sector['sessions'] }}</p>
                            <p class="text-[10px] text-gray-400">sessões</p>
                        </div>
                    </div>
                    <p class="text-[10px] text-gray-400">{{ $sector['nurses_count'] }} plantonistas</p>
                </div>
                <div class="px-4 py-3 space-y-2">
                    {{-- Métricas operacionais --}}
                    <div class="grid grid-cols-3 gap-2 text-center pb-2 border-b border-gray-50">
                        <div>
                            <p class="text-sm font-bold text-gray-800 tabular-nums">{{ $sector['avg_beds'] }}</p>
                            <p class="text-xs text-gray-400">pac./sessão</p>
                        </div>
                        <div title="Turnos (manhã/tarde/noite) que tiveram pelo menos uma anotação, desde que o setor começou a usar o sistema">
                            @php $cov = $sector['shift_coverage_pct'] ?? null; @endphp
                            <p class="text-sm font-bold tabular-nums {{ $cov >= 70 ? 'text-emerald-600' : ($cov >= 40 ? 'text-amber-600' : 'text-red-500') }}">
                                {{ $cov !== null ? $cov.'%' : '—' }}
                            </p>
                            <p class="text-xs text-gray-400">turnos cobertos</p>
                        </div>
                    </div>

                    {{-- Distribuição de turnos --}}
                    <div class="space-y-1">
                        @foreach([['M','Manhã','#D97706',$sector['pct_M'],$sector['shift_M']],['T','Tarde','#EA580C',$sector['pct_T'],$sector['shift_T']],['N','Noite','#4F46E5',$sector['pct_N'],$sector['shift_N']]] as [$k,$l,$c,$pct,$cnt])
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] w-10 text-gray-500">{{ $l }}</span>
                            <div class="flex-1 h-1 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full" style="width:{{ $pct }}%; background:{{ $c }}"></div>
                            </div>
                            <span class="text-[9px] text-gray-400 tabular-nums w-12 text-right">{{ $cnt }} ({{ $pct }}%)</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    {{-- NÍVEL 3 — ANÁLISE POR PLANTONISTA --}}
    {{-- ════════════════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="flex items-center gap-2 mb-3">
            <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
            <h2 class="text-sm font-semibold text-gray-700">Por plantonista</h2>
            <span class="text-xs text-gray-400">participação individual — não é métrica de produtividade</span>
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
                    @foreach(['sessions'=>'Sessões','total_messages'=>'Anotações','avg_beds'=>'Leitos/sessão'] as $col=>$lbl)
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
                <div class="col-span-5">Nome · Setor</div>
                <div class="col-span-2 text-center">Sessões</div>
                <div class="col-span-2 text-center">Anot.</div>
                <div class="col-span-2 text-center">Turnos</div>
            </div>

            <div class="divide-y divide-gray-50">
                <template x-for="(nurse, idx) in paged" :key="nurse.user_id">
                    <div class="grid grid-cols-12 gap-2 px-4 py-2.5 hover:bg-gray-50/60 cursor-pointer group transition-colors items-center"
                         @click="$wire.openAndLoadNurse(nurse.user_id); $wire.loadNurseDetail(nurse.user_id)">

                        {{-- Posição --}}
                        <div class="col-span-1 text-right">
                            <span class="text-xs font-bold tabular-nums"
                                  :class="page*perPage+idx===0?'text-amber-400':page*perPage+idx===1?'text-gray-400':page*perPage+idx===2?'text-amber-700':'text-gray-200'"
                                  x-text="page*perPage+idx+1"></span>
                        </div>

                        {{-- Nome + setor --}}
                        <div class="col-span-5 min-w-0">
                            <p class="text-xs font-semibold text-gray-800 truncate" x-text="nurse.name"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-text="nurse.sectors || '—'"></p>
                        </div>

                        {{-- Sessões --}}
                        <div class="col-span-2 text-center">
                            <p class="text-sm font-bold text-[#004D9D] tabular-nums" x-text="nurse.sessions"></p>
                            <p class="text-[9px] text-gray-400 hidden md:block"
                               x-text="nurse.sessions_per_week ? nurse.sessions_per_week+'/sem' : ''"></p>
                        </div>

                        {{-- Total anotações --}}
                        <div class="col-span-2 text-center hidden md:block">
                            <p class="text-xs font-semibold text-gray-700 tabular-nums" x-text="nurse.total_messages"></p>
                            <p class="text-[9px] text-gray-400" x-text="nurse.avg_messages+'⌀'"></p>
                        </div>

                        {{-- Barras de turno --}}
                        <div class="col-span-2 hidden md:flex gap-px items-end h-4">
                            <template x-for="(pct, key) in nurse.shift_pct" :key="key">
                                <div class="flex-1 rounded-sm"
                                     :style="`height:${Math.max(pct,4)}%; background:${shiftColor(key)}`"
                                     :title="(key==='M'?'Manhã':key==='T'?'Tarde':'Noite')+': '+pct+'%'"></div>
                            </template>
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
        </div>
    </div>

    @endif {{-- /sessions->isEmpty --}}

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
                        <i class="fas fa-circle-notch fa-spin"></i> Carregando...
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
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200">
                        <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ $nurseDetail['summary']['avg_beds'] ?? '—' }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">leitos/sessão</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200">
                        <p class="text-2xl font-bold text-gray-800 tabular-nums">{{ $nurseDetail['summary']['avg_messages_per_session'] ?? '—' }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">anot./sessão</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-center border border-gray-200"
                         title="Média de caracteres por nota. Ideal: 60–200ch">
                        @php
                            $avgCh = $nurseDetail['summary']['avg_chars'] ?? 0;
                            $tl = $nurseDetail['summary']['tamanho_label'] ?? null;
                            $chClass = $tl === 'notas curtas' ? 'text-amber-600' : ($tl === 'notas longas' ? 'text-orange-600' : 'text-emerald-700');
                            $approxLines = $avgCh > 0 ? '~'.max(1, round($avgCh / 80)).'L' : null;
                        @endphp
                        <p class="text-2xl font-bold tabular-nums {{ $chClass }}">{{ $avgCh > 0 ? $avgCh.'ch' : '—' }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">por nota{{ $approxLines ? ' · '.$approxLines : '' }}</p>
                        @if($tl && $tl !== 'tamanho adequado')<p class="text-[10px] font-bold {{ $chClass }}">{{ $tl }}</p>@endif
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
                                        <td class="px-3 py-2.5 text-right font-semibold text-gray-700 tabular-nums" x-text="s.beds_visited"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums"
                                            :class="s.messages_written===0?'text-gray-300':'text-gray-600'"
                                            x-text="s.messages_written||'—'"></td>
                                        <td class="px-3 py-2.5 text-right text-gray-400 tabular-nums hidden sm:table-cell"
                                            x-text="s.duration_min ? Math.round(s.duration_min)+'min' : '—'"></td>
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
                <i class="fas fa-wand-magic-sparkles text-gray-400 text-lg"></i>
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
