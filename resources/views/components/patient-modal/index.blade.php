@props([
    'showModal' => false,
    'currentPatient' => null,
    'patientDetails' => null,
    'loadingPatient' => false,
    'currentHospitalName' => null,
    'showAlertsModal' => false,
    'patientAlerts' => []
])

@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" style="overflow: hidden;">
        <!-- Disable body scroll when modal is open -->
        <style>
            body { overflow: hidden !important; }
        </style>
        
        <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
            <!-- Backdrop with blur -->
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"></div>
            
            <!-- Modal Content -->
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-[95vw] sm:max-w-4xl md:max-w-5xl lg:max-w-6xl xl:max-w-7xl mx-auto h-[95vh] sm:h-[90vh] transition-all flex flex-col overflow-hidden"
                 x-data="{ activeTab: 'tab-s', activeCpoeCategory: 'cpoe-exames' }">
                
                <!-- Modal Header -->
                <x-patient-modal.header 
                    :currentHospitalName="$currentHospitalName"
                    :currentPatient="$currentPatient"
                    :patientDetails="$patientDetails" />
                
                <!-- Tab Navigation -->
                <x-patient-modal.tabs />

                <!-- Modal Body - Scrollable Content -->
                <div class="flex-1 min-h-0 bg-gray-50 relative">
                <!-- Tab S: Situação -->
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
                    :patientDetails="$patientDetails" />
                </div>

                <!-- Tab B: Background -->
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
                    :patientDetails="$patientDetails" />
                </div>

                <!-- Tab A: Avaliação -->
                <div
                    x-show="activeTab === 'tab-a'"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 transform translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 transform translate-y-0"
                    x-transition:leave-end="opacity-0 transform translate-y-2"
                    class="h-full overflow-y-auto"
                >
                    <x-patient-modal.content.sbar-avaliacao 
                    :loadingPatient="$loadingPatient"
                    :currentPatient="$currentPatient"
                    :patientDetails="$patientDetails" />
                </div>

                <!-- Tab R: Recomendações (includes CPOE) -->
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
                    :patientDetails="$patientDetails" />
                </div>
                </div>
                
                <!-- Modal Footer -->
                <x-patient-modal.footer />
            </div>
        </div>
    </div>
@endif

<!-- Include Alerts Modal -->
<x-patient-modal.alerts-modal 
    :showAlertsModal="$showAlertsModal"
    :patientAlerts="$patientAlerts"
    :currentPatient="$currentPatient" />