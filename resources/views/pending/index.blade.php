@extends('layouts.app')

@push('head')
<style>
    html, body { height: auto !important; min-height: 100vh; overflow-y: auto !important; }
    main { overflow-y: visible !important; }

    /* Badge de status */
    .badge-status {
        display: inline-flex; align-items: center;
        padding: 2px 7px; border-radius: 9999px;
        font-size: 11px; font-weight: 600; white-space: nowrap;
    }
    .badge-aguard-coleta   { background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; }
    .badge-coletado        { background:#FFFBEB; color:#92400E; border:1px solid #FDE68A; }
    .badge-resultado       { background:#F0FDF4; color:#166534; border:1px solid #BBF7D0; }
    .badge-laudo           { background:#F0FDF4; color:#15803D; border:1px solid #86EFAC; }
    .badge-laudo-scola     { background:#ECFDF5; color:#065F46; border:1px solid #6EE7B7; }
    .badge-nova-coleta     { background:#FFF7ED; color:#9A3412; border:1px solid #FDBA74; }
    .badge-execucao        { background:#F5F3FF; color:#5B21B6; border:1px solid #DDD6FE; }
    .badge-outros          { background:#F9FAFB; color:#374151; border:1px solid #E5E7EB; }
    .badge-urgente         { background:#FEF2F2; color:#991B1B; border:1px solid #FECACA; }
    .badge-antimicrobiano  { background:#FEFCE8; color:#713F12; border:1px solid #FEF08A; }

    /* KPI cards */
    #kpi-bar {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 8px;
        padding: 12px 14px 8px;
    }
    @media (max-width: 639px) {
        #kpi-bar { grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 6px; padding: 10px; }
    }
    .kpi-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-bottom: 3px solid #e5e7eb;
        border-radius: 8px;
        padding: 10px 12px 8px;
        display: flex; flex-direction: column; gap: 3px;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
        user-select: none;
        min-height: 56px;
    }
    .kpi-card:active { transform: scale(.97); }
    .kpi-card:hover  { border-color: #9ca3af; border-bottom-color: #9ca3af; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
    .kpi-card.active { border-color: #cbd5e1; border-bottom-color: #004D9D; box-shadow: 0 0 0 1px #004D9D22; }
    .kpi-card .kpi-count { font-size: 22px; font-weight: 700; color: #111827; line-height: 1; }
    .kpi-card .kpi-label { font-size: 10px; font-weight: 500; color: #9ca3af; line-height: 1.3; }
    .kpi-card.kpi-total  { border-bottom-color: #004D9D; }
    .kpi-card.kpi-overdue { border-bottom-color: #dc2626; }
    .kpi-card.kpi-overdue .kpi-count { color: #dc2626; }

    /* Filtros */
    .filter-control {
        height: 40px;
        min-height: 40px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        font-size: 13px;
        padding: 0 10px;
        outline: none;
        transition: border-color .12s, box-shadow .12s;
        -webkit-appearance: none;
        appearance: none;
    }
    select.filter-control { padding-right: 28px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M19 9l-7 7-7-7'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 8px center; background-size: 14px; }
    .filter-control:focus { border-color: #004D9D; box-shadow: 0 0 0 3px rgba(0,77,157,.12); }

    /* Tabela DataTables */
    #dt-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; overscroll-behavior-x: contain; }
    #pendencias-table { min-width: 900px; }
    #pendencias-table thead th { position: sticky; top: 0; z-index: 5; }
    #pendencias-table td, #pendencias-table th { white-space: nowrap; }
    #pendencias-table td.wrap-cell { white-space: normal; }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #d1d5db; border-radius: 6px;
        padding: 6px 10px; font-size: 13px; outline: none;
        min-height: 36px;
    }
    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: #004D9D; box-shadow: 0 0 0 2px rgba(0,77,157,.12);
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        min-width: 36px; min-height: 36px; padding: 4px 10px !important;
        font-size: 13px !important;
        border-radius: 6px !important;
        display: inline-flex !important; align-items: center; justify-content: center;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #004D9D !important; color: #fff !important; border-color: #004D9D !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current) {
        background: #f3f4f6 !important; color: #111 !important; border-color: #d1d5db !important;
    }
    .dataTables_wrapper .dataTables_info { font-size: 12px; color: #6b7280; }
</style>
@endpush

@section('content')
<div id="report-root" class="font-montserrat space-y-3">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#004D9D]/10 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-list-check text-[#004D9D]"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">Relatório de Pendências</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $sectorName ?: 'Pendências em aberto por setor' }}
                    <span id="total-count-wrapper" class="hidden">&mdash; <span id="total-rows-count" class="font-semibold text-[#004D9D]">0</span> pendências</span>
                </p>
            </div>
        </div>
        @if(!empty($selectedSectors))
        <a id="btn-export"
           href="{{ route('pending.report.export', array_merge(['hospital_ids' => $selectedHospitals], $selectedSectors ? ['sector_ids' => $selectedSectors] : [])) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl transition shadow-sm flex-shrink-0 min-h-[44px]"
           title="Exportar CSV compatível com Excel">
            <i class="fas fa-file-excel"></i>
            <span>Exportar Excel</span>
        </a>
        @endif
    </div>

    {{-- Filter toolbar --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-3">
        <div class="flex flex-wrap gap-2">

            {{-- Hospital multi-select --}}
            @php
                $hospitalBtnLabel = match(true) {
                    count($selectedHospitals) === 0 => 'Nenhum hospital',
                    count($selectedHospitals) === $hospitals->count() => 'Todos os hospitais',
                    count($selectedHospitals) === 1 => $hospitals->firstWhere('hospital_id', $selectedHospitals[0])['hospital_name'] ?? '1 hospital',
                    default => count($selectedHospitals).' hospitais',
                };
            @endphp
            <div class="relative" id="hospital-dropdown-wrap">
                <button type="button" id="hospital-dropdown-btn"
                        class="filter-control flex items-center gap-2 cursor-pointer"
                        style="min-width:160px; padding-left:12px; padding-right:32px; background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M19 9l-7 7-7-7'/%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:right 8px center; background-size:14px">
                    <span class="truncate flex-1 text-left">{{ $hospitalBtnLabel }}</span>
                </button>
                <div id="hospital-dropdown-panel" style="display:none"
                     class="absolute left-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 min-w-[240px] max-w-[340px]">
                    <form method="GET" action="{{ route('pending.report') }}" id="form-hospitals">
                        <div class="p-1 max-h-60 overflow-y-auto overscroll-contain">
                            @foreach($hospitals as $h)
                                <label class="flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-gray-50 cursor-pointer text-sm text-gray-700 min-h-[44px]">
                                    <input type="checkbox" name="hospital_ids[]" value="{{ $h['hospital_id'] }}"
                                           {{ in_array($h['hospital_id'], $selectedHospitals) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-gray-300 text-[#004D9D] flex-shrink-0">
                                    <span class="truncate">{{ $h['hospital_name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="border-t border-gray-100 p-2 flex items-center justify-between gap-2">
                            <button type="button"
                                    onclick="document.querySelectorAll('#form-hospitals input[type=checkbox]').forEach(c=>c.checked=true)"
                                    class="text-xs font-medium text-[#004D9D] px-3 py-2 rounded-lg hover:bg-[#004D9D]/8 transition min-h-[36px]">Todos</button>
                            <button type="button"
                                    onclick="document.querySelectorAll('#form-hospitals input[type=checkbox]').forEach(c=>c.checked=false)"
                                    class="text-xs font-medium text-gray-500 px-3 py-2 rounded-lg hover:bg-gray-100 transition min-h-[36px]">Limpar</button>
                            <button type="submit" onclick="showPendingLoader()"
                                    class="px-4 py-2 bg-[#004D9D] text-white text-sm font-semibold rounded-lg hover:bg-[#003d7a] transition min-h-[36px]">
                                Aplicar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                (function() {
                    var btn = document.getElementById('hospital-dropdown-btn');
                    var panel = document.getElementById('hospital-dropdown-panel');
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                    });
                    document.addEventListener('click', function(e) {
                        if (!document.getElementById('hospital-dropdown-wrap').contains(e.target)) {
                            panel.style.display = 'none';
                        }
                    });
                })();
            </script>

            {{-- Setor multi-select --}}
            @php
                $selectedNames = collect($sectorsForFilter)
                    ->filter(fn($s) => in_array($s['code'], $selectedSectors))
                    ->pluck('name');
                $sectorBtnLabel = match(true) {
                    count($selectedSectors) === 0 => 'Nenhum setor',
                    count($selectedSectors) === 1 => $selectedNames->first() ?? '1 setor',
                    default => count($selectedSectors).' setores',
                };
            @endphp
            <div class="relative" id="sector-dropdown-wrap">
                <button type="button" id="sector-dropdown-btn"
                        class="filter-control flex items-center gap-2 cursor-pointer"
                        style="min-width:160px; padding-left:12px; padding-right:32px; background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M19 9l-7 7-7-7'/%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:right 8px center; background-size:14px">
                    <span class="truncate flex-1 text-left">{{ $sectorBtnLabel }}</span>
                </button>
                <div id="sector-dropdown-panel" style="display:none"
                     class="absolute left-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 min-w-[220px] max-w-[300px]">
                    <form method="GET" action="{{ route('pending.report') }}" id="form-sectors">
                        @foreach($selectedHospitals as $hid)
                            <input type="hidden" name="hospital_ids[]" value="{{ $hid }}">
                        @endforeach
                        <div class="p-1 max-h-72 overflow-y-auto overscroll-contain">
                            @foreach($sectorsForFilter as $s)
                                <label class="flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-gray-50 cursor-pointer text-sm text-gray-700 min-h-[44px]">
                                    <input type="checkbox" name="sector_ids[]" value="{{ $s['code'] }}"
                                           {{ in_array($s['code'], $selectedSectors) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-gray-300 text-[#004D9D] flex-shrink-0">
                                    <span class="truncate">{{ $s['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="border-t border-gray-100 p-2 flex items-center justify-between gap-2">
                            <button type="button"
                                    onclick="document.querySelectorAll('#form-sectors input[type=checkbox]').forEach(c=>c.checked=true)"
                                    class="text-xs font-medium text-[#004D9D] px-3 py-2 rounded-lg hover:bg-[#004D9D]/8 transition min-h-[36px]">Todos</button>
                            <button type="button"
                                    onclick="document.querySelectorAll('#form-sectors input[type=checkbox]').forEach(c=>c.checked=false)"
                                    class="text-xs font-medium text-gray-500 px-3 py-2 rounded-lg hover:bg-gray-100 transition min-h-[36px]">Limpar</button>
                            <button type="submit" onclick="showPendingLoader()"
                                    class="px-4 py-2 bg-[#004D9D] text-white text-sm font-semibold rounded-lg hover:bg-[#003d7a] transition min-h-[36px]">
                                Aplicar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                (function() {
                    var btn = document.getElementById('sector-dropdown-btn');
                    var panel = document.getElementById('sector-dropdown-panel');
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                    });
                    document.addEventListener('click', function(e) {
                        if (!document.getElementById('sector-dropdown-wrap').contains(e.target)) {
                            panel.style.display = 'none';
                        }
                    });
                })();
            </script>

            @if(!empty($selectedSectors))
                {{-- Filtro: Tipo — populado via JS em initComplete --}}
                <select id="filter-tipo" class="filter-control" style="min-width:150px">
                    <option value="">Todos os tipos</option>
                </select>

                {{-- Filtro: Status — populado via JS em initComplete --}}
                <select id="filter-status" class="filter-control" style="min-width:190px">
                    <option value="">Todos os status</option>
                </select>

                {{-- Filtro: Classificação multi-select — populado via JS, oculto até ter dados --}}
                <div class="relative" id="classif-dropdown-wrap" style="display:none">
                    <button type="button" id="classif-dropdown-btn"
                            class="filter-control flex items-center gap-2 cursor-pointer"
                            style="min-width:160px; padding-left:12px; padding-right:32px; background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M19 9l-7 7-7-7'/%3E%3C/svg%3E\"); background-repeat:no-repeat; background-position:right 8px center; background-size:14px">
                        <span id="classif-btn-label" class="truncate flex-1 text-left">Todas as classificações</span>
                    </button>
                    <div id="classif-dropdown-panel" style="display:none"
                         class="absolute left-0 top-full mt-1 z-50 bg-white rounded-xl shadow-xl border border-gray-200 min-w-[220px] max-w-[320px]">
                        <div class="p-1 max-h-60 overflow-y-auto overscroll-contain" id="classif-checkbox-list"></div>
                        <div class="border-t border-gray-100 p-2 flex items-center justify-between gap-2">
                            <button type="button" id="classif-all"
                                    class="text-xs font-medium text-[#004D9D] px-3 py-2 rounded-lg hover:bg-[#004D9D]/8 transition min-h-[36px]">Todos</button>
                            <button type="button" id="classif-none"
                                    class="text-xs font-medium text-gray-500 px-3 py-2 rounded-lg hover:bg-gray-100 transition min-h-[36px]">Limpar</button>
                        </div>
                    </div>
                </div>
                <script>
                (function() {
                    var btn = document.getElementById('classif-dropdown-btn');
                    var panel = document.getElementById('classif-dropdown-panel');
                    var label = document.getElementById('classif-btn-label');
                    function checks() { return document.querySelectorAll('.classif-check'); }
                    function applyFilter() {
                        var selected = Array.from(checks()).filter(function(c) { return c.checked; }).map(function(c) { return c.value; });
                        if (selected.length === 0 || selected.length === checks().length) {
                            label.textContent = 'Todas as classificações';
                            if (window._dtTable) window._dtTable.column(15).search('', true, false).draw();
                        } else {
                            label.textContent = selected.length === 1 ? selected[0] : selected.length + ' classificações';
                            var regex = '^(' + selected.map(function(v) { return $.fn.dataTable.util.escapeRegex(v); }).join('|') + ')$';
                            if (window._dtTable) window._dtTable.column(15).search(regex, true, false).draw();
                        }
                    }
                    if (btn) btn.addEventListener('click', function(e) { e.stopPropagation(); panel.style.display = panel.style.display === 'none' ? 'block' : 'none'; });
                    document.addEventListener('click', function(e) {
                        var wrap = document.getElementById('classif-dropdown-wrap');
                        if (wrap && !wrap.contains(e.target)) panel.style.display = 'none';
                    });
                    document.getElementById('classif-all') && document.getElementById('classif-all').addEventListener('click', function() { checks().forEach(function(c) { c.checked = true; }); applyFilter(); });
                    document.getElementById('classif-none') && document.getElementById('classif-none').addEventListener('click', function() { checks().forEach(function(c) { c.checked = false; }); applyFilter(); });
                    document.getElementById('classif-checkbox-list') && document.getElementById('classif-checkbox-list').addEventListener('change', applyFilter);
                    window._applyClassifFilter = applyFilter;
                })();
                </script>

                {{-- Só vencidos --}}
                <label class="inline-flex items-center gap-2 cursor-pointer min-h-[40px] px-3 bg-white border border-gray-200 rounded-lg whitespace-nowrap select-none text-sm text-gray-600 hover:bg-gray-50 transition">
                    <input type="checkbox" id="chk-overdue" class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-400 cursor-pointer flex-shrink-0">
                    Só vencidos
                </label>

                {{-- Limpar filtros --}}
                <button type="button" id="btn-clear-filters"
                    class="text-sm text-gray-400 hover:text-gray-600 px-3 min-h-[40px] hover:bg-gray-100 rounded-lg transition whitespace-nowrap">
                    Limpar filtros
                </button>
            @endif

            {{-- Atualizar --}}
            @if(!empty($selectedSectors))
            <form method="POST" action="{{ route('pending.report.refresh') }}" id="form-refresh" class="ml-auto">
                @csrf
                @foreach($selectedHospitals as $hid)
                    <input type="hidden" name="hospital_ids[]" value="{{ $hid }}">
                @endforeach
                @foreach($selectedSectors as $sid)
                    <input type="hidden" name="sector_ids[]" value="{{ $sid }}">
                @endforeach
                <button type="submit" id="btn-refresh"
                    class="inline-flex items-center gap-2 px-3 min-h-[40px] text-sm text-gray-500 hover:text-[#004D9D] bg-gray-100 hover:bg-[#004D9D]/10 rounded-lg transition"
                    title="Limpar cache e recarregar">
                    <x-heroicon-o-arrow-path id="icon-refresh" class="w-4 h-4" />
                    <span id="label-refresh">Atualizar</span>
                </button>
            </form>
            <script>
                document.getElementById('form-refresh').addEventListener('submit', function () {
                    var icon = document.getElementById('icon-refresh');
                    var label = document.getElementById('label-refresh');
                    var btn = document.getElementById('btn-refresh');
                    btn.disabled = true;
                    btn.classList.add('opacity-60', 'cursor-not-allowed');
                    icon.classList.add('animate-spin');
                    if (label) label.textContent = 'Atualizando…';
                });
            </script>
            @endif
        </div>
    </div>

    {{-- Loading overlay --}}
    <div id="pending-loading"
         class="fixed inset-0 z-50 items-center justify-center"
         style="background:rgba(0,20,70,0.55);backdrop-filter:blur(3px);display:none">
        <div class="flex flex-col items-center gap-3 mt-32">
            <div class="w-10 h-10 border-4 border-white/20 border-t-white rounded-full animate-spin"></div>
            <span class="text-white text-sm font-medium tracking-wide">Carregando pendências...</span>
        </div>
    </div>
    @if(!empty($selectedSectors))
    <script>document.getElementById('pending-loading').style.display = 'flex';</script>
    @endif

    {{-- Body --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

        @if(!empty($errorMessage))
            <div class="mx-3 mt-3 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0" />
                {{ $errorMessage }}
            </div>
        @endif

        @if(!empty($selectedSectors))
            {{--
                Colunas DataTables (índices, data: object keys):
                 0  paciente         1  ugb             2  atendimento
                 3  tipo_label       4  motivo_categoria 5  item
                 6  data_solicitacao 7  data_coleta      8  data_resultado
                 9  tempo_pendente  10  setor_origem    11  prev_alta
                12  tipo_evento (hidden) 13 is_overdue (hidden)
                14  motivo_categoria (hidden) 15 classificacao (hidden)
            --}}
            <div id="kpi-bar"></div>

            <div class="pb-3 pt-1" id="dt-wrapper">
                <table id="pendencias-table" class="w-full">
                    <thead>
                        <tr class="bg-[#004D9D]/5 border-b border-[#004D9D]/10">
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Paciente</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Leito</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Atend.</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Tipo</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Status</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600">Pendência</th>
                            <th class="px-2 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Dt. Prescrição</th>
                            <th class="px-2 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Coletado</th>
                            <th class="px-2 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Resultado</th>
                            <th class="px-2 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Em aberto</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Unidade</th>
                            <th class="px-2 py-3 text-left text-xs font-semibold text-gray-600 whitespace-nowrap">Prev. Alta</th>
                            <th></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        @else
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <x-heroicon-o-building-office-2 class="w-12 h-12 text-gray-200 mb-4" />
                <h3 class="text-base font-semibold text-gray-700 mb-1">Nenhum setor selecionado</h3>
                <p class="text-sm text-gray-400">Selecione um hospital e setor no filtro acima.</p>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function showPendingLoader() { document.getElementById('pending-loading').style.display = 'flex'; }
function hidePendingLoader() { document.getElementById('pending-loading').style.display = 'none'; }
</script>

@if(!empty($selectedSectors))
<script>
$(document).ready(function () {

    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var multiSector = {{ count($selectedSectors) > 1 ? 'true' : 'false' }};

    var dtDataUrl = (function () {
        var params = new URLSearchParams();
        @foreach($selectedHospitals as $hid)
        params.append('hospital_ids[]', {{ $hid }});
        @endforeach
        @foreach($selectedSectors as $sid)
        params.append('sector_ids[]', {{ $sid }});
        @endforeach
        return '{{ route('pending.report.data') }}?' + params.toString();
    })();

    function renderStatus(r) {
        var html = '';
        if (r.motivo_categoria) {
            html += '<span class="badge-status ' + esc(r.badge_cls) + ' mb-1">' + esc(r.motivo_categoria) + '</span>';
        }
        if (r.motivo_pendente) {
            html += '<div class="flex items-start gap-1 text-[10px] mt-1">'
                + '<i class="fas fa-notes-medical flex-shrink-0 mt-0.5 text-gray-400" style="font-size:10px;width:12px"></i>'
                + '<span class="text-gray-500 font-medium mr-0.5">Tasy:</span>'
                + '<span class="text-gray-600">' + esc(r.motivo_pendente) + '</span></div>';
        }
        if (r.is_exam && r.scola_status) {
            html += '<div class="flex items-start gap-1 text-[10px] mt-0.5">'
                + '<i class="fas fa-flask flex-shrink-0 mt-0.5 text-gray-400" style="font-size:10px;width:12px"></i>'
                + '<span class="text-gray-500 font-medium mr-0.5">SCOLA:</span>'
                + '<span class="text-gray-600">' + esc(r.scola_status) + '</span></div>';
        }
        if (r.scola_resultado) {
            html += '<div class="flex items-start gap-1 text-[10px] mt-0.5">'
                + '<i class="fas fa-flask flex-shrink-0 mt-0.5 text-emerald-500" style="font-size:10px;width:12px"></i>'
                + '<span class="text-emerald-700 font-semibold">' + esc(r.scola_resultado) + '</span></div>';
        }
        return html || '<span class="text-gray-300">—</span>';
    }

    var tableReady = false;
    var tipoLabelMap = {};

    var table = $('#pendencias-table').DataTable({
        ajax: { url: dtDataUrl, dataSrc: 'data', cache: true },
        deferRender: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, { label: 'Todos', value: -1 }],
        autoWidth: false,
        scrollX: false,
        order: [],
        columns: [
            // 0 Paciente
            {
                data: 'paciente',
                render: function (d, t) {
                    if (t !== 'display') { return d || ''; }
                    return '<div class="truncate text-xs text-gray-800 font-medium" style="min-width:120px;max-width:150px" title="' + esc(d) + '">' + esc(d) + '</div>';
                },
            },
            // 1 Leito
            { data: 'ugb', className: 'px-3 py-2.5 text-xs text-gray-600 font-mono whitespace-nowrap' },
            // 2 Atendimento
            { data: 'atendimento', className: 'px-3 py-2.5 text-xs text-gray-700 font-mono font-medium whitespace-nowrap' },
            // 3 Tipo
            {
                data: 'tipo_label',
                render: function (d, t, r) {
                    if (t !== 'display') { return d || ''; }
                    var html = '<div class="truncate text-gray-700 font-semibold">' + esc(d || '-') + '</div>';
                    if (r.classificacao) { html += '<div class="truncate text-[10px] text-gray-400 mt-0.5">' + esc(r.classificacao) + '</div>'; }
                    return html;
                },
            },
            // 4 Status
            {
                data: 'motivo_categoria',
                render: function (d, t, r) {
                    if (t !== 'display') { return d || ''; }
                    return renderStatus(r);
                },
            },
            // 5 Pendência
            {
                data: 'item',
                className: 'wrap-cell',
                render: function (d, t, r) {
                    if (t !== 'display') { return d || ''; }
                    var html = '<div class="text-gray-700 font-medium leading-snug" style="min-width:180px;max-width:220px" title="' + esc(d) + '">' + esc(d) + '</div>';
                    if (r.nr_prescricao) { html += '<div class="text-[10px] text-gray-400 font-mono mt-0.5">#' + esc(r.nr_prescricao) + '</div>'; }
                    if (r.nm_prescritor) { html += '<div class="text-[10px] text-gray-400 mt-0.5 truncate" title="' + esc(r.nm_prescritor) + '">' + esc(r.nm_prescritor) + '</div>'; }
                    if (r.status_execucao) { html += '<span class="inline-block mt-0.5 px-1.5 py-px rounded border text-[10px] font-medium leading-tight bg-gray-50 text-gray-600 border-gray-200">' + esc(r.status_execucao) + '</span>'; }
                    return html;
                },
            },
            // 6 Dt. Prescrição
            {
                data: 'data_solicitacao',
                render: function (d, t, r) {
                    if (t === 'sort') { return r.data_solicitacao_sort || 0; }
                    return '<span class="text-[11px] text-gray-500 whitespace-nowrap font-mono">' + esc(d || '-') + '</span>';
                },
            },
            // 7 Coletado
            {
                data: 'data_coleta',
                render: function (d, t, r) {
                    if (t === 'sort') { return r.data_coleta_sort || 0; }
                    return '<span class="text-[11px] text-gray-500 whitespace-nowrap font-mono">' + esc(d || '-') + '</span>';
                },
            },
            // 8 Resultado
            {
                data: 'data_resultado',
                render: function (d, t, r) {
                    if (t === 'sort') { return r.data_resultado_sort || 0; }
                    if (!d) { return '<span class="text-[11px] text-gray-300 whitespace-nowrap font-mono">-</span>'; }
                    return '<span class="text-[11px] text-emerald-700 font-semibold whitespace-nowrap font-mono">' + esc(d) + '</span>';
                },
            },
            // 9 Em aberto
            {
                data: 'tempo_pendente',
                render: function (d, t, r) {
                    if (t === 'sort') { return r.tempo_pendente_sort || 0; }
                    var cls = r.is_overdue ? 'text-amber-600' : 'text-[#0071B9]';
                    return '<span class="text-[11px] whitespace-nowrap font-semibold font-mono ' + cls + '">' + esc(d || '-') + '</span>';
                },
            },
            // 10 Unidade
            {
                data: 'setor_origem',
                visible: multiSector,
                render: function (d, t) {
                    if (t !== 'display') { return d || ''; }
                    return '<div class="truncate text-xs text-gray-700 font-medium" style="max-width:130px" title="' + esc(d) + '">' + esc(d || '-') + '</div>';
                },
            },
            // 11 Prev. Alta
            {
                data: 'prev_alta',
                render: function (d, t) {
                    if (t !== 'display') { return d || ''; }
                    if (!d) { return '<span class="text-xs font-semibold text-gray-300">—</span>'; }
                    return '<span class="text-xs font-semibold text-purple-700">' + esc(d) + '</span>';
                },
            },
            // 12 tipo_evento (hidden — filtro tipo)
            { data: 'tipo_evento', visible: false, searchable: true, orderable: false },
            // 13 is_overdue (hidden — filtro vencido)
            {
                data: 'is_overdue',
                visible: false,
                searchable: true,
                orderable: false,
                render: function (d) { return d ? '1' : '0'; },
            },
            // 14 motivo_categoria (hidden — filtro status)
            { data: 'motivo_categoria', visible: false, searchable: true, orderable: false },
            // 15 classificacao (hidden — filtro classificação)
            { data: 'classificacao', visible: false, searchable: true, orderable: false },
        ],
        language: {
            url: '', decimal: ',', thousands: '.',
            emptyTable: 'Nenhuma pendência encontrada',
            info: '_START_–_END_ de _TOTAL_',
            infoEmpty: '0 registros', infoFiltered: '(filtrado de _MAX_)',
            lengthMenu: '_MENU_ por página',
            loadingRecords: 'Carregando...', processing: 'Processando...',
            search: 'Buscar:', zeroRecords: 'Nenhum resultado para este filtro',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
        },
        dom: '<"flex flex-wrap items-center justify-between gap-3 mb-3 px-3 pt-3"lf>t<"flex flex-wrap items-center justify-between gap-2 mt-3 px-3 pb-2"ip>',
        initComplete: function (settings, json) {
            if (json && json.meta) {
                populateFilterDropdowns(json.meta.tipo_labels, json.meta.categorias, json.meta.classificacoes);
                var total = json.meta.total || 0;
                if (total > 0) {
                    var cnt = document.getElementById('total-rows-count');
                    var wrap = document.getElementById('total-count-wrapper');
                    if (cnt) { cnt.textContent = total; }
                    if (wrap) { wrap.classList.remove('hidden'); }
                }
            }
            tableReady = true;
            rebuildCascadeFilters();
            rebuildKpis();
            $('#pendencias-table_filter input').addClass('filter-control').css('min-width', '180px');
            $('#pendencias-table_length select').addClass('filter-control').css('min-width', '80px');
            $('#pendencias-table_filter label, #pendencias-table_length label').addClass('text-sm text-gray-600 flex items-center gap-2');
            $('#pendencias-table_info, #pendencias-table_paginate').addClass('text-sm text-gray-500');
            hidePendingLoader();
        },
    });

    window._pendenciasTable = table;
    window._dtTable = table;
    setTimeout(hidePendingLoader, 12000);

    function populateFilterDropdowns(tipoLabels, categorias, classificacoes) {
        tipoLabelMap = tipoLabels || {};
        var tipoSel = document.getElementById('filter-tipo');
        if (tipoSel && tipoLabels) {
            var tipoHtml = '<option value="">Todos os tipos</option>';
            Object.keys(tipoLabels).sort(function (a, b) {
                return (tipoLabels[a] || a).localeCompare(tipoLabels[b] || b, 'pt-BR');
            }).forEach(function (k) {
                tipoHtml += '<option value="' + esc(k) + '">' + esc(tipoLabels[k] || k) + '</option>';
            });
            tipoSel.innerHTML = tipoHtml;
        }

        var statusSel = document.getElementById('filter-status');
        if (statusSel && categorias) {
            var statusHtml = '<option value="">Todos os status</option>';
            categorias.forEach(function (cat) {
                statusHtml += '<option value="' + esc(cat) + '">' + esc(cat) + '</option>';
            });
            statusSel.innerHTML = statusHtml;
        }

        if (classificacoes && classificacoes.length > 0) {
            var classifList = document.getElementById('classif-checkbox-list');
            if (classifList) {
                var classifHtml = '';
                classificacoes.forEach(function (cl) {
                    classifHtml += '<label class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 cursor-pointer text-sm text-gray-700 min-h-[40px]">'
                        + '<input type="checkbox" class="classif-check w-4 h-4 rounded border-gray-300 text-[#004D9D] flex-shrink-0" value="' + esc(cl) + '">'
                        + '<span class="truncate">' + esc(cl) + '</span></label>';
                });
                classifList.innerHTML = classifHtml;
                classifList.addEventListener('change', function () {
                    if (window._applyClassifFilter) { window._applyClassifFilter(); }
                });
            }
            var wrap = document.getElementById('classif-dropdown-wrap');
            if (wrap) { wrap.style.display = ''; }
        }
    }

    function rebuildSelect(selector, colIdx, allLabel, labelMap) {
        var $sel = $(selector);
        var currentVal = $sel.val();
        var vals = table.column(colIdx, { filter: 'applied' }).data().unique().sort().toArray().filter(function (v) { return v !== null && v !== ''; });
        var html = '<option value="">' + allLabel + '</option>';
        vals.forEach(function (v) {
            var label = (labelMap && labelMap[v]) ? labelMap[v] : v;
            html += '<option value="' + esc(v) + '"' + (v === currentVal ? ' selected' : '') + '>' + esc(label) + '</option>';
        });
        $sel.html(html);
    }

    function rebuildCascadeFilters() {
        rebuildSelect('#filter-tipo', 12, 'Todos os tipos', tipoLabelMap);
        rebuildSelect('#filter-status', 14, 'Todos os status', null);
    }

    var activeKpiFilter = '';

    function rebuildKpis() {
        var rows = table.rows({ filter: 'applied' });
        var total = rows.count();
        var overdue = 0;
        var catMap = {};
        rows.every(function () {
            var d = this.data();
            var cat = d.motivo_categoria || '';
            if (cat) { catMap[cat] = (catMap[cat] || 0) + 1; }
            if (d.is_overdue) { overdue++; }
        });

        var html = '<div class="kpi-card kpi-total" data-kpi=""><span class="kpi-count">' + total + '</span><span class="kpi-label">Pendências</span></div>';
        if (overdue > 0) {
            var overdueActive = $('#chk-overdue').prop('checked') ? ' active' : '';
            html += '<div class="kpi-card kpi-overdue' + overdueActive + '" data-kpi-overdue="1"><span class="kpi-count">' + overdue + '</span><span class="kpi-label">SLA Violado</span></div>';
        }
        Object.entries(catMap).sort(function (a, b) { return b[1] - a[1]; }).forEach(function (pair) {
            var cat = pair[0];
            var count = pair[1];
            var isActive = (activeKpiFilter === cat) ? ' active' : '';
            html += '<div class="kpi-card' + isActive + '" data-kpi="' + esc(cat) + '"><span class="kpi-count">' + count + '</span><span class="kpi-label">' + esc(cat) + '</span></div>';
        });
        $('#kpi-bar').html(html);

        $('#kpi-bar .kpi-card[data-kpi]').on('click', function () {
            var val = $(this).data('kpi');
            activeKpiFilter = (activeKpiFilter === val) ? '' : val;
            $('#filter-status').val(activeKpiFilter).trigger('change');
        });
        $('#kpi-bar .kpi-card[data-kpi-overdue]').on('click', function () {
            var chk = $('#chk-overdue');
            chk.prop('checked', !chk.prop('checked')).trigger('change');
        });
    }

    table.on('draw', function () {
        if (tableReady) {
            rebuildCascadeFilters();
            rebuildKpis();
        }
    });

    $('#filter-status').on('change', function () { activeKpiFilter = $(this).val(); });

    $('#filter-tipo').on('change', function () {
        table.column(12).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });
    $('#filter-status').on('change', function () {
        table.column(14).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });
    $('#chk-overdue').on('change', function () {
        table.column(13).search(this.checked ? '^1$' : '', true, false).draw();
    });
    $('#btn-clear-filters').on('click', function () {
        $('#filter-tipo, #filter-status').val('');
        $('#chk-overdue').prop('checked', false);
        document.querySelectorAll('.classif-check').forEach(function (c) { c.checked = false; });
        var lbl = document.getElementById('classif-btn-label');
        if (lbl) { lbl.textContent = 'Todas as classificações'; }
        table.columns([12, 13, 14, 15]).search('').draw();
        table.search('').draw();
    });

    @if(!empty($selectedSectors))
    (function () {
        var base = '{{ route('pending.report.export') }}?';
        @foreach($selectedHospitals as $hid) base += 'hospital_ids[]={{ $hid }}&'; @endforeach
        @foreach($selectedSectors as $sid) base += 'sector_ids[]={{ $sid }}&'; @endforeach
        var exportBtn = document.getElementById('btn-export');
        if (exportBtn) { exportBtn.href = base; }
    })();
    @endif
});
</script>
@endif
@endpush
@endsection
