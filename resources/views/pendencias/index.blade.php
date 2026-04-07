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
    <div id="modal-criterios" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.4)">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">
                    <i class="fas fa-circle-question text-santacasa-100 mr-1.5"></i>
                    Critérios para definição de pendências
                </h2>
                <button type="button" onclick="document.getElementById('modal-criterios').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 transition text-lg leading-none">&times;</button>
            </div>
            <div class="overflow-y-auto px-5 py-4 text-xs text-gray-600 space-y-4">

                <p class="text-gray-500">Um item é considerado <strong class="text-gray-700">pendente</strong> quando atende a <em>todos</em> os critérios abaixo ao mesmo tempo.</p>

                <div>
                    <h3 class="text-[11px] font-semibold text-gray-700 uppercase tracking-wide mb-2">Exames e procedimentos prescritos</h3>
                    <ul class="space-y-1.5">
                        <li class="flex gap-2"><span class="text-santacasa-100 mt-0.5">•</span><span>Status de execução diferente de <em>Executado</em>, <em>Cancelado</em>, <em>Rejeitado</em> ou <em>Baixa especial</em></span></li>
                        <li class="flex gap-2"><span class="text-santacasa-100 mt-0.5">•</span><span>Sem data de baixa (<code class="bg-gray-100 px-1 rounded">dt_baixa</code>) registrada na prescrição</span></li>
                        <li class="flex gap-2"><span class="text-santacasa-100 mt-0.5">•</span><span>Prescrição não cancelada e não suspensa</span></li>
                        <li class="flex gap-2"><span class="text-santacasa-100 mt-0.5">•</span><span>Prescrição médica já liberada e não suspensa pelo médico</span></li>
                        <li class="flex gap-2"><span class="text-santacasa-100 mt-0.5">•</span><span>Sem resultado de laboratório coletado vinculado a esta linha de prescrição</span></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-[11px] font-semibold text-gray-700 uppercase tracking-wide mb-2">Consultorias multidisciplinares</h3>
                    <ul class="space-y-1.5">
                        <li class="flex gap-2"><span class="text-santacasa-100 mt-0.5">•</span><span>Solicitação com status diferente de <em>Respondido</em></span></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-[11px] font-semibold text-gray-700 uppercase tracking-wide mb-2">O que cada motivo significa</h3>
                    <div class="space-y-2">

                        {{-- Exames --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Exames</p>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Aguardando coleta</span>
                            <span>Exame prescrito e liberado, mas ainda não foi coletado pelo laboratório.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Urgente — aguardando coleta</span>
                            <span>Mesmo que acima, porém marcado como urgente pelo médico.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Aguardando laudo</span>
                            <span>Material já coletado, mas o laudo ainda não foi liberado pelo laboratório.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Material em análise — aguardando laudo</span>
                            <span>Amostra em processamento no laboratório. Aguardar liberação do resultado.</span>
                        </div>

                        {{-- Procedimentos --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Procedimentos</p>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Aguardando execução</span>
                            <span>Procedimento prescrito e autorizado, mas ainda não realizado.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Urgente — aguardando execução</span>
                            <span>Mesmo que acima, porém com flag de urgência na prescrição.</span>
                        </div>

                        {{-- Cirurgias --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Cirurgias</p>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Cirurgia eletiva — aguardando realização</span>
                            <span>Cirurgia eletiva agendada nos próximos 30 dias ainda não realizada.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Cirurgia de urgência — aguardando realização</span>
                            <span>Cirurgia de urgência ou emergência agendada — atenção à priorização.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Cirurgia eletiva confirmada</span>
                            <span>Agendamento confirmado pelo centro cirúrgico.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Cirurgia em preparo</span>
                            <span>Paciente em fase de preparo pré-operatório — verificar checklist de preparo.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Paciente em sala — cirurgia em andamento</span>
                            <span>Paciente já foi encaminhado ao centro cirúrgico.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Cirurgia aguardando remarcação</span>
                            <span>Cirurgia foi desmarcada e precisa ser reagendada.</span>
                        </div>

                        {{-- Hemoterapia --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Hemoterapia</p>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Aguardando transfusão de …</span>
                            <span>Hemocomponente específico programado nas próximas 48h ainda não administrado. O tipo aparece no motivo (ex: Concentrado de Hemácias).</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Urgente — aguardando transfusão de …</span>
                            <span>Mesma situação acima com flag de urgência — priorizar preparo e administração imediata.</span>
                        </div>

                        {{-- Quimioterapia --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Quimioterapia</p>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Sessão de quimioterapia agendada</span>
                            <span>Sessão agendada nos próximos 30 dias. Quando disponível, o ciclo é informado no motivo.</span>
                        </div>

                        {{-- Antimicrobianos --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Antimicrobianos</p>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Antimicrobiano em uso — Dia N · dose · via</span>
                            <span>Antibiótico em uso ativo hoje. O motivo detalha o dia de uso, dose, via e frequência. Registrado para acompanhamento — não requer ação imediata.</span>
                        </div>

                        {{-- Consultorias --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Consultorias</p>
                        <div class="flex gap-2 p-2 bg-gray-50 rounded-lg">
                            <span class="shrink-0 font-medium text-gray-700 w-56">Aguardando resposta</span>
                            <span>Consultoria multidisciplinar solicitada sem parecer registrado até o momento.</span>
                        </div>

                        {{-- Diagnósticos de consistência --}}
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide pt-1">Inconsistências no sistema</p>
                        <div class="flex gap-2 p-2 bg-amber-50 rounded-lg border border-amber-100">
                            <span class="shrink-0 font-medium text-amber-800 w-56">Realizado — prescrição não baixada</span>
                            <span class="text-amber-700">O procedimento consta como executado no sistema, mas a linha de prescrição não recebeu baixa. Requer correção no Tasy.</span>
                        </div>
                        <div class="flex gap-2 p-2 bg-amber-50 rounded-lg border border-amber-100">
                            <span class="shrink-0 font-medium text-amber-800 w-56">Exame realizado em solicitação mais recente</span>
                            <span class="text-amber-700">O exame foi coletado via uma prescrição posterior. A solicitação original ficou em aberto sem ser encerrada. Requer baixa manual no Tasy.</span>
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

            <div class="flex flex-col gap-1 w-48">
                <label class="text-xs text-gray-500 font-medium">Tempo pendente</label>
                <select id="filter-tempo"
                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100">
                    <option value="">Qualquer período</option>
                    <option value="0-24">Pendente hoje (até 24h)</option>
                    <option value="24-48">Desde ontem (24h – 48h)</option>
                    <option value="48-168">2 a 7 dias</option>
                    <option value="168-720">8 a 30 dias</option>
                    <option value="720-">Mais de 30 dias</option>
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
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap w-[52px] hidden lg:table-cell">UGB</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide w-[110px] hidden md:table-cell">UGA</th>
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
                                <td class="px-2 py-1.5 text-[11px] whitespace-nowrap">
                                    @if($row['vence_hoje'] ?? false)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 border border-amber-300">
                                            <i class="fas fa-circle-exclamation"></i> Hoje
                                        </span>
                                    @else
                                        <span class="text-gray-600">{{ $row['data_prev_execucao'] }}</span>
                                    @endif
                                </td>
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

    // Converte dd/mm/yyyy HH:mm → horas decorridas desde agora (positivo = no passado)
    function cellDateToHoursAgo(val) {
        if (!val || val === '-') { return null; }
        var m = val.match(/^(\d{2})\/(\d{2})\/(\d{4})(?: (\d{2}):(\d{2}))?/);
        if (!m) { return null; }
        var ts = new Date(m[3], m[2] - 1, m[1], m[4] || 0, m[5] || 0).getTime();
        return (Date.now() - ts) / 3600000;
    }

    // Filtro customizado por tempo pendente:
    // prioriza o valor numérico de data-order na coluna "Pendente há" (col 10)
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'pendencias-table') { return true; }
        var range = $('#filter-tempo').val();
        if (range) {
            var hours = null;

            var row = settings.aoData[dataIndex];
            var pendingCell = row && row.anCells && row.anCells[10] ? row.anCells[10] : null;
            if (pendingCell) {
                var pendingSeconds = parseFloat(pendingCell.getAttribute('data-order') || '');
                if (!isNaN(pendingSeconds) && pendingSeconds >= 0) {
                    hours = pendingSeconds / 3600;
                }
            }

            if (hours === null) {
                hours = cellDateToHoursAgo(data[8]);
            }

            if (hours === null) {
                return false;
            }

            var parts = range.split('-');
            var min = parseFloat(parts[0]);
            var max = parts[1] !== '' ? parseFloat(parts[1]) : Infinity;
            if (!(hours >= min && hours < max)) {
                return false;
            }
        }

        if (activePendingTypeTab === 'all') {
            return true;
        }

        var tipo = String(data[13] || '').toLowerCase();
        if (activePendingTypeTab === 'exame') {
            return tipo.indexOf('exame') !== -1 || tipo.indexOf('laboratório') !== -1 || tipo.indexOf('laboratorio') !== -1;
        }

        if (activePendingTypeTab === 'procedimento') {
            return tipo === 'procedimento';
        }

        if (activePendingTypeTab === 'cirurgia') {
            return tipo === 'cirurgia';
        }

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
