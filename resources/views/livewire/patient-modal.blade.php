<div x-data="{ 
    showAlertsModal: @entangle('showAlertsModal'),
    showModal: @entangle('showModal').live,
    init() {
        this.$watch('showModal', (value) => {
            if (value) {
                this.$nextTick(() => {
                    document.body.style.overflow = 'hidden';
                    document.body.classList.add('modal-active');
                });
            } else {
                document.body.style.overflow = '';
                document.body.classList.remove('modal-active');
            }
        });
             
        window.addEventListener('openModal', () => {
            this.showModal = true;
        });
    }
}" 
x-cloak>

    {{-- Modal de Alertas --}}
    <x-patient-modal.alerts-modal 
        :showAlertsModal="$showAlertsModal"
        :patientAlerts="$patientAlerts"
        :currentPatient="$currentPatient"
    />

    <div 
        x-show="showModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="modal-backdrop-container"
        style="display: none;"
        x-bind:style="showModal ? 'display: flex;' : 'display: none;'"
    >
        {{-- Backdrop --}}
        <div class="modal-backdrop-overlay"
             @click="if(!showAlertsModal) { showModal = false; $wire.closeModal(); }"></div>
        
        {{-- Modal Container --}}
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
                    
                    if (navigator.vibrate) {
                        navigator.vibrate(10);
                    }
                    
                    setTimeout(() => {
                        this.isTransitioning = false;
                    }, 200);
                }
            }"
            x-init="currentTabIndex = tabs.indexOf(activeTab);"
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
                
                if (Math.abs(deltaX) > Math.abs(deltaY) + 20 && 
                    (Math.abs(deltaX) > swipeThreshold || velocity > swipeVelocity)) {
                    handleSwipe(deltaX > 0 ? 'right' : 'left');
                }
                
                swipeStartX = null;
                swipeStartY = null;
                swipeStartTime = null;
            "
        >
            {{-- Loading Overlay --}}
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

            {{-- Header --}}
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

            {{-- Content Area --}}
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

                {{-- Avaliação --}}
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

    <style>
        /* x-cloak helper */
        [x-cloak] { display: none !important; }
        
        /* Mobile First Responsive Design */
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
            height: 100dvh;
            max-width: 100vw;
            max-height: 100vh;
            max-height: 100dvh;
            border-radius: 0;
            margin: 0;
        }
        
        .modal-header {
            flex-shrink: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 10;
        }
        
        .modal-tabs-container {
            flex-shrink: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 10;
        }
        
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
        
        .modal-content-wrapper {
            flex: 1;
            background: #f9fafb;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .modal-tab-content {
            position: absolute;
            inset: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-y: contain;
            scroll-behavior: auto;
            touch-action: pan-y pinch-zoom;
            pointer-events: auto;
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
        
        .modal-tab-chat {
            padding: 0;
        }
        
        .tab-content-padding {
            padding: 16px;
        }
        
        /* Loading States */
        .modal-loading-overlay,
        #modal-global-loading {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important;
            z-index: 9999 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent !important;
            backdrop-filter: none !important;
            pointer-events: auto !important;
            touch-action: none !important;
            user-select: none !important;
            -webkit-user-select: none !important;
            -webkit-touch-callout: none !important;
            will-change: opacity;
            contain: layout style paint;
        }
        
        .modal-loading-overlay.hidden,
        #modal-global-loading.hidden {
            display: none !important;
        }
        
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
            pointer-events: auto;
            touch-action: none;
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
        
        /* Mobile Scrollbar */
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
        
        body.loading-active {
            overflow: hidden !important;
            height: 100vh;
            height: 100dvh;
            position: relative;
        }
        
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
        
        /* Tablet Styles (641px and up) */
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
        }
        
        /* Desktop Styles (1025px and up) */
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
                scroll-behavior: smooth;
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
        
        /* Accessibility & Performance */
        @media (prefers-contrast: high) {
            .modal-tab-content::-webkit-scrollbar-thumb {
                background: #000;
            }
        }
        
        @media (prefers-reduced-motion: reduce) {
            .modal-main-container,
            .modal-tab-content,
            * {
                transition: none !important;
                animation: none !important;
            }
        }
        
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
        
        /* Safe area support */
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
        }
    </style>
</div>