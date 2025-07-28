<div>
    {{-- Alerts Modal --}}
    <x-patient-modal.alerts-modal 
        :showAlertsModal="$showAlertsModal"
        :patientAlerts="$patientAlerts"
        :currentPatient="$currentPatient" 
    />

    <div 
        x-data="{ showModal: @entangle('showModal'), loadingPatient: @entangle('loadingPatient') }"
        x-show="showModal"
        x-cloak
    >
        <div class="fixed inset-0 z-50 overflow-y-auto" style="overflow: hidden;">
            <style>
                body { overflow: hidden !important; }
            </style>
            <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"></div>
                <div                 
                    class="relative bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-4xl md:max-w-5xl lg:max-w-6xl xl:max-w-7xl mx-auto h-[95vh] sm:h-[90vh] transition-all flex flex-col overflow-hidden"
                    x-data="{
                        activeTab: 'tab-s',
                        activeCpoeCategory: 'cpoe-exames',
                        tabChanged(tab) {
                            if(tab === 'tab-a') {
                                $wire.emit('openChatSession');
                            }
                            this.activeTab = tab;
                        },
                        get patientId() { return @js($currentPatient['nr_atendimento'] ?? null) },
                        get shift() { return @js($currentShift ?? null) }
                    }"
                    x-init="
                        if (patientId && shift && window.bindChatEchoListeners) {
                            window.bindChatEchoListeners(patientId, shift);
                        }
                        $watch('patientId', (value) => {
                            if (value && shift && window.bindChatEchoListeners) {
                                window.bindChatEchoListeners(value, shift);
                            }
                        });
                        $watch('shift', (value) => {
                            if (patientId && value && window.bindChatEchoListeners) {
                                window.bindChatEchoListeners(patientId, value);
                            }
                        });
                    "
                    data-patient-id="{{ $currentPatient['nr_atendimento'] ?? '' }}"
                    data-shift="{{ $currentShift ?? '' }}"
                >
                    {{-- Modal Header --}}
                    <x-patient-modal.header 
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails" 
                    />

                    {{-- Tabs --}}
                    <x-patient-modal.tabs x-on:tab-change="tabChanged($event.detail)" />

                    {{-- Modal Content --}}
                    <div class="flex-1 min-h-0 bg-gray-50 relative">
                        {{-- Situação Tab --}}
                        <div
                            x-show="activeTab === 'tab-s'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform translate-y-2"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform translate-y-2"
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
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform translate-y-2"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform translate-y-2"
                            class="h-full overflow-y-auto"
                        >
                            <x-patient-modal.content.sbar-background 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>

                        {{-- Avaliação Tab --}}
                        <div
                            x-show="activeTab === 'tab-a'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform translate-y-2"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform translate-y-2"
                            class="overflow-y-auto"
                        >
                            <x-patient-modal.content.sbar-avaliacao 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                                :currentShift="$currentShift"
                                :currentUser="$currentUser"
                                :viewingHistory="$viewingHistory"
                                :shiftMessages="$shiftMessages"
                                :newChatMessage="$newChatMessage"
                                :messageLoading="$messageLoading"
                                :selectedHistoryDate="$selectedHistoryDate"
                                :selectedHistoryShift="$selectedHistoryShift"
                                :availableSessions="$availableSessions"
                                :selectedSession="$selectedSession"
                                :loadingMessages="$loadingMessages"
                            />
                        </div>

                        {{-- Recomendações Tab --}}
                        <div
                            x-show="activeTab === 'tab-r'"
                            x-cloak
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform translate-y-2"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform translate-y-2"
                            class="h-full overflow-y-auto"
                        >
                            <x-patient-modal.content.sbar-recomendacoes 
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails" 
                            />
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <x-patient-modal.footer />
                </div>
            </div>
        </div>
    </div>
    <script>
        // Unbind listeners when modal is hidden
        document.addEventListener('alpine:init', () => {
            Alpine.effect(() => {
                if (!Alpine.store('showModal')) {
                    if (window.unbindChatEchoListeners) {
                        window.unbindChatEchoListeners();
                    }
                }
            });
        });
    </script>
</div>
