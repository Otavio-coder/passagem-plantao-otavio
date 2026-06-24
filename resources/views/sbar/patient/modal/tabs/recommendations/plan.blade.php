{{--
    Plano Terapêutico — Sub-tabs + Grade MAR + seções Blade.
    Props: $plan, $scheduleDate, $medicationSchedule, $planDisplayData
--}}
<div wire:key="tp-{{ $scheduleDate }}"
    x-data="therapeuticPlan(
        @js($planDisplayData['alpine_payload']['meds'] ?? []),
        @js($planDisplayData['alpine_payload']['schedule'] ?? []),
        @js($planDisplayData['alpine_payload']['time_columns'] ?? []),
        @js($planDisplayData['alpine_payload']['current_hour'] ?? ''),
        @js($planDisplayData['alpine_payload']['procedures'] ?? []),
        @js($planDisplayData['alpine_payload']['recommendations'] ?? []),
        @js($planDisplayData['alpine_payload']['interventions'] ?? []),
        @js($planDisplayData['alpine_payload']['hemotherapy'] ?? []),
        @js($planDisplayData['alpine_payload']['surgery'] ?? []),
        @js($planDisplayData['alpine_payload']['nutrition'] ?? []),
        @js($planDisplayData['alpine_payload']['chemotherapy'] ?? []),
        @js($planDisplayData['alpine_payload']['gasotherapy'] ?? []),
        @js($planDisplayData['alpine_payload']['dialysis'] ?? [])
    )"
    x-init="if (({{ (int) ($planDisplayData['counts']['tab-med'] ?? 0) }}) === 0 && '{{ $planDisplayData['default_tab'] ?? 'tab-med' }}' !== 'tab-med') activeRecomendacaoTab = '{{ $planDisplayData['default_tab'] ?? 'tab-med' }}'">

{{-- ════════════════════════════════════════════════════════
     SUB-TAB NAVIGATION
════════════════════════════════════════════════════════ --}}
<div class="bg-white border-b border-gray-200 -mx-3 sm:-mx-4 lg:-mx-6 mb-0">
    <nav class="flex overflow-x-auto scrollbar-hide px-3 sm:px-4 lg:px-6 -mb-px">
        @foreach([
            ['tab-med',  'antimicrobiano.svg',        'Medicamentos',  'border-[#BDAD02] text-[#5C5300]',    'bg-[#BDAD02] text-[#5C5300]'],
            ['tab-exam', 'outpatient-department.svg', 'Exames',        'border-blue-500 text-blue-700',      'bg-blue-100 text-blue-700'],
            ['tab-proc', 'tac.svg',                   'Procedimentos', 'border-indigo-500 text-indigo-700',  'bg-indigo-100 text-indigo-700'],
            ['tab-surg', 'general-surgery.svg',       'Cirurgias',     'border-[#7712C7] text-[#7712C7]',    'bg-[#7712C7]/10 text-[#7712C7]'],
            ['tab-hemo', 'hemoterapia.svg',           'Hemoterapia',   'border-red-500 text-red-700',        'bg-red-100 text-red-700'],
            ['tab-chemo','quimioterapia.svg',         'Quimioterapia', 'border-[#0A4700] text-[#0A4700]',    'bg-[#0A4700]/10 text-[#0A4700]'],
            ['tab-nut',  'nutricao.svg',              'Nutrição',      'border-emerald-500 text-emerald-700','bg-emerald-100 text-emerald-700'],
            ['tab-rec',  'notes.svg',                 'Recomendações', 'border-slate-500 text-slate-700',    'bg-slate-100 text-slate-700'],
            ['tab-int',  'alert.svg',                 'Intervenções',  'border-amber-500 text-amber-700',    'bg-amber-100 text-amber-700'],
            ['tab-gas',  'lungs.svg',                 'Gasoterapia',   'border-cyan-500 text-cyan-700',      'bg-cyan-100 text-cyan-700'],
            ['tab-dial', 'blood-drop.svg',            'Diálise',       'border-rose-500 text-rose-700',      'bg-rose-100 text-rose-700'],
        ] as [$tabId, $icon, $label, $activeColor, $badgeColor])
            @if(($planDisplayData['counts'][$tabId] ?? 0) > 0)
                <button @click="activeRecomendacaoTab = '{{ $tabId }}'"
                        :class="activeRecomendacaoTab === '{{ $tabId }}'
                            ? '{{ $activeColor }}'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="group flex items-center gap-1.5 px-3 py-2.5 border-b-2 text-xs font-semibold whitespace-nowrap transition-colors flex-shrink-0">
                    <img src="{{ asset('images/icons/patient-card/' . $icon) }}"
                         alt=""
                         class="w-3.5 h-3.5 object-contain opacity-90">
                    <span>{{ $label }}</span>
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none transition-colors"
                        :class="activeRecomendacaoTab === '{{ $tabId }}'
                            ? '{{ $badgeColor }}'
                            : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200'">
                        {{ $planDisplayData['counts'][$tabId] ?? 0 }}
                    </span>
                </button>
            @else
                <span class="flex items-center justify-center px-2 py-2.5 border-b-2 border-transparent text-gray-300 flex-shrink-0"
                      title="{{ $label }} sem informações">
                    <img src="{{ asset('images/icons/patient-card/' . $icon) }}"
                         alt=""
                         class="w-3.5 h-3.5 object-contain opacity-40 grayscale">
                </span>
            @endif
        @endforeach
    </nav>
</div>

{{-- ════════════════════════════════════════════════════════
     TABS — cada sub-tab em arquivo dedicado
════════════════════════════════════════════════════════ --}}
@include('sbar.patient.modal.tabs.recommendations.tabs.medications')
@include('sbar.patient.modal.tabs.recommendations.tabs.exams')
@include('sbar.patient.modal.tabs.recommendations.tabs.procedures')
@include('sbar.patient.modal.tabs.recommendations.tabs.surgeries')
@include('sbar.patient.modal.tabs.recommendations.tabs.nutrition')
@include('sbar.patient.modal.tabs.recommendations.tabs.hemotherapy')
@include('sbar.patient.modal.tabs.recommendations.tabs.chemotherapy')
@include('sbar.patient.modal.tabs.recommendations.tabs.recommendations')
@include('sbar.patient.modal.tabs.recommendations.tabs.interventions')
@include('sbar.patient.modal.tabs.recommendations.tabs.gasotherapy')
@include('sbar.patient.modal.tabs.recommendations.tabs.dialysis')

</div>{{-- /x-data therapeuticPlan --}}
