import './bootstrap';


import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
            console.log('Mensagem Recebida', e);
            if (window.Livewire) {
                window.Livewire.dispatch('chatMessageReceived', e);
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