<div class="font-montserrat relative">
    {{-- Overlay de carregamento profissional --}}
    <div wire:loading class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50">
        <div class="text-center">
            <div class="border-4 border-white border-t-transparent rounded-full h-16 w-16 animate-spin mx-auto"></div>
            <p class="mt-4 text-white font-semibold">Carregando, aguarde...</p>
        </div>
    </div>

    <!-- Main container with more subtle gradient background -->
    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden">
        <!-- Header section with more professional look - NOW FIXED -->
        <div class="bg-[#004D9D]/90 px-4 sm:px-8 py-5 top-0 z-50 shadow-lg">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center space-y-4 md:space-y-0">
                <h1 class="text-xl sm:text-2xl font-bold text-white">SBAR - Painel de Pacientes</h1>
                
                <div class="flex flex-wrap flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-3">
                    <!-- Sector Selector -->
                    <div class="relative w-full sm:w-auto mb-2 sm:mb-0">
                        <select 
                            wire:model="selectedSector" 
                            wire:change="changeSelector($event.target.value)" 
                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-4 pr-8 w-full focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50"
                        >
                            @foreach($sectors as $sector)
                                <option value="{{ $sector['cd_setor_atendimento'] }}">{{ $sector['ds_setor_atendimento'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- MEWS Filter -->
                    <div class="relative w-full sm:w-auto mb-2 sm:mb-0">
                        <select 
                            wire:model="mewsFilter" 
                            wire:change="applyMewsFilter($event.target.value)"
                            class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-lg py-2 px-4 pr-8 w-full focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50"
                        >
                            <option value="all">Todos os leitos</option>
                            <option value="critical">Pacientes críticos (MEWS ≥ 5)</option>
                            <option value="warning">Pacientes em alerta (MEWS ≥ 3)</option>
                            <option value="normal">Pacientes normais (MEWS < 3)</option>
                        </select>
                    </div>
                    
                    <!-- Order By Filter - NEW COMPONENT -->
                    <div class="flex w-full sm:w-auto mb-2 sm:mb-0">
                        <div class="relative flex-grow">
                            <select 
                                wire:model="orderBy" 
                                wire:change="applyOrderBy($event.target.value)"
                                class="appearance-none bg-white text-gray-700 border border-gray-300 rounded-l-lg py-2 px-4 pr-8 w-full focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50"
                            >
                                <option value="leito">Ordenar por Leito</option>
                                <option value="mews">Ordenar por MEWS</option>
                                <option value="name">Ordenar por Nome</option>
                                <option value="prontuario">Ordenar por Prontuário</option>
                                <option value="internment">Ordenar por Internação</option>
                                <option value="age">Ordenar por Idade</option>
                            </select>
                        </div>
                        <!-- Toggle direction button -->
                        <button 
                            wire:click="toggleOrderDirection" 
                            class="bg-white border border-gray-300 border-l-0 rounded-r-lg px-3 focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 hover:bg-gray-50"
                            title="{{ $orderDirection === 'asc' ? 'Ordem crescente' : 'Ordem decrescente' }}"
                        >
                            @if($orderDirection === 'asc')
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                                </svg>
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                </svg>
                            @endif
                        </button>
                    </div>
                    
                    <!-- Refresh Button -->
                    <button 
                        wire:click="refreshData" 
                        @if($loading) disabled @endif
                        class="inline-flex items-center px-4 py-2 rounded-lg text-white transition-all duration-200 bg-[#0071B9] hover:bg-[#004D9D] shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 disabled:opacity-50"
                    >
                        <svg class="w-5 h-5 mr-2 {{ $loading ? 'animate-spin' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Atualizar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Add padding to account for fixed header -->
        <div id="patientsContainer" class="p-4 sm:p-6 lg:p-8 bg-white">
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
            @elseif($pagination['total'] === 0)
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 mr-2 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Nenhum paciente encontrado para o filtro.
                    </div>
                </div>
            @elseif($pagination['total'] > 0)
                <!-- Patient Cards Grid - optimized for multiple devices -->
                <div id="patientCardsContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4 gap-4 sm:gap-6">
                    @foreach($patients as $index => $patient)
                        <div wire:key="patient-{{ $patient->nr_atendimento ?? 'empty-'.$index }}" class="relative patient-card">
                            <!-- Patient Card - simplified for Livewire only -->
                            <div wire:click="openModal('{{ $patient->nr_atendimento }}', '{{ $patient->cd_pessoa_fisica }}', {{ $patient->has_patient ? 'true' : 'false' }})" 
                                class="cursor-pointer transform transition-all duration-300 hover:scale-105 focus:outline-none h-full">
                                <div class="rounded-xl shadow-lg h-64 sm:h-80">
                                    <!-- Card Header with gradient color based on status -->
                                    @if(!$patient->has_patient)
                                        <!-- Empty Bed -->
                                        <div class="h-full bg-gradient-to-br from-gray-200 to-gray-300 p-4 flex flex-col">
                                            <div class="flex justify-between items-center mb-3">
                                                <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                                                    Leito {{ $patient->cd_unidade_basica }}
                                                </span>
                                            </div>
                                            
                                            <div class="flex-grow flex items-center justify-center">
                                                <div class="text-center">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <p class="text-gray-500 text-sm font-medium">Leito Vazio</p>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        @php 
                                            // MEWS-based gradient and border styling
                                            $gradientClass = 'from-blue-50 to-blue-100';
                                            $borderClass = 'border border-gray-200';
                                            $textColorClass = 'text-sky-800';
                                            
                                            // CORREÇÃO: Paciente novo APENAS se internado há exatamente 0 dias
                                            $isNewPatient = ($patient->internment_days !== null && $patient->internment_days === 0);
                                            
                                            // Apply special styling for new patients
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
                                        <div class="h-full bg-gradient-to-br {{ $gradientClass }} p-4 flex flex-col {{ $borderClass }}">
                                            <!-- Card Header -->
                                            <div class="flex justify-between items-center mb-3">
                                                <div class="flex items-center space-x-2">
                                                    <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                                                        Leito {{ $patient->cd_unidade_basica }}
                                                    </span>
                                                    @if($isNewPatient)
                                                        <span class="bg-green-500 text-white text-xs font-bold px-2 py-0.5 rounded-full animate-pulse">
                                                            NOVO
                                                        </span>
                                                    @endif
                                                </div>
                                                
                                                <!-- Alert Icons - Enhanced with CPOE -->
                                                <div class="flex items-center space-x-1">
                                                    @if(isset($patient->has_allergy) && $patient->has_allergy)
                                                        <div class="relative group">
                                                            <div class="bg-red-500 text-white rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente com alergias">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
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
                                                            <div class="bg-yellow-500 text-black rounded-full p-1.5 shadow-lg animate-pulse" title="Paciente em isolamento">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                                </svg>
                                                            </div>
                                                            <!-- Tooltip with count -->
                                                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-blue-600 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap z-[9999]">
                                                                {{ $patient->cpoe_pending_count ?? 0 }} CPOE pendente(s)
                                                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-2 border-transparent border-t-blue-600"></div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                
                                                @if($patient->mews_score !== null && $patient->mews_score > 0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $textColorClass }} bg-white/70">
                                                        MEWS: {{ $patient->mews_score }}
                                                    </span>
                                                @elseif($isNewPatient)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium text-green-700 bg-white/70">
                                                        MEWS: Pendente
                                                    </span>
                                                @elseif($patient->mews_score === null || $patient->mews_score === 0)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium text-red-700 bg-white/70">
                                                        MEWS: Não realizado
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            <!-- Patient Info -->
                                            <div class="mb-3 flex items-center">
                                                <div class="h-10 w-10 rounded-full flex items-center justify-center bg-gray-200 text-gray-700 font-semibold">
                                                    {{ $patient->initials }}
                                                </div>
                                                <div class="ml-3 truncate">
                                                    <p class="text-gray-600 text-xs">Atend: {{ $patient->nr_atendimento ?? 'N/A' }}</p>
                                                    <p class="text-gray-600 text-xs">Pront: {{ $patient->nr_prontuario ?? 'N/A' }}</p>
                                                </div>
                                            </div>

                                            <!-- Patient Details - Enhanced -->
                                            <div class="flex-grow">
                                                <div class="space-y-1.5">
                                                    <!-- Sexo e Idade detalhada -->
                                                    <div class="text-xs text-gray-600">
                                                        <span class="font-medium">
                                                            @if($patient->sexo === 'F')
                                                                <span class="text-pink-600">♀</span> Feminino
                                                            @elseif($patient->sexo === 'M')
                                                                <span class="text-blue-600">♂</span> Masculino
                                                            @else
                                                                Sexo: N/I
                                                            @endif
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="text-xs text-gray-600">
                                                        <span class="font-medium">Idade:</span> 
                                                        {{ $patient->age_detailed ?? ($patient->age ?? 'N/A') }} 
                                                        @if($patient->birth_date)
                                                            <span class="text-gray-500">({{ $patient->birth_date }})</span>
                                                        @endif
                                                    </div>
                                                    
                                                    <div class="text-xs text-gray-600">
                                                        <span class="font-medium">Internação:</span> 
                                                        @if($patient->internment_days === null)
                                                            <span class="text-gray-500">N/A</span>
                                                        @elseif($patient->internment_days === 0)
                                                            <span class="text-green-600 font-semibold">Recém-chegado (hoje)</span>
                                                        @else
                                                            {{ $patient->internment_days }} dia{{ $patient->internment_days > 1 ? 's' : '' }}
                                                        @endif
                                                    </div>
                                                    
                                                    @if($patient->medico_responsavel)
                                                        <div class="text-xs text-gray-600 truncate">
                                                            <span class="font-medium">Médico:</span> {{ $patient->medico_responsavel }}
                                                        </div>
                                                    @endif
                                                    
                                                    @if($patient->convenio)
                                                        <div class="text-xs text-gray-600 truncate">
                                                            <span class="font-medium">Convênio:</span> {{ $patient->convenio }}
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
                <div id="cardPaginationControls" class="mt-6 flex items-center justify-between sm:hidden">
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
    
    <!-- Legend Section with Hospital-Like Colors -->
    <div class="mt-6 mb-16 lg:mb-6 p-6 bg-white rounded-xl shadow-lg border border-gray-100">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Legenda</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Bed Status Legend -->
            <div>
                <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b border-gray-200 pb-1">Status do Leito</h3>
                <ul class="space-y-3">
                    <li class="flex items-center">
                        <span class="w-6 h-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-md mr-3 border border-gray-200"></span>
                        <span class="text-gray-600">Leito Ocupado</span>
                    </li>
                    <li class="flex items-center">
                        <span class="w-6 h-6 bg-gradient-to-br from-gray-200 to-gray-300 rounded-md mr-3 border border-gray-200"></span>
                        <span class="text-gray-600">Leito Vazio</span>
                    </li>
                </ul>
            </div>
            
            <!-- MEWS Score Legend -->
            <div>
                <h3 class="text-lg font-semibold text-gray-700 mb-3 border-b border-gray-200 pb-1">Escala MEWS</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex items-center">
                        <span class="w-6 h-6 bg-gradient-to-br from-red-50 to-red-100 rounded-md mr-3 border-2 border-red-500"></span>
                        <span class="text-gray-600">MEWS ≥ 5 (Crítico)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-6 h-6 bg-gradient-to-br from-amber-50 to-amber-100 rounded-md mr-3 border-2 border-amber-500"></span>
                        <span class="text-gray-600">MEWS 3-4 (Alerta)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-6 h-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-md mr-3 border border-gray-200"></span>
                        <span class="text-gray-600">MEWS 0-2 (Normal)</span>
                    </div>
                    <div class="flex items-center">
                        <span class="w-6 h-6 bg-gradient-to-br from-green-50 to-green-100 rounded-md mr-3 border-2 border-green-400"></span>
                        <span class="text-gray-600">Recém-chegado</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Enhanced Floating Action Button (FAB) for Auto-Scroll with improved positioning -->
    <div id="fabContainer" class="hidden lg:block fixed bottom-6 right-6 z-50" style="position: fixed !important; will-change: auto !important;">
        <!-- Main FAB button with nested controls -->
        <div class="relative">
            <!-- Main expandable button -->
            <button id="mainFabButton" 
                class="p-4 bg-[#0071B9] hover:bg-[#004D9D] text-white rounded-full shadow-lg transition-all duration-200 flex items-center justify-center"
                title="Controle de rolagem automática (duplo-clique para debug)">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13a9 9 0 110-18 9 9 0 010 18z" />
                </svg>
            </button>

            <!-- FAB Controls panel - initially hidden -->
            <div id="fabControlsPanel" class="absolute bottom-16 right-0 bg-white rounded-lg shadow-xl p-3 hidden min-w-[240px] transition-all duration-300">
                <div class="mb-4">
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
                
                <div class="mb-4">
                    <h3 class="text-gray-700 font-semibold mb-2 border-b pb-1">Modo</h3>
                    <div class="flex space-x-2">
                        <button id="modeCycle" class="mode-btn flex-1 px-3 py-1 rounded-full text-xs bg-[#0071B9] text-white hover:bg-[#004D9D] transition-colors" data-mode="cycle">
                            Cíclico
                        </button>
                        <button id="modeRestart" class="mode-btn flex-1 px-3 py-1 rounded-full text-xs bg-gray-200 hover:bg-gray-300 transition-colors" data-mode="restart">
                            Reinício
                        </button>
                    </div>
                </div>
                
                <!-- ADICIONADO: Botão de debug -->
                <div class="mb-2">
                    <button id="debugButton" class="w-full text-xs text-gray-500 hover:text-gray-700 border border-gray-300 rounded px-2 py-1" onclick="console.log('Debug: max scroll =', Math.max(0, document.documentElement.scrollHeight - window.innerHeight))">
                        Debug Info
                    </button>
                </div>
                
                <!-- Close Button for control panel -->
                <button id="closeFabPanel" class="w-full mt-1 text-xs text-gray-500 hover:text-gray-700 flex items-center justify-center">
                    <span>Fechar</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>
            
            <!-- Start/Stop button - Active even during scrolling -->
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
            
            <!-- Settings button - Always visible -->
            <button id="settingsButton" 
                class="absolute -top-3 -right-3 bg-gray-100 text-gray-600 rounded-full p-1.5 shadow-md hover:bg-gray-200"
                title="Configurações de rolagem">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </button>
            
            <!-- Status indicator -->
            <div id="scrollStatusIndicator" class="absolute -top-1 -left-1 h-3 w-3 rounded-full bg-green-500 hidden animate-pulse"></div>
            
            <!-- Refresh indicator - shows when auto-refresh happens -->
            <div id="refreshIndicator" class="absolute -top-1 -right-6 h-3 w-3 rounded-full bg-blue-500 hidden animate-ping"></div>
        </div>
    </div>

    <!-- Patient Modal using only Livewire -->
    <x-patient-modal.index 
        :showModal="$showModal"
        :currentPatient="$currentPatient"
        :patientDetails="$patientDetails"
        :loadingPatient="$loadingPatient"
        :currentHospitalName="$currentHospitalName" />

    <!-- Auto-scroll JavaScript with enhanced controls and auto-refresh -->
    <script src="{{ asset('js/sbar-autoscroll.js') }}"></script>
</div>
