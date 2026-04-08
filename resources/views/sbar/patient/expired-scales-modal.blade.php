<div x-data="{ show: @entangle('isOpen') }" x-init="$watch('show', v => { document.body.style.overflow = v ? 'hidden' : ''; })" x-show="show" x-cloak style="display: none;">
    <div class="fixed inset-0 z-[9998]" x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$wire.close()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl lg:w-[600px] lg:h-[85vh] shadow-2xl" @click.stop x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4" x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0" x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-red-600 to-orange-500 flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-white leading-tight">Escalas Pendentes</h2>
                            <p class="text-white/70 text-xs leading-tight">Avaliado às {{ now()->format('H:i') }}</p>
                        </div>
                        @if($totalExpired > 0)
                            <span class="px-2.5 py-0.5 bg-white text-red-600 text-xs font-bold rounded-full flex-shrink-0">{{ $totalExpired }} {{ $totalExpired === 1 ? 'paciente' : 'pacientes' }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button wire:click="refresh" wire:loading.attr="disabled" title="Atualizar" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" wire:loading.class="animate-spin" wire:target="refresh" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                        <button wire:click="close" title="Fechar" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0 bg-gray-50" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">
                    @if($loading)
                        <div class="flex flex-col items-center justify-center h-full">
                            <div class="relative mb-4">
                                <div class="w-12 h-12 border-4 border-red-200 border-t-red-500 rounded-full animate-spin"></div>
                            </div>
                            <p class="text-sm text-gray-500">Carregando escalas pendentes...</p>
                        </div>
                    @elseif(count($patientsWithExpiredScales) === 0)
                        <div class="flex flex-col items-center justify-center h-full text-center px-6">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <p class="text-lg font-semibold text-gray-700">Todas em dia!</p>
                            <p class="text-sm text-gray-400 mt-1">Nenhuma escala pendente no turno atual.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-200">
                            @foreach($patientsWithExpiredScales as $patient)
                                <div class="pl-4 pr-4 py-4 border-l-4 {{ $patient['priority_border_class'] ?? 'border-l-amber-400' }} bg-white hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold text-sm flex-shrink-0 shadow-sm">{{ $patient['bed'] }}</div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-semibold text-gray-900 text-sm truncate">{{ $patient['name'] }}</div>
                                            <div class="text-xs text-gray-400 leading-tight">Pront. {{ $patient['medical_record'] }} {{ ($patient['age'] && $patient['age'] !== 'N/A') ? '· ' . $patient['age'] . 'a' : '' }}</div>
                                        </div>
                                        <span class="flex-shrink-0 px-2.5 py-1 rounded-full text-xs font-bold {{ $patient['priority_count_class'] ?? 'bg-amber-100 text-amber-700' }}">{{ $patient['total_expired'] }} pendente{{ $patient['total_expired'] > 1 ? 's' : '' }}</span>
                                    </div>
                                    <div class="space-y-2 pl-13">
                                        @foreach($patient['expired_scales'] as $scale)
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                                                <span class="font-semibold text-gray-800 w-14 flex-shrink-0">{{ $scale['name'] }}</span>
                                                @if($scale['last_value'] !== null && $scale['last_value'] !== '')
                                                    <span class="text-gray-500 text-xs">Último: <span class="font-semibold text-gray-800">{{ $scale['last_value'] }}</span></span>
                                                @else
                                                    <span class="text-gray-400 text-xs italic">sem valor</span>
                                                @endif
                                                <span class="ml-auto flex-shrink-0 {{ !empty($scale['last_timestamp_label']) ? 'text-gray-400 text-xs' : 'text-red-500 text-xs font-medium' }}">{{ $scale['last_timestamp_label'] ?? 'sem registro' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-100 flex-shrink-0 flex items-center justify-between">
                    <span class="text-xs text-gray-500">Manhã 07–13h · Tarde 13–19h · Noite 19–07h</span>
                    @if(!$loading && $totalExpired > 0)
                        <span class="text-xs text-gray-500 font-medium">{{ $totalExpired }} {{ $totalExpired === 1 ? 'paciente' : 'pacientes' }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
