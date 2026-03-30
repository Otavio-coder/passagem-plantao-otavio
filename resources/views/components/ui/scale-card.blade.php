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
])

<div class="{{ $styling['bg'] }} p-3 rounded-lg border {{ $styling['border'] }} relative transition-all hover:shadow-md">
    <label class="block text-xs font-bold text-gray-600 mb-1 flex items-center justify-between">
        <span>{{ $title }}</span>
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
            {{-- Score Atual --}}
            <div class="{{ $styling['text'] }}">
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold">{{ $score }}</span>
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
                        @if($increased)
                            <span class="text-[10px] text-red-600 font-bold">
                                ↑ {{ $score - $previousScore }}
                            </span>
                        @elseif($score < $previousScore)
                            <span class="text-[10px] text-green-600 font-bold">
                                ↓ {{ $previousScore - $score }}
                            </span>
                        @endif
                    </div>
                    @if($previousTimestamp)
                        <p class="text-[9px] text-gray-400 mt-0.5">{{ $previousTimestamp }}</p>
                    @endif
                </div>
            @endif
        </div>
    @else
        <p class="text-sm font-medium text-gray-500 py-2">Não avaliado</p>
    @endif
</div>
