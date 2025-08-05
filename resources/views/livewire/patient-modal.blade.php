<div x-data="{ 
    showAlertsModal: @entangle('showAlertsModal'),
    init() {
        // Observar mudanças no showAlertsModal do Livewire
        this.$watch('showAlertsModal', (value) => {
            if (value) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
    }
}">
    {{-- Modal de Alertas (children modal) - Passando controle para Alpine --}}
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
                    @click.away="!showAlertsModal && $wire.closeModal()"
                    @click.stop
                >
                    {{-- Overlay de carregamento sobre o conteúdo do modal --}}
                    @if($loadingPatient)
                        <div class="absolute inset-0 z-50 flex items-center justify-center bg-white/80 backdrop-blur-sm">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-8 h-8 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin"></div>
                                <span class="text-[#004D9D] font-medium text-sm">Carregando dados do paciente...</span>
                            </div>
                        </div>
                    @endif

                    {{-- Cabeçalho --}}
                    <x-patient-modal.header 
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails" 
                    />

                    {{-- Abas --}}
                    <x-patient-modal.tabs />

                    {{-- Conteúdo das abas SBAR --}}
                    <div class="flex-1 min-h-0 bg-gray-50 relative">
                        {{-- Situação --}}
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

                        {{-- Background --}}
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

                        {{-- Avaliação (Chat) --}}
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

                        {{-- Recomendações --}}
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

                    {{-- Rodapé --}}
                    <x-patient-modal.footer />
                </div>
            </div>
        </div>
    @endif
</div>