@props([
    'currentHospitalName' => null,
    'currentPatient' => null,
    'patientDetails' => null
])

<div class="bg-[#004D9D] px-4 sm:px-6 py-3 sm:py-4 flex-shrink-0 rounded-t-2xl relative">
    <!-- Close button -->
    <button 
        wire:click="closeModal"
        class="absolute top-3 right-3 sm:top-4 sm:right-4 text-white/70 hover:text-white transition-colors focus:outline-none bg-white/10 hover:bg-white/20 rounded-full p-1.5 z-10"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    <!-- Main Header Content -->
    <div class="pr-10 sm:pr-12">
        <!-- Logo and Title Row -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-2 sm:mb-3 gap-2 sm:gap-0">
            <div class="flex items-center space-x-3 sm:space-x-4">
                <img src="{{ asset('images/santacasa-horizontal-branco.svg') }}" 
                     alt="Santa Casa de Porto Alegre" 
                     class="h-7 sm:h-8 w-auto opacity-90 max-w-[140px] sm:max-w-none">
                <div class="border-l border-white/30 pl-3 sm:pl-4">
                    <h3 class="text-xs sm:text-sm font-medium text-blue-100 leading-tight">
                        Modelo de Comunicação SBAR para<br>
                        Passagem de Plantão de Enfermeiros
                    </h3>
                </div>
            </div>
        </div>
        
        <!-- Information Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 sm:gap-4 text-xs sm:text-sm text-blue-100">
            <!-- Left Column: Hospital and Date -->
            <div class="flex flex-col xs:flex-row items-start xs:items-center space-y-1 xs:space-y-0 xs:space-x-4 sm:space-x-6">
                <div class="flex items-center space-x-1.5 sm:space-x-2">
                    <span class="font-medium opacity-80">Hospital:</span>
                    <span class="text-white font-medium truncate max-w-[120px] sm:max-w-none">{{ $currentHospitalName ?? 'Carregando...' }}</span>
                </div>
                <div class="flex items-center space-x-1.5 sm:space-x-2">
                    <span class="font-medium opacity-80">Data:</span>
                    <span class="text-white font-medium">{{ date('d/m/Y') }}</span>
                </div>
            </div>
            
            <!-- Right Column: Patient Information -->
            @if($currentPatient && $currentPatient['has_patient'] && $patientDetails)
                <div class="flex flex-col xs:flex-row items-start xs:items-center space-y-1 xs:space-y-0 xs:space-x-4 sm:space-x-6 mt-1 md:mt-0">
                    <div class="flex items-center space-x-1.5 sm:space-x-2">
                        <span class="font-medium opacity-80">Prontuário:</span>
                        <span class="text-white font-mono font-medium">{{ $patientDetails->nr_prontuario ?? 'N/A' }}</span>
                    </div>
                    <div class="flex items-center space-x-1.5 sm:space-x-2">
                        <span class="font-medium opacity-80">Atendimento:</span>
                        <span class="text-white font-mono font-medium">{{ $currentPatient['nr_atendimento'] }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>