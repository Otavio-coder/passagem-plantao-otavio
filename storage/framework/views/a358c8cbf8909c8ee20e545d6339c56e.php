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

<div class="p-3 sm:p-4 lg:p-6 h-full overflow-y-auto">
    <!--[if BLOCK]><![endif]--><?php if($loadingPatient): ?>
        <div class="flex flex-col items-center justify-center py-12 sm:py-20">
            <span class="text-blue-500 opacity-75">
                <i class="fas fa-spinner fa-3x animate-spin"></i>
            </span>
            <p class="text-gray-700 text-lg sm:text-xl mt-4">Carregando detalhes do paciente...</p>
        </div>
    <?php elseif($currentPatient && (isset($currentPatient['has_patient']) && !$currentPatient['has_patient'])): ?>
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-gray-700 text-base sm:text-lg">Leito Vazio</p>
            <p class="text-gray-500 mt-2 text-sm sm:text-base">Este leito não possui paciente internado no momento.</p>
        </div>
    <?php elseif($patientDetails): ?>
        <!-- Recomendações -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
            <h4 class="text-lg sm:text-xl font-bold text-gray-800 border-b border-gray-200 pb-3 mb-6 flex items-center">
                <span class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-[#28a745] text-white mr-3 text-sm sm:text-base font-bold">R</span>
                <div>
                    <span class="text-base sm:text-lg">RECOMENDAÇÕES</span>
                    <p class="text-xs text-gray-500 font-normal mt-1">Orientações e condutas necessárias</p>
                </div>
            </h4>
            
            <!-- Cards em grid compacto -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
                <!-- Exames Prioritários -->
                <div class="bg-white p-3 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                    <h5 class="text-sm font-medium text-gray-800 mb-2 flex items-center">
                        <svg class="w-4 h-4 rounded-full bg-yellow-500 mr-2 text-black flex-shrink-0" viewBox="0 0 24 24" fill="none">
                            <path d="M15.2501 6.5C16.4927 6.5 17.5001 5.49264 17.5001 4.25C17.5001 3.00736 16.4927 2 15.2501 2C14.0074 2 13.0001 3.00736 13.0001 4.25C13.0001 5.49264 14.0074 6.5 15.2501 6.5Z" fill="currentColor"/>
                            <path d="M12.3827 6.49876C10.8875 6.28944 7.47101 6.89609 6.06373 10.6488C5.86981 11.166 6.13181 11.7424 6.64893 11.9363C7.16605 12.1302 7.74247 11.8682 7.93639 11.3511C8.5197 9.7956 9.57155 9.03454 10.5097 8.69638L9.34067 11.7021C9.32145 11.7515 9.30642 11.8015 9.29542 11.8518C9.20171 12.1529 9.25147 12.4933 9.45894 12.7616L13.0211 17.3687L13.252 21.0623C13.2864 21.6135 13.7612 22.0325 14.3124 21.998C14.8636 21.9636 15.2826 21.4888 15.2481 20.9376L14.9789 16.6312L12.8861 13.9244L14.2594 11.2629L14.3519 11.3973C14.8887 12.1774 15.8991 12.4741 16.7725 12.1081L18.8866 11.2222C19.3959 11.0087 19.6358 10.4228 19.4224 9.91341C19.2089 9.40404 18.6229 9.16415 18.1136 9.3776L15.9995 10.2635L14.393 7.92894C14.0375 7.31458 13.4664 6.81797 12.7317 6.5684C12.6163 6.52917 12.4991 6.50636 12.3827 6.49876Z" fill="currentColor"/>
                        </svg>
                        Exames Prioritários
                    </h5>
                    <div class="text-sm text-gray-700 p-2 bg-gray-50 rounded border min-h-[60px]">
                        <?php echo e($patientDetails->prioridade_exames ?? 'Nenhum exame prioritário'); ?>

                    </div>
                </div>
                
                <!-- Antimicrobianos -->
                <div class="bg-white p-3 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                    <h5 class="text-sm font-medium text-gray-800 mb-2 flex items-center">
                        <svg class="w-4 h-4 flex-shrink-0 text-blue-950 mr-2" viewBox="0 0 48 48" fill="none">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M9 7C9 5.34315 10.3431 4 12 4H36C37.6569 4 39 5.34315 39 7V15C39 16.6569 37.6569 18 36 18V41C36 42.6569 34.6569 44 33 44H15C13.3431 44 12 42.6569 12 41L12 18C10.3431 18 9 16.6569 9 15V7ZM16 16L16 6H12C11.4477 6 11 6.44772 11 7V15C11 15.5523 11.4477 16 12 16H16ZM18 16H23L23 6H18V16ZM25 16H30V6H25V16ZM32 16H36C36.5523 16 37 15.5523 37 15V7C37 6.44772 36.5523 6 36 6H32V16ZM23 30V35H25V30H30V28H25V23H23V28H18V30H23Z" fill="currentColor"/>
                        </svg>
                        Antimicrobianos
                    </h5>
                    <div class="text-sm text-gray-700 p-2 bg-gray-50 rounded border min-h-[60px]">
                        <?php echo e($patientDetails->materiais ?? 'Nenhum antimicrobiano'); ?>

                    </div>
                </div>

                <!-- Procedimentos Cirúrgicos -->
                <div class="bg-white p-3 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                    <h5 class="text-sm font-medium text-gray-800 mb-2 flex items-center">
                        <svg class="w-4 h-4 flex-shrink-0 text-purple-950 mr-2" viewBox="0 0 48 48" fill="none">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M40 8H8V40H40V8ZM8 6C6.89543 6 6 6.89543 6 8V40C6 41.1046 6.89543 42 8 42H40C41.1046 42 42 41.1046 42 40V8C42 6.89543 41.1046 6 40 6H8Z" fill="currentColor"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                        </svg>
                        Cirurgias
                    </h5>
                    <div class="text-sm text-gray-700 p-2 bg-gray-50 rounded border min-h-[60px]">
                        <!--[if BLOCK]><![endif]--><?php if($patientDetails && isset($patientDetails->procedimentos_cirurgicos) && is_array($patientDetails->procedimentos_cirurgicos) && count($patientDetails->procedimentos_cirurgicos)): ?>
                            <?php $firstProc = $patientDetails->procedimentos_cirurgicos[0]; ?>
                            <div class="font-medium"><?php echo e($firstProc['procedimento'] ?? 'Procedimento'); ?></div>
                            <div class="text-xs text-gray-600 mt-1">
                                <?php echo e($firstProc['data_agenda'] ?? 'Data não informada'); ?>

                                <!--[if BLOCK]><![endif]--><?php if(count($patientDetails->procedimentos_cirurgicos) > 1): ?>
                                    <br><span class="italic">+<?php echo e(count($patientDetails->procedimentos_cirurgicos) - 1); ?> mais</span>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            </div>
                        <?php else: ?>
                            <span class="text-gray-500">Nenhuma cirurgia agendada</span>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>
            </div>
            
            <!-- CPOE Section -->
            <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 space-y-2 sm:space-y-0">
                    <h4 class="text-lg sm:text-xl font-semibold text-gray-800">Prescrições do Dia - <?php echo e(date('d/m/Y')); ?></h4>
                    <div class="text-sm text-gray-500 font-medium">CPOE</div>
                </div>
                
                <!-- Tabs CPOE -->
                <div class="border-b border-gray-200 mb-4">
                    <nav class="flex space-x-1 overflow-x-auto pb-2 scrollbar-hide">
                        <!-- Exames -->
                        <button @click.prevent="activeCpoeCategory = 'cpoe-exames'"
                                :class="activeCpoeCategory === 'cpoe-exames' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                            <div class="flex items-center space-x-1 sm:space-x-1.5">
                                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                <span>Exames</span>
                            </div>
                        </button>
                        
                        <!-- Medicamentos -->
                        <button @click.prevent="activeCpoeCategory = 'cpoe-medicamentos'"
                                :class="activeCpoeCategory === 'cpoe-medicamentos' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                            <div class="flex items-center space-x-1 sm:space-x-1.5">
                                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                <span>Medicamentos</span>
                            </div>
                        </button>
                        
                        <!-- Nutrição -->
                        <button @click.prevent="activeCpoeCategory = 'cpoe-nutricao'"
                                :class="activeCpoeCategory === 'cpoe-nutricao' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                            <div class="flex items-center space-x-1 sm:space-x-1.5">
                                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                                </svg>
                                <span>Nutrição</span>
                            </div>
                        </button>
                        
                        <!-- Recomendações - CORRIGIDO -->
                        <button @click.prevent="activeCpoeCategory = 'cpoe-recomendacoes'"
                                :class="activeCpoeCategory === 'cpoe-recomendacoes' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                            <div class="flex items-center space-x-1 sm:space-x-1.5">
                                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Recomendações</span>
                            </div>
                        </button>

                        <!-- Intervenções -->
                        <button @click.prevent="activeCpoeCategory = 'cpoe-intervencoes'"
                                :class="activeCpoeCategory === 'cpoe-intervencoes' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                                class="flex-shrink-0 px-2 sm:px-3 py-1.5 text-xs sm:text-sm font-medium rounded border-b-2 whitespace-nowrap transition-colors">
                            <div class="flex items-center space-x-1 sm:space-x-1.5">
                                <svg class="h-3 w-3 sm:h-3.5 sm:w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>Intervenções</span>
                            </div>
                        </button>
                    </nav>
                </div>
                
                <!-- CPOE Content -->
                <div class="min-h-[400px]">
                    <!-- Exames -->
                    <div x-show="activeCpoeCategory === 'cpoe-exames'" 
                         x-transition:enter="transition-opacity ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         style="display: none;">
                         <!--[if BLOCK]><![endif]--><?php if($patientDetails && isset($patientDetails->cpoe_procedures) && $patientDetails->cpoe_procedures['total_count'] > 0): ?>
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span><?php echo e($patientDetails->cpoe_procedures['total_count']); ?> procedimento(s) agendado(s)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto"><?php echo e(date('d/m/Y')); ?></span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3">
                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = ['MANHÃ', 'TARDE', 'NOITE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $shiftData = $patientDetails->cpoe_procedures['shifts'][$shift] ?? ['count' => 0, 'procedures' => []];
                                ?>
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide"><?php echo e($shift); ?></h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                <?php echo e($shiftData['count']); ?>

                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        <!--[if BLOCK]><![endif]--><?php if($shiftData['count'] > 0): ?>
                                            <div class="space-y-2">
                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $shiftData['procedures']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $procedure): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm">
                                                        <div class="text-xs font-medium text-gray-800 mb-2 leading-tight break-words">
                                                            <?php echo e($procedure['procedimento']); ?>

                                                        </div>
                                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-600 space-y-1 sm:space-y-0">
                                                            <span class="font-mono bg-white px-2 py-1 rounded border self-start"><?php echo e($procedure['horario']); ?></span>
                                                            <!--[if BLOCK]><![endif]--><?php if($procedure['is_completed']): ?>
                                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200 self-start sm:self-auto">
                                                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></span>
                                                                    Realizado
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200 self-start sm:self-auto">
                                                                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-1.5"></span>
                                                                    Pendente
                                                                </span>
                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                        </div>
                                                    </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhum procedimento</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhum exame ou procedimento agendado</p>
                            <p class="text-gray-500 text-xs">para o dia <?php echo e(date('d/m/Y')); ?></p>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                    
                    <!-- Medicamentos -->
                    <div x-show="activeCpoeCategory === 'cpoe-medicamentos'"
                         x-transition:enter="transition-opacity ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         style="display: none;">
                         <!--[if BLOCK]><![endif]--><?php if($patientDetails && isset($patientDetails->cpoe_medications) && $patientDetails->cpoe_medications['total_count'] > 0): ?>
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span><?php echo e($patientDetails->cpoe_medications['total_count']); ?> medicamento(s) prescrito(s)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto"><?php echo e(date('d/m/Y')); ?></span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3" x-data="{ selectedMedication: null }">
                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = ['MANHÃ', 'TARDE', 'NOITE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $shiftData = $patientDetails->cpoe_medications['shifts'][$shift] ?? ['count' => 0, 'medications' => []];
                                ?>
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide"><?php echo e($shift); ?></h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                <?php echo e($shiftData['count']); ?>

                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        <!--[if BLOCK]><![endif]--><?php if($shiftData['count'] > 0): ?>
                                            <div class="space-y-2">
                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $shiftData['medications']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $medication): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm cursor-pointer"
                                                         @click="selectedMedication = selectedMedication === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? null : '<?php echo e($shift); ?>_<?php echo e($index); ?>'">
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words">
                                                                    <?php echo e($medication['medicamento']); ?>

                                                                </div>
                                                                <div class="text-xs text-gray-600 mb-2">
                                                                    <span class="font-mono bg-white px-1.5 py-0.5 rounded border mr-2"><?php echo e($medication['horario']); ?></span>
                                                                    <span class="font-medium"><?php echo e($medication['dose']); ?></span>
                                                                </div>
                                                                
                                                                <!-- NEW: Display dynamic labels -->
                                                                <!--[if BLOCK]><![endif]--><?php if(!empty($medication['labels'])): ?>
                                                                    <div class="flex flex-wrap gap-1 mb-1">
                                                                        <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $medication['labels']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                                                <?php echo e($label); ?>

                                                                            </span>
                                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                                                    </div>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </div>
                                                            <div class="flex flex-col items-end space-y-1 ml-2">
                                                                <!--[if BLOCK]><![endif]--><?php if($medication['is_administered']): ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>
                                                                        Aplicado
                                                                    </span>
                                                                <?php elseif($medication['is_suspended']): ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 border border-red-200">
                                                                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full mr-1"></span>
                                                                        Suspenso
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200">
                                                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full mr-1"></span>
                                                                        Pendente
                                                                    </span>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                
                                                                <svg class="w-3 h-3 text-gray-400 transform transition-transform" 
                                                                     :class="selectedMedication === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? 'rotate-180' : ''" 
                                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                            </svg>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Detalhes expandidos -->
                                                        <div x-show="selectedMedication === '<?php echo e($shift); ?>_<?php echo e($index); ?>'" 
                                                             x-transition:enter="transition ease-out duration-200"
                                                             x-transition:enter-start="opacity-0 max-h-0"
                                                             x-transition:enter-end="opacity-100 max-h-96"
                                                             x-transition:leave="transition ease-in duration-150"
                                                             x-transition:leave-start="opacity-100 max-h-96"
                                                             x-transition:leave-end="opacity-0 max-h-0"
                                                             class="overflow-hidden mt-3 pt-3 border-t border-gray-200">
                                                            <div class="space-y-3">
                                                                <!-- Enhanced medication details -->
                                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                                    <div class="bg-white p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 mb-1">Administração</div>
                                                                        <div class="space-y-1">
                                                                            <div><span class="font-medium">Via:</span> <?php echo e($medication['via_aplicacao']); ?></div>
                                                                            <div><span class="font-medium">Dispensar:</span> <?php echo e($medication['dispensar']); ?></div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="bg-white p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 mb-1">Prescrição</div>
                                                                        <div class="space-y-1">
                                                                            <div><span class="font-medium">Nº:</span> <?php echo e($medication['nr_prescricao']); ?></div>
                                                                            <!--[if BLOCK]><![endif]--><?php if($medication['dt_prescricao']): ?>
                                                                                <div><span class="font-medium">Data:</span> <?php echo e(\Carbon\Carbon::parse($medication['dt_prescricao'])->format('d/m/Y H:i')); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <!--[if BLOCK]><![endif]--><?php if($medication['resumo_formatado']): ?>
                                                                    <div class="bg-gray-100 p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 text-xs mb-1">Resumo Formatado</div>
                                                                        <div class="text-xs text-gray-700 font-mono break-words leading-relaxed">
                                                                            <?php echo e($medication['resumo_formatado']); ?>

                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhum medicamento</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhum medicamento prescrito</p>
                            <p class="text-gray-500 text-xs">para o dia <?php echo e(date('d/m/Y')); ?></p>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                    
                    <!-- Nutrição - CORRIGIDO -->
                    <div x-show="activeCpoeCategory === 'cpoe-nutricao'"
                         x-transition:enter="transition-opacity ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         style="display: none;">
                         <!--[if BLOCK]><![endif]--><?php if($patientDetails && isset($patientDetails->cpoe_nutrition) && $patientDetails->cpoe_nutrition['total_count'] > 0): ?>
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span><?php echo e($patientDetails->cpoe_nutrition['total_count']); ?> prescrição(ões) nutricional(is)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto"><?php echo e(date('d/m/Y')); ?></span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3" x-data="{ selectedNutrition: null }">
                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = ['MANHÃ', 'TARDE', 'NOITE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $shiftData = $patientDetails->cpoe_nutrition['shifts'][$shift] ?? ['count' => 0, 'prescriptions' => []];
                                ?>
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide"><?php echo e($shift); ?></h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                <?php echo e($shiftData['count']); ?>

                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        <!--[if BLOCK]><![endif]--><?php if($shiftData['count'] > 0): ?>
                                            <div class="space-y-2">
                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $shiftData['prescriptions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $prescription): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm <?php echo e(($prescription['has_details'] ?? false) ? 'cursor-pointer' : ''); ?>"
                                                         <?php if(($prescription['has_details'] ?? false)): ?>
                                                             @click="selectedNutrition = selectedNutrition === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? null : '<?php echo e($shift); ?>_<?php echo e($index); ?>'"
                                                         <?php endif; ?>>
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words">
                                                                    <?php echo e($prescription['prescricao'] ?? 'Prescrição nutricional'); ?>

                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['is_jejum'] ?? false)): ?>
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200 ml-2">
                                                                            JEJUM
                                                                        </span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </div>
                                                                <div class="text-xs text-gray-600">
                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['periodo_completo'] ?? null)): ?>
                                                                        <span class="font-mono bg-white px-1.5 py-0.5 rounded border"><?php echo e($prescription['periodo_completo']); ?></span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['tipo_nutricao'] ?? null)): ?>
                                                                        <span class="ml-2 font-medium">Tipo: <?php echo e($prescription['tipo_nutricao']); ?></span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['volume'] ?? null)): ?>
                                                                        <span class="ml-2 font-medium"><?php echo e($prescription['volume']); ?>ml</span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['kcal_total'] ?? null)): ?>
                                                                        <span class="ml-2 font-medium"><?php echo e($prescription['kcal_total']); ?>kcal</span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </div>
                                                                
                                                                <!-- Special labels for nutrition -->
                                                                <div class="flex flex-wrap gap-1 mt-1">
                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['is_enteral'] ?? false)): ?>
                                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                                            Enteral
                                                                        </span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['is_especial'] ?? false)): ?>
                                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                                                                            Especial
                                                                        </span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <!--[if BLOCK]><![endif]--><?php if(($prescription['alergias_alimentares'] ?? null)): ?>
                                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                                                            Alergias
                                                                        </span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </div>
                                                            </div>
                                                            <div class="flex flex-col items-end space-y-1 ml-2">
                                                                <!--[if BLOCK]><![endif]--><?php if(($prescription['is_active'] ?? false)): ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>
                                                                        Ativo
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                                                        <span class="w-1.5 h-1.5 bg-gray-500 rounded-full mr-1"></span>
                                                                        Inativo
                                                                    </span>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                
                                                                <!--[if BLOCK]><![endif]--><?php if(($prescription['has_details'] ?? false)): ?>
                                                                    <svg class="w-3 h-3 text-gray-400 transform transition-transform" 
                                                                         :class="selectedNutrition === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? 'rotate-180' : ''" 
                                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                                    </svg>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Detalhes expandidos -->
                                                        <div x-show="selectedNutrition === '<?php echo e($shift); ?>_<?php echo e($index); ?>'" 
                                                             x-transition:enter="transition ease-out duration-200"
                                                             x-transition:enter-start="opacity-0 max-h-0"
                                                             x-transition:enter-end="opacity-100 max-h-96"
                                                             x-transition:leave="transition ease-in duration-150"
                                                             x-transition:leave-start="opacity-100 max-h-96"
                                                             x-transition:leave-end="opacity-0 max-h-0"
                                                             class="overflow-hidden mt-3 pt-3 border-t border-gray-200">
                                                            <div class="space-y-3">
                                                                <!-- Informações da prescrição -->
                                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                                                    <div class="bg-white p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 mb-1">Prescrição</div>
                                                                        <div class="space-y-1">
                                                                            <div><span class="font-medium">Nº:</span> <?php echo e($prescription['nr_sequencia'] ?? 'N/A'); ?></div>
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['nome_nutricionista'] ?? null)): ?>
                                                                                <div><span class="font-medium">Nutricionista:</span> <?php echo e($prescription['nome_nutricionista']); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['dt_liberacao'] ?? null)): ?>
                                                                                <div><span class="font-medium">Liberação:</span> <?php echo e(\Carbon\Carbon::parse($prescription['dt_liberacao'])->format('d/m/Y H:i')); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="bg-white p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 mb-1">Período & Valores</div>
                                                                        <div class="space-y-1">
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['data_inicio'] ?? null)): ?>
                                                                                <div><span class="font-medium">Início:</span> <?php echo e($prescription['data_inicio']); ?><?php echo e(($prescription['horario_inicio'] ?? null) ? ' às ' . $prescription['horario_inicio'] : ''); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['data_fim'] ?? null)): ?>
                                                                                <div><span class="font-medium">Fim:</span> <?php echo e($prescription['data_fim']); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['volume_total'] ?? null)): ?>
                                                                                <div><span class="font-medium">Volume Total:</span> <?php echo e($prescription['volume_total']); ?>ml</div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <?php if(($prescription['is_jejum'] ?? false) && (($prescription['tipo_jejum'] ?? null) || ($prescription['objetivo_jejum'] ?? null))): ?>
                                                                    <div class="bg-orange-50 p-2 rounded border border-orange-200">
                                                                        <div class="font-medium text-orange-800 text-xs mb-1">Informações do Jejum</div>
                                                                        <div class="text-xs text-orange-700 space-y-1">
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['tipo_jejum'] ?? null)): ?>
                                                                                <div><span class="font-medium">Tipo:</span> <?php echo e($prescription['tipo_jejum']); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['objetivo_jejum'] ?? null)): ?>
                                                                                <div><span class="font-medium">Objetivo:</span> <?php echo e($prescription['objetivo_jejum']); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                
                                                                <!--[if BLOCK]><![endif]--><?php if(($prescription['observacoes'] ?? null) || ($prescription['alergias_alimentares'] ?? null)): ?>
                                                                    <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                                        <div class="font-medium text-blue-800 text-xs mb-1">Observações e Alertas</div>
                                                                        <div class="text-xs text-blue-700 space-y-1">
                                                                            <?php if(($prescription['observacoes'] ?? null)): ?>
                                                                                <div><span class="font-medium">Observações:</span> <?php echo e($prescription['observacoes']); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                            <!--[if BLOCK]><![endif]--><?php if(($prescription['alergias_alimentares'] ?? null)): ?>
                                                                                <div class="text-red-700"><span class="font-medium">Alergias:</span> <?php echo e($prescription['alergias_alimentares']); ?></div>
                                                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                
                                                                <!--[if BLOCK]><![endif]--><?php if(($prescription['resumo_formatado'] ?? null)): ?>
                                                                    <div class="bg-gray-100 p-2 rounded border">
                                                                        <div class="font-medium text-gray-600 text-xs mb-1">Resumo Formatado</div>
                                                                        <div class="text-xs text-gray-700 font-mono break-words leading-relaxed">
                                                                            <?php echo e($prescription['resumo_formatado']); ?>

                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhuma prescrição nutricional</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16l3-3m-3 3l-3-3" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhuma prescrição nutricional</p>
                            <p class="text-gray-500 text-xs">para o dia <?php echo e(date('d/m/Y')); ?></p>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                
                    <!-- Recomendações - CORRIGIDO -->
                    <div x-show="activeCpoeCategory === 'cpoe-recomendacoes'"
                         x-transition:enter="transition-opacity ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         style="display: none;">
                         <!--[if BLOCK]><![endif]--><?php if($patientDetails && isset($patientDetails->cpoe_recommendations) && $patientDetails->cpoe_recommendations['total_count'] > 0): ?>
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span><?php echo e($patientDetails->cpoe_recommendations['total_count']); ?> recomendação(ões) ativa(s)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto"><?php echo e(date('d/m/Y')); ?></span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3" x-data="{ selectedRecommendation: null }">
                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = ['MANHÃ', 'TARDE', 'NOITE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $shiftData = $patientDetails->cpoe_recommendations['shifts'][$shift] ?? ['count' => 0, 'recommendations' => []];
                                ?>
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide"><?php echo e($shift); ?></h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                <?php echo e($shiftData['count']); ?>

                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        <!--[if BLOCK]><![endif]--><?php if($shiftData['count'] > 0): ?>
                                            <div class="space-y-2">
                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $shiftData['recommendations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $recommendation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm <?php echo e($recommendation['has_details'] ? 'cursor-pointer' : ''); ?>"
                                                         <?php if($recommendation['has_details']): ?>
                                                             @click="selectedRecommendation = selectedRecommendation === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? null : '<?php echo e($shift); ?>_<?php echo e($index); ?>'"
                                                         <?php endif; ?>>
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words">
                                                                    <!--[if BLOCK]><![endif]--><?php if($recommendation['tipo_recomendacao']): ?>
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 border border-indigo-200 mr-2">
                                                                            <?php echo e($recommendation['tipo_recomendacao']); ?>

                                                                        </span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <?php echo e(Str::limit($recommendation['recomendacao'], 100)); ?>

                                                                </div>
                                                                <div class="text-xs text-gray-600">
                                                                    <!--[if BLOCK]><![endif]--><?php if($recommendation['data_inicio']): ?>
                                                                        <span class="font-mono bg-white px-1.5 py-0.5 rounded border"><?php echo e($recommendation['data_inicio']); ?><?php echo e($recommendation['horario_inicio'] ? ' ' . $recommendation['horario_inicio'] : ''); ?></span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <!--[if BLOCK]><![endif]--><?php if($recommendation['nome_profissional']): ?>
                                                                        <span class="ml-2 font-medium"><?php echo e(Str::limit($recommendation['nome_profissional'], 20)); ?></span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </div>
                                                            </div>
                                                            <div class="flex flex-col items-end space-y-1 ml-2">
                                                                <!--[if BLOCK]><![endif]--><?php if($recommendation['is_active']): ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>
                                                                        Ativo
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                                                        <span class="w-1.5 h-1.5 bg-gray-500 rounded-full mr-1"></span>
                                                                        Inativo
                                                                    </span>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                
                                                                <!--[if BLOCK]><![endif]--><?php if($recommendation['has_details']): ?>
                                                                    <svg class="w-3 h-3 text-gray-400 transform transition-transform" 
                                                                         :class="selectedRecommendation === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? 'rotate-180' : ''" 
                                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                                    </svg>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Detalhes expandidos para recomendações -->
                                                        <!--[if BLOCK]><![endif]--><?php if($recommendation['has_details']): ?>
                                                            <div x-show="selectedRecommendation === '<?php echo e($shift); ?>_<?php echo e($index); ?>'" 
                                                                 x-transition:enter="transition ease-out duration-200"
                                                                 x-transition:enter-start="opacity-0 max-h-0"
                                                                 x-transition:enter-end="opacity-100 max-h-96"
                                                                 x-transition:leave="transition ease-in duration-150"
                                                                 x-transition:leave-start="opacity-100 max-h-96"
                                                                 x-transition:leave-end="opacity-0 max-h-0"
                                                                 class="overflow-hidden mt-3 pt-3 border-t border-gray-200">
                                                                <div class="space-y-3">
                                                                    <div class="bg-indigo-50 p-2 rounded border border-indigo-200">
                                                                        <div class="font-medium text-indigo-800 text-xs mb-1">Recomendação Completa</div>
                                                                        <div class="text-xs text-indigo-700 break-words leading-relaxed">
                                                                            <?php echo e($recommendation['recomendacao']); ?>

                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <!--[if BLOCK]><![endif]--><?php if($recommendation['observacoes']): ?>
                                                                        <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                                            <div class="font-medium text-blue-800 text-xs mb-1">Observações</div>
                                                                            <div class="text-xs text-blue-700 break-words leading-relaxed">
                                                                                <?php echo e($recommendation['observacoes']); ?>

                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    
                                                                    <!--[if BLOCK]><![endif]--><?php if($recommendation['resumo_formatado']): ?>
                                                                        <div class="bg-gray-100 p-2 rounded border">
                                                                            <div class="font-medium text-gray-600 text-xs mb-1">Resumo Formatado</div>
                                                                            <div class="text-xs text-gray-700 font-mono break-words leading-relaxed">
                                                                                <?php echo e($recommendation['resumo_formatado']); ?>

                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </div>
                                                            </div>
                                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                    </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhuma recomendação</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhuma recomendação ativa</p>
                            <p class="text-gray-500 text-xs">para o dia <?php echo e(date('d/m/Y')); ?></p>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>

                    <!-- Intervenções - CORRIGIDO -->
                    <div x-show="activeCpoeCategory === 'cpoe-intervencoes'"
                         x-transition:enter="transition-opacity ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         style="display: none;">
                         <!--[if BLOCK]><![endif]--><?php if($patientDetails && isset($patientDetails->cpoe_interventions) && $patientDetails->cpoe_interventions['total_count'] > 0): ?>
                        <div class="text-sm text-gray-600 mb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-1 sm:space-y-0">
                            <span><?php echo e($patientDetails->cpoe_interventions['total_count']); ?> intervenção(ões) ativa(s)</span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded self-start sm:self-auto"><?php echo e(date('d/m/Y')); ?></span>
                        </div>
                        
                        <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-3 lg:gap-3" x-data="{ selectedIntervention: null }">
                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = ['MANHÃ', 'TARDE', 'NOITE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shift): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $shiftData = $patientDetails->cpoe_interventions['shifts'][$shift] ?? ['count' => 0, 'interventions' => []];
                                ?>
                                
                                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="px-3 py-2.5 border-b border-gray-200 bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <h6 class="font-medium text-gray-800 text-sm uppercase tracking-wide"><?php echo e($shift); ?></h6>
                                            <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full font-medium">
                                                <?php echo e($shiftData['count']); ?>

                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 lg:max-h-80 lg:overflow-y-auto custom-scroll">
                                        <!--[if BLOCK]><![endif]--><?php if($shiftData['count'] > 0): ?>
                                            <div class="space-y-2">
                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $shiftData['interventions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $intervention): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <div class="bg-gray-50 rounded-lg border p-3 hover:bg-gray-100 transition-colors shadow-sm <?php echo e($intervention['has_details'] ? 'cursor-pointer' : ''); ?>"
                                                         <?php if($intervention['has_details']): ?>
                                                             @click="selectedIntervention = selectedIntervention === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? null : '<?php echo e($shift); ?>_<?php echo e($index); ?>'"
                                                         <?php endif; ?>>
                                                        <div class="flex items-start justify-between">
                                                            <div class="flex-1 min-w-0">
                                                                <div class="text-xs font-medium text-gray-800 mb-1 leading-tight break-words">
                                                                    <?php echo e($intervention['procedimento']); ?>

                                                                </div>
                                                                <div class="text-xs text-gray-600 mb-2">
                                                                    <!--[if BLOCK]><![endif]--><?php if($intervention['data_inicio']): ?>
                                                                        <span class="font-mono bg-white px-1.5 py-0.5 rounded border"><?php echo e($intervention['data_inicio']); ?><?php echo e($intervention['horario_inicio'] ? ' ' . $intervention['horario_inicio'] : ''); ?></span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    <!--[if BLOCK]><![endif]--><?php if($intervention['nome_profissional']): ?>
                                                                        <span class="ml-2 font-medium"><?php echo e(Str::limit($intervention['nome_profissional'], 20)); ?></span>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </div>
                                                                
                                                                <!-- Labels for intervention flags -->
                                                                <!--[if BLOCK]><![endif]--><?php if(!empty($intervention['labels'])): ?>
                                                                    <div class="flex flex-wrap gap-1 mb-1">
                                                                        <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $intervention['labels']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium 
                                                                                <?php echo e(str_contains(strtolower($label), 'urgente') ? 'bg-red-100 text-red-800 border-red-200' : 
                                                                                   (str_contains(strtolower($label), 'lado') ? 'bg-blue-100 text-blue-800 border-blue-200' : 
                                                                                    'bg-gray-100 text-gray-800 border-gray-200')); ?> border">
                                                                                <?php echo e($label); ?>

                                                                            </span>
                                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                                                    </div>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </div>
                                                            <div class="flex flex-col items-end space-y-1 ml-2">
                                                                <!--[if BLOCK]><![endif]--><?php if($intervention['is_active']): ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1"></span>
                                                                        Ativo
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                                                        <span class="w-1.5 h-1.5 bg-gray-500 rounded-full mr-1"></span>
                                                                        Inativo
                                                                    </span>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                                                                <!--[if BLOCK]><![endif]--><?php if($intervention['has_details']): ?>
                                                                    <svg class="w-3 h-3 text-gray-400 transform transition-transform" 
                                                                         :class="selectedIntervention === '<?php echo e($shift); ?>_<?php echo e($index); ?>' ? 'rotate-180' : ''" 
                                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                                    </svg>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Detalhes expandidos para intervenções -->
                                                        <!--[if BLOCK]><![endif]--><?php if($intervention['has_details']): ?>
                                                            <div x-show="selectedIntervention === '<?php echo e($shift); ?>_<?php echo e($index); ?>'" 
                                                                 x-transition:enter="transition ease-out duration-200"
                                                                 x-transition:enter-start="opacity-0 max-h-0"
                                                                 x-transition:enter-end="opacity-100 max-h-96"
                                                                 x-transition:leave="transition ease-in duration-150"
                                                                 x-transition:leave-start="opacity-100 max-h-96"
                                                                 x-transition:leave-end="opacity-0 max-h-0"
                                                                 class="overflow-hidden mt-3 pt-3 border-t border-gray-200">
                                                                <div class="space-y-3">
                                                                    <div class="bg-purple-50 p-2 rounded border border-purple-200">
                                                                        <div class="font-medium text-purple-800 text-xs mb-1">Procedimento Completo</div>
                                                                        <div class="text-xs text-purple-700 break-words leading-relaxed">
                                                                            <?php echo e($intervention['procedimento']); ?>

                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <!--[if BLOCK]><![endif]--><?php if($intervention['observacoes']): ?>
                                                                        <div class="bg-blue-50 p-2 rounded border border-blue-200">
                                                                            <div class="font-medium text-blue-800 text-xs mb-1">Observações</div>
                                                                            <div class="text-xs text-blue-700 break-words leading-relaxed">
                                                                                <?php echo e($intervention['observacoes']); ?>

                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                    
                                                                    <!--[if BLOCK]><![endif]--><?php if($intervention['resumo_formatado']): ?>
                                                                        <div class="bg-gray-100 p-2 rounded border">
                                                                            <div class="font-medium text-gray-600 text-xs mb-1">Resumo Formatado</div>
                                                                            <div class="text-xs text-gray-700 font-mono break-words leading-relaxed">
                                                                                <?php echo e($intervention['resumo_formatado']); ?>

                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </div>
                                                            </div>
                                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                    </div>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-center py-8">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                </svg>
                                                <div class="text-gray-500 text-sm">Nenhuma intervenção</div>
                                                <div class="text-gray-400 text-xs">neste turno</div>
                                            </div>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 bg-gray-50 rounded border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            </svg>
                            <p class="text-gray-600 text-sm font-medium">Nenhuma intervenção ativa</p>
                            <p class="text-gray-500 text-xs">para o dia <?php echo e(date('d/m/Y')); ?></p>
                        </div>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <svg class="w-12 h-12 sm:w-16 sm:h-16 text-red-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-gray-700 text-base sm:text-lg">Erro ao carregar detalhes do paciente</p>
        </div>
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div>

<style>
.custom-scroll {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}

.custom-scroll::-webkit-scrollbar {
    width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Garantir que divs ocultas não interfiram */
[x-show][style*="display: none"] {
    display: none !important;
    pointer-events: none !important;
}
</style><?php /**PATH /var/www/passagem-plantao/resources/views/components/patient-modal/content/sbar-recomendacoes.blade.php ENDPATH**/ ?>