/**
 * Patient Modal Management System - VERSÃO OTIMIZADA
 * Remoção de handlers desnecessários e foco em exibição rápida
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

    init() {
        if (this.state.isInitialized) return;
        this.detectDevice();
        this.setupViewport();
        this.setupEventListeners();
        this.setupTouchHandlers();
        this.setupKeyboardHandlers();
        this.injectMobileStyles();
        this.state.isInitialized = true;
        
    }

    detectDevice() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        
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

    setupViewport() {
        this.setViewportHeight();
        
        let viewportTimeout;
        const handleViewportChange = () => {
            clearTimeout(viewportTimeout);
            viewportTimeout = setTimeout(() => {
                this.setViewportHeight();
                this.detectDevice();
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

    setupEventListeners() {      
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
        
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.detectDevice();
                this.updateModalDimensions();
            }, 150);
        });
    }

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

    setupKeyboardHandlers() {
        if (this.device.isMobile) {
            document.addEventListener('focusin', (e) => {
                if (e.target.matches('input, textarea, select')) {
                    e.target.style.fontSize = '16px';
                }
            });
        }
    }

    injectMobileStyles() {
        const css = `
            @media (max-width: 640px) {
                .modal-main-container {
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    user-select: none;
                    -webkit-touch-callout: none;
                }
                
                .modal-tab-content p,
                .modal-tab-content span,
                .modal-tab-content div[class*="text"],
                .modal-tab-content input,
                .modal-tab-content textarea {
                    -webkit-user-select: text;
                    -moz-user-select: text;
                    user-select: text;
                }
                
                .modal-tab-content {
                    -webkit-transform: translateZ(0);
                    transform: translateZ(0);
                    -webkit-backface-visibility: hidden;
                    backface-visibility: hidden;
                }
                
                button, [role="button"], .clickable {
                    min-height: var(--touch-target, 44px);
                    min-width: var(--touch-target, 44px);
                }
                
                body.keyboard-open .modal-main-container {
                    height: 70vh;
                    height: 70dvh;
                }
                
                @media (orientation: landscape) {
                    .mobile-nav-indicators {
                        bottom: 8px;
                    }
                    
                    .nav-indicator-container {
                        padding: 8px 12px;
                    }
                }
            }
            
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

    updateModalDimensions() {
        this.updateCSSVariables();
        if (this.device.isMobile) {
            setTimeout(() => {
                window.scrollTo(0, 0);
            }, 50);
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

// Initialization
let patientModalManager;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        patientModalManager = new PatientModalManager();
    });
} else {
    patientModalManager = new PatientModalManager();
}

// Global Exports
window.modalState = () => patientModalManager?.state || {};
window.modalLoading = {
    show: () => patientModalManager?.showLoading(),
    hide: () => patientModalManager?.hideLoading()
};
window.patientModalManager = patientModalManager;