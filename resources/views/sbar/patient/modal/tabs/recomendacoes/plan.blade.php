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
        ['tab-med',  'fa-pills',           'Medicamentos',   $medCount],
        ['tab-exam', 'fa-vial',            'Exames',         $examCount],
        ['tab-proc', 'fa-clipboard-list',  'Procedimentos',  $procCount],
        ['tab-surg', 'fa-user-doctor',     'Cirurgias',      $surgCount],
        ['tab-hemo', 'fa-droplet',         'Hemoterapia',    $hemoCount],
        ['tab-chemo','fa-flask-vial',      'Quimioterapia',  $chemoCount],
        ['tab-nut',  'fa-utensils',        'Nutrição',       $nutCount],
        ['tab-rec',  'fa-notes-medical',   'Recomendações',  $ordCount],
        ['tab-int',  'fa-heart-pulse',     'Intervenções',   $intCount],
        ['tab-gas',  'fa-lungs',           'Gasoterapia',    $gasCount],
        ['tab-dial', 'fa-circle-dot',      'Diálise',        $dialCount],
    ];

    $defaultTab = 'tab-med';
    foreach ($subtabs as [$tabId, $_icon, $_label, $count]) {
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
        @foreach($subtabs as [$tabId, $icon, $label, $count])
        @if($count > 0)
            <button @click="activeRecomendacaoTab = '{{ $tabId }}'"
                    :class="activeRecomendacaoTab === '{{ $tabId }}'
                        ? 'border-[#004D9D] text-[#004D9D]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="group flex items-center gap-1.5 px-3 py-2.5 border-b-2 text-xs font-semibold whitespace-nowrap transition-colors flex-shrink-0">
                <i class="fa-solid {{ $icon }}" style="font-size:11px;"></i>
                <span>{{ $label }}</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none transition-colors"
                    :class="activeRecomendacaoTab === '{{ $tabId }}'
                        ? 'bg-[#004D9D] text-white'
                        : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200'">
                    {{ $count }}
                </span>
            </button>
        @else
            <span class="flex items-center justify-center px-2 py-2.5 border-b-2 border-transparent text-gray-300 flex-shrink-0"
                  title="{{ $label }} sem informações">
                <i class="fa-solid {{ $icon }}" style="font-size:11px;"></i>
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
