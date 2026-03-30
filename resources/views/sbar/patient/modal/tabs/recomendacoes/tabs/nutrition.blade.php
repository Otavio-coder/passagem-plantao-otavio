<div x-show="activeRecomendacaoTab === 'tab-nut'" style="display:none;" class="pt-4">
    @php
    $shiftConfig = [
        'MORNING'   => ['label' => 'Manhã',  'icon' => 'fa-sun',       'colors' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'AFTERNOON' => ['label' => 'Tarde',  'icon' => 'fa-cloud-sun', 'colors' => 'bg-orange-50 text-orange-700 border-orange-200'],
        'NIGHT'     => ['label' => 'Noite',  'icon' => 'fa-moon',      'colors' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
    ];
    $nutTypeBadge = fn(string $t) => match($t) {
        'Fasting'  => ['bg-rose-50 text-rose-700 border-rose-200',   'fa-ban',           'Jejum'],
        'Enteral'  => ['bg-violet-50 text-violet-700 border-violet-200', 'fa-syringe',    'Enteral'],
        'Special'  => ['bg-teal-50 text-teal-700 border-teal-200',   'fa-star',          'Especial'],
        default    => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-utensils','Dieta'],
    };
    @endphp

    <div class="space-y-4">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Nutrição</span>
            <span class="text-[10px] text-gray-400">{{ $nutCount }} item(s)</span>
        </div>
        @foreach($shiftConfig as $shiftKey => $shift)
            @php $items = $nutShifts[$shiftKey] ?? []; @endphp
            @if(count($items) > 0)
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 text-[10px] font-bold px-2 py-1 rounded border {{ $shift['colors'] }}">
                        <i class="fa-solid {{ $shift['icon'] }}" style="font-size:9px;"></i>
                        {{ $shift['label'] }}
                    </span>
                    <span class="text-[10px] text-gray-400 font-medium">{{ count($items) }} item(s)</span>
                </div>
                <div class="space-y-2">
                    @foreach($items as $nut)
                    @php
                        [$nutBadgeClass, $nutBadgeIcon, $nutBadgeLabel] = $nutTypeBadge($nut['type'] ?? 'Diet');
                        $isFasting = ($nut['is_fasting'] ?? false);
                        $schedPills = array_filter(explode(' ', $nut['schedule'] ?? ''));
                    @endphp
                    <div class="bg-white rounded-lg border shadow-sm px-3 py-2.5
                                {{ $isFasting ? 'border-rose-200 bg-rose-50/30' : 'border-gray-200' }}">

                        {{-- Header row --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                @if($isFasting)
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-ban text-rose-500" style="font-size:13px;"></i>
                                        <p class="text-xs font-bold text-rose-700 leading-snug tracking-wide uppercase">
                                            Jejum
                                        </p>
                                    </div>
                                @else
                                    <p class="text-xs font-semibold text-gray-800 leading-snug">
                                        {{ $nut['name'] ?? 'Prescrição nutricional' }}
                                    </p>
                                @endif
                            </div>
                            <span class="flex-shrink-0 inline-flex items-center gap-1 text-[10px] font-bold
                                         px-2 py-0.5 rounded border {{ $nutBadgeClass }}">
                                <i class="fa-solid {{ $nutBadgeIcon }}" style="font-size:9px;"></i>
                                {{ $nutBadgeLabel }}
                            </span>
                        </div>

                        {{-- Volume + schedule pills --}}
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            @if(!empty($nut['volume']))
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-600
                                             bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-flask text-gray-400" style="font-size:9px;"></i>
                                    {{ $nut['volume'] }}
                                </span>
                            @endif
                            @foreach($schedPills as $pill)
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-mono font-semibold
                                             px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                    {{ $pill }}
                                </span>
                            @endforeach
                        </div>

                        {{-- Allergies/restrictions --}}
                        @if(!empty($nut['allergies']))
                            <div class="flex items-start gap-1.5 mt-1.5 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                <i class="fa-solid fa-triangle-exclamation text-amber-500 flex-shrink-0 mt-0.5" style="font-size:10px;"></i>
                                <p class="text-[10px] text-amber-800 leading-snug font-medium">{{ $nut['allergies'] }}</p>
                            </div>
                        @endif

                        {{-- Observation --}}
                        @if(!empty($nut['observation']))
                            <p class="text-[10px] text-gray-500 mt-1.5 pl-2 border-l-2 border-emerald-200 leading-snug">
                                {{ $nut['observation'] }}
                            </p>
                        @endif

                        {{-- Footer: prescriber + date range --}}
                        @if(!empty($nut['prescriber']) || !empty($nut['dt_start']))
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-2 pt-1.5 border-t border-gray-100">
                                @if(!empty($nut['prescriber']))
                                    <span class="text-[10px] text-gray-400">
                                        <i class="fa-solid fa-user-doctor mr-0.5"></i>{{ $nut['prescriber'] }}
                                    </span>
                                @endif
                                @if(!empty($nut['dt_start']))
                                    <span class="text-[10px] text-gray-400">
                                        <i class="fa-regular fa-calendar mr-0.5"></i>{{ $nut['dt_start'] }}
                                        @if(!empty($nut['dt_end'])) → {{ $nut['dt_end'] }} @endif
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        @endforeach
    </div>
</div>{{-- /tab-nut --}}
