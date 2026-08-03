@props([
    'patient',
    'currentHospitalName' => '',
    'sectorId' => 0,
])

@php
    $isPatient = (bool) ($patient['has_patient'] ?? false);
    $styling = $patient['huddle_styling'] ?? [
        'gradient' => 'from-red-50 to-red-100',
        'border' => 'border-2 border-red-400',
        'badge' => 'bg-red-500 text-white',
        'text' => 'text-red-700',
    ];
    $redCount = (int) ($patient['huddle_red_count'] ?? 0);
    $greenCount = (int) ($patient['huddle_green_count'] ?? 0);
    $reasons = $patient['huddle_reasons'] ?? [];
    $edd = $patient['huddle_expected_discharge'] ?? null;
    $isRed = ($patient['huddle_color'] ?? 'red') === 'red';
@endphp

<div class="relative w-full">
    <div class="flex flex-col rounded-xl shadow-lg overflow-hidden h-[300px] bg-gradient-to-br {{ $styling['gradient'] }} {{ $styling['border'] }}">

        @if(! $isPatient)
            <x-huddle-card.empty-bed :patient="$patient" />
        @else
            <div class="flex flex-col h-full overflow-hidden">

                {{-- Cabeçalho: leito + badge Red/Green --}}
                <div class="flex-shrink-0 p-3 flex flex-col gap-2">
                    <div class="flex justify-between items-center gap-2">
                        <span class="inline-flex items-center bg-white/80 text-gray-800 text-sm md:text-base font-bold px-3 py-1.5 rounded-full shadow-sm">
                            Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                        </span>

                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide shadow-sm {{ $styling['badge'] }}">
                            <span class="w-2 h-2 rounded-full bg-white/80"></span>
                            {{ $patient['huddle_color_label'] ?? 'Red' }}
                        </span>
                    </div>

                    {{-- Identificação --}}
                    <div class="min-w-0">
                        <p class="font-bold text-gray-900 text-sm md:text-base truncate" title="{{ $patient['nm_pessoa_fisica'] ?? '' }}">
                            {{ $patient['nm_pessoa_fisica'] ?? 'Paciente' }}
                        </p>
                        <p class="text-xs text-gray-600 mt-0.5">
                            @if(isset($patient['age'])) {{ $patient['age'] }} anos @endif
                            @if(isset($patient['internment_days']) && $patient['internment_days'] >= 0)
                                <span class="mx-1">•</span> {{ $patient['internment_days'] }} dia(s) internado
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Corpo: previsão de alta + contadores + motivos --}}
                <div class="flex-1 overflow-y-auto px-3 pb-2 custom-scrollbar space-y-2">

                    {{-- Previsão de alta (EDD) --}}
                    <div class="flex items-center gap-2 text-xs bg-white/60 rounded-lg px-2.5 py-1.5">
                        <i class="fas fa-calendar-day text-gray-500"></i>
                        <span class="text-gray-600">Previsão de alta:</span>
                        <span class="font-semibold {{ $edd ? 'text-gray-900' : 'text-amber-600' }}">
                            {{ $edd ?? 'não definida' }}
                        </span>
                    </div>

                    {{-- Contadores da internação --}}
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-semibold text-red-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                            {{ $redCount }} red
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-semibold text-green-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            {{ $greenCount }} green
                        </span>
                        <span class="text-gray-400">na internação</span>
                    </div>

                    {{-- Motivos do dia vermelho --}}
                    @if($isRed && ! empty($reasons))
                        <div class="flex flex-wrap gap-1">
                            @foreach($reasons as $reason)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium {{ $reason['accent'] }}"
                                      title="{{ $reason['category'] }}">
                                    {{ $reason['label'] }}
                                </span>
                            @endforeach
                        </div>
                    @elseif($isRed)
                        <p class="text-[11px] text-gray-500 italic">Sem motivos registrados para o dia.</p>
                    @endif
                </div>

                {{-- Botão de detalhe --}}
                <div class="mt-auto flex-shrink-0 p-1.5 border-t border-white/30">
                    <button
                        type="button"
                        class="w-full bg-white/40 text-gray-800 px-3 py-2 rounded-md flex items-center justify-center gap-2 shadow-sm text-sm font-medium hover:bg-white/60 transition-colors"
                        @click.prevent="$dispatch('openModal', {
                            attendanceNumber: {{ (int) ($patient['nr_atendimento'] ?? 0) }},
                            hospital: {{ \Illuminate\Support\Js::from($currentHospitalName) }},
                            sectorId: {{ (int) $sectorId }},
                            sbarPatient: {{ \Illuminate\Support\Js::from($patient) }},
                            patients: window.__huddleModalPatients ?? []
                        })"
                    >
                        <i class="fas fa-notes-medical text-gray-500"></i>
                        <span>Detalhes</span>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
