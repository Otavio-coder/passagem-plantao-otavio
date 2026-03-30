{{--
    Componente de avatar de usuário.
    Exibe a foto base64 se disponível, caso contrário exibe as iniciais com cor determinística.

    Props:
      - photo   : string|null  — base64 da foto (sem o prefixo data:URI)
      - name    : string       — nome do usuário (usado para iniciais e cor)
      - format  : string       — formato da imagem: 'jpeg' (padrão) ou 'png'

    Classes extras (size, border, mt-, etc.) devem ser passadas via class="..."
    e serão mescladas automaticamente via $attributes.

    Exemplo:
      <x-ui.user-avatar :photo="$user['photo']" :name="$user['name']" class="w-8 h-8" />
--}}
@props([
    'photo'  => null,
    'name'   => 'U',
    'format' => 'jpeg',
])

@php
    // Iniciais: primeira letra do primeiro e do último nome
    $words    = preg_split('/[\s.]+/', trim($name ?: 'U'));
    $initials = strtoupper(substr($words[0], 0, 1));
    if (count($words) > 1) {
        $initials .= strtoupper(substr(end($words), 0, 1));
    }

    // Cor determinística baseada no nome (consistente entre renders)
    $palette = ['4F46E5','0891B2','059669','B45309','7C3AED','0284C7','BE185D','0F766E'];
    $bg      = '#' . $palette[abs(crc32($name ?: 'U')) % count($palette)];
@endphp

@if($photo)
    <img
        src="data:image/{{ $format }};base64,{{ $photo }}"
        alt="{{ $name }}"
        {{ $attributes->merge(['class' => 'rounded-full object-cover flex-shrink-0']) }}
    />
@else
    <div
        {{ $attributes->merge(['class' => 'rounded-full flex items-center justify-center flex-shrink-0 select-none']) }}
        style="background-color: {{ $bg }}; container-type: inline-size;"
    >
        <span class="font-bold text-white leading-none" style="font-size: 38cqi">{{ $initials }}</span>
    </div>
@endif
