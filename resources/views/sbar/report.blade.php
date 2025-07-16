@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                @livewire('sbar-report')
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
// Intervalo do relógio em tempo real
let clockInterval = null;

// Atualiza todos os horários em tempo real
function updateRealTimeClock() {
    const currentTime = new Date().toLocaleTimeString('pt-BR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    // Atualiza elementos de exibição de horário
    document.querySelectorAll('#current-time-display, #input-time').forEach(el => {
        el.textContent = currentTime;
    });
}

// Inicia o intervalo do relógio, evitando duplicidade
function startClockInterval() {
    if (clockInterval) clearInterval(clockInterval);
    updateRealTimeClock();
    clockInterval = setInterval(updateRealTimeClock, 1000);
}

// Faz scroll automático do container de mensagens
function scrollMessagesContainer() {
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 100);
    }
}

// Ajusta altura do textarea e contador de caracteres
function handleTextareaInput(e) {
    if (e.target.matches('textarea[wire\\:model\\.defer="newChatMessage"]')) {
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 80) + 'px';
        const charCount = e.target.value.length;
        const counter = e.target.closest('form').querySelector('[class*="bg-gray-100"]');
        if (counter) {
            counter.textContent = charCount + '/1000';
            // Altera cor do contador conforme quantidade de caracteres
            if (charCount > 900) {
                counter.className = counter.className.replace('bg-gray-100 text-gray-600', 'bg-red-100 text-red-600');
            } else if (charCount > 800) {
                counter.className = counter.className.replace('bg-gray-100 text-gray-600', 'bg-yellow-100 text-yellow-600');
            } else {
                counter.className = counter.className.replace(/bg-(red|yellow)-100 text-(red|yellow)-600/, 'bg-gray-100 text-gray-600');
            }
        }
    }
}

// Protege envio de mensagem vazia
function handleFormSubmit(e) {
    if (e.target.matches('form[wire\\:submit\\.prevent="sendChatMessage"]')) {
        const textarea = e.target.querySelector('textarea');
        if (!textarea.value.trim()) {
            e.preventDefault();
        }
    }
}

// Listeners globais para inicialização e atualização
document.addEventListener('DOMContentLoaded', function() {
    startClockInterval();
    scrollMessagesContainer();
});

document.addEventListener('livewire:navigated', function () {
    startClockInterval();
    scrollMessagesContainer();
});

document.addEventListener('livewire:component.updated', function(e) {
    startClockInterval();
    scrollMessagesContainer();
});

document.addEventListener('input', handleTextareaInput);
document.addEventListener('submit', handleFormSubmit);
</script>

<!-- Script de autoscroll externo -->
<script src="{{ asset('js/sbar-autoscroll.js') }}"></script>

<script>
// Inicialização do modal de boas-vindas (primeira visita)
document.addEventListener('DOMContentLoaded', function() {
    const welcomeShown = localStorage.getItem('sbar_welcome_shown');
    if (welcomeShown) {
        const welcomeElement = document.querySelector('[x-data*="showWelcome"]');
        if (welcomeElement && welcomeElement.__x) {
            welcomeElement.__x.$data.showWelcome = false;
        }
    }
});
</script>

<script>
// Inicialização adicional e verificação do Livewire
document.addEventListener('DOMContentLoaded', function() {
    const welcomeShown = localStorage.getItem('sbar_welcome_shown');
    if (welcomeShown) {
        const welcomeElement = document.querySelector('[x-data*="showWelcome"]');
        if (welcomeElement && welcomeElement.__x) {
            welcomeElement.__x.$data.showWelcome = false;
        }
    }

    if (window.Livewire) {
        // Livewire carregado
        console.log('Livewire loaded successfully');
    }
});
</script>
@endpush
