@props([
    'currentHospitalName' => null,
    'currentPatient' => null,
    'patientDetails' => null
])

<div class="bg-[#004D9D] px-4 sm:px-6 py-4 sm:py-6 flex-shrink-0 rounded-t-2xl">
    <!-- Santa Casa Header with Logo -->
    <div class="text-center mb-4 relative">
        <!-- Centered Logo -->
        <div class="flex justify-center mb-4">
            <img src="{{ asset('images/santacasa-horizontal-branco.svg') }}" 
                 alt="Santa Casa de Porto Alegre" 
                 class="h-12 sm:h-14 w-auto opacity-90">
        </div>
        
        <!-- Centered subtitle content -->
        <div>
            <h3 class="text-base sm:text-lg md:text-xl text-blue-100 mb-3">
                Modelo de Comunicação SBAR para Passagem de Plantão de Enfermeiros
            </h3>
        </div>
        
        <!-- Hospital Info Row -->
        <div class="flex flex-col sm:flex-row justify-between items-center text-xs sm:text-sm text-blue-100 space-y-2 sm:space-y-0 mt-4">
            <div class="flex flex-col sm:flex-row sm:space-x-6 space-y-1 sm:space-y-0">
                <div>
                    <span class="font-medium">Hospital:</span> 
                    <span class="text-white">{{ $currentHospitalName ?? 'Carregando...' }}</span>
                </div>
                <div>
                    <span class="font-medium">Data:</span> 
                    <span class="text-white">{{ date('d/m/Y') }}</span>
                </div>
            </div>
            
            @if($currentPatient && $currentPatient['has_patient'] && $patientDetails)
                <div class="flex flex-col sm:flex-row sm:space-x-4 space-y-1 sm:space-y-0">
                    <div>
                        <span class="font-medium">Prontuário:</span> 
                        <span class="text-white font-mono">{{ $patientDetails->nr_prontuario ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <span class="font-medium">Atendimento:</span> 
                        <span class="text-white font-mono">{{ $currentPatient['nr_atendimento'] }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
    
    <!-- Close button -->
    <div class="absolute top-4 right-4 sm:top-6 sm:right-6">
        <button 
            wire:click="closeModal"
            class="text-white/80 hover:text-white transition-colors focus:outline-none bg-white/10 hover:bg-white/20 rounded-full p-1.5"
        >
            <svg class="h-5 w-5 sm:h-6 sm:w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>