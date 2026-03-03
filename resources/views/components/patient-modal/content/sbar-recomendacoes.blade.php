@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null,
])

<div class="p-3 sm:p-4 lg:p-6 h-full overflow-y-auto" id="recomendacoes-container">
    {{-- Verifica se os dados já estão carregados --}}
    @if($patientDetails && (
        (isset($patientDetails->procedimentos) && is_array($patientDetails->procedimentos)) ||
        (isset($patientDetails->medicamentos)  && is_array($patientDetails->medicamentos))  ||
        (isset($patientDetails->nutricao)      && is_array($patientDetails->nutricao))      ||
        (isset($patientDetails->recomendacoes) && is_array($patientDetails->recomendacoes)) ||
        (isset($patientDetails->intervencoes)  && is_array($patientDetails->intervencoes))
    ))
        {{-- Dados carregados - mostra o conteúdo --}}
        @include('livewire.partials.recomendacoes-content', [
            'loadingPatient' => $loadingPatient,
            'currentPatient' => $currentPatient,
            'patientDetails' => $patientDetails,
        ])
    @elseif($loadingPatient)
        {{-- Estado de carregamento --}}
        <div class="flex flex-col items-center justify-center py-12">
            <div class="relative">
                <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <div class="absolute inset-0 w-12 h-12 border-4 border-blue-600/20 border-t-transparent rounded-full animate-pulse"></div>
            </div>
            <p class="mt-4 text-gray-600 text-sm font-medium">Carregando recomendações...</p>
        </div>
    @else
        {{-- Carregamento via JS puro --}}
        <div id="recomendacoes-loading" class="flex flex-col items-center justify-center py-12">
            <div class="relative">
                <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <p class="mt-4 text-sm text-gray-500 font-medium">Carregando recomendações...</p>
        </div>

        <div id="recomendacoes-error" class="hidden flex-col items-center text-center py-12">
            <svg class="w-12 h-12 text-red-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p id="recomendacoes-error-msg" class="text-sm text-red-600 mb-4"></p>
            <button onclick="loadRecomendacoes()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Tentar novamente
            </button>
        </div>
    @endif
</div>

@once
<script>
console.log('[Recomendacoes] Script carregado');

function loadRecomendacoes() {
    console.log('[Recomendacoes] loadRecomendacoes() chamado');
    
    const loading = document.getElementById('recomendacoes-loading');
    const error = document.getElementById('recomendacoes-error');
    const errorMsg = document.getElementById('recomendacoes-error-msg');
    
    console.log('[Recomendacoes] Elementos:', {loading: !!loading, error: !!error, errorMsg: !!errorMsg});
    
    if (!loading) {
        console.log('[Recomendacoes] Loading não encontrado, saindo');
        return;
    }
    
    loading.classList.remove('hidden');
    loading.classList.add('flex');
    if (error) {
        error.classList.add('hidden');
        error.classList.remove('flex');
    }
    
    const attendanceNumber = {{ $currentPatient['nr_atendimento'] ?? 0 }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const url = '/passagem-de-plantao/paciente/' + attendanceNumber + '/recomendacoes';
    
    console.log('[Recomendacoes] Attendance:', attendanceNumber);
    console.log('[Recomendacoes] URL:', url);
    console.log('[Recomendacoes] CSRF:', csrfToken ? 'presente' : 'ausente');
    
    if (!attendanceNumber) {
        console.error('[Recomendacoes] Attendance number inválido');
        if (errorMsg) errorMsg.textContent = 'Número de atendimento inválido';
        loading.classList.add('hidden');
        loading.classList.remove('flex');
        if (error) {
            error.classList.remove('hidden');
            error.classList.add('flex');
        }
        return;
    }
    
    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken || ''
        },
        credentials: 'same-origin'
    })
    .then(async r => {
        console.log('[Recomendacoes] Response status:', r.status);
        
        if (!r.ok) {
            const text = await r.text().catch(() => 'Erro');
            console.error('[Recomendacoes] Response não OK:', r.status, text.substring(0, 200));
            let msg = 'Erro ao carregar (' + r.status + ')';
            if (r.status === 419) msg = 'Sessão expirada. Recarregue a página.';
            if (r.status === 401) msg = 'Não autenticado.';
            if (r.status === 403) msg = 'Acesso negado.';
            throw new Error(msg);
        }
        
        return r.json();
    })
    .then(data => {
        console.log('[Recomendacoes] Dados recebidos:', data);
        
        // Usa Livewire para atualizar os dados
        const wireId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
        console.log('[Recomendacoes] Wire ID:', wireId);
        
        if (window.Livewire && wireId) {
            const component = window.Livewire.find(wireId);
            console.log('[Recomendacoes] Component:', component ? 'encontrado' : 'não encontrado');
            if (component) {
                component.call('receiveRecomendacoesData', data);
            }
        }
    })
    .catch(e => {
        console.error('[Recomendacoes] Erro:', e);
        loading.classList.add('hidden');
        loading.classList.remove('flex');
        if (error) {
            error.classList.remove('hidden');
            error.classList.add('flex');
        }
        if (errorMsg) errorMsg.textContent = e.message;
    });
}

// Carrega automaticamente
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Recomendacoes] DOMContentLoaded');
    setTimeout(loadRecomendacoes, 100);
});

// Também tenta carregar quando o Livewire atualiza
document.addEventListener('livewire:updated', function() {
    console.log('[Recomendacoes] Livewire updated');
    setTimeout(loadRecomendacoes, 100);
});
</script>
@endonce

<style>
    .custom-scroll {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
    }
    .custom-scroll::-webkit-scrollbar { width: 6px; }
    .custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    [x-show][style*="display: none"] { display: none !important; pointer-events: none !important; }
</style>
