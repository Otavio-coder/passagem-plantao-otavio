@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null,
])

<div class="p-3 sm:p-4 lg:p-6 h-full overflow-y-auto">
    {{-- Verifica se os dados CPOE já estão carregados --}}
    @if($patientDetails && (
        (isset($patientDetails->cpoe_procedures) && is_array($patientDetails->cpoe_procedures)) ||
        (isset($patientDetails->cpoe_medications) && is_array($patientDetails->cpoe_medications)) ||
        (isset($patientDetails->cpoe_nutrition) && is_array($patientDetails->cpoe_nutrition)) ||
        (isset($patientDetails->cpoe_recommendations) && is_array($patientDetails->cpoe_recommendations)) ||
        (isset($patientDetails->cpoe_interventions) && is_array($patientDetails->cpoe_interventions))
    ))
        {{-- Dados CPOE carregados - mostra o conteúdo --}}
        @include('livewire.partials.cpoe-content', [
            'loadingPatient' => $loadingPatient,
            'currentPatient' => $currentPatient,
            'patientDetails' => $patientDetails,
        ])
    @elseif($loadingPatient)
        {{-- Estado de carregamento --}}
        <div class="flex flex-col items-center justify-center py-12">
            <div class="relative">
                <div class="w-12 h-12 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                <div class="absolute inset-0 w-12 h-12 border-4 border-blue-600/20 border-t-transparent rounded-full animate-pulse"></div>
            </div>
            <p class="mt-4 text-gray-600 text-sm font-medium">Carregando prescrições...</p>
        </div>
    @else
        {{-- Botão para carregar CPOE (lazy loading) --}}
        <div class="text-center py-12">
            <div class="mb-6">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Prescrições Médicas</h3>
                <p class="text-sm text-gray-500 mb-6">Clique no botão abaixo para carregar exames, medicamentos, nutrição e recomendações</p>
            </div>

            <button
                onclick="Livewire.dispatch('loadCpoeData')"
                class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors shadow-sm"
            >
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Carregar Prescrições
            </button>

            <p class="mt-4 text-xs text-gray-400">
                Os dados serão carregados sob demanda para melhor performance
            </p>
        </div>
    @endif
</div>

<style>
    /* Mantém estilos locais mínimos caso o componente seja usado isoladamente */
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
    [x-show][style*="display: none"] {
        display: none !important;
        pointer-events: none !important;
    }
</style>
