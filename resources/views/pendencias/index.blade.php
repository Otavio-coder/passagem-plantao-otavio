@extends('layouts.app')

@section('content')
<div class="w-full px-3 my-2 text-gray-600">
    <div class="flex justify-between items-center mb-3">
        <div class="flex items-center gap-3">
            <span class="text-md md:text-2xl font-medium text-santacasa-100">
                <i class="fas fa-list-check mr-1"></i>
                Relatório de Pendências
            </span>
            @if($totalRows > 0)
                <span class="hidden sm:inline text-xs text-gray-500">
                    {{ $totalRows }} {{ $totalRows === 1 ? 'registro' : 'registros' }}
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="document.getElementById('modal-criterios').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 transition">
                <i class="fas fa-circle-question"></i>
                <span class="hidden sm:inline">Critérios de pendência</span>
            </button>
            <a href="{{ route('sbar.report') }}"
               class="inline-flex items-center px-3 py-2 text-santacasa-100 hover:text-santacasa-200 transition text-sm font-medium">
                <i class="fas fa-arrow-left mr-1"></i>
                <span class="hidden md:inline">Voltar</span>
            </a>
        </div>
    </div>

    {{-- Modal: Critérios de pendência --}}
    <div id="modal-criterios" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3" style="background:rgba(0,0,0,0.4)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[84vh] flex flex-col">
            <div class="flex items-center justify-between px-3 py-2.5 border-b border-gray-100">
                <h2 class="text-[13px] font-semibold text-gray-800">
                    <i class="fas fa-circle-question text-santacasa-100 mr-1.5"></i>
                    Critérios para definição de pendências
                </h2>
                <button type="button" onclick="document.getElementById('modal-criterios').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 transition text-lg leading-none">&times;</button>
            </div>
            <div class="overflow-y-auto px-3 py-2.5 text-[11px] text-gray-600 space-y-2.5">
                <p class="text-gray-500">
                    Um item aparece como <strong class="text-gray-700">pendente</strong> quando existe uma ação assistencial ainda não concluída.
                    Abaixo estão os critérios de forma simples, por tipo de pendência.
                </p>
                <div>
                    <h3 class="text-[10px] font-semibold text-gray-700 uppercase tracking-wide mb-1">Critérios por tipo de pendência</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5 text-[10px] leading-tight">
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-1.5">
                            <p class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Exames</p>
                            <ul class="space-y-0.5 text-gray-600">
                                <li><span class="font-semibold text-gray-700">Aguardando coleta:</span> exame pendente sem coleta vinculada.</li>
                                <li><span class="font-semibold text-gray-700">Aguardando laudo:</span> exame coletado sem resultado liberado.</li>
                                <li><span class="font-semibold text-gray-700">Material em análise:</span> exame em processamento.</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-1.5">
                            <p class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Procedimentos</p>
                            <ul class="space-y-0.5 text-gray-600">
                                <li><span class="font-semibold text-gray-700">Aguardando execução:</span> procedimento prescrito e ainda não realizado.</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-1.5">
                            <p class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Cirurgias</p>
                            <ul class="space-y-0.5 text-gray-600">
                                <li><span class="font-semibold text-gray-700">Eletiva pendente:</span> caráter eletivo com agenda pendente.</li>
                                <li><span class="font-semibold text-gray-700">Urgência pendente:</span> urgência/emergência ainda não executada.</li>
                                <li><span class="font-semibold text-gray-700">Eletiva confirmada:</span> confirmada e aguardando execução.</li>
                                <li><span class="font-semibold text-gray-700">Em preparo:</span> paciente em preparo pré-operatório.</li>
                                <li><span class="font-semibold text-gray-700">Paciente em sala:</span> paciente já no centro cirúrgico.</li>
                                <li><span class="font-semibold text-gray-700">Aguardando remarcação:</span> cirurgia desmarcada.</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-1.5">
                            <p class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Hemoterapia</p>
                            <ul class="space-y-0.5 text-gray-600">
                                <li><span class="font-semibold text-gray-700">Aguardando transfusão:</span> programada nas próximas 48h e não administrada.</li>
                                <li><span class="font-semibold text-gray-700">Urgente:</span> mesma situação com prioridade imediata.</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-1.5">
                            <p class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Quimioterapia</p>
                            <ul class="space-y-0.5 text-gray-600">
                                <li><span class="font-semibold text-gray-700">Sessão agendada:</span> sessão nos próximos 30 dias.</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-1.5">
                            <p class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Antimicrobianos</p>
                            <ul class="space-y-0.5 text-gray-600">
                                <li><span class="font-semibold text-gray-700">Dose não administrada:</span> horário aprazado hoje sem registro de administração.</li>
                                <li><span class="font-semibold text-gray-700">Dose reaprazada:</span> horário reagendado e ainda não administrado.</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-1.5">
                            <p class="text-[9px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Consultorias</p>
                            <ul class="space-y-0.5 text-gray-600">
                                <li><span class="font-semibold text-gray-700">Aguardando resposta:</span> solicitação sem parecer registrado.</li>
                            </ul>
                        </div>
                        <div class="rounded-lg border border-amber-100 bg-amber-50 p-1.5 md:col-span-2">
                            <p class="text-[9px] font-semibold text-amber-700 uppercase tracking-wide mb-1">Inconsistências no sistema</p>
                            <ul class="space-y-0.5 text-amber-700">
                                <li><span class="font-semibold text-amber-800">Realizado sem baixa:</span> item executado mas prescrição não foi baixada no sistema.</li>
                                <li><span class="font-semibold text-amber-800">Pedido mais recente em aberto:</span> existe uma solicitação mais nova do mesmo exame ainda pendente — indica que a anterior pode ter sido superada.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($errorMessage))
        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg mb-3">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Loading overlay --}}
    <div id="pending-loading"
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,20,70,0.55); backdrop-filter: blur(3px);">
        <div class="flex flex-col items-center gap-3">
            <div class="w-10 h-10 border-4 border-white/20 border-t-white rounded-full animate-spin"></div>
            <span class="text-white text-sm font-medium tracking-wide">Carregando pendências...</span>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow-sm p-3 mb-3">
        <div class="flex flex-wrap gap-2 items-end">
            <div class="flex flex-col gap-1 min-w-44">
                <label class="text-xs text-gray-500 font-medium">Hospital</label>
                <form method="GET" action="{{ route('pending.report') }}" id="pending-filter-form">
                <select name="hospital_id"
                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100"
                    onchange="showPendingLoader(); this.form.submit()">
                    @foreach($hospitals as $hospital)
                        <option value="{{ $hospital['hospital_id'] }}" {{ (int)$selectedHospital === (int)$hospital['hospital_id'] ? 'selected' : '' }}>
                            {{ $hospital['hospital_name'] }}
                        </option>
                    @endforeach
                </select>
                </form>
            </div>

            <div class="flex flex-col gap-1 min-w-52">
                <label class="text-xs text-gray-500 font-medium">Setor</label>
                <form method="GET" action="{{ route('pending.report') }}" id="pending-filter-form-sector">
                    @if($selectedHospital)
                        <input type="hidden" name="hospital_id" value="{{ $selectedHospital }}">
                    @endif
                    <select name="sector_id"
                        class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100"
                        onchange="showPendingLoader(); this.form.submit()">
                        @foreach($sectors as $sector)
                            <option value="{{ $sector['sector_code'] }}" {{ (int)$selectedSector === (int)$sector['sector_code'] ? 'selected' : '' }}>
                                {{ $sector['sector_name'] }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if($rows->count() > 0)
            <div class="hidden md:block self-stretch w-px bg-gray-200 mx-1"></div>

            <div class="flex flex-col gap-1 w-44">
                <label class="text-xs text-gray-500 font-medium">Tipo</label>
                <select id="filter-tipo"
                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100">
                    <option value="">Todos os tipos</option>
                </select>
            </div>

            <div class="flex flex-col gap-1 w-56">
                <label class="text-xs text-gray-500 font-medium">Período / Turno</label>
                <select id="filter-tempo"
                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100">
                    <option value="">Qualquer período</option>
                    <optgroup label="Por turno — Prev. exec. hoje">
                        <option value="turno:manha">Manhã · 07h–13h</option>
                        <option value="turno:tarde">Tarde · 13h–19h</option>
                        <option value="turno:noite">Noite · 19h–07h</option>
                    </optgroup>
                    <optgroup label="Por data — Prev. exec.">
                        <option value="data:hoje">Hoje (qualquer turno)</option>
                    </optgroup>
                    <optgroup label="Tempo pendente (Pend. há)">
                        <option value="pendente:0:24">Menos de 24h</option>
                        <option value="pendente:24:">Mais de 24h</option>
                        <option value="pendente:48:">Mais de 2 dias</option>
                        <option value="pendente:168:">Mais de 7 dias</option>
                        <option value="pendente:720:">Mais de 30 dias</option>
                    </optgroup>
                </select>
            </div>

            <button type="button" id="filter-limpar"
                class="self-end px-3 py-1.5 text-xs text-gray-500 border border-gray-300 rounded-lg hover:bg-gray-50 transition hidden">
                <i class="fas fa-times mr-1"></i>Limpar
            </button>
            @endif
        </div>
    </div>

    @if($rows->count() > 0)
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-3">
            <div class="overflow-x-auto">
                {{--
                    Colunas (índices DataTables):
                     0  Atend.
                     1  Paciente
                     2  Leito          (md+)
                     3  Tipo + Classif. (concatenados)
                     4  Pendência + badge de status abaixo
                     5  Data / Prazo   (data-date + data-order — prev.exec. empilhado com pend.há)
                     6  Motivo         (lg+)
                     7  Setor exec.    (xl+)
                     8  Tipo bruto     (hidden, usado nos filtros JS)
                --}}
                <table id="pendencias-table" class="w-full min-w-[560px]" style="width:100%">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap" style="width:65px">Atend.</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide" style="width:120px">Paciente</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap hidden md:table-cell" style="width:50px">Leito</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide" style="width:90px">Tipo</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide" style="width:180px">Pendência</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap" style="width:88px">Data / Prazo</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell" style="width:150px">Motivo</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide hidden xl:table-cell" style="width:100px">Setor exec.</th>
                            <th class="hidden">Tipo bruto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            @php
                                $statusRaw   = trim((string) ($row['status'] ?? ''));
                                $statusLower = mb_strtolower($statusRaw);
                                if (str_contains($statusLower, 'urgent') || str_contains($statusLower, 'emergê')) {
                                    $statusBadge = 'bg-red-50 text-red-700 border-red-200';
                                } elseif (str_contains($statusLower, 'análise') || str_contains($statusLower, 'analise') || str_contains($statusLower, 'coleta') || str_contains($statusLower, 'preparo') || str_contains($statusLower, 'sala')) {
                                    $statusBadge = 'bg-yellow-50 text-yellow-700 border-yellow-200';
                                } elseif (str_contains($statusLower, 'laudo') || str_contains($statusLower, 'aguardando')) {
                                    $statusBadge = 'bg-blue-50 text-blue-700 border-blue-200';
                                } elseif (str_contains($statusLower, 'execuç') || str_contains($statusLower, 'execuc') || str_contains($statusLower, 'andamento') || str_contains($statusLower, 'progresso')) {
                                    $statusBadge = 'bg-purple-50 text-purple-700 border-purple-200';
                                } else {
                                    $statusBadge = 'bg-gray-100 text-gray-600 border-gray-200';
                                }
                            @endphp
                            <tr class="hover:bg-gray-50 transition-colors">
                                {{-- 0: Atend. --}}
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 font-medium whitespace-nowrap">{{ $row['atendimento'] }}</td>
                                {{-- 1: Paciente --}}
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 max-w-[110px] truncate" title="{{ $row['paciente'] }}">{{ $row['paciente'] }}</td>
                                {{-- 2: Leito (md+) --}}
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 whitespace-nowrap hidden md:table-cell">{{ $row['ugb'] }}</td>
                                {{-- 3: Tipo + Classif. --}}
                                <td class="px-2 py-1.5 text-[11px] w-[90px] max-w-[90px]">
                                    <div class="truncate text-gray-700 font-medium">{{ $row['tipo_label'] ?? '-' }}</div>
                                    @if(!empty($row['classificacao']) && $row['classificacao'] !== '-')
                                        <div class="truncate text-[10px] text-gray-400">{{ $row['classificacao'] }}</div>
                                    @endif
                                </td>
                                {{-- 4: Pendência + status badge abaixo --}}
                                <td class="px-2 py-1.5 text-[11px]" style="max-width:180px">
                                    <div class="truncate text-gray-700" title="{{ $row['item'] }}">{{ $row['item'] }}</div>
                                    @if($statusRaw !== '')
                                        <span class="inline-block mt-0.5 px-1.5 py-px rounded border text-[10px] font-medium leading-tight {{ $statusBadge }}">{{ $statusRaw }}</span>
                                    @endif
                                </td>
                                {{-- 5: Data / Prazo (data-date + data-order para filtros JS) --}}
                                <td class="px-2 py-1.5 text-[11px] whitespace-nowrap"
                                    data-date="{{ $row['data_prev_execucao'] }}"
                                    data-order="{{ $row['tempo_pendente_sort'] ?? 0 }}">
                                    <div class="text-gray-600">{{ $row['data_prev_execucao'] }}</div>
                                    @if(!empty($row['tempo_pendente']))
                                        <div class="text-[10px] text-gray-400">{{ $row['tempo_pendente'] }}</div>
                                    @endif
                                </td>
                                {{-- 6: Motivo (lg+) --}}
                                <td class="px-2 py-1.5 text-[11px] text-gray-500 max-w-[160px] truncate hidden lg:table-cell" title="{{ $row['motivo_pendente'] ?? '-' }}">{{ $row['motivo_pendente'] ?? '-' }}</td>
                                {{-- 7: Setor exec. (xl+) --}}
                                <td class="px-2 py-1.5 text-[11px] text-gray-500 max-w-[100px] truncate hidden xl:table-cell" title="{{ $row['setor_execucao'] ?? '-' }}">{{ $row['setor_execucao'] ?? '-' }}</td>
                                {{-- 8: Tipo bruto (hidden — usado nos filtros JS) --}}
                                <td class="hidden">{{ $row['tipo_evento'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        @if($selectedSector > 0)
            <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                <i class="fas fa-inbox text-gray-300 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma pendência encontrada</h3>
                <p class="text-sm text-gray-500">Não há pendências em aberto para este setor.</p>
            </div>
        @endif
    @endif
</div>

@push('scripts')
<script>
function showPendingLoader() {
    document.getElementById('pending-loading').style.display = 'flex';
}
function hidePendingLoader() {
    document.getElementById('pending-loading').style.display = 'none';
}
@if($totalRows === 0)
document.addEventListener('DOMContentLoaded', hidePendingLoader);
@endif
</script>
<script>
$(document).ready(function () {
    var activePendingTypeTab = 'all';

    var table = $('#pendencias-table').DataTable({
        pageLength: 15,
        lengthMenu: [15, 50, 100, 200],
        autoWidth: false,
        order: [],
        language: {
            url: '',
            decimal: ',',
            thousands: '.',
            emptyTable: 'Nenhuma pendência encontrada',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros)',
            lengthMenu: 'Mostrar _MENU_ por página',
            loadingRecords: 'Carregando...',
            processing: 'Processando...',
            search: 'Buscar:',
            zeroRecords: 'Nenhum registro encontrado',
            paginate: {
                first: 'Primeiro',
                last: 'Último',
                next: 'Próximo',
                previous: 'Anterior'
            }
        },
        dom: '<"flex flex-wrap items-center justify-between gap-2 mb-3"lf>rt<"flex flex-wrap items-center justify-between gap-2 mt-3"ip>',
        initComplete: function () {
            var api = this.api();

            function titleCase(text) {
                return String(text || '')
                    .toLowerCase()
                    .split(' ')
                    .filter(function (part) { return part !== ''; })
                    .map(function (part) {
                        return part.charAt(0).toUpperCase() + part.slice(1);
                    })
                    .join(' ');
            }

            $('#pendencias-table_filter input').addClass('px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100');
            $('#pendencias-table_length select').addClass('px-2 py-1.5 border border-gray-300 rounded-lg text-xs');

            // Popula o filtro de Tipo a partir da coluna 8 (tipo bruto, hidden)
            var tiposSet = {};
            api.column(8).data().each(function (val) {
                var label = String(val || '').trim();
                if (label) { tiposSet[label] = true; }
            });
            Object.keys(tiposSet).sort().forEach(function (tipo) {
                $('#filter-tipo').append('<option value="' + tipo + '">' + titleCase(tipo) + '</option>');
            });

            hidePendingLoader();
        }
    });

    // Parseia "dd/mm/yyyy HH:mm" → Date (ou null)
    function parseRowDate(val) {
        if (!val || val === '-') { return null; }
        var m = val.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?/);
        if (!m) { return null; }
        return new Date(+m[3], +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0));
    }

    function isDateToday(d) {
        var now = new Date();
        return d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
    }

    // col 5 = Data / Prazo (data-date + data-order)
    function getPrevExecDate(row) {
        var cell = row && row.anCells && row.anCells[5] ? row.anCells[5] : null;
        if (!cell) { return null; }
        return parseRowDate(cell.getAttribute('data-date') || '');
    }

    function getPendingSeconds(row) {
        var cell = row && row.anCells && row.anCells[5] ? row.anCells[5] : null;
        if (!cell) { return null; }
        var v = parseFloat(cell.getAttribute('data-order') || '');
        return isNaN(v) || v < 0 ? null : v;
    }

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'pendencias-table') { return true; }

        var filter = $('#filter-tempo').val();
        var row = settings.aoData[dataIndex];

        if (filter) {
            var passed = false;

            if (filter.indexOf('turno:') === 0 || filter === 'data:hoje') {
                var d = getPrevExecDate(row);
                if (!d) { return false; }

                if (filter === 'data:hoje') {
                    passed = isDateToday(d);
                } else {
                    var h = d.getHours();
                    if (filter === 'turno:manha')  { passed = isDateToday(d) && h >= 7  && h < 13; }
                    else if (filter === 'turno:tarde') { passed = isDateToday(d) && h >= 13 && h < 19; }
                    else if (filter === 'turno:noite') { passed = isDateToday(d) && (h >= 19 || h < 7); }
                }
            } else if (filter.indexOf('pendente:') === 0) {
                var secs = getPendingSeconds(row);
                if (secs === null) { return false; }
                var parts = filter.split(':');
                var minH = parseFloat(parts[1]);
                var maxH = (parts[2] !== '' && parts[2] !== undefined) ? parseFloat(parts[2]) : Infinity;
                passed = (secs / 3600) >= minH && (secs / 3600) < maxH;
            }

            if (!passed) { return false; }
        }

        if (activePendingTypeTab === 'all') { return true; }

        // col 8 = tipo bruto
        var tipo = String(data[8] || '').toLowerCase();
        if (activePendingTypeTab === 'exame')         { return tipo.indexOf('exame') !== -1 || tipo.indexOf('laboratório') !== -1 || tipo.indexOf('laboratorio') !== -1; }
        if (activePendingTypeTab === 'procedimento')  { return tipo === 'procedimento'; }
        if (activePendingTypeTab === 'cirurgia')      { return tipo === 'cirurgia'; }

        return true;
    });

    function updateLimparBtn() {
        var hasFilter = $('#filter-tipo').val() !== '' || $('#filter-tempo').val() !== '';
        $('#filter-limpar').toggleClass('hidden', !hasFilter);
    }

    // Filtro Tipo → coluna 8 (tipo bruto)
    $('#filter-tipo').on('change', function () {
        table.column(8).search(
            this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) : '',
            true, false
        ).draw();
        updateLimparBtn();
    });

    $('#filter-tempo').on('change', function () {
        table.draw();
        updateLimparBtn();
    });

    $('#filter-limpar').on('click', function () {
        $('#filter-tipo').val('');
        $('#filter-tempo').val('');
        table.column(8).search('', false, false).draw();
        updateLimparBtn();
    });

    $('#pending-type-tabs').on('click', '.pending-tab', function () {
        activePendingTypeTab = $(this).data('type');
        $('.pending-tab')
            .removeClass('border-santacasa-100 bg-santacasa-100 text-white')
            .addClass('border-gray-300 text-gray-600');
        $(this)
            .removeClass('border-gray-300 text-gray-600')
            .addClass('border-santacasa-100 bg-santacasa-100 text-white');
        table.draw();
    });
});
</script>
@endpush
@endsection
