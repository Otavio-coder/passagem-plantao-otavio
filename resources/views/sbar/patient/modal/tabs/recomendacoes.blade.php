@props([
    'planLoaded'         => false,
    'planError'          => false,
    'prescriptions'      => null,
    'scheduleDate'       => '',
    'medicationSchedule' => [],
    'planDisplayData'    => [],
])

{{-- window.therapeuticPlan (Alpine component) is registered via @script in modal/index.blade.php --}}

<div class="p-3 sm:p-4 lg:p-6 h-full overflow-y-auto">

    @if($planLoaded && $prescriptions)

        @include('sbar.patient.modal.tabs.recomendacoes.plan', [
            'plan'               => $prescriptions,
            'scheduleDate'       => $scheduleDate,
            'medicationSchedule' => $medicationSchedule,
            'planDisplayData'    => $planDisplayData,
        ])

    @elseif($planError)

        <div class="flex flex-col items-center justify-center text-center py-16">
            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-700 mb-1">Falha ao carregar o plano terapêutico</p>
            <p class="text-xs text-gray-400 mb-4">Verifique sua conexão e tente novamente.</p>
            <button wire:click="reloadPrescriptions"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Tentar novamente
            </button>
        </div>

    @else

        <div class="flex flex-col items-center justify-center py-16">
            <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-4 text-sm text-gray-500 font-medium">Carregando plano terapêutico...</p>
        </div>

    @endif

</div>
