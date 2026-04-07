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
                                <li><span class="font-semibold text-amber-800">Realizado sem baixa:</span> item realizado e ainda em aberto.</li>
                                <li><span class="font-semibold text-amber-800">Realizado em solicitação mais recente:</span> exame feito em solicitação nova e anterior ficou aberta.</li>
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

    {{-- Loading overlay — visível por padrão, escondido após renderização --}}
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
                <table id="pendencias-table" class="w-full min-w-[900px]" style="width:100%">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap w-[68px]">Atend.</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide w-[130px]">Paciente</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap w-[52px] hidden lg:table-cell">Leito</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide w-[110px] hidden md:table-cell">Setor</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap w-[90px]">Tipo</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide w-[110px] hidden xl:table-cell">Setor exec.</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap hidden xl:table-cell">Classif.</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Pendência</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap w-[88px] hidden lg:table-cell">Solicitação</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap w-[80px]">Prev. exec.</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap w-[80px]">Pend. há</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide w-[200px]">Motivo</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide w-[100px] hidden xl:table-cell">Desc.</th>
                            <th class="hidden">Tipo bruto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 font-medium whitespace-nowrap">{{ $row['atendimento'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 max-w-[130px] truncate" title="{{ $row['paciente'] }}">{{ $row['paciente'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 whitespace-nowrap hidden lg:table-cell">{{ $row['ugb'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-500 max-w-[110px] truncate hidden md:table-cell" title="{{ $row['uga'] ?? '-' }}">{{ $row['uga'] ?? '-' }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-500 whitespace-nowrap">{{ $row['tipo_label'] ?? '-' }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 max-w-[110px] truncate hidden xl:table-cell" title="{{ $row['setor_execucao'] ?? '-' }}">{{ $row['setor_execucao'] ?? '-' }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-500 whitespace-nowrap hidden xl:table-cell">{{ $row['classificacao'] ?? '-' }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 max-w-[200px] truncate" title="{{ $row['item'] }}">{{ $row['item'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 whitespace-nowrap hidden lg:table-cell">{{ $row['data_solicitacao'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 whitespace-nowrap"
                                    data-date="{{ $row['data_prev_execucao'] }}">{{ $row['data_prev_execucao'] }}</td>
                                <td data-order="{{ $row['tempo_pendente_sort'] ?? 0 }}" class="px-2 py-1.5 text-[11px] text-gray-700 whitespace-nowrap">{{ $row['tempo_pendente'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-500 max-w-[200px] truncate" title="{{ $row['motivo_pendente'] ?? '-' }}">{{ $row['motivo_pendente'] ?? '-' }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 max-w-[100px] truncate hidden xl:table-cell" title="{{ $row['laudo'] ?? '-' }}">{{ $row['laudo'] ?? '-' }}</td>
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
// Sem tabela para inicializar — esconde o loader assim que o DOM estiver pronto
document.addEventListener('DOMContentLoaded', hidePendingLoader);
@endif
</script>
<script>
$(document).ready(function () {
    var activePendingTypeTab = 'all';

    var table = $('#pendencias-table').DataTable({
        pageLength: 15,
        lengthMenu: [15, 50, 100, 200],
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

            // Popula o filtro de Tipo com o tipo bruto da consulta
            var tiposSet = {};
            api.column(13).data().each(function (val) {
                var label = String(val || '').trim();
                if (label) tiposSet[label] = true;
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

    // Lê o data-date da célula "Prev. exec." (col 9) via DOM — sempre disponível
    // mesmo quando a célula exibe o badge "Hoje" em vez do texto da data.
    function getPrevExecDate(row) {
        var cell = row && row.anCells && row.anCells[9] ? row.anCells[9] : null;
        if (!cell) { return null; }
        return parseRowDate(cell.getAttribute('data-date') || '');
    }

    // Lê os segundos pendentes do data-order da coluna "Pend. há" (col 10)
    function getPendingSeconds(row) {
        var cell = row && row.anCells && row.anCells[10] ? row.anCells[10] : null;
        if (!cell) { return null; }
        var v = parseFloat(cell.getAttribute('data-order') || '');
        return isNaN(v) || v < 0 ? null : v;
    }

    // Filtro principal — lida com turno, data e duração pendente
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'pendencias-table') { return true; }

        var filter = $('#filter-tempo').val();
        var row = settings.aoData[dataIndex];

        if (filter) {
            var passed = false;

            if (filter.indexOf('turno:') === 0 || filter === 'data:hoje') {
                // Filtra pelo datetime agendado (Prev. exec.)
                var d = getPrevExecDate(row);
                if (!d) { return false; }

                if (filter === 'data:hoje') {
                    passed = isDateToday(d);
                } else {
                    var h = d.getHours();
                    if (filter === 'turno:manha') { passed = isDateToday(d) && h >= 7 && h < 13; }
                    else if (filter === 'turno:tarde') { passed = isDateToday(d) && h >= 13 && h < 19; }
                    else if (filter === 'turno:noite') { passed = isDateToday(d) && (h >= 19 || h < 7); }
                }
            } else if (filter.indexOf('pendente:') === 0) {
                // Filtra pelo tempo decorrido desde a criação (Pend. há)
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

        var tipo = String(data[13] || '').toLowerCase();
        if (activePendingTypeTab === 'exame') {
            return tipo.indexOf('exame') !== -1 || tipo.indexOf('laboratório') !== -1 || tipo.indexOf('laboratorio') !== -1;
        }
        if (activePendingTypeTab === 'procedimento') { return tipo === 'procedimento'; }
        if (activePendingTypeTab === 'cirurgia') { return tipo === 'cirurgia'; }

        return true;
    });

    function updateLimparBtn() {
        var hasFilter = $('#filter-tipo').val() !== '' || $('#filter-tempo').val() !== '';
        $('#filter-limpar').toggleClass('hidden', !hasFilter);
    }

    // Filtro por Tipo (prefixo da coluna Pendência)
    $('#filter-tipo').on('change', function () {
        table.column(13).search(
            this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) : '',
            true, false
        ).draw();
        updateLimparBtn();
    });

    // Filtro por tempo pendente
    $('#filter-tempo').on('change', function () {
        table.draw();
        updateLimparBtn();
    });

    // Limpar todos os filtros da tabela
    $('#filter-limpar').on('click', function () {
        $('#filter-tipo').val('');
        $('#filter-tempo').val('');
        table.column(13).search('', false, false).draw();
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
