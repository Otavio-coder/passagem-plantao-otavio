// chat-component-global.js — Chat contínuo com edição e reações

window.chatComponent = function() {
    return {
        messageText: '',
        echoReady: false,
        pinnedMinimized: false,

        // Edit state
        editingId: null,
        editText: '',

        // Loading states
        loadingStates: {
            sending: false,
            pinning: new Set(),
        },

        initialize() {
            const patientId = this.$wire.get('patientId');
            if (!patientId) return;

            this.$wire.initialize().then(() => {
                this.$nextTick(() => {
                    this.scrollToBottom();
                    setTimeout(() => this.scrollToBottom(), 400);
                });
            });

            // Connect to Echo channel
            const connectToEcho = () => {
                const componentId = this.$wire.__instance.id;

                if (window.connectChatChannel) {
                    window.connectChatChannel(patientId, componentId);
                    this.echoReady = true;
                }
            };

            if (window.ChatChannels) {
                connectToEcho();
            } else {
                window.addEventListener('chat-echo-ready', connectToEcho, { once: true });
            }

            this.$nextTick(() => this.scrollToBottom());
        },

        // ── Send message ──────────────────────────────────────────────────────

        async sendMessage() {
            const message = this.messageText.trim();
            if (!message || this.loadingStates.sending) return;

            this.loadingStates.sending = true;
            const backup = this.messageText;
            this.messageText = '';
            this.autoResize();

            try {
                const response = await this.$wire.sendMessage(message);
                if (response?.success) {
                    this.scrollToBottom();
                } else {
                    this.messageText = backup;
                    this.showErrorToast(response?.error || 'Erro ao enviar mensagem');
                }
            } catch (error) {
                this.messageText = backup;
                this.showErrorToast('Erro de conexão. Tente novamente.');
                console.error('[Chat] Erro ao enviar:', error);
            } finally {
                this.loadingStates.sending = false;
            }
        },

        // ── Pin message ───────────────────────────────────────────────────────

        async togglePin(messageId) {
            if (!messageId || this.loadingStates.pinning.has(messageId)) return;

            this.loadingStates.pinning.add(messageId);

            try {
                const response = await this.$wire.toggleMessagePin(messageId);
                if (response?.success) {
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

        // ── Edit message ──────────────────────────────────────────────────────

        startEdit(messageId, currentText) {
            this.editingId = messageId;
            this.editText  = currentText;
            this.$nextTick(() => {
                const ta = document.getElementById('edit-textarea-' + messageId);
                if (ta) {
                    ta.focus();
                    ta.setSelectionRange(ta.value.length, ta.value.length);
                }
            });
        },

        cancelEdit() {
            this.editingId = null;
            this.editText  = '';
        },

        isEditing(messageId) {
            return this.editingId === messageId;
        },

        async saveEdit(messageId) {
            const content = this.editText.trim();
            if (!content) return;

            // Optimistically close edit form
            this.editingId = null;
            this.editText  = '';

            try {
                const response = await this.$wire.editMessage(messageId, content);
                if (!response?.success) {
                    this.showErrorToast(response?.error || 'Erro ao editar mensagem');
                }
            } catch (error) {
                this.showErrorToast('Erro ao editar mensagem');
                console.error('[Chat] Erro ao editar:', error);
            }
        },

        /** Returns true if the message is still within the 6-hour edit window. */
        canEditMessage(dtCreacaoRaw) {
            if (!dtCreacaoRaw) return false;
            const created  = new Date(dtCreacaoRaw);
            const diffHours = (Date.now() - created.getTime()) / (1000 * 60 * 60);
            return diffHours < 6;
        },

        // ── Reactions ─────────────────────────────────────────────────────────

        async toggleReaction(messageId) {
            if (!messageId) return;
            try {
                await this.$wire.toggleReaction(messageId);
            } catch (error) {
                this.showErrorToast('Erro ao reagir');
                console.error('[Chat] Erro ao reagir:', error);
            }
        },

        // ── UI helpers ────────────────────────────────────────────────────────

        togglePinnedMinimized() {
            this.pinnedMinimized = !this.pinnedMinimized;
            try {
                localStorage.setItem('chat_pinned_minimized', this.pinnedMinimized);
            } catch (e) { /* ignore */ }
        },

        async refreshChat() {
            try {
                await this.$wire.refreshCurrentMessages();
                this.scrollToBottom();
            } catch (error) {
                this.showErrorToast('Erro ao atualizar chat');
            }
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = document.getElementById('messages-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        autoResize() {
            const textarea = this.$refs.textarea;
            if (textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 160) + 'px';
            }
        },

        // ── Loading state helpers ─────────────────────────────────────────────

        isSendingMessage() {
            return this.loadingStates.sending || this.$wire.sendingMessage;
        },

        isPinning(messageId) {
            return this.loadingStates.pinning.has(messageId);
        },

        // ── Toast notifications ───────────────────────────────────────────────

        showSuccessToast(message) { this.showToast(message, 'success'); },
        showErrorToast(message)   { this.showToast(message, 'error');   },

        showToast(message, type = 'info') {
            const bgColor = type === 'error'   ? 'bg-red-100 text-red-800 border-red-200'     :
                            type === 'success' ? 'bg-green-100 text-green-800 border-green-200' :
                                                 'bg-blue-100 text-blue-800 border-blue-200';

            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 ${bgColor} px-4 py-2 rounded-lg shadow-lg border z-50 text-sm font-medium transition-all duration-300`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },
    }
}
