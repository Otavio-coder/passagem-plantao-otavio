{{-- resources/views/livewire/expired-scales-modal.blade.php --}}
<div>
    @if($isOpen)
        <div
            class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
            x-data="{ show: @entangle('isOpen') }"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.close()"></div>

            <div
                class="relative w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-xl shadow-2xl flex flex-col"
                style="height: 88vh; max-height: 88vh; min-height: 200px;"
                @click.stop
            >
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-red-600 to-orange-500 rounded-t-2xl sm:rounded-t-xl flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="min-w-0">
                            <h2 class="text-sm font-bold text-white leading-tight">Escalas Pendentes</h2>
                            <p class="text-white/65 text-xs leading-tight">Avaliado às {{ now()->format('H:i') }}</p>
                        </div>
                        @if($totalExpired > 0)
                            <span class="px-2 py-0.5 bg-white text-red-600 text-xs font-bold rounded-full flex-shrink-0">
                                {{ $totalExpired }} {{ $totalExpired === 1 ? 'pac.' : 'pac.' }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button wire:click="refresh" wire:loading.attr="disabled" title="Atualizar"
                                class="p-1.5 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refresh" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <button wire:click="close" title="Fechar" class="p-1.5 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0">
                    @if($loading)
                        <div class="flex flex-col items-center justify-center h-full">
                            <div class="w-7 h-7 border-2 border-t-red-500 border-gray-200 rounded-full animate-spin mb-2"></div>
                            <p class="text-xs text-gray-400">Carregando...</p>
                        </div>

                    @elseif(count($patientsWithExpiredScales) === 0)
                        <div class="flex flex-col items-center justify-center h-full text-center px-6">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-3">
                                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-700">Todas em dia!</p>
                            <p class="text-xs text-gray-400 mt-1">Nenhuma escala pendente no turno atual.</p>
                        </div>

                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($patientsWithExpiredScales as $patient)
                                @php
                                    $borderColor = match($patient['priority'] ?? 'medium') {
                                        'critical' => 'border-l-red-500',
                                        'high'     => 'border-l-orange-400',
                                        default    => 'border-l-amber-400',
                                    };
                                    $countColor = match($patient['priority'] ?? 'medium') {
                                        'critical' => 'bg-red-100 text-red-700',
                                        'high'     => 'bg-orange-100 text-orange-700',
                                        default    => 'bg-amber-100 text-amber-700',
                                    };
                                @endphp
                                <div class="pl-3 pr-4 py-3 border-l-4 {{ $borderColor }}">

                                    {{-- Patient row --}}
                                    <div class="flex items-center gap-2.5 mb-2">
                                        <div class="w-9 h-9 bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold text-[11px] flex-shrink-0 shadow-sm">
                                            {{ $patient['bed'] }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-semibold text-gray-900 text-sm truncate">{{ $patient['name'] }}</div>
                                            <div class="text-[11px] text-gray-400 leading-tight">
                                                Pront. {{ $patient['medical_record'] }}
                                                @if($patient['age'] && $patient['age'] !== 'N/A')
                                                    · {{ $patient['age'] }}a
                                                @endif
                                            </div>
                                        </div>
                                        <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-bold {{ $countColor }}">
                                            {{ $patient['total_expired'] }} pendente{{ $patient['total_expired'] > 1 ? 's' : '' }}
                                        </span>
                                    </div>

                                    {{-- Scales list --}}
                                    <div class="space-y-1.5 pl-11">
                                        @foreach($patient['expired_scales'] as $scale)
                                            @php
                                                $ts = $scale['last_timestamp'] ?? null;
                                                $tsLabel = null;
                                                if ($ts) {
                                                    try {
                                                        $dt = \Carbon\Carbon::parse($ts);
                                                        $tsLabel = $dt->isToday()
                                                            ? 'Hoje ' . $dt->format('H:i')
                                                            : ($dt->isYesterday() ? 'Ontem ' . $dt->format('H:i') : $dt->format('d/m H:i'));
                                                    } catch (\Throwable $e) {}
                                                }
                                                $isShiftBased = !in_array($scale['name'], ['TEV']);
                                            @endphp
                                            <div class="flex items-center gap-2 text-xs">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>
                                                <span class="font-semibold text-gray-800 w-12 flex-shrink-0">{{ $scale['name'] }}</span>
                                                @if($scale['last_value'] !== null && $scale['last_value'] !== '')
                                                    <span class="text-gray-500">
                                                        Último: <span class="font-semibold text-gray-800">{{ $scale['last_value'] }}</span>
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 italic">sem valor</span>
                                                @endif
                                                <span class="ml-auto flex-shrink-0 {{ $tsLabel ? 'text-gray-400' : 'text-red-400 font-medium' }}">
                                                    {{ $tsLabel ?? 'sem registro' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="px-4 py-2 border-t border-gray-100 bg-gray-50 rounded-b-2xl sm:rounded-b-xl flex-shrink-0 flex items-center justify-between flex-wrap gap-1">
                    <span class="text-[10px] text-gray-400">Manhã 07–13h · Tarde 13–19h · Noite 19–07h</span>
                    @if(!$loading && $totalExpired > 0)
                        <span class="text-[10px] text-gray-400">{{ $totalExpired }} {{ $totalExpired === 1 ? 'paciente' : 'pacientes' }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
