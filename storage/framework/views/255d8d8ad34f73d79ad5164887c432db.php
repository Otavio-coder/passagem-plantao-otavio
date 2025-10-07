<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'showAlertsModal' => false,
    'patientAlerts' => [],
    'currentPatient' => null
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'showAlertsModal' => false,
    'patientAlerts' => [],
    'currentPatient' => null
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<!--[if BLOCK]><![endif]--><?php if($showAlertsModal && !empty($patientAlerts)): ?>
    <div class="fixed inset-0 z-[60] overflow-y-auto" 
         style="z-index: 9999 !important;"
         x-data="{ show: true }"
         x-show="show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        <!-- Backdrop com fechamento instantâneo -->
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"
             @click="show = false; setTimeout(() => $wire.set('showAlertsModal', false), 150)"></div>
        
        <!-- Alert Modal Content -->
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto transform transition-all"
                 @click.stop
                 x-show="show"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                
                <!-- Alert Header -->
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-4 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-white/20 rounded-full p-2 mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">ALERTAS ATIVOS</h3>
                                <p class="text-white/90 text-sm">Atendimento: <?php echo e($currentPatient['nr_atendimento'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        
                        <button 
                            @click="show = false; setTimeout(() => $wire.set('showAlertsModal', false), 150)"
                            class="text-white/80 hover:text-white transition-colors p-1 rounded-full hover:bg-white/10"
                            title="Fechar alertas"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Alert Content -->
                <div class="p-6 max-h-96 overflow-y-auto">
                    <div class="space-y-4">
                        <?php
                            $alertsByType = collect($patientAlerts)->groupBy('type');
                            $activeAlertsCount = collect($patientAlerts)->filter(function($alert) {
                                return !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                            })->count();
                        ?>
                        
                        <!--[if BLOCK]><![endif]--><?php if($activeAlertsCount === 0): ?>
                            <div class="text-center py-8">
                                <div class="text-gray-400 mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-gray-500">Todos os alertas expiraram</p>
                            </div>
                        <?php else: ?>
                            <?php $__currentLoopData = $alertsByType; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $alerts): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $activeAlerts = collect($alerts)->filter(function($alert) {
                                        return !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                                    });
                                ?>
                                
                                <!--[if BLOCK]><![endif]--><?php if($activeAlerts->count() > 0): ?>
                                    <div class="mb-6">
                                        <!-- Type Header -->
                                        <div class="flex items-center mb-3">
                                            <!--[if BLOCK]><![endif]--><?php if($type === 'ISOLAMENTO'): ?>
                                                <div class="bg-yellow-100 rounded-full p-2 mr-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-lg font-semibold text-yellow-800">Precauções de Isolamento</h4>
                                            <?php else: ?>
                                                <div class="bg-red-100 rounded-full p-2 mr-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-lg font-semibold text-red-800">Alertas do Paciente</h4>
                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                        </div>
                                        
                                        <!-- Alerts List -->
                                        <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $activeAlerts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $alert): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php
                                                $isActive = !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                                            ?>
                                            
                                            <!--[if BLOCK]><![endif]--><?php if($isActive): ?>
                                                <div class="border-l-4 <?php echo e($alert['type'] === 'ISOLAMENTO' ? 'border-yellow-500 bg-yellow-50' : 'border-red-500 bg-red-50'); ?> p-4 rounded-r-lg mb-3 shadow-sm">
                                                    <!-- Alert Header -->
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                            <?php echo e($alert['type'] === 'ISOLAMENTO' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                            <?php echo e($alert['type']); ?>

                                                        </span>
                                                        
                                                        <div class="text-xs text-gray-500">
                                                            <!--[if BLOCK]><![endif]--><?php if($alert['type'] === 'ISOLAMENTO' && isset($alert['start_date']) && $alert['start_date']): ?>
                                                                <span class="bg-white px-2 py-1 rounded mr-1">
                                                                    Início: <?php echo e(\Carbon\Carbon::parse($alert['start_date'])->format('d/m/Y')); ?>

                                                                </span>
                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            
                                                            <!--[if BLOCK]><![endif]--><?php if(isset($alert['end_date']) && $alert['end_date']): ?>
                                                                <?php
                                                                    $endDate = \Carbon\Carbon::parse($alert['end_date']);
                                                                    $isExpired = $endDate->isPast();
                                                                ?>
                                                                <!--[if BLOCK]><![endif]--><?php if(!$isExpired): ?>
                                                                    <span class="bg-white px-2 py-1 rounded">
                                                                        Até: <?php echo e($endDate->format('d/m/Y')); ?>

                                                                    </span>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            <?php else: ?>
                                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded font-medium">
                                                                    Ativo
                                                                </span>
                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Alert Message -->
                                                    <div class="text-sm <?php echo e($alert['type'] === 'ISOLAMENTO' ? 'text-yellow-800' : 'text-red-800'); ?> leading-relaxed text-justify bg-white/50 p-3 rounded border">
                                                        <?php echo e($alert['message']); ?>

                                                    </div>
                                                </div>
                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>
                
                <!-- Alert Footer -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-xl border-t">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-xs text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Total: <?php echo e($activeAlertsCount); ?> alerta(s) ativo(s)
                        </div>
                        
                        <button 
                            @click="show = false; setTimeout(() => $wire.set('showAlertsModal', false), 150)"
                            class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-sm transition-colors"
                            title="Fechar alertas"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?><!--[if ENDBLOCK]><![endif]--><?php /**PATH /var/www/passagem-plantao/resources/views/components/patient-modal/alerts-modal.blade.php ENDPATH**/ ?>