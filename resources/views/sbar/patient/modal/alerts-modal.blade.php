@props([
    'showAlertsModal' => false,
    'patientAlerts' => [],
    'currentPatient' => null
])

@if($showAlertsModal && !empty($patientAlerts))
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
            <div class="relative bg-white rounded-none sm:rounded-2xl shadow-2xl w-full h-full sm:h-auto sm:max-h-[85vh] sm:w-[500px] flex flex-col overflow-hidden"
                 @click.stop
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                {{-- Header --}}
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-3 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="bg-white/20 rounded-lg p-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-white">ALERTAS ATIVOS</h3>
                                <p class="text-white/90 text-xs">Atendimento: {{ $currentPatient['nr_atendimento'] ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <button
                            @click="show = false; setTimeout(() => $wire.closeAlertsModal(), 250)"
                            class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors"
                            title="Fechar alertas"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                    <div class="space-y-4">
                        @php
                            $alertsByType = collect($patientAlerts)->groupBy('type');
                            $activeAlertsCount = collect($patientAlerts)->filter(function($alert) {
                                return !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                            })->count();
                        @endphp
                        
                        @if($activeAlertsCount === 0)
                            <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-medium">Todos os alertas expiraram</p>
                            </div>
                        @else
                            @foreach($alertsByType as $type => $alerts)
                                @php
                                    $activeAlerts = collect($alerts)->filter(function($alert) {
                                        return !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                                    });
                                @endphp
                                
                                @if($activeAlerts->count() > 0)
                                    <div>
                                        {{-- Type Header --}}
                                        <div class="flex items-center gap-2 mb-3">
                                            @if($type === 'ISOLAMENTO')
                                                <div class="bg-yellow-100 rounded-lg p-1.5">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-sm font-semibold text-yellow-800">Precauções de Isolamento</h4>
                                            @else
                                                <div class="bg-red-100 rounded-lg p-1.5">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-sm font-semibold text-red-800">Alertas do Paciente</h4>
                                            @endif
                                        </div>
                                        
                                        {{-- Alerts List --}}
                                        @foreach($activeAlerts as $index => $alert)
                                            @php
                                                $isActive = !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                                            @endphp
                                            
                                            @if($isActive)
                                                <div class="border-l-4 {{ $alert['type'] === 'ISOLAMENTO' ? 'border-yellow-500 bg-yellow-50' : 'border-red-500 bg-red-50' }} p-3 rounded-r-lg mb-2 shadow-sm">
                                                    {{-- Alert Header --}}
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                                            {{ $alert['type'] === 'ISOLAMENTO' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800' }}">
                                                            {{ $alert['type'] }}
                                                        </span>
                                                        
                                                        <div class="text-xs text-gray-500">
                                                            @if($alert['type'] === 'ISOLAMENTO' && isset($alert['start_date']) && $alert['start_date'])
                                                                <span class="bg-white px-2 py-0.5 rounded mr-1 border border-gray-200">
                                                                    Início: {{ \Carbon\Carbon::parse($alert['start_date'])->format('d/m/Y') }}
                                                                </span>
                                                            @endif
                                                            
                                                            @if(isset($alert['end_date']) && $alert['end_date'])
                                                                @php
                                                                    $endDate = \Carbon\Carbon::parse($alert['end_date']);
                                                                    $isExpired = $endDate->isPast();
                                                                @endphp
                                                                @if(!$isExpired)
                                                                    <span class="bg-white px-2 py-0.5 rounded border border-gray-200">
                                                                        Até: {{ $endDate->format('d/m/Y') }}
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-medium border border-green-200">
                                                                    Ativo
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    
                                                    {{-- Alert Message --}}
                                                    <div class="text-sm {{ $alert['type'] === 'ISOLAMENTO' ? 'text-yellow-800' : 'text-red-800' }} leading-relaxed bg-white/60 p-2 rounded border border-gray-100">
                                                        {{ $alert['message'] }}
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
                
                {{-- Footer --}}
                <div class="bg-gray-100 px-4 py-3 border-t border-gray-200 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-xs text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Total: {{ $activeAlertsCount }} alerta(s) ativo(s)
                        </div>
                        
                        <button
                            @click="show = false; setTimeout(() => $wire.closeAlertsModal(), 250)"
                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm font-medium transition-colors"
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
