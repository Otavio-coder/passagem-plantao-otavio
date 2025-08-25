<div x-data="{ 
    showAlertsModal: @entangle('showAlertsModal'),
    showModal: @entangle('showModal'),
    init() {
        // Responsividade e controle de scroll do body
        this.$watch('showAlertsModal', (value) => {
            document.body.style.overflow = value ? 'hidden' : '';
        });
        this.$watch('showModal', (value) => {
            document.body.style.overflow = value ? 'hidden' : '';
            if (value) {
                document.body.classList.add('modal-active');
                // Mobile: fixar html para evitar bounce
                if (window.innerWidth < 640) {
                    document.documentElement.style.position = 'fixed';
                    document.documentElement.style.width = '100%';
                    document.documentElement.style.height = '100%';
                    document.documentElement.style.top = '0';
                    document.documentElement.style.left = '0';
                }
            } else {
                document.body.classList.remove('modal-active');
                document.documentElement.style.position = '';
                document.documentElement.style.width = '';
                document.documentElement.style.height = '';
                document.documentElement.style.top = '';
                document.documentElement.style.left = '';
                document.body.style.overflow = '';
            }
        });
    }
}">
    {{-- Modal de Alertas (children modal) --}}
    <x-patient-modal.alerts-modal 
        :showAlertsModal="$showAlertsModal"
        :patientAlerts="$patientAlerts"
        :currentPatient="$currentPatient"
    />

    @if($showModal)
        <div 
            x-show="showModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="modal-backdrop-container"
            style="touch-action: none;"
        >
            {{-- Backdrop com blur gradual --}}
            <div class="modal-backdrop-overlay"
                 @click="!showAlertsModal && (showModal = false); setTimeout(() => $wire.closeModal(), 150)"></div>
            
            {{-- Modal Container - Mobile First Design --}}
            <div
                class="modal-main-container"
                x-data="{
                    activeTab: 'tab-s',
                    activeCpoeCategory: 'cpoe-exames',
                    swipeStartX: null,
                    swipeStartY: null,
                    swipeStartTime: null,
                    currentTabIndex: 0,
                    tabs: ['tab-s', 'tab-b', 'tab-a', 'tab-r'],
                    tabLabels: ['Situação', 'Background', 'Avaliação', 'Recomendações'],
                    isSwipeEnabled: false,
                    swipeThreshold: 50,
                    swipeVelocity: 0.3,
                    isTransitioning: false,
                    deviceType: 'desktop',
                    
                    init() {
                        this.updateDeviceType();
                        this.currentTabIndex = this.tabs.indexOf(this.activeTab);
                        
                        // Responsive observer
                        const resizeObserver = new ResizeObserver(() => {
                            this.updateDeviceType();
                        });
                        resizeObserver.observe(document.body);
                        
                        window.addEventListener('orientationchange', () => {
                            setTimeout(() => this.updateDeviceType(), 150);
                        });
                    },
                    
                    updateDeviceType() {
                        const width = window.innerWidth;
                        if (width < 640) {
                            this.deviceType = 'mobile';
                            this.isSwipeEnabled = true;
                        } else if (width < 1024) {
                            this.deviceType = 'tablet';
                            this.isSwipeEnabled = true;
                        } else {
                            this.deviceType = 'desktop';
                            this.isSwipeEnabled = false;
                        }
                    },
                    
                    handleSwipe(direction) {
                        if (!this.isSwipeEnabled || this.isTransitioning) return;
                        
                        const newIndex = direction === 'left' 
                            ? Math.min(this.currentTabIndex + 1, this.tabs.length - 1)
                            : Math.max(this.currentTabIndex - 1, 0);
                            
                        if (newIndex !== this.currentTabIndex) {
                            this.switchTab(newIndex);
                        }
                    },
                    
                    switchTab(newIndex) {
                        if (this.isTransitioning) return;
                        
                        this.isTransitioning = true;
                        this.currentTabIndex = newIndex;
                        this.activeTab = this.tabs[this.currentTabIndex];
                        
                        // Haptic feedback
                        if (navigator.vibrate) {
                            navigator.vibrate(10);
                        }
                        
                        setTimeout(() => {
                            this.isTransitioning = false;
                        }, 200);
                    }
                }"
                x-init="
                    currentTabIndex = tabs.indexOf(activeTab);
                "
                data-patient-id="{{ $currentPatient['nr_atendimento'] ?? '' }}"
                data-shift="{{ $currentShift ?? '' }}"
                @click.stop
                @touchstart.passive="
                    if (!isSwipeEnabled || isTransitioning) return;
                    
                    const touch = $event.touches[0];
                    swipeStartX = touch.clientX;
                    swipeStartY = touch.clientY;
                    swipeStartTime = Date.now();
                "
                @touchmove="
                    if (!isSwipeEnabled || swipeStartX === null || isTransitioning) return;
                    
                    const touch = $event.touches[0];
                    const deltaX = touch.clientX - swipeStartX;
                    const deltaY = touch.clientY - swipeStartY;
                    
                    // Prevent horizontal scroll only if it's clearly a horizontal swipe
                    if (Math.abs(deltaX) > Math.abs(deltaY) + 40 && Math.abs(deltaX) > 60) {
                        $event.preventDefault();
                    }
                "
                @touchend.passive="
                    if (!isSwipeEnabled || swipeStartX === null || swipeStartY === null || isTransitioning) return;
                    
                    const touch = $event.changedTouches[0];
                    const deltaX = touch.clientX - swipeStartX;
                    const deltaY = touch.clientY - swipeStartY;
                    const deltaTime = Date.now() - (swipeStartTime || 0);
                    const velocity = Math.abs(deltaX) / deltaTime;
                    
                    // Trigger swipe only for horizontal dominant movement
                    if (Math.abs(deltaX) > Math.abs(deltaY) + 20 && 
                        (Math.abs(deltaX) > swipeThreshold || velocity > swipeVelocity)) {
                        handleSwipe(deltaX > 0 ? 'right' : 'left');
                    }
                    
                    swipeStartX = null;
                    swipeStartY = null;
                    swipeStartTime = null;
                "
            >
                {{-- Loading Overlay - Mantendo compatibilidade com ID original --}}
                @if($loadingPatient)
                    <div id="modal-global-loading" class="modal-loading-overlay">
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
                    </div>
                @endif

                {{-- Header - Fixed and responsive --}}
                <div class="modal-header">
                    <x-patient-modal.header 
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails" 
                    />
                </div>

                {{-- Tabs Navigation --}}
                <div class="modal-tabs-container">
                    <x-patient-modal.tabs />
                    
                    {{-- Mobile swipe indicator --}}
                    <div x-show="isSwipeEnabled && deviceType === 'mobile'" class="mobile-swipe-indicator">
                        <div class="swipe-hint">
                            <svg class="swipe-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l4-4-4-4m6 8l4-4-4-4"></path>
                            </svg>
                            <span>Deslize para navegar</span>
                            <svg class="swipe-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l4-4-4-4m6 8l4-4-4-4"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Content Area - Mobile optimized scrolling --}}
                <div class="modal-content-wrapper">
                    {{-- Situação --}}
                    <div
                        x-show="activeTab === 'tab-s'"
                        class="modal-tab-content"
                        x-bind:class="activeTab === 'tab-s' ? 'active' : ''"
                    >
                        <div class="tab-content-padding">
                            <x-patient-modal.content.sbar-situacao 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>
                    </div>

                    {{-- Background --}}
                    <div
                        x-show="activeTab === 'tab-b'"
                        class="modal-tab-content"
                        x-bind:class="activeTab === 'tab-b' ? 'active' : ''"
                    >
                        <div class="tab-content-padding">
                            <x-patient-modal.content.sbar-background 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>
                    </div>

                    {{-- Avaliação (Chat) --}}
                    <div
                        x-show="activeTab === 'tab-a'"
                        class="modal-tab-content modal-tab-chat"
                        x-bind:class="activeTab === 'tab-a' ? 'active' : ''"
                    >
                        <x-patient-modal.content.sbar-avaliacao 
                            :loadingPatient="$loadingPatient"
                            :currentPatient="$currentPatient"
                            :patientDetails="$patientDetails"
                        />
                    </div>

                    {{-- Recomendações --}}
                    <div
                        x-show="activeTab === 'tab-r'"
                        class="modal-tab-content"
                        x-bind:class="activeTab === 'tab-r' ? 'active' : ''"
                    >
                        <div class="tab-content-padding">
                            <x-patient-modal.content.sbar-recomendacoes 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
        /* ================================
           MOBILE FIRST RESPONSIVE DESIGN
           ================================ */
        
        /* Base styles - Mobile First */
        .modal-backdrop-container {
            position: fixed;
            inset: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-backdrop-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            transition: all 0.3s ease;
        }
        
        /* Mobile First Modal Container */
        .modal-main-container {
            position: relative;
            background: white;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            contain: layout style paint;
            will-change: transform;
            
            /* Mobile: Fullscreen */
            width: 100vw;
            height: 100vh;
            height: 100dvh; /* Dynamic viewport height for mobile browsers */
            max-width: 100vw;
            max-height: 100vh;
            max-height: 100dvh;
            border-radius: 0;
            margin: 0;
        }
        
        /* Header - Fixed */
        .modal-header {
            flex-shrink: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 10;
        }
        
        /* Tabs Container */
        .modal-tabs-container {
            flex-shrink: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 10;
        }
        
        /* Mobile Swipe Indicator */
        .mobile-swipe-indicator {
            display: flex;
            justify-content: center;
            padding: 8px 0;
            background: rgba(249, 250, 251, 0.5);
        }
        
        .swipe-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6b7280;
        }
        
        .swipe-icon {
            width: 16px;
            height: 16px;
        }
        
        /* Content Wrapper - Mobile Optimized */
        .modal-content-wrapper {
            flex: 1;
            background: #f9fafb;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        /* Tab Content - Mobile Scroll Optimized */
        .modal-tab-content {
            position: absolute;
            inset: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-y: contain;
            scroll-behavior: auto; /* Better performance on mobile */
            
            /* Mobile touch optimization */
            touch-action: pan-y pinch-zoom;
            pointer-events: auto;
            
            /* Hide by default */
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        
        .modal-tab-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Chat tab special handling */
        .modal-tab-chat {
            padding: 0; /* Chat component handles its own padding */
        }
        
        /* Content padding for non-chat tabs */
        .tab-content-padding {
            padding: 16px;
        }
        
        /* Mobile Navigation Indicators */
        .mobile-nav-indicators {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 20;
            pointer-events: none;
        }
        
        .nav-indicator-container {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 12px 16px;
            box-shadow: 0 10px 25px -12px rgba(0, 0, 0, 0.3);
        }
        
        .nav-dots {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            justify-content: center;
        }
        
        .nav-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transition: all 0.2s ease;
            pointer-events: auto;
            border: none;
            cursor: pointer;
        }
        
        .nav-dot.active {
            background: white;
            transform: scale(1.25);
        }
        
        .nav-dot:hover {
            background: rgba(255, 255, 255, 0.6);
        }
        
        .nav-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 12px;
            font-weight: 500;
            text-align: center;
        }
        
        /* Loading States - Global fullscreen overlay */
        .modal-loading-overlay,
        #modal-global-loading {
            position: fixed !important; /* Fixed para cobrir toda a viewport */
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important; /* Dynamic viewport height */
            z-index: 9999 !important; /* Z-index muito alto para ficar acima de tudo */
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent !important; /* Background transparente */
            backdrop-filter: none !important;
            
            /* Bloqueia todas as interações */
            pointer-events: auto !important;
            touch-action: none !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -webkit-touch-callout: none !important;
            
            /* Performance */
            will-change: opacity;
            contain: layout style paint;
        }
        
        /* Estado hidden para compatibilidade */
        .modal-loading-overlay.hidden,
        #modal-global-loading.hidden {
            display: none !important;
        }
        
        /* Loading content com fundo semi-transparente */
        .modal-loading-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            padding: 24px;
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(8px) !important;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            
            /* Bloqueia interações no conteúdo também */
            pointer-events: auto;
            touch-action: none;
        }
        
        .modal-loading-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        
        .modal-loading-spinner {
            position: relative;
        }
        
        .spinner-primary {
            width: 40px;
            height: 40px;
            border: 4px solid #004D9D;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .spinner-secondary {
            position: absolute;
            inset: 0;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(0, 77, 157, 0.2);
            border-top-color: transparent;
            border-radius: 50%;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        .modal-loading-text {
            color: #004D9D;
            font-weight: 500;
            font-size: 14px;
        }
        
        .modal-loading-dots {
            display: flex;
            gap: 4px;
        }
        
        .dot {
            width: 8px;
            height: 8px;
            background: #004D9D;
            border-radius: 50%;
            animation: bounce 1.4s ease-in-out infinite both;
        }
        
        .dot-1 { animation-delay: 0ms; }
        .dot-2 { animation-delay: 150ms; }
        .dot-3 { animation-delay: 300ms; }
        
        /* Mobile Scrollbar Styling */
        .modal-tab-content {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        
        .modal-tab-content::-webkit-scrollbar {
            width: 2px;
            display: block;
        }
        
        .modal-tab-content::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .modal-tab-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
            transition: background-color 0.2s ease;
        }
        
        .modal-tab-content::-webkit-scrollbar-thumb:active {
            background: #94a3b8;
        }
        
        /* Body modal state */
        body.modal-active {
            overflow: hidden !important;
            height: 100vh;
            height: 100dvh;
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
        }
        
        /* Body loading state */
        body.loading-active {
            overflow: hidden !important;
            height: 100vh;
            height: 100dvh;
            position: relative;
        }
        
        /* Disable pointer events on everything when loading */
        body.loading-active > *:not(#modal-global-loading):not(.modal-loading-overlay) {
            pointer-events: none !important;
            touch-action: none !important;
        }
        
        /* Animations */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        @keyframes bounce {
            0%, 80%, 100% { 
                transform: scale(0);
                opacity: 0.5;
            }
            40% { 
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* ================================
           TABLET STYLES (641px and up)
           ================================ */
        
        @media (min-width: 641px) {
            .modal-backdrop-container {
                padding: 32px;
            }
            
            .modal-main-container {
                width: 90vw;
                height: 88vh;
                max-width: 90vw;
                max-height: 88vh;
                border-radius: 16px;
                margin: 0 auto;
            }
            
            .mobile-swipe-indicator {
                padding: 12px 0;
            }
            
            .tab-content-padding {
                padding: 24px;
            }
            
            .modal-tab-content::-webkit-scrollbar {
                width: 4px;
            }
            
            .mobile-nav-indicators {
                display: none; /* Hide on tablet and up */
            }
        }
        
        /* ================================
           DESKTOP STYLES (1025px and up)
           ================================ */
        
        @media (min-width: 1025px) {
            .modal-backdrop-container {
                padding: 48px;
            }
            
            .modal-main-container {
                width: 80vw;
                height: 90vh;
                max-width: min(80vw, 1400px);
                max-height: 90vh;
                border-radius: 24px;
            }
            
            .mobile-swipe-indicator {
                display: none;
            }
            
            .tab-content-padding {
                padding: 32px;
            }
            
            .modal-tab-content {
                scroll-behavior: smooth; /* Smooth scrolling on desktop */
            }
            
            .modal-tab-content::-webkit-scrollbar {
                width: 6px;
            }
            
            .modal-tab-content::-webkit-scrollbar-track {
                background: #f8fafc;
                border-radius: 3px;
            }
            
            .modal-tab-content::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }
        }
        
        /* ================================
           ACCESSIBILITY & PERFORMANCE
           ================================ */
        
        /* High contrast support */
        @media (prefers-contrast: high) {
            .modal-tab-content::-webkit-scrollbar-thumb {
                background: #000;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .modal-main-container,
            .modal-tab-content,
            .nav-dot,
            * {
                transition: none !important;
                animation: none !important;
            }
        }
        
        /* Focus management */
        .modal-main-container *:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
            border-radius: 4px;
        }
        
        /* Touch target optimization */
        @media (max-width: 640px) {
            button, 
            [role="button"], 
            .clickable,
            input,
            textarea,
            select {
                min-height: 44px;
                touch-action: manipulation;
            }
            
            /* Prevent zoom on form inputs */
            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="tel"],
            input[type="url"],
            input[type="password"],
            textarea,
            select {
                font-size: 16px !important;
            }
        }
        
        /* Safe area support for devices with notch */
        @supports (padding-top: env(safe-area-inset-top)) {
            @media (max-width: 640px) {
                .modal-main-container {
                    padding-top: env(safe-area-inset-top);
                    padding-bottom: env(safe-area-inset-bottom);
                    height: calc(100vh - env(safe-area-inset-top) - env(safe-area-inset-bottom));
                    height: calc(100dvh - env(safe-area-inset-top) - env(safe-area-inset-bottom));
                }
            }
        }
        
        /* Landscape mobile optimization */
        @media (max-width: 640px) and (orientation: landscape) {
            .modal-main-container {
                height: 100vh;
                height: 100dvh;
            }
            
            .mobile-nav-indicators {
                bottom: 8px;
            }
            
            .nav-indicator-container {
                padding: 8px 12px;
            }
        }
    </style>
</div>