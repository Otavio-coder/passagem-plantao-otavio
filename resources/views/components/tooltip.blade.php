@blaze(fold: true)

@props([
    'bg' => 'bg-white',
    'text' => 'text-black',
    'maxWidth' => 'w-72',
    'small' => false
])
@php
    $smallStyle = $small ? 'font-size:11px;padding:6px 8px;' : '';
    $arrowColor = $bg === 'bg-white' ? 'border-b-white' : ($bg === 'bg-yellow-400' ? 'border-b-yellow-400' : ($bg === 'bg-red-500' ? 'border-b-red-500' : 'border-b-white'));
@endphp

<div {{ $attributes->merge(['class' => 'relative group']) }}>
    <div class="flex items-center">
        @isset($trigger)
            {{ $trigger }}
        @else
            <span class="sr-only">tooltip trigger</span>
        @endisset
    </div>

    <div class="tooltip-container absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-3 py-2 {{ $bg }} {{ $text }} text-xs rounded-lg shadow-lg {{ $maxWidth }} z-50"
         style="{{ $smallStyle }}">
        <div class="font-semibold mb-1 flex items-center gap-1">
            @isset($icon)
                <span class="flex-shrink-0">
                    {{ $icon }}
                </span>
            @endisset
            @isset($title)
                {{ $title }}
            @endisset
        </div>

        <div class="tooltip-body leading-relaxed {{ $small ? 'text-[11px]' : 'text-xs' }}">
            {{ $slot }}
        </div>

        <div class="tooltip-arrow absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent {{ $arrowColor }}"></div>
    </div>
</div>
