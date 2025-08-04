// Global flag to control auto-scroll
window.modalScrollingDisabled = false;

window.bindChatEchoListeners = (patientId, shift, componentId) => {
    if (!window.Echo || !window.Livewire || !patientId || !shift) {
        console.warn('📡 Echo not available or missing parameters:', { patientId, shift, componentId });
        return;
    }
    
    const component = window.Livewire.find(componentId);
    if (!component) {
        console.warn('💬 Chat component not found:', componentId);
        return;
    }

    const channelName = `chat.${patientId}.${shift}`;
    
    // Leave existing channel to avoid duplicates
    try {
        window.Echo.leave(channelName);
    } catch (e) {
        // Channel may not exist, ignore
    }
    
    // Subscribe to new channel with optimized error handling
    try {
        const channel = window.Echo.channel(channelName);
        
        channel
            .listen('ChatMessageSent', event => {
                console.log('📨 Message received via Echo:', event);
                component.call('handleNewMessage', event);
            })
            .listen('ChatMessagePinned', event => {
                console.log('📌 Message pinned via Echo:', event);
                component.call('handleMessagePinned', event);
            })
            .error(error => {
                console.error('❌ Echo channel error:', error);
            });
            
        console.log('✅ Echo listeners bound for channel:', channelName);
        
    } catch (e) {
        console.error('❌ Error binding Echo listeners:', e);
    }
};

// Global cleanup function for Echo channels
window.cleanupChatEcho = (patientId, shift) => {
    if (window.Echo && patientId && shift) {
        try {
            const channelName = `chat.${patientId}.${shift}`;
            window.Echo.leave(channelName);
            console.log('🧹 Cleaned up Echo channel:', channelName);
        } catch (e) {
            console.warn('Channel cleanup failed:', e);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // FIXED: Disable auto-scroll when modal is open
    document.addEventListener('livewire:init', () => {
        Livewire.on('openPatientModal', () => {
            window.modalScrollingDisabled = true;
            console.log('🚫 Auto-scroll disabled - Modal opened');
        });
        
        Livewire.on('closePatientModal', () => {
            window.modalScrollingDisabled = false;
            console.log('✅ Auto-scroll enabled - Modal closed');
        });
    });

    // Enhanced clock update with better performance
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
    setInterval(updateClock, 60000);

    // Enhanced auto-scroll with smooth animation
    let scrollTimeout;
    window.addEventListener('scroll-to-bottom', () => {
        const container = document.getElementById('messages-container');
        if (container) {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                const isUserScrolledUp = container.scrollTop < (container.scrollHeight - container.clientHeight - 100);
                
                if (!isUserScrolledUp) {
                    requestAnimationFrame(() => {
                        container.scrollTo({
                            top: container.scrollHeight,
                            behavior: 'smooth'
                        });
                    });
                }
            }, 100);
        }
    });

    // Enhanced message input experience
    document.addEventListener('input', (e) => {
        if (e.target.matches('textarea[wire\\:model\\.defer="newMessage"]')) {
            const textarea = e.target;
            
            // Auto-resize textarea
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            
            // Show typing indicator (if implemented)
            if (textarea.value.length > 0) {
                textarea.classList.add('typing');
            } else {
                textarea.classList.remove('typing');
            }
        }
    });

    // Enhanced keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        const textarea = document.querySelector('textarea[wire\\:model\\.defer="newMessage"]');
        if (!textarea || e.target !== textarea) return;

        // Ctrl/Cmd + B for bold
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            insertFormatting(textarea, '**', '**');
        }
        
        // Ctrl/Cmd + I for italic
        if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
            e.preventDefault();
            insertFormatting(textarea, '*', '*');
        }
        
        // Ctrl/Cmd + L for bullet point
        if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
            e.preventDefault();
            insertBulletPoint(textarea);
        }
    });

    // Cleanup Echo channels when page unloads
    window.addEventListener('beforeunload', () => {
        if (window.Echo) {
            const modalElement = document.querySelector('[data-patient-id]');
            if (modalElement) {
                const patientId = modalElement.getAttribute('data-patient-id');
                const shift = modalElement.getAttribute('data-shift');
                if (patientId && shift) {
                    window.cleanupChatEcho(patientId, shift);
                }
            }
        }
    });

    // Enhanced message animations
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && node.id && node.id.startsWith('msg-')) {
                    node.style.transform = 'translateY(20px)';
                    node.style.opacity = '0';
                    
                    requestAnimationFrame(() => {
                        node.style.transition = 'all 0.3s ease-out';
                        node.style.transform = 'translateY(0)';
                        node.style.opacity = '1';
                    });
                }
            });
        });
    });

    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        observer.observe(messagesContainer, { childList: true, subtree: true });
    }
});

// Helper functions for text formatting
function insertFormatting(textarea, before, after) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    const replacement = before + selectedText + after;
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    textarea.focus();
    textarea.setSelectionRange(start + before.length, start + before.length + selectedText.length);
    
    // Trigger input event to update Livewire
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function insertBulletPoint(textarea) {
    const start = textarea.selectionStart;
    const beforeCursor = textarea.value.substring(0, start);
    const afterCursor = textarea.value.substring(start);
    
    const isStartOfLine = start === 0 || beforeCursor.endsWith('\n');
    const bullet = isStartOfLine ? '- ' : '\n- ';
    
    textarea.value = beforeCursor + bullet + afterCursor;
    textarea.focus();
    textarea.setSelectionRange(start + bullet.length, start + bullet.length);
    
    // Trigger input event to update Livewire
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

// Enhanced auto-scroll control for SBAR report
window.disableAutoScrollWhenModalOpen = () => {
    return window.modalScrollingDisabled;
};