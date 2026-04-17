@props([
    'padding' => 'p-6',
    'shadow' => 'shadow-sm',
    'hover' => false,
    'tone' => 'default',
])

@php
    $toneClasses = [
        'default' => 'theme-panel rounded-2xl border',
        'subtle' => 'theme-panel-subtle rounded-2xl border',
    ];

    $baseClasses = $toneClasses[$tone] ?? $toneClasses['default'];
    $classes = "{$baseClasses} {$padding} {$shadow}";
    if ($hover) {
        $classes .= ' transition-shadow duration-200 hover:shadow-md';
    }
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} data-card-surface>
    {{ $slot }}
</div>
