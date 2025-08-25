/**
 * Patient Modal Management System
 * Mobile-First Responsive Design with Fullscreen Support
 * Optimized for devices < 640px width
 *
 * All chat-related logic has been removed.
 */

class PatientModalManager {
    constructor() {
        this.state = {
            scrollingDisabled: false,
            currentPatientId: null,
            currentShift: null,
            isInitialized: false,
            modalActive: false
        };
        this.device = {
            type: 'desktop',
            isMobile: false,
            isTablet: false,
            isDesktop: true,
            orientation: 'portrait',
            viewportHeight: window.innerHeight,
            safeAreaTop: 0,
            safeAreaBottom: 0
        };
        this.touch = {
            startX: null,
            startY: null,
            startTime: null,
            isSwipeEnabled: false,
            threshold: 50,
            velocityThreshold: 0.3,
            isScrolling: false,
            scrollElement: null
        };
        this.init();
    }

    // --- Initialization ---
    init() {
        if (this.state.isInitialized) return;
        this.detectDevice();
        this.setupViewport();
        this.setupEventListeners();
        this.setupTouchHandlers();
        this.setupKeyboardHandlers();
        this.setupModalLifecycle();
        this.injectMobileStyles();
        this.state.isInitialized = true;
    }

    // --- Device Detection ---
    detectDevice() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        const isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        if (width < 640) {
            this.device.type = 'mobile';
            this.device.isMobile = true;
            this.device.isTablet = false;
            this.device.isDesktop = false;
            this.touch.isSwipeEnabled = true;
        } else if (width < 1024) {
            this.device.type = 'tablet';
            this.device.isMobile = false;
            this.device.isTablet = true;
            this.device.isDesktop = false;
            this.touch.isSwipeEnabled = true;
        } else {
            this.device.type = 'desktop';
            this.device.isMobile = false;
            this.device.isTablet = false;
            this.device.isDesktop = true;
            this.touch.isSwipeEnabled = false;
        }
        this.device.orientation = width > height ? 'landscape' : 'portrait';
        this.device.viewportHeight = height;
        if (this.device.isMobile && CSS.supports('padding-top', 'env(safe-area-inset-top)')) {
            this.calculateSafeArea();
        }
        window.dispatchEvent(new CustomEvent('device-detected', { detail: this.device }));
        this.updateCSSVariables();
    }

    calculateSafeArea() {
        const testEl = document.createElement('div');
        testEl.style.position = 'fixed';
        testEl.style.top = 'env(safe-area-inset-top)';
        testEl.style.bottom = 'env(safe-area-inset-bottom)';
        testEl.style.visibility = 'hidden';
        testEl.style.pointerEvents = 'none';
        document.body.appendChild(testEl);
        const rect = testEl.getBoundingClientRect();
        this.device.safeAreaTop = rect.top;
        this.device.safeAreaBottom = window.innerHeight - rect.bottom;
        document.body.removeChild(testEl);
    }

    // --- Viewport Handling ---
    setupViewport() {
        this.setViewportHeight();
        let viewportTimeout;
        const handleViewportChange = () => {
            clearTimeout(viewportTimeout);
            viewportTimeout = setTimeout(() => {
                this.setViewportHeight();
                this.detectDevice();
                this.handleViewportResize();
            }, 100);
        };
        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('orientationchange', () => {
            setTimeout(handleViewportChange, 300);
        });
        if ('visualViewport' in window) {
            window.visualViewport.addEventListener('resize', handleViewportChange);
        }
    }

    setViewportHeight() {
        const vh = window.innerHeight * 0.01;
        const dvh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
        document.documentElement.style.setProperty('--dvh', `${dvh}px`);
        this.device.viewportHeight = window.innerHeight;
    }

    updateCSSVariables() {
        const root = document.documentElement;
        root.style.setProperty('--modal-padding', this.device.isMobile ? '0' : '2rem');
        root.style.setProperty('--modal-radius', this.device.isMobile ? '0' : '1rem');
        root.style.setProperty('--scroll-width', this.device.isMobile ? '2px' : '6px');
        root.style.setProperty('--touch-target', this.device.isMobile ? '44px' : '32px');
        if (this.device.isMobile) {
            root.style.setProperty('--safe-area-top', `${this.device.safeAreaTop}px`);
            root.style.setProperty('--safe-area-bottom', `${this.device.safeAreaBottom}px`);
        }
    }

    // --- Event Listeners ---
    setupEventListeners() {
        document.addEventListener('livewire:init', () => {});
        document.addEventListener('click', this.handleModalClick.bind(this), true);
        window.addEventListener('beforeunload', () => { this.cleanup(); });
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.detectDevice();
                this.updateModalDimensions();
            }, 150);
        });
    }

    // --- Touch Handlers ---
    setupTouchHandlers() {
        if (!this.touch.isSwipeEnabled) return;
        document.addEventListener('touchstart', (e) => {
            const modal = e.target.closest('[data-patient-id]');
            if (!modal) return;
            const touch = e.touches[0];
            this.touch.startX = touch.clientX;
            this.touch.startY = touch.clientY;
            this.touch.startTime = Date.now();
            this.touch.isScrolling = false;
            this.touch.scrollElement = e.target.closest('.modal-tab-content, .scrollable, [data-scrollable]');
        }, { passive: true });

        document.addEventListener('touchmove', (e) => {
            if (!this.touch.startX || !this.touch.startY) return;
            const touch = e.touches[0];
            const deltaX = touch.clientX - this.touch.startX;
            const deltaY = touch.clientY - this.touch.startY;
            const absDeltaX = Math.abs(deltaX);
            const absDeltaY = Math.abs(deltaY);
            if (!this.touch.isScrolling && (absDeltaX > 10 || absDeltaY > 10)) {
                if (absDeltaY > absDeltaX + 20 && this.touch.scrollElement) {
                    this.touch.isScrolling = true;
                    return;
                } else if (absDeltaX > absDeltaY + 30 && absDeltaX > 40) {
                    this.touch.isScrolling = false;
                    e.preventDefault();
                }
            }
            if (!this.touch.isScrolling && absDeltaX > absDeltaY + 20 && absDeltaX > 30) {
                e.preventDefault();
            }
        }, { passive: false });

        document.addEventListener('touchend', (e) => {
            if (!this.touch.startX || !this.touch.startY || this.touch.isScrolling) {
                this.resetTouchState();
                return;
            }
            const touch = e.changedTouches[0];
            const deltaX = touch.clientX - this.touch.startX;
            const deltaY = touch.clientY - this.touch.startY;
            const deltaTime = Date.now() - this.touch.startTime;
            const velocity = Math.abs(deltaX) / deltaTime;
            if (Math.abs(deltaX) > Math.abs(deltaY) + 25 &&
                (Math.abs(deltaX) > this.touch.threshold || velocity > this.touch.velocityThreshold)) {
                const modal = e.target.closest('[data-patient-id]');
                if (modal) {
                    const direction = deltaX > 0 ? 'right' : 'left';
                    this.handleSwipe(modal, direction, velocity);
                }
            }
            this.resetTouchState();
        }, { passive: true });
    }

    resetTouchState() {
        this.touch.startX = null;
        this.touch.startY = null;
        this.touch.startTime = null;
        this.touch.isScrolling = false;
        this.touch.scrollElement = null;
    }

    handleSwipe(modal, direction, velocity) {
        if ('vibrate' in navigator && this.device.isMobile) {
            navigator.vibrate(10);
        }
        const swipeEvent = new CustomEvent('modal-swipe', {
            detail: { direction, velocity },
            bubbles: true
        });
        modal.dispatchEvent(swipeEvent);
    }

    // --- Keyboard Handlers ---
    setupKeyboardHandlers() {
        document.addEventListener('keydown', (e) => {
            const textarea = document.querySelector('textarea[wire\\:model\\.defer="newMessage"]');
            if (!textarea || e.target !== textarea) return;
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                this.insertFormatting(textarea, '**', '**');
            } else if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
                e.preventDefault();
                this.insertFormatting(textarea, '*', '*');
            } else if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
                e.preventDefault();
                this.insertBulletPoint(textarea);
            }
        });
        document.addEventListener('input', (e) => {
            if (e.target.matches('textarea[wire\\:model\\.defer="newMessage"]')) {
                const textarea = e.target;
                const maxHeight = this.device.isMobile ? 100 : 120;
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, maxHeight) + 'px';
            }
        });
        if (this.device.isMobile) {
            document.addEventListener('focusin', (e) => {
                if (e.target.matches('input, textarea, select')) {
                    e.target.style.fontSize = '16px';
                }
            });
        }
    }

    // --- Modal Lifecycle ---
    setupModalLifecycle() {
        this.startClockUpdate();
        window.addEventListener('scroll-to-bottom', this.triggerAutoScroll.bind(this));
        if (this.device.isMobile) {
            this.setupMobileKeyboardHandling();
        }
    }

    setupMobileKeyboardHandling() {
        let initialViewportHeight = window.innerHeight;
        const handleKeyboardToggle = () => {
            const currentHeight = window.innerHeight;
            const heightDifference = initialViewportHeight - currentHeight;
            if (heightDifference > 150) {
                document.body.classList.add('keyboard-open');
                this.setViewportHeight();
            } else {
                document.body.classList.remove('keyboard-open');
                initialViewportHeight = currentHeight;
                this.setViewportHeight();
            }
        };
        window.addEventListener('resize', handleKeyboardToggle);
        if ('visualViewport' in window) {
            window.visualViewport.addEventListener('resize', handleKeyboardToggle);
        }
    }

    injectMobileStyles() {
        const css = `
            /* Mobile-specific enhancements */
            @media (max-width: 640px) {
                /* Prevent text selection on UI elements */
                .modal-main-container {
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    user-select: none;
                    -webkit-touch-callout: none;
                }
                
                /* Re-enable text selection for content */
                .modal-tab-content p,
                .modal-tab-content span,
                .modal-tab-content div[class*="text"],
                .modal-tab-content input,
                .modal-tab-content textarea {
                    -webkit-user-select: text;
                    -moz-user-select: text;
                    user-select: text;
                }
                
                /* Optimize scrolling performance */
                .modal-tab-content {
                    -webkit-transform: translateZ(0);
                    transform: translateZ(0);
                    -webkit-backface-visibility: hidden;
                    backface-visibility: hidden;
                }
                
                /* Better tap targets */
                button, [role="button"], .clickable {
                    min-height: var(--touch-target, 44px);
                    min-width: var(--touch-target, 44px);
                }
                
                /* Keyboard handling */
                body.keyboard-open .modal-main-container {
                    height: 70vh;
                    height: 70dvh;
                }
                
                /* Landscape optimizations */
                @media (orientation: landscape) {
                    .mobile-nav-indicators {
                        bottom: 8px;
                    }
                    
                    .nav-indicator-container {
                        padding: 8px 12px;
                    }
                }
            }
            
            /* High DPI displays */
            @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
                .modal-tab-content::-webkit-scrollbar {
                    width: 1px;
                }
            }
        `;
        const styleElement = document.createElement('style');
        styleElement.id = 'patient-modal-mobile-enhancements';
        styleElement.textContent = css;
        document.head.appendChild(styleElement);
    }

    // --- Modal Event Handlers ---
    onModalOpen(data) {
        this.state.modalActive = true;
        this.state.scrollingDisabled = true;
        this.state.currentPatientId = data?.patientId;
        this.state.currentShift = data?.shift;
        document.body.classList.add('modal-active');
        if (this.device.isMobile) {
            document.documentElement.style.position = 'fixed';
            document.documentElement.style.width = '100%';
            document.documentElement.style.height = '100%';
            document.documentElement.style.top = '0';
            document.documentElement.style.left = '0';
            document.body.style.overflow = 'hidden';
        }
        this.updateModalDimensions();
    }

    onModalClose() {
        this.state.modalActive = false;
        this.state.scrollingDisabled = false;
        this.state.currentPatientId = null;
        this.state.currentShift = null;
        document.body.classList.remove('modal-active', 'keyboard-open');
        document.documentElement.style.position = '';
        document.documentElement.style.width = '';
        document.documentElement.style.height = '';
        document.documentElement.style.top = '';
        document.documentElement.style.left = '';
        document.body.style.overflow = '';
    }

    onLoadingStarted(data) {
        this.showLoading();
    }

    onDataLoaded(data) {
        this.hideLoading();
    }

    onHistoryLoading() {}
    onHistoryLoaded() {}

    // --- Utility Functions ---
    updateModalDimensions() {
        this.updateCSSVariables();
        if (this.device.isMobile) {
            setTimeout(() => { window.scrollTo(0, 0); }, 50);
        }
    }

    handleModalClick(e) {
        const patientCard = e.target.closest('.patient-card');
        if (patientCard) {
            this.showLoading();
            setTimeout(() => {
                if (!this.state.modalActive) {
                    this.hideLoading();
                }
            }, 5000);
        }
    }

    ensureLoadingOverlay() {
        let overlay = document.getElementById('modal-global-loading');
        if (!overlay) {
            overlay = document.querySelector('.modal-loading-overlay');
        }
        if (!overlay) {
            const modalContainer = document.querySelector('.modal-main-container');
            if (modalContainer) {
                overlay = document.createElement('div');
                overlay.id = 'modal-global-loading';
                overlay.className = 'modal-loading-overlay';
                overlay.innerHTML = `
                    <div class="modal-loading-content">
                        <div class="modal-loading-spinner">
                            <div class="spinner-primary"></div>
                            <div class="spinner-secondary"></div>
                        </div>
                        <span class="modal-loading-text">Carregando dados do paciente...</span>
                        <div class="modal-loading-dots">
                            <div class="dot dot-1"></div>
                            <div class="dot dot-2"></div>
                            <div class="dot dot-3"></div>
                        </div>
                    </div>
                `;
                modalContainer.appendChild(overlay);
            }
        }
        if (overlay) {
            overlay.style.display = 'flex';
            overlay.classList.remove('hidden');
        }
    }

    handleViewportResize() {
        if (!this.state.modalActive) return;
        if (this.device.isMobile) {
            setTimeout(() => {
                this.updateModalDimensions();
                window.scrollTo(0, 0);
            }, 100);
        }
    }

    showLoading() {
        document.body.style.overflow = 'hidden';
        document.body.classList.add('loading-active');
        let overlay = document.getElementById('modal-global-loading');
        if (!overlay) {
            overlay = document.querySelector('.modal-loading-overlay');
        }
        if (overlay) {
            overlay.style.display = 'flex';
            overlay.classList.remove('hidden');
        } else {
            this.createGlobalLoadingOverlay();
        }
    }

    hideLoading() {
        document.body.style.overflow = '';
        document.body.classList.remove('loading-active');
        let overlay = document.getElementById('modal-global-loading');
        if (!overlay) {
            overlay = document.querySelector('.modal-loading-overlay');
        }
        if (overlay) {
            overlay.style.display = 'none';
            overlay.classList.add('hidden');
        }
    }

    createGlobalLoadingOverlay() {
        const existingOverlay = document.getElementById('modal-global-loading');
        if (existingOverlay) {
            existingOverlay.remove();
        }
        const overlay = document.createElement('div');
        overlay.id = 'modal-global-loading';
        overlay.className = 'modal-loading-overlay';
        overlay.innerHTML = `
            <div class="modal-loading-content">
                <div class="modal-loading-spinner">
                    <div class="spinner-primary"></div>
                    <div class="spinner-secondary"></div>
                </div>
                <span class="modal-loading-text">Carregando dados do paciente...</span>
                <div class="modal-loading-dots">
                    <div class="dot dot-1"></div>
                    <div class="dot dot-2"></div>
                    <div class="dot dot-3"></div>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.style.display = 'flex';
        overlay.classList.remove('hidden');
        overlay.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); }, true);
        overlay.addEventListener('touchstart', (e) => { e.preventDefault(); e.stopPropagation(); }, { passive: false });
        overlay.addEventListener('touchmove', (e) => { e.preventDefault(); e.stopPropagation(); }, { passive: false });
        overlay.addEventListener('touchend', (e) => { e.preventDefault(); e.stopPropagation(); }, { passive: false });
        return overlay;
    }

    insertFormatting(textarea, before, after) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        const replacement = before + selectedText + after;
        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + before.length, start + before.length + selectedText.length);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    insertBulletPoint(textarea) {
        const start = textarea.selectionStart;
        const beforeCursor = textarea.value.substring(0, start);
        const afterCursor = textarea.value.substring(start);
        const isStartOfLine = start === 0 || beforeCursor.endsWith('\n');
        const bullet = isStartOfLine ? '- ' : '\n- ';
        textarea.value = beforeCursor + bullet + afterCursor;
        textarea.focus();
        textarea.setSelectionRange(start + bullet.length, start + bullet.length);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    startClockUpdate() {
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
    }

    triggerAutoScroll() {
        if (this.state.scrollingDisabled) return;
        const container = document.getElementById('messages-container');
        if (!container) return;
        const isNearBottom = container.scrollTop >= (container.scrollHeight - container.clientHeight - 100);
        if (isNearBottom) {
            const behavior = this.device.isMobile ? 'auto' : 'smooth';
            container.scrollTo({
                top: container.scrollHeight,
                behavior: behavior
            });
        }
    }

    cleanup() {
        this.hideLoading();
        const styleElement = document.getElementById('patient-modal-mobile-enhancements');
        if (styleElement) {
            styleElement.remove();
        }
        document.body.classList.remove('modal-active', 'keyboard-open');
        document.documentElement.style.position = '';
        document.documentElement.style.width = '';
        document.documentElement.style.height = '';
        document.body.style.overflow = '';
        this.state.isInitialized = false;
    }
}

// --- Initialization ---
let patientModalManager;
document.addEventListener('DOMContentLoaded', () => {
    patientModalManager = new PatientModalManager();
});

// --- Global Exports ---
window.modalState = () => patientModalManager?.state || {};
window.modalLoading = {
    show: () => patientModalManager?.showLoading(),
    hide: () => patientModalManager?.hideLoading()
};
window.patientModalManager = patientModalManager;