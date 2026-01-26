{{-- resources/views/sbar/avaliacoes-turno.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliações do Turno</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Paleta de cores Santa Casa */
        :root {
            --santa-casa-primary: #004D9D;
            --santa-casa-secondary: #0071B9;
            --santa-casa-light: #E8F4FD;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<div class="container mx-auto px-4 py-6 max-w-5xl" x-data="{ openBed: null }">

    {{-- Header com Filtro de Busca --}}
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-[#004D9D]">Avaliações do Turno</h1>
                <p class="text-sm text-gray-600 mt-1">{{ $sectorName }}</p>
                @if($totalBeds > 0)
                    <p class="text-xs text-gray-500 mt-1">
                        Mostrando {{ count($beds) }} de {{ $totalBeds }} leitos com mensagens
                    </p>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                {{-- Filtro de Busca --}}
                <form method="GET" action="{{ route('sbar.avaliacoes.turno') }}" class="flex items-center gap-2">
                    <input type="hidden" name="sector_id" value="{{ $sectorId }}">
                    <div class="relative">
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Buscar leito, nome, prontuário..."
                            class="w-full sm:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#0071B9] focus:border-[#0071B9] transition-colors"
                        >
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-[#0071B9] text-white rounded-lg hover:bg-[#004D9D] transition-colors text-sm font-medium">
                        <i class="fas fa-filter mr-1"></i>Filtrar
                    </button>
                    @if(!empty($search))
                        <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $sectorId]) }}" class="px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors text-sm">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </form>

                {{-- Botão de Exportação --}}
                @if(isset($beds) && count($beds) > 0)
                    <a href="{{ route('sbar.avaliacoes.export', ['sector_id' => $sectorId]) }}"
                       class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium flex items-center gap-2"
                       title="Exportar para Excel">
                        <i class="fas fa-file-excel"></i>
                        <span class="hidden sm:inline">Exportar</span>
                    </a>
                @endif

                <a href="{{ route('sbar.report') }}" class="text-[#0071B9] hover:text-[#004D9D] text-sm font-medium flex items-center gap-1">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    {{-- Lista de Leitos --}}
    @if(isset($beds) && count($beds) > 0)
        <div class="space-y-3">
            @foreach($beds as $index => $bed)
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    {{-- Header do Leito (Clicável) --}}
                    <button
                        @click="openBed = openBed === {{ $index }} ? null : {{ $index }}"
                        class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors"
                    >
                        <div class="flex items-center gap-3 text-left min-w-0">
                            {{-- Número do Leito - Paleta Santa Casa com suporte a leitos longos --}}
                            @php
                                $leitoText = $bed['leito'];
                                $leitoLength = strlen($leitoText);
                                $fontSize = $leitoLength > 5 ? 'text-[10px]' : ($leitoLength > 4 ? 'text-xs' : 'text-sm');
                                $boxSize = $leitoLength > 5 ? 'w-14 h-12' : 'w-12 h-12';
                            @endphp
                            <div class="flex-shrink-0 {{ $boxSize }} bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold {{ $fontSize }} shadow-md">
                                {{ $leitoText }}
                            </div>

                            {{-- Informações do Paciente --}}
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-800 truncate">{{ $bed['nome_paciente'] }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas fa-id-card text-[#0071B9]"></i> {{ $bed['prontuario'] }}
                                    </span>
                                    <span class="mx-1">|</span>
                                    <span class="inline-flex items-center gap-1">
                                        <i class="fas fa-hospital-user text-[#0071B9]"></i> {{ $bed['atendimento'] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Badge de Mensagens + Ícone --}}
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="bg-[#E8F4FD] text-[#004D9D] text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ $bed['total_mensagens'] }} {{ $bed['total_mensagens'] == 1 ? 'msg' : 'msgs' }}
                            </span>
                            <i class="fas fa-chevron-down text-gray-400 transition-transform duration-200"
                               :class="{ 'rotate-180': openBed === {{ $index }} }"></i>
                        </div>
                    </button>

                    {{-- Conteúdo Expansível (Mensagens) --}}
                    <div
                        x-show="openBed === {{ $index }}"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        class="border-t border-gray-100"
                    >
                        <div class="p-4 bg-gray-50 space-y-3 max-h-96 overflow-y-auto">
                            @foreach($bed['mensagens'] as $msg)
                                <div class="bg-white rounded-lg p-3 shadow-sm {{ $msg['is_pinned'] ? 'border-l-4 border-[#0071B9]' : '' }}">
                                    {{-- Header da Mensagem --}}
                                    <div class="flex items-center gap-2 mb-2 pb-2 border-b border-gray-100">
                                        {{-- Avatar --}}
                                        @if($msg['user_photo'])
                                            <img
                                                src="data:image/jpeg;base64,{{ $msg['user_photo'] }}"
                                                alt="{{ $msg['user_name'] }}"
                                                class="w-8 h-8 rounded-full object-cover"
                                            />
                                        @else
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#004D9D] to-[#0071B9] flex items-center justify-center text-white text-xs font-bold">
                                                {{ $msg['user_initials'] }}
                                            </div>
                                        @endif

                                        {{-- Nome e Timestamp --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm text-gray-800">{{ $msg['user_name'] }}</div>
                                            <div class="text-xs text-gray-500 flex items-center gap-2 flex-wrap">
                                                <span><i class="far fa-clock mr-1"></i>{{ $msg['timestamp'] }}</span>
                                                <span class="px-1.5 py-0.5 bg-[#E8F4FD] text-[#004D9D] text-xs rounded font-medium">{{ $msg['turno'] }}</span>
                                                @if($msg['is_pinned'])
                                                    <span class="inline-flex items-center px-1.5 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">
                                                        <i class="fas fa-thumbtack mr-1"></i>Fixada
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Conteúdo da Mensagem --}}
                                    <div class="text-sm text-gray-700 leading-relaxed">
                                        {!! $msg['content'] !!}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Paginação --}}
        @if($totalPages > 1)
            <div class="mt-6 flex justify-center">
                <nav class="inline-flex items-center gap-1 bg-white rounded-lg shadow-sm px-2 py-2">
                    {{-- Primeira página --}}
                    @if($currentPage > 1)
                        <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $sectorId, 'search' => $search, 'page' => 1]) }}"
                           class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
                            <i class="fas fa-angles-left"></i>
                        </a>
                        <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $sectorId, 'search' => $search, 'page' => $currentPage - 1]) }}"
                           class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    @endif

                    {{-- Páginas --}}
                    @php
                        $start = max(1, $currentPage - 2);
                        $end = min($totalPages, $currentPage + 2);
                    @endphp
                    @for($i = $start; $i <= $end; $i++)
                        @if($i == $currentPage)
                            <span class="px-3 py-2 text-sm font-medium text-white bg-[#0071B9] rounded-md">{{ $i }}</span>
                        @else
                            <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $sectorId, 'search' => $search, 'page' => $i]) }}"
                               class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition-colors">{{ $i }}</a>
                        @endif
                    @endfor

                    {{-- Última página --}}
                    @if($currentPage < $totalPages)
                        <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $sectorId, 'search' => $search, 'page' => $currentPage + 1]) }}"
                           class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $sectorId, 'search' => $search, 'page' => $totalPages]) }}"
                           class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-md transition-colors">
                            <i class="fas fa-angles-right"></i>
                        </a>
                    @endif
                </nav>
            </div>
        @endif
    @else
        {{-- Estado Vazio --}}
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">
                @if(!empty($search))
                    Nenhum Resultado Encontrado
                @else
                    Nenhuma Avaliação Encontrada
                @endif
            </h3>
            <p class="text-sm text-gray-500">
                @if(!empty($search))
                    Não foram encontrados leitos para "{{ $search }}". Tente outra busca.
                @else
                    Não há mensagens de avaliação para este turno no setor selecionado.
                @endif
            </p>
            @if(!empty($search))
                <a href="{{ route('sbar.avaliacoes.turno', ['sector_id' => $sectorId]) }}"
                   class="inline-flex items-center mt-4 px-4 py-2 bg-[#0071B9] text-white rounded-lg hover:bg-[#004D9D] transition-colors text-sm">
                    <i class="fas fa-times mr-2"></i>Limpar Busca
                </a>
            @endif
        </div>
    @endif
</div>
</body>
</html>
