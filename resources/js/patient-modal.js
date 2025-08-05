// Global state management
window.modalState = {
    scrollingDisabled: false,
    currentPatientId: null,
    currentShift: null,
    echoChannel: null,
    loadingComplete: false
};

window.modalLoadingManager = {
    stages: ['initial', 'fetching', 'processing', 'alerts', 'rendering', 'complete'],
    currentStage: 'initial',
    
    updateStage(stage) {
        this.currentStage = stage;
        this.notifyStageChange(stage);
    },
    
    notifyStageChange(stage) {
        // Dispatch custom event for stage changes
        window.dispatchEvent(new CustomEvent('modal-loading-stage-changed', {
            detail: { stage, timestamp: Date.now() }
        }));
        
        console.log(`Modal loading stage: ${stage}`);
    },
    
    isComplete() {
        return this.currentStage === 'complete';
    }
};



// Optimized Echo binding with connection pooling
window.bindChatEchoListeners = (patientId, shift, componentId) => {
    if (!window.Echo || !window.Livewire || !patientId || !shift) {
        console.warn('Echo binding failed: missing dependencies');
        return;
    }
    
    // Cleanup existing channel first
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

// Optimized cleanup
const modalLifecycleManager = {
    onModalOpen(patientId, shift) {
        console.log(`Modal opening for patient ${patientId}, shift ${shift}`);
        window.modalState.scrollingDisabled = true;
        window.modalState.currentPatientId = patientId;
        window.modalState.currentShift = shift;
        window.modalState.loadingComplete = false;
        
        window.modalLoadingManager.updateStage('fetching');
    },
    
    onDataLoaded(data) {
        console.log('Patient data loaded:', data);
        window.modalLoadingManager.updateStage('complete');
        window.modalState.loadingComplete = true;
        
        // Initialize chat listeners after data is loaded
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
        window.modalState.loadingComplete = false;
        window.modalLoadingManager.updateStage('initial');
        window.cleanupChatEcho();
        
        // Reset patient state
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

// Throttled clock update
let clockUpdateTimeout;
const updateClock = () => {
    clearTimeout(clockUpdateTimeout);
    clockUpdateTimeout = setTimeout(() => {
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
    }, 100);
};

// Optimized auto-scroll with performance throttling
let scrollAnimationFrame;
const triggerAutoScroll = () => {
    if (window.modalState.scrollingDisabled) return;
    
    if (scrollAnimationFrame) {
        cancelAnimationFrame(scrollAnimationFrame);
    }
    
    scrollAnimationFrame = requestAnimationFrame(() => {
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
    });
};

// Optimized text formatting
const insertFormatting = (textarea, before, after) => {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const replacement = before + selectedText + after;
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    textarea.focus();
    textarea.setSelectionRange(start + before.length, start + before.length + selectedText.length);
    
    // Trigger Livewire update
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

// Initialize with performance optimizations
document.addEventListener('DOMContentLoaded', () => {
    // Livewire event listeners
    document.addEventListener('livewire:init', () => {
        Livewire.on('openPatientModal', () => {
            window.modalState.scrollingDisabled = true;
        });
        
        Livewire.on('closePatientModal', () => {
            window.modalState.scrollingDisabled = false;
            window.cleanupChatEcho();
        });
    });

    // Clock update - reduce frequency
    updateClock();
    setInterval(updateClock, 30000); // Every 30 seconds instead of every minute

    // Scroll events
    window.addEventListener('scroll-to-bottom', triggerAutoScroll);

    // Optimized textarea handling with debouncing
    let textareaTimeout;
    document.addEventListener('input', (e) => {
        if (e.target.matches('textarea[wire\\:model\\.defer="newMessage"]')) {
            clearTimeout(textareaTimeout);
            textareaTimeout = setTimeout(() => {
                const textarea = e.target;
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            }, 50);
        }
    });

    // Keyboard shortcuts
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
    window.addEventListener('beforeunload', window.cleanupChatEcho);

    // Optimized message animations with Intersection Observer
    const messageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.target.id && entry.target.id.startsWith('msg-')) {
                entry.target.style.transform = 'translateY(0)';
                entry.target.style.opacity = '1';
                messageObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    // Observe new messages
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1 && node.id && node.id.startsWith('msg-')) {
                        node.style.transform = 'translateY(20px)';
                        node.style.opacity = '0';
                        node.style.transition = 'all 0.3s ease-out';
                        messageObserver.observe(node);
                    }
                });
            });
        }).observe(messagesContainer, { childList: true, subtree: true });
    }
});