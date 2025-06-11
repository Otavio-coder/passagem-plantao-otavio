@props([
    'showAlertsModal' => false,
    'patientAlerts' => [],
    'currentPatient' => null
])

@if($showAlertsModal && !empty($patientAlerts))
    <div class="fixed inset-0 z-[60] overflow-y-auto" style="z-index: 9999 !important;">
        <!-- Backdrop with higher opacity -->
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm transition-opacity"></div>
        
        <!-- Alert Modal Content -->
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg mx-auto transform transition-all">
                
                <!-- Alert Header -->
                <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-6 py-4 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="bg-white/20 rounded-full p-2 mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">ALERTAS ATIVOS</h3>
                                <p class="text-white/90 text-sm">Atendimento: {{ $currentPatient['nr_atendimento'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                        
                        <button 
                            wire:click="closeAlertsModal"
                            class="text-white/80 hover:text-white transition-colors p-1 rounded-full hover:bg-white/10"
                            title="Fechar alertas"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Alert Content -->
                <div class="p-6 max-h-96 overflow-y-auto">
                    <div class="space-y-4">
                        @php
                            $alertsByType = collect($patientAlerts)->groupBy('type');
                            $activeAlertsCount = collect($patientAlerts)->filter(function($alert) {
                                return !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                            })->count();
                        @endphp
                        
                        @if($activeAlertsCount === 0)
                            <div class="text-center py-8">
                                <div class="text-gray-400 mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-gray-500">Todos os alertas expiraram</p>
                            </div>
                        @else
                            @foreach($alertsByType as $type => $alerts)
                                @php
                                    // Filter only active alerts for display
                                    $activeAlerts = collect($alerts)->filter(function($alert) {
                                        return !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                                    });
                                @endphp
                                
                                @if($activeAlerts->count() > 0)
                                    <div class="mb-6">
                                        <!-- Type Header -->
                                        <div class="flex items-center mb-3">
                                            @if($type === 'ISOLAMENTO')
                                                <div class="bg-red-100 rounded-full p-2 mr-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-lg font-semibold text-red-800">Precauções de Isolamento</h4>
                                            @else
                                                <div class="bg-amber-100 rounded-full p-2 mr-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                </div>
                                                <h4 class="text-lg font-semibold text-amber-800">Alertas do Paciente</h4>
                                            @endif
                                        </div>
                                        
                                        <!-- Alerts List -->
                                        @foreach($activeAlerts as $index => $alert)
                                            @php
                                                $isActive = !isset($alert['end_date']) || $alert['end_date'] === null || \Carbon\Carbon::parse($alert['end_date'])->isFuture();
                                            @endphp
                                            
                                            @if($isActive)
                                                <div class="border-l-4 {{ $alert['type'] === 'ISOLAMENTO' ? 'border-red-500 bg-red-50' : 'border-amber-500 bg-amber-50' }} p-4 rounded-r-lg mb-3 shadow-sm">
                                                    <!-- Alert Header -->
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                            {{ $alert['type'] === 'ISOLAMENTO' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">
                                                            {{ $alert['type'] }}
                                                        </span>
                                                        
                                                        <div class="text-xs text-gray-500">
                                                            @if($alert['type'] === 'ISOLAMENTO' && isset($alert['start_date']) && $alert['start_date'])
                                                                <span class="bg-white px-2 py-1 rounded mr-1">
                                                                    Início: {{ \Carbon\Carbon::parse($alert['start_date'])->format('d/m/Y') }}
                                                                </span>
                                                            @endif
                                                            
                                                            @if(isset($alert['end_date']) && $alert['end_date'])
                                                                @php
                                                                    $endDate = \Carbon\Carbon::parse($alert['end_date']);
                                                                    $isExpired = $endDate->isPast();
                                                                @endphp
                                                                @if(!$isExpired)
                                                                    <span class="bg-white px-2 py-1 rounded">
                                                                        Até: {{ $endDate->format('d/m/Y') }}
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded font-medium">
                                                                    Ativo
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Alert Message with justified text -->
                                                    <div class="text-sm {{ $alert['type'] === 'ISOLAMENTO' ? 'text-red-800' : 'text-amber-800' }} leading-relaxed text-justify bg-white/50 p-3 rounded border">
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
                
                <!-- Alert Footer -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-xl border-t">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center text-xs text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Total: {{ $activeAlertsCount }} alerta(s) ativo(s)
                        </div>
                        
                        <button 
                            wire:click="closeAlertsModal"
                            class="px-4 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 flex items-center"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
