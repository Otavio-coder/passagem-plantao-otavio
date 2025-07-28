import './bootstrap';

window.scrollMessagesContainer = function() {
    // Implemente o scroll automático do container de mensagens aqui
    // Exemplo:
    const el = document.getElementById('messagesContainer');
    if (el) el.scrollTop = el.scrollHeight;
};

window.bindChatEchoListeners = function(nr_atendimento, turno_id) {
    if (!nr_atendimento || !turno_id) return;

    // Unsubscribe previous channel if needed
    if (window.currentChatChannel) {
        window.Echo.leave(`chat.${window.currentChatChannel}`);
    }
    window.currentChatChannel = `${nr_atendimento}.${turno_id}`;

    // Listen for new messages and pinned messages
    window.Echo.channel(`chat.${nr_atendimento}.${turno_id}`)
        .listen('ChatMessageSent', (e) => {
            if (window.Livewire) {
                // Convert to plain object for Livewire
                window.Livewire.dispatch('chatMessageReceived', JSON.parse(JSON.stringify(e)));
            }
            setTimeout(() => {
                if (typeof scrollMessagesContainer === 'function') scrollMessagesContainer();
            }, 300);
        })
        .listen('ChatMessagePinned', (e) => {
            console.log('Mensagem Fixada', e);
            if (window.Livewire) {
                window.Livewire.dispatch('chatMessagePinned', e);
            }
             setTimeout(() => {
                if (typeof scrollMessagesContainer === 'function') scrollMessagesContainer();
            }, 300);
        });
};

// Optional: Unbind when modal closes
window.unbindChatEchoListeners = function() {
    if (window.currentChatChannel) {
        window.Echo.leave(`chat.${window.currentChatChannel}`);
        window.currentChatChannel = null;
    }
};