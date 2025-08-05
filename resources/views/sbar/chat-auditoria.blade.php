@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-900">Auditoria do Chat</h2>
            <p class="text-gray-600 mt-1">Histórico de mensagens por atendimento e usuários</p>
        </div>

        <!-- Filters -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                        <input type="date" name="data_inicio" value="{{ request('data_inicio', now()->subDays(7)->format('Y-m-d')) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                        <input type="date" name="data_fim" value="{{ request('data_fim', now()->format('Y-m-d')) }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Usuário</label>
                        <select name="usuario_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todos os usuários</option>
                            @foreach($usuarios as $user)
                                <option value="{{ $user->id }}" {{ request('usuario_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nº Atendimento</label>
                        <input type="text" name="nr_atendimento" value="{{ request('nr_atendimento') }}" 
                               placeholder="Digite o número do atendimento"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Filtrar
                    </button>
                    <a href="{{ route('chat-auditoria') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                        Limpar Filtros
                    </a>
                </div>
            </form>
        </div>

        <!-- Statistics -->
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm font-medium text-blue-600">Total de Mensagens</p>
                    <p class="text-2xl font-bold text-blue-900">{{ $chatStats['total_mensagens'] }}</p>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm font-medium text-yellow-600">Mensagens Fixadas</p>
                    <p class="text-2xl font-bold text-yellow-900">{{ $chatStats['mensagens_fixadas'] }}</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-sm font-medium text-green-600">Usuários Ativos</p>
                    <p class="text-2xl font-bold text-green-900">{{ $chatStats['usuarios_ativos'] }}</p>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <p class="text-sm font-medium text-purple-600">Atendimentos com Chat</p>
                    <p class="text-2xl font-bold text-purple-900">{{ $chatStats['atendimentos_com_chat'] }}</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button onclick="showTab('atendimentos')" id="tab-atendimentos" 
                        class="tab-button border-blue-500 text-blue-600 py-2 px-1 border-b-2 font-medium text-sm">
                    Por Atendimento ({{ $gruposAtendimento->count() }})
                </button>
                <button onclick="showTab('usuarios')" id="tab-usuarios" 
                        class="tab-button border-transparent text-gray-500 hover:text-gray-700 py-2 px-1 border-b-2 font-medium text-sm">
                    Por Usuários ({{ $gruposUsuarios->count() }})
                </button>
            </nav>
        </div>

        <!-- Content Atendimentos -->
        <div id="content-atendimentos" class="tab-content">
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($gruposAtendimento as $nrAtendimento => $dados)
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-semibold text-gray-900 text-lg">Atendimento: {{ $nrAtendimento }}</h4>
                                    <div class="mt-2 space-y-1">
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">{{ $dados['total_mensagens'] }}</span> mensagens
                                            @if($dados['mensagens_fixadas'] > 0)
                                                • <span class="font-medium text-yellow-600">{{ $dados['mensagens_fixadas'] }}</span> fixadas
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">Usuários:</span> {{ $dados['usuarios_envolvidos']->join(', ') }}
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">Turnos:</span> {{ $dados['turnos']->map(fn($t) => ucfirst($t))->join(', ') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right text-sm text-gray-500">
                                    <p><span class="font-medium">Primeira:</span> {{ \Carbon\Carbon::parse($dados['primeira_mensagem'])->format('d/m/Y H:i') }}</p>
                                    <p><span class="font-medium">Última:</span> {{ \Carbon\Carbon::parse($dados['ultima_mensagem'])->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <p class="text-gray-500">Nenhum atendimento com mensagens encontrado</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Content Usuários -->
        <div id="content-usuarios" class="tab-content hidden">
            <div class="p-6">
                <div class="space-y-4">
                    @forelse($gruposUsuarios as $usuarioId => $dados)
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div class="flex items-center space-x-3">
                                    @if($dados['usuario']->hasValidPhoto())
                                        <img class="h-12 w-12 rounded-full object-cover" 
                                            src="data:image/png;base64,{{ $dados['usuario']->getUserPhoto() }}" 
                                            alt="{{ $dados['usuario']->name }}">
                                    @else
                                        <div class="h-12 w-12 rounded-full bg-gray-300 flex items-center justify-center">
                                            <svg class="h-6 w-6 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    @endif
                                    <div>
                                        <h4 class="font-semibold text-gray-900 text-lg">{{ $dados['usuario']->name }}</h4>
                                        <div class="mt-1 space-y-1">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">{{ $dados['total_mensagens'] }}</span> mensagens
                                                @if($dados['mensagens_fixadas'] > 0)
                                                    • <span class="font-medium text-yellow-600">{{ $dados['mensagens_fixadas'] }}</span> fixadas
                                                @endif
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">{{ $dados['atendimentos_distintos'] }}</span> atendimentos distintos
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right text-sm text-gray-500">
                                    <p><span class="font-medium">Primeira:</span> {{ \Carbon\Carbon::parse($dados['primeira_atividade'])->format('d/m/Y H:i') }}</p>
                                    <p><span class="font-medium">Última:</span> {{ \Carbon\Carbon::parse($dados['ultima_atividade'])->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">
                            <p class="text-gray-500">Nenhum usuário com mensagens encontrado</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all content
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(el => {
        el.classList.remove('border-blue-500', 'text-blue-600');
        el.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    document.getElementById('tab-' + tabName).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + tabName).classList.add('border-blue-500', 'text-blue-600');
}
</script>
@endsection