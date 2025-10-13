<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'currentHospitalName' => null,
    'currentPatient' => null,
    'patientDetails' => null
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
    'currentHospitalName' => null,
    'currentPatient' => null,
    'patientDetails' => null
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<div class="bg-[#004D9D] px-4 sm:px-6 py-3 sm:py-4 relative">
    
    <button 
        @click="showModal = false; $wire.closeModal();"
        class="absolute top-1/2 -translate-y-1/2 right-3 sm:right-4 z-10 flex items-center justify-center w-10 h-10 sm:w-8 sm:h-8 text-white/70 hover:text-white transition-colors bg-white/10 hover:bg-white/20 rounded-full focus:outline-none focus:ring-2 focus:ring-white/50"
        aria-label="Fechar modal"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
    
    
    <div class="pr-12 sm:pr-10">
        
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 mb-3">
            <img src="<?php echo e(asset('images/santacasa-horizontal-branco.svg')); ?>" 
                 alt="Santa Casa" 
                 class="h-6 sm:h-7 w-auto opacity-90">
            
            <div class="sm:border-l sm:border-white/30 sm:pl-4">
                <h3 class="text-xs sm:text-sm text-blue-100 font-medium leading-tight">
                    Modelo de Comunicação SBAR para Passagem de Plantão
                </h3>
            </div>
        </div>
        
        
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 text-xs sm:text-sm text-blue-100">
            <div class="flex items-center gap-2">
                <span class="opacity-80 whitespace-nowrap">Hospital:</span>
                <span class="text-white font-medium truncate"><?php echo e($currentHospitalName ?? 'Carregando...'); ?></span>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="opacity-80 whitespace-nowrap">Data:</span>
                <span class="text-white font-medium font-mono"><?php echo e(date('d/m/Y')); ?></span>
            </div>
            
            <!--[if BLOCK]><![endif]--><?php if($currentPatient && $currentPatient['has_patient'] && $patientDetails): ?>
                <div class="flex items-center gap-2">
                    <span class="opacity-80 whitespace-nowrap">Prontuário:</span>
                    <span class="text-white font-medium font-mono"><?php echo e($patientDetails->nr_prontuario ?? 'N/A'); ?></span>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="opacity-80 whitespace-nowrap">Atendimento:</span>
                    <span class="text-white font-medium font-mono"><?php echo e($currentPatient['nr_atendimento']); ?></span>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
        </div>
    </div>
</div><?php /**PATH /var/www/passagem-plantao/resources/views/components/patient-modal/header.blade.php ENDPATH**/ ?>