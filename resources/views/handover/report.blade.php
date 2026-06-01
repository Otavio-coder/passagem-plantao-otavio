@extends('layouts.app')

@php
$shiftBadge = [
    'morning'   => 'bg-sky-100 text-sky-800',
    'afternoon' => 'bg-amber-100 text-amber-800',
    'night'     => 'bg-indigo-100 text-indigo-800',
];
@endphp

@section('content')
<div class="space-y-5">

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.dashboard') }}"
               class="w-10 h-10 rounded-xl bg-[#004D9D]/10 flex items-center justify-center flex-shrink-0 hover:bg-[#004D9D]/20 transition-colors"
               title="Histórico de anotações">
                <i class="fas fa-arrow-left text-[#004D9D]"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">Métricas do Módulo de Passagem de Plantão</h1>
                <p class="text-sm text-gray-500 mt-0.5">Sessões estruturadas de passagem registradas no sistema</p>
            </div>
        </div>

        <form method="GET" action="{{ route('handover.metrics') }}" class="flex flex-wrap items-center gap-2">
            <select name="period" onchange="this.form.submit()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30">
                <option value="7"   {{ $period == 7   ? 'selected' : '' }}>Últimos 7 dias</option>
                <option value="30"  {{ $period == 30  ? 'selected' : '' }}>Últimos 30 dias</option>
                <option value="90"  {{ $period == 90  ? 'selected' : '' }}>Últimos 90 dias</option>
                <option value="180" {{ $period == 180 ? 'selected' : '' }}>Últimos 6 meses</option>
            </select>

            @if($sectors->isNotEmpty())
            <select name="sector" onchange="this.form.submit()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 max-w-[200px]">
                <option value="">Todos os setores</option>
                @foreach($sectors as $s)
                    <option value="{{ $s }}" {{ $sectorFilter === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            @endif

            @if($sectorFilter)
            <a href="{{ route('handover.report', ['period' => $period]) }}"
               class="px-3 py-2 text-xs text-gray-500 hover:text-red-600 border border-gray-200 rounded-lg hover:border-red-300 transition-colors">
                <i class="fas fa-times mr-1"></i>Limpar filtro
            </a>
            @endif
        </form>
    </div>

    @if($totalCompleted === 0)
    <div class="bg-gray-50 border border-gray-200 rounded-xl px-6 py-12 text-center">
        <i class="fas fa-clipboard-list text-gray-300 text-3xl mb-3 block"></i>
        <p class="text-gray-500 font-medium">Nenhuma passagem concluída no período selecionado.</p>
        <p class="text-gray-400 text-sm mt-1">Ajuste o filtro de período ou verifique se a funcionalidade está sendo utilizada.</p>
    </div>
    @else

    {{-- ── Sumário horizontal ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100">

            <div class="px-5 py-4">
                <p class="text-sm text-gray-500 mb-1">Passagens concluídas</p>
                <p class="text-3xl font-bold text-[#004D9D]">{{ number_format($totalCompleted) }}</p>
                @if($completionRate !== null)
                <p class="text-xs text-gray-400 mt-1">{{ $completionRate }}% de conclusão</p>
                @endif
            </div>

            <div class="px-5 py-4">
                <p class="text-sm text-gray-500 mb-1">Enfermeiros ativos</p>
                <p class="text-3xl font-bold text-[#004D9D]">{{ $activeNurses }}</p>
                <p class="text-xs text-gray-400 mt-1">utilizaram o sistema</p>
            </div>

            <div class="px-5 py-4">
                <p class="text-sm text-gray-500 mb-1">Setores com passagens</p>
                <p class="text-3xl font-bold text-[#004D9D]">{{ $activeSectors }}</p>
                <p class="text-xs text-gray-400 mt-1">setores registrados</p>
            </div>

            <div class="px-5 py-4">
                <p class="text-sm text-gray-500 mb-1">Média de leitos</p>
                <p class="text-3xl font-bold text-[#004D9D]">{{ $avgBeds ? number_format($avgBeds, 1) : '—' }}</p>
                <p class="text-xs text-gray-400 mt-1">por passagem concluída</p>
            </div>

        </div>
    </div>

    @if($totalAbandoned + $totalCanceled > 0)
    <div class="flex items-start gap-2.5 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
        <i class="fas fa-triangle-exclamation flex-shrink-0 mt-0.5 text-amber-500"></i>
        <span>
            No período selecionado: <strong>{{ $totalAbandoned }}</strong> {{ $totalAbandoned === 1 ? 'sessão abandonada' : 'sessões abandonadas' }}
            @if($totalCanceled > 0)e <strong>{{ $totalCanceled }}</strong> {{ $totalCanceled === 1 ? 'cancelada' : 'canceladas' }}@endif
            — iniciadas mas não concluídas, não incluídas nos totais acima.
        </span>
    </div>
    @endif

    {{-- ── Dois painéis: turnos + gráfico ────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- Distribuição por turno --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col gap-3">
            <p class="text-sm font-semibold text-gray-700">Por turno</p>

            @foreach([
                ['label' => 'Manhã',  'key' => 'Manhã',  'color' => 'bg-sky-400',    'text' => 'text-sky-700',    'bg' => 'bg-sky-50'],
                ['label' => 'Tarde',  'key' => 'Tarde',  'color' => 'bg-amber-400',  'text' => 'text-amber-700',  'bg' => 'bg-amber-50'],
                ['label' => 'Noite',  'key' => 'Noite',  'color' => 'bg-indigo-400', 'text' => 'text-indigo-700', 'bg' => 'bg-indigo-50'],
            ] as $t)
            @php $sh = $byShift[$t['key']] ?? ['sessions' => 0, 'nurses' => 0]; @endphp
            <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg {{ $t['bg'] }}">
                <div class="w-2 h-8 rounded-full {{ $t['color'] }} flex-shrink-0"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium {{ $t['text'] }}">{{ $t['label'] }}</p>
                    <p class="text-xs text-gray-500">{{ $sh['nurses'] }} {{ $sh['nurses'] === 1 ? 'enfermeiro' : 'enfermeiros' }}</p>
                </div>
                <p class="text-2xl font-bold {{ $t['text'] }}">{{ $sh['sessions'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Gráfico diário --}}
        @if(array_sum($dailyCounts) > 0)
        <div class="sm:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-5">
            <p class="text-sm font-semibold text-gray-700 mb-4">Passagens por dia — últimos {{ count($dailyLabels) }} dias</p>
            @php $maxCount = max(1, max($dailyCounts)); @endphp
            <div class="flex items-end gap-1 h-20">
                @foreach($dailyCounts as $idx => $count)
                @php $pct = round($count / $maxCount * 100); @endphp
                <div class="flex-1 flex flex-col items-center justify-end h-full gap-1">
                    @if($count > 0)
                    <span class="text-[9px] text-gray-400 leading-none">{{ $count }}</span>
                    @endif
                    <div class="w-full rounded-t {{ $count > 0 ? 'bg-[#004D9D]' : 'bg-gray-100' }}"
                         style="height: {{ max(2, $pct) }}%"
                         title="{{ $dailyLabels[$idx] }}: {{ $count }}"></div>
                </div>
                @endforeach
            </div>
            <div class="flex gap-1 mt-1.5">
                @foreach($dailyLabels as $label)
                <div class="flex-1 text-center text-[9px] text-gray-400">{{ $label }}</div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ── Por setor ───────────────────────────────────────────────────── --}}
    @if($bySector->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-700">Utilização por setor</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr class="text-xs text-gray-500 font-medium">
                        <th class="text-left px-5 py-3">Setor</th>
                        <th class="text-right px-4 py-3">Passagens</th>
                        <th class="text-right px-4 py-3 hidden sm:table-cell">Enfermeiros</th>
                        <th class="text-right px-4 py-3 hidden sm:table-cell">Média de leitos</th>
                        <th class="text-right px-4 py-3 hidden md:table-cell">Duração média</th>
                        <th class="text-right px-5 py-3">Última passagem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @foreach($bySector as $row)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3.5 font-medium text-gray-900">{{ $row['sector_name'] }}</td>
                        <td class="px-4 py-3.5 text-right font-bold text-[#004D9D]">{{ $row['sessions'] }}</td>
                        <td class="px-4 py-3.5 text-right text-gray-600 hidden sm:table-cell">{{ $row['nurses'] }}</td>
                        <td class="px-4 py-3.5 text-right text-gray-600 hidden sm:table-cell">
                            {{ $row['avg_beds'] ? number_format($row['avg_beds'], 1) : '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-right text-gray-600 hidden md:table-cell">
                            {{ $row['avg_min'] ? number_format($row['avg_min'], 1).'min' : '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-right text-gray-400 text-xs">{{ $row['last_session'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Registro de sessões ─────────────────────────────────────────── --}}
    @if($recentSessions->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-2">
            <p class="text-sm font-semibold text-gray-700">Registro de sessões</p>
            <span class="text-xs text-gray-400">últimas {{ $recentSessions->count() }} concluídas</span>
        </div>

        {{-- Mobile --}}
        <div class="divide-y divide-gray-100 sm:hidden">
            @foreach($recentSessions as $s)
            <div class="px-4 py-3.5">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 text-sm truncate">{{ $s['user_name'] }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $s['sector_name'] }}</p>
                    </div>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0 {{ $shiftBadge[$s['shift_key']] ?? 'bg-gray-100 text-gray-600' }}">
                        {{ $s['shift'] }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                    <span>{{ $s['started_at'] }} {{ $s['started_time'] }}</span>
                    <span>{{ $s['beds_visited'] }}/{{ $s['beds_expected'] ?: $s['beds_visited'] }} leitos</span>
                    @if($s['duration_min'])<span>{{ $s['duration_min'] }}min</span>@endif
                    @if($s['forced_finish'])<span class="text-amber-600 font-medium">encerramento forçado</span>@endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Desktop --}}
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr class="text-xs text-gray-500 font-medium">
                        <th class="text-left px-5 py-3">Enfermeiro</th>
                        <th class="text-left px-4 py-3">Setor</th>
                        <th class="text-left px-4 py-3">Turno</th>
                        <th class="text-right px-4 py-3">Leitos</th>
                        <th class="text-right px-4 py-3">Duração</th>
                        <th class="text-right px-5 py-3">Data / hora</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @foreach($recentSessions as $s)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $s['user_name'] }}</td>
                        <td class="px-4 py-3 text-gray-600 max-w-[160px] truncate">{{ $s['sector_name'] }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $shiftBadge[$s['shift_key']] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ $s['shift'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700 tabular-nums">
                            {{ $s['beds_visited'] }}
                            @if($s['beds_expected'] && $s['beds_expected'] !== $s['beds_visited'])
                            <span class="text-gray-400">/{{ $s['beds_expected'] }}</span>
                            @endif
                            @if($s['forced_finish'])
                            <span class="ml-1.5 text-[10px] text-amber-600">forçada</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500 tabular-nums">
                            {{ $s['duration_min'] ? $s['duration_min'].'min' : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right text-gray-400 text-xs tabular-nums whitespace-nowrap">
                            {{ $s['started_at'] }} {{ $s['started_time'] }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @endif
</div>
@endsection
