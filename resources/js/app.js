import './bootstrap';

// Faz scroll automático para o final do container de mensagens
window.scrollMessagesContainer = function() {
    const el = document.getElementById('messages-container');
    if (el) el.scrollTop = el.scrollHeight;
};

// Inicia listeners do Echo para o chat de um paciente/turno específico
window.bindChatEchoListeners = function(nr_atendimento, turno_id) {
    if (!nr_atendimento || !turno_id) return;

    // Sai do canal anterior, se houver
    if (window.currentChatChannel) {
        window.Echo.leave(`chat.${window.currentChatChannel}`);
    }
    window.currentChatChannel = `${nr_atendimento}.${turno_id}`;

    // Escuta eventos de nova mensagem e mensagem fixada
    window.Echo.channel(`chat.${nr_atendimento}.${turno_id}`)
        .listen('ChatMessageSent', (e) => {
            // Dispara evento Livewire para nova mensagem
            if (window.Livewire) {
                window.Livewire.dispatch('chatMessageReceived', e);
            }
            // Faz scroll após renderização
            setTimeout(() => {
                if (typeof scrollMessagesContainer === 'function') scrollMessagesContainer();
            }, 300);
        })
        .listen('ChatMessagePinned', (e) => {
            // Dispara evento Livewire para mensagem fixada
            if (window.Livewire) {
                window.Livewire.dispatch('chatMessagePinned', e);
            }
            // Faz scroll após renderização
            setTimeout(() => {
                if (typeof scrollMessagesContainer === 'function') scrollMessagesContainer();
            }, 300);
        });
};

// Remove listeners do Echo do chat atual
window.unbindChatEchoListeners = function() {
    if (window.currentChatChannel) {
        window.Echo.leave(`chat.${window.currentChatChannel}`);
        window.currentChatChannel = null;
    }
};