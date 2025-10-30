{{-- resources/views/sbar/avaliacoes-turno.blade.php --}}
    <!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avaliações do Turno</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<div class="container mx-auto px-4 py-6 max-w-4xl" x-data="{ openBed: null }">

    {{-- Header Simples --}}
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Avaliações do Turno</h1>
                <p class="text-sm text-gray-600 mt-1">{{ $sectorName }}</p>
            </div>
            <a href="{{ route('sbar.report') }}" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                ← Voltar
            </a>
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
                        <div class="flex items-center gap-3 text-left">
                            {{-- Número do Leito --}}
                            <div class="flex-shrink-0 w-12 h-12 bg-blue-600 text-white rounded-lg flex items-center justify-center font-bold text-lg">
                                {{ $bed['leito'] }}
                            </div>

                            {{-- Informações do Paciente --}}
                            <div>
                                <div class="font-semibold text-gray-800">{{ $bed['nome_paciente'] }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    Prontuário: {{ $bed['prontuario'] }} | Atendimento: {{ $bed['atendimento'] }}
                                </div>
                            </div>
                        </div>

                        {{-- Badge de Mensagens + Ícone --}}
                        <div class="flex items-center gap-2">
                                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    {{ $bed['total_mensagens'] }} {{ $bed['total_mensagens'] == 1 ? 'mensagem' : 'mensagens' }}
                                </span>
                            <svg
                                class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                :class="{ 'rotate-180': openBed === {{ $index }} }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
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
                        <div class="p-4 bg-gray-50 space-y-3">
                            @foreach($bed['mensagens'] as $msg)
                                <div class="bg-white rounded-lg p-3 shadow-sm">
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
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white text-xs font-bold">
                                                {{ $msg['user_initials'] }}
                                            </div>
                                        @endif

                                        {{-- Nome e Timestamp --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm text-gray-800">{{ $msg['user_name'] }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ $msg['timestamp'] }} | Turno: {{ $msg['turno'] }}
                                                @if($msg['is_pinned'])
                                                    <span class="inline-flex items-center ml-1 px-1.5 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">
                                                            📌 Fixada
                                                        </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Conteúdo da Mensagem --}}
                                    <div class="text-sm text-gray-700 leading-relaxed">
                                        {!! $msg['content'] !!}
                                    </div>

                                    {{-- Auditoria (se disponível) --}}
                                    @if(!empty($msg['auditoria']))
                                        <div class="mt-2 pt-2 border-t border-gray-100">
                                            <details class="text-xs text-gray-500">
                                                <summary class="cursor-pointer hover:text-gray-700 font-medium">
                                                    Informações de Auditoria
                                                </summary>
                                                <div class="mt-1 pl-4 space-y-0.5">
                                                    @foreach($msg['auditoria'] as $audit)
                                                        <div>
                                                            <span class="font-medium">{{ $audit['acao'] }}</span>
                                                            por {{ $audit['usuario'] }}
                                                            em {{ $audit['data'] }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Estado Vazio --}}
        <div class="bg-white rounded-lg shadow-sm p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma Avaliação Encontrada</h3>
            <p class="text-sm text-gray-500">Não há mensagens de avaliação para este turno no setor selecionado.</p>
        </div>
    @endif
</div>
</body>
</html>
