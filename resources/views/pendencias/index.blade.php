@extends('layouts.app')

@push('head')
<style>
    html, body { height: auto !important; min-height: 100vh; overflow-y: auto !important; }
    main { overflow-y: visible !important; }
    .col-unidade-hidden { display: none !important; }
    /* Badge de status */
    .badge-status {
        display: inline-flex; align-items: center;
        padding: 1px 6px; border-radius: 9999px;
        font-size: 10px; font-weight: 600; white-space: nowrap;
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
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 6px;
        padding: 10px 12px 4px;
    }
    .kpi-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-bottom: 3px solid #e5e7eb;
        border-radius: 6px;
        padding: 7px 10px 6px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s;
        user-select: none;
    }
    .kpi-card:hover { border-color: #9ca3af; border-bottom-color: #9ca3af; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
    .kpi-card.active { border-color: #cbd5e1; border-bottom-color: #004D9D; box-shadow: 0 0 0 1px #004D9D22; }
    .kpi-card .kpi-count {
        font-size: 18px; font-weight: 700;
        color: #111827; line-height: 1;
    }
    .kpi-card .kpi-label {
        font-size: 9px; font-weight: 500;
        color: #9ca3af; line-height: 1.3;
    }
    .kpi-card.kpi-total  { border-bottom-color: #004D9D; }
    .kpi-card.kpi-overdue { border-bottom-color: #dc2626; }
    .kpi-card.kpi-overdue .kpi-count { color: #dc2626; }
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
                    @if($totalRows > 0)
                        &mdash; <span class="font-semibold text-[#004D9D]">{{ $totalRows }}</span> pendências
                    @endif
                </p>
            </div>
        </div>
        {{-- Botão exportar --}}
        @if($totalRows > 0)
        <a id="btn-export"
           href="{{ route('pending.report.export', array_merge(['hospital_id' => $selectedHospital], array_map(fn($id) => $id, $selectedSectors) ? ['sector_ids' => $selectedSectors] : [])) }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition shadow-sm flex-shrink-0"
           title="Exportar CSV compatível com Excel">
            <i class="fas fa-file-excel"></i>
            <span>Exportar Excel</span>
        </a>
        @endif
    </div>

    {{-- Filter toolbar --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">

            {{-- Hospital --}}
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
                        class="flex items-center gap-1.5 bg-white border border-gray-300 text-gray-700 text-xs rounded-lg px-2.5 py-1.5 hover:bg-gray-50 transition min-w-[140px] max-w-[220px]">
                    <span class="truncate flex-1 text-left">{{ $sectorBtnLabel }}</span>
                    <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="sector-dropdown-panel" style="display:none"
                     class="absolute left-0 top-full mt-1 z-50 bg-white rounded-lg shadow-xl border border-gray-200 min-w-[200px] max-w-[280px]">
                    <form method="GET" action="{{ route('pending.report') }}" id="form-sectors">
                        @if($selectedHospital)
                            <input type="hidden" name="hospital_id" value="{{ $selectedHospital }}">
                        @endif
                        <div class="p-1 max-h-56 overflow-y-auto">
                            @foreach($sectorsForFilter as $s)
                                <label class="flex items-center gap-2 px-2.5 py-1.5 rounded-md hover:bg-gray-50 cursor-pointer text-xs text-gray-700">
                                    <input type="checkbox" name="sector_ids[]" value="{{ $s['code'] }}"
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
                            <button type="submit" onclick="showPendingLoader()"
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
                {{-- Filtro: Tipo --}}
                <select id="filter-tipo"
                    class="bg-white border border-gray-300 text-gray-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 min-w-[130px]">
                    <option value="">Todos os tipos</option>
                    @foreach($tipoLabels as $tipoKey => $tipoLabel)
                        <option value="{{ $tipoKey }}">{{ $tipoLabel }}</option>
                    @endforeach
                </select>

                {{-- Filtro: Status --}}
                <select id="filter-status"
                    class="bg-white border border-gray-300 text-gray-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 min-w-[180px]">
                    <option value="">Todos os status</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>

                {{-- Filtro: Classificação --}}
                @if(count($classificacoes) > 0)
                <select id="filter-classif"
                    class="bg-white border border-gray-300 text-gray-700 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 min-w-[150px]">
                    <option value="">Todas as classificações</option>
                    @foreach($classificacoes as $cl)
                        <option value="{{ $cl }}">{{ $cl }}</option>
                    @endforeach
                </select>
                @endif

                {{-- Só vencidos --}}
                <label class="inline-flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer whitespace-nowrap select-none">
                    <input type="checkbox" id="chk-overdue" class="rounded border-gray-300 text-amber-500 focus:ring-amber-400 cursor-pointer">
                    Só vencidos
                </label>

                {{-- Limpar filtros --}}
                <button type="button" id="btn-clear-filters"
                    class="text-[10px] text-gray-400 hover:text-gray-600 hover:underline whitespace-nowrap">
                    Limpar filtros
                </button>
            @endif

            {{-- Atualizar --}}
            @if(!empty($selectedSectors))
            <form method="POST" action="{{ route('pending.report.refresh') }}" id="form-refresh" class="ml-auto">
                @csrf
                <input type="hidden" name="hospital_id" value="{{ $selectedHospital }}">
                @foreach($selectedSectors as $sid)
                    <input type="hidden" name="sector_ids[]" value="{{ $sid }}">
                @endforeach
                <button type="submit" id="btn-refresh"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-gray-500 hover:text-[#004D9D] bg-gray-100 hover:bg-[#004D9D]/10 rounded-md transition"
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

    {{-- Loading overlay --}}
    <div id="pending-loading"
         class="fixed inset-0 z-50 items-center justify-center"
         style="background:rgba(0,20,70,0.55);backdrop-filter:blur(3px);display:none">
        <div class="flex flex-col items-center gap-3 mt-32">
            <div class="w-10 h-10 border-4 border-white/20 border-t-white rounded-full animate-spin"></div>
            <span class="text-white text-sm font-medium tracking-wide">Carregando pendências...</span>
        </div>
    </div>
    @if($rows->count() > 0)
    <script>document.getElementById('pending-loading').style.display = 'flex';</script>
    @endif

    {{-- Body --}}
    <div class="bg-white rounded-b-xl">

        @if(!empty($errorMessage))
            <div class="mx-3 mt-3 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0" />
                {{ $errorMessage }}
            </div>
        @endif

        @if($rows->count() > 0)
            {{--
                Colunas DataTables (índices):
                 0  Paciente        1  Leito (md+)     2  Atend.
                 3  Tipo            4  Status          5  Pendência
                 6  Data Prescrição 7  Lib. médica (lg+) 8  Liberado
                 9  Prev. exec.    10  Coletado (lg+)  11 Em aberto
                 12 Setor exec.(xl+) 13 Unidade
                 14 tipo_evento (hidden - filtro tipo)
                 15 vencido (hidden - filtro overdue)
                 16 motivo_categoria (hidden - filtro status)
                 17 classificacao (hidden - filtro classificação)
            --}}
            <div id="kpi-bar"></div>

            <div class="pb-4 pt-2" id="dt-wrapper">
                <table id="pendencias-table" class="w-full" style="width:100%">
                    <thead>
                        <tr class="bg-[#004D9D]/5 border-b border-[#004D9D]/10">
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Paciente</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap hidden md:table-cell">Leito</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap">Atend.</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap">Tipo</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 hidden lg:table-cell">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Pendência</th>
                            <th class="px-1.5 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap">Data Prescrição</th>
                            <th class="px-1.5 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap hidden lg:table-cell">Lib. médica</th>
                            <th class="px-1.5 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap">Liberado</th>
                            <th class="px-1.5 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap">Prev. exec.</th>
                            <th class="px-1.5 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap hidden lg:table-cell">Coletado</th>
                            <th class="px-1.5 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap">Em aberto</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 hidden xl:table-cell whitespace-nowrap">Setor exec.</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 whitespace-nowrap col-unidade {{ count($selectedSectors) <= 1 ? 'col-unidade-hidden' : '' }}">Unidade</th>
                            <th class="hidden">tipo_raw</th>
                            <th class="hidden">vencido</th>
                            <th class="hidden">motivo_cat</th>
                            <th class="hidden">classif_raw</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            @php
                                $statusExec = $row['status_execucao'] ?? '';
                                $isExam = in_array($row['tipo_evento'] ?? '', ['exame', 'proc_exame']);
                                $motCat = $row['motivo_categoria'] ?? '';
                                $badgeCls = match($motCat) {
                                    'Aguardando coleta', 'Urgente — aguardando coleta' => $motCat === 'Urgente — aguardando coleta' ? 'badge-urgente' : 'badge-aguard-coleta',
                                    'Coletado — aguardando resultado' => 'badge-coletado',
                                    'Resultado disponível' => 'badge-resultado',
                                    'Laudo disponível' => 'badge-laudo',
                                    'Laudo liberado (SCOLA)' => 'badge-laudo-scola',
                                    'Nova coleta necessária' => 'badge-nova-coleta',
                                    'Em execução', 'Em preparo' => 'badge-execucao',
                                    'Antimicrobiano pendente' => 'badge-antimicrobiano',
                                    default => 'badge-outros',
                                };
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
                                    {{-- Badge de categoria de status --}}
                                    @if($motCat)
                                        <span class="badge-status {{ $badgeCls }} mb-1">{{ $motCat }}</span>
                                    @endif
                                    {{-- Motivo detalhado TASY --}}
                                    @if(!empty($row['motivo_pendente']))
                                        <div class="flex items-start gap-1 text-[10px] mt-1">
                                            <x-healthicons-o-health-worker-form class="w-3 h-3 flex-shrink-0 mt-0.5 text-gray-400" />
                                            <span class="text-gray-500 font-medium mr-0.5">Tasy:</span>
                                            <span class="text-gray-600">{{ $row['motivo_pendente'] }}</span>
                                        </div>
                                    @endif
                                    {{-- SCOLA status --}}
                                    @if($isExam && !empty($row['scola_status']))
                                        <div class="flex items-start gap-1 text-[10px] mt-0.5">
                                            <x-healthicons-o-lab-search class="w-3 h-3 flex-shrink-0 mt-0.5 text-gray-400" />
                                            <span class="text-gray-500 font-medium mr-0.5">SCOLA:</span>
                                            <span class="text-gray-600">{{ $row['scola_status'] }}</span>
                                        </div>
                                    @endif
                                    {{-- Resultado bacteriológico --}}
                                    @if(!empty($row['scola_resultado']))
                                        <div class="flex items-start gap-1 text-[10px] mt-0.5">
                                            <i class="fas fa-flask w-3 h-3 flex-shrink-0 mt-0.5 text-emerald-500" style="font-size:10px;"></i>
                                            <span class="text-emerald-700 font-semibold">{{ $row['scola_resultado'] }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-[11px]" style="max-width:200px">
                                    <div class="truncate text-gray-700 font-medium" title="{{ $row['item'] }}">{{ $row['item'] }}</div>
                                    @if(!empty($row['nr_prescricao']))
                                        <div class="text-[10px] text-gray-400 font-mono mt-0.5">#{{ $row['nr_prescricao'] }}</div>
                                    @endif
                                    @if(!empty($row['nm_prescritor']))
                                        <div class="text-[10px] text-gray-400 mt-0.5 truncate" title="{{ $row['nm_prescritor'] }}">{{ $row['nm_prescritor'] }}</div>
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
                                <td class="px-1.5 py-1.5 text-[10px] whitespace-nowrap font-semibold font-mono {{ ($row['is_overdue'] ?? false) ? 'text-amber-600' : 'text-[#0071B9]' }}"
                                    data-order="{{ $row['tempo_pendente_sort'] ?? 0 }}">{{ $row['tempo_pendente'] ?? '-' }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-gray-500 max-w-[110px] truncate hidden xl:table-cell" title="{{ $row['setor_execucao'] ?? '-' }}">{{ $row['setor_execucao'] ?? '-' }}</td>
                                <td class="px-3 py-1.5 text-[11px] text-gray-700 font-medium max-w-[120px] truncate col-unidade {{ count($selectedSectors) <= 1 ? 'col-unidade-hidden' : '' }}" title="{{ $row['setor_origem'] ?? '-' }}">{{ $row['setor_origem'] ?? '-' }}</td>
                                <td class="hidden">{{ $row['tipo_evento'] ?? '' }}</td>
                                <td class="hidden">{{ ($row['is_overdue'] ?? false) ? '1' : '0' }}</td>
                                <td class="hidden">{{ $row['motivo_categoria'] ?? '' }}</td>
                                <td class="hidden">{{ $row['classificacao'] ?? '' }}</td>
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
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, { label: 'Todos', value: -1 }],
        autoWidth: true,
        scrollX: false,
        order: [],
        columns: [
            { },  // 0  Paciente
            { },  // 1  Leito
            { },  // 2  Atend.
            { },  // 3  Tipo
            { },  // 4  Status
            { },  // 5  Pendência
            { },  // 6  Data Prescrição
            { },  // 7  Lib. médica
            { },  // 8  Liberado
            { },  // 9  Prev. exec.
            { },  // 10 Coletado
            { },  // 11 Em aberto
            { },  // 12 Setor exec.
            { },  // 13 Unidade
            { width: '0', orderable: false, searchable: true  },  // 14 tipo_raw
            { width: '0', orderable: false, searchable: true  },  // 15 vencido
            { width: '0', orderable: false, searchable: true  },  // 16 motivo_cat
            { width: '0', orderable: false, searchable: true  },  // 17 classif_raw
        ],
        language: {
            url: '', decimal: ',', thousands: '.',
            emptyTable: 'Nenhuma pendência encontrada',
            info: '_START_–_END_ de _TOTAL_',
            infoEmpty: '0 registros', infoFiltered: '(filtrado de _MAX_)',
            lengthMenu: '_MENU_ por página',
            loadingRecords: 'Carregando...', processing: 'Processando...',
            search: 'Buscar:', zeroRecords: 'Nenhum resultado para este filtro',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },
        dom: '<"flex flex-wrap items-center justify-between gap-2 mb-2 px-3"lf>t<"flex flex-wrap items-center justify-between gap-2 mt-2 px-3"ip>',
        initComplete: function () {
            $('#pendencias-table_filter input').addClass('px-2 py-1 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D]');
            $('#pendencias-table_length select').addClass('px-2 py-1 border border-gray-300 rounded-lg text-xs');
            $('#pendencias-table_filter label, #pendencias-table_length label').addClass('text-xs text-gray-500 flex items-center gap-1.5');
            $('#pendencias-table_info, #pendencias-table_paginate').addClass('text-xs text-gray-500');
            hidePendingLoader();
        }
    });

    window._pendenciasTable = table;

    // Fallback loader hide
    setTimeout(hidePendingLoader, 8000);

    // Mapa tipo_evento → label para reconstruir opções do dropdown de tipo
    var tipoLabelMap = @json($tipoLabels);

    // Reconstrói as opções de um <select> com base nos valores visíveis na tabela filtrada.
    // Preserva a seleção atual se o valor ainda existir nas linhas visíveis.
    function rebuildSelect(selector, colIdx, allLabel, labelMap) {
        var $sel = $(selector);
        var currentVal = $sel.val();

        var vals = table.column(colIdx, { filter: 'applied' })
            .data()
            .unique()
            .sort()
            .toArray()
            .filter(function(v) { return v !== null && v !== ''; });

        var html = '<option value="">' + allLabel + '</option>';
        vals.forEach(function(v) {
            var label = (labelMap && labelMap[v]) ? labelMap[v] : v;
            html += '<option value="' + $('<div>').text(v).html() + '"' +
                    (v === currentVal ? ' selected' : '') + '>' +
                    $('<div>').text(label).html() + '</option>';
        });
        $sel.html(html);
    }

    function rebuildCascadeFilters() {
        rebuildSelect('#filter-tipo',    14, 'Todos os tipos',           tipoLabelMap);
        rebuildSelect('#filter-status',  16, 'Todos os status',          null);
        rebuildSelect('#filter-classif', 17, 'Todas as classificações',  null);
    }

    // Mapa categoria → classe CSS lido do DOM (coluna Status já renderiza os badges com as classes corretas)
    var kpiCssMap = {};
    $('#pendencias-table .badge-status').each(function() {
        var text = $(this).text().trim();
        var badgeCls = $(this).attr('class').split(/\s+/).find(function(c) {
            return c.startsWith('badge-') && c !== 'badge-status' && c !== 'mb-1';
        });
        if (text && badgeCls && !kpiCssMap[text]) kpiCssMap[text] = badgeCls;
    });

    var activeKpiFilter = '';

    function rebuildKpis() {
        var rows     = table.rows({ filter: 'applied' });
        var total    = rows.count();
        var overdue  = 0;
        var catMap   = {};

        rows.every(function() {
            var d = this.data();
            var cat = d[16] || '';
            if (cat) catMap[cat] = (catMap[cat] || 0) + 1;
            if (d[15] === '1') overdue++;
        });

        var html = '';

        // Card total
        html += '<div class="kpi-card kpi-total" data-kpi="">' +
                '<span class="kpi-count">' + total + '</span>' +
                '<span class="kpi-label">Total visível</span></div>';

        // Card vencidos (só se houver)
        if (overdue > 0) {
            var overdueActive = $('#chk-overdue').prop('checked') ? ' active' : '';
            html += '<div class="kpi-card kpi-overdue' + overdueActive + '" data-kpi-overdue="1">' +
                    '<span class="kpi-count">' + overdue + '</span>' +
                    '<span class="kpi-label">Vencidos</span></div>';
        }

        // Cards por categoria, ordenados por contagem desc
        var sorted = Object.entries(catMap).sort(function(a, b) { return b[1] - a[1]; });
        sorted.forEach(function(pair) {
            var cat = pair[0], count = pair[1];
            var isActive = (activeKpiFilter === cat) ? ' active' : '';
            html += '<div class="kpi-card' + isActive + '" data-kpi="' + $('<div>').text(cat).html() + '">' +
                    '<span class="kpi-count">' + count + '</span>' +
                    '<span class="kpi-label">' + $('<div>').text(cat).html() + '</span></div>';
        });

        $('#kpi-bar').html(html);

        // Clique → aplica/remove filtro de status
        $('#kpi-bar .kpi-card[data-kpi]').on('click', function() {
            var val = $(this).data('kpi');
            if (activeKpiFilter === val) {
                activeKpiFilter = '';
                $('#filter-status').val('').trigger('change');
            } else {
                activeKpiFilter = val;
                $('#filter-status').val(val).trigger('change');
            }
        });

        // Clique em vencidos
        $('#kpi-bar .kpi-card[data-kpi-overdue]').on('click', function() {
            var chk = $('#chk-overdue');
            chk.prop('checked', !chk.prop('checked')).trigger('change');
        });
    }

    // Sincroniza activeKpiFilter quando status é alterado por dropdown (não por clique no KPI)
    $('#filter-status').on('change', function() {
        activeKpiFilter = $(this).val();
    });

    // Recalcula dropdowns após cada draw (filtro aplicado, página trocada, etc.)
    table.on('draw', function() { rebuildCascadeFilters(); rebuildKpis(); });
    // Primeira execução após inicialização
    rebuildCascadeFilters();
    rebuildKpis();

    // Filtro: Tipo (col 14)
    $('#filter-tipo').on('change', function () {
        table.column(14).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });

    // Filtro: Status / categoria (col 16)
    $('#filter-status').on('change', function () {
        table.column(16).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });

    // Filtro: Classificação (col 17)
    $('#filter-classif').on('change', function () {
        table.column(17).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });

    // Filtro: Só vencidos (col 15)
    $('#chk-overdue').on('change', function () {
        table.column(15).search(this.checked ? '^1$' : '', true, false).draw();
    });

    // Limpar todos os filtros
    $('#btn-clear-filters').on('click', function () {
        $('#filter-tipo').val('');
        $('#filter-status').val('');
        $('#filter-classif').val('');
        $('#chk-overdue').prop('checked', false);
        table.columns([14, 15, 16, 17]).search('').draw();
        table.search('').draw();
    });

    // Atualiza URL do botão exportar com filtros de setor ativos
    @if(!empty($selectedSectors))
    (function() {
        var base = '{{ route('pending.report.export') }}?hospital_id={{ $selectedHospital }}';
        @foreach($selectedSectors as $sid)
        base += '&sector_ids[]={{ $sid }}';
        @endforeach
        var exportBtn = document.getElementById('btn-export');
        if (exportBtn) exportBtn.href = base;
    })();
    @endif
});
</script>
@endif
@endpush
@endsection
