// Global state management
window.modalState = {
    scrollingDisabled: false,
    currentPatientId: null,
    currentShift: null,
    echoChannel: null
};

// Global loading overlay management
window.modalLoading = {
    show: function() {
        const overlay = document.getElementById('modal-global-loading');
        if (overlay) {
            overlay.classList.remove('hidden');
            console.log('Global modal loading shown');
        }
    },
    
    hide: function() {
        const overlay = document.getElementById('modal-global-loading');
        if (overlay) {
            overlay.classList.add('hidden');
            console.log('Global modal loading hidden');
        }
    }
};

// Optimized Echo binding
window.bindChatEchoListeners = (patientId, shift, componentId) => {
    if (!window.Echo || !window.Livewire || !patientId || !shift) {
        console.warn('Echo binding failed: missing dependencies');
        return;
    }
    
    if (window.modalState.echoChannel) {
        try {
            window.Echo.leave(window.modalState.echoChannel);
            console.log('Cleaned up previous Echo channel');
        } catch (e) {
            console.warn('Echo cleanup warning:', e);
        }
    }
    
    const component = window.Livewire.find(componentId);
    if (!component) {
        console.warn('Livewire component not found:', componentId);
        return;
    }

    const channelName = `chat.${patientId}.${shift}`;
    window.modalState.echoChannel = channelName;
    
    try {
        window.Echo.channel(channelName)
            .listen('ChatMessageSent', event => {
                console.log('New chat message received');
                component.call('handleNewMessage', event);
            })
            .listen('ChatMessagePinned', event => {
                console.log('Chat message pinned');
                component.call('handleMessagePinned', event);
            });
            
        console.log(`Echo listeners bound to channel: ${channelName}`);
    } catch (e) {
        console.error('Echo binding failed:', e);
    }
};

// Simplified modal lifecycle manager
const modalLifecycleManager = {
    onModalOpen(patientId, shift) {
        console.log(`Modal opening for patient ${patientId}, shift ${shift}`);
        window.modalState.scrollingDisabled = true;
        window.modalState.currentPatientId = patientId;
        window.modalState.currentShift = shift;
    },
    
    onLoadingStarted(data) {
        console.log('Patient loading started:', data);
    },
    
    onDataLoaded(data) {
        console.log('Patient data loaded:', data);
        window.modalLoading.hide();
        
        if (data.patientId && data.shift) {
            setTimeout(() => {
                const chatComponent = document.querySelector('[wire\\:id*="chat"]');
                if (chatComponent) {
                    const componentId = chatComponent.getAttribute('wire:id');
                    window.bindChatEchoListeners(data.patientId, data.shift, componentId);
                }
            }, 500);
        }
    },
    
    onModalClose() {
        console.log('Modal closing');
        window.modalState.scrollingDisabled = false;
        window.cleanupChatEcho();
        window.modalLoading.hide();
        window.modalState.currentPatientId = null;
        window.modalState.currentShift = null;
    }
};

// Cleanup function
window.cleanupChatEcho = () => {
    if (window.Echo && window.modalState.echoChannel) {
        try {
            window.Echo.leave(window.modalState.echoChannel);
            window.modalState.echoChannel = null;
            console.log('Echo channel cleaned up');
        } catch (e) {
            console.warn('Echo cleanup warning:', e);
        }
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('livewire:init', () => {
        Livewire.on('openModal', () => {
            console.log('openModal event received');
            window.modalState.scrollingDisabled = true;
        });
        
        Livewire.on('closeModal', () => {
            console.log('closeModal event received');
            modalLifecycleManager.onModalClose();
        });

        Livewire.on('patient-loading-started', (data) => {
            console.log('patient-loading-started event received:', data);
            modalLifecycleManager.onLoadingStarted(data);
        });

        Livewire.on('patient-data-loaded', (data) => {
            console.log('patient-data-loaded event received:', data);
            modalLifecycleManager.onDataLoaded(data);
        });
    });

    // Show loading on patient card click
    document.addEventListener('click', function(e) {
        const patientCard = e.target.closest('.patient-card');
        if (patientCard) {
            console.log('Patient card clicked, showing global loading');
            window.modalLoading.show();
            
            setTimeout(() => {
                console.log('Safety timeout reached, hiding loading');
                window.modalLoading.hide();
            }, 5000);
        }
    }, true);

    // Clock update
    const updateClock = () => {
        const now = new Date().toLocaleTimeString('pt-BR', {
            hour: '2-digit', 
            minute: '2-digit'
        });
        
        const elements = document.querySelectorAll('#current-time-display, #input-time');
        elements.forEach(el => {
            if (el && el.textContent !== now) {
                el.textContent = now;
            }
        });
    };

    updateClock();
    setInterval(updateClock, 30000);

    // Auto scroll
    const triggerAutoScroll = () => {
        if (window.modalState.scrollingDisabled) return;
        
        const container = document.getElementById('messages-container');
        if (container) {
            const isUserScrolledUp = container.scrollTop < (container.scrollHeight - container.clientHeight - 100);
            
            if (!isUserScrolledUp) {
                container.scrollTo({
                    top: container.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }
    };

    window.addEventListener('scroll-to-bottom', triggerAutoScroll);

    // Textarea auto-resize
    document.addEventListener('input', (e) => {
        if (e.target.matches('textarea[wire\\:model\\.defer="newMessage"]')) {
            const textarea = e.target;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }
    });

    // Keyboard shortcuts
    const insertFormatting = (textarea, before, after) => {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        const replacement = before + selectedText + after;
        
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + before.length, start + before.length + selectedText.length);
        
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const insertBulletPoint = (textarea) => {
        const start = textarea.selectionStart;
        const beforeCursor = textarea.value.substring(0, start);
        const afterCursor = textarea.value.substring(start);
        
        const isStartOfLine = start === 0 || beforeCursor.endsWith('\n');
        const bullet = isStartOfLine ? '- ' : '\n- ';
        
        textarea.value = beforeCursor + bullet + afterCursor;
        textarea.focus();
        textarea.setSelectionRange(start + bullet.length, start + bullet.length);
        
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    document.addEventListener('keydown', (e) => {
        const textarea = document.querySelector('textarea[wire\\:model\\.defer="newMessage"]');
        if (!textarea || e.target !== textarea) return;

        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            insertFormatting(textarea, '**', '**');
        } else if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
            e.preventDefault();
            insertFormatting(textarea, '*', '*');
        } else if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
            e.preventDefault();
            insertBulletPoint(textarea);
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        window.modalLoading.hide();
        window.cleanupChatEcho();
    });
});