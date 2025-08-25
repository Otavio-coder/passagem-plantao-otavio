@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-6">
    <div class="bg-white rounded-lg shadow-sm">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-900">Auditoria do Chat</h2>
            <p class="text-gray-700 mt-1">Histórico de ações e mensagens por atendimento e usuários</p>
        </div>

        <!-- Filters -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ação</label>
                        <select name="acao" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Todas as ações</option>
                            <option value="mensagem_enviada" {{ request('acao') == 'mensagem_enviada' ? 'selected' : '' }}>Mensagem enviada</option>
                            <option value="mensagem_editada" {{ request('acao') == 'mensagem_editada' ? 'selected' : '' }}>Mensagem editada</option>
                            <option value="mensagem_deletada" {{ request('acao') == 'mensagem_deletada' ? 'selected' : '' }}>Mensagem deletada</option>
                            <option value="mensagem_fixada" {{ request('acao') == 'mensagem_fixada' ? 'selected' : '' }}>Mensagem fixada</option>
                            <option value="mensagem_desfixada" {{ request('acao') == 'mensagem_desfixada' ? 'selected' : '' }}>Mensagem desfixada</option>
                            <option value="falha_envio" {{ request('acao') == 'falha_envio' ? 'selected' : '' }}>Falha de envio</option>
                            <option value="acesso_historico" {{ request('acao') == 'acesso_historico' ? 'selected' : '' }}>Acesso histórico</option>
                        </select>
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
                <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-800">Total de Mensagens</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $chatStats['total_mensagens'] }}</p>
                </div>
                <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-800">Mensagens Fixadas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $chatStats['mensagens_fixadas'] }}</p>
                </div>
                <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-800">Usuários Ativos</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $chatStats['usuarios_ativos'] }}</p>
                </div>
                <div class="bg-gray-100 border border-gray-300 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-800">Atendimentos com Chat</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $chatStats['atendimentos_com_chat'] }}</p>
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

        <!-- Auditoria detalhada -->
        <div class="px-6 py-4" id="audit-log-wrapper" style="position:relative;">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Log de Auditoria</h3>
            <div class="overflow-x-auto" id="audit-log-table">
                <table class="min-w-full bg-white border border-gray-300 rounded-lg">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">Data/Hora</th>
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">Usuário</th>
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">Ação</th>
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">Atendimento</th>
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">Turno</th>
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">IP</th>
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">User Agent</th>
                            <th class="px-3 py-2 border-b text-xs font-bold text-gray-700">Detalhes</th>
                        </tr>
                    </thead>
                    <tbody id="audit-log-body">
                        @forelse($auditoriaLogs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 border-b text-xs text-gray-700">{{ $log->dt_acao->format('d/m/Y H:i:s') }}</td>
                                <td class="px-3 py-2 border-b text-xs text-gray-800">{{ $log->usuario?->name ?? 'N/A' }}</td>
                                <td class="px-3 py-2 border-b text-xs text-gray-800">{{ $log->acao }}</td>
                                <td class="px-3 py-2 border-b text-xs text-gray-700">{{ $log->nr_atendimento }}</td>
                                <td class="px-3 py-2 border-b text-xs text-gray-700">{{ ucfirst($log->turno_id) }}</td>
                                <td class="px-3 py-2 border-b text-xs text-gray-700">{{ $log->ip_address }}</td>
                                <td class="px-3 py-2 border-b text-xs text-gray-700">{{ Str::limit($log->user_agent, 32) }}</td>
                                <td class="px-3 py-2 border-b text-xs text-gray-700">
                                    <pre class="whitespace-pre-wrap text-xs bg-gray-100 rounded p-2 border border-gray-200">{{ json_encode($log->detalhes_acao, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-gray-500">Nenhum registro de auditoria encontrado</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-between items-center">
                <div class="text-xs text-gray-600" id="audit-log-info">
                    Página {{ $auditoriaLogs->currentPage() }} de {{ $auditoriaLogs->lastPage() }} | Total: {{ $auditoriaLogs->total() }}
                </div>
                <div id="audit-log-pagination">
                    <!-- Paginação AJAX -->
                    <nav class="inline-flex -space-x-px">
                        @if($auditoriaLogs->onFirstPage())
                            <span class="px-3 py-1 text-gray-400 bg-gray-100 border border-gray-300 rounded-l">Anterior</span>
                        @else
                            <button class="px-3 py-1 text-gray-700 bg-gray-50 border border-gray-300 rounded-l hover:bg-gray-200 audit-pagination-btn" data-page="{{ $auditoriaLogs->currentPage() - 1 }}">Anterior</button>
                        @endif
                        <span class="px-3 py-1 text-gray-700 bg-gray-50 border-t border-b border-gray-300">{{ $auditoriaLogs->currentPage() }}</span>
                        @if($auditoriaLogs->hasMorePages())
                            <button class="px-3 py-1 text-gray-700 bg-gray-50 border border-gray-300 rounded-r hover:bg-gray-200 audit-pagination-btn" data-page="{{ $auditoriaLogs->currentPage() + 1 }}">Próxima</button>
                        @else
                            <span class="px-3 py-1 text-gray-400 bg-gray-100 border border-gray-300 rounded-r">Próxima</span>
                        @endif
                    </nav>
                </div>
            </div>
            <!-- Overlay de loading para paginação AJAX -->
            <div id="audit-log-loading-overlay" style="display:none; position:absolute; inset:0; background:rgba(255,255,255,0.7); z-index:50; align-items:center; justify-content:center;">
                <div style="text-align:center;">
                    <div class="w-8 h-8 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-2"></div>
                    <div class="text-gray-600 text-sm">Carregando página...</div>
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

// Paginação AJAX para logs de auditoria
function loadAuditPage(page) {
    const overlay = document.getElementById('audit-log-loading-overlay');
    if (overlay) overlay.style.display = 'flex';

    const params = new URLSearchParams(window.location.search);
    params.set('page', page);

    fetch(window.location.pathname + '?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
    .then(html => {
        let newAuditLogWrapper = null;
        if (html.trim().startsWith('<div') && html.includes('id="audit-log-wrapper"')) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            newAuditLogWrapper = tempDiv.querySelector('#audit-log-wrapper');
        } else {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            newAuditLogWrapper = doc.getElementById('audit-log-wrapper');
        }
        if (newAuditLogWrapper) {
            document.getElementById('audit-log-wrapper').replaceWith(newAuditLogWrapper);
            window.scrollTo({ top: newAuditLogWrapper.offsetTop - 80, behavior: 'smooth' });
        } else {
            window.location.href = window.location.pathname + '?' + params.toString();
        }
    })
    .finally(() => {
        const overlay = document.getElementById('audit-log-loading-overlay');
        if (overlay) overlay.style.display = 'none';
        // Reanexa listeners após atualização
        attachAuditPaginationListeners();
    });
}

// Delegação de evento para paginação AJAX (clean code, previne duplicidade)
function attachAuditPaginationListeners() {
    // Remove listeners antigos
    document.querySelectorAll('.audit-pagination-btn').forEach(btn => {
        btn.removeEventListener('click', btn._auditClickListener || (()=>{}));
        btn._auditClickListener = function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            if (page) loadAuditPage(page);
        };
        btn.addEventListener('click', btn._auditClickListener);
    });
}

// Inicializa listeners na primeira carga
document.addEventListener('DOMContentLoaded', function() {
    attachAuditPaginationListeners();
});
</script>
@endsection