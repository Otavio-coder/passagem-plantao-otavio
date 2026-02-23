{{-- resources/views/livewire/expired-scales-modal.blade.php --}}
<div>
    @if($isOpen)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4"
            x-data="{ show: @entangle('isOpen') }"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div class="fixed inset-0 bg-black/50" @click="$wire.close()"></div>

            <div
                class="relative w-full max-w-2xl bg-white rounded-lg shadow-xl flex flex-col"
                style="max-height: 85vh; height: 600px;"
                @click.stop
            >
                {{-- Header Compacto --}}
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-red-600 rounded-t-lg flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-white">Escalas Pendentes</h2>
                        @if($totalExpired > 0)
                            <span class="px-2 py-0.5 bg-white/20 text-white text-xs font-medium rounded">
                                {{ $totalExpired }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            wire:click="refresh"
                            wire:loading.attr="disabled"
                            class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors"
                        >
                            <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refresh" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <button wire:click="close" class="p-1.5 text-white/80 hover:text-white hover:bg-white/10 rounded transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Content com Scroll --}}
                <div class="flex-1 overflow-y-auto min-h-0">
                    @if($loading)
                        <div class="flex items-center justify-center h-full">
                            <div class="w-6 h-6 border-2 border-t-red-500 border-gray-200 rounded-full animate-spin"></div>
                        </div>
                    @elseif(count($patientsWithExpiredScales) === 0)
                        <div class="flex flex-col items-center justify-center h-full text-center px-4">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mb-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-700">Todas as escalas em dia</p>
                        </div>
                    @else
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr class="text-left text-xs text-gray-500 uppercase">
                                    <th class="px-3 py-2 font-medium">Leito</th>
                                    <th class="px-3 py-2 font-medium">Paciente</th>
                                    <th class="px-3 py-2 font-medium hidden sm:table-cell">Pront.</th>
                                    <th class="px-3 py-2 font-medium">Escalas Pendentes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($patientsWithExpiredScales as $patient)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center justify-center min-w-[2.5rem] px-2 py-1 bg-[#004D9D] text-white text-xs font-bold rounded">
                                                {{ $patient['bed'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="font-medium text-gray-900 truncate max-w-[120px] sm:max-w-[180px]">
                                                {{ $patient['name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 sm:hidden">{{ $patient['medical_record'] }}</div>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 hidden sm:table-cell">
                                            {{ $patient['medical_record'] }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($patient['expired_scales'] as $scale)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 bg-red-100 text-red-700 text-xs font-medium rounded">
                                                        {{ $scale['name'] }}
                                                        @if($scale['last_value'] && $scale['last_value'] !== '-')
                                                            <span class="ml-1 text-red-500">{{ $scale['last_value'] }}</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- Footer Compacto --}}
                @if(count($patientsWithExpiredScales) > 0)
                    <div class="px-4 py-2 border-t border-gray-200 bg-gray-50 rounded-b-lg flex-shrink-0">
                        <p class="text-xs text-gray-500">
                            Escalas não avaliadas no turno atual
                        </p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
