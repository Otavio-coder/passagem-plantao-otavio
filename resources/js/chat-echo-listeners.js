// Sistema simplificado de Echo para o chat

// Aguarda o Echo estar disponível
function waitForEcho(callback, maxAttempts = 50) {
    let attempts = 0;
    const checkEcho = setInterval(() => {
        attempts++;
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            clearInterval(checkEcho);
            callback();
        } else if (attempts >= maxAttempts) {
            clearInterval(checkEcho);
            console.error('[Chat] Echo não disponível após', maxAttempts, 'tentativas');
        }
    }, 100);
}

// Inicializa os listeners do chat
waitForEcho(() => {
    // Gerenciador global de canais
    window.ChatChannels = {
        active: new Map(),

        // Conecta a um canal por paciente (sem turno)
        connect(patientId, componentId) {
            const channelKey = `chat.${patientId}`;

            if (this.active.has(channelKey)) {
                return;
            }

            try {
                const channel = window.Echo.private(channelKey);

                // Listener para novas mensagens
                channel.listen('.ChatMessageSent', (data) => {
                    const component = window.Livewire.find(componentId);
                    if (component) {
                        component.call('handleNewMessage', data);
                    }
                });

                // Listener para pins
                channel.listen('.ChatMessagePinned', (data) => {
                    const component = window.Livewire.find(componentId);
                    if (component) {
                        component.call('handleMessagePinned', data);
                    }
                });

                this.active.set(channelKey, { channel, componentId });

            } catch (error) {
                console.error('[Chat] Erro ao conectar canal:', error);
            }
        },

        // Desconecta de um canal
        disconnect(patientId) {
            const channelKey = `chat.${patientId}`;
            if (this.active.has(channelKey)) {
                window.Echo.leave(channelKey);
                this.active.delete(channelKey);
            }
        },

        // Desconecta todos os canais
        disconnectAll() {
            this.active.forEach((data, key) => {
                window.Echo.leave(key);
            });
            this.active.clear();
        }
    };

    // Expõe função global para o Alpine usar
    window.connectChatChannel = (patientId, componentId) => {
        window.ChatChannels.connect(patientId, componentId);
    };

    window.disconnectChatChannel = (patientId) => {
        window.ChatChannels.disconnect(patientId);
    };

    window.dispatchEvent(new CustomEvent('chat-echo-ready'));
});

// Livewire hooks para reconectar após navegação
document.addEventListener('livewire:navigated', () => {
    window.ChatChannels?.active.forEach((data, key) => {
        // canais ativos mantidos após navegação
    });
});
