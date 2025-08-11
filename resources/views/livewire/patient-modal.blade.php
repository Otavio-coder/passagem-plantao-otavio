<div x-data="{ 
    showAlertsModal: @entangle('showAlertsModal'),
    showModal: @entangle('showModal'),
    init() {
        this.$watch('showAlertsModal', (value) => {
            document.body.style.overflow = value ? 'hidden' : '';
        });
        this.$watch('showModal', (value) => {
            document.body.style.overflow = value ? 'hidden' : '';
            if (value) {
                // Prevent iOS Safari bounce scroll
                document.documentElement.style.position = 'fixed';
                document.documentElement.style.width = '100%';
                document.documentElement.style.height = '100%';
            } else {
                document.documentElement.style.position = '';
                document.documentElement.style.width = '';
                document.documentElement.style.height = '';
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
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-[5vw]"
            style="touch-action: none;"
        >
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"
                 @click="!showAlertsModal && (showModal = false); setTimeout(() => $wire.closeModal(), 150)"></div>
            
            {{-- Modal Container - Corrigido para responsividade profissional --}}
            <div
                class="relative bg-white 
                       w-full h-full
                       sm:w-full sm:h-[90vh] 
                       sm:max-w-none sm:max-h-[90vh]
                       max-w-none max-h-none
                       rounded-none sm:rounded-2xl 
                       shadow-2xl transition-all 
                       flex flex-col overflow-hidden
                       mx-auto"
                x-data="{
                    activeTab: 'tab-s',
                    activeCpoeCategory: 'cpoe-exames',
                    swipeStartX: null,
                    swipeStartY: null,
                    currentTabIndex: 0,
                    tabs: ['tab-s', 'tab-b', 'tab-a', 'tab-r'],
                    isSwipeEnabled: window.innerWidth < 768,
                    swipeThreshold: 50,
                    swipeVelocity: 0.3,
                    handleSwipe(direction) {
                        if (!this.isSwipeEnabled) return;
                        
                        const newIndex = direction === 'left' 
                            ? Math.min(this.currentTabIndex + 1, this.tabs.length - 1)
                            : Math.max(this.currentTabIndex - 1, 0);
                            
                        if (newIndex !== this.currentTabIndex) {
                            this.currentTabIndex = newIndex;
                            this.activeTab = this.tabs[this.currentTabIndex];
                            this.showSwipeFeedback(direction);
                        }
                    },
                    showSwipeFeedback(direction) {
                        // Haptic feedback se disponível
                        if (navigator.vibrate) {
                            navigator.vibrate(10);
                        }
                        
                        // Visual feedback for swipe
                        const modal = this.$el;
                        const offset = direction === 'left' ? '-3px' : '3px';
                        modal.style.transform = `translateX(${offset})`;
                        modal.style.transition = 'transform 0.1s ease-out';
                        
                        setTimeout(() => {
                            modal.style.transform = 'translateX(0)';
                            modal.style.transition = 'transform 0.3s ease-out';
                        }, 100);
                    }
                }"
                x-init="
                    currentTabIndex = tabs.indexOf(activeTab);
                    
                    // Update swipe enabled on resize
                    const updateSwipeState = () => {
                        isSwipeEnabled = window.innerWidth < 768;
                    };
                    
                    window.addEventListener('resize', updateSwipeState);
                    window.addEventListener('orientationchange', () => {
                        setTimeout(updateSwipeState, 100);
                    });
                "
                data-patient-id="{{ $currentPatient['nr_atendimento'] ?? '' }}"
                data-shift="{{ $currentShift ?? '' }}"
                @click.stop
                @touchstart="
                    if (!isSwipeEnabled) return;
                    
                    const touch = $event.touches[0];
                    swipeStartX = touch.clientX;
                    swipeStartY = touch.clientY;
                    swipeStartTime = Date.now();
                "
                @touchmove.passive="
                    if (!isSwipeEnabled || swipeStartX === null) return;
                    
                    const touch = $event.touches[0];
                    const deltaX = touch.clientX - swipeStartX;
                    const deltaY = touch.clientY - swipeStartY;
                    
                    // Prevent default if horizontal swipe is detected
                    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10) {
                        $event.preventDefault();
                    }
                "
                @touchend="
                    if (!isSwipeEnabled || swipeStartX === null || swipeStartY === null) return;
                    
                    const touch = $event.changedTouches[0];
                    const deltaX = touch.clientX - swipeStartX;
                    const deltaY = touch.clientY - swipeStartY;
                    const deltaTime = Date.now() - (swipeStartTime || 0);
                    const velocity = Math.abs(deltaX) / deltaTime;
                    
                    // Only trigger swipe if horizontal movement is greater than vertical
                    // and meets threshold or velocity requirements
                    if (Math.abs(deltaX) > Math.abs(deltaY) && 
                        (Math.abs(deltaX) > swipeThreshold || velocity > swipeVelocity)) {
                        handleSwipe(deltaX > 0 ? 'right' : 'left');
                    }
                    
                    swipeStartX = null;
                    swipeStartY = null;
                    swipeStartTime = null;
                "
                style="transform: translateX(0); transition: transform 0.3s ease-out;"
            >
                {{-- Loading Overlay --}}
                @if($loadingPatient)
                    <div class="absolute inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm">
                        <div class="flex flex-col items-center justify-center space-y-4">
                            <div class="relative">
                                <div class="w-10 h-10 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin"></div>
                                <div class="absolute inset-0 w-10 h-10 border-4 border-t-transparent border-[#004D9D]/20 rounded-full animate-pulse"></div>
                            </div>
                            <span class="text-[#004D9D] font-medium text-sm">Carregando dados do paciente...</span>
                            <div class="flex space-x-1">
                                <div class="w-2 h-2 bg-[#004D9D] rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                <div class="w-2 h-2 bg-[#004D9D] rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                <div class="w-2 h-2 bg-[#004D9D] rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Header - Fixed with mobile optimization --}}
                <div class="flex-shrink-0">
                    <x-patient-modal.header 
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails" 
                    />
                </div>

                {{-- Tabs Navigation - Fixed with improved mobile UX --}}
                <div class="flex-shrink-0">
                    <x-patient-modal.tabs />
                </div>

                {{-- Content Area - Optimized scrolling --}}
                <div class="flex-1 min-h-0 bg-gray-50 relative">
                    {{-- Situação --}}
                    <div
                        x-show="activeTab === 'tab-s'"
                        x-cloak
                        class="absolute inset-0 overflow-y-auto overscroll-y-contain mobile-scroll"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform translate-x-4"
                        x-transition:enter-end="opacity-100 transform translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform translate-x-0"
                        x-transition:leave-end="opacity-0 transform -translate-x-4"
                    >
                        <div class="min-h-full">
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
                        x-cloak
                        class="absolute inset-0 overflow-y-auto overscroll-y-contain mobile-scroll"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform translate-x-4"
                        x-transition:enter-end="opacity-100 transform translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform translate-x-0"
                        x-transition:leave-end="opacity-0 transform -translate-x-4"
                    >
                        <div class="min-h-full">
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
                        x-cloak
                        class="absolute inset-0"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform translate-x-4"
                        x-transition:enter-end="opacity-100 transform translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform translate-x-0"
                        x-transition:leave-end="opacity-0 transform -translate-x-4"
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
                        x-cloak
                        class="absolute inset-0 overflow-y-auto overscroll-y-contain mobile-scroll"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform translate-x-4"
                        x-transition:enter-end="opacity-100 transform translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform translate-x-0"
                        x-transition:leave-end="opacity-0 transform -translate-x-4"
                    >
                        <div class="min-h-full">
                            <x-patient-modal.content.sbar-recomendacoes 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>
                    </div>

                    {{-- Mobile Navigation Indicators --}}
                    <div class="sm:hidden absolute bottom-6 left-1/2 transform -translate-x-1/2 z-20 pointer-events-none">
                        <div class="bg-black/40 backdrop-blur-md rounded-full px-4 py-3 flex flex-col items-center space-y-2 shadow-lg">
                            {{-- Dots Indicator --}}
                            <div class="flex space-x-2">
                                <template x-for="(tab, index) in tabs" :key="tab">
                                    <button 
                                        @click="currentTabIndex = index; activeTab = tabs[index]"
                                        class="w-2.5 h-2.5 rounded-full transition-all duration-300 pointer-events-auto"
                                        :class="currentTabIndex === index ? 'bg-white scale-125 shadow-sm' : 'bg-white/50 hover:bg-white/70'"
                                    ></button>
                                </template>
                            </div>
                            {{-- Tab Names --}}
                            <div class="text-white/90 text-xs font-medium text-center leading-tight">
                                <span x-show="currentTabIndex === 0">Situação</span>
                                <span x-show="currentTabIndex === 1">Background</span>
                                <span x-show="currentTabIndex === 2">Avaliação</span>
                                <span x-show="currentTabIndex === 3">Recomendações</span>
                            </div>
                            {{-- Swipe Instruction --}}
                            <div class="text-white/70 text-xs text-center">
                                Deslize para navegar
                            </div>
                        </div>
                    </div>

                    {{-- Swipe Edge Indicators --}}
                    <div class="sm:hidden absolute inset-y-0 left-0 w-8 z-10 flex items-center" 
                         x-show="currentTabIndex > 0">
                        <div class="w-1 h-8 bg-white/30 rounded-r-full ml-1"></div>
                    </div>
                    <div class="sm:hidden absolute inset-y-0 right-0 w-8 z-10 flex items-center justify-end" 
                         x-show="currentTabIndex < tabs.length - 1">
                        <div class="w-1 h-8 bg-white/30 rounded-l-full mr-1"></div>
                    </div>
                </div>

                {{-- Footer - Fixed --}}
                <div class="flex-shrink-0">
                    <x-patient-modal.footer />
                </div>
            </div>
        </div>
    @endif

    <style>
        /* Modal responsivo profissional */
        @media (max-width: 640px) {
            /* Mobile: fullscreen otimizado */
            .modal-mobile-fullscreen {
                width: 100vw !important;
                height: 100vh !important;
                max-width: none !important;
                max-height: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
            }
        }

        @media (min-width: 641px) {
            /* Tablet e Desktop: 90% da largura (5% de margem de cada lado) */
            .modal-container {
                width: 90vw !important;
                max-width: 90vw !important;
                height: 90vh !important;
                max-height: 90vh !important;
            }
        }

        /* Enhanced scrollbar styling */
        .mobile-scroll {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-y: contain;
        }

        .mobile-scroll::-webkit-scrollbar {
            width: 4px;
        }

        .mobile-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 2px;
        }

        .mobile-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        .mobile-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Mobile-specific optimizations */
        @media (max-width: 767px) {
            .mobile-scroll {
                scroll-behavior: smooth;
                scroll-snap-type: none;
            }
            
            /* Better touch targets */
            button, .clickable {
                min-height: 44px;
                min-width: 44px;
                touch-action: manipulation;
            }
            
            /* Prevent text selection during swipe */
            .mobile-scroll {
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }
        }

        /* Tablet optimizations */
        @media (min-width: 768px) and (max-width: 1024px) {
            .mobile-scroll {
                scrollbar-width: thin;
            }
        }

        /* Safe area handling para dispositivos com notch */
        @supports (padding-top: env(safe-area-inset-top)) {
            @media (max-width: 640px) {
                .modal-mobile-fullscreen {
                    padding-top: env(safe-area-inset-top);
                    padding-bottom: env(safe-area-inset-bottom);
                    height: calc(100vh - env(safe-area-inset-top) - env(safe-area-inset-bottom));
                }
            }
        }

        /* Smooth transitions para reduced motion preference */
        @media (prefers-reduced-motion: reduce) {
            .transition-all,
            [x-transition] {
                transition: none !important;
                animation: none !important;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .mobile-scroll::-webkit-scrollbar-thumb {
                background: #000;
            }
            
            .bg-black\/40 {
                background-color: rgba(0, 0, 0, 0.8) !important;
            }
        }

        /* Correção específica para o padding do container principal */
        .modal-backdrop-container {
            padding: 5vw;
        }

        @media (max-width: 640px) {
            .modal-backdrop-container {
                padding: 0;
            }
        }
    </style>
</div>