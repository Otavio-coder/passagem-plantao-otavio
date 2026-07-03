@extends('layouts.app')
@section('title', 'Panorama do Sistema')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/cal-heatmap/dist/cal-heatmap.css">
<style>
    [x-cloak] { display: none !important; }
    #archive-table_wrapper .dt-search input,
    #archive-table_wrapper .dataTables_filter input {
        border: 1px solid #D1D5DB; border-radius: 0.375rem;
        padding: 0.25rem 0.625rem; font-size: 0.875rem; outline: none; margin-left: 4px;
    }
    #archive-table_wrapper .dt-search input:focus,
    #archive-table_wrapper .dataTables_filter input:focus {
        border-color: #0071B9; box-shadow: 0 0 0 2px rgba(0,113,185,.15);
    }
    #archive-table_wrapper .dt-length select, .dataTables_length select {
        border: 1px solid #D1D5DB; border-radius: 0.375rem;
        padding: 0.25rem 1.5rem 0.25rem 0.5rem; font-size: 0.875rem; margin-right: 0.5rem;
    }
    #archive-table_wrapper .dt-paging button {
        border: 1px solid #E5E7EB; border-radius: 0.375rem;
        padding: 0.25rem 0.625rem; font-size: 0.75rem; color: #4B5563; margin: 0 1px;
    }
    #archive-table_wrapper .dt-paging button.current { background-color: #0071B9 !important; color: white !important; border-color: #0071B9 !important; }
    #archive-table_wrapper .dt-paging button:hover:not(.current) { background-color: #F3F4F6; }
    #archive-table th { white-space: nowrap; }
    main { background-color: transparent !important; }
</style>
@endpush

@push('page-bg')
<div class="fixed inset-0 pointer-events-none select-none overflow-hidden bg-gray-50" aria-hidden="true" style="z-index:0"></div>
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
                <p class="text-sm text-gray-500 mt-0.5">Usuários, setores, anotações e passagens de plantão</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if(session('cache_cleared'))
            <span class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                <i class="fas fa-check-circle"></i> Métricas atualizadas
            </span>
            @endif
            <form method="POST" action="{{ route('metrics.cache.clear') }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-colors"
                        title="Limpa o cache e recarrega as métricas do zero">
                    <i class="fas fa-rotate-right text-[10px]"></i>
                    Atualizar métricas
                </button>
            </form>
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
                    <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-xs text-gray-500 font-medium">Acessos recentes</p>
                        @if($userMetrics['today_access']->isNotEmpty())
                            <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">
                                {{ $userMetrics['today_access']->count() }} hoje
                            </span>
                        @endif
                    </div>
                    @if($userMetrics['today_access']->isNotEmpty())
                    <div class="px-3 pt-2 pb-1">
                        <p class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wide mb-1">Hoje</p>
                        <div class="space-y-1">
                            @foreach($userMetrics['today_access'] as $u)
                            @php
                                $words = preg_split('/[\s.]+/', trim($u->name ?: 'U')) ?: ['U'];
                                $ini = strtoupper(substr($words[0] ?? 'U', 0, 1));
                                if (count($words) > 1) { $ini .= strtoupper(substr(end($words), 0, 1)); }
                                $palette = ['4F46E5','0891B2','059669','B45309','7C3AED','0284C7','BE185D','0F766E'];
                                $bg = '#'.($palette[abs(crc32($u->name ?: 'U')) % count($palette)]);
                                $hasPhoto = !empty($u->photo) && strlen($u->photo) > 100;
                                $ts = $u->last_access_at ? \Carbon\Carbon::parse($u->last_access_at)->format('H:i') : '—';
                            @endphp
                            <div class="flex items-center gap-2">
                                @if($hasPhoto)
                                    <img src="data:image/jpeg;base64,{{ $u->photo }}" alt="{{ $u->name }}" class="w-5 h-5 rounded-full object-cover flex-shrink-0">
                                @else
                                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[7px] font-bold flex-shrink-0" style="background:{{ $bg }}">{{ $ini }}</div>
                                @endif
                                <p class="text-xs text-gray-700 truncate flex-1">{{ $u->name }}</p>
                                <span class="text-[10px] text-emerald-500 flex-shrink-0 font-mono">{{ $ts }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="border-t border-dashed border-gray-100 mx-3 my-1"></div>
                    @endif
                    <div class="divide-y divide-gray-50 px-0">
                        @php
                            $todayIds = $userMetrics['today_access']->pluck('username')->all();
                            $previousAccess = $userMetrics['recent_access']->filter(fn($u) => !in_array($u->username, $todayIds))->take(5);
                        @endphp
                        @foreach($previousAccess as $u)
                        @php
                            $words = preg_split('/[\s.]+/', trim($u->name ?: 'U')) ?: ['U'];
                            $ini = strtoupper(substr($words[0] ?? 'U', 0, 1));
                            if (count($words) > 1) { $ini .= strtoupper(substr(end($words), 0, 1)); }
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

            @php
                $withBeds = $userMetrics['beds_configured'];
                $withoutBeds = $userMetrics['beds_without'];
                $totalNurses = $withBeds->count() + $withoutBeds->count();
                $pctConfigured = $totalNurses > 0 ? round($withBeds->count() / $totalNurses * 100) : 0;
            @endphp
            @if($totalNurses > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ expanded: false }">
                <div class="px-4 py-2.5 flex items-center gap-2 cursor-pointer select-none" @click="expanded = !expanded">
                    <i class="fas fa-bed text-[#004D9D] text-xs flex-shrink-0"></i>
                    <p class="text-xs font-semibold text-gray-700 flex-1">Leitos por plantonista</p>
                    <span class="text-[10px] font-bold {{ $pctConfigured >= 80 ? 'text-emerald-600' : ($pctConfigured >= 40 ? 'text-amber-600' : 'text-red-500') }}">
                        {{ $withBeds->count() }}/{{ $totalNurses }}
                    </span>
                    <i class="fas fa-chevron-down text-gray-300 text-[9px] ml-1 transition-transform" :class="expanded && 'rotate-180'"></i>
                </div>
                <div class="px-4 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500 transition-all" style="width:{{ $pctConfigured }}%"></div>
                        </div>
                        <span class="text-[10px] text-gray-400 flex-shrink-0">{{ $pctConfigured }}% configurados</span>
                    </div>
                </div>
                <div x-show="expanded" x-cloak class="border-t border-gray-100">
                    <div class="px-3 py-2 grid grid-cols-2 gap-x-3 gap-y-0.5 max-h-48 overflow-y-auto">
                        @foreach($withBeds as $n)
                        <div class="flex items-center gap-1.5 py-0.5 min-w-0" title="{{ $n->bed_list }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 flex-shrink-0"></span>
                            <span class="text-[10px] text-gray-700 truncate flex-1">{{ $n->user_name }}</span>
                            <span class="text-[9px] text-gray-400 flex-shrink-0">{{ $n->bed_count }}L</span>
                        </div>
                        @endforeach
                        @foreach($withoutBeds->take(20) as $n)
                        <div class="flex items-center gap-1.5 py-0.5 min-w-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                            <span class="text-[10px] text-gray-500 truncate flex-1">{{ $n->user_name }}</span>
                            <span class="text-[9px] text-gray-400 flex-shrink-0">—</span>
                        </div>
                        @endforeach
                    </div>
                    @if($withoutBeds->count() > 20)
                    <p class="text-[9px] text-gray-400 px-3 pb-2">+{{ $withoutBeds->count() - 20 }} sem leitos configurados</p>
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- Coluna direita: Setores Monitorados --}}
        @if($sectorPanorama['total_sectors'] > 0)
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-emerald-500"></div>
                <h2 class="text-sm font-semibold text-gray-700">Setores Monitorados</h2>
            </div>
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
    {{-- SECTION: Panorama de Passagens (período dinâmico) --}}
    <div class="overflow-hidden">

        {{-- ── Header com filtros ── --}}
        <div class="px-5 py-4 border-b border-gray-100 bg-white rounded-t-xl border border-gray-200 shadow-sm flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 mr-1">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">Panorama de Passagens</h2>
            </div>
            <span class="text-[10px] font-semibold text-gray-400">Período:</span>
            @php
                $buildPeriodUrl = fn($p) => route('admin.dashboard', array_filter(['period' => $p, 'sector' => $sectorFilter], fn($v) => $v !== null));
            @endphp
            <a href="{{ $buildPeriodUrl(null) }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all {{ $period === null ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]/40 hover:text-[#004D9D]' }}">
                Tudo
            </a>
            @foreach([7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 180 => '6 meses'] as $val => $lbl)
            <a href="{{ $buildPeriodUrl($val) }}"
               class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-all {{ $period === $val ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]/40 hover:text-[#004D9D]' }}">
                {{ $lbl }}
            </a>
            @endforeach

            @if($sectors->isNotEmpty())
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-semibold text-gray-400">Setor:</span>
                <select onchange="window.location.href='{{ route('admin.dashboard') }}?'+(this.value?'sector='+this.value:'')+'{{ $period !== null ? '&period='.$period : '' }}'"
                        class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#004D9D]/30">
                    <option value="">Todos</option>
                    @foreach($sectors as $id => $name)
                    <option value="{{ $id }}" {{ $sectorFilter == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <span class="ml-auto text-[10px] text-gray-400">
                {{ $sessions->count() }} sessões · {{ $institutionalStats['active_nurses'] }} plantonistas
            </span>
        </div>

        @if($sessions->isEmpty())
        <div class="p-12 text-center bg-white border border-t-0 border-gray-200 shadow-sm rounded-b-xl">
            <i class="fas fa-chart-bar text-gray-200 text-3xl mb-3 block"></i>
            <p class="font-semibold text-gray-500">Nenhuma passagem registrada no período</p>
            <p class="text-xs text-gray-400 mt-1">Dados aparecem conforme os enfermeiros usam o SBAR.</p>
        </div>
        @else

        <div class="p-5 md:p-7 bg-white border border-t-0 border-gray-200 shadow-sm rounded-b-xl">

        {{-- KPIs principais --}}
        <div class="flex items-start gap-2 mb-5">
            <div class="w-1 h-4 rounded-full bg-[#004D9D] mt-1 flex-shrink-0"></div>
            <div>
                <h2 class="text-sm font-semibold text-gray-700">Adoção do sistema</h2>
                <span class="text-xs text-gray-400">sessões, plantonistas e anotações no período selecionado</span>
            </div>
        </div>

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

        {{-- Calendar heatmap --}}
        @if(!empty($annotationsByDay))
        @php
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
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Atividade diária</p>
                    <p class="text-xs text-gray-400">total de anotações escritas por dia — cada quadrado = 1 dia</p>
                </div>
                <div class="flex items-center gap-2 text-[10px] text-gray-400">
                    <span>menos</span>
                    <div class="flex gap-0.5">
                        @foreach(['#FFF9C4','#FFCA28','#FF8F00','#E64A19','#B71C1C'] as $swatch)
                        <div class="w-3 h-3 rounded-sm" style="background:{{ $swatch }}"></div>
                        @endforeach
                    </div>
                    <span>mais</span>
                </div>
            </div>
            <div
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
                                    type: 'month', gutter: 6,
                                    label: {
                                        text: (ts) => { const d=new Date(ts); const k=d.getUTCFullYear()+'-'+String(d.getUTCMonth()+1).padStart(2,'0'); const lbl=d.toLocaleDateString('pt-BR',{month:'short',timeZone:'UTC'}); const n=_monthTotals[k]||0; return n>0?lbl+' ('+n+')':lbl; },
                                        position: 'top', textAlign: 'start',
                                    },
                                },
                                subDomain: { type: 'day', radius: 2, width: 13, height: 13, gutter: 2 },
                                scale: { color: { type: 'linear', range: ['#FFF9C4', '#B71C1C'], domain: [_domainMin, _domainMax] } },
                            });
                        }
                    };
                })()"
                x-init="container = $el; paint()"
                class="overflow-x-auto flex justify-center">
            </div>
        </div>
        @endif

        {{-- Distribuição por turno + Volume por hora + Classificação de conteúdo --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">

            {{-- Turnos --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-700">Sessões por turno</p>
                        <p class="text-[10px] text-gray-400">distribuição entre manhã (7–13h), tarde (13–19h) e noite (19–7h)</p>
                    </div>
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
            </div>

            {{-- Volume por hora --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-xs font-semibold text-gray-700 mb-1">Volume por hora</p>
                        <p class="text-[10px] text-gray-400">volume de anotações por hora do dia — ativo + histórico arquivado</p>
                    </div>
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
            </div>

            {{-- Classificação de conteúdo --}}
            @if(!empty($contentClassification))
            @php
                $catColors = [
                    'pendência'            => '#004D9D',
                    'risco'                => '#0071B9',
                    'conduta/procedimento' => '#073772',
                    'alta/evolução'        => '#3B82F6',
                    'condição clínica'     => '#93C5FD',
                ];
                $cumDeg = 0; $segments = [];
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
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-0.5">Tipo de conteúdo</p>
                        <p class="text-xs text-gray-400">scoring por keywords em {{ number_format(array_sum(array_column($contentClassification, 'count'))) }} anotações</p>
                    </div>
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
            </div>
            @endif
        </div>

        {{-- Análise de conteúdo: top termos + distribuição de escrita --}}
        @if(!empty($topTerms) || !empty($charDistribution))
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">

            @if(!empty($topTerms))
            @php $termsForJs = array_slice($topTerms, 0, 50, true); @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex flex-col">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Termos mais registrados</p>
                        <p class="text-xs text-gray-400">top 50 termos — tamanho = nº de mensagens; posição = TF-IDF</p>
                    </div>
                </div>
                <script>
                window.d3BubbleCloud = function(terms) {
                    return {
                        _engine: null, _runner: null, _raf: null,
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
                            const d3 = window.d3, Matter = window.Matter;
                            const { Engine, Runner, Bodies, Body, World, Events } = Matter;
                            const W = wrap.getBoundingClientRect().width || 600, H = 560;
                            const entries = Object.entries(terms);
                            const maxC = Math.max(...entries.map(([,c])=>c));
                            const minC = Math.min(...entries.map(([,c])=>c));
                            const rScale = d3.scaleLog().domain([Math.max(1,minC),maxC]).range([14,58]).clamp(true);
                            const fillScale = d3.scaleLinear().domain([0,0.25,0.55,1]).range(['#93C5FD','#3B82F6','#0071B9','#1E3A8A']);
                            const sorted = [...entries].sort(([,a],[,b])=>b-a);
                            const bubbles = sorted.map(([term,count])=>({ term, count, r:rScale(count), fill:fillScale(count/maxC), dark:(count/maxC)>=0.12 }));
                            const totalArea = bubbles.reduce((s,b)=>s+Math.PI*b.r*b.r,0);
                            const CW = Math.min(W-20,Math.max(320,Math.sqrt(totalArea)*1.6));
                            const CX = (W-CW)/2;
                            const engine = Engine.create(); engine.gravity.y = 1.2;
                            const WALL=60, wallOpts={isStatic:true,friction:1,restitution:0,render:{visible:false}};
                            World.add(engine.world,[
                                Bodies.rectangle(CX+CW/2,H+WALL/2,CW+WALL*2,WALL,wallOpts),
                                Bodies.rectangle(CX-WALL/2,H/2,WALL,H*3,wallOpts),
                                Bodies.rectangle(CX+CW+WALL/2,H/2,WALL,H*3,wallOpts),
                            ]);
                            const bodyMap = new Map();
                            bubbles.forEach((b,idx)=>{
                                const body = Bodies.circle(CX+CW/2+(Math.random()-0.5)*40,-(b.r*2)-idx*10,b.r,{restitution:0.05,friction:0.8,frictionStatic:1.0,density:b.r*0.001});
                                bodyMap.set(b.term,body); World.add(engine.world,body);
                            });
                            const tip = d3.select(wrap).append('div')
                                .style('position','absolute').style('z-index','50')
                                .style('background','#0f172a').style('color','#f1f5f9')
                                .style('border-radius','6px').style('padding','6px 12px')
                                .style('font-size','11px').style('pointer-events','none').style('opacity','0')
                                .style('transition','opacity .12s').style('white-space','nowrap')
                                .style('box-shadow','0 4px 16px rgba(0,0,0,.35)');
                            const svg = d3.select(wrap).append('svg').attr('width',W).attr('height',H).style('display','block').style('overflow','hidden');
                            svg.append('rect').attr('x',CX).attr('y',0).attr('width',CW).attr('height',H).attr('rx',8).attr('fill','none').attr('stroke','#cbd5e1').attr('stroke-width',1);
                            const nodeG = svg.selectAll('g.bnode').data(bubbles,d=>d.term).join('g').classed('bnode',true).style('cursor','default')
                                .on('mouseover',function(event,d){ d3.select(this).select('circle').attr('stroke','#fff').attr('stroke-width',2.5); tip.style('opacity','1').html(`<strong style="font-size:12px">${d.term}</strong>&ensp;<span style="color:#94a3b8">${d.count.toLocaleString()} msgs</span>`); })
                                .on('mousemove',function(event){ const b=wrap.getBoundingClientRect(); tip.style('left',(event.clientX-b.left+14)+'px').style('top',(event.clientY-b.top-44)+'px'); })
                                .on('mouseout',function(event,d){ d3.select(this).select('circle').attr('stroke',d3.color(d.fill).darker(0.8).formatHex()).attr('stroke-width',1.5); tip.style('opacity','0'); });
                            nodeG.append('circle').attr('r',d=>d.r).attr('fill',d=>d.fill).attr('stroke',d=>d3.color(d.fill).darker(0.8).formatHex()).attr('stroke-width',1.5);
                            nodeG.each(function(d){
                                const g=d3.select(this), fs=Math.max(5,Math.min(11,d.r*0.30)), fsc=Math.max(4.5,fs*0.76), tc=d.dark?'#fff':'#1e3a8a';
                                const words=d.term.split(' '), half=Math.ceil(words.length/2);
                                const lines=words.length>1?[words.slice(0,half).join(' '),words.slice(half).join(' ')]:[d.term];
                                const showFreq=d.r>20, totalH=lines.length*fs*1.2+(showFreq?fsc*1.3:0), startY=-(totalH/2)+fs*0.8;
                                const txt=g.append('text').attr('text-anchor','middle').attr('font-family','system-ui,-apple-system,sans-serif').attr('font-size',fs).attr('font-weight','700').attr('fill',tc).style('pointer-events','none').style('user-select','none');
                                lines.forEach((line,i)=>txt.append('tspan').attr('x',0).attr('y',startY+i*fs*1.2).text(line));
                                if(showFreq) txt.append('tspan').attr('x',0).attr('y',startY+lines.length*fs*1.2).attr('font-size',fsc).attr('font-weight','400').attr('fill-opacity','0.70').text(d.count.toLocaleString()+' msgs');
                            });
                            const self=this, runner=Runner.create(); Runner.run(runner,engine); this._engine=engine; this._runner=runner;
                            function render(){ nodeG.attr('transform',function(d){ const body=bodyMap.get(d.term); if(!body) return ''; return `translate(${body.position.x},${body.position.y})`; }); self._raf=requestAnimationFrame(render); }
                            self._raf=requestAnimationFrame(render);
                        }
                    };
                };
                </script>
                <div class="flex-1" x-data="window.d3BubbleCloud(@js($termsForJs))" x-init="init()">
                    <div x-ref="bubbleWrap" style="position:relative;width:100%;height:560px"></div>
                </div>
            </div>
            @endif

            @if(!empty($charDistribution))
            @php
                $cd = $charDistribution;
                $pCur = $cd['buckets']['curtas']['pct'];
                $pAde = $cd['buckets']['adequadas']['pct'];
                $pLon = $cd['buckets']['longas']['pct'];
                $aCur = $pCur * 3.6;
                $aAde = ($pCur + $pAde) * 3.6;
                $colCur = '#7FA8E9'; $colAde = '#004D9D'; $colLon = '#062047';
                $donut = "conic-gradient({$colCur} 0deg {$aCur}deg, {$colAde} {$aCur}deg {$aAde}deg, {$colLon} {$aAde}deg 360deg)";
                $avgLabelColor = match($cd['size_label']) {
                    'notas curtas' => '#F97316', 'tamanho adequado' => '#004D9D', default => '#062047',
                };
                $bucketMeta = [
                    'curtas'    => ['label' => 'Curtas',    'sub' => '< 60 ch',   'color' => $colCur],
                    'adequadas' => ['label' => 'Adequadas', 'sub' => '60–200 ch', 'color' => $colAde],
                    'longas'    => ['label' => 'Longas',    'sub' => '> 200 ch',  'color' => $colLon],
                ];
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Distribuição de escrita</p>
                        <p class="text-xs text-gray-400">{{ number_format($cd['total']) }} anotações — distribuição por extensão em caracteres</p>
                    </div>
                </div>
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
            </div>
            @endif
        </div>
        @endif

        {{-- Por unidade assistencial --}}
        @if($sectorStats->isNotEmpty())
        <div class="border-t border-gray-100 pt-7 mt-7">
            <div class="flex items-center gap-2 mb-5">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">Por unidade assistencial</h2>
                <span class="text-xs text-gray-400">sessões, cobertura de leitos e turnos documentados por unidade</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($sectorStats as $sector)
                @php
                    $cov      = $sector['shift_coverage_pct'] ?? null;
                    $inactive = $sector['days_inactive'] ?? null;
                    $inactiveColor = $inactive === null ? 'text-gray-400' : ($inactive <= 2 ? 'text-emerald-600' : ($inactive <= 7 ? 'text-amber-500' : 'text-red-500'));
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
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
                    <div class="grid grid-cols-4 divide-x divide-gray-50 border-b border-gray-100">
                        <div class="px-2 py-2 text-center" title="Média de pacientes visitados por sessão">
                            <p class="text-xs font-bold text-gray-800 tabular-nums">{{ $sector['avg_beds'] ?? '—' }}</p>
                            <p class="text-[9px] text-gray-400 leading-tight mt-0.5">pac./sessão</p>
                        </div>
                        <div class="px-2 py-2 text-center">
                            <p class="text-xs font-bold text-gray-800 tabular-nums">{{ $sector['avg_msgs_per_session'] ?? '—' }}</p>
                            <p class="text-[9px] text-gray-400 leading-tight mt-0.5">anot./sessão</p>
                        </div>
                        <div class="px-2 py-2 text-center">
                            <p class="text-xs font-bold text-gray-800 tabular-nums">{{ $sector['avg_chars'] ? $sector['avg_chars'].'ch' : '—' }}</p>
                            <p class="text-[9px] text-gray-400 leading-tight mt-0.5">chars/msg</p>
                        </div>
                        <div class="px-2 py-2 text-center" title="% de turnos com pelo menos 1 anotação desde o início do uso">
                            <p class="text-xs font-bold tabular-nums {{ $cov >= 70 ? 'text-emerald-600' : ($cov >= 40 ? 'text-amber-500' : ($cov !== null ? 'text-red-500' : 'text-gray-400')) }}">
                                {{ $cov !== null ? $cov.'%' : '—' }}
                            </p>
                            <p class="text-[9px] text-gray-400 leading-tight mt-0.5">cobertura</p>
                        </div>
                    </div>
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
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Por plantonista --}}
        <div class="border-t border-gray-100 pt-7 mt-7">
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
                        arr.sort((a,b) => {
                            const av = a[this.sortBy] ?? -Infinity, bv = b[this.sortBy] ?? -Infinity;
                            return this.sortDir === 'desc' ? bv-av : av-bv;
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

                <div class="hidden md:grid grid-cols-11 gap-2 px-4 py-2 bg-gray-50 text-[10px] font-semibold text-gray-400 border-b border-gray-100">
                    <div class="col-span-1 text-right">#</div>
                    <div class="col-span-3">Nome · Setor</div>
                    <div class="col-span-2 text-center">Sessões</div>
                    <div class="col-span-1 text-center">Anot.</div>
                    <div class="col-span-2 text-center">Leitos/sess</div>
                    <div class="col-span-1 text-center">Chars</div>
                    <div class="col-span-1 text-center">Turno</div>
                </div>

                <div class="divide-y divide-gray-50">
                    <template x-for="(nurse, idx) in paged" :key="nurse.user_id">
                        <div class="grid grid-cols-11 gap-2 px-4 py-2.5 items-center">
                            <div class="col-span-1 text-right">
                                <span class="text-xs font-bold tabular-nums"
                                      :class="page*perPage+idx===0?'text-amber-400':page*perPage+idx===1?'text-gray-400':page*perPage+idx===2?'text-amber-700':'text-gray-200'"
                                      x-text="page*perPage+idx+1"></span>
                            </div>
                            <div class="col-span-3 min-w-0">
                                <p class="text-xs font-semibold text-gray-800 truncate" x-text="nurse.name"></p>
                                <p class="text-[10px] text-gray-400 truncate" x-text="nurse.sectors || '—'"></p>
                            </div>
                            <div class="col-span-2 text-center">
                                <p class="text-sm font-bold text-[#004D9D] tabular-nums" x-text="nurse.sessions"></p>
                                <p class="text-[9px] text-gray-400 hidden md:block" x-text="nurse.sessions_per_week ? nurse.sessions_per_week+'/sem' : ''"></p>
                            </div>
                            <div class="col-span-1 text-center hidden md:block">
                                <p class="text-xs font-semibold text-gray-700 tabular-nums" x-text="nurse.total_messages"></p>
                                <p class="text-[9px] text-gray-400" x-text="nurse.avg_messages+'⌀'"></p>
                            </div>
                            <div class="col-span-2 text-center hidden md:block">
                                <p class="text-xs font-semibold text-gray-700 tabular-nums"
                                   :title="nurse.avg_beds == null && nurse.all_archive ? 'Sessões históricas — rastreamento de leitos não disponível' : ''"
                                   x-text="nurse.avg_beds || '—'"></p>
                                <p class="text-[9px] text-gray-400">leitos</p>
                            </div>
                            <div class="col-span-1 text-center hidden md:block">
                                <p class="text-xs font-semibold tabular-nums"
                                   :class="nurse.size_label==='notas curtas'?'text-amber-600':nurse.size_label==='notas longas'?'text-orange-600':'text-emerald-700'"
                                   x-text="nurse.avg_chars > 0 ? nurse.avg_chars+'ch' : '—'"></p>
                            </div>
                            <div class="col-span-1 hidden md:flex justify-center items-center">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                      :style="`color:${shiftColor(nurse.dominant_shift)}; background:${shiftColor(nurse.dominant_shift)}20`"
                                      x-text="nurse.dominant_shift || '—'"></span>
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

        </div>{{-- /panorama content --}}
        @endif {{-- /sessions->isEmpty --}}

    </div>{{-- /panorama section --}}

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECTION: Feedback do Sistema --}}
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
    {{-- SECTION: Trilha de Acessos --}}
    <div>
        <div class="flex items-center gap-2 mb-3">
            <div class="w-1 h-5 rounded-full bg-gray-400"></div>
            <h2 class="text-sm font-semibold text-gray-700">Trilha de Acessos</h2>
            <span class="text-[10px] text-gray-400">últimos acessos ao relatório de pendências, análises e pacientes</span>
        </div>
        <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500">Usuário</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500">Ação</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500">Atendimento</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500">Setor</th>
                            <th class="px-3 py-2.5 text-left text-xs font-medium text-gray-500">Quando</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($accessLog as $access)
                            @php
                                $accessBadge = match ($access->event) {
                                    \App\Models\HandoverActivityLog::EVENT_REPORT_OPEN => 'bg-sky-50 text-sky-700 border-sky-200',
                                    \App\Models\HandoverActivityLog::EVENT_ANALYSIS_OPEN => 'bg-violet-50 text-violet-700 border-violet-200',
                                    default => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="font-medium text-gray-700">{{ $access->user_name ?? '—' }}</span>
                                    @if ($access->user_role)
                                        <span class="block text-[10px] text-gray-400">{{ $access->user_role }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $accessBadge }}">
                                        {{ \App\Models\HandoverActivityLog::EVENT_LABELS[$access->event] ?? $access->event }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $access->nr_atendimento ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-600">{{ $access->sector_name ?? '—' }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ \Carbon\Carbon::parse($access->occurred_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-xs text-gray-400">Nenhum acesso registrado ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECTION: Histórico de Atendimentos --}}
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
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal('archive-modal')"></div>
            <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
                <div class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-auto sm:max-h-[90vh] sm:rounded-2xl lg:w-[860px] shadow-2xl"
                     onclick="event.stopPropagation()"
                     x-show="open"
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4">
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
<script src="https://unpkg.com/cal-heatmap/dist/cal-heatmap.min.js"></script>
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
        const res  = await fetch(`{{ route('metrics.show', '') }}/${nr}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data  = await res.json();
        const msgs  = data.messages || [];
        const users = data.users    || {};
        if (data.patient_name) document.getElementById('archive-modal-title').textContent = data.patient_name;
        document.getElementById('archive-modal-subtitle').textContent = (data.total || msgs.length) + ' anotações · Atend. ' + nr;
        if (msgs.length === 0) { archiveModalState('empty'); return; }
        document.getElementById('modal-msgs-body').innerHTML = msgs.map(msg => {
            const shiftClass = {'Manhã':'bg-amber-50 text-amber-700','Tarde':'bg-sky-50 text-sky-700','Noite':'bg-indigo-50 text-indigo-700'}[msg.turno] || 'bg-gray-100 text-gray-500';
            const photo = users[msg.user] || null;
            const ini   = archiveInitials(msg.user);
            const color = archiveAvatarColor(msg.user);
            const avatar = photo
                ? `<img src="data:image/jpeg;base64,${photo}" alt="${esc(msg.user)}" class="w-6 h-6 rounded-full object-cover flex-shrink-0">`
                : `<div class="w-6 h-6 rounded-full text-white flex items-center justify-center text-[9px] font-bold flex-shrink-0" style="background:${color}">${ini}</div>`;
            return `<tr>
                <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-500">${esc(msg.date)}</td>
                <td class="px-2 py-2"><div class="flex items-center gap-1.5">${avatar}<span class="text-xs font-medium text-gray-700 whitespace-nowrap">${esc(msg.user)}</span></div></td>
                <td class="px-2 py-2"><span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full ${shiftClass}">${esc(msg.turno)}</span></td>
                <td class="px-2 py-2 text-xs text-gray-600 max-w-xs">${esc(msg.text)}</td>
            </tr>`;
        }).join('');
        archiveModalState('table');
        try {
            window._msgDT = new DataTable('#modal-msgs-dt', {
                language: { info: 'Mostrando _START_–_END_ de _TOTAL_', infoEmpty: 'Nenhum registro', zeroRecords: 'Nenhuma anotação encontrada', paginate: { previous: '‹', next: '›', first: '«', last: '»' } },
                searching: false, lengthChange: false, order: [[0,'asc']], pageLength: 15, columnDefs: [{ targets: 3, orderable: false }],
            });
        } catch(e) { console.warn('[ArchiveModal] DataTable init error:', e); }
    } catch (e) {
        console.error('[ArchiveModal] load error:', e);
        document.getElementById('archive-modal-error-msg').textContent = 'Não foi possível carregar as mensagens.';
        archiveModalState('error');
    }
}

document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal('archive-modal'); });

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

document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#archive-table', {
        ajax: { url: '{{ route("metrics.client-data") }}', type: 'GET' },
        processing: true,
        language: { url: '//cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json', processing: '<div class="text-xs text-gray-400 py-1">Carregando...</div>', search: 'Buscar:' },
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, { label: 'Todos', value: -1 }],
        order: [[7, 'desc']],
        columns: [
            { data: 'nr_atendimento', render: (v) => `<span class="font-mono font-semibold text-gray-700">${v}</span>` },
            { data: 'patient_name',   render: (v) => v ? `<span class="text-gray-700">${esc(v)}</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'sector_name',    render: (v) => v ? `<span class="px-2 py-0.5 rounded bg-blue-50 text-santacasa-100 font-medium text-xs">${esc(v)}</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'dt_entrada',     render: (v) => v ? `<span class="text-gray-500 text-xs">${v}</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'still_admitted', render: (v, type, row) => {
                if (v === true)  return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-medium border border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse inline-block"></span>Internado</span>';
                if (v === false) return `<span class="text-gray-500 text-xs">${row.dt_alta || '—'}</span>`;
                return '<span class="text-gray-300">—</span>';
            }},
            { data: 'admission_days', render: (v) => v != null ? `<span class="${v >= 14 ? 'text-amber-600 font-semibold' : 'text-gray-500'} text-xs">${v}d</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'message_count',  render: (v) => `<span class="text-xs text-gray-600 font-semibold">${v}</span>` },
            { data: 'last_message_at', orderData: [9], render: (v) => v ? `<span class="text-xs text-gray-400">${v}</span>` : '—' },
            { data: 'nr_atendimento', orderable: false, searchable: false, render: (nr, type, row) => {
                if (type !== 'display') return nr;
                const safeName = (row.patient_name || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/\n/g,' ').replace(/\r/g,'');
                return `<button data-archive-nr="${nr}" data-archive-name="${safeName}" class="text-xs text-santacasa-100 hover:text-santacasa-200 font-medium px-2 py-1 rounded hover:bg-blue-50 transition-colors"><i class="fas fa-eye fa-xs"></i> Ver</button>`;
            }},
            { data: 'last_message_raw', visible: false, searchable: false },
        ],
        rowCallback: function (row) { row.querySelectorAll('td').forEach(td => td.classList.add('px-3', 'py-2.5')); },
    });
});
</script>
@endpush
