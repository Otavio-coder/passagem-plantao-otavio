<div>
    {{-- Main modal loading overlay - FIXED: Show loading for all modal operations --}}
    <div
        wire:loading.class="block"
        wire:loading.remove.class="hidden"
        wire:target="openModal,loadPatientDetails,closeModal"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#004D9D]/20 hidden"
        role="status"
        aria-live="polite"
    >
        <div class="flex flex-col items-center justify-center space-y-2 min-h-screen">
            <div class="w-12 h-12 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin" aria-hidden="true"></div>
            <span class="text-[#004D9D] font-medium">Carregando detalhes do paciente...</span>
        </div>
    </div>

    {{-- Alerts modal --}}
    <x-patient-modal.alerts-modal 
        :showAlertsModal="$showAlertsModal"
        :patientAlerts="$patientAlerts"
        :currentPatient="$currentPatient"
    />

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"></div>
                <div
                    class="relative bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-4xl md:max-w-5xl lg:max-w-6xl xl:max-w-7xl mx-auto h-[95vh] sm:h-[90vh] transition-all flex flex-col overflow-hidden"
                    x-data="{
                        activeTab: 'tab-s',
                        activeCpoeCategory: 'cpoe-exames'
                    }"
                    data-patient-id="{{ $currentPatient['nr_atendimento'] ?? '' }}"
                    data-shift="{{ $currentShift ?? '' }}"
                    @click.away="$wire.closeModal()"
                >
                    {{-- FIXED: Show loading overlay over entire modal content --}}
                    @if($loadingPatient)
                        <div class="absolute inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-sm">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-8 h-8 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin"></div>
                                <span class="text-[#004D9D] font-medium text-sm">Carregando dados do paciente...</span>
                            </div>
                        </div>
                    @endif

                    {{-- Header --}}
                    <x-patient-modal.header 
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails" 
                    />

                    {{-- Tabs --}}
                    <x-patient-modal.tabs />

                    {{-- Content --}}
                    <div class="flex-1 min-h-0 bg-gray-50 relative">
                        {{-- Situação Tab --}}
                        <div
                            x-show="activeTab === 'tab-s'"
                            x-cloak
                            class="h-full overflow-y-auto"
                        >
                            <x-patient-modal.content.sbar-situacao 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>

                        {{-- Background Tab --}}
                        <div
                            x-show="activeTab === 'tab-b'"
                            x-cloak
                            class="h-full overflow-y-auto"
                        >
                            <x-patient-modal.content.sbar-background 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>

                        {{-- Avaliação Tab (Chat) --}}
                        <div
                            x-show="activeTab === 'tab-a'"
                            x-cloak
                            class="h-full"
                        >
                            <x-patient-modal.content.sbar-avaliacao 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        {{-- Recomendações Tab --}}
                        <div
                            x-show="activeTab === 'tab-r'"
                            x-cloak
                            class="h-full overflow-y-auto"
                        >
                            <x-patient-modal.content.sbar-recomendacoes 
                                :loadingPatient="$loadingPatient"
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