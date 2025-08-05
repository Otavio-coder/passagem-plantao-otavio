<div>
    {{-- Alerts modal --}}
    <x-patient-modal.alerts-modal 
        :showAlertsModal="$showAlertsModal"
        :patientAlerts="$patientAlerts"
        :currentPatient="$currentPatient"
    />

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" 
             x-data="{ 
                 modalReady: @js($this->isContentReady()),
                 loadingStage: @entangle('loadingStage'),
                 loadingMessage: @entangle('loadingMessage')
             }"
             x-init="
                 // Listen for patient data loaded event
                 $wire.on('patient-data-loaded', () => {
                     setTimeout(() => {
                         modalReady = true;
                     }, 200);
                 });
                 
                 // Listen for modal state changes
                 $wire.on('modal-state-changed', (data) => {
                     if (!data[0].loading) {
                         modalReady = true;
                     }
                 });
             ">
            <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"></div>
                
                {{-- Loading State - Melhorado --}}
                <div x-show="!modalReady || $wire.isLoading" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="relative bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-4xl md:max-w-5xl lg:max-w-6xl xl:max-w-7xl mx-auto h-[95vh] sm:h-[90vh] flex flex-col overflow-hidden">
                    
                    {{-- Enhanced Loading Indicator --}}
                    <div class="absolute inset-0 bg-gradient-to-br from-white via-blue-50 to-white flex items-center justify-center rounded-2xl z-10">
                        <div class="flex flex-col items-center space-y-6 p-8">
                            {{-- Animated loader --}}
                            <div class="relative">
                                <div class="w-20 h-20 border-4 border-t-[#004D9D] border-r-[#0071B9] border-b-gray-200 border-l-gray-200 rounded-full animate-spin"></div>
                                <div class="absolute inset-0 w-20 h-20 border-4 border-t-transparent border-r-transparent border-b-[#004D9D] border-l-[#0071B9] rounded-full animate-pulse"></div>
                                {{-- Inner circle --}}
                                <div class="absolute inset-3 w-14 h-14 bg-gradient-to-br from-[#004D9D] to-[#0071B9] rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            </div>
                            
                            {{-- Loading text with stage info --}}
                            <div class="text-center space-y-3">
                                <h3 class="text-[#004D9D] font-bold text-xl lg:text-2xl">
                                    Carregando Dados do Paciente
                                </h3>
                                
                                {{-- Dynamic loading message --}}
                                <div class="min-h-[1.5rem]">
                                    <p class="text-gray-600 text-sm lg:text-base font-medium" x-text="loadingMessage || 'Preparando...'"></p>
                                </div>
                                
                                {{-- Stage indicators --}}
                                <div class="flex items-center justify-center space-x-2 mt-4">
                                    <div class="flex space-x-1">
                                        <div class="w-2 h-2 rounded-full transition-colors duration-300"
                                             :class="['fetching', 'processing', 'alerts', 'rendering', 'complete'].includes(loadingStage) ? 'bg-[#004D9D]' : 'bg-gray-300'"></div>
                                        <div class="w-2 h-2 rounded-full transition-colors duration-300"
                                             :class="['processing', 'alerts', 'rendering', 'complete'].includes(loadingStage) ? 'bg-[#004D9D]' : 'bg-gray-300'"></div>
                                        <div class="w-2 h-2 rounded-full transition-colors duration-300"
                                             :class="['alerts', 'rendering', 'complete'].includes(loadingStage) ? 'bg-[#004D9D]' : 'bg-gray-300'"></div>
                                        <div class="w-2 h-2 rounded-full transition-colors duration-300"
                                             :class="['rendering', 'complete'].includes(loadingStage) ? 'bg-[#004D9D]' : 'bg-gray-300'"></div>
                                        <div class="w-2 h-2 rounded-full transition-colors duration-300"
                                             :class="loadingStage === 'complete' ? 'bg-green-500' : 'bg-gray-300'"></div>
                                    </div>
                                </div>
                                
                                {{-- Animated dots --}}
                                <div class="flex justify-center mt-4 space-x-1">
                                    <div class="w-2 h-2 bg-[#004D9D] rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                    <div class="w-2 h-2 bg-[#0071B9] rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                    <div class="w-2 h-2 bg-[#004D9D] rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                                </div>
                                
                                {{-- Progress info --}}
                                <div class="text-xs text-gray-500 mt-2">
                                    <span x-show="loadingStage === 'fetching'">Conectando com base de dados...</span>
                                    <span x-show="loadingStage === 'processing'">Processando informações clínicas...</span>
                                    <span x-show="loadingStage === 'alerts'">Verificando alertas médicos...</span>
                                    <span x-show="loadingStage === 'rendering'">Preparando interface do usuário...</span>
                                    <span x-show="loadingStage === 'complete'">Finalizando...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Background skeleton (subtle) --}}
                    <div class="animate-pulse opacity-30">
                        <div class="flex-shrink-0 bg-gray-200 h-16 sm:h-20 rounded-t-2xl"></div>
                        <div class="flex-1 min-h-0 bg-gray-50 p-4 sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                @for($i = 0; $i < 3; $i++)
                                    <div class="bg-white rounded-lg p-4 shadow-sm">
                                        <div class="w-24 h-4 bg-gray-300 rounded mb-2"></div>
                                        <div class="w-32 h-6 bg-gray-300 rounded"></div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Modal Content - Só mostra quando pronto --}}
                <div x-show="modalReady && !$wire.isLoading" 
                     x-transition:enter="transition ease-out duration-500"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-300"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="relative bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-4xl md:max-w-5xl lg:max-w-6xl xl:max-w-7xl mx-auto h-[95vh] sm:h-[90vh] transition-all flex flex-col overflow-hidden"
                     x-data="{
                         activeTab: 'tab-s',
                         activeCpoeCategory: 'cpoe-exames'
                     }"
                     data-patient-id="{{ $currentPatient['nr_atendimento'] ?? '' }}"
                     data-shift="{{ $currentShift ?? '' }}"
                     @click.away="$wire.closeModal()">
                    
                    {{-- Header - Always visible --}}
                    <x-patient-modal.header 
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails" 
                    />

                    {{-- Tabs - Always visible --}}
                    <x-patient-modal.tabs />

                    {{-- Content --}}
                    <div class="flex-1 min-h-0 bg-gray-50 relative">
                        <div x-show="activeTab === 'tab-s'" x-cloak class="h-full overflow-y-auto">
                            <x-patient-modal.content.sbar-situacao 
                                :loadingPatient="false"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>

                        <div x-show="activeTab === 'tab-b'" x-cloak class="h-full overflow-y-auto">
                            <x-patient-modal.content.sbar-background 
                                :loadingPatient="false"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>

                        <div x-show="activeTab === 'tab-a'" x-cloak class="h-full">
                            <x-patient-modal.content.sbar-avaliacao 
                                :loadingPatient="false"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        <div x-show="activeTab === 'tab-r'" x-cloak class="h-full overflow-y-auto">
                            <x-patient-modal.content.sbar-recomendacoes 
                                :loadingPatient="false"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>
                    </div>

                    {{-- Footer --}}
                    <x-patient-modal.footer />
                </div>
            </div>
        </div>
    @endif
</div>