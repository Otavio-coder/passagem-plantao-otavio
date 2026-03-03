<div x-data="{
    showAlertsModal: @entangle('showAlertsModal'),
    showModal: @entangle('showModal'),
    init() {
        this.$watch('showAlertsModal', (value) => {
            if (value) {
                document.body.style.overflow = 'hidden';
            } else if (!this.showModal) {
                document.body.style.overflow = '';
            }
        });

        this.$watch('showModal', (value) => {
            if (value) {
                document.body.style.overflow = 'hidden';
                document.body.classList.add('modal-active');

                if (window.innerWidth < 640) {
                    document.documentElement.style.position = 'fixed';
                    document.documentElement.style.width = '100%';
                    document.documentElement.style.height = '100%';
                    document.documentElement.style.top = '0';
                }
            } else {
                document.body.style.overflow = '';
                document.body.classList.remove('modal-active');
                document.documentElement.style.position = '';
                document.documentElement.style.width = '';
                document.documentElement.style.height = '';
                document.documentElement.style.top = '';
            }
        });
    }
}">

    {{-- Modal de Alertas --}}
    <x-patient-modal.alerts-modal
        :showAlertsModal="$showAlertsModal"
        :patientAlerts="$patientAlerts"
        :currentPatient="$currentPatient"
    />

    {{-- Modal Principal --}}
    <div
        x-show="showModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9998]"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showModal = false; $wire.closeModal();"></div>

        {{-- Container do Modal --}}
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div
                class="relative bg-white flex flex-col overflow-hidden
                       w-full h-full
                       sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                       lg:w-[85vw] lg:h-[90vh] lg:max-w-[1600px]
                       shadow-2xl"
                x-data="{
                    activeTab: 'tab-s',
                    activeRecomendacaoTab: 'tab-proc',
                    currentTabIndex: 0,
                    tabs: ['tab-s', 'tab-b', 'tab-a', 'tab-r'],
                    isSwipeEnabled: window.innerWidth < 1024,
                    swipeStartX: null,
                    swipeStartY: null,
                    isTransitioning: false,

                    init() {
                        this.updateSwipeEnabled();
                        window.addEventListener('resize', () => this.updateSwipeEnabled());
                    },

                    updateSwipeEnabled() {
                        this.isSwipeEnabled = window.innerWidth < 1024;
                    },

                    switchTab(tabName) {
                        if (this.isTransitioning) return;

                        this.isTransitioning = true;
                        this.activeTab = tabName;
                        this.currentTabIndex = this.tabs.indexOf(tabName);

                        // Haptic feedback
                        if (navigator.vibrate) {
                            navigator.vibrate(10);
                        }

                        setTimeout(() => {
                            this.isTransitioning = false;
                        }, 300);
                    },

                    handleSwipe(direction) {
                        if (!this.isSwipeEnabled || this.isTransitioning) return;

                        const newIndex = direction === 'left'
                            ? Math.min(this.currentTabIndex + 1, this.tabs.length - 1)
                            : Math.max(this.currentTabIndex - 1, 0);

                        if (newIndex !== this.currentTabIndex) {
                            this.switchTab(this.tabs[newIndex]);
                        }
                    }
                }"
                @click.stop
                @touchstart.passive="
                    if (!isSwipeEnabled) return;
                    const touch = $event.touches[0];
                    swipeStartX = touch.clientX;
                    swipeStartY = touch.clientY;
                "
                @touchend.passive="
                    if (!isSwipeEnabled || swipeStartX === null) return;

                    const touch = $event.changedTouches[0];
                    const deltaX = touch.clientX - swipeStartX;
                    const deltaY = touch.clientY - swipeStartY;

                    if (Math.abs(deltaX) > Math.abs(deltaY) + 30 && Math.abs(deltaX) > 80) {
                        handleSwipe(deltaX > 0 ? 'right' : 'left');
                    }

                    swipeStartX = null;
                    swipeStartY = null;
                "
            >
                {{-- Loading Overlay --}}
                @if($loadingPatient)
                    <div class="absolute inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm">
                        <div class="flex flex-col items-center gap-4">
                            <div class="relative">
                                <div class="w-12 h-12 border-4 border-[#004D9D] border-t-transparent rounded-full animate-spin"></div>
                                <div class="absolute inset-0 w-12 h-12 border-4 border-[#004D9D]/20 border-t-transparent rounded-full animate-pulse"></div>
                            </div>
                            <span class="text-[#004D9D] font-medium text-sm">Carregando dados do paciente...</span>
                        </div>
                    </div>
                @endif

                {{-- Header - ALTURA FIXA --}}
                <div class="flex-shrink-0">
                    <x-patient-modal.header
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails"
                    />
                </div>

                {{-- Tabs Navigation - ALTURA FIXA --}}
                <div class="flex-shrink-0 border-b border-gray-200">
                    <x-patient-modal.tabs />
                </div>

                {{-- Content Area - FLEX-1 COM OVERFLOW --}}
                <div class="flex-1 bg-gray-50 relative overflow-hidden min-h-0">
                    {{-- Container com position relative para as tabs absolutas --}}
                    <div class="absolute inset-0">
                        {{-- Situação --}}
                        <div x-show="activeTab === 'tab-s'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <x-patient-modal.content.sbar-situacao
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        {{-- Background --}}
                        <div x-show="activeTab === 'tab-b'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <x-patient-modal.content.sbar-background
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        {{-- Avaliação (Chat) --}}
                        <div x-show="activeTab === 'tab-a'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0"
                             style="display: none;">
                            <x-patient-modal.content.sbar-avaliacao
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        {{-- Recomendações --}}
                        <div x-show="activeTab === 'tab-r'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
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
    </div>

    <style>
        /* Body modal state */
        body.modal-active {
            overflow: hidden !important;
            position: fixed;
            width: 100%;
            height: 100%;
        }

        /* Scrollbar customizado */
        .overflow-y-auto {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        .overflow-y-auto::-webkit-scrollbar {
            width: 6px;
        }

        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Mobile optimizations */
        @media (max-width: 640px) {
            .overflow-y-auto {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }

            .overflow-y-auto::-webkit-scrollbar {
                width: 3px;
            }
        }

        /* Prevent zoom on inputs in mobile */
        @media (max-width: 640px) {
            input[type="text"],
            input[type="email"],
            input[type="number"],
            textarea,
            select {
                font-size: 16px !important;
            }
        }

        /* Transitions suaves */
        .transition-opacity {
            transition-property: opacity;
        }

        /* Garantir que tabs ocultas não ocupem espaço e não interfiram */
        [x-show][style*="display: none"] {
            display: none !important;
            pointer-events: none !important;
            visibility: hidden !important;
        }
    </style>
</div>
