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

// Mobile-specific optimizations
const mobileOptimizations = {
    // Prevent iOS Safari zoom on input focus
    preventZoom: function() {
        const viewportMeta = document.querySelector('meta[name="viewport"]');
        if (viewportMeta) {
            const originalContent = viewportMeta.getAttribute('content');
            
            document.addEventListener('focusin', (e) => {
                if (e.target.matches('input, textarea, select')) {
                    viewportMeta.setAttribute('content', originalContent + ', user-scalable=no');
                }
            });
            
            document.addEventListener('focusout', (e) => {
                if (e.target.matches('input, textarea, select')) {
                    viewportMeta.setAttribute('content', originalContent);
                }
            });
        }
    },
    
    // Improve touch scrolling
    improveScrolling: function() {
        const scrollableElements = document.querySelectorAll('.overscroll-y-contain, .chat-container');
        scrollableElements.forEach(element => {
            element.style.webkitOverflowScrolling = 'touch';
            element.style.overscrollBehavior = 'contain';
        });
    },
    
    // Handle keyboard on mobile
    handleVirtualKeyboard: function() {
        let initialViewportHeight = window.innerHeight;
        
        window.addEventListener('resize', () => {
            const currentHeight = window.innerHeight;
            const heightDifference = initialViewportHeight - currentHeight;
            
            // Keyboard is likely open if height difference is significant
            if (heightDifference > 150) {
                document.body.classList.add('keyboard-open');
            } else {
                document.body.classList.remove('keyboard-open');
                initialViewportHeight = currentHeight;
            }
        });
    },
    
    // Improve mobile modal behavior
    optimizeModalForMobile: function() {
        // Prevent zoom on double tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Handle safe area insets for devices with notches
        if (CSS.supports('padding-top: env(safe-area-inset-top)')) {
            document.documentElement.style.setProperty('--safe-area-top', 'env(safe-area-inset-top)');
            document.documentElement.style.setProperty('--safe-area-bottom', 'env(safe-area-inset-bottom)');
        }
    },

    // Handle device orientation changes
    handleOrientationChange: function() {
        window.addEventListener('orientationchange', () => {
            // Force layout recalculation after orientation change
            setTimeout(() => {
                window.scrollTo(0, 0);
                const modalContainer = document.querySelector('[data-patient-id]');
                if (modalContainer) {
                    modalContainer.style.height = '100vh';
                    setTimeout(() => {
                        modalContainer.style.height = '';
                    }, 100);
                }
            }, 100);
        });
    },

    // Optimize scroll performance
    optimizeScrollPerformance: function() {
        const scrollableElements = document.querySelectorAll('.overscroll-y-contain');
        scrollableElements.forEach(element => {
            // Use passive listeners for better performance
            element.addEventListener('touchstart', (e) => {
                element.style.pointerEvents = 'auto';
            }, { passive: true });

            element.addEventListener('touchend', (e) => {
                // Re-enable smooth scrolling after touch
                setTimeout(() => {
                    element.style.scrollBehavior = 'smooth';
                }, 100);
            }, { passive: true });
        });
    },

    // Optimize modal dimensions and positioning - CORRIGIDO
    optimizeModalLayout: function() {
        const updateModalSize = () => {
            const modals = document.querySelectorAll('[data-patient-id]');
            modals.forEach(modal => {
                // Remover qualquer padding/margin que estava comprimindo
                modal.style.marginLeft = 'auto';
                modal.style.marginRight = 'auto';
                modal.style.paddingLeft = '0';
                modal.style.paddingRight = '0';
                
                if (window.innerWidth <= 640) {
                    // Mobile: fullscreen completo
                    modal.style.width = '100vw';
                    modal.style.height = '100vh';
                    modal.style.maxWidth = 'none';
                    modal.style.maxHeight = 'none';
                    modal.style.borderRadius = '0';
                    modal.style.margin = '0';
                } else {
                    // Tablet e desktop: 90% da largura (5% de margem de cada lado)
                    modal.style.width = '90vw';
                    modal.style.height = '90vh';
                    modal.style.maxWidth = window.innerWidth >= 1024 ? 'min(90vw, 1400px)' : '90vw';
                    modal.style.maxHeight = '90vh';
                    modal.style.borderRadius = '1rem';
                    modal.style.margin = 'auto';
                }
            });
            
            // Ajustar o container pai se necessário
            const modalContainers = document.querySelectorAll('.fixed.inset-0.z-50');
            modalContainers.forEach(container => {
                if (window.innerWidth <= 640) {
                    container.style.padding = '0';
                } else {
                    container.style.padding = '5vh 5vw';
                }
            });
        };

        // Initial setup
        updateModalSize();

        // Update on resize and orientation change
        window.addEventListener('resize', updateModalSize);
        window.addEventListener('orientationchange', () => {
            setTimeout(updateModalSize, 100);
        });

        return updateModalSize;
    },

    // Enhanced touch handling for better swipe detection
    enhanceTouchHandling: function() {
        let touchStartX = null;
        let touchStartY = null;
        let touchStartTime = null;
        let isSwiping = false;

        document.addEventListener('touchstart', (e) => {
            const modal = e.target.closest('[data-patient-id]');
            if (!modal || window.innerWidth >= 768) return;

            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchStartTime = Date.now();
            isSwiping = false;
        }, { passive: true });

        document.addEventListener('touchmove', (e) => {
            if (!touchStartX || !touchStartY) return;

            const touchCurrentX = e.touches[0].clientX;
            const touchCurrentY = e.touches[0].clientY;
            const deltaX = touchCurrentX - touchStartX;
            const deltaY = touchCurrentY - touchStartY;

            // Detect horizontal swipe
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10) {
                isSwiping = true;
                // Prevent vertical scroll during horizontal swipe
                if (Math.abs(deltaX) > 20) {
                    e.preventDefault();
                }
            }
        }, { passive: false });

        document.addEventListener('touchend', (e) => {
            if (!touchStartX || !touchStartY || !isSwiping) {
                touchStartX = null;
                touchStartY = null;
                touchStartTime = null;
                isSwiping = false;
                return;
            }

            const touchEndX = e.changedTouches[0].clientX;
            const deltaX = touchEndX - touchStartX;
            const deltaTime = Date.now() - touchStartTime;
            const velocity = Math.abs(deltaX) / deltaTime;

            // Trigger swipe event if conditions are met
            if (Math.abs(deltaX) > 50 || velocity > 0.3) {
                const modal = e.target.closest('[data-patient-id]');
                if (modal) {
                    const swipeEvent = new CustomEvent('modal-swipe', {
                        detail: { 
                            direction: deltaX > 0 ? 'right' : 'left',
                            velocity: velocity,
                            distance: Math.abs(deltaX)
                        }
                    });
                    modal.dispatchEvent(swipeEvent);
                }
            }

            touchStartX = null;
            touchStartY = null;
            touchStartTime = null;
            isSwiping = false;
        }, { passive: true });
    },

    // Optimize viewport handling for mobile browsers
    optimizeViewport: function() {
        // Handle viewport height changes (mobile keyboard, etc.)
        let initialViewportHeight = window.innerHeight;
        
        const handleViewportChange = () => {
            const currentHeight = window.innerHeight;
            const heightDifference = initialViewportHeight - currentHeight;
            
            if (heightDifference > 150) {
                // Keyboard likely open
                document.body.classList.add('keyboard-open');
                document.documentElement.style.setProperty('--vh', `${currentHeight * 0.01}px`);
            } else {
                // Keyboard closed
                document.body.classList.remove('keyboard-open');
                document.documentElement.style.setProperty('--vh', `${currentHeight * 0.01}px`);
                initialViewportHeight = currentHeight;
            }
        };

        // Set initial viewport height
        document.documentElement.style.setProperty('--vh', `${initialViewportHeight * 0.01}px`);

        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('orientationchange', () => {
            setTimeout(handleViewportChange, 500);
        });
    },

    // Initialize all mobile features
    initMobileFeatures: function() {
        this.optimizeModalForMobile();
        this.handleOrientationChange();
        this.optimizeScrollPerformance();
        this.optimizeModalLayout();
        this.enhanceTouchHandling();
        this.optimizeViewport();
        
        // Add comprehensive mobile CSS - ATUALIZADO
        const mobileCSS = `
            :root {
                --vh: 1vh;
            }
            
            /* Correção específica para remover compressão lateral */
            .modal-backdrop-container {
                padding: 5vh 5vw !important;
            }
            
            @media (max-width: 640px) {
                .modal-backdrop-container {
                    padding: 0 !important;
                }
                
                /* Use CSS custom properties for dynamic viewport */
                .modal-mobile-fullscreen {
                    width: 100vw !important;
                    height: calc(var(--vh, 1vh) * 100) !important;
                    max-width: none !important;
                    max-height: none !important;
                    margin: 0 !important;
                    border-radius: 0 !important;
                }
                
                /* Improve touch scrolling */
                .mobile-scroll {
                    -webkit-overflow-scrolling: touch;
                    overscroll-behavior: contain;
                    scroll-snap-type: none;
                }
                
                /* Better button spacing */
                .touch-manipulation {
                    touch-action: manipulation;
                    min-height: 44px;
                    min-width: 44px;
                }
                
                /* Prevent horizontal scroll */
                body.modal-open {
                    overflow-x: hidden;
                    position: fixed;
                    width: 100%;
                    height: 100%;
                }
            }
            
            @media (min-width: 641px) {
                /* Tablet e Desktop: garantir 90% da largura */
                [data-patient-id] {
                    width: 90vw !important;
                    height: 90vh !important;
                    max-width: 90vw !important;
                    max-height: 90vh !important;
                    margin: auto !important;
                }
            }
            
            @media (min-width: 1024px) {
                /* Desktop grande: máximo de 1400px */
                [data-patient-id] {
                    max-width: min(90vw, 1400px) !important;
                }
            }
            
            @media (max-width: 640px) and (orientation: landscape) {
                /* Landscape mobile optimizations */
                .modal-mobile-fullscreen {
                    height: calc(var(--vh, 1vh) * 100) !important;
                }
            }
            
            /* Improved accessibility */
            @media (prefers-reduced-motion: reduce) {
                * {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }
        `;
        
        const styleElement = document.createElement('style');
        styleElement.textContent = mobileCSS;
        document.head.appendChild(styleElement);
    }
};

// Enhanced modal lifecycle manager
const modalLifecycleManager = {
    onModalOpen(patientId, shift) {
        console.log(`Modal opening for patient ${patientId}, shift ${shift}`);
        window.modalState.scrollingDisabled = true;
        window.modalState.currentPatientId = patientId;
        window.modalState.currentShift = shift;
        
        // Add modal-open class to body
        document.body.classList.add('modal-open');
        
        // Apply mobile optimizations
        if (window.innerWidth < 768) {
            mobileOptimizations.initMobileFeatures();
        }
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
        
        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        
        // Reset any mobile-specific styles
        document.documentElement.style.position = '';
        document.documentElement.style.width = '';
        document.documentElement.style.height = '';
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
    // Apply mobile optimizations on mobile devices
    if (window.innerWidth < 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        mobileOptimizations.preventZoom();
        mobileOptimizations.handleVirtualKeyboard();
        mobileOptimizations.initMobileFeatures();
    }
    
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
                // Use immediate scrolling on mobile for better performance
                const behavior = window.innerWidth < 768 ? 'auto' : 'smooth';
                container.scrollTo({
                    top: container.scrollHeight,
                    behavior: behavior
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

// Add CSS for keyboard handling
const mobileStyles = document.createElement('style');
mobileStyles.textContent = `
    @media (max-width: 767px) {
        .keyboard-open {
            height: 100vh;
            overflow: hidden;
        }
        
        .keyboard-open .chat-container {
            height: calc(100vh - 200px);
        }
        
        /* Improve touch targets */
        button, .clickable {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* Better focus styles for mobile */
        input:focus, textarea:focus, select:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }
    }
`;
document.head.appendChild(mobileStyles);