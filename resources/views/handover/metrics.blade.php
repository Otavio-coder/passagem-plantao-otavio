@extends('layouts.app')
@section('title', 'Métricas de Passagem de Plantão')

@php
    function fmtSec(float|int|null $sec): string {
        if ($sec === null) return '—';
        $m = intdiv((int)$sec, 60);
        $s = (int)$sec % 60;
        return $m > 0 ? "{$m}min {$s}s" : "{$s}s";
    }
    function fmtMin(float|null $min): string {
        if ($min === null) return '—';
        return number_format($min, 1).'min';
    }
@endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('chat.archive.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Métricas de Passagem de Plantão</h1>
                <p class="text-xs text-gray-500 mt-0.5">Análise de eficiência e qualidade das passagens realizadas</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('handover.metrics') }}" class="flex flex-wrap items-center gap-2">
            <select name="period" onchange="this.form.submit()"
                class="text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                <option value="7" {{ $period == 7 ? 'selected' : '' }}>Últimos 7 dias</option>
                <option value="30" {{ $period == 30 ? 'selected' : '' }}>Últimos 30 dias</option>
                <option value="90" {{ $period == 90 ? 'selected' : '' }}>Últimos 90 dias</option>
                <option value="180" {{ $period == 180 ? 'selected' : '' }}>Últimos 6 meses</option>
            </select>
            @if($sectors->isNotEmpty())
            <select name="sector" onchange="this.form.submit()"
                class="text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30">
                <option value="">Todos os setores</option>
                @foreach($sectors as $s)
                    <option value="{{ $s }}" {{ $sectorFilter === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            @endif
        </form>
    </div>

    @if($totalSessions === 0)
    <div class="bg-gray-50 border border-gray-200 rounded-xl px-6 py-12 text-center">
        <i class="fas fa-chart-bar text-gray-300 text-4xl mb-3"></i>
        <p class="text-gray-500 text-sm">Nenhuma passagem concluída no período selecionado.</p>
    </div>
    @else

    {{-- ── Overview cards ────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Passagens</p>
            <p class="text-2xl font-bold text-emerald-600 mt-0.5">{{ $totalSessions }}</p>
            <p class="text-xs text-gray-400 mt-0.5">concluídas</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Duração média</p>
            <p class="text-2xl font-bold text-blue-600 mt-0.5">{{ fmtMin($avgDurationSec ? round($avgDurationSec/60,1) : null) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">por sessão</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Tempo/leito</p>
            <p class="text-2xl font-bold text-indigo-600 mt-0.5">{{ fmtSec($avgSecPerBed) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">média por leito</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Leitos/min</p>
            <p class="text-2xl font-bold text-sky-600 mt-0.5">{{ $avgBedsPerMinute ? number_format($avgBedsPerMinute, 2) : '—' }}</p>
            <p class="text-xs text-gray-400 mt-0.5">velocidade média</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Horas economizadas</p>
            <p class="text-2xl font-bold text-teal-600 mt-0.5">{{ $hoursSavedMonth !== null ? $hoursSavedMonth.'h' : '—' }}</p>
            <p class="text-xs text-gray-400 mt-0.5">vs. 45min manual</p>
        </div>
    </div>

    {{-- ── Risk + Consistency ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Risk index --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-triangle-exclamation text-amber-500"></i>
                <h2 class="text-sm font-semibold text-gray-700">Índice de Risco</h2>
                <span class="ml-auto text-xs text-gray-400">Revisões rápidas demais (&lt;60s/leito)</span>
            </div>
            <div class="flex items-end gap-3">
                <p class="text-4xl font-bold {{ $riskIndex > 20 ? 'text-red-500' : ($riskIndex > 10 ? 'text-amber-500' : 'text-emerald-500') }}">
                    {{ $riskIndex }}%
                </p>
                <p class="text-sm text-gray-500 mb-1">
                    {{ $riskyCount }} de {{ $totalSessions }} sessões
                </p>
            </div>
            <div class="mt-3 h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all {{ $riskIndex > 20 ? 'bg-red-400' : ($riskIndex > 10 ? 'bg-amber-400' : 'bg-emerald-400') }}"
                     style="width: {{ min($riskIndex, 100) }}%"></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">
                {{ $riskIndex > 20 ? 'Atenção: muitas passagens abaixo de 1 minuto por leito' : ($riskIndex > 10 ? 'Moderado: algumas passagens merecem atenção' : 'Bom: poucas revisões muito rápidas') }}
            </p>
        </div>

        {{-- Consistency by sector --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-chart-line text-blue-500"></i>
                <h2 class="text-sm font-semibold text-gray-700">Índice de Consistência por Setor</h2>
            </div>
            @if(empty($consistencyBySector))
                <p class="text-xs text-gray-400">Mínimo 3 sessões por setor para calcular consistência.</p>
            @else
                <div class="space-y-2.5">
                    @foreach($consistencyBySector as $c)
                    <div>
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-gray-600 truncate max-w-[180px]" title="{{ $c['sector'] }}">{{ $c['sector'] }}</span>
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded
                                {{ $c['label'] === 'Estável' ? 'bg-emerald-50 text-emerald-700' : ($c['label'] === 'Moderado' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') }}">
                                {{ $c['label'] }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $c['label'] === 'Estável' ? 'bg-emerald-400' : ($c['label'] === 'Moderado' ? 'bg-amber-400' : 'bg-red-400') }}"
                                     style="width: {{ min(($c['cv'] ?? 0) * 200, 100) }}%"></div>
                            </div>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap">
                                {{ fmtMin($c['avg_min']) }} ±{{ fmtMin($c['std_min']) }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <p class="text-[10px] text-gray-400 mt-3">CV ≤0.15 = Estável · CV ≤0.35 = Moderado · CV >0.35 = Instável</p>
            @endif
        </div>
    </div>

    {{-- ── By sector ────────────────────────────────────────────────────── --}}
    @if($bySector->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-hospital text-blue-500 text-sm"></i>
            <h2 class="text-sm font-semibold text-gray-700">Média por Setor</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[10px] text-gray-500 font-semibold uppercase">Setor</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Sessões</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Duração média</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Tempo/leito</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Leitos/min</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Leitos/sessão</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Risco</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($bySector as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 font-medium text-gray-700 max-w-[200px] truncate" title="{{ $s['sector_name'] }}">
                            {{ $s['sector_name'] }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $s['sessions'] }}</td>
                        <td class="px-4 py-2.5 text-right text-blue-700 font-mono">{{ fmtMin($s['avg_duration_min']) }}</td>
                        <td class="px-4 py-2.5 text-right text-indigo-700 font-mono">{{ fmtSec($s['avg_sec_per_bed']) }}</td>
                        <td class="px-4 py-2.5 text-right text-sky-700 font-mono">{{ $s['avg_beds_per_min'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $s['avg_beds'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <span class="font-semibold {{ $s['risk_pct'] > 20 ? 'text-red-600' : ($s['risk_pct'] > 10 ? 'text-amber-600' : 'text-emerald-600') }}">
                                {{ $s['risk_pct'] }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── By shift + Weekly evolution (side by side on lg) ─────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- By shift --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-clock text-indigo-500 text-sm"></i>
                <h2 class="text-sm font-semibold text-gray-700">Média por Turno</h2>
            </div>
            <div class="p-4 space-y-3">
                @forelse($byShift as $s)
                <div class="flex items-center gap-3">
                    <span class="w-16 text-[11px] font-semibold px-2 py-1 rounded text-center
                        {{ $s['shift_key'] === 'morning' ? 'bg-yellow-50 text-yellow-700' : ($s['shift_key'] === 'afternoon' ? 'bg-sky-50 text-sky-700' : 'bg-indigo-50 text-indigo-700') }}">
                        {{ $s['shift'] }}
                    </span>
                    <div class="flex-1">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-600">{{ $s['sessions'] }} sessões</span>
                            <span class="font-mono font-semibold text-gray-800">{{ fmtMin($s['avg_duration_min']) }}</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            @php $maxMin = $byShift->max('avg_duration_min') ?: 1; @endphp
                            <div class="h-full rounded-full {{ $s['shift_key'] === 'morning' ? 'bg-yellow-400' : ($s['shift_key'] === 'afternoon' ? 'bg-sky-400' : 'bg-indigo-400') }}"
                                 style="width: {{ $s['avg_duration_min'] ? min($s['avg_duration_min'] / $maxMin * 100, 100) : 0 }}%"></div>
                        </div>
                    </div>
                    <span class="text-[10px] text-gray-400 w-14 text-right">{{ $s['avg_beds'] ?? '—' }} leitos</span>
                </div>
                @empty
                <p class="text-xs text-gray-400">Sem dados.</p>
                @endforelse
            </div>
        </div>

        {{-- Weekly evolution --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-chart-area text-emerald-500 text-sm"></i>
                <h2 class="text-sm font-semibold text-gray-700">Evolução Semanal</h2>
            </div>
            <div class="p-4">
                @if($weeklyEvolution->isEmpty())
                    <p class="text-xs text-gray-400">Sem dados suficientes.</p>
                @else
                    @php $maxWkMin = $weeklyEvolution->max('avg_duration_min') ?: 1; @endphp
                    <div class="space-y-2">
                        @foreach($weeklyEvolution as $w)
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-400 w-12 text-right">{{ $w['week_label'] }}</span>
                            <div class="flex-1 h-5 bg-gray-50 rounded overflow-hidden relative">
                                <div class="h-full bg-emerald-400/80 rounded"
                                     style="width: {{ $w['avg_duration_min'] ? min($w['avg_duration_min'] / $maxWkMin * 100, 100) : 0 }}%"></div>
                                <span class="absolute inset-0 flex items-center pl-2 text-[10px] font-mono text-gray-700">
                                    {{ fmtMin($w['avg_duration_min']) }} · {{ $w['sessions'] }} sess. · {{ $w['avg_beds'] ?? '—' }} leitos
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Recent sessions detail ────────────────────────────────────────── --}}
    @if($recentSessions->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-list text-gray-400 text-sm"></i>
            <h2 class="text-sm font-semibold text-gray-700">Últimas sessões — tempo por paciente individual</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[10px] text-gray-500 font-semibold uppercase">Enfermeiro</th>
                        <th class="px-4 py-2.5 text-left text-[10px] text-gray-500 font-semibold uppercase">Data/Hora</th>
                        <th class="px-4 py-2.5 text-left text-[10px] text-gray-500 font-semibold uppercase">Setor</th>
                        <th class="px-4 py-2.5 text-left text-[10px] text-gray-500 font-semibold uppercase">Turno</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Leitos</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Duração</th>
                        <th class="px-4 py-2.5 text-right text-[10px] text-gray-500 font-semibold uppercase">Tempo/leito</th>
                        <th class="px-4 py-2.5 text-left text-[10px] text-gray-500 font-semibold uppercase">Leitos cobertos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentSessions as $h)
                    <tr class="hover:bg-gray-50 {{ $h['is_risky'] ? 'bg-red-50/40' : '' }}">
                        <td class="px-4 py-2 text-gray-700 font-medium">{{ $h['user_name'] }}</td>
                        <td class="px-4 py-2 text-gray-400 font-mono">{{ $h['started_at'] }}</td>
                        <td class="px-4 py-2 text-gray-600 max-w-[120px] truncate" title="{{ $h['sector_name'] }}">{{ $h['sector_name'] }}</td>
                        <td class="px-4 py-2">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold
                                {{ $h['shift_key'] === 'morning' ? 'bg-yellow-50 text-yellow-700' : ($h['shift_key'] === 'afternoon' ? 'bg-sky-50 text-sky-700' : 'bg-indigo-50 text-indigo-700') }}">
                                {{ $h['shift'] }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-gray-600">{{ $h['beds_visited'] }}/{{ $h['beds_total'] }}</td>
                        <td class="px-4 py-2 text-right font-mono text-blue-700">{{ fmtMin($h['duration_min']) }}</td>
                        <td class="px-4 py-2 text-right font-mono font-semibold {{ $h['is_risky'] ? 'text-red-600' : 'text-gray-700' }}">
                            {{ fmtSec($h['sec_per_bed']) }}
                            @if($h['is_risky'])
                                <i class="fas fa-triangle-exclamation text-red-400 ml-0.5"></i>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if(!empty($h['bed_codes']))
                                <div class="flex flex-wrap gap-1">
                                    @foreach(array_slice($h['bed_codes'], 0, 6) as $bed)
                                        <span class="inline-block px-1 py-0.5 bg-gray-100 rounded text-[10px] font-mono">{{ $bed }}</span>
                                    @endforeach
                                    @if(count($h['bed_codes']) > 6)
                                        <span class="text-gray-400 text-[10px]">+{{ count($h['bed_codes']) - 6 }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Note about "time between beds" --}}
    <p class="text-[11px] text-gray-400 text-center">
        <i class="fas fa-info-circle"></i>
        O tempo entre leitos não é mensurável com os dados atuais (exigiria registro de timestamp por leito individual).
        As demais métricas são calculadas a partir da duração total da sessão dividida pelos leitos visitados.
    </p>

    @endif
</div>
@endsection
