@props([
    'title' => '',
    'subtitle' => '',
    'score' => null,
    'previousScore' => null,
    'styling' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'],
    'increased' => false,
    'timestamp' => null,
    'previousTimestamp' => null,
    'classification' => null,
    'needsAssessment' => false,
    'details' => null,
])

@php
    $delta = ($score !== null && $previousScore !== null) ? ($score - $previousScore) : null;
@endphp

<div class="{{ $styling['bg'] }} p-3 rounded-lg border {{ $styling['border'] }} relative transition-all hover:shadow-md">

    {{-- Header: título + ponto piscante quando aumentou --}}
    <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center justify-between">
        <span class="{{ $needsAssessment ? 'underline decoration-red-500 decoration-2 underline-offset-2' : '' }}">{{ $title }}</span>
        @if($increased)
            <span class="flex items-center gap-1 text-red-600">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L6.707 7.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
                <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
            </span>
        @endif
    </label>

    <p class="text-[10px] text-gray-500 mb-2">{{ $subtitle }}</p>

    @if($score !== null)
        <div class="space-y-2">
            {{-- Score Atual — sublinhado vermelho quando precisa de nova aferição --}}
            <div class="{{ $styling['text'] }}">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span class="text-2xl font-bold
                        {{ $needsAssessment ? 'underline decoration-red-500 decoration-2 underline-offset-2' : '' }}">
                        {{ $score }}
                    </span>
                    {{-- Variação em relação ao anterior, exibida ao lado do score atual --}}
                    @if($delta !== null && $delta > 0)
                        <span class="text-[10px] text-red-600 font-bold">↑ {{ $delta }}</span>
                    @elseif($delta !== null && $delta < 0)
                        <span class="text-[10px] text-green-600 font-bold">↓ {{ abs($delta) }}</span>
                    @endif
                    @if($classification)
                        <span class="text-xs font-medium">{{ $classification }}</span>
                    @endif
                </div>
                @if($timestamp)
                    <p class="text-[10px] text-gray-500 mt-0.5">{{ $timestamp }}</p>
                @endif
            </div>

            {{-- Score Anterior --}}
            @if($previousScore !== null)
                <div class="pt-2 border-t border-gray-200">
                    <p class="text-[10px] text-gray-500 font-medium mb-0.5">Anterior:</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm font-semibold text-gray-600">{{ $previousScore }}</span>
                    </div>
                    @if($previousTimestamp)
                        <p class="text-[9px] text-gray-400 mt-0.5">{{ $previousTimestamp }}</p>
                    @endif
                </div>
            @endif

            {{-- Sub-scores detalhados (só disponível no modal) --}}
            @if($details && is_array($details))
                <div class="pt-1.5 border-t border-gray-200 space-y-1">
                    @php
                        $detailItems = [];

                        // MEWS
                        if (isset($details['pa']) && $details['pa'])           $detailItems[] = ['PA',          $details['pa']];
                        if (isset($details['fc']) && $details['fc'])           $detailItems[] = ['FC',          $details['fc']];
                        if (isset($details['fr']) && $details['fr'])           $detailItems[] = ['FR',          $details['fr']];
                        if (isset($details['temp']) && $details['temp'])       $detailItems[] = ['Temp',        $details['temp']];
                        if (!empty($details['consciencia']))                    $detailItems[] = ['Consciência', $details['consciencia']];

                        // PEWS
                        if (!empty($details['cardiovascular']))  $detailItems[] = ['Cardiovasc.', $details['cardiovascular']];
                        if (!empty($details['respiratorio']))    $detailItems[] = ['Resp.',        $details['respiratorio']];
                        if (!empty($details['vomito']))          $detailItems[] = ['Vômito',       $details['vomito']];
                        if (!empty($details['nebulizacao']))     $detailItems[] = ['Nebulização',  $details['nebulizacao']];

                        // Braden
                        if (isset($details['percepcao_sensorial']))    $detailItems[] = ['Percepção',  $details['percepcao_sensorial']];
                        if (isset($details['umidade']))                 $detailItems[] = ['Umidade',    $details['umidade']];
                        if (isset($details['atividade']))               $detailItems[] = ['Atividade',  $details['atividade']];
                        if (isset($details['mobilidade']))              $detailItems[] = ['Mobilidade', $details['mobilidade']];
                        if (isset($details['nutricao']))                $detailItems[] = ['Nutrição',   $details['nutricao']];
                        if (isset($details['friccao_cisalhamento']))    $detailItems[] = ['Fricção',    $details['friccao_cisalhamento']];

                        // Morse
                        if (isset($details['hist_queda']))      $detailItems[] = ['Hist. queda',  $details['hist_queda'] ? 'Sim' : 'Não'];
                        if (isset($details['diag_secundario'])) $detailItems[] = ['Diag. 2º',     $details['diag_secundario'] ? 'Sim' : 'Não'];
                        if (isset($details['ajuda_deambular'])) $detailItems[] = ['Deambulação',  $details['ajuda_deambular']];
                        if (isset($details['uso_heparina']))    $detailItems[] = ['Heparina IV',  $details['uso_heparina'] ? 'Sim' : 'Não'];
                        if (isset($details['modo_andar']))      $detailItems[] = ['Marcha',       $details['modo_andar']];
                        if (isset($details['estado_mental']))   $detailItems[] = ['Est. mental',  $details['estado_mental']];

                        // TEV
                        if (!empty($details['tipo']))    $detailItems[] = ['Tipo',   $details['tipo']];
                        if (!empty($details['fatores'])) $detailItems[] = ['Fatores', implode(', ', $details['fatores'])];
                    @endphp

                    @if(!empty($detailItems))
                        <div class="grid grid-cols-2 gap-x-2 gap-y-0.5">
                            @foreach($detailItems as [$label, $value])
                                <div class="flex items-start gap-1 text-[10px] leading-tight">
                                    <span class="text-gray-400 shrink-0">{{ $label }}:</span>
                                    <span class="text-gray-700 font-medium">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(!empty($details['recomendacao']))
                        <p class="text-[10px] text-gray-500 italic leading-tight mt-1 line-clamp-3">{{ $details['recomendacao'] }}</p>
                    @endif

                    @if(!empty($details['avaliador']))
                        <p class="text-[9px] text-gray-400 mt-0.5">Avaliador: {{ $details['avaliador'] }}</p>
                    @endif
                </div>
            @endif
        </div>
    @else
        <p class="text-sm font-medium text-gray-500 py-2">Não avaliado</p>
    @endif
</div>
