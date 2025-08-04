@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null,
])

<div x-show="activeTab === 'tab-a'" 
     class="p-1 sm:p-2 lg:p-3 h-full flex flex-col"
     style="min-height: 0;">
    
    @if($currentPatient && !$currentPatient['has_patient'])
        <div class="flex flex-col items-center justify-center py-4 sm:py-6 text-gray-600 flex-1">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-gray-700 text-sm sm:text-base">Leito Vazio</p>
            <p class="text-gray-500 text-xs">Este leito não possui paciente internado no momento.</p>
        </div>
    @elseif($patientDetails)
        <!-- Use the dedicated chat component -->
        <div class="flex-1 min-h-0">
            @livewire('chat-component', [
                'patientId' => $currentPatient['nr_atendimento'] ?? '',
                'bedUnit' => $patientDetails->cd_unidade_basica ?? null
            ], key('chat-' . ($currentPatient['nr_atendimento'] ?? 'empty')))
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-4 text-gray-600 flex-1">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-red-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-gray-700 text-sm">Erro ao carregar detalhes do paciente</p>
        </div>
    @endif
</div>