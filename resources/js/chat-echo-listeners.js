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
    //console.log('[Chat] Echo disponível, configurando sistema de chat');
    
    // Gerenciador global de canais
    window.ChatChannels = {
        active: new Map(),
        
        // Conecta a um canal
        connect(patientId, shift, componentId) {
            const channelKey = `chat.${patientId}.${shift}`;
            
            // Se já existe, não recria
            if (this.active.has(channelKey)) {
                //console.log('[Chat] Canal já ativo:', channelKey);
                return;
            }
            
            try {
                // Conecta ao canal privado
                const channel = window.Echo.private(channelKey);
                
                // Listener para novas mensagens
                channel.listen('.ChatMessageSent', (data) => {
                    //console.log('[Chat] Nova mensagem:', data.id);
                    
                    // Encontra o componente Livewire e envia o evento
                    const component = window.Livewire.find(componentId);
                    if (component) {
                        component.call('handleNewMessage', data);
                    }
                });
                
                // Listener para pins
                channel.listen('.ChatMessagePinned', (data) => {
                    //.log('[Chat] Pin alterado:', data.id, '- fixado:', data.is_fixed);
                    
                    // Encontra o componente Livewire e envia o evento
                    const component = window.Livewire.find(componentId);
                    if (component) {
                        component.call('handleMessagePinned', data);
                    }
                });
                
                // Salva referência do canal
                this.active.set(channelKey, { channel, componentId });
                //console.log('[Chat] Canal conectado:', channelKey);
                
            } catch (error) {
                //console.error('[Chat] Erro ao conectar canal:', error);
            }
        },
        
        // Desconecta de um canal
        disconnect(patientId, shift) {
            const channelKey = `chat.${patientId}.${shift}`;
            const channelData = this.active.get(channelKey);
            
            if (channelData) {
                window.Echo.leave(channelKey);
                this.active.delete(channelKey);
                //console.log('[Chat] Canal desconectado:', channelKey);
            }
        },
        
        // Desconecta todos os canais
        disconnectAll() {
            this.active.forEach((data, key) => {
                window.Echo.leave(key);
            });
            this.active.clear();
            //console.log('[Chat] Todos os canais desconectados');
        }
    };
    
    // Expõe função global para o Alpine usar
    window.connectChatChannel = (patientId, shift, componentId) => {
        window.ChatChannels.connect(patientId, shift, componentId);
    };
    
    window.disconnectChatChannel = (patientId, shift) => {
        window.ChatChannels.disconnect(patientId, shift);
    };
    
    // Evento para sinalizar que o sistema está pronto
    window.dispatchEvent(new CustomEvent('chat-echo-ready'));
});

// Livewire hooks para reconectar após navegação
document.addEventListener('livewire:navigated', () => {
    // Reconecta canais ativos se necessário
    window.ChatChannels?.active.forEach((data, key) => {
        //console.log('[Chat] Reconectando canal após navegação:', key);
    });
});