import './bootstrap';

// Compatibilidade com sistema de abas
document.addEventListener('alpine:init', () => {
    // Observer para quando a aba do chat se torna visível
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                const target = mutation.target;
                
                // Se é uma div de aba do chat que ficou visível
                if (target.matches('[x-show*="tab-a"]') && 
                    !target.style.display.includes('none') && 
                    target.querySelector('[wire\\:key*="chat-"]')) {
                    
                    // Aguarda um tick para garantir que o Livewire foi inicializado
                    setTimeout(() => {
                        // Debug para verificar se os listeners estão funcionando
                        if (window.debugChatEcho) {
                            console.log('[Tab] Aba do chat ativada, verificando listeners...');
                            window.debugChatEcho();
                        }
                        
                        // 🔧 NOVO: Verificar se Echo está conectado quando aba ativa
                        if (window.Echo && window.Echo.connector.pusher.connection.state === 'connected') {
                            console.log('[Tab] ✅ Echo já conectado na ativação da aba');
                        } else {
                            console.log('[Tab] ⏳ Echo não conectado, aguardando...');
                            window.addEventListener('echo-connected', () => {
                                console.log('[Tab] ✅ Echo conectou após ativação da aba');
                                if (window.debugChatEcho) {
                                    window.debugChatEcho();
                                }
                            }, { once: true });
                        }
                    }, 100);
                }
            }
        });
    });
    
    // Observa mudanças no DOM
    observer.observe(document.body, {
        attributes: true,
        subtree: true,
        attributeFilter: ['style']
    });
});