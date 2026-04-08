@php
    $avatarPhoto = $photo ?? null;
    $avatarName = $name ?? 'U';
    $avatarFormat = $format ?? 'jpeg';
    $avatarInitials = $initials
        ?? collect(preg_split('/[\s.]+/', trim((string) $avatarName)) ?: ['U'])
            ->filter()
            ->pipe(function ($parts) {
                if ($parts->isEmpty()) {
                    return 'U';
                }

                $first = strtoupper(substr((string) $parts->first(), 0, 1));
                $last = $parts->count() > 1 ? strtoupper(substr((string) $parts->last(), 0, 1)) : '';

                return $first.$last;
            });
    $avatarBgColor = $bgColor ?? '#6B7280';
@endphp

@if($avatarPhoto)
    <img
        src="data:image/{{ $avatarFormat }};base64,{{ $avatarPhoto }}"
        alt="{{ $avatarName }}"
        {{ $attributes->merge(['class' => 'rounded-full object-cover flex-shrink-0']) }}
    />
@else
    <div
        {{ $attributes->merge(['class' => 'rounded-full flex items-center justify-center flex-shrink-0 select-none']) }}
        style="background-color: {{ $avatarBgColor }}; container-type: inline-size;"
    >
        <span class="font-bold text-white leading-none" style="font-size: 38cqi">{{ $avatarInitials }}</span>
    </div>
@endif
