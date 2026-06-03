@props([
    'patient',
    'currentHospitalName' => '',
    'currentShiftName'    => '',
    'modalPatients'       => [],
    'sectorId'              => 0,
    'showHandover'          => false,
    'showAlerts'            => false,
    'showMews'              => false,
    'showAdminData'         => false,
    'showScales'            => false,
    'showMultidisciplinary' => false,
    'showPendingEvents'     => false,
])

<div class="relative patient-card w-full">
    <div
        class="card-inner patient-card-fixed flex flex-col rounded-xl shadow-lg overflow-hidden h-[400px] md:h-[520px] lg:h-[420px] xl:h-[430px] max-h-[400px] md:max-h-[520px] lg:max-h-[420px] xl:max-h-[430px]
        {{ ($patient['has_patient'] ?? false) ? ($patient['border_class'] ?? '') . ' ' . ($patient['text_color_class'] ?? '') : '' }}"
        style="{{ ($patient['gradient_style'] ?? '') }}"
    >
        @if(!($patient['has_patient'] ?? false))
            <x-patient-card.empty-bed :patient="$patient" />
        @else
            <div class="flex flex-col h-full overflow-hidden">
                {{-- Header Section --}}
                <div class="flex-shrink-0 p-3 md:p-4 lg:p-2.5 flex flex-col gap-2 md:gap-3 lg:gap-1.5">
                    {{-- Row 1: Bed + (optional) Alerts + (optional) MEWS --}}
                    <div class="flex justify-between items-center gap-2">
                        <x-patient-card.bed-header :patient="$patient" :show-handover="$showHandover" />

                        @if($showAlerts)
                            <x-patient-card.alerts-row :patient="$patient" />
                        @endif

                        @if($showMews)
                            <div class="flex-shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs sm:text-sm font-bold shadow-sm whitespace-nowrap relative border
                                    {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_styling.bg', 'bg-white/90') }}
                                    {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_styling.text', 'text-gray-700') }}
                                    {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_styling.border', 'border-gray-300') }}
                                    {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_needs_assessment', false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>{{ $patient['ews_display']['label'] ?? 'MEWS' }}:</strong>
                                    <span class="ml-1">{{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_score', '-') }}</span>
                                    @if(data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_shift'))
                                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_shift') }})</span>
                                    @endif
                                    @if(data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_increased', false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                            </div>
                        @endif
                    </div>

                    <x-patient-card.demographics :patient="$patient" :show-admin-data="$showAdminData" :show-scales="$showScales" />
                </div>

                @if($showMultidisciplinary)
                    <x-patient-card.multidisciplinary :patient="$patient" />
                @endif

                @if($showPendingEvents)
                    <x-patient-card.pending-events :patient="$patient" :sector-id="$sectorId" />
                @endif

                {{-- Details Button --}}
                <div class="mt-auto flex-shrink-0 p-1.5 border-t border-white/10 z-10">
                    <button
                        type="button"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-60 cursor-not-allowed hover:bg-white/20 hover:shadow-sm"
                        wire:target="changeHospital,changeSector,refreshData"
                        class="w-full bg-white/20 text-gray-700 px-3 py-2 md:py-3 lg:py-2 rounded-md flex items-center justify-center gap-2 shadow-sm transition-all duration-150 text-xs sm:text-sm md:text-base lg:text-sm font-medium backdrop-blur-[4px] cursor-pointer hover:bg-white/30 hover:shadow-md active:bg-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-white/20 disabled:hover:shadow-sm"
                        @click.prevent="$dispatch('openModal', { attendanceNumber: {{ (int) ($patient['nr_atendimento'] ?? 0) }}, sectorId: {{ (int) ($patient['cd_setor_atendimento'] ?? 0) }}, hospital: {{ \Illuminate\Support\Js::from($currentHospitalName) }}, patients: window.__sbarModalPatients ?? [] })"
                    >
                        <span>Detalhes</span>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    @keyframes modal-slide-in {
        from { opacity: 0; transform: translateY(-20px) scale(0.95); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .patient-card .card-inner.patient-card-fixed {
        height: 400px !important;
        max-height: 400px !important;
        display: flex;
        flex-direction: column;
    }

    @media (min-width: 768px) {
        .patient-card .card-inner.patient-card-fixed {
            height: 520px !important;
            max-height: 520px !important;
        }
    }

    @media (min-width: 1024px) {
        .patient-card .card-inner.patient-card-fixed {
            height: 420px !important;
            max-height: 420px !important;
        }
    }

    @media (min-width: 1280px) {
        .patient-card .card-inner.patient-card-fixed {
            height: 430px !important;
            max-height: 430px !important;
        }
    }

    .patient-card .card-inner > * { min-height: 0; }

    .patient-card .card-inner .flex-1,
    .patient-card .card-inner .flex-grow,
    .patient-card .card-inner .h-full { min-height: 0; }

    .patient-card .card-inner .custom-scrollbar-pending { overflow: auto; }

    .custom-scrollbar-pending::-webkit-scrollbar,
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }

    .custom-scrollbar-pending::-webkit-scrollbar-track,
    .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,.05); border-radius: 3px; }

    .custom-scrollbar-pending::-webkit-scrollbar-thumb,
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,.25); border-radius: 3px; }

    .custom-scrollbar-pending::-webkit-scrollbar-thumb:hover,
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,.45); }

    .custom-scrollbar-pending,
    .custom-scrollbar { scrollbar-width: thin; scrollbar-color: rgba(0,0,0,.25) rgba(0,0,0,.05); }

    [x-cloak] { display: none !important; }
</style>
