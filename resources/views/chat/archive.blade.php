@extends('layouts.app')
@section('title', 'Panorama do Sistema')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/cal-heatmap/dist/cal-heatmap.css">
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
    main { background-color: transparent !important; }
</style>
@endpush

@push('page-bg')
@php
    mt_srand(42);
    $_r  = fn() => mt_rand() / mt_getrandmax();
    $cols = 14;
    $BASE = 100 / $cols;
    $H    = $BASE * (sqrt(3) / 2);
    $rowY = [-$H * 0.4];
    $nRows = (int) ceil(110 / $H) + 1;
    for ($i = 1; $i <= $nRows; $i++) {
        $rowY[] = $rowY[$i - 1] + $H * (0.55 + $_r() * 0.9);
    }
    $lines = [];
    $rowCount = count($rowY);
    foreach ($rowY as $ri => $y) {
        $pts = [];
        $x = -$BASE * (0.3 + $_r() * 0.8);
        $edge = $ri === 0 || $ri === $rowCount - 1;
        while ($x < 100 + $BASE * 1.5) {
            $pts[] = ['x' => $x, 'y' => $y + ($edge ? 0 : ($_r() - 0.5) * $H * 0.28)];
            $x += $BASE * (0.45 + $_r() * 1.15);
        }
        $lines[] = $pts;
    }
    $f   = fn($p) => number_format($p['x'], 2, '.', '') . ',' . number_format($p['y'], 2, '.', '');
    $att = 'fill="rgba(7,55,114,0)" stroke="rgba(7,55,114,0.028)" stroke-width="0.12"';
    $svgPoly = '';
    for ($ri = 0; $ri < count($lines) - 1; $ri++) {
        $top = $lines[$ri]; $bot = $lines[$ri + 1];
        $ti = 0; $bi = 0; $tl = count($top); $bl = count($bot);
        while ($ti < $tl - 1 || $bi < $bl - 1) {
            $tn = $ti < $tl - 1 ? $top[$ti + 1]['x'] : PHP_INT_MAX;
            $bn = $bi < $bl - 1 ? $bot[$bi + 1]['x'] : PHP_INT_MAX;
            if ($tn <= $bn && $ti < $tl - 1) {
                if ($bi < $bl) $svgPoly .= '<polygon points="'.$f($top[$ti]).' '.$f($top[$ti+1]).' '.$f($bot[$bi]).'" '.$att.'/>';
                $ti++;
            } elseif ($bi < $bl - 1) {
                if ($ti < $tl) $svgPoly .= '<polygon points="'.$f($top[$ti]).' '.$f($bot[$bi]).' '.$f($bot[$bi+1]).'" '.$att.'/>';
                $bi++;
            } else { break; }
        }
    }
@endphp
<div class="fixed inset-0 pointer-events-none select-none overflow-hidden bg-gray-50" aria-hidden="true" style="z-index:0">
    <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid slice">
        {!! $svgPoly !!}
    </svg>
</div>
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
        <div class="flex items-center gap-2">
            @if(session('cache_cleared'))
            <span class="text-[10px] text-emerald-600 font-medium flex items-center gap-1">
                <i class="fas fa-check-circle"></i> Métricas atualizadas
            </span>
            @endif
            <form method="POST" action="{{ route('chat.archive.clear-cache') }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-colors"
                        title="Limpa o cache e recarrega as métricas do zero (Oracle + MySQL)">
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
                    <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                        <p class="text-xs text-gray-500 font-medium">Acessos recentes</p>
                        @if($userMetrics['today_access']->isNotEmpty())
                            <span class="text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">
                                {{ $userMetrics['today_access']->count() }} hoje
                            </span>
                        @endif
                    </div>

                    {{-- Hoje --}}
                    @if($userMetrics['today_access']->isNotEmpty())
                    <div class="px-3 pt-2 pb-1">
                        <p class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wide mb-1">Hoje</p>
                        <div class="space-y-1">
                            @foreach($userMetrics['today_access'] as $u)
                            @php
                                $words = preg_split('/[\s.]+/', trim($u->name ?: 'U')) ?: ['U'];
                                $ini = strtoupper(substr($words[0] ?? 'U', 0, 1));
                                if (count($words) > 1) $ini .= strtoupper(substr(end($words), 0, 1));
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

                    {{-- Anteriores --}}
                    <div class="divide-y divide-gray-50 px-0">
                        @php
                            $todayIds = $userMetrics['today_access']->pluck('username')->all();
                            $previousAccess = $userMetrics['recent_access']->filter(fn($u) => !in_array($u->username, $todayIds))->take(5);
                        @endphp
                        @foreach($previousAccess as $u)
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

            {{-- Leitos configurados --}}
            @php
                $withBeds = $userMetrics['beds_configured'];
                $withoutBeds = $userMetrics['beds_without'];
                $totalNurses = $withBeds->count() + $withoutBeds->count();
                $pctConfigured = $totalNurses > 0 ? round($withBeds->count() / $totalNurses * 100) : 0;
            @endphp
            @if($totalNurses > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
                 x-data="{ expanded: false }">
                <div class="px-4 py-2.5 flex items-center gap-2 cursor-pointer select-none"
                     @click="expanded = !expanded">
                    <i class="fas fa-bed text-[#004D9D] text-xs flex-shrink-0"></i>
                    <p class="text-xs font-semibold text-gray-700 flex-1">Leitos por plantonista</p>
                    <span class="text-[10px] font-bold {{ $pctConfigured >= 80 ? 'text-emerald-600' : ($pctConfigured >= 40 ? 'text-amber-600' : 'text-red-500') }}">
                        {{ $withBeds->count() }}/{{ $totalNurses }}
                    </span>
                    <i class="fas fa-chevron-down text-gray-300 text-[9px] ml-1 transition-transform" :class="expanded && 'rotate-180'"></i>
                </div>

                {{-- Barra de progresso sempre visível --}}
                <div class="px-4 pb-2.5">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500 transition-all" style="width:{{ $pctConfigured }}%"></div>
                        </div>
                        <span class="text-[10px] text-gray-400 flex-shrink-0">{{ $pctConfigured }}% configurados</span>
                    </div>
                </div>

                {{-- Detalhe expansível --}}
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
    {{-- SECTION 3: Análise de Logs                                           --}}
    {{-- Fonte: chat_analytics_daily (hour_counts, content_tags, char_buckets) --}}
    {{--         handover_activity_log (cobertura de passagem)                 --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @php
        $da = $userMetrics['daily_analytics'];
        $ai = $userMetrics['audit_insights'];
        $shiftLabels = ['M' => 'Manhã', 'T' => 'Tarde', 'N' => 'Noite'];
        $shiftColors = ['M' => 'bg-amber-400', 'T' => 'bg-sky-400', 'N' => 'bg-indigo-400'];
    @endphp
    @if($da['total_msgs'] > 0 || $ai['total_opens'] > 0)
    <div>
        <div class="flex items-center gap-2 mb-3">
            <div class="w-1 h-5 rounded-full bg-[#004D9D]"></div>
            <h2 class="text-sm font-semibold text-gray-700">Análise de Logs</h2>
            <span class="text-[10px] text-gray-400">chat_analytics_daily · handover_activity_log</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

            {{-- ── Col 1: Volume e distribuição temporal ── --}}
            @php
                $hours = $da['hour_counts'];
                $maxHour = !empty($hours) ? (max($hours) ?: 1) : 1;
                $topHour = !empty($hours) ? array_search(max($hours), $hours) : null;
                $maxShiftMsgs = $da['by_shift']->isNotEmpty() ? ($da['by_shift']->max('msgs') ?: 1) : 1;
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-clock text-[#004D9D] text-xs"></i>
                    <p class="text-xs font-semibold text-gray-700">Volume e horários</p>
                </div>
                <div class="px-4 py-3 space-y-3">
                    {{-- KPIs --}}
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-gray-50 rounded-lg px-2 py-2 text-center">
                            <p class="text-base font-bold text-[#004D9D] tabular-nums">{{ number_format($da['total_msgs']) }}</p>
                            <p class="text-[9px] text-gray-400">mensagens</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-2 py-2 text-center">
                            <p class="text-base font-bold text-gray-700 tabular-nums">{{ $da['active_days'] }}</p>
                            <p class="text-[9px] text-gray-400">dias ativos</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-2 py-2 text-center">
                            @if($da['peak_day'])
                            <p class="text-base font-bold text-purple-600 tabular-nums">{{ $da['peak_day']['msgs'] }}</p>
                            <p class="text-[9px] text-gray-400">pico {{ \Carbon\Carbon::parse($da['peak_day']['date'])->format('d/m') }}</p>
                            @else
                            <p class="text-base font-bold text-gray-300">—</p>
                            <p class="text-[9px] text-gray-400">pico</p>
                            @endif
                        </div>
                    </div>

                    {{-- Sparkline 24h --}}
                    @if($maxHour > 0)
                    <div>
                        <p class="text-[9px] font-semibold text-gray-400 mb-1">
                            Distribuição horária
                            @if($topHour !== false && $topHour !== null)
                            <span class="font-normal">— pico às {{ str_pad($topHour, 2, '0', STR_PAD_LEFT) }}h</span>
                            @endif
                        </p>
                        <div class="flex items-end gap-px h-10">
                            @for($h = 0; $h < 24; $h++)
                            @php
                                $val = $hours[$h] ?? 0;
                                $pct = round($val / $maxHour * 100);
                                $barColor = ($h >= 7 && $h < 13) ? 'bg-amber-300' : (($h >= 13 && $h < 19) ? 'bg-sky-300' : 'bg-indigo-300');
                            @endphp
                            <div class="flex-1 flex flex-col justify-end" title="{{ str_pad($h,2,'0',STR_PAD_LEFT) }}h: {{ number_format($val) }}">
                                <div class="w-full rounded-sm {{ $barColor }}" style="height:{{ max($pct,2) }}%"></div>
                            </div>
                            @endfor
                        </div>
                        <div class="flex justify-between text-[8px] text-gray-300 mt-0.5 px-px">
                            <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>23h</span>
                        </div>
                        <div class="flex gap-3 mt-1">
                            <span class="text-[8px] text-gray-400 flex items-center gap-0.5"><span class="inline-block w-2 h-2 rounded-sm bg-amber-300"></span>Manhã</span>
                            <span class="text-[8px] text-gray-400 flex items-center gap-0.5"><span class="inline-block w-2 h-2 rounded-sm bg-sky-300"></span>Tarde</span>
                            <span class="text-[8px] text-gray-400 flex items-center gap-0.5"><span class="inline-block w-2 h-2 rounded-sm bg-indigo-300"></span>Noite</span>
                        </div>
                    </div>
                    @endif

                    {{-- Por turno --}}
                    @if($da['by_shift']->isNotEmpty())
                    <div>
                        <p class="text-[9px] font-semibold text-gray-400 mb-1.5">Por turno</p>
                        <div class="space-y-1.5">
                            @foreach(['M','T','N'] as $sh)
                            @php $sv = $da['by_shift'][$sh] ?? null; @endphp
                            @if($sv)
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-medium text-gray-500 w-10 flex-shrink-0">{{ $shiftLabels[$sh] }}</span>
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $shiftColors[$sh] }}" style="width:{{ round($sv['msgs'] / $maxShiftMsgs * 100) }}%"></div>
                                </div>
                                <span class="text-[9px] tabular-nums text-gray-500 w-28 text-right flex-shrink-0">{{ number_format($sv['msgs']) }} · {{ $sv['days'] }}d</span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── Col 2: Conteúdo e qualidade ── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-tag text-[#004D9D] text-xs"></i>
                    <p class="text-xs font-semibold text-gray-700">Conteúdo das anotações</p>
                </div>
                <div class="px-4 py-3 space-y-4">

                    {{-- Tipos (content_tags) --}}
                    @if(!empty($da['content_tags']))
                    @php
                        $tags = collect($da['content_tags'])->filter()->sortDesc();
                        $maxTag = $tags->max() ?: 1;
                        $totalTags = $tags->sum();
                    @endphp
                    <div>
                        <p class="text-[9px] font-semibold text-gray-400 mb-2">Tipos de conteúdo <span class="font-normal">({{ number_format($totalTags) }} tags)</span></p>
                        <div class="space-y-1.5">
                            @foreach($tags as $tag => $cnt)
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-600 truncate flex-1 capitalize" title="{{ $tag }}">{{ $tag }}</span>
                                <div class="w-24 h-2 bg-gray-100 rounded-full overflow-hidden flex-shrink-0">
                                    <div class="h-full bg-[#004D9D]/50 rounded-full" style="width:{{ round($cnt / $maxTag * 100) }}%"></div>
                                </div>
                                <span class="text-[9px] text-gray-400 tabular-nums w-12 text-right flex-shrink-0">{{ number_format($cnt) }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Qualidade (char_buckets) --}}
                    @if(!empty($da['char_buckets']))
                    @php
                        $buckets = $da['char_buckets'];
                        $totalBuckets = array_sum($buckets) ?: 1;
                        $bucketColors = ['curtas' => 'bg-red-300', 'adequadas' => 'bg-emerald-400', 'longas' => 'bg-sky-400'];
                        $bucketLabels = ['curtas' => 'Curtas <30c', 'adequadas' => 'Adequadas', 'longas' => 'Longas >150c'];
                    @endphp
                    <div>
                        <p class="text-[9px] font-semibold text-gray-400 mb-2">Comprimento das mensagens</p>
                        <div class="flex h-4 rounded-lg overflow-hidden gap-px mb-2">
                            @foreach(['curtas','adequadas','longas'] as $b)
                            @php $bval = $buckets[$b] ?? 0; $bpct = round($bval / $totalBuckets * 100); @endphp
                            @if($bpct > 0)
                            <div class="{{ $bucketColors[$b] }} h-full transition-all" style="width:{{ $bpct }}%"
                                 title="{{ $bucketLabels[$b] }}: {{ number_format($bval) }} ({{ $bpct }}%)"></div>
                            @endif
                            @endforeach
                        </div>
                        <div class="grid grid-cols-3 gap-1">
                            @foreach(['curtas','adequadas','longas'] as $b)
                            @php $bval = $buckets[$b] ?? 0; $bpct = round($bval / $totalBuckets * 100); @endphp
                            <div class="text-center">
                                <div class="text-sm font-bold tabular-nums {{ $b === 'adequadas' ? 'text-emerald-600' : ($b === 'curtas' ? 'text-red-400' : 'text-sky-500') }}">{{ $bpct }}%</div>
                                <div class="text-[8px] text-gray-400">{{ $bucketLabels[$b] }}</div>
                                <div class="text-[8px] text-gray-300">{{ number_format($bval) }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- ── Col 3: Cobertura da passagem (handover_activity_log) ── --}}
            @if($ai['total_opens'] > 0)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-hospital text-[#004D9D] text-xs"></i>
                    <p class="text-xs font-semibold text-gray-700">Cobertura da passagem</p>
                </div>
                <div class="px-4 py-3 space-y-3">
                    {{-- KPIs --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-gray-50 rounded-lg px-3 py-2 text-center">
                            <p class="text-base font-bold text-[#004D9D] tabular-nums">{{ number_format($ai['total_sessions']) }}</p>
                            <p class="text-[9px] text-gray-400">sessões</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2 text-center">
                            <p class="text-base font-bold text-emerald-600 tabular-nums">{{ number_format($ai['unique_users']) }}</p>
                            <p class="text-[9px] text-gray-400">plantonistas</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="bg-gray-50 rounded-lg px-3 py-2 text-center">
                            <p class="text-base font-bold text-sky-600 tabular-nums">{{ number_format($ai['total_opens']) }}</p>
                            <p class="text-[9px] text-gray-400">pacientes abertos</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2 text-center">
                            <p class="text-base font-bold text-violet-600 tabular-nums">{{ number_format($ai['unique_patients']) }}</p>
                            <p class="text-[9px] text-gray-400">pac. únicos</p>
                        </div>
                    </div>

                    {{-- Taxa global --}}
                    @if($ai['coverage_rate'] !== null)
                    @php $cr = $ai['coverage_rate']; @endphp
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[9px] text-gray-500">Abertura → anotação</span>
                            <span class="text-sm font-bold {{ $cr >= 70 ? 'text-emerald-600' : ($cr >= 40 ? 'text-amber-600' : 'text-red-500') }}">{{ $cr }}%</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all {{ $cr >= 70 ? 'bg-emerald-500' : ($cr >= 40 ? 'bg-amber-400' : 'bg-red-400') }}"
                                 style="width:{{ min($cr,100) }}%"></div>
                        </div>
                        <p class="text-[9px] text-gray-400 mt-1">{{ number_format($ai['total_posts']) }} de {{ number_format($ai['total_opens']) }} aberturas resultaram em anotação</p>
                    </div>
                    @endif

                    {{-- Por turno --}}
                    @if(!empty($ai['by_shift']))
                    <div>
                        <p class="text-[9px] font-semibold text-gray-400 mb-1.5">Por turno</p>
                        <div class="space-y-1.5">
                            @foreach(['M','T','N'] as $sh)
                            @php $s = $ai['by_shift'][$sh] ?? null; @endphp
                            @if($s)
                            @php $spct = $s['coverage'] ?? 0; @endphp
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-medium text-gray-500 w-10 flex-shrink-0">{{ $shiftLabels[$sh] }}</span>
                                <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $spct >= 70 ? 'bg-emerald-400' : ($spct >= 40 ? 'bg-amber-400' : 'bg-red-300') }}"
                                         style="width:{{ min($spct,100) }}%"></div>
                                </div>
                                <span class="text-[9px] tabular-nums text-gray-500 w-24 text-right flex-shrink-0">
                                    {{ $s['opens'] }}→{{ $s['posts'] }}
                                    @if($s['coverage'] !== null) <span class="text-gray-400">({{ $s['coverage'] }}%)</span> @endif
                                </span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 4: Feedback do Sistema                                       --}}
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
{{-- /triangle-mesh wrapper --}}

@endsection

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
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
            { data: 'last_message_at', orderData: [9], render: (v) => v ? `<span class="text-xs text-gray-400">${v}</span>` : '—' },
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
