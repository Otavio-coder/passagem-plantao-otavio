@extends('layouts.app')
@section('title', 'Panorama do Sistema')

@push('head')
<style>
    [x-cloak] { display: none !important; }

    /* DataTables overrides */
    #archive-table_wrapper .dt-search input,
    #archive-table_wrapper .dataTables_filter input {
        border: 1px solid #D1D5DB;
        border-radius: 0.375rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.875rem;
        outline: none;
        margin-left: 4px;
    }
    #archive-table_wrapper .dt-search input:focus,
    #archive-table_wrapper .dataTables_filter input:focus {
        border-color: #0071B9;
        box-shadow: 0 0 0 2px rgba(0,113,185,.15);
    }
    #archive-table_wrapper .dt-length select,
    .dataTables_length select {
        border: 1px solid #D1D5DB;
        border-radius: 0.375rem;
        padding: 0.25rem 1.5rem 0.25rem 0.5rem;
        font-size: 0.875rem;
        margin-right: 0.5rem;
    }
    #archive-table_wrapper .dt-paging button {
        border: 1px solid #E5E7EB;
        border-radius: 0.375rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        color: #4B5563;
        margin: 0 1px;
    }
    #archive-table_wrapper .dt-paging button.current {
        background-color: #0071B9 !important;
        color: white !important;
        border-color: #0071B9 !important;
    }
    #archive-table_wrapper .dt-paging button:hover:not(.current) { background-color: #F3F4F6; }
    #archive-table th { white-space: nowrap; }
</style>
@endpush

@section('content')
<div class="space-y-6">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#004D9D]/10 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-gauge-high text-[#004D9D]"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">Panorama do Sistema</h1>
                <p class="text-sm text-gray-500 mt-0.5">Panorama de usuários, setores, anotações e passagens de plantão</p>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 1+2: Usuários · Setores (lado a lado) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Coluna esquerda: Usuários e Acessos --}}
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">Usuários e Acessos</h2>
            </div>

            {{-- KPIs compactos --}}
            <div class="grid grid-cols-4 gap-2">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                    <p class="text-[10px] text-gray-400">Ativos</p>
                    <p class="text-xl font-bold text-[#004D9D]">{{ $userMetrics['total_active'] }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                    <p class="text-[10px] text-gray-400">7 dias</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $userMetrics['last_7d'] }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                    <p class="text-[10px] text-gray-400">30 dias</p>
                    <p class="text-xl font-bold text-sky-600">{{ $userMetrics['last_30d'] }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                    <p class="text-[10px] text-gray-400">Enf.</p>
                    <p class="text-xl font-bold text-indigo-600">{{ $userMetrics['nurses'] }}</p>
                </div>
            </div>

            {{-- Funções + Acessos recentes --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @if($userMetrics['top_roles']->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                    <p class="text-xs text-gray-500 font-medium mb-2">Por função</p>
                    @php $maxRoleCount = $userMetrics['top_roles']->max('count') ?: 1; @endphp
                    <div class="space-y-1.5">
                        @foreach($userMetrics['top_roles'] as $role)
                        <div>
                            <div class="flex justify-between text-[10px] text-gray-500 mb-0.5">
                                <span class="truncate max-w-[140px]" title="{{ $role->role }}">{{ $role->role }}</span>
                                <span class="font-semibold ml-1 flex-shrink-0">{{ $role->count }}</span>
                            </div>
                            <div class="h-1 bg-gray-100 rounded-full">
                                <div class="h-1 rounded-full bg-[#004D9D]" style="width:{{ round($role->count / $maxRoleCount * 100) }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($userMetrics['recent_access']->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-100">
                        <p class="text-xs text-gray-500 font-medium">Acessos recentes</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($userMetrics['recent_access']->take(5) as $u)
                        @php
                            $words = preg_split('/[\s.]+/', trim($u->name ?: 'U')) ?: ['U'];
                            $ini = strtoupper(substr($words[0] ?? 'U', 0, 1));
                            if (count($words) > 1) $ini .= strtoupper(substr(end($words), 0, 1));
                            $palette = ['4F46E5','0891B2','059669','B45309','7C3AED','0284C7','BE185D','0F766E'];
                            $bg = '#'.($palette[abs(crc32($u->name ?: 'U')) % count($palette)]);
                            $hasPhoto = !empty($u->photo) && strlen($u->photo) > 100;
                            $ts = $u->last_access_at ? \Carbon\Carbon::parse($u->last_access_at)->diffForHumans() : '—';
                        @endphp
                        <div class="flex items-center gap-2 px-3 py-1.5">
                            @if($hasPhoto)
                                <img src="data:image/jpeg;base64,{{ $u->photo }}" alt="{{ $u->name }}" class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[8px] font-bold flex-shrink-0" style="background:{{ $bg }}">{{ $ini }}</div>
                            @endif
                            <p class="text-xs text-gray-700 truncate flex-1">{{ $u->name }}</p>
                            <span class="text-[10px] text-gray-400 flex-shrink-0">{{ $ts }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Coluna direita: Setores Monitorados --}}
        @if($sectorPanorama['total_sectors'] > 0)
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-emerald-500"></div>
                <h2 class="text-sm font-semibold text-gray-700">Setores Monitorados</h2>
            </div>

            {{-- KPIs compactos --}}
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                    <p class="text-[10px] text-gray-400">Usuários</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $sectorPanorama['total_configured_users'] }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                    <p class="text-[10px] text-gray-400">Setores</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $sectorPanorama['total_sectors'] }}</p>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                    <p class="text-[10px] text-gray-400">Hospitais</p>
                    <p class="text-xl font-bold text-emerald-600">{{ $sectorPanorama['total_hospitals'] }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @if($sectorPanorama['top_hospitals']->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                    <p class="text-xs text-gray-500 font-medium mb-2">Por hospital</p>
                    @php $maxH = $sectorPanorama['top_hospitals']->max('user_count') ?: 1; @endphp
                    <div class="space-y-1.5">
                        @foreach($sectorPanorama['top_hospitals'] as $h)
                        <div>
                            <div class="flex justify-between text-[10px] text-gray-500 mb-0.5">
                                <span class="truncate max-w-[140px]" title="{{ $h->hospital_name }}">{{ $h->hospital_name }}</span>
                                <span class="font-semibold ml-1 flex-shrink-0 text-emerald-700">{{ $h->user_count }}u · {{ $h->sector_count }}s</span>
                            </div>
                            <div class="h-1 bg-gray-100 rounded-full">
                                <div class="h-1 rounded-full bg-emerald-500" style="width:{{ round($h->user_count / $maxH * 100) }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($sectorPanorama['top_sectors']->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-100">
                        <p class="text-xs text-gray-500 font-medium">Mais monitorados</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($sectorPanorama['top_sectors']->take(6) as $i => $s)
                        <div class="flex items-center gap-2 px-3 py-1.5">
                            <span class="text-[9px] font-bold w-4 text-center flex-shrink-0 text-gray-300">{{ $i + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-800 truncate">{{ $s->sector_name }}</p>
                            </div>
                            <span class="text-xs font-semibold text-emerald-700 flex-shrink-0">{{ $s->user_count }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 3: SBAR - Passagem de Plantão --}}
    @if(($stats && $stats->total > 0) || (!empty($handoverMetrics) && $handoverMetrics['total'] > 0))
    <div>
        <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">SBAR — Passagem de Plantão</h2>
            </div>
            <div class="flex items-center gap-3">
                @can('ver historico chat')
                <a href="{{ route('handover.metrics') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-bold text-white bg-[#004D9D] border-2 border-[#004D9D] px-3 py-1.5 rounded-lg hover:bg-[#003d7a] transition-all">
                    <i class="fas fa-chart-line text-[10px]"></i> Análise detalhada
                </a>
                @endcan
            </div>
        </div>

        {{-- KPIs unificados --}}
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-3">
            @if($stats && $stats->total > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                <p class="text-[10px] text-gray-400">Atendimentos</p>
                <p class="text-xl font-bold text-[#004D9D]">{{ number_format($stats->total) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                <p class="text-[10px] text-gray-400">Anotações</p>
                <p class="text-xl font-bold text-[#004D9D]">{{ number_format($stats->total_msgs) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-3 py-2.5">
                <p class="text-[10px] text-gray-400">Cobertura</p>
                <p class="text-xl font-bold {{ $coveragePct >= 70 ? 'text-emerald-600' : ($coveragePct >= 40 ? 'text-amber-500' : 'text-red-500') }}">{{ $coveragePct }}%</p>
            </div>
            @endif
        </div>

        {{-- Conteúdo em 2 colunas --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3 md:items-stretch">

            {{-- Esq: anotações por turno + série --}}
            @if($stats && $stats->total > 0)
            <div class="flex flex-col gap-3">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                    <p class="text-xs text-gray-500 font-medium mb-2">Anotações por turno</p>
                    <div class="space-y-1.5">
                        @foreach($shiftDistribution as $shift)
                        <div>
                            <div class="flex justify-between text-[10px] text-gray-500 mb-0.5">
                                <span>{{ $shift['label'] }}</span>
                                <span class="font-semibold">{{ $shift['percentage'] }}%</span>
                            </div>
                            <div class="h-1.5 bg-gray-100 rounded-full">
                                <div class="h-1.5 rounded-full" style="width:{{ $shift['percentage'] }}%; background-color:{{ $shift['color'] }}"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex-1">
                    <p class="text-xs text-gray-500 font-medium mb-2">Últimos 6 meses</p>
                    <div class="space-y-2.5">
                        @foreach($seriesData as $data)
                        <div>
                            {{-- Barra principal --}}
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-[10px] text-gray-400 w-11 text-right flex-shrink-0">{{ $data['label'] }}</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full" style="width:{{ $data['percentage'] }}%; background-color:#0071B9"></div>
                                </div>
                                <span class="text-[10px] text-gray-600 font-semibold w-8 text-right">{{ $data['messages'] ?: '—' }}</span>
                            </div>
                            {{-- Top setores --}}
                            @if(!empty($data['sectors']))
                            @php
                                $colors = ['#0071B9','#10B981','#F59E0B','#6B7280'];
                                $total = array_sum(array_column($data['sectors'], 'count'));
                            @endphp
                            <div class="flex items-end gap-1 pl-[52px] mt-1" style="height:32px">
                                @foreach($data['sectors'] as $si => $sector)
                                @php
                                    $pct = $total > 0 ? round($sector['count']/$total*100) : 0;
                                    $color = $colors[$si] ?? '#9CA3AF';
                                    $barH = max(20, $pct);
                                @endphp
                                <div class="flex flex-col items-center justify-end flex-1 h-full">
                                    <span class="text-[8px] font-bold leading-none mb-0.5" style="color:{{ $color }}">{{ $pct > 4 ? $pct.'%' : '' }}</span>
                                    <div class="w-full rounded-t-sm"
                                         style="height:{{ $barH }}%; background:{{ $color }}"
                                         title="{{ $sector['name'] }}: {{ $sector['count'] }} msgs"></div>
                                </div>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 pl-[52px]">
                                @foreach($data['sectors'] as $si => $sector)
                                @php $color = $colors[$si] ?? '#9CA3AF'; @endphp
                                <span class="inline-flex items-center gap-1 text-[9px] text-gray-500">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:{{ $color }}"></span>
                                    {{ $sector['name'] }}
                                </span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Dir: passagens recentes paginadas --}}
            @if(!empty($recentShiftTimelines))
            <div class="flex flex-col" x-data="{
                    sessions: @js($recentShiftTimelines),
                    search: '',
                    filterShift: '',
                    page: 0,
                    perPage: 5,
                    get filtered() {
                        const q = this.search.toLowerCase().trim();
                        return this.sessions.filter(s =>
                            (!this.filterShift || s.shift === this.filterShift) &&
                            (!q || s.name.toLowerCase().includes(q) || (s.sector_name||'').toLowerCase().includes(q))
                        );
                    },
                    get paged() { return this.filtered.slice(this.page*this.perPage, (this.page+1)*this.perPage); },
                    get totalPages() { return Math.ceil(this.filtered.length/this.perPage); },
                    resetPage() { this.page = 0; },
                    barColor(shift) { return shift==='M'?'#D97706':shift==='T'?'#EA580C':'#4F46E5'; },
                    badgeClass(shift) { return shift==='M'?'bg-amber-50 text-amber-700 border-amber-200':shift==='T'?'bg-orange-50 text-orange-700 border-orange-200':'bg-indigo-50 text-indigo-700 border-indigo-200'; },
                    fmtDur(m) { if(!m||m<=0) return ''; const h=Math.floor(m/60),min=m%60; return (h>0?h+'h':'')+(min>0?min+'min':''); }
                 }"
                 class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex-1 flex flex-col">

                {{-- Header com busca e filtros --}}
                <div class="px-3 py-2 border-b border-gray-100 space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-gray-500 font-medium flex-shrink-0">Atividade por plantonista</p>
                        <span class="text-[10px] text-gray-400" x-text="filtered.length + ' sessões'"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Search --}}
                        <div class="relative flex-1 min-w-0">
                            <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                            <input type="text"
                                   x-model="search"
                                   @input="resetPage()"
                                   placeholder="Nome ou setor..."
                                   class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30 focus:border-[#004D9D]/40 font-montserrat">
                        </div>
                        {{-- Filtro turno --}}
                        <div class="flex gap-1 flex-shrink-0">
                            @foreach([''=>'Todos','M'=>'Manhã','T'=>'Tarde','N'=>'Noite'] as $key => $label)
                            <button type="button"
                                    @click="filterShift = '{{ $key }}'; resetPage()"
                                    :class="filterShift === '{{ $key }}'
                                        ? '{{ $key==='M'?'bg-amber-500 text-white':($key==='T'?'bg-orange-500 text-white':($key==='N'?'bg-indigo-500 text-white':'bg-[#004D9D] text-white')) }}'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                    class="text-[9px] font-bold px-2 py-1 rounded-md transition-colors">
                                {{ $label }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Linhas --}}
                <div class="divide-y divide-gray-50">
                    <template x-for="s in paged" :key="s.name + s.shift_date + s.shift">
                        <div class="px-3 py-2.5">
                            {{-- Linha 1: nome + badge + stats --}}
                            <div class="flex items-center gap-2 mb-0.5">
                                <p class="text-xs font-semibold text-gray-800 truncate flex-1 min-w-0" x-text="s.name"></p>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border flex-shrink-0"
                                      :class="badgeClass(s.shift)"
                                      x-text="s.shift_label + ' ' + s.shift_date"></span>
                                <span class="text-[10px] font-bold text-[#004D9D] flex-shrink-0" x-text="s.message_count + ' msgs'"></span>
                            </div>

                            {{-- Linha 2: setor + horário --}}
                            <div class="flex items-center gap-2 mb-2">
                                <p class="text-[10px] text-gray-400 truncate flex-1 min-w-0" x-text="s.sector_name"></p>
                                <span class="text-[10px] text-gray-400 flex-shrink-0" x-text="s.first_msg + '–' + s.last_msg"></span>
                                <span class="text-[10px] text-gray-400 flex-shrink-0" x-show="s.active_min" x-text="fmtDur(s.active_min) + ' ativo'"></span>
                            </div>

                            {{-- Gráfico de colunas --}}
                            <div class="mt-2 rounded-lg overflow-hidden" style="background:#f1f5f9; border:1px solid #e2e8f0; padding:8px 6px 4px">
                                {{-- Área do gráfico com grid --}}
                                <div class="relative" style="height:52px">
                                    {{-- Linhas de grid horizontais --}}
                                    <div class="absolute inset-0 flex flex-col justify-between pointer-events-none" style="padding-bottom:0">
                                        <div style="border-top:1px dashed #cbd5e1"></div>
                                        <div style="border-top:1px dashed #cbd5e1"></div>
                                        <div style="border-top:1px dashed #cbd5e1"></div>
                                        <div style="border-top:1px solid #cbd5e1"></div>
                                    </div>
                                    {{-- Colunas --}}
                                    <div class="absolute inset-0 flex items-end gap-px px-px">
                                        <template x-for="(count, i) in s.dist" :key="i">
                                            <div class="flex-1 flex flex-col items-center justify-end">
                                                <span class="text-[8px] font-bold tabular-nums mb-px leading-none"
                                                      :style="`color:${count>0 ? barColor(s.shift) : 'transparent'}`"
                                                      x-text="count > 0 ? count : ' '"></span>
                                                <div class="w-full rounded-t"
                                                     :style="`height:${count>0 ? Math.max(Math.round(count/s.max_dist*80),10) : 2}%; min-height:${count>0?'8px':'2px'}; background:${count>0 ? barColor(s.shift) : '#e2e8f0'}`"></div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                {{-- Labels de hora --}}
                                <div class="flex gap-px mt-1.5 px-px">
                                    <template x-for="h in s.axis_hours" :key="h">
                                        <div class="flex-1 text-center text-[8px] font-bold text-slate-500 font-mono" x-text="h"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Paginação --}}
                <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-between" x-show="totalPages > 1">
                    <button @click="page = Math.max(0, page-1)"
                            :disabled="page === 0"
                            class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-left text-[8px] mr-1"></i>Anterior
                    </button>
                    <span class="text-[10px] text-gray-400" x-text="(page+1) + ' / ' + totalPages"></span>
                    <button @click="page = Math.min(totalPages-1, page+1)"
                            :disabled="page >= totalPages-1"
                            class="text-[10px] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 text-gray-600 disabled:opacity-30 hover:bg-gray-50 transition-colors">
                        Próxima<i class="fas fa-chevron-right text-[8px] ml-1"></i>
                    </button>
                </div>
            </div>
            @endif
        </div>

        {{-- Ranking anotadores --}}
        @if(count($topAnnotators) > 0)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm"
             x-data="{
                all: @js($allAnnotators),
                top: @js($topAnnotators),
                search: '',
                get results() {
                    const q = this.search.trim().toLowerCase();
                    if (!q) return null;
                    return this.all.filter(a => a.name.toLowerCase().includes(q)).slice(0, 30);
                }
             }">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-3">
                <p class="text-xs text-gray-500 font-medium flex-shrink-0">Ranking de anotações</p>
                <div class="relative flex-1 max-w-xs">
                    <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                    <input type="text"
                           x-model="search"
                           placeholder="Buscar plantonista..."
                           class="w-full pl-7 pr-3 py-1 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30 focus:border-[#004D9D]/40 font-montserrat">
                </div>
                <span class="text-[10px] text-gray-400 flex-shrink-0"
                      x-text="search ? (results?.length + ' resultado' + (results?.length!==1?'s':'')) : '{{ count($allAnnotators) }} plantonistas'"></span>
            </div>

            {{-- Grid padrão top 20 (sem busca) --}}
            <div x-show="!search" class="p-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-0.5">
                @foreach($topAnnotators as $i => $a)
                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 transition-colors min-w-0">
                    <span class="text-[10px] font-bold w-5 text-center flex-shrink-0 {{ $i === 0 ? 'text-amber-400' : ($i === 1 ? 'text-gray-400' : ($i === 2 ? 'text-amber-700' : 'text-gray-200')) }}">{{ $i + 1 }}</span>
                    <x-ui.user-avatar :photo="$a['photo']" :name="$a['name']" class="w-6 h-6 flex-shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-gray-700 truncate">{{ $a['name'] }}</p>
                        <p class="text-[10px] text-gray-400 truncate"><span class="font-semibold text-[#004D9D]">{{ number_format($a['count']) }}</span> anot.</p>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Resultados da busca --}}
            <div x-show="search" style="display:none" class="divide-y divide-gray-50">
                <template x-if="results && results.length > 0">
                    <div>
                        <template x-for="(a, i) in results" :key="a.username">
                            <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors">
                                <span class="text-[10px] font-bold text-gray-300 w-6 text-right flex-shrink-0" x-text="all.indexOf(a) + 1 + 'º'"></span>
                                <div class="w-7 h-7 rounded-full bg-[#004D9D]/10 flex items-center justify-center flex-shrink-0">
                                    <span class="text-[10px] font-bold text-[#004D9D]" x-text="a.name.charAt(0).toUpperCase()"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 truncate" x-text="a.name"></p>
                                </div>
                                <span class="text-xs font-bold text-[#004D9D] flex-shrink-0" x-text="a.count.toLocaleString('pt-BR') + ' anot.'"></span>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!results || results.length === 0">
                    <p class="px-4 py-6 text-center text-xs text-gray-400">Nenhum plantonista encontrado.</p>
                </template>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 5: Feedback do Sistema                                       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="flex items-center justify-between gap-2 mb-3">
            <div class="flex items-center gap-2">
                <div class="w-1 h-5 rounded-full bg-violet-500"></div>
                <h2 class="text-sm font-semibold text-gray-700">Feedback do Sistema</h2>
                <span class="text-[10px] text-gray-400">respostas internas</span>
            </div>
            <a href="{{ route('feedback') }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100 transition-colors flex-shrink-0">
                <i class="fas fa-external-link-alt fa-xs"></i>
                Abrir formulário
            </a>
        </div>

        @if($feedbackStats['total'] === 0)
        <div class="bg-white border border-gray-200 rounded-xl px-6 py-8 text-center">
            <i class="fas fa-comment-dots text-gray-200 text-3xl mb-2 block"></i>
            <p class="text-sm text-gray-400">Nenhum feedback recebido ainda.</p>
        </div>
        @else
        @php
            $ratingMap = [
                'excelente' => ['label' => 'Excelente', 'emoji' => '🤩', 'color' => 'bg-emerald-500'],
                'bom'       => ['label' => 'Bom',       'emoji' => '😊', 'color' => 'bg-sky-500'],
                'regular'   => ['label' => 'Regular',   'emoji' => '😐', 'color' => 'bg-amber-400'],
                'ruim'      => ['label' => 'Ruim',      'emoji' => '😞', 'color' => 'bg-red-500'],
            ];
            $maxRating = max(array_values($feedbackStats['by_rating']) ?: [1]);
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">

            {{-- KPI + distribuição --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-gray-500 font-medium">Total de respostas</p>
                    <span class="text-2xl font-bold text-violet-600">{{ $feedbackStats['total'] }}</span>
                </div>
                <div class="space-y-2">
                    @foreach($ratingMap as $key => $info)
                    @php $count = $feedbackStats['by_rating'][$key] ?? 0; @endphp
                    <div class="flex items-center gap-2">
                        <span class="text-sm w-5 text-center">{{ $info['emoji'] }}</span>
                        <span class="text-[10px] text-gray-500 w-14 flex-shrink-0">{{ $info['label'] }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                            <div class="{{ $info['color'] }} h-1.5 rounded-full transition-all" style="width:{{ $maxRating > 0 ? round($count/$maxRating*100) : 0 }}%"></div>
                        </div>
                        <span class="text-[10px] font-semibold text-gray-600 w-4 text-right">{{ $count }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Respostas recentes --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100">
                    <p class="text-xs text-gray-500 font-medium">Respostas recentes</p>
                </div>
                <div class="divide-y divide-gray-50 max-h-[220px] overflow-y-auto">
                    @foreach($feedbackStats['recent'] as $fb)
                    <div class="px-4 py-2.5 flex items-start gap-3">
                        <span class="text-sm flex-shrink-0 mt-0.5">{{ $ratingMap[$fb->rating]['emoji'] ?? '•' }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-xs font-medium text-gray-700 truncate">{{ $fb->display_name }}</p>
                                @if($fb->sector)
                                <span class="text-[10px] text-gray-400 truncate">{{ $fb->sector }}</span>
                                @endif
                            </div>
                            @if($fb->difficulty && $fb->difficulty !== 'Nenhuma')
                            <p class="text-[10px] text-gray-500 mt-0.5 line-clamp-2">{{ $fb->difficulty }}</p>
                            @endif
                            @if($fb->suggestion)
                            <p class="text-[10px] text-violet-500 mt-0.5 line-clamp-1"><i class="fas fa-lightbulb fa-xs mr-0.5"></i>{{ $fb->suggestion }}</p>
                            @endif
                        </div>
                        <span class="text-[9px] text-gray-300 flex-shrink-0">{{ $fb->created_at->diffForHumans() }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 6: Histórico de Atendimentos (arquivo)                       --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="flex items-center gap-2 mb-3">
            <div class="w-1 h-5 rounded-full bg-gray-400"></div>
            <h2 class="text-sm font-semibold text-gray-700">Histórico de Atendimentos</h2>
            <span class="text-[10px] text-gray-400">atendimentos com anotações registradas</span>
        </div>

        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto p-2">
                <table id="archive-table" class="min-w-full text-sm" style="width:100%">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:110px">Atendimento</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:180px">Paciente</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:160px">Setor</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:100px">Internação</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:100px">Alta</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:60px">Dias</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:70px">Anot.</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500" style="width:110px">Últ. Anot.</th>
                            <th class="px-3 py-2.5" style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── Modal de mensagens ───────────────────────────────────────────────── --}}
    <div id="archive-modal" class="fixed inset-0 z-[9998] hidden" x-data="{ open: false }" x-init="
        $el._show = () => { open = true; document.body.style.overflow = 'hidden'; };
        $el._hide = () => { open = false; document.body.style.overflow = ''; };
        $watch('open', val => { if (!val) archiveModalDestroy(); });
    ">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0"
        >
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal('archive-modal')"></div>
            <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
                <div
                    class="relative bg-white flex flex-col overflow-hidden
                           w-full h-full
                           sm:w-[95vw] sm:h-auto sm:max-h-[90vh] sm:rounded-2xl
                           lg:w-[860px]
                           shadow-2xl"
                    onclick="event.stopPropagation()"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                >
                    <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="fas fa-clock-rotate-left text-white flex-shrink-0"></i>
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-white leading-tight truncate" id="archive-modal-title">Anotações</h2>
                                <p class="text-xs text-white/70" id="archive-modal-subtitle"></p>
                            </div>
                        </div>
                        <button onclick="closeModal('archive-modal')" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto min-h-0 p-4">
                        <div id="archive-modal-loading" class="flex items-center justify-center py-12">
                            <div class="text-center">
                                <div class="w-7 h-7 border-gray-200 rounded-full animate-spin mx-auto mb-2" style="border-width:3px; border-top-color:#0071B9"></div>
                                <p class="text-xs text-gray-400">Carregando...</p>
                            </div>
                        </div>
                        <div id="archive-modal-error" class="hidden flex-col items-center justify-center py-12 gap-3">
                            <i class="fas fa-triangle-exclamation text-3xl text-amber-400"></i>
                            <p class="text-sm text-gray-500" id="archive-modal-error-msg">Não foi possível carregar as mensagens.</p>
                            <button onclick="archiveModalLoad()" class="px-3 py-1.5 text-sm bg-[#004D9D] text-white rounded-md hover:bg-[#003d7a] transition">
                                Tentar novamente
                            </button>
                        </div>
                        <div id="archive-modal-table" class="hidden">
                            <table id="modal-msgs-dt" class="min-w-full text-sm" style="width:100%">
                                <thead>
                                    <tr>
                                        <th class="text-left text-xs font-medium text-gray-500 px-2 py-1.5">Data/Hora</th>
                                        <th class="text-left text-xs font-medium text-gray-500 px-2 py-1.5">Usuário</th>
                                        <th class="text-left text-xs font-medium text-gray-500 px-2 py-1.5">Turno</th>
                                        <th class="text-left text-xs font-medium text-gray-500 px-2 py-1.5">Mensagem</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-msgs-body"></tbody>
                            </table>
                        </div>
                        <div id="archive-modal-empty" class="hidden text-center py-8 text-gray-400 text-sm">
                            Nenhuma anotação encontrada.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

const ARCHIVE_PALETTE = ['4F46E5','0891B2','059669','B45309','7C3AED','0284C7','BE185D','0F766E'];

function archiveInitials(name) {
    if (name === null || name === undefined) return '?';
    const normalized = String(name).trim();
    if (!normalized || normalized === '—') return '?';
    const parts = normalized.split(/[\s.]+/);
    let ini = (parts[0] || '').charAt(0).toUpperCase();
    if (parts.length > 1) ini += (parts[parts.length - 1] || '').charAt(0).toUpperCase();
    return ini;
}

function archiveAvatarColor(name) {
    if (name === null || name === undefined) return '#6B7280';
    name = String(name);
    if (!name || name === '—') return '#6B7280';
    let h = 0;
    for (let i = 0; i < name.length; i++) h = (Math.imul(31, h) + name.charCodeAt(i)) | 0;
    return '#' + ARCHIVE_PALETTE[Math.abs(h) % ARCHIVE_PALETTE.length];
}

let _archiveNr = null;

function archiveModalState(state) {
    document.getElementById('archive-modal-loading').classList.toggle('hidden', state !== 'loading');
    document.getElementById('archive-modal-error').classList.toggle('hidden',   state !== 'error');
    document.getElementById('archive-modal-table').classList.toggle('hidden',   state !== 'table');
    document.getElementById('archive-modal-empty').classList.toggle('hidden',   state !== 'empty');
    document.getElementById('archive-modal-error').style.display = state === 'error' ? 'flex' : '';
}

function archiveModalDestroy() {
    if (window._msgDT) {
        try { window._msgDT.destroy(); } catch(e) {}
        window._msgDT = null;
    }
}

async function archiveModalLoad() {
    const nr = _archiveNr;
    if (!nr) return;

    archiveModalDestroy();
    document.getElementById('modal-msgs-body').innerHTML = '';
    archiveModalState('loading');

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const res  = await fetch(`{{ url('/administracao/historico') }}/${nr}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data  = await res.json();
        const msgs  = data.messages || [];
        const users = data.users    || {};

        if (data.patient_name) {
            document.getElementById('archive-modal-title').textContent = data.patient_name;
        }
        document.getElementById('archive-modal-subtitle').textContent =
            (data.total || msgs.length) + ' anotações · Atend. ' + nr;

        if (msgs.length === 0) {
            archiveModalState('empty');
            return;
        }

        document.getElementById('modal-msgs-body').innerHTML = msgs.map(msg => {
            const shiftClass = {
                'Manhã': 'bg-amber-50 text-amber-700',
                'Tarde': 'bg-sky-50 text-sky-700',
                'Noite': 'bg-indigo-50 text-indigo-700',
            }[msg.turno] || 'bg-gray-100 text-gray-500';
            const photo = users[msg.user] || null;
            const ini   = archiveInitials(msg.user);
            const color = archiveAvatarColor(msg.user);
            const avatar = photo
                ? `<img src="data:image/jpeg;base64,${photo}" alt="${esc(msg.user)}" class="w-6 h-6 rounded-full object-cover flex-shrink-0">`
                : `<div class="w-6 h-6 rounded-full text-white flex items-center justify-center text-[9px] font-bold flex-shrink-0" style="background:${color}">${ini}</div>`;
            return `<tr>
                <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-500">${esc(msg.date)}</td>
                <td class="px-2 py-2">
                    <div class="flex items-center gap-1.5">${avatar}<span class="text-xs font-medium text-gray-700 whitespace-nowrap">${esc(msg.user)}</span></div>
                </td>
                <td class="px-2 py-2">
                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full ${shiftClass}">${esc(msg.turno)}</span>
                </td>
                <td class="px-2 py-2 text-xs text-gray-600 max-w-xs">${esc(msg.text)}</td>
            </tr>`;
        }).join('');

        archiveModalState('table');

        try {
            window._msgDT = new DataTable('#modal-msgs-dt', {
                language: {
                    info: 'Mostrando _START_–_END_ de _TOTAL_',
                    infoEmpty: 'Nenhum registro',
                    zeroRecords: 'Nenhuma anotação encontrada',
                    paginate: { previous: '‹', next: '›', first: '«', last: '»' },
                },
                searching:  false,
                lengthChange: false,
                order:      [[0, 'asc']],
                pageLength: 15,
                columnDefs: [{ targets: 3, orderable: false }],
            });
        } catch(e) {
            console.warn('[ArchiveModal] DataTable init error:', e);
        }

    } catch (e) {
        console.error('[ArchiveModal] load error:', e);
        document.getElementById('archive-modal-error-msg').textContent = 'Não foi possível carregar as mensagens.';
        archiveModalState('error');
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal('archive-modal');
});

document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-archive-nr]');
    if (!btn) return;

    _archiveNr = btn.dataset.archiveNr;
    const name = btn.dataset.archiveName || null;

    document.getElementById('archive-modal-title').textContent    = name || ('Atendimento ' + _archiveNr);
    document.getElementById('archive-modal-subtitle').textContent = '';
    archiveModalState('loading');

    openModal('archive-modal');
    archiveModalLoad();
});

let archiveTable;

document.addEventListener('DOMContentLoaded', function () {
    archiveTable = new DataTable('#archive-table', {
        ajax: {
            url:  '{{ route("chat.archive.client-data") }}',
            type: 'GET',
            error: function (xhr, error, thrown) {
                console.error('DataTable ajax error:', error, thrown, xhr.responseText);
            },
        },
        processing: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json',
            processing: '<div class="text-xs text-gray-400 py-1">Carregando...</div>',
            search: 'Buscar:',
        },
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, { label: 'Todos', value: -1 }],
        order: [[7, 'desc']],
        columns: [
            { data: 'nr_atendimento', render: (v) => `<span class="font-mono font-semibold text-gray-700">${v}</span>` },
            { data: 'patient_name',   render: (v) => v ? `<span class="text-gray-700">${esc(v)}</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'sector_name',    render: (v) => v ? `<span class="px-2 py-0.5 rounded bg-blue-50 text-santacasa-100 font-medium text-xs">${esc(v)}</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'dt_entrada',     render: (v) => v ? `<span class="text-gray-500 text-xs">${v}</span>` : '<span class="text-gray-300">—</span>' },
            {
                data: 'still_admitted',
                render: (v, type, row) => {
                    if (v === true)  return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-medium border border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse inline-block"></span>Internado</span>';
                    if (v === false) return `<span class="text-gray-500 text-xs">${row.dt_alta || '—'}</span>`;
                    return '<span class="text-gray-300">—</span>';
                },
            },
            { data: 'admission_days', render: (v) => v != null ? `<span class="${v >= 14 ? 'text-amber-600 font-semibold' : 'text-gray-500'} text-xs">${v}d</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'message_count',  render: (v) => `<span class="text-xs text-gray-600 font-semibold">${v}</span>` },
            { data: 'last_message_at',render: (v) => v ? `<span class="text-xs text-gray-400">${v}</span>` : '—' },
            {
                data: 'nr_atendimento', orderable: false, searchable: false,
                render: (nr, type, row) => {
                    if (type !== 'display') return nr;
                    const safeName = (row.patient_name || '')
                        .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/\n/g, ' ').replace(/\r/g, '');
                    return `<button data-archive-nr="${nr}" data-archive-name="${safeName}"
                        class="text-xs text-santacasa-100 hover:text-santacasa-200 font-medium px-2 py-1 rounded hover:bg-blue-50 transition-colors">
                        <i class="fas fa-eye fa-xs"></i> Ver
                    </button>`;
                },
            },
            { data: 'last_message_raw', visible: false, searchable: false },
        ],
        rowCallback: function (row) {
            row.querySelectorAll('td').forEach(td => td.classList.add('px-3', 'py-2.5'));
        },
    });
});
</script>
@endpush
