<div x-data="{ 
    showAlertsModal: <?php if ((object) ('showAlertsModal') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showAlertsModal'->value()); ?>')<?php echo e('showAlertsModal'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showAlertsModal'); ?>')<?php endif; ?>,
    showModal: <?php if ((object) ('showModal') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showModal'->value()); ?>')<?php echo e('showModal'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('showModal'); ?>')<?php endif; ?>,
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

    
    <?php if (isset($component)) { $__componentOriginal98889a1f8ec018a5f75996283a91794b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal98889a1f8ec018a5f75996283a91794b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.patient-modal.alerts-modal','data' => ['showAlertsModal' => $showAlertsModal,'patientAlerts' => $patientAlerts,'currentPatient' => $currentPatient]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('patient-modal.alerts-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['showAlertsModal' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($showAlertsModal),'patientAlerts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($patientAlerts),'currentPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentPatient)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal98889a1f8ec018a5f75996283a91794b)): ?>
<?php $attributes = $__attributesOriginal98889a1f8ec018a5f75996283a91794b; ?>
<?php unset($__attributesOriginal98889a1f8ec018a5f75996283a91794b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal98889a1f8ec018a5f75996283a91794b)): ?>
<?php $component = $__componentOriginal98889a1f8ec018a5f75996283a91794b; ?>
<?php unset($__componentOriginal98889a1f8ec018a5f75996283a91794b); ?>
<?php endif; ?>

    
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
        
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showModal = false; $wire.closeModal();"></div>
        
        
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div
                class="relative bg-white flex flex-col overflow-hidden
                       w-full h-full
                       sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                       lg:w-[85vw] lg:h-[90vh] lg:max-w-[1600px]
                       shadow-2xl"
                x-data="{
                    activeTab: 'tab-s',
                    activeCpoeCategory: 'cpoe-exames',
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
                
                <!--[if BLOCK]><![endif]--><?php if($loadingPatient): ?>
                    <div class="absolute inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm">
                        <div class="flex flex-col items-center gap-4">
                            <div class="relative">
                                <div class="w-12 h-12 border-4 border-[#004D9D] border-t-transparent rounded-full animate-spin"></div>
                                <div class="absolute inset-0 w-12 h-12 border-4 border-[#004D9D]/20 border-t-transparent rounded-full animate-pulse"></div>
                            </div>
                            <span class="text-[#004D9D] font-medium text-sm">Carregando dados do paciente...</span>
                        </div>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                
                <div class="flex-shrink-0">
                    <?php if (isset($component)) { $__componentOriginal4bf6bb988fdfe580fbc23256011219b6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4bf6bb988fdfe580fbc23256011219b6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.patient-modal.header','data' => ['currentHospitalName' => $currentHospitalName,'currentPatient' => $currentPatient,'patientDetails' => $patientDetails]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('patient-modal.header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['currentHospitalName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentHospitalName),'currentPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentPatient),'patientDetails' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($patientDetails)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4bf6bb988fdfe580fbc23256011219b6)): ?>
<?php $attributes = $__attributesOriginal4bf6bb988fdfe580fbc23256011219b6; ?>
<?php unset($__attributesOriginal4bf6bb988fdfe580fbc23256011219b6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4bf6bb988fdfe580fbc23256011219b6)): ?>
<?php $component = $__componentOriginal4bf6bb988fdfe580fbc23256011219b6; ?>
<?php unset($__componentOriginal4bf6bb988fdfe580fbc23256011219b6); ?>
<?php endif; ?>
                </div>

                
                <div class="flex-shrink-0 border-b border-gray-200">
                    <?php if (isset($component)) { $__componentOriginalf5e3eb31ed4066b4d609e56376850514 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf5e3eb31ed4066b4d609e56376850514 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.patient-modal.tabs','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('patient-modal.tabs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf5e3eb31ed4066b4d609e56376850514)): ?>
<?php $attributes = $__attributesOriginalf5e3eb31ed4066b4d609e56376850514; ?>
<?php unset($__attributesOriginalf5e3eb31ed4066b4d609e56376850514); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf5e3eb31ed4066b4d609e56376850514)): ?>
<?php $component = $__componentOriginalf5e3eb31ed4066b4d609e56376850514; ?>
<?php unset($__componentOriginalf5e3eb31ed4066b4d609e56376850514); ?>
<?php endif; ?>
                </div>

                
                <div class="flex-1 bg-gray-50 relative overflow-hidden min-h-0">
                    
                    <div class="absolute inset-0">
                        
                        <div x-show="activeTab === 'tab-s'" 
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <?php if (isset($component)) { $__componentOriginal7d98fd0210ab584bddbed0ec9645a4a1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7d98fd0210ab584bddbed0ec9645a4a1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.patient-modal.content.sbar-situacao','data' => ['loadingPatient' => $loadingPatient,'currentPatient' => $currentPatient,'patientDetails' => $patientDetails]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('patient-modal.content.sbar-situacao'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loadingPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($loadingPatient),'currentPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentPatient),'patientDetails' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($patientDetails)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7d98fd0210ab584bddbed0ec9645a4a1)): ?>
<?php $attributes = $__attributesOriginal7d98fd0210ab584bddbed0ec9645a4a1; ?>
<?php unset($__attributesOriginal7d98fd0210ab584bddbed0ec9645a4a1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7d98fd0210ab584bddbed0ec9645a4a1)): ?>
<?php $component = $__componentOriginal7d98fd0210ab584bddbed0ec9645a4a1; ?>
<?php unset($__componentOriginal7d98fd0210ab584bddbed0ec9645a4a1); ?>
<?php endif; ?>
                        </div>

                        
                        <div x-show="activeTab === 'tab-b'" 
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <?php if (isset($component)) { $__componentOriginalcdde50ed7fba9ea869fd8fc0728335a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcdde50ed7fba9ea869fd8fc0728335a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.patient-modal.content.sbar-background','data' => ['loadingPatient' => $loadingPatient,'currentPatient' => $currentPatient,'patientDetails' => $patientDetails]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('patient-modal.content.sbar-background'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loadingPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($loadingPatient),'currentPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentPatient),'patientDetails' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($patientDetails)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcdde50ed7fba9ea869fd8fc0728335a2)): ?>
<?php $attributes = $__attributesOriginalcdde50ed7fba9ea869fd8fc0728335a2; ?>
<?php unset($__attributesOriginalcdde50ed7fba9ea869fd8fc0728335a2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcdde50ed7fba9ea869fd8fc0728335a2)): ?>
<?php $component = $__componentOriginalcdde50ed7fba9ea869fd8fc0728335a2; ?>
<?php unset($__componentOriginalcdde50ed7fba9ea869fd8fc0728335a2); ?>
<?php endif; ?>
                        </div>

                        
                        <div x-show="activeTab === 'tab-a'" 
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0"
                             style="display: none;">
                            <?php if (isset($component)) { $__componentOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.patient-modal.content.sbar-avaliacao','data' => ['loadingPatient' => $loadingPatient,'currentPatient' => $currentPatient,'patientDetails' => $patientDetails]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('patient-modal.content.sbar-avaliacao'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loadingPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($loadingPatient),'currentPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentPatient),'patientDetails' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($patientDetails)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c)): ?>
<?php $attributes = $__attributesOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c; ?>
<?php unset($__attributesOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c)): ?>
<?php $component = $__componentOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c; ?>
<?php unset($__componentOriginaldcb788dfdd3ddcea40c1ffd8d1adfe9c); ?>
<?php endif; ?>
                        </div>

                        
                        <div x-show="activeTab === 'tab-r'" 
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <?php if (isset($component)) { $__componentOriginal17f895e11d1d0760497c472b26dd09a1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal17f895e11d1d0760497c472b26dd09a1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.patient-modal.content.sbar-recomendacoes','data' => ['loadingPatient' => $loadingPatient,'currentPatient' => $currentPatient,'patientDetails' => $patientDetails]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('patient-modal.content.sbar-recomendacoes'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['loadingPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($loadingPatient),'currentPatient' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($currentPatient),'patientDetails' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($patientDetails)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal17f895e11d1d0760497c472b26dd09a1)): ?>
<?php $attributes = $__attributesOriginal17f895e11d1d0760497c472b26dd09a1; ?>
<?php unset($__attributesOriginal17f895e11d1d0760497c472b26dd09a1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal17f895e11d1d0760497c472b26dd09a1)): ?>
<?php $component = $__componentOriginal17f895e11d1d0760497c472b26dd09a1; ?>
<?php unset($__componentOriginal17f895e11d1d0760497c472b26dd09a1); ?>
<?php endif; ?>
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
</div><?php /**PATH /var/www/passagem-plantao/resources/views/livewire/patient-modal.blade.php ENDPATH**/ ?>