<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'loadingPatient' => false,
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
    'loadingPatient' => false,
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

<div x-show="activeTab === 'tab-s'" class="p-3 sm:p-6">
    <!--[if BLOCK]><![endif]--><?php if($loadingPatient): ?>
        <div class="flex flex-col items-center justify-center py-12 sm:py-20">
            <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center" style="top: 50%;">
                <i class="fas fa-spinner fa-3x animate-spin"></i>
            </span>
            <p class="text-gray-700 text-lg sm:text-xl">Carregando detalhes do paciente...</p>
        </div>
    <?php elseif($currentPatient && (is_array($currentPatient) ? (isset($currentPatient['has_patient']) && !$currentPatient['has_patient']) : (property_exists($currentPatient, 'has_patient') && !$currentPatient->has_patient))): ?>
        <!-- Empty Bed -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-gray-700 text-base sm:text-lg">Leito Vazio</p>
            <p class="text-gray-500 mt-2 text-sm sm:text-base">Este leito não possui paciente internado no momento.</p>
        </div>
    <?php elseif(isset($patientDetails) && $patientDetails): ?>
        <!-- Situação - O que está acontecendo no momento? -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
            <h4 class="text-lg sm:text-xl font-bold text-gray-800 border-b border-gray-200 pb-3 mb-6 flex items-center">
                <span class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-[#007D44] text-white mr-3 text-sm sm:text-base font-bold">S</span>
                <div>
                    <span class="text-base sm:text-lg">SITUAÇÃO</span>
                    <p class="text-xs text-gray-500 font-normal mt-1">O que está acontecendo no momento?</p>
                </div>
            </h4>
            
            <div class="space-y-5">
                <!-- Identificação do Paciente -->
                <div>
                    <h5 class="text-sm font-medium text-gray-800 text-gray-800 mb-4 border-l-4 border-blue-500 pl-3 bg-blue-50 py-2 rounded-r">
                        Identificação do Paciente
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                        <div class="lg:col-span-2 bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Nome Completo:</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo e(isset($patientDetails->nm_pessoa_fisica) ? $patientDetails->nm_pessoa_fisica : 'Não informado'); ?></p>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Data de Nascimento:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                <!--[if BLOCK]><![endif]--><?php if(isset($patientDetails->birth_date) && $patientDetails->birth_date): ?>
                                    <?php echo e($patientDetails->birth_date); ?>

                                <?php else: ?>
                                    Não informado
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Idade:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                <!--[if BLOCK]><![endif]--><?php if(isset($patientDetails->age_detailed) && $patientDetails->age_detailed): ?>
                                    <?php echo e($patientDetails->age_detailed); ?>

                                <?php elseif(isset($patientDetails->age) && $patientDetails->age !== null): ?>
                                    <?php echo e($patientDetails->age); ?>a
                                <?php else: ?>
                                    N/A
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Sexo:</label>
                            <p class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                                <!--[if BLOCK]><![endif]--><?php if(isset($patientDetails->sexo) && $patientDetails->sexo === 'F'): ?>
                                    <span class="inline-flex items-center justify-center text-pink-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <line x1="12" y1="12" x2="12" y2="20" stroke="currentColor" stroke-width="2"/>
                                            <line x1="9" y1="17" x2="15" y2="17" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs text-gray-500 font-normal">F</span>
                                <?php elseif(isset($patientDetails->sexo) && $patientDetails->sexo === 'M'): ?>
                                    <span class="inline-flex items-center justify-center text-blue-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                            <line x1="16" y1="8" x2="20" y2="4" stroke="currentColor" stroke-width="2"/>
                                            <line x1="20" y1="4" x2="20" y2="8" stroke="currentColor" stroke-width="2"/>
                                            <line x1="20" y1="4" x2="16" y2="4" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs text-gray-500 font-normal">M</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-500 font-normal">N/A</span>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Tempo Internação:</label>
                            <p class="text-sm font-semibold text-gray-800">
                                <!--[if BLOCK]><![endif]--><?php if(!isset($patientDetails->internment_days) || $patientDetails->internment_days === null): ?>
                                    <span class="text-gray-500">N/A</span>
                                <?php elseif(is_numeric($patientDetails->internment_days) && $patientDetails->internment_days >= 0 && $patientDetails->internment_days < 1): ?>
                                    <span class="text-green-600 font-bold">Hoje</span>
                                <?php else: ?>
                                    <?php $days = ceil($patientDetails->internment_days); ?>
                                    <?php echo e($days); ?>d
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </p>
                        </div>
                    </div>
                    <!-- Médico Responsável e Convênio em uma linha compacta -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                        <div class="lg:col-span-2 bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Médico Responsável:</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo e(isset($patientDetails->medico_responsavel) ? $patientDetails->medico_responsavel : 'Não informado'); ?></p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Convênio:</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo e(isset($patientDetails->convenio) ? $patientDetails->convenio : 'Não informado'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Avaliações e Status Clínico -->
                <div>
                    <h5 class="text-sm font-medium text-gray-800 text-gray-800 mb-4 border-l-4 border-green-500 pl-3 bg-green-50 py-2 rounded-r">
                        Avaliações e Status Clínico
                    </h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Medida Bloqueio:</label>
                            <p class="text-sm font-medium text-gray-800 <?php echo e((isset($patientDetails->medida_bloqueio) && $patientDetails->medida_bloqueio === 'Sim') ? 'text-red-700' : 'text-green-700'); ?>">
                                <?php echo e(isset($patientDetails->medida_bloqueio) ? $patientDetails->medida_bloqueio : 'N/A'); ?>

                            </p>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Avaliação ENF.:</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo e(isset($patientDetails->avaliacao_enf) ? (strlen($patientDetails->avaliacao_enf) > 20 ? substr($patientDetails->avaliacao_enf, 0, 20) . '...' : $patientDetails->avaliacao_enf) : 'N/A'); ?></p>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Plano Educ.:</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo e(isset($patientDetails->plano_educ) ? (strlen($patientDetails->plano_educ) > 20 ? substr($patientDetails->plano_educ, 0, 20) . '...' : $patientDetails->plano_educ) : 'N/A'); ?></p>
                        </div>
                    </div>
                    <!-- PE, Diag. ENF. e Queda lado a lado -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">PE:</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo e(isset($patientDetails->pe_data) ? $patientDetails->pe_data : 'Não realizado'); ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Diag. ENF.:</label>
                            <p class="text-sm font-semibold text-gray-800"><?php echo e(isset($patientDetails->diag) ? $patientDetails->diag : 'Não informado'); ?></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Queda:</label>
                            <p class="text-sm font-medium text-gray-800 <?php echo e((isset($patientDetails->ds_queda) && $patientDetails->ds_queda !== 'Não') ? 'text-red-700' : 'text-green-700'); ?>">
                                <?php echo e(isset($patientDetails->ds_queda) ? $patientDetails->ds_queda : 'Não avaliado'); ?>

                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Escalas de Avaliação -->
                <div>
                <h5 class="text-sm font-medium text-gray-800 mb-4 border-l-4 border-purple-500 pl-3 bg-purple-50 py-2 rounded-r">
                    Escalas de Avaliação
                </h5>
                
                <!-- Legenda de Risco -->
                <div class="mb-4 p-3 bg-gray-50 rounded-lg border">
                    <div class="grid grid-cols-4 gap-3 text-xs">
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-red-100 border border-red-300 rounded"></div>
                            <span class="text-gray-700 font-bold">Alto Risco</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-yellow-100 border border-yellow-300 rounded"></div>
                            <span class="text-gray-700 font-bold">Risco Moderado</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-gray-50 border border-gray-300 rounded"></div>
                            <span class="text-gray-700 font-bold">Baixo Risco</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-3 h-3 bg-green-50 border border-green-300 rounded"></div>
                            <span class="text-gray-700 font-bold">Sem Risco</span>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php
                        $isPediatricPatient = isset($patientDetails->age) && intval($patientDetails->age) < 16;
                    ?>
                    
                    
                    <!--[if BLOCK]><![endif]--><?php if(!$isPediatricPatient): ?>
                        <?php
                            $mewsScore = $patientDetails->mews_score ?? null;
                            $mewsStyling = $patientDetails->mews_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                            $mewsIncreased = isset($patientDetails->mews_increased) ? $patientDetails->mews_increased : false;
                            $mewsTimestamp = $patientDetails->mews_timestamp ?? null;
                            $mewsClassification = $patientDetails->mews_classification ?? null;
                        ?>
                        <div class="<?php echo e($mewsStyling['bg']); ?> p-4 rounded-lg border <?php echo e($mewsStyling['border']); ?> relative">
                            <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center">
                                MEWS (Modified Early Warning Score):
                                <!--[if BLOCK]><![endif]--><?php if($mewsIncreased): ?>
                                    <span class="ml-2 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </label>
                            <p class="text-sm font-medium <?php echo e($mewsStyling['text']); ?>">
                                <!--[if BLOCK]><![endif]--><?php if($mewsScore !== null): ?>
                                    Score: <?php echo e($mewsScore); ?>

                                    <!--[if BLOCK]><![endif]--><?php if($mewsClassification): ?>
                                        (<?php echo e($mewsClassification); ?>)
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    <!--[if BLOCK]><![endif]--><?php if($mewsTimestamp): ?>
                                        <br><small class="text-xs text-gray-500"><?php echo e($mewsTimestamp); ?></small>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <?php else: ?>
                                    Não avaliado
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </p>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    
                    
                    <!--[if BLOCK]><![endif]--><?php if($isPediatricPatient): ?>
                        <?php
                            $pewsScore = $patientDetails->pews_score ?? null;
                            $pewsStyling = $patientDetails->pews_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                            $pewsIncreased = isset($patientDetails->pews_increased) ? $patientDetails->pews_increased : false;
                            $pewsTimestamp = $patientDetails->pews_timestamp ?? null;
                            $pewsClassification = $patientDetails->pews_classification ?? null;
                        ?>
                        <div class="<?php echo e($pewsStyling['bg']); ?> p-4 rounded-lg border <?php echo e($pewsStyling['border']); ?> relative">
                            <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center">
                                PEWS (Pediatric Early Warning Score):
                                <!--[if BLOCK]><![endif]--><?php if($pewsIncreased): ?>
                                    <span class="ml-2 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </label>
                            <p class="text-sm font-medium <?php echo e($pewsStyling['text']); ?>">
                                <!--[if BLOCK]><![endif]--><?php if($pewsScore !== null): ?>
                                    Score: <?php echo e($pewsScore); ?>

                                    <!--[if BLOCK]><![endif]--><?php if($pewsClassification): ?>
                                        (<?php echo e($pewsClassification); ?>)
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    <!--[if BLOCK]><![endif]--><?php if($pewsTimestamp): ?>
                                        <br><small class="text-xs text-gray-500"><?php echo e($pewsTimestamp); ?></small>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <?php else: ?>
                                    Não avaliado
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </p>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    
                    
                    <?php
                        $bradenScore = $patientDetails->braden_score ?? null;
                        $bradenStyling = $patientDetails->braden_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                        $bradenIncreased = isset($patientDetails->braden_increased) ? $patientDetails->braden_increased : false;
                    ?>
                    <div class="<?php echo e($bradenStyling['bg']); ?> p-4 rounded-lg border <?php echo e($bradenStyling['border']); ?> relative">
                        <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center">
                            Braden (Escala de Braden):
                            <!--[if BLOCK]><![endif]--><?php if($bradenIncreased): ?>
                                <span class="ml-2 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </label>
                        <p class="text-sm font-medium <?php echo e($bradenStyling['text']); ?>">
                            <!--[if BLOCK]><![endif]--><?php if($patientDetails->braden_score !== null): ?>
                                Score: <?php echo e($patientDetails->braden_score); ?>

                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->braden_classification): ?>
                                    (<?php echo e($patientDetails->braden_classification); ?>)
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->braden_timestamp): ?>
                                    <br><small class="text-xs text-gray-500"><?php echo e($patientDetails->braden_timestamp); ?></small>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <?php else: ?>
                                Não avaliado
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </p>
                    </div>
                    
                    
                    <?php
                        // Usar diretamente o valor numérico do score
                        $morseScore = $patientDetails->morse_score ?? null;
                        $morseStyling = $patientDetails->morse_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                        $morseIncreased = isset($patientDetails->morse_increased) ? $patientDetails->morse_increased : false;
                    ?>
                    <div class="<?php echo e($morseStyling['bg']); ?> p-4 rounded-lg border <?php echo e($morseStyling['border']); ?> relative">
                        <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center">
                            Morse (Risco de Queda):
                            <!--[if BLOCK]><![endif]--><?php if($morseIncreased): ?>
                                <span class="ml-2 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </label>
                        <p class="text-sm font-medium <?php echo e($morseStyling['text']); ?>">
                            <!--[if BLOCK]><![endif]--><?php if($patientDetails->morse_score !== null): ?>
                                Score: <?php echo e($patientDetails->morse_score); ?>

                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->morse_classification): ?>
                                    (<?php echo e($patientDetails->morse_classification); ?>)
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->morse_timestamp): ?>
                                    <br><small class="text-xs text-gray-500"><?php echo e($patientDetails->morse_timestamp); ?></small>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <?php else: ?>
                                Não avaliado
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </p>
                    </div>
                    
                    
                    <?php
                        // Usar diretamente o valor numérico do score
                        $dorScore = $patientDetails->dor_score ?? null;
                        $dorStyling = $patientDetails->dor_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                        $dorIncreased = isset($patientDetails->dor_increased) ? $patientDetails->dor_increased : false;
                    ?>
                    <div class="<?php echo e($dorStyling['bg']); ?> p-4 rounded-lg border <?php echo e($dorStyling['border']); ?> relative">
                        <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center">
                            Dor (Pain Scale):
                            <!--[if BLOCK]><![endif]--><?php if($dorIncreased): ?>
                                <span class="ml-2 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </label>
                        <p class="text-sm font-medium <?php echo e($dorStyling['text']); ?>">
                            <!--[if BLOCK]><![endif]--><?php if($patientDetails->dor_score !== null): ?>
                                Score: <?php echo e($patientDetails->dor_score); ?>

                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->dor_classification): ?>
                                    (<?php echo e($patientDetails->dor_classification); ?>)
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->dor_timestamp): ?>
                                    <br><small class="text-xs text-gray-500"><?php echo e($patientDetails->dor_timestamp); ?></small>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <?php else: ?>
                                Não avaliado
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </p>
                    </div>
                    
                    
                    <?php
                        // Usar diretamente o valor numérico do score
                        $tevScore = $patientDetails->tev_score ?? null;
                        $tevStyling = $patientDetails->tev_styling ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                        $tevIncreased = isset($patientDetails->tev_increased) ? $patientDetails->tev_increased : false;
                    ?>
                    <div class="<?php echo e($tevStyling['bg']); ?> p-4 rounded-lg border <?php echo e($tevStyling['border']); ?> relative">
                        <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center">
                            TEV (Tromboembolismo Venoso):
                            <!--[if BLOCK]><![endif]--><?php if($tevIncreased): ?>
                                <span class="ml-2 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </label>
                        <p class="text-sm font-medium <?php echo e($tevStyling['text']); ?>">
                            <!--[if BLOCK]><![endif]--><?php if($patientDetails->tev_score !== null): ?>
                                Score: <?php echo e($patientDetails->tev_score); ?>

                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->tev_classification): ?>
                                    (<?php echo e($patientDetails->tev_classification); ?>)
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <!--[if BLOCK]><![endif]--><?php if($patientDetails->tev_timestamp): ?>
                                    <br><small class="text-xs text-gray-500"><?php echo e($patientDetails->tev_timestamp); ?></small>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <?php else: ?>
                                Não avaliado
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </p>
                    </div>
                </div>
                
                </div> 
            </div>
        </div>
    <?php else: ?>
        <!-- Error State -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-red-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-gray-700 text-base sm:text-lg">Erro ao carregar detalhes do paciente</p>
            
            <button 
                wire:click="showPatientDetails(
                    '<?php echo e(is_array($currentPatient) ? ($currentPatient['nr_atendimento'] ?? '') : (is_object($currentPatient) && property_exists($currentPatient, 'nr_atendimento') ? $currentPatient->nr_atendimento : '')); ?>'
                )"
                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
            >
                Tentar novamente
            </button>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div><?php /**PATH /var/www/passagem-plantao/resources/views/components/patient-modal/content/sbar-situacao.blade.php ENDPATH**/ ?>