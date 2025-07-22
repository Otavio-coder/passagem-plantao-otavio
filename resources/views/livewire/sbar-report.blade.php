<div class="font-montserrat relative">
    
    {{-- Overlay de carregamento --}}
    <div
    wire:loading
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#004D9D]/20"
    role="status"
    aria-live="polite"
    >
    <div class="flex flex-col items-center space-y-2">
        {{-- Spinner minimalista --}}
        <div
        class="w-12 h-12 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin"
        aria-hidden="true"
        ></div>
        {{-- Texto simples --}}
        <span class="text-[#004D9D] font-medium">Carregando...</span>
    </div>
    </div>

    <!-- Sistema de Aviso - Aparece sempre -->
    <div x-data="{ showAlert: true }" 
         x-show="showAlert" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="mb-4 sm:mb-6 bg-blue-50 border-l-4 border-blue-400 p-3 sm:p-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3 flex-1 sm:mr-8">
                    <div class="text-xs sm:text-sm text-blue-800 leading-relaxed">
                        <p class="mb-2 sm:mb-3">
                            <strong>Sistema SBAR - Passsagem de Plantão :</strong> Este sistema é um <strong>visualizador de informações</strong> 
                            que apresenta dados dos pacientes de forma otimizada. <strong>Não substitui o Tasy</strong> e <strong>não realiza 
                            inserções diretas de dados</strong> no sistema ERP hospitalar.
                        </p>
                        <p class="text-xs sm:text-sm">
                            <strong>Objetivo:</strong> Organizar informações por hospital, setor e leito, digitalizar dados tradicionalmente registrados 
                            no SBAR em papel e oferecer interface para avaliações de enfermagem por turno, reduzindo o uso de papel. 
                            Para ações no prontuário, continue utilizando o Tasy normalmente.
                        </p>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0 mt-3 sm:mt-0 self-start sm:ml-4">
                <button @click="showAlert = false" 
                        class="text-blue-400 hover:text-blue-600 transition-colors p-1">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    @if($loading)
    <!-- Skeleton Loading melhorado -->
    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden">
        <!-- Header Skeleton -->
        <div class="bg-[#004D9D]/90 px-3 sm:px-4 lg:px-8 py-2 sm:py-3 lg:py-4">
            <div class="animate-pulse">
                <div class="h-6 bg-white/20 rounded w-2/3 mx-auto mb-4"></div>
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-1 sm:gap-1.5 xl:gap-3">
                    @for($i = 0; $i < 4; $i++)
                        <div class="h-16 bg-white/10 rounded"></div>
                    @endfor
                </div>
            </div>
        </div>
        
        <!-- Cards Skeleton -->
        <div class="p-3 sm:p-4 lg:p-6 xl:p-8 bg-white">
            <div class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
                @for($i = 0; $i < 8; $i++)
                    <div class="animate-pulse">
                        <div class="bg-gray-200 h-64 sm:h-72 md:h-80 lg:h-80 rounded-xl">
                            <div class="p-4 h-full flex flex-col">
                                <!-- Header skeleton -->
                                <div class="flex justify-between mb-3">
                                    <div class="h-6 bg-gray-300 rounded-full w-20"></div>
                                    <div class="h-6 bg-gray-300 rounded-full w-16"></div>
                                </div>
                                
                                <!-- Avatar skeleton -->
                                <div class="flex items-center mb-3">
                                    <div class="h-10 w-10 bg-gray-300 rounded-full"></div>
                                    <div class="ml-3 flex-1">
                                        <div class="h-4 bg-gray-300 rounded w-3/4 mb-1"></div>
                                        <div class="h-4 bg-gray-300 rounded w-1/2"></div>
                                    </div>
                                </div>
                                
                                <!-- Content skeleton -->
                                <div class="space-y-2 flex-grow">
                                    <div class="h-3 bg-gray-300 rounded w-full"></div>
                                    <div class="h-3 bg-gray-300 rounded w-5/6"></div>
                                    <div class="h-3 bg-gray-300 rounded w-4/6"></div>
                                </div>
                                
                                <!-- Footer skeleton -->
                                <div class="h-4 bg-gray-300 rounded w-1/3 mt-auto"></div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
@else
    <!-- Main container with more subtle gradient background -->
    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden">
        <!-- Header section with more professional look - NOW FIXED -->
        <div class="bg-[#004D9D]/90 px-3 sm:px-4 lg:px-8 py-2 sm:py-3 lg:py-4 top-0 z-50 shadow-lg">
            <div class="flex flex-col space-y-2 lg:space-y-3">
                <h1 class="text-sm sm:text-base lg:text-2xl font-bold text-white text-center">Sistema SBAR - Passsagem de Plantão</h1>
                
                <!-- Filtros e botões responsivos em grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 xl:grid-cols-7 gap-2 xl:gap-4 items-end">
                    <!-- Hospital Selector -->
                    <div class="min-w-[140px] col-span-1">
                        <label class="block text-white text-xs mb-1 font-medium">Hospital:</label>
                        <select 
                            wire:model="selectedHospital" 
                            wire:change="changeHospital($event.target.value)" 
                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-1 pr-4 text-xs w-full focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                        >
                            @foreach($hospitals as $hospital)
                                <option value="{{ data_get($hospital, 'hospital_id') }}">{{ data_get($hospital, 'hospital_name') }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sector Selector -->
                    <div class="min-w-[140px] col-span-1">
                        <label class="block text-white text-xs mb-1 font-medium">Setor:</label>
                        <select 
                            wire:model="selectedSector" 
                            wire:change="changeSelector($event.target.value)" 
                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-1 pr-4 text-xs w-full focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                        >
                            @foreach($sectors as $sector)
                                <option value="{{ data_get($sector, 'cd_setor_atendimento') }}" class="text-gray-800 bg-white hover:bg-blue-50">{{ data_get($sector, 'ds_setor_atendimento') }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- MEWS Filter -->
                    <div class="min-w-[140px] col-span-1">
                        <label class="block text-white text-xs mb-1 font-medium">Criticidade (MEWS):</label>
                        <select 
                            wire:model="mewsFilter" 
                            wire:change="applyMewsFilter($event.target.value)"
                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-1 pr-4 text-xs w-full focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                        >
                            <option value="all" class="text-gray-800 bg-white hover:bg-blue-50">Todos os níveis</option>
                            <option value="critical" class="text-red-700 bg-red-50 hover:bg-red-100">CRÍTICOS (≥5)</option>
                            <option value="warning" class="text-amber-700 bg-amber-50 hover:bg-amber-100">ALERTA (≥3)</option>
                            <option value="normal" class="text-green-700 bg-green-50 hover:bg-green-100">NORMAIS (&lt;3)</option>
                        </select>
                    </div>
                    
                    <!-- Surgical Filter -->
                    <div class="min-w-[140px] col-span-1">
                        <label class="block text-white text-xs mb-1 font-medium">Cirurgias:</label>
                        <select 
                            wire:model="surgicalFilter" 
                            wire:change="applySurgicalFilter($event.target.value)"
                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-0.5 px-1 pr-4 text-xs w-full focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                        >
                            <option value="all" class="text-gray-800 bg-white hover:bg-blue-50">Todos os pacientes</option>
                            <option value="with_surgery" class="text-purple-700 bg-purple-50 hover:bg-purple-100">COM CIRURGIAS</option>
                        </select>
                    </div>
                    
                    <!-- Order By Filter with direction toggle -->
                    <div class="flex min-w-[180px]">
                        <div class="flex-1 min-w-0">
                            <label class="block text-white text-xs mb-1 font-medium">Ordenar por:</label>
                            <div class="flex">
                                <select 
                                    wire:model="orderBy" 
                                    wire:change="applyOrderBy($event.target.value)"
                                    class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-l py-0.5 px-1 pr-3 text-xs flex-1 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors"
                                >
                                    <option value="leito" class="text-gray-800 bg-white hover:bg-blue-50">Número do Leito</option>
                                    <option value="mews" class="text-gray-800 bg-white hover:bg-blue-50">Score MEWS</option>
                                    <option value="name" class="text-gray-800 bg-white hover:bg-blue-50">Nome do Paciente</option>
                                    <option value="prontuario" class="text-gray-800 bg-white hover:bg-blue-50">Número do Prontuário</option>
                                    <option value="internment" class="text-gray-800 bg-white hover:bg-blue-50">Tempo de Internação</option>
                                    <option value="age" class="text-gray-800 bg-white hover:bg-blue-50">Idade do Paciente</option>
                                </select>
                                <!-- Toggle direction button -->
                                <button 
                                    wire:click="toggleOrderDirection" 
                                    class="bg-white border border-gray-300 border-l-0 rounded-r px-1 focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:bg-gray-50 flex-shrink-0 transition-colors"
                                    title="{{ $orderDirection === 'asc' ? 'Ordem crescente (A→Z, 1→9)' : 'Ordem decrescente (Z→A, 9→1)' }}"
                                >
                                    @if($orderDirection === 'asc')
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                        </svg>
                                    @endif
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons container -->
                    <div class="flex gap-2 min-w-[140px]">
                        <!-- Refresh Button -->
                        <button 
                            wire:click="refreshData" 
                            @if($loading) disabled @endif
                            class="inline-flex items-center justify-center px-4 py-2 rounded text-white transition-all duration-200 bg-[#0071B9] hover:bg-[#004D9D] shadow-md hover:shadow-lg focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 disabled:opacity-50 text-sm font-semibold min-w-[90px]"
                            title="Atualizar dados do setor selecionado"
                        >
                            <svg class="w-4 h-4 mr-2 {{ $loading ? 'animate-spin' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="hidden sm:inline">Atualizar</span>
                            <span class="sm:hidden">ATZ</span>
                        </button>
                        
                        <!-- Reset Filters Button -->
                        <button 
                            wire:click="resetFilters" 
                            @if($loading) disabled @endif
                            class="inline-flex items-center justify-center px-4 py-2 rounded text-gray-700 bg-gray-100 border border-gray-300 transition-all duration-200 hover:bg-gray-200 hover:border-gray-400 shadow-md hover:shadow-lg focus:outline-none focus:ring-1 focus:ring-gray-400/50 disabled:opacity-50 text-sm font-semibold min-w-[90px]"
                            title="Limpar todos os filtros aplicados"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 -ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span class="ml-2 hidden sm:inline">Limpar</span>
                            <span class="ml-2 sm:hidden">LMP</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add padding to account for fixed header -->
        <div id="patientsContainer" class="p-3 sm:p-4 lg:p-6 xl:p-8 bg-white">
            @if($errorMessage)
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                <div class="flex items-center">
                <svg class="w-6 h-6 mr-2 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <strong class="font-bold">Erro:</strong> <span class="ml-2">{{ $errorMessage }}</span>
                </div>
            </div>
            @endif
            
            @if($loading)
                <div class="flex flex-col items-center justify-center py-20">
                    <div class="w-16 h-16 border-t-4 border-r-4 border-[#0071B9] border-solid rounded-full animate-spin mb-4"></div>
                    <p class="text-gray-700 text-xl">{{ $loadingMessage }}</p>
                </div>
            @elseif(empty($patients))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg">
                    <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Nenhum paciente encontrado para o filtro.
                    </div>
                </div>
            @else
            <!-- Patient Cards Grid - wider cards with responsive layout -->
            <div id="patientCardsContainer" class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
                @foreach($patients as $index => $patient)
                <div wire:key="patient-{{ $patient->nr_atendimento ?? 'empty-'.$index }}" class="relative patient-card">
                    <!-- Patient Card - simplified for Livewire only -->
                    <div wire:click="openModal('{{ $patient->nr_atendimento }}', '{{ $patient->cd_pessoa_fisica }}', {{ $patient->has_patient ? 'true' : 'false' }})" 
                    class="cursor-pointer transform transition-all duration-300 hover:scale-105 focus:outline-none h-full">
                    <div class="rounded-xl shadow-lg h-64 sm:h-72 md:h-80 lg:h-80">
                        <!-- Card Header with gradient color based on status -->
                        @if(!$patient->has_patient)
                        <!-- Empty Bed -->
                        <div class="h-full bg-gradient-to-br from-gray-200 to-gray-300 p-3 sm:p-4 flex flex-col">
                            <div class="flex justify-between items-center mb-3">
                            <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                                Leito {{ $patient->cd_unidade_basica }}
                            </span>
                            </div>
                            
                            <div class="flex-grow flex items-center justify-center">
                            <div class="text-center">
                                <svg class="mx-auto h-10 w-10 sm:h-12 sm:w-12 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p class="text-gray-500 text-sm font-medium">Leito Vazio</p>
                            </div>
                            </div>
                        </div>
                        @else
                        @php 
                            $gradientClass = 'from-blue-50 to-blue-100';
                            $borderClass = 'border border-gray-200';
                            $textColorClass = 'text-sky-800';

                            $isNewPatient = ($patient->tempo_internacao_dias !== null && $patient->tempo_internacao_dias >= 0 && $patient->tempo_internacao_dias < 1);

                            if ($isNewPatient) {
                                $gradientClass = 'from-green-50 to-green-100';
                                $borderClass = 'border-2 border-green-400';
                                $textColorClass = 'text-green-800';
                            } elseif ($patient->mews_score !== null) {
                                if ($patient->mews_score >= 5) {
                                    $gradientClass = 'from-red-50 to-red-100';
                                    $borderClass = 'border-2 border-red-500';
                                    $textColorClass = 'text-red-800';
                                } elseif ($patient->mews_score >= 3) {
                                    $gradientClass = 'from-amber-50 to-amber-100';
                                    $borderClass = 'border-2 border-amber-500';
                                    $textColorClass = 'text-amber-800';
                                }
                            }
                        @endphp
                        <div class="h-full bg-gradient-to-br {{ $gradientClass }} p-3 sm:p-4 flex flex-col {{ $borderClass }}">
                            <!-- Card Header with icons between bed and MEWS -->
                            <div class="flex justify-between items-start mb-3">
                            <!-- Left side: Bed number and NEW badge -->
                            <div class="flex items-center space-x-2 flex-shrink-0">
                                <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                                Leito {{ $patient->cd_unidade_basica }}
                                </span>
                            </div>
                            
                            <!-- Center: Alert Icons -->
                            <div class="flex items-center justify-center space-x-1 flex-1 px-2">
                                @if(isset($patient->has_allergy) && $patient->has_allergy)
                                <div class="relative group">
                                    <div class="bg-red-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente com alergias">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    </div>
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-red-600 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap z-[9999]">
                                    Alergias
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-2 border-transparent border-t-red-600"></div>
                                    </div>
                                </div>
                                @endif
                                
                                @if(isset($patient->has_isolation) && $patient->has_isolation)
                                <div class="relative group">
                                    <div class="bg-yellow-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente em isolamento">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    </div>
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-yellow-600 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap z-[9999]">
                                    Isolamento
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-2 border-transparent border-t-yellow-600"></div>
                                    </div>
                                </div>
                                @endif
                                
                                @if(isset($patient->has_cpoe_pending) && $patient->has_cpoe_pending)
                                <div class="relative group">
                                    <div class="bg-blue-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="CPOE pendente para baixa">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </div>
                                    <!-- Tooltip with count -->
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-blue-600 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap z-[9999]">
                                    {{ $patient->cpoe_pending_count ?? 0 }} CPOE pendente(s)
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-2 border-transparent border-t-blue-600"></div>
                                    </div>
                                </div>
                                @endif
                                
                                @if(isset($patient->has_surgery) && $patient->has_surgery)
                                <div class="relative group">
                                    <div class="bg-purple-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente com cirurgias programadas/realizadas">
                                        <!-- Ícone SVG de tesoura para cirurgia -->
                                        @svg('healthicons-o-surgical-sterilization', 'h-3 w-3 sm:h-4 sm:w-4')
                                    </div>
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-purple-600 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap z-[9999]">
                                        {{ count($patient->surgical_procedures ?? []) }} cirurgia(s)
                                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-2 border-transparent border-t-purple-600"></div>
                                    </div>
                                </div>
                                @endif
                            </div>
                            
                            <!-- Right side: MEWS Score -->
                            <div class="flex-shrink-0">
                                @if($patient->mews_score !== null)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $textColorClass }} bg-white/70 whitespace-nowrap">
                                        {{ $patient->mews_display }}
                                    </span>
                                @elseif($isNewPatient)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-green-700 bg-white/70 whitespace-nowrap">
                                        MEWS: Pendente
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-red-700 bg-white/70 whitespace-nowrap">
                                        MEWS: não aferido
                                    </span>
                                @endif
                            </div>
                            </div>
                            
                            <!-- Patient Info -->
                            <div class="mb-3 flex items-center">
                            <div class="ml-3 truncate flex-1 min-w-0">
                                <p class="text-gray-600 text-xs truncate"><strong>{{ $patient->nm_pessoa_fisica ?? 'N/A' }}</strong></p>
                                <p class="text-gray-600 text-xs truncate">Atend: {{ $patient->nr_atendimento ?? 'N/A' }}</p>
                                <p class="text-gray-600 text-xs truncate">Pront: {{ $patient->nr_prontuario ?? 'N/A' }}</p>
                            </div>
                            </div>

                            <!-- Patient Details - Enhanced with better text handling -->
                            <div class="flex-grow">
                            <div class="space-y-1.5">
                                <!-- Sexo e Idade detalhada -->
                                <div class="text-xs text-gray-600">
                                <span class="flex items-center gap-2 font-normal text-xs text-gray-600">
                                    @if($patient->sexo === 'F')
                                        <span class="inline-flex items-center justify-center rounded-full text-pink-600" style="width: 1.5em; height: 1.5em;">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <line x1="12" y1="12" x2="12" y2="20" stroke="currentColor" stroke-width="2"/>
                                                <line x1="9" y1="17" x2="15" y2="17" stroke="currentColor" stroke-width="2"/>
                                            </svg>
                                        </span>
                                        <span class="opacity-70">Feminino</span>
                                    @elseif($patient->sexo === 'M')
                                        <span class="inline-flex items-center justify-center rounded-full bg-blue-100" style="width: 1.5em; height: 1.5em;">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <circle cx="10" cy="14" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path stroke="currentColor" stroke-width="2" d="M14 10l6-6m0 0h-4m4 0v4"/>
                                            </svg>
                                        </span>
                                        <span class="opacity-70">Masculino</span>
                                    @else
                                        <span class="inline-flex items-center justify-center rounded-full bg-gray-100" style="width: 1.5em; height: 1.5em;">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <path stroke="currentColor" stroke-width="2" d="M12 16v2"/>
                                            </svg>
                                        </span>
                                        <span class="opacity-70">Sexo: N/I</span>
                                    @endif
                                </span>
                                </div>
                                
                                <div class="text-xs text-gray-600">
                                <span class="font-medium">Idade:</span> 
                                <span class="truncate">{{ $patient->age_detailed ?? ($patient->age ?? 'N/A') }}</span>
                                @if($patient->birth_date)
                                    <span class="text-gray-500 block sm:inline">({{ $patient->birth_date }})</span>
                                @endif
                                </div>
                                
                                <div class="text-xs text-gray-600">
                                <span class="font-medium">Internação:</span> 
                                @if($patient->tempo_internacao_dias === null)
                                    <span class="text-gray-500">N/A</span>
                                @elseif($patient->tempo_internacao_dias >= 0 && $patient->tempo_internacao_dias < 1)
                                    <span class="text-green-600 font-semibold">Recém-chegado (hoje)</span>
                                @else
                                    @php $days = ceil($patient->tempo_internacao_dias); @endphp
                                    {{ $days }} dia{{ $days != 1 ? 's' : '' }}
                                @endif
                                </div>
                                
                                @if($patient->medico_responsavel)
                                <div class="text-xs text-gray-600 truncate">
                                    <span class="font-medium">Médico:</span> 
                                    <span class="truncate">{{ $patient->medico_responsavel }}</span>
                                </div>
                                @endif
                                
                                @if($patient->convenio)
                                <div class="text-xs text-gray-600 truncate">
                                    <span class="font-medium">Convênio:</span> 
                                    <span class="truncate">{{ $patient->convenio }}</span>
                                </div>
                                @endif
                            </div>
                            </div>

                            <!-- Bottom indicators -->
                            <div class="mt-3 flex justify-between items-center">
                            <div class="flex space-x-1">
                                <!-- Removed CPOE and Exams indicators -->
                            </div>
                            
                            <div class="text-xs text-gray-500">
                                Clique para detalhes
                            </div>
                            </div>
                        </div>
                        @endif
                    </div>
                    </div>
                </div>
                @endforeach
            </div>
                
                <!-- Mobile/Tablet Pagination Controls - Only visible on small screens -->
                <div id="cardPaginationControls" class="mt-6 flex items-center justify-between lg:hidden">
                    <button id="prevPageBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 disabled:opacity-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div class="text-sm">
                        <span id="currentPageIndicator">Página <span id="currentPage">1</span> de <span id="totalPages">1</span></span>
                    </div>
                    <button id="nextPageBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 disabled:opacity-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
    
    <!-- Legend Section with Hospital-Like Colors - Responsive -->
    <div class="mt-6 mb-16 lg:mb-6 p-3 sm:p-4 md:p-6 bg-white rounded-xl shadow-lg border border-gray-100">
        <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-3 sm:mb-4">Legenda</h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <!-- Bed Status Legend -->
            <div>
                <h3 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3 border-b border-gray-200 pb-1">Status do Leito</h3>
                <ul class="space-y-2 sm:space-y-3">
                    <li class="flex items-center">
                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-md mr-2 sm:mr-3 border border-gray-200 flex-shrink-0"></span>
                        <span class="text-sm sm:text-base text-gray-600">Leito Ocupado</span>
                    </li>
                    <li class="flex items-center">
                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-gray-200 to-gray-300 rounded-md mr-2 sm:mr-3 border border-gray-200 flex-shrink-0"></span>
                        <span class="text-sm sm:text-base text-gray-600">Leito Vazio</span>
                    </li>
                </ul>
            </div>
            
            <!-- MEWS Score Legend -->
            <div>
                <h3 class="text-base sm:text-lg font-semibold text-gray-700 mb-2 sm:mb-3 border-b border-gray-200 pb-1">Escala MEWS</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                    <div class="flex items-center">
                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-red-50 to-red-100 rounded-md mr-2 sm:mr-3 border-2 border-red-500 flex-shrink-0"></span>
                        <span class="text-xs sm:text-sm text-gray-600">MEWS ≥ 5 (Crítico)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-amber-50 to-amber-100 rounded-md mr-2 sm:mr-3 border-2 border-amber-500 flex-shrink-0"></span>
                        <span class="text-xs sm:text-sm text-gray-600">MEWS 3-4 (Alerta)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-md mr-2 sm:mr-3 border border-gray-200 flex-shrink-0"></span>
                        <span class="text-xs sm:text-sm text-gray-600">MEWS 0-2 (Normal)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-5 h-5 sm:w-6 sm:h-6 bg-gradient-to-br from-green-50 to-green-100 rounded-md mr-2 sm:mr-3 border-2 border-green-400 flex-shrink-0"></span>
                        <span class="text-xs sm:text-sm text-gray-600">Recém-chegado</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Floating Action Button (FAB) for Auto-Scroll - Only Cíclico mode with speed controls -->
    <div id="fabContainer" class="hidden lg:block fixed bottom-6 right-6 z-50" style="position: fixed !important; will-change: auto !important;">
        <div class="relative">
            <!-- Main FAB button -->
            <button id="mainFabButton" 
                class="p-4 bg-[#0071B9] hover:bg-[#004D9D] text-white rounded-full shadow-lg transition-all duration-200 flex items-center justify-center"
                title="Controle de rolagem automática">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13a9 9 0 110-18 9 9 0 010 18z" />
                </svg>
            </button>

            <!-- FAB Controls panel - only speed controls -->
            <div id="fabControlsPanel" class="absolute bottom-16 right-0 bg-white rounded-lg shadow-xl p-3 hidden min-w-[240px] transition-all duration-300">
                <div class="mb-2">
                    <h3 class="text-gray-700 font-semibold mb-2 border-b pb-1">Velocidade</h3>
                    <div class="flex space-x-2">
                        <button id="speedSlow" class="speed-btn flex-1 px-3 py-1 rounded-full text-xs bg-gray-200 hover:bg-gray-300 transition-colors" data-speed="slow" data-delay="80">
                            Lenta
                        </button>
                        <button id="speedMedium" class="speed-btn flex-1 px-3 py-1 rounded-full text-xs bg-[#0071B9] text-white hover:bg-[#004D9D] transition-colors" data-speed="medium" data-delay="40">
                            Média
                        </button>
                        <button id="speedFast" class="speed-btn flex-1 px-3 py-1 rounded-full text-xs bg-gray-200 hover:bg-gray-300 transition-colors" data-speed="fast" data-delay="15">
                            Rápida
                        </button>
                    </div>
                </div>
                <!-- Close Button for control panel -->
                <button id="closeFabPanel" class="w-full mt-1 text-xs text-gray-500 hover:text-gray-700 flex items-center justify-center">
                    <span>Fechar</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>
            
            <!-- Start/Stop button -->
            <button id="autoScrollButton" 
                class="absolute top-0 left-0 w-full h-full rounded-full flex items-center justify-center transition-opacity duration-300"
                title="Iniciar/Parar rolagem automática">
                <div class="bg-[#0071B9] hover:bg-[#004D9D] rounded-full w-full h-full flex items-center justify-center">
                    <svg id="playIcon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    </svg>
                    <svg id="pauseIcon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6" />
                    </svg>
                </div>
            </button>
            
            <!-- Settings button -->
            <button id="settingsButton" 
                class="absolute -top-3 -right-3 bg-gray-100 text-gray-600 rounded-full p-1.5 shadow-md hover:bg-gray-200"
                title="Configurações de rolagem">
                @svg('mdi-mouse-scroll-wheel', 'h-4 w-4')
            </button>
            
            <!-- Status indicator -->
            <div id="scrollStatusIndicator" class="absolute -top-1 -left-1 h-3 w-3 rounded-full bg-green-500 hidden animate-pulse"></div>
            
            <!-- Refresh indicator -->
            <div id="refreshIndicator" class="absolute -top-1 -right-6 h-3 w-3 rounded-full bg-blue-500 hidden animate-ping"></div>
        </div>
    </div>

    <!-- Patient Modal using only Livewire -->
    @livewire('patient-modal')
</div>



