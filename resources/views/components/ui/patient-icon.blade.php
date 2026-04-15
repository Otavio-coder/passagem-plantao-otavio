@props([
    'name' => 'default',
    'variant' => 'o',
])

@php
    $rawName = is_string($name) ? $name : 'default';
    $rawName = strtolower(trim(pathinfo($rawName, PATHINFO_FILENAME)));

    $map = [
        'patient-isolated' => 'contact-support',
        'general-surgery' => 'general-surgery',
        'alta' => 'discharge',
        'fisioterapia' => 'physical-therapy',
        'psicologia' => 'psychology',
        'nutricao' => 'nutrition',
        'fonoaudiologia' => 'speech-language-therapy',
        'servico-social' => 'social-work',
        'catheter-svgrepo-com' => 'vascular-surgery',
        'notes' => 'medical-records',
        'blood-drop' => 'blood-drop',
        'quimioterapia' => 'medical-sample',
        'hemoterapia' => 'blood-drop',
        'lungs' => 'lungs',
        'alert' => 'alert',
        'alert-circle' => 'alert-circle',
        'antimicrobiano' => 'bacteria',
        'outpatient-department' => 'outpatient-department',
        'tac' => 'tac',
    ];

    $icon = $map[$rawName] ?? 'default';
    $resolvedVariant = in_array($variant, ['f', 'o'], true) ? $variant : 'o';
    $component = 'healthicons-'.$resolvedVariant.'-'.$icon;
@endphp

<x-dynamic-component :component="$component" {{ $attributes }} />
