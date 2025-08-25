// chat-alpine-improved.js - Componente Alpine com melhorias UX

window.chatComponent = function() {
    return {
        messageText: '',
        showFormatHelp: false,
        echoReady: false,
        pinnedMinimized: false,
        
        // História com paginação (controla navegação entre páginas de datas)
        historyPage: 0,
        totalHistoryPages: 1,
        
        // Estados de loading separados
        loadingStates: {
            sending: false,
            pinning: new Set(), // IDs das mensagens sendo processadas
            history: false, // Estado local para histórico
        },
        
        initialize() {
            const patientId = this.$wire.get('patientId');
            if (!patientId) return;
            
            // Inicializa o componente Livewire
            this.$wire.initialize().then(() => {
                // Sincroniza dados de paginação após inicialização
                this.totalHistoryPages = this.$wire.get('totalHistoryPages') || 1;
                this.historyPage = this.$wire.get('currentHistoryPage') || 0;
                
                console.log('[Chat Alpine] Paginação inicializada:', {
                    page: this.historyPage,
                    total: this.totalHistoryPages
                });
            });
            
            // Conecta ao canal Echo quando estiver pronto
            const connectToEcho = () => {
                const shift = this.$wire.get('currentShift');
                const componentId = this.$wire.__instance.id;
                
                if (window.connectChatChannel) {
                    window.connectChatChannel(patientId, shift, componentId);
                    this.echoReady = true;
                    console.log('[Chat Alpine] Conectado ao Echo');
                }
            };
            
            // Se o Echo já estiver pronto, conecta imediatamente
            if (window.ChatChannels) {
                connectToEcho();
            } else {
                // Senão, aguarda o evento de pronto
                window.addEventListener('chat-echo-ready', connectToEcho, { once: true });
            }
            
            // Scroll inicial para o final
            this.$nextTick(() => this.scrollToBottom());
        },

        // Função melhorada para envio de mensagem
        async sendMessage() {
            const message = this.messageText.trim();
            if (!message || this.loadingStates.sending) return;

            // Estado local de envio
            this.loadingStates.sending = true;
            const originalMessage = this.messageText;
            this.messageText = '';
            this.autoResize();

            try {
                const response = await this.$wire.sendMessage(message);
                
                if (response?.success) {
                    // Sucesso - mensagem já foi adicionada localmente pelo componente
                    this.scrollToBottom();
                } else {
                    // Erro - restaura mensagem
                    this.messageText = originalMessage;
                    this.showErrorToast('Erro ao enviar mensagem');
                }
            } catch (error) {
                // Erro de rede - restaura mensagem  
                this.messageText = originalMessage;
                this.showErrorToast('Erro de conexão. Tente novamente.');
                console.error('[Chat] Erro ao enviar:', error);
            } finally {
                this.loadingStates.sending = false;
            }
        },
        
        // Função melhorada para toggle pin
        async togglePin(messageId) {
            if (!messageId || this.loadingStates.pinning.has(messageId)) return;
            
            this.loadingStates.pinning.add(messageId);
            
            try {
                const response = await this.$wire.toggleMessagePin(messageId);
                
                if (response?.success) {
                    // Sucesso - estado já foi atualizado localmente pelo componente
                    this.showSuccessToast('Fixação atualizada');
                } else {
                    this.showErrorToast('Erro ao fixar mensagem');
                }
            } catch (error) {
                this.showErrorToast('Erro ao fixar mensagem');
                console.error('[Chat] Erro ao fixar:', error);
            } finally {
                this.loadingStates.pinning.delete(messageId);
            }
        },

        // Funções para paginação do histórico
        previousHistoryPage() {
            if (this.historyPage > 0 && !this.loadingStates.history) {
                this.historyPage--;
                this.loadHistoryPage();
            }
        },

        nextHistoryPage() {
            if (this.historyPage < this.totalHistoryPages - 1 && !this.loadingStates.history) {
                this.historyPage++;
                this.loadHistoryPage();
            }
        },

        async loadHistoryPage() {
            if (this.loadingStates.history) return;
            
            this.loadingStates.history = true;
            this.$wire.selectedSession = ''; // Limpa seleção atual
            
            try {
                await this.$wire.loadHistoryPage(this.historyPage);
                // Atualiza total de páginas baseado na resposta do servidor
                this.totalHistoryPages = this.$wire.get('totalHistoryPages') || 1;
            } catch (error) {
                this.showErrorToast('Erro ao carregar página do histórico');
                console.error('[Chat] Erro na paginação:', error);
            } finally {
                this.loadingStates.history = false;
            }
        },

        // Função melhorada para carregar histórico
        async loadSessionHistory() {
            if (!this.$wire.selectedSession || this.loadingStates.history) return;
            
            this.loadingStates.history = true;
            
            try {
                await this.$wire.loadSessionHistory();
                this.scrollToBottom();
            } catch (error) {
                this.showErrorToast('Erro ao carregar histórico');
                console.error('[Chat] Erro no histórico:', error);
            } finally {
                this.loadingStates.history = false;
            }
        },

        // Função para voltar ao turno atual  
        async returnToCurrentShift() {
            if (this.loadingStates.history) return;
            
            this.loadingStates.history = true;
            
            try {
                await this.$wire.returnToCurrentShift();
                this.scrollToBottom();
            } catch (error) {
                this.showErrorToast('Erro ao retornar');
                console.error('[Chat] Erro ao retornar:', error);
            } finally {
                this.loadingStates.history = false;
            }
        },

        // Função para limpar seleção de sessão
        async clearSessionSelection() {
            if (this.loadingStates.history) return;
            
            try {
                this.$wire.selectedSession = '';
                await this.$wire.clearSessionSelection();
            } catch (error) {
                console.error('[Chat] Erro ao limpar seleção:', error);
            }
        },

        // Toggle da mensagem fixada minimizada
        togglePinnedMinimized() {
            this.pinnedMinimized = !this.pinnedMinimized;
            
            // Salva preferência no localStorage se disponível
            try {
                localStorage.setItem('chat_pinned_minimized', this.pinnedMinimized);
            } catch (e) {
                // Ignora erros de localStorage
            }
        },

        // Função para atualizar chat
        async refreshChat() {
            try {
                await this.$wire.refreshCurrentMessages();
                this.scrollToBottom();
            } catch (error) {
                this.showErrorToast('Erro ao atualizar chat');
                console.error('[Chat] Erro ao atualizar:', error);
            }
        },
        
        // Scroll para o final otimizado
        scrollToBottom() {
            this.$nextTick(() => {
                const container = document.getElementById('messages-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },
        
        // Auto resize do textarea
        autoResize() {
            const textarea = this.$refs.textarea;
            if (textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            }
        },
        
        // Toggle da ajuda de formatação
        toggleFormatHelp() {
            this.showFormatHelp = !this.showFormatHelp;
        },

        // Toasts para feedback visual
        showSuccessToast(message) {
            this.showToast(message, 'success');
        },

        showErrorToast(message) {
            this.showToast(message, 'error');
        },

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            const bgColor = type === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 
                           type === 'success' ? 'bg-green-100 text-green-800 border-green-200' :
                           'bg-blue-100 text-blue-800 border-blue-200';
            
            toast.className = `fixed top-4 right-4 ${bgColor} px-4 py-2 rounded-lg shadow-lg border z-50 text-sm font-medium`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            // Remove após 3 segundos
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },
        
        // Estados de loading simplificados
        isSendingMessage() { 
            return this.loadingStates.sending || this.$wire.sendingMessage; 
        },
        
        isLoadingHistory() { 
            return this.loadingStates.history || this.$wire.loadingHistory; 
        },
        
        isPinning(messageId) { 
            return this.loadingStates.pinning.has(messageId); 
        },

        // Função para retry de mensagem falhada (se necessário)
        async retryMessage(messageId) {
            try {
                // Implementar lógica de retry se necessário
                console.log('[Chat] Retry message:', messageId);
            } catch (error) {
                this.showErrorToast('Erro ao reenviar mensagem');
            }
        }
    }
}