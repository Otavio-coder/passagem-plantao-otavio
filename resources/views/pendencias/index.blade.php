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
        <a href="{{ route('sbar.report') }}"
           class="inline-flex items-center px-3 py-2 text-santacasa-100 hover:text-santacasa-200 transition text-sm font-medium">
            <i class="fas fa-arrow-left mr-1"></i>
            <span class="hidden md:inline">Voltar</span>
        </a>
    </div>

    @if(!empty($errorMessage))
        <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg mb-3">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Filtros de hospital/setor --}}
    <div class="bg-white rounded-lg shadow-sm p-3 mb-3">
        <form method="GET" action="{{ route('pending.report') }}" class="flex flex-wrap gap-2 items-end">
            <div class="flex flex-col gap-1 min-w-48">
                <label class="text-xs text-gray-500 font-medium">Hospital</label>
                <select name="hospital_id"
                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100"
                    onchange="this.form.submit()">
                    @foreach($hospitals as $hospital)
                        <option value="{{ $hospital['hospital_id'] }}" {{ (int)$selectedHospital === (int)$hospital['hospital_id'] ? 'selected' : '' }}>
                            {{ $hospital['hospital_name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col gap-1 min-w-56">
                <label class="text-xs text-gray-500 font-medium">Setor</label>
                <select name="sector_id"
                    class="px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100"
                    onchange="this.form.submit()">
                    @foreach($sectors as $sector)
                        <option value="{{ $sector['sector_code'] }}" {{ (int)$selectedSector === (int)$sector['sector_code'] ? 'selected' : '' }}>
                            {{ $sector['sector_name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        @if($sectorName)
            <p class="mt-1.5 text-xs text-gray-400">
                <i class="fas fa-hospital mr-1"></i>{{ $sectorName }}
            </p>
        @endif
    </div>

    @if($rows->count() > 0)
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-3">
            <div class="overflow-x-auto p-6">
                <table id="pendencias-table" class="w-full" style="width:100%">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Atend.</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Paciente</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Leito</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Classif.</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Descrição</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Solicitação</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Agendamento</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Pendente</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                            <th class="px-2 py-2 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Laudo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 font-medium whitespace-nowrap">{{ $row['atendimento'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 whitespace-nowrap">{{ $row['paciente'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 whitespace-nowrap">{{ $row['ugb'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-500 whitespace-nowrap">{{ $row['classificacao'] ?? '-' }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 max-w-[220px] truncate" title="{{ $row['item'] }}">{{ $row['item'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 whitespace-nowrap">{{ $row['data_solicitacao'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 whitespace-nowrap">{{ $row['data_agendamento'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-700 whitespace-nowrap">{{ $row['tempo_pendente'] }}</td>
                                <td class="px-2 py-1.5 text-[11px] whitespace-nowrap">
                                    @php $isUrgent = str_contains(mb_strtolower($row['status'] ?? ''), 'urg'); @endphp
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full font-medium text-[10px] {{ $isUrgent ? 'bg-red-100 text-red-700' : 'bg-blue-50 text-blue-700' }}">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-[11px] text-gray-600 max-w-[200px] truncate" title="{{ $row['laudo'] }}">{{ $row['laudo'] ?? '-' }}</td>
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
$(document).ready(function () {
    $('#pendencias-table').DataTable({
        pageLength: 5,
        lengthMenu: [5,15,50, 100, 200],
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
        columnDefs: [
            { targets: [4, 9], orderable: true }
        ],
        dom: '<"flex flex-wrap items-center justify-between gap-2 mb-3"lf>rt<"flex flex-wrap items-center justify-between gap-2 mt-3"ip>',
        initComplete: function () {
            $('#pendencias-table_filter input').addClass('px-2 py-1.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100');
            $('#pendencias-table_length select').addClass('px-2 py-1.5 border border-gray-300 rounded-lg text-xs');
        }
    });
});
</script>
@endpush
@endsection
