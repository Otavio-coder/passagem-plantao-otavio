<div
    x-data="{ open: true }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center p-0 sm:p-4"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div class="absolute inset-0 bg-black/40" @click="open = false; $wire.dismissBedReminder()"></div>

    <div
        class="relative bg-white w-full sm:w-auto sm:min-w-[360px] sm:max-w-sm rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden"
        @click.stop
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
    >
        {{-- Handle drag — visual only --}}
        <div class="sm:hidden flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
        </div>

        {{-- Header --}}
        <div class="px-5 pt-3 sm:pt-5 pb-1 flex items-start gap-3">
            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                <x-heroicon-o-calendar-days class="w-5 h-5 text-[#004D9D]" />
            </div>
            <div class="min-w-0">
                <h3 class="font-bold text-gray-900 text-base leading-tight">Configure seus leitos</h3>
                <p class="text-xs text-gray-500 mt-0.5 leading-snug">
                    Selecione os leitos que você acompanha para iniciar a passagem de plantão diretamente.
                </p>
            </div>
        </div>

        {{-- Content --}}
        <div class="px-5 py-3">
            @php
                $configuredBedCount = Auth::user()->handoverBeds()->count();
            @endphp
            @if($configuredBedCount > 0)
                <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-sm text-green-800">
                    <x-heroicon-o-check-circle class="w-4 h-4 text-green-600 flex-shrink-0" />
                    <span>Você tem <strong>{{ $configuredBedCount }} {{ $configuredBedCount === 1 ? 'leito configurado' : 'leitos configurados' }}</strong>.</span>
                </div>
            @else
                <div class="flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm text-amber-800">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500 flex-shrink-0" />
                    <span>Nenhum leito configurado ainda.</span>
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="px-5 pb-5 flex items-center gap-2">
            <a
                href="{{ route('user.preferences.index') }}#leitos"
                class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#004D9D] hover:bg-[#003d7a] text-white text-sm font-semibold rounded-xl transition-colors"
                @click="open = false; $wire.dismissBedReminder()"
            >
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Configurar leitos
            </a>
            <button
                @click="open = false; $wire.dismissBedReminder()"
                class="flex-shrink-0 px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors"
            >
                Agora não
            </button>
        </div>
    </div>
</div>
