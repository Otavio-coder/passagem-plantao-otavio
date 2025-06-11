@props([
    'showModal' => false,
    'currentPatient' => null,
    'patientDetails' => null,
    'loadingPatient' => false,
    'currentHospitalName' => null
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
                 x-data="{ activeTab: 'tab-1', activeCpoeCategory: 'cpoe-exames' }">
                
                <!-- Modal Header -->
                <x-patient-modal.header 
                    :currentHospitalName="$currentHospitalName"
                    :currentPatient="$currentPatient"
                    :patientDetails="$patientDetails" />
                
                <!-- Tab Navigation -->
                <x-patient-modal.tabs />

                <!-- Modal Body - Scrollable Content -->
                <div class="flex-1 overflow-y-auto bg-gray-50">
                    <!-- Tab 1: SBAR Geral -->
                    <x-patient-modal.content.sbar-geral 
                        :loadingPatient="$loadingPatient"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails" />
                    
                    <!-- Tab 2: CPOE -->
                    <x-patient-modal.content.cpoe 
                        :patientDetails="$patientDetails" />
                    
                    <!-- Tab 3: SBAR por Turno -->
                    <x-patient-modal.content.sbar-turnos />
                </div>
                
                <!-- Modal Footer -->
                <x-patient-modal.footer />
            </div>
        </div>
    </div>
@endif