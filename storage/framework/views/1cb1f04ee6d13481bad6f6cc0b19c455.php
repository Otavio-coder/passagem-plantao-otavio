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

<div class="bg-[#004D9D] px-3 sm:px-6 py-3 sm:py-4 flex-shrink-0 rounded-none sm:rounded-t-2xl relative">
    <!-- Close button - Centralizado vertical e horizontal -->
    <button 
        @click="showModal = false; setTimeout(() => $wire.closeModal(), 150)"
        class="absolute top-1/2 right-3 sm:right-4 -translate-y-1/2 flex items-center justify-center text-white/70 hover:text-white transition-colors focus:outline-none bg-white/10 hover:bg-white/20 rounded-full p-2 sm:p-1.5 z-10"
        style="min-height: 44px; min-width: 44px;"
    >
        <svg class="h-5 w-5 mx-auto block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
    
    <!-- Main Header Content -->
    <div class="pr-0 sm:pr-12">
        <!-- Logo and Title Row -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-2 sm:mb-3 gap-2 sm:gap-0">
            <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 w-full">
                <img src="<?php echo e(asset('images/santacasa-horizontal-branco.svg')); ?>" 
                     alt="Santa Casa de Porto Alegre" 
                     class="h-6 sm:h-8 md:h-7 w-auto opacity-90 mb-2 sm:mb-0">
                <div class="sm:border-l sm:border-white/30 sm:pl-4 mt-2 sm:mt-0 w-full flex flex-col justify-center">
                    <h3 class="
                        text-xs
                        sm:text-sm
                        md:text-[0.70rem]
                        lg:text-base
                        font-medium text-blue-100 leading-tight whitespace-nowrap
                        text-center sm:text-left
                        "
                    >
                        <span class="sm:hidden block">Comunicação SBAR - Passagem de Plantão</span>
                        <span class="hidden sm:block">
                            <span class="hidden md:inline">Modelo de Comunicação SBAR para Passagem de Plantão de Enfermeiros</span>
                            <span class="md:hidden block">Modelo SBAR para Passagem de Plantão</span>
                        </span>
                    </h3>
                </div>
            </div>
        </div>
        
        <!-- Information Grid - 2x2 centralizada em telas menores, linha única em telas grandes -->
        <div class="w-full flex justify-center">
            <div class="
                w-full
                max-w-xs sm:max-w-md md:max-w-lg lg:max-w-5xl
                grid
                gap-2
                grid-cols-2
                sm:grid-cols-2
                md:grid-cols-2
                lg:grid-cols-4
                text-[0.65rem] sm:text-xs md:text-[0.70rem] lg:text-sm
                text-blue-100
                items-center
            ">
                <!-- Hospital -->
                <div class="flex flex-row items-center min-w-0 w-full">
                    <span class="font-medium opacity-80 text-right w-[90px] sm:w-[110px] md:w-[110px] lg:w-[130px] whitespace-nowrap">Hospital:</span>
                    <span class="text-white font-medium whitespace-nowrap text-left ml-2 flex-1"><?php echo e($currentHospitalName ?? 'Carregando...'); ?></span>
                </div>
                <!-- Data -->
                <div class="flex flex-row items-center min-w-0 w-full">
                    <span class="font-medium opacity-80 text-right w-[90px] sm:w-[110px] md:w-[110px] lg:w-[130px] whitespace-nowrap">Data:</span>
                    <span class="text-white font-medium font-mono whitespace-nowrap text-left ml-2 flex-1"><?php echo e(date('d/m/Y')); ?></span>
                </div>
                <!-- Prontuário -->
                <!--[if BLOCK]><![endif]--><?php if($currentPatient && $currentPatient['has_patient'] && $patientDetails): ?>
                <div class="flex flex-row items-center min-w-0 w-full">
                    <span class="font-medium opacity-80 text-right w-[90px] sm:w-[110px] md:w-[110px] lg:w-[130px] whitespace-nowrap">Prontuário:</span>
                    <span class="text-white font-mono font-medium whitespace-nowrap text-left ml-2 flex-1"><?php echo e($patientDetails->nr_prontuario ?? 'N/A'); ?></span>
                </div>
                <!-- Atendimento -->
                <div class="flex flex-row items-center min-w-0 w-full">
                    <span class="font-medium opacity-80 text-right w-[90px] sm:w-[110px] md:w-[110px] lg:w-[130px] whitespace-nowrap">Atendimento:</span>
                    <span class="text-white font-mono font-medium whitespace-nowrap text-left ml-2 flex-1"><?php echo e($currentPatient['nr_atendimento']); ?></span>
                </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
            </div>
        </div>
    </div>
</div><?php /**PATH /var/www/passagem-plantao/resources/views/components/patient-modal/header.blade.php ENDPATH**/ ?>