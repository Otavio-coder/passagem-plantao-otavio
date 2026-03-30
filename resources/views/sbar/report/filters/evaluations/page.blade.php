@extends('layouts.app')

@section('content')
@php
    $baseParams = [
        'sector_id' => $sectorId,
        'search'    => $search,
        'date_from' => $dateFrom,
        'date_to'   => $dateTo,
        'per_page'  => $perPage,
    ];

    $sortLink = fn($field) => route('sbar.evaluations.shift', array_merge($baseParams, [
        'sort_by'  => $field,
        'sort_dir' => ($sortBy === $field && $sortDir === 'asc') ? 'desc' : 'asc',
        'page'     => 1,
    ]));

    $sortIcon = function($field) use ($sortBy, $sortDir) {
        if ($sortBy !== $field) return '<i class="fas fa-sort text-gray-300 ml-1 text-[10px]"></i>';
        return $sortDir === 'asc'
            ? '<i class="fas fa-sort-up text-santacasa-100 ml-1 text-[10px]"></i>'
            : '<i class="fas fa-sort-down text-santacasa-100 ml-1 text-[10px]"></i>';
    };

    $hasActiveFilters = !empty($search)
        || $dateFrom !== \Carbon\Carbon::today()->format('Y-m-d')
        || $dateTo   !== \Carbon\Carbon::today()->format('Y-m-d');
@endphp

<div
    class="w-full px-3 my-2 text-gray-600"
    x-data="{ modalOpen: false, selectedBedIdx: null }"
    @keydown.escape.window="modalOpen = false"
>

    {{-- ─── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <span class="text-md md:text-2xl font-medium text-santacasa-100">
                <i class="fas fa-comments mr-1"></i>
                Avaliações do Turno
            </span>
            @if($totalBeds > 0)
                <span class="hidden sm:inline text-xs text-gray-500">
                    {{ $totalBeds }} {{ $totalBeds === 1 ? 'leito' : 'leitos' }} com anotações
                </span>
            @endif
        </div>
        <a href="{{ route('sbar.report') }}"
           class="inline-flex items-center px-3 py-2 text-santacasa-100 hover:text-santacasa-200 transition text-sm font-medium">
            <i class="fas fa-arrow-left mr-1"></i>
            <span class="hidden md:inline">Voltar</span>
        </a>
    </div>

    {{-- ─── Filter Bar ──────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-lg shadow-sm p-3 mb-4">
        <form method="GET" action="{{ route('sbar.evaluations.shift') }}" class="flex flex-wrap gap-2 items-end">
            <input type="hidden" name="sector_id" value="{{ $sectorId }}">
            <input type="hidden" name="sort_by"   value="{{ $sortBy }}">
            <input type="hidden" name="sort_dir"  value="{{ $sortDir }}">
            <input type="hidden" name="page"      value="1">

            {{-- Date From --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500 font-medium">De</label>
                <input
                    type="date"
                    name="date_from"
                    value="{{ $dateFrom }}"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100"
                >
            </div>

            {{-- Date To --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500 font-medium">Até</label>
                <input
                    type="date"
                    name="date_to"
                    value="{{ $dateTo }}"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100"
                >
            </div>

            {{-- Search --}}
            <div class="flex flex-col gap-1 flex-1 min-w-36">
                <label class="text-xs text-gray-500 font-medium">Busca</label>
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Leito, nome, prontuário..."
                        class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-santacasa-100 focus:border-santacasa-100"
                    >
                    <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
            </div>

            {{-- Per Page --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500 font-medium">Por página</label>
                <select
                    name="per_page"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-santacasa-100 bg-white"
                >
                    @foreach($allowedPerPage as $pp)
                        <option value="{{ $pp }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Actions --}}
            <div class="flex items-end gap-2 flex-wrap">
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-santacasa-100 text-white rounded-lg hover:bg-santacasa-200 transition text-sm font-medium"
                >
                    <i class="fas fa-filter text-xs"></i>
                    <span class="hidden sm:inline">Filtrar</span>
                </button>

                @if($hasActiveFilters)
                    <a
                        href="{{ route('sbar.evaluations.shift', ['sector_id' => $sectorId]) }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm"
                    >
                        <i class="fas fa-times text-xs"></i>
                        <span class="hidden sm:inline">Limpar</span>
                    </a>
                @endif

                @if($totalBeds > 0)
                    <a
                        href="{{ route('sbar.evaluations.export', ['sector_id' => $sectorId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm"
                        title="Exportar para Excel"
                    >
                        <i class="fas fa-file-excel text-xs"></i>
                        <span class="hidden sm:inline">Exportar</span>
                    </a>
                @endif
            </div>
        </form>

        {{-- Sector label --}}
        <p class="mt-2 text-xs text-gray-400">
            <i class="fas fa-hospital mr-1"></i>{{ $sectorName }}
            @if($totalBeds > 0)
                &nbsp;·&nbsp;
                Mostrando {{ count($beds) }} de {{ $totalBeds }}
                @if($totalPages > 1)
                    &nbsp;·&nbsp; Página {{ $currentPage }}/{{ $totalPages }}
                @endif
            @endif
        </p>
    </div>

    {{-- ─── Table ───────────────────────────────────────────────────────── --}}
    @if(count($beds) > 0)
        <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 py-3 text-left">
                                <a href="{{ $sortLink('leito') }}" class="inline-flex items-center text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-santacasa-100 transition whitespace-nowrap">
                                    Leito {!! $sortIcon('leito') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-left">
                                <a href="{{ $sortLink('nome_paciente') }}" class="inline-flex items-center text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-santacasa-100 transition whitespace-nowrap">
                                    Paciente {!! $sortIcon('nome_paciente') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-left hidden lg:table-cell">
                                <a href="{{ $sortLink('setor') }}" class="inline-flex items-center text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-santacasa-100 transition whitespace-nowrap">
                                    Setor {!! $sortIcon('setor') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-left hidden md:table-cell">
                                <a href="{{ $sortLink('dt_entrada') }}" class="inline-flex items-center text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-santacasa-100 transition whitespace-nowrap">
                                    Internação {!! $sortIcon('dt_entrada') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-left hidden md:table-cell">
                                <a href="{{ $sortLink('dt_alta') }}" class="inline-flex items-center text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-santacasa-100 transition whitespace-nowrap">
                                    Alta {!! $sortIcon('dt_alta') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-center hidden md:table-cell">
                                <a href="{{ $sortLink('internment_days') }}" class="inline-flex items-center justify-center text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-santacasa-100 transition whitespace-nowrap">
                                    Dias {!! $sortIcon('internment_days') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-center">
                                <a href="{{ $sortLink('total_mensagens') }}" class="inline-flex items-center justify-center text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-santacasa-100 transition whitespace-nowrap">
                                    Anotações {!! $sortIcon('total_mensagens') !!}
                                </a>
                            </th>
                            <th class="px-3 py-3 text-center w-16">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ver</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($beds as $idx => $bed)
                            <tr class="hover:bg-gray-50 transition-colors">
                                {{-- Leito --}}
                                <td class="px-3 py-3">
                                    @php
                                        $bl = strlen($bed['leito']);
                                        $fs = $bl > 5 ? 'text-[9px]' : ($bl > 4 ? 'text-[10px]' : 'text-xs');
                                        $bw = $bl > 5 ? 'w-14' : 'w-11';
                                    @endphp
                                    <div class="{{ $bw }} h-10 bg-gradient-to-br from-santacasa-100 to-santacasa-default text-white rounded-lg flex items-center justify-center font-bold {{ $fs }} shadow-sm">
                                        {{ $bed['leito'] }}
                                    </div>
                                </td>

                                {{-- Paciente --}}
                                <td class="px-3 py-3 min-w-0">
                                    <div class="font-medium text-gray-800 truncate max-w-[180px]">{{ $bed['nome_paciente'] }}</div>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        <i class="fas fa-id-card mr-0.5"></i>{{ $bed['prontuario'] }}
                                    </div>
                                </td>

                                {{-- Setor --}}
                                <td class="px-3 py-3 text-xs text-gray-600 hidden lg:table-cell">
                                    {{ $bed['setor'] }}
                                </td>

                                {{-- Internação --}}
                                <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap hidden md:table-cell">
                                    {{ $bed['dt_entrada_formatted'] ?? '—' }}
                                </td>

                                {{-- Alta --}}
                                <td class="px-3 py-3 text-xs whitespace-nowrap hidden md:table-cell">
                                    @if($bed['dt_alta_formatted'])
                                        <span class="text-amber-600 font-medium">
                                            <i class="fas fa-door-open mr-1 text-[10px]"></i>{{ $bed['dt_alta_formatted'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Dias --}}
                                <td class="px-3 py-3 text-center hidden md:table-cell">
                                    @if($bed['internment_days'] !== null)
                                        @php $dias = (int) $bed['internment_days']; @endphp
                                        <span class="inline-flex items-center justify-center w-9 h-7 text-xs font-semibold rounded-full
                                            {{ $dias >= 30 ? 'bg-red-100 text-red-700' : ($dias >= 14 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                            {{ $dias }}d
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>

                                {{-- Anotações --}}
                                <td class="px-3 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 bg-blue-50 text-santacasa-100 text-xs font-semibold px-2.5 py-1 rounded-full">
                                        <i class="fas fa-comment-alt text-[9px]"></i>
                                        {{ $bed['total_mensagens'] }}
                                    </span>
                                </td>

                                {{-- Ver --}}
                                <td class="px-3 py-3 text-center">
                                    <button
                                        @click="selectedBedIdx = {{ $idx }}; modalOpen = true"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-santacasa-100 text-white rounded-lg hover:bg-santacasa-200 transition text-xs font-medium"
                                        title="Ver anotações"
                                    >
                                        <i class="fas fa-eye text-[10px]"></i>
                                        <span class="hidden sm:inline">Ver</span>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ─── Pagination ──────────────────────────────────────────────────── --}}
        @if($totalPages > 1)
            @php
                $paginationBase = array_merge($baseParams, ['sort_by' => $sortBy, 'sort_dir' => $sortDir]);
                $pageUrl = fn($p) => route('sbar.evaluations.shift', array_merge($paginationBase, ['page' => $p]));
            @endphp
            <div class="flex justify-center">
                <nav class="inline-flex items-center gap-1 bg-white rounded-lg shadow-sm px-2 py-2">
                    @if($currentPage > 1)
                        <a href="{{ $pageUrl(1) }}" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition"><i class="fas fa-angles-left"></i></a>
                        <a href="{{ $pageUrl($currentPage - 1) }}" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition"><i class="fas fa-angle-left"></i></a>
                    @endif

                    @php
                        $rangeStart = max(1, $currentPage - 2);
                        $rangeEnd   = min($totalPages, $currentPage + 2);
                    @endphp
                    @for($i = $rangeStart; $i <= $rangeEnd; $i++)
                        @if($i === $currentPage)
                            <span class="px-3 py-2 text-sm font-medium text-white bg-santacasa-100 rounded-md">{{ $i }}</span>
                        @else
                            <a href="{{ $pageUrl($i) }}" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition">{{ $i }}</a>
                        @endif
                    @endfor

                    @if($currentPage < $totalPages)
                        <a href="{{ $pageUrl($currentPage + 1) }}" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition"><i class="fas fa-angle-right"></i></a>
                        <a href="{{ $pageUrl($totalPages) }}" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition"><i class="fas fa-angles-right"></i></a>
                    @endif
                </nav>
            </div>
        @endif

    @else
        {{-- ─── Empty State ─────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <i class="fas fa-inbox text-gray-300 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">
                @if($hasActiveFilters) Nenhum resultado encontrado @else Nenhuma anotação encontrada @endif
            </h3>
            <p class="text-sm text-gray-500 mb-4">
                @if($hasActiveFilters)
                    Nenhum leito com anotações para os filtros selecionados.
                @else
                    Não há anotações registradas para hoje neste setor.
                @endif
            </p>
            @if($hasActiveFilters)
                <a href="{{ route('sbar.evaluations.shift', ['sector_id' => $sectorId]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-santacasa-100 text-white rounded-lg hover:bg-santacasa-200 transition text-sm">
                    <i class="fas fa-times"></i> Limpar filtros
                </a>
            @endif
        </div>
    @endif

    {{-- ─── Messages Modal ─────────────────────────────────────────────── --}}
    <div
        x-show="modalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/50" @click="modalOpen = false"></div>

        {{-- Panel --}}
        <div
            class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col z-10"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.stop
        >
            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 flex-shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    @foreach($beds as $idx => $bed)
                        <div x-show="selectedBedIdx === {{ $idx }}" class="flex items-center gap-3 min-w-0">
                            @php
                                $bl = strlen($bed['leito']);
                                $fs = $bl > 5 ? 'text-[9px]' : ($bl > 4 ? 'text-[10px]' : 'text-xs');
                                $bw = $bl > 5 ? 'w-14' : 'w-11';
                            @endphp
                            <div class="{{ $bw }} h-10 bg-gradient-to-br from-santacasa-100 to-santacasa-default text-white rounded-lg flex items-center justify-center font-bold {{ $fs }} shadow-sm flex-shrink-0">
                                {{ $bed['leito'] }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-800 truncate">{{ $bed['nome_paciente'] }}</div>
                                <div class="text-xs text-gray-500">
                                    <i class="fas fa-id-card mr-1"></i>{{ $bed['prontuario'] }}
                                    <span class="mx-1">·</span>
                                    <span class="text-santacasa-100 font-medium">{{ $bed['total_mensagens'] }} {{ $bed['total_mensagens'] === 1 ? 'anotação' : 'anotações' }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button
                    @click="modalOpen = false"
                    class="flex-shrink-0 ml-3 w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition"
                >
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="overflow-y-auto flex-1 p-4">
                @foreach($beds as $idx => $bed)
                    <div x-show="selectedBedIdx === {{ $idx }}" class="space-y-3">
                        @forelse($bed['mensagens'] as $msg)
                            <div class="rounded-lg border p-3 {{ $msg['is_pinned'] ? 'border-santacasa-100 bg-blue-50/30' : 'border-gray-200 bg-white' }}">
                                <div class="flex items-start gap-2.5 mb-2 pb-2 border-b border-gray-100">
                                    <x-ui.user-avatar
                                        :photo="$msg['user_photo'] ?? null"
                                        :name="$msg['user_name'] ?? 'U'"
                                        class="w-8 h-8 flex-shrink-0"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-sm text-gray-800">{{ $msg['user_name'] }}</div>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-0.5">
                                            <span class="text-xs text-gray-500">
                                                <i class="far fa-clock mr-0.5"></i>{{ $msg['timestamp'] }}
                                            </span>
                                            <span class="px-1.5 py-0.5 bg-blue-50 text-santacasa-100 text-[10px] font-medium rounded">
                                                {{ $msg['turno'] }}
                                            </span>
                                            @if($msg['is_pinned'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 bg-yellow-100 text-yellow-700 text-[10px] font-medium rounded">
                                                    <i class="fas fa-thumbtack mr-0.5"></i>Fixada
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-700 leading-relaxed">{!! $msg['content'] !!}</div>
                            </div>
                        @empty
                            <p class="text-center text-sm text-gray-500 py-6">Nenhuma anotação.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>

            {{-- Modal Footer --}}
            <div class="px-5 py-3 border-t border-gray-200 flex-shrink-0 flex justify-end">
                <button
                    @click="modalOpen = false"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium"
                >
                    Fechar
                </button>
            </div>
        </div>
    </div>

</div>
@endsection
