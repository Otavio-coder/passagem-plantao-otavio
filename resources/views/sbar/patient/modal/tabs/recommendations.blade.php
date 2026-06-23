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

        @include('sbar.patient.modal.tabs.recommendations.plan', [
            'plan'               => $prescriptions,
            'scheduleDate'       => $scheduleDate,
            'medicationSchedule' => $medicationSchedule,
            'planDisplayData'    => $planDisplayData,
        ])

    @elseif($planError)

        <div class="flex flex-col items-center justify-center text-center py-16">
            <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mb-3">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-400" />
            </div>
            <p class="text-sm font-medium text-gray-700 mb-1">Falha ao carregar o plano terapêutico</p>
            <p class="text-xs text-gray-400 mb-4">Verifique sua conexão e tente novamente.</p>
            <button wire:click="reloadPrescriptions"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors">
                <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
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
