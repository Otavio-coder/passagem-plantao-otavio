@extends('layouts.app')

@push('head')
<style>
    /* This page uses natural page scroll — override the locked viewport layout */
    html, body { height: auto !important; min-height: 100vh; overflow-y: auto !important; }
    main { overflow-y: visible !important; }
    /* Coluna Unidade: visível apenas com múltiplos setores selecionados */
    .col-unidade-hidden { display: none !important; }
</style>
@endpush

@section('content')
<div id="report-root" class="font-montserrat space-y-4">

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
                </p>
            </div>
        </div>
    </div>

    {{-- Filter toolbar --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">

            <form method="GET" action="{{ route('pending.report') }}" id="form-hospital">
                <select name="hospital_id"
                    class="bg-white border border-gray-300 text-gray-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D] min-w-[120px]"
                    onchange="showPendingLoader(); this.form.submit()">
                    @foreach($hospitals as $h)
                        <option value="{{ $h['hospital_id'] }}"
                            {{ (int)$selectedHospital === (int)$h['hospital_id'] ? 'selected' : '' }}>
                            {{ $h['hospital_name'] }}
                        </option>
                    @endforeach
                </select>
            </form>

                {{-- Dropdown multi-setor --}}
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
                    <button type="button"
                            id="sector-dropdown-btn"
                            class="flex items-center gap-1.5 bg-white border border-gray-300 text-gray-700 text-xs rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition min-w-[140px] max-w-[220px]">
                        <span class="truncate flex-1 text-left">{{ $sectorBtnLabel }}</span>
                        <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div id="sector-dropdown-panel"
                         style="display:none"
                         class="absolute left-0 top-full mt-1 z-50 bg-white rounded-lg shadow-xl border border-gray-200 min-w-[200px] max-w-[280px]">

                        <form method="GET" action="{{ route('pending.report') }}" id="form-sectors">
                            @if($selectedHospital)
                                <input type="hidden" name="hospital_id" value="{{ $selectedHospital }}">
                            @endif

                            <div class="p-1 max-h-56 overflow-y-auto">
                                @foreach($sectorsForFilter as $s)
                                    <label class="flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-gray-50 cursor-pointer text-xs text-gray-700">
                                        <input type="checkbox"
                                               name="sector_ids[]"
                                               value="{{ $s['code'] }}"
                                               {{ in_array($s['code'], $selectedSectors) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-[#004D9D]">
                                        <span class="truncate">{{ $s['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <div class="border-t border-gray-100 p-2 flex items-center justify-between gap-2">
                                <button type="button"
                                        onclick="document.querySelectorAll('#form-sectors input[type=checkbox]').forEach(c=>c.checked=true)"
                                        class="text-[10px] text-[#004D9D] hover:underline">Todos</button>
                                <button type="button"
                                        onclick="document.querySelectorAll('#form-sectors input[type=checkbox]').forEach(c=>c.checked=false)"
                                        class="text-[10px] text-gray-400 hover:underline">Limpar</button>
                                <button type="submit"
                                        onclick="showPendingLoader()"
                                        class="px-3 py-1 bg-[#004D9D] text-white text-[10px] font-semibold rounded-md hover:bg-[#003d7a] transition">
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

                @if($rows->count() > 0)
                    <select id="filter-tipo"
                        class="bg-white/10 border border-white/25 text-white text-xs rounded-md px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-white/40 min-w-[110px]">
                        <option value="" class="text-gray-900 bg-white">Todos os tipos</option>
                    </select>

                    <label class="inline-flex items-center gap-1.5 text-xs text-white/80 cursor-pointer whitespace-nowrap select-none">
                        <input type="checkbox" id="chk-overdue" class="rounded border-white/40 bg-white/10 text-amber-400 focus:ring-amber-400 cursor-pointer">
                        Só vencidos
                    </label>
                @endif

                @if(!empty($selectedSectors))
                <form method="POST" action="{{ route('pending.report.refresh') }}" id="form-refresh">
                    @csrf
                    <input type="hidden" name="hospital_id" value="{{ $selectedHospital }}">
                    @foreach($selectedSectors as $sid)
                        <input type="hidden" name="sector_ids[]" value="{{ $sid }}">
                    @endforeach
                    <button type="submit" id="btn-refresh"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-white/80 hover:text-white bg-white/10 hover:bg-white/20 rounded-md transition"
                        title="Limpar cache e recarregar">
                        <x-heroicon-o-arrow-path id="icon-refresh" class="w-3.5 h-3.5" />
                        <span id="label-refresh" class="hidden sm:inline">Atualizar</span>
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
    </div>

    {{-- Loading overlay --}}
    <div id="pending-loading"
         class="fixed inset-0 z-50 items-center justify-center"
         style="background:rgba(0,20,70,0.55);backdrop-filter:blur(3px);display:{{ $rows->count() > 0 ? 'flex' : 'none' }}">
        <div class="flex flex-col items-center gap-3 mt-32">
            <div class="w-10 h-10 border-4 border-white/20 border-t-white rounded-full animate-spin"></div>
            <span class="text-white text-sm font-medium tracking-wide">Carregando pendências...</span>
        </div>
    </div>

    {{-- Body --}}
    <div class="bg-white rounded-b-xl">

        @if(!empty($errorMessage))
            <div class="mx-3 mt-3 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0" />
                {{ $errorMessage }}
            </div>
        @endif

        @if($rows->count() > 0)
            <div class="px-2 pb-4 pt-2" id="dt-wrapper">
                {{--
                    Colunas (índices DataTables):
                     0  Paciente          1  Leito (md+)      2  Atend.
                     3  Tipo              4  Status (lg+)     5  Pendência
                     6  Data Prescrição   7  Lib. médica (lg+) 8  Liberado
                     9  Prev. exec.      10  Coletado (lg+)   11 Em aberto
                     12 Setor exec.(xl+) 13 Unidade          14  Tipo bruto (hidden)
                --}}
                <table id="pendencias-table" class="w-full" style="width:100%">
                    <thead>
                        <tr class="bg-[#004D9D]/5 border-b border-[#004D9D]/10">
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider">Paciente</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap hidden md:table-cell">Leito</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap">Atend.</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap">Tipo</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider hidden lg:table-cell">Status</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider">Pendência</th>
                            <th class="px-1.5 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap">Data Prescrição</th>
                            <th class="px-1.5 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">Lib. médica</th>
                            <th class="px-1.5 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap">Liberado</th>
                            <th class="px-1.5 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap">Prev. exec.</th>
                            <th class="px-1.5 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">Coletado</th>
                            <th class="px-1.5 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap">Em aberto</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider hidden xl:table-cell whitespace-nowrap">Setor exec.</th>
                            <th class="px-3 py-2 text-left text-[10px] font-semibold text-[#004D9D] uppercase tracking-wider whitespace-nowrap col-unidade {{ count($selectedSectors) <= 1 ? 'col-unidade-hidden' : '' }}">Unidade</th>
                            <th class="hidden">Tipo bruto</th>
                            <th class="hidden">Vencido</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            @php
                                $statusExec = $row['status_execucao'] ?? '';
                                $isExam = in_array($row['tipo_evento'] ?? '', ['exame', 'proc_exame']);
                            @endphp
                            <tr class="hover:bg-[#004D9D]/[0.03] transition-colors">
                                <td class="px-3 py-1.5 text-[11px] text-gray-800 font-medium max-w-[130px] truncate" title="{{ $row['paciente'] }}">{{ $row['paciente'] }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-gray-600 font-mono whitespace-nowrap hidden md:table-cell">{{ $row['ugb'] }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-gray-700 font-mono font-medium whitespace-nowrap">{{ $row['atendimento'] }}</td>
                                <td class="px-3 py-1.5 text-[11px]">
                                    <div class="truncate text-gray-700 font-semibold">{{ $row['tipo_label'] ?? '-' }}</div>
                                    @if(!empty($row['classificacao']) && $row['classificacao'] !== '-')
                                        <div class="truncate text-[10px] text-gray-400 mt-0.5">{{ $row['classificacao'] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-[11px] text-gray-600 hidden lg:table-cell">
                                    @if(!empty($row['motivo_pendente']))
                                        <div class="flex items-start gap-1 text-[10px]">
                                            <x-healthicons-o-health-worker-form class="w-3 h-3 flex-shrink-0 mt-0.5 text-gray-400" />
                                            <span class="text-gray-500 font-medium mr-0.5">Tasy:</span>
                                            <span>{{ $row['motivo_pendente'] }}</span>
                                        </div>
                                    @endif
                                    @if($isExam && !empty($row['scola_status']))
                                        <div class="flex items-start gap-1 text-[10px] mt-0.5">
                                            <x-healthicons-o-lab-search class="w-3 h-3 flex-shrink-0 mt-0.5 text-gray-400" />
                                            <span class="text-gray-500 font-medium mr-0.5">SCOLA:</span>
                                            <span>{{ $row['scola_status'] }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-[11px]" style="max-width:200px">
                                    <div class="truncate text-gray-700 font-medium" title="{{ $row['item'] }}">{{ $row['item'] }}</div>
                                    @if(!empty($row['nr_prescricao']))
                                        <div class="text-[10px] text-gray-400 font-mono mt-0.5">Prescr. {{ $row['nr_prescricao'] }}</div>
                                    @endif
                                    @if($statusExec !== '')
                                        <span class="inline-block mt-0.5 px-1.5 py-px rounded border text-[10px] font-medium leading-tight bg-gray-50 text-gray-600 border-gray-200">{{ $statusExec }}</span>
                                    @endif
                                </td>
                                <td class="px-1.5 py-1.5 text-[10px] text-gray-500 whitespace-nowrap font-mono"
                                    data-order="{{ $row['data_solicitacao_sort'] ?? 0 }}">{{ $row['data_solicitacao'] ?? '-' }}</td>
                                <td class="px-1.5 py-1.5 text-[10px] text-gray-500 whitespace-nowrap font-mono hidden lg:table-cell"
                                    data-order="{{ $row['data_lib_medica_sort'] ?? 0 }}">{{ $row['data_lib_medica'] ?? '-' }}</td>
                                <td class="px-1.5 py-1.5 text-[10px] text-gray-500 whitespace-nowrap font-mono"
                                    data-order="{{ $row['data_lib_prescricao_sort'] ?? 0 }}">{{ $row['data_lib_prescricao'] ?? '-' }}</td>
                                <td class="px-1.5 py-1.5 text-[10px] text-gray-500 whitespace-nowrap font-mono"
                                    data-order="{{ $row['data_prev_execucao_sort'] ?? 0 }}">{{ $row['data_prev_execucao'] ?? '-' }}</td>
                                <td class="px-1.5 py-1.5 text-[10px] text-gray-500 whitespace-nowrap font-mono hidden lg:table-cell"
                                    data-order="{{ $row['data_coleta_sort'] ?? 0 }}">{{ $row['data_coleta'] ?? '-' }}</td>
                                <td class="px-1.5 py-1.5 text-[10px] whitespace-nowrap font-semibold font-mono text-[#0071B9]"
                                    data-order="{{ $row['tempo_pendente_sort'] ?? 0 }}">{{ $row['tempo_pendente'] ?? '-' }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-gray-500 max-w-[110px] truncate hidden xl:table-cell" title="{{ $row['setor_execucao'] ?? '-' }}">{{ $row['setor_execucao'] ?? '-' }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-gray-700 font-medium max-w-[120px] truncate col-unidade {{ count($selectedSectors) <= 1 ? 'col-unidade-hidden' : '' }}" title="{{ $row['setor_origem'] ?? '-' }}">{{ $row['setor_origem'] ?? '-' }}</td>
                                <td class="hidden">{{ $row['tipo_evento'] ?? '-' }}</td>
                                <td class="hidden">{{ ($row['is_overdue'] ?? false) ? '1' : '0' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @else
            @if(!empty($selectedSectors))
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <x-heroicon-o-check-circle class="w-12 h-12 text-emerald-300 mb-4" />
                    <h3 class="text-base font-semibold text-gray-700 mb-1">Nenhuma pendência encontrada</h3>
                    <p class="text-sm text-gray-400">Não há pendências em aberto para este setor.</p>
                </div>
            @endif
        @endif
    </div>
</div>

@push('scripts')
<script>
function showPendingLoader() {
    document.getElementById('pending-loading').style.display = 'flex';
}
function hidePendingLoader() {
    document.getElementById('pending-loading').style.display = 'none';
}

// Expand report edge-to-edge by cancelling the layout's horizontal padding
function fitReport() {
    var root = document.getElementById('report-root');
    if (!root) return;
    var el = root.parentElement;
    while (el && el.tagName !== 'MAIN') {
        var cs = getComputedStyle(el);
        var px = parseFloat(cs.paddingLeft) || 0;
        if (px > 0 && el === root.parentElement) {
            root.style.marginLeft  = '-' + px + 'px';
            root.style.marginRight = '-' + px + 'px';
            root.style.width       = 'calc(100% + ' + (px * 2) + 'px)';
        }
        el = el.parentElement;
    }
}

document.addEventListener('DOMContentLoaded', fitReport);
window.addEventListener('resize', fitReport);
</script>

@if($rows->count() > 0)
<script>
$(document).ready(function () {
    fitReport();

    var table = $('#pendencias-table').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100, { label: 'Todos', value: -1 }],
        autoWidth: false,
        order: [],
        columns: [
            { width: '110px' },  // 0  Paciente
            { width: '45px'  },  // 1  Leito
            { width: '70px'  },  // 2  Atend.
            { width: '80px'  },  // 3  Tipo
            { width: '180px' },  // 4  Status
            { width: '160px' },  // 5  Pendência
            { width: '72px'  },  // 6  Data Prescrição
            { width: '72px'  },  // 7  Lib. médica
            { width: '72px'  },  // 8  Liberado
            { width: '72px'  },  // 9  Prev. exec.
            { width: '72px'  },  // 10 Coletado
            { width: '70px'  },  // 11 Em aberto
            { width: '90px'  },  // 12 Setor exec.
            { width: '110px' },  // 13 Unidade
            { width: '0'     },  // 14 Tipo bruto (hidden)
            { width: '0'     },  // 15 Vencido (hidden)
        ],
        language: {
            url: '', decimal: ',', thousands: '.',
            emptyTable: 'Nenhuma pendência encontrada',
            info: '_START_–_END_ de _TOTAL_',
            infoEmpty: '0 registros', infoFiltered: '(de _MAX_)',
            lengthMenu: '_MENU_ por página',
            loadingRecords: 'Carregando...', processing: 'Processando...',
            search: 'Buscar:', zeroRecords: 'Nenhum registro encontrado',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },
        dom: '<"flex flex-wrap items-center justify-between gap-2 mb-2 px-3"lf>t<"flex flex-wrap items-center justify-between gap-2 mt-2 px-3"ip>',
        initComplete: function () {
            var api = this.api();
            $('#pendencias-table_filter input').addClass('px-2 py-1 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D]');
            $('#pendencias-table_length select').addClass('px-2 py-1 border border-gray-300 rounded-lg text-xs');
            $('#pendencias-table_filter label, #pendencias-table_length label').addClass('text-xs text-gray-500 flex items-center gap-1.5');
            $('#pendencias-table_info, #pendencias-table_paginate').addClass('text-xs text-gray-500');

            var tiposSet = {};
            api.column(14).data().each(function (val) {
                var v = String(val || '').trim();
                if (v) tiposSet[v] = true;
            });
            function titleCase(t) {
                return String(t || '').toLowerCase().split(' ').filter(Boolean)
                    .map(function (p) { return p.charAt(0).toUpperCase() + p.slice(1); }).join(' ');
            }
            Object.keys(tiposSet).sort().forEach(function (tipo) {
                $('#filter-tipo').append('<option value="' + tipo + '" class="text-gray-900">' + titleCase(tipo) + '</option>');
            });

            hidePendingLoader();
        }
    });

    window._pendenciasTable = table;

    // Fallback: garante que o loader some mesmo se initComplete não disparar
    setTimeout(hidePendingLoader, 8000);

    $('#filter-tipo').on('change', function () {
        table.column(14).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) : '', true, false).draw();
    });

    $('#chk-overdue').on('change', function () {
        table.column(15).search(this.checked ? '^1$' : '', true, false).draw();
    });
});
</script>
@endif
@endpush
@endsection
