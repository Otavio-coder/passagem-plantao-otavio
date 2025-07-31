// --- Global optimistic messages store ---
window.optimisticMessages = {};

// --- Add an optimistic (pending) chat message to the UI ---
function addOptimisticMessage(msg) {
    const messagesContainer = document.getElementById('messages-container');
    if (!messagesContainer) return;
    const tempId = 'temp-' + Date.now();
    window.optimisticMessages[tempId] = msg;
    const div = document.createElement('div');
    div.id = 'msg-' + tempId;
    div.className = 'flex justify-end opacity-60';
    div.innerHTML = `
        <div class="flex items-start space-x-2 flex-row-reverse space-x-reverse">
            <div class="w-6 h-6 rounded-full bg-blue-200 flex items-center justify-center text-xs text-blue-700 flex-shrink-0">EU</div>
            <div class="flex-1 min-w-0">
                <div class="bg-blue-50 rounded p-2 shadow-sm border border-blue-200">
                    <div class="flex items-center space-x-2 mb-1">
                        <p class="text-xs font-medium text-gray-800 truncate">${msg.author}</p>
                        <span class="text-xs text-gray-500">${msg.time}</span>
                        <span class="text-xs text-blue-400">(enviando...)</span>
                    </div>
                    <p class="text-xs text-gray-700 leading-relaxed">${msg.mensagem}</p>
                </div>
            </div>
        </div>
    `;
    messagesContainer.appendChild(div);
    scrollMessagesContainer();
    return tempId;
}

// --- Handle chat form submission for optimistic UI ---
document.addEventListener('submit', function(e) {
    if (e.target.matches('form[wire\\:submit\\.prevent="sendChatMessage"]')) {
        const textarea = e.target.querySelector('textarea');
        const msg = textarea.value.trim();
        if (msg) {
            const now = new Date();
            const time = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            const author = window.currentUserName || 'EU';
            const tempId = addOptimisticMessage({ mensagem: msg, author, time });
            textarea.value = '';
            window.addEventListener('chatMessageReceived', function handler(e) {
                const tempDiv = document.getElementById('msg-' + tempId);
                if (tempDiv) tempDiv.remove();
                window.removeEventListener('chatMessageReceived', handler);
            });
        }
    }
});

document.addEventListener('DOMContentLoaded', function () {
    // --- Real-time clock for modal ---
    let clockInterval = null;

    // Atualiza o relógio em tempo real
    function updateRealTimeClock() {
        const currentTime = new Date().toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        document.querySelectorAll('#current-time-display, #input-time').forEach(el => {
            el.textContent = currentTime;
        });
    }

    // Inicia o intervalo do relógio
    function startClockInterval() {
        if (clockInterval) clearInterval(clockInterval);
        updateRealTimeClock();
        clockInterval = setInterval(updateRealTimeClock, 1000);
    }

    // --- Scrolla o container de mensagens para o final ---
    function scrollMessagesContainer() {
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            requestAnimationFrame(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }, 100);
            });
        }
    }

    // --- Scrolla até uma mensagem fixada ao receber o evento ---
    window.addEventListener('scroll-to-message', function (e) {
        const id = e.detail && e.detail.messageId ? e.detail.messageId : null;
        if (id) {
            const el = document.getElementById('msg-' + id);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('ring-2', 'ring-yellow-400');
                setTimeout(() => el.classList.remove('ring-2', 'ring-yellow-400'), 1200);
            }
        }
    });

    // --- Clique na barra fixada ou botão de fixar/desfixar mensagem ---
    document.addEventListener('click', function (e) {
        // Clique na barra fixada
        const pinnedBar = e.target.closest('.pinned-message-bar');
        if (pinnedBar && pinnedBar.dataset.messageId) {
            window.dispatchEvent(new CustomEvent('scroll-to-message', { detail: { messageId: pinnedBar.dataset.messageId } }));
        }
        // Clique no botão de fixar/desfixar
        const pinBtn = e.target.closest('.pin-btn');
        if (pinBtn && pinBtn.dataset.messageId) {
            e.preventDefault();
            if (window.Alpine) {
                let root = pinBtn.closest('[x-data]');
                if (root && root.__x && root.__x.$data && typeof root.__x.$data.pinMessage === 'function') {
                    root.__x.$data.pinMessage(Number(pinBtn.dataset.messageId));
                }
            }
        }
    });

    // --- Listeners Livewire/Alpine específicos do modal ---
    document.addEventListener('livewire:component.updated', function () {
        startClockInterval();
        scrollMessagesContainer();

        // Detecta se está em histórico
        const modal = document.querySelector('.relative[data-patient-id][data-shift]');
        const isViewingHistory = window.Livewire?.find(modal?.getAttribute('wire:id'))?.get('viewingHistory');
        if (modal && window.bindChatEchoListeners) {
            const patientId = modal.getAttribute('data-patient-id');
            const shift = modal.getAttribute('data-shift');
            if (patientId && shift && !isViewingHistory) {
                window.bindChatEchoListeners(patientId, shift);
            } else {
                window.unbindChatEchoListeners();
            }
        }
    });

    // --- Evento de confirmação de fixação de mensagem (Livewire) ---
    window.addEventListener('show-pin-confirm', function (e) {
        // Pode adicionar JS extra aqui se necessário
    });

    // --- Auto-resize do textarea e contador de caracteres ---
    function handleTextareaInput(e) {
        if (e.target.matches('textarea[wire\\:model\\.defer="newChatMessage"]')) {
            e.target.style.height = 'auto';
            e.target.style.height = Math.min(e.target.scrollHeight, 80) + 'px';
            const charCount = e.target.value.length;
            const counter = e.target.closest('form').querySelector('[class*="bg-gray-100"]');
            if (counter) {
                counter.textContent = charCount + '/1000';
                // Muda cor do contador conforme quantidade de caracteres
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

    // --- Impede envio de mensagem vazia no chat ---
    function handleFormSubmit(e) {
        if (e.target.matches('form[wire\\:submit\\.prevent="sendChatMessage"]')) {
            const textarea = e.target.querySelector('textarea');
            if (!textarea.value.trim()) {
                e.preventDefault();
            }
        }
    }

    // --- Listeners Livewire/Alpine específicos do modal (duplicado para garantir rebind) ---
    document.addEventListener('livewire:component.updated', function () {
        startClockInterval();
        scrollMessagesContainer();

        // Re-bind chat listeners se necessário
        const modal = document.querySelector('.relative[data-patient-id][data-shift]');
        if (modal && window.bindChatEchoListeners) {
            const patientId = modal.getAttribute('data-patient-id');
            const shift = modal.getAttribute('data-shift');
            if (patientId && shift) {
                window.bindChatEchoListeners(patientId, shift);
            }
        }
    });

    // --- Listeners de input e submit para o chat ---
    document.addEventListener('input', handleTextareaInput);
    document.addEventListener('submit', handleFormSubmit);

    // --- Execução inicial ao carregar DOM ---
    startClockInterval();
    scrollMessagesContainer();
});