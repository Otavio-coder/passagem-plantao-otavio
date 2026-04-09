@props([
    'data' => null, {{-- array ou stdClass: $patient (card) ou $patientDetails (modal) --}}
])

@php
    $age = (int) data_get($data, 'age', 99);
    $isPediatric = $age < 18;
    $ewsPrefix = $isPediatric ? 'pews' : 'mews';
@endphp

{{-- Legenda de Risco --}}
<div class="mb-2 p-1.5 bg-gray-50 rounded-lg border">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
        <div class="flex items-center space-x-1.5">
            <div class="w-3 h-3 bg-red-100 border border-red-300 rounded"></div>
            <span class="text-gray-700 font-bold">Alto Risco</span>
        </div>
        <div class="flex items-center space-x-1.5">
            <div class="w-3 h-3 bg-yellow-100 border border-yellow-300 rounded"></div>
            <span class="text-gray-700 font-bold">Risco Moderado</span>
        </div>
        <div class="flex items-center space-x-1.5">
            <div class="w-3 h-3 bg-gray-50 border border-gray-300 rounded"></div>
            <span class="text-gray-700 font-bold">Baixo Risco</span>
        </div>
        <div class="flex items-center space-x-1.5">
            <div class="w-3 h-3 bg-green-50 border border-green-300 rounded"></div>
            <span class="text-gray-700 font-bold">Sem Risco</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
    {{-- MEWS ou PEWS --}}
    <x-ui.scale-card
        :title="$isPediatric ? 'PEWS' : 'MEWS'"
        :subtitle="$isPediatric ? 'Pediatric Early Warning Score' : 'Modified Early Warning Score'"
        :score="data_get($data, $ewsPrefix . '_score')"
        :previousScore="data_get($data, $ewsPrefix . '_previous_score')"
        :styling="data_get($data, $ewsPrefix . '_styling', ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'])"
        :increased="(bool) data_get($data, $ewsPrefix . '_increased', false)"
        :timestamp="data_get($data, $ewsPrefix . '_timestamp')"
        :previousTimestamp="data_get($data, $ewsPrefix . '_previous_timestamp')"
        :classification="data_get($data, $ewsPrefix . '_classification')"
    />

    {{-- BRADEN --}}
    <x-ui.scale-card
        title="Braden"
        subtitle="Escala de Braden"
        :score="data_get($data, 'braden_score')"
        :previousScore="data_get($data, 'braden_previous_score')"
        :styling="data_get($data, 'braden_styling', ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'])"
        :increased="(bool) data_get($data, 'braden_increased', false)"
        :timestamp="data_get($data, 'braden_timestamp')"
        :previousTimestamp="data_get($data, 'braden_previous_timestamp')"
        :classification="data_get($data, 'braden_classification')"
    />

    {{-- MORSE --}}
    <x-ui.scale-card
        title="Morse"
        subtitle="Risco de Queda"
        :score="data_get($data, 'morse_score')"
        :previousScore="data_get($data, 'morse_previous_score')"
        :styling="data_get($data, 'morse_styling', ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'])"
        :increased="(bool) data_get($data, 'morse_increased', false)"
        :timestamp="data_get($data, 'morse_timestamp')"
        :previousTimestamp="data_get($data, 'morse_previous_timestamp')"
        :classification="data_get($data, 'morse_classification')"
    />

    {{-- DOR --}}
    <x-ui.scale-card
        title="Dor"
        subtitle="Pain Scale"
        :score="data_get($data, 'pain_score')"
        :previousScore="data_get($data, 'pain_previous_score')"
        :styling="data_get($data, 'pain_styling', ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'])"
        :increased="(bool) data_get($data, 'pain_increased', false)"
        :timestamp="data_get($data, 'pain_timestamp')"
        :previousTimestamp="data_get($data, 'pain_previous_timestamp')"
        :classification="data_get($data, 'pain_classification')"
    />

    {{-- TEV --}}
    <x-ui.scale-card
        title="TEV"
        subtitle="Tromboembolismo Venoso"
        :score="data_get($data, 'vte_score')"
        :previousScore="data_get($data, 'vte_previous_score')"
        :styling="data_get($data, 'vte_styling', ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'])"
        :increased="(bool) data_get($data, 'vte_increased', false)"
        :timestamp="data_get($data, 'vte_timestamp')"
        :previousTimestamp="data_get($data, 'vte_previous_timestamp')"
        :classification="data_get($data, 'vte_classification')"
    />
</div>
