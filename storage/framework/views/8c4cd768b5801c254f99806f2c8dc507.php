

<div class="w-full my-2 text-[#004D9D] relative font-montserrat">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4 font-montserrat">
            
            
            <div
                class="relative font-montserrat"
                x-data="sbarDashboard()"
                x-init="init()"
                @modal-opened.window="modalOpen = true; document.body.classList.add('modal-open')"
                @modal-closed.window="modalOpen = false; document.body.classList.remove('modal-open')"
                @auto-refreshed.window="handleAutoRefresh()"
            >
                
                
                
                <div
                    wire:loading.delay.longer
                    class="fixed inset-0 z-[9998] flex items-center justify-center bg-[#004D9D]/20 font-montserrat"
                    role="status"
                    aria-live="polite"
                >
                    <div class="flex flex-col items-center justify-center space-y-2 min-h-screen font-montserrat">
                        <div class="w-12 h-12 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin" aria-hidden="true"></div>
                        <span class="text-[#004D9D] font-medium font-montserrat"><?php echo e($loadingMessage ?? 'Carregando...'); ?></span>
                    </div>
                </div>
                <div class="font-montserrat relative">
                    
                    
                    <div 
                        x-data="{ showAlert: true }"
                        x-show="showAlert"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-2"
                        class="mb-4 sm:mb-6 bg-blue-50 border-l-4 border-blue-400 p-3 sm:p-4 shadow-sm font-montserrat"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-start font-montserrat">
                            <div class="flex items-start font-montserrat">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3 flex-1 sm:mr-8 font-montserrat">
                                    <div class="text-xs sm:text-sm text-blue-800 leading-relaxed font-montserrat">
                                        <p class="mb-2 sm:mb-3 font-montserrat">
                                            <strong>Sistema SBAR - Passagem de Plantão:</strong> Este sistema é um <strong>visualizador de informações</strong>
                                            que apresenta dados dos pacientes de forma otimizada. <strong>Não substitui o Tasy</strong> e <strong>não realiza
                                            inserções diretas de dados</strong> no sistema ERP hospitalar.
                                        </p>
                                        <p class="text-xs sm:text-sm font-montserrat">
                                            <strong>Objetivo:</strong> Organizar informações por hospital, setor e leito, digitalizar dados tradicionalmente registrados
                                            no SBAR em papel e oferecer interface para avaliações de enfermagem por turno, reduzindo o uso de papel.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            
                            <div class="flex-shrink-0 mt-3 sm:mt-0 self-start sm:ml-4 font-montserrat">
                                <button @click="showAlert = false"
                                        class="text-blue-400 hover:text-blue-600 transition-colors p-1 font-montserrat">
                                    <svg class="h-4 w-4 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    
                    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">
                        
                        
                        <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 top-0 z-50 shadow-lg font-montserrat">
                            <div class="flex flex-col space-y-3 sm:space-y-2 font-montserrat">
                                
                                
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 font-montserrat">
                                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white font-montserrat text-center lg:text-left lg:flex-1 lg:min-w-0">Sistema SBAR - Passagem de Plantão</h1>

                                    <div class="flex items-center justify-center lg:justify-end gap-1 flex-shrink-0 font-montserrat">
                                        
                                        <!--[if BLOCK]><![endif]--><?php if($lastRefresh): ?>
                                            <span class="hidden sm:block text-white/80 text-xs font-montserrat mr-2">
                                                Última atualização: <?php echo e($lastRefresh); ?>

                                            </span>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                                        
                                        <div x-show="autoRefreshEnabled"
                                             class="flex items-center gap-1 bg-green-500/20 px-1.5 py-0.5 rounded-full font-montserrat">
                                            <div class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></div>
                                            <span class="text-green-200 text-xs font-medium font-montserrat hidden sm:inline">Auto-refresh</span>
                                            <span x-text="nextRefreshIn + 's'" class="text-green-200 text-xs font-montserrat"></span>
                                        </div>

                                        
                                        <button @click="toggleAutoRefresh()"
                                                class="hidden sm:inline-flex items-center justify-center px-2 py-1 rounded-md shadow-md font-medium transition text-xs focus:outline-none focus:ring-2 font-montserrat"
                                                :class="autoRefreshEnabled
                                                    ? 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500/40'
                                                    : 'bg-gray-100 text-gray-700 border border-gray-300 hover:bg-gray-200 focus:ring-gray-400/40'"
                                                title="Atualização automática a cada 10 minutos">
                                            <span x-text="autoRefreshEnabled ? 'Auto-refresh ON' : 'Auto-refresh OFF'"></span>
                                        </button>
                                    </div>
                                </div>

                                
                                <div class="w-full font-montserrat">
                                    <div class="flex flex-col lg:flex-row lg:items-end gap-6 lg:gap-8 font-montserrat">

                                        
                                        <div class="flex-1 min-w-0 font-montserrat">
                                            
                                            
                                            <div class="lg:hidden font-montserrat" x-data="{ filtersOpen: false }">
                                                <div class="flex items-center justify-center space-x-2 font-montserrat">
                                                    <button
                                                        @click="filtersOpen = !filtersOpen"
                                                        class="flex items-center justify-center px-2.5 py-2 bg-white/10 hover:bg-white/20 border border-white/30 rounded-md text-white text-xs font-medium transition-colors font-montserrat"
                                                        :class="{ 'bg-white/20': filtersOpen }"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z" />
                                                        </svg>
                                                        Filtros
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" :class="{ 'rotate-180': filtersOpen }">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                    </button>

                                                    <button
                                                        wire:click="refreshData"
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center justify-center px-2.5 py-2 rounded-md text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md focus:outline-none focus:ring-2 focus:ring-[#0071B9]/40 disabled:opacity-50 text-xs font-medium whitespace-nowrap font-montserrat"
                                                        title="Atualizar dados"
                                                    >
                                                        <span wire:loading.remove wire:target="refreshData" class="flex items-center font-montserrat">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                            </svg>
                                                            Atualizar
                                                        </span>
                                                        <span wire:loading wire:target="refreshData" class="flex items-center font-montserrat">
                                                            <div class="w-4 h-4 border-2 border-t-white border-white/30 rounded-full animate-spin mr-1"></div>
                                                            Atualizando...
                                                        </span>
                                                    </button>

                                                    
                                                    <button
                                                        wire:click="resetFilters"
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center justify-center px-2.5 py-2 rounded-md text-gray-700 bg-gray-100 border border-gray-300 hover:bg-gray-200 shadow-md focus:outline-none focus:ring-2 focus:ring-gray-400/40 disabled:opacity-50 text-xs font-medium whitespace-nowrap font-montserrat"
                                                        title="Limpar todos os filtros"
                                                    >
                                                        <span wire:loading.remove wire:target="resetFilters" class="flex items-center font-montserrat">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                            Limpar
                                                        </span>
                                                        <span wire:loading wire:target="resetFilters" class="flex items-center font-montserrat">
                                                            <div class="w-4 h-4 border-2 border-t-gray-600 border-gray-600/30 rounded-full animate-spin mr-1"></div>
                                                            Limpando...
                                                        </span>
                                                    </button>
                                                </div>

                                                
                                                <div
                                                    x-show="filtersOpen"
                                                    x-transition:enter="transition ease-out duration-200"
                                                    x-transition:enter-start="opacity-0 scale-95"
                                                    x-transition:enter-end="opacity-100 scale-100"
                                                    x-transition:leave="transition ease-in duration-150"
                                                    x-transition:leave-start="opacity-100 scale-100"
                                                    x-transition:leave-end="opacity-0 scale-95"
                                                    class="mt-2 p-4 bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg space-y-4 font-montserrat"
                                                    @click.away="filtersOpen = false"
                                                >
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 font-montserrat">
                                                        
                                                        
                                                        <div class="flex flex-col font-montserrat">
                                                            <label class="block text-white text-xs mb-2 font-medium font-montserrat">Hospital:</label>
                                                            <select
                                                                wire:model="selectedHospital"
                                                                wire:change.debounce.200ms="changeHospital($event.target.value)"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                            >
                                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $hospitals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hospital): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                    <option value="<?php echo e($hospital['hospital_id']); ?>" class="font-montserrat"><?php echo e($hospital['hospital_name']); ?></option>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                                            </select>
                                                        </div>

                                                        
                                                        <div class="flex flex-col font-montserrat">
                                                            <label class="block text-white text-xs mb-2 font-medium font-montserrat">Setor:</label>
                                                            <select
                                                                wire:model="selectedSector"
                                                                wire:change.debounce.200ms="changeSector($event.target.value)"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                            >
                                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $sectors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sector): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                    <option value="<?php echo e($sector['cd_setor_atendimento']); ?>" class="text-gray-800 bg-white hover:bg-blue-50 font-montserrat">
                                                                        <?php echo e($sector['ds_setor_atendimento']); ?>

                                                                    </option>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                                            </select>
                                                        </div>

                                                        
                                                        <div class="flex flex-col font-montserrat">
                                                            <label class="block text-white text-xs mb-2 font-medium font-montserrat">Criticidade:</label>
                                                            <select
                                                                wire:change="applyMewsFilter($event.target.value)"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                            >
                                                                <option value="all" class="font-montserrat">Todos MEWS</option>
                                                                <option value="critical" class="font-montserrat">CRÍTICOS (≥5)</option>
                                                                <option value="warning" class="font-montserrat">ALERTA (3-4)</option>
                                                                <option value="normal" class="font-montserrat">NORMAIS (0-2)</option>
                                                            </select>
                                                        </div>

                                                        
                                                        <div class="flex flex-col font-montserrat">
                                                            <label class="block text-white text-xs mb-2 font-medium font-montserrat">Cirurgias:</label>
                                                            <select
                                                                wire:change="applySurgicalFilter($event.target.value)"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-2 px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                            >
                                                                <option value="all" class="font-montserrat">Todas Cirurgias</option>
                                                                <option value="with_surgery" class="font-montserrat">COM CIRURGIAS</option>
                                                                <option value="without_surgery" class="font-montserrat">SEM CIRURGIAS</option>
                                                            </select>
                                                        </div>

                                                        
                                                        <div class="flex flex-col sm:col-span-2 font-montserrat">
                                                            <label class="block text-white text-xs mb-2 font-medium font-montserrat">Ordenação:</label>
                                                            <div class="flex">
                                                                <select
                                                                    wire:change="applyOrderBy($event.target.value)"
                                                                    class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-l py-2 px-3 text-sm flex-1 min-w-0 focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                                >
                                                                    <option value="bed" class="font-montserrat">Leito</option>
                                                                    <option value="mews" class="font-montserrat">MEWS</option>
                                                                    <option value="name" class="font-montserrat">Nome</option>
                                                                    <option value="prontuario" class="font-montserrat">Prontuário</option>
                                                                    <option value="internment" class="font-montserrat">Internação</option>
                                                                    <option value="age" class="font-montserrat">Idade</option>
                                                                </select>

                                                                <button
                                                                    wire:click="toggleOrderDirection"
                                                                    class="bg-white border border-gray-300 border-l-0 rounded-r px-3 py-2 flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:bg-gray-50 transition-colors font-montserrat"
                                                                    title="<?php echo e($orderDirection === 'asc' ? 'Ordem crescente' : 'Ordem decrescente'); ?>"
                                                                    aria-label="Toggle order direction"
                                                                >
                                                                    <!--[if BLOCK]><![endif]--><?php if($orderDirection === 'asc'): ?>
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                                                        </svg>
                                                                    <?php else: ?>
                                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                                                        </svg>
                                                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            
                                            <div class="hidden lg:block font-montserrat">
                                                <div class="grid grid-cols-6 xl:flex xl:flex-row xl:items-end gap-6 xl:gap-8 font-montserrat">
                                                    
                                                    
                                                    <div class="flex flex-col items-center font-montserrat">
                                                        <label class="block text-white text-xs mb-2 font-medium font-montserrat text-center">Hospital:</label>
                                                        <select
                                                            wire:model="selectedHospital"
                                                            wire:change.debounce.200ms="changeHospital($event.target.value)"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-1 px-2 text-xs w-full min-w-[144px] xl:min-w-[168px] focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                        >
                                                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $hospitals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hospital): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <option value="<?php echo e($hospital['hospital_id']); ?>" class="font-montserrat"><?php echo e($hospital['hospital_name']); ?></option>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                                        </select>
                                                    </div>

                                                    
                                                    <div class="flex flex-col items-center font-montserrat">
                                                        <label class="block text-white text-xs mb-2 font-medium font-montserrat text-center">Setor:</label>
                                                        <select
                                                            wire:model="selectedSector"
                                                            wire:change.debounce.200ms="changeSector($event.target.value)"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-1 px-2 text-xs w-full min-w-[144px] xl:min-w-[168px] focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                        >
                                                            <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $sectors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sector): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                <option value="<?php echo e($sector['cd_setor_atendimento']); ?>" class="text-gray-800 bg-white hover:bg-blue-50 font-montserrat">
                                                                    <?php echo e($sector['ds_setor_atendimento']); ?>

                                                                </option>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                                        </select>
                                                    </div>

                                                    
                                                    <div class="flex flex-col items-center font-montserrat">
                                                        <label class="block text-white text-xs mb-2 font-medium font-montserrat text-center">Criticidade:</label>
                                                        <select
                                                            wire:change="applyMewsFilter($event.target.value)"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-1 px-2 text-xs w-full min-w-[144px] xl:min-w-[168px] focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                        >
                                                            <option value="all" class="font-montserrat">Todos MEWS</option>
                                                            <option value="critical" class="font-montserrat">CRÍTICOS (≥5)</option>
                                                            <option value="warning" class="font-montserrat">ALERTA (3-4)</option>
                                                            <option value="normal" class="font-montserrat">NORMAIS (0-2)</option>
                                                        </select>
                                                    </div>

                                                    
                                                    <div class="flex flex-col items-center font-montserrat">
                                                        <label class="block text-white text-xs mb-2 font-medium font-montserrat text-center">Cirurgias:</label>
                                                        <select
                                                            wire:change="applySurgicalFilter($event.target.value)"
                                                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-1 px-2 text-xs w-full min-w-[144px] xl:min-w-[168px] focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                        >
                                                            <option value="all" class="font-montserrat">Todas Cirurgias</option>
                                                            <option value="with_surgery" class="font-montserrat">COM CIRURGIAS</option>
                                                            <option value="without_surgery" class="font-montserrat">SEM CIRURGIAS</option>
                                                        </select>
                                                    </div>

                                                    
                                                    <div class="flex flex-col items-center font-montserrat">
                                                        <label class="block text-white text-xs mb-2 font-medium font-montserrat text-center">Ordenação:</label>
                                                        <div class="flex w-full min-w-[144px] xl:min-w-[168px]">
                                                            <select
                                                                wire:change="applyOrderBy($event.target.value)"
                                                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-l py-1 px-2 text-xs flex-1 min-w-0 focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors font-montserrat"
                                                            >
                                                                <option value="bed" class="font-montserrat">Leito</option>
                                                                <option value="mews" class="font-montserrat">MEWS</option>
                                                                <option value="name" class="font-montserrat">Nome</option>
                                                                <option value="prontuario" class="font-montserrat">Prontuário</option>
                                                                <option value="internment" class="font-montserrat">Internação</option>
                                                                <option value="age" class="font-montserrat">Idade</option>
                                                            </select>

                                                            <button
                                                                wire:click="toggleOrderDirection"
                                                                class="bg-white border border-gray-300 border-l-0 rounded-r px-2 py-1 flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:bg-gray-50 transition-colors font-montserrat"
                                                                title="<?php echo e($orderDirection === 'asc' ? 'Ordem crescente' : 'Ordem decrescente'); ?>"
                                                                aria-label="Toggle order direction"
                                                            >
                                                                <!--[if BLOCK]><![endif]--><?php if($orderDirection === 'asc'): ?>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                                                    </svg>
                                                                <?php else: ?>
                                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                                                    </svg>
                                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="hidden lg:flex items-center justify-center gap-3 flex-shrink-0 font-montserrat">
                                            
                                            <button
                                                wire:click="refreshData"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center justify-center px-3 py-2 rounded-md text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md focus:outline-none focus:ring-2 focus:ring-[#0071B9]/40 disabled:opacity-50 text-xs font-medium whitespace-nowrap font-montserrat"
                                                title="Atualizar dados"
                                            >
                                                <span wire:loading.remove wire:target="refreshData" class="flex items-center font-montserrat">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                    Atualizar
                                                </span>
                                                <span wire:loading wire:target="refreshData" class="flex items-center font-montserrat">
                                                    <div class="w-4 h-4 border-2 border-t-white border-white/30 rounded-full animate-spin mr-1.5"></div>
                                                    Atualizando...
                                                </span>
                                            </button>

                                            
                                            <button
                                                wire:click="resetFilters"
                                                wire:loading.attr="disabled"
                                                class="inline-flex items-center justify-center px-3 py-2 rounded-md text-gray-700 bg-gray-100 border border-gray-300 hover:bg-gray-200 shadow-md focus:outline-none focus:ring-2 focus:ring-gray-400/40 disabled:opacity-50 text-xs font-medium whitespace-nowrap font-montserrat"
                                                title="Limpar todos os filtros"
                                            >
                                                <span wire:loading.remove wire:target="resetFilters" class="flex items-center font-montserrat">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Limpar
                                                </span>
                                                <span wire:loading wire:target="resetFilters" class="flex items-center font-montserrat">
                                                    <div class="w-4 h-4 border-2 border-t-gray-600 border-gray-600/30 rounded-full animate-spin mr-1.5"></div>
                                                    Limpando...
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                        <div id="patientsContainer" class="p-2 sm:p-3 lg:p-4 bg-white font-montserrat">
                            <!--[if BLOCK]><![endif]--><?php if(isset($errorMessage) && $errorMessage): ?>
                                
                                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6 font-montserrat">
                                    <div class="flex items-center font-montserrat">
                                        <svg class="w-6 h-6 mr-2 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <strong class="font-bold font-montserrat">Erro:</strong> 
                                        <span class="ml-2 font-montserrat"><?php echo e($errorMessage); ?></span>
                                    </div>
                                </div>
                            <?php elseif(empty($patients)): ?>
                                
                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg font-montserrat">
                                    <div class="flex items-center font-montserrat">
                                        <svg class="w-6 h-6 mr-2 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Nenhum paciente encontrado para o filtro aplicado.
                                    </div>
                                </div>
                            <?php else: ?>
                                
                                <div id="patientCardsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-4 lg:gap-4 font-montserrat">
                                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $patients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $patient): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div wire:key="patient-<?php echo e($patient['nr_atendimento'] ?? 'empty-' . $index); ?>" class="relative patient-card font-montserrat" x-data>
                                            <?php echo $__env->make('livewire.partials.patient-card', ['patient' => $patient, 'currentHospitalName' => $currentHospitalName], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        </div>
                    </div>

                    
                    <div class="font-montserrat">
                        <?php echo $__env->make('livewire.partials.sbar-legend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>

                    
                    <div class="font-montserrat">
                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('patient-modal', []);

$__html = app('livewire')->mount($__name, $__params, 'patient-modal', $__slots ?? [], get_defined_vars());

echo $__html;

unset($__html);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /var/www/passagem-plantao/resources/views/livewire/sbar-report.blade.php ENDPATH**/ ?>