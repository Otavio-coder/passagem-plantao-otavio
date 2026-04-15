@props([
    'showAlertsModal'     => false,
    'alertsGroupedByType' => [],
    'activeAlertsCount'   => 0,
    'currentPatient'      => null,
])

@if($showAlertsModal && $activeAlertsCount > 0)
    <div class="fixed inset-0 z-[10000]"
         x-data="{ show: true }"
         x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="show = false; setTimeout(() => $wire.closeAlertsModal(), 250)"></div>

        {{-- Container --}}
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div class="relative bg-white rounded-none sm:rounded-2xl shadow-2xl w-[min(960px,96vw)] h-[min(420px,88vh)] flex flex-col overflow-hidden"
                 @click.stop
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                {{-- Header --}}
                <div class="bg-amber-500 px-4 py-3 flex-shrink-0 border-b border-amber-600/40">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="bg-white/20 rounded-lg p-2">
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-white" />
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-white">Alertas Ativos</h3>
                                <p class="text-white/90 text-xs">Atendimento {{ $currentPatient['nr_atendimento'] ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <button
                            @click="show = false; setTimeout(() => $wire.closeAlertsModal(), 250)"
                            class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors"
                            title="Fechar alertas"
                        >
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                    <div class="space-y-4">
                        @foreach($alertsGroupedByType as $type => $alerts)
                            @php
                                $normalizedType = strtoupper((string) $type);
                                $isIsolation = $normalizedType === 'ISOLAMENTO';
                                $typeLabel = mb_strtoupper($isIsolation ? 'ISOLAMENTO' : $normalizedType);
                                $typeBadgeClass = $isIsolation
                                    ? 'text-amber-700'
                                    : 'text-blue-700';
                            @endphp
                            <div>
                                {{-- Type Header --}}
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold {{ $typeBadgeClass }}">{{ $typeLabel }}</span>
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        {{ count($alerts) }} alerta(s)
                                    </span>
                                </div>

                                {{-- Alerts List — all entries are already active (filtered in PatientModal) --}}
                                @foreach($alerts as $alert)
                                    @php
                                        $rowClass = $isIsolation
                                            ? 'border-amber-300 bg-amber-50'
                                            : 'border-blue-200 bg-blue-50';
                                        $messageClass = $isIsolation
                                            ? 'bg-white/80 border-amber-100 text-amber-900'
                                            : 'bg-white/90 border-blue-100 text-gray-800';
                                    @endphp
                                    <div class="border-l-4 border {{ $rowClass }} p-3 rounded-r-lg rounded-l-sm mb-2 shadow-sm">
                                        {{-- Alert Header --}}
                                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                            <div></div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                @if(!empty($alert['sent_date_formatted']))
                                                    <span class="text-xs text-gray-500">
                                                        Comunicado: {{ $alert['sent_date_formatted'] }}
                                                    </span>
                                                @endif

                                                @if(!empty($alert['start_date_formatted']))
                                                    <span class="text-xs text-gray-500">
                                                        Início: {{ $alert['start_date_formatted'] }}
                                                    </span>
                                                @endif

                                                @if(!empty($alert['end_date_formatted']))
                                                    <span class="text-xs text-gray-500">
                                                        Até: {{ $alert['end_date_formatted'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Alert Message --}}
                                        <div class="text-sm leading-relaxed p-2 rounded border {{ $messageClass }}">
                                            {{ $alert['message'] }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Footer --}}
                <div class="bg-gray-100 px-4 py-3 border-t border-gray-200 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-xs text-gray-500">
                            <x-heroicon-o-information-circle class="h-4 w-4 mr-1" />
                            Total: {{ $activeAlertsCount }} alerta(s) ativo(s)
                        </div>

                        <button
                            @click="show = false; setTimeout(() => $wire.closeAlertsModal(), 250)"
                            class="px-3 py-1.5 bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-md text-sm font-medium transition-colors"
                            title="Fechar alertas"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
