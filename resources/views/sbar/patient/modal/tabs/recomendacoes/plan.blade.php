{{--
    Plano Terapêutico — Sub-tabs + Grade MAR + seções Blade.
    Props: $plan, $scheduleDate, $medicationSchedule
--}}
@php
    use Carbon\Carbon;

    $medCount  = $plan['medications']['count']   ?? 0;
    $nutCount  = $plan['nutrition']['count']      ?? 0;
    $ordCount  = $plan['orders']['count']         ?? 0;
    $intCount  = $plan['interventions']['count']  ?? 0;
    $allProcedureItems = collect($plan['procedures']['items'] ?? []);
    $examCount = $allProcedureItems->filter(function (array $item) {
        $eventType = strtolower((string) ($item['event_type'] ?? ''));
        $type = strtolower((string) ($item['type'] ?? ''));

        return $eventType === 'exame'
            || str_contains($type, 'exame')
            || str_contains($type, 'laborat');
    })->count();
    $procCount = $allProcedureItems->count() - $examCount;
    $hemoCount = $plan['hemotherapy']['count']    ?? 0;
    $surgCount = $plan['surgery']['count']        ?? 0;
    $chemoCount= $plan['chemotherapy']['count']   ?? 0;
    $gasCount  = $plan['gasotherapy']['count']    ?? 0;
    $dialCount = $plan['dialysis']['count']       ?? 0;

    $subtabs = [
        ['tab-med',  'antimicrobiano.svg',       'Medicamentos',   $medCount,  'border-[#BDAD02] text-[#5C5300]', 'bg-[#BDAD02] text-[#5C5300]'],
        ['tab-exam', 'outpatient-department.svg','Exames',         $examCount, 'border-blue-500 text-blue-700',   'bg-blue-100 text-blue-700'],
        ['tab-proc', 'tac.svg',                  'Procedimentos',  $procCount, 'border-indigo-500 text-indigo-700','bg-indigo-100 text-indigo-700'],
        ['tab-surg', 'general-surgery.svg',      'Cirurgias',      $surgCount, 'border-[#7712C7] text-[#7712C7]', 'bg-[#7712C7]/10 text-[#7712C7]'],
        ['tab-hemo', 'hemoterapia.svg',          'Hemoterapia',    $hemoCount, 'border-red-500 text-red-700',     'bg-red-100 text-red-700'],
        ['tab-chemo','quimioterapia.svg',        'Quimioterapia',  $chemoCount,'border-[#0A4700] text-[#0A4700]', 'bg-[#0A4700]/10 text-[#0A4700]'],
        ['tab-nut',  'nutricao.svg',             'Nutrição',       $nutCount,  'border-emerald-500 text-emerald-700','bg-emerald-100 text-emerald-700'],
        ['tab-rec',  'notes.svg',                'Recomendações',  $ordCount,  'border-slate-500 text-slate-700', 'bg-slate-100 text-slate-700'],
        ['tab-int',  'alert.svg',                'Intervenções',   $intCount,  'border-amber-500 text-amber-700', 'bg-amber-100 text-amber-700'],
        ['tab-gas',  'lungs.svg',                'Gasoterapia',    $gasCount,  'border-cyan-500 text-cyan-700',   'bg-cyan-100 text-cyan-700'],
        ['tab-dial', 'blood-drop.svg',           'Diálise',        $dialCount, 'border-rose-500 text-rose-700',   'bg-rose-100 text-rose-700'],
    ];

    $defaultTab = 'tab-med';
    foreach ($subtabs as [$tabId, $_icon, $_label, $count, $_activeColor, $_badgeColor]) {
        if ($count > 0) {
            $defaultTab = $tabId;
            break;
        }
    }

    $today     = now()->format('Y-m-d');
    $yesterday = now()->subDay()->format('Y-m-d');
    $tomorrow  = now()->addDay()->format('Y-m-d');

    $scheduleCarbon = Carbon::parse($scheduleDate);
    $dateLabel = $scheduleCarbon->format('d/m/Y');
    $dateBadge = match($scheduleDate) {
        $today     => 'Hoje',
        $yesterday => 'Ontem',
        $tomorrow  => 'Amanhã',
        default    => null,
    };

    // Always show all 24 hours
    $allHours = [];
    for ($h = 0; $h < 24; $h++) {
        $allHours[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
    }
    $timeColumns = $allHours;
    $currentHour = now()->format('H') . ':00';

    $flags = JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;

    // JSON for Alpine — all dynamic tabs handled client-side
    $medsJson        = json_encode($plan['medications']['items']  ?? [], $flags);
    $schedJson       = json_encode($medicationSchedule,                  $flags);
    $colsJson        = json_encode($timeColumns,                         $flags);
    $currentHourJson = json_encode($currentHour,                         $flags);
    $procsJson       = json_encode($plan['procedures']['items']    ?? [], $flags);
    $ordsJson        = json_encode($plan['orders']['items']        ?? [], $flags);
    $intsJson        = json_encode($plan['interventions']['items'] ?? [], $flags);
    $hemoJson        = json_encode($plan['hemotherapy']['items']   ?? [], $flags);
    $surgJson        = json_encode($plan['surgery']['items']       ?? [], $flags);
    $chemoJson       = json_encode($plan['chemotherapy']['items']  ?? [], $flags);
    $gasJson         = json_encode($plan['gasotherapy']['items']   ?? [], $flags);
    $dialJson        = json_encode($plan['dialysis']['items']      ?? [], $flags);
    $nutJson         = json_encode($plan['nutrition']['items']     ?? [], $flags);
@endphp

<div wire:key="tp-{{ $scheduleDate }}"
    x-data="therapeuticPlan({{ $medsJson }}, {{ $schedJson }}, {{ $colsJson }}, {{ $currentHourJson }}, {{ $procsJson }}, {{ $ordsJson }}, {{ $intsJson }}, {{ $hemoJson }}, {{ $surgJson }}, {{ $nutJson }}, {{ $chemoJson }}, {{ $gasJson }}, {{ $dialJson }})"
    x-init="@if($medCount === 0 && $defaultTab !== 'tab-med') activeRecomendacaoTab = '{{ $defaultTab }}' @endif">

{{-- ════════════════════════════════════════════════════════
     SUB-TAB NAVIGATION
════════════════════════════════════════════════════════ --}}
<div class="bg-white border-b border-gray-200 -mx-3 sm:-mx-4 lg:-mx-6 mb-0">
    <nav class="flex overflow-x-auto scrollbar-hide px-3 sm:px-4 lg:px-6 -mb-px">
        @foreach($subtabs as [$tabId, $icon, $label, $count, $activeColor, $badgeColor])
        @if($count > 0)
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
                    {{ $count }}
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
@include('sbar.patient.modal.tabs.recomendacoes.tabs.medications')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.exams')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.procedures')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.surgeries')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.nutrition')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.hemotherapy')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.chemotherapy')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.orders')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.interventions')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.gasotherapy')
@include('sbar.patient.modal.tabs.recomendacoes.tabs.dialysis')

</div>{{-- /x-data therapeuticPlan --}}
