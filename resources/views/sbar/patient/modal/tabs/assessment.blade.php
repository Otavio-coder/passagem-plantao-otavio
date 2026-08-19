@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null,
])

<div x-show="activeTab === 'tab-a'"
     class="p-0.5 sm:p-1 lg:p-1.5 h-full flex flex-col"
     style="min-height: 0;">

    @if($currentPatient && !$currentPatient['has_patient'])
        <div class="flex flex-col items-center justify-center py-4 sm:py-6 text-gray-600 flex-1">
            <x-healthicons-o-inpatient class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400 mb-2 animate-pulse" />
            <p class="text-gray-700 text-sm sm:text-base">Leito Vago</p>
            <p class="text-gray-500 text-xs">Este leito não possui paciente internado no momento.</p>
        </div>
    @elseif(!empty($currentPatient['nr_atendimento']))
        {{-- Chat renderiza direto dos dados de navegação — não espera patientDetails (carga diferida) --}}
        <div class="flex-1 min-h-0">
            @livewire('sbar-chat-component', [
                'patientId' => $currentPatient['nr_atendimento'] ?? '',
                'cdPessoaFisica' => $currentPatient['cd_pessoa_fisica'] ?? null,
                'bedUnit' => $patientDetails->bed_name ?? $patientDetails->cd_unidade_basica ?? $currentPatient['cd_unidade_basica'] ?? null,
                'internmentDays' => $currentPatient['internment_days'] ?? null,
                'sectorId' => $currentPatient['cd_setor_atendimento'] ?? null,
                'sectorName' => $currentPatient['ds_prescricao'] ?? $currentPatient['ds_setor_atendimento'] ?? null,
            ], key('chat-' . ($currentPatient['nr_atendimento'] ?? 'empty')))
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-4 text-gray-600 flex-1">
            <x-heroicon-o-exclamation-triangle class="w-6 h-6 sm:w-8 sm:h-8 text-red-500 mb-2" />
            <p class="text-gray-700 text-sm">Erro ao carregar detalhes do paciente</p>
        </div>
    @endif
</div>
