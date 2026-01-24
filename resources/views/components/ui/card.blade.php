@props([
    'padding' => 'p-6',
    'shadow' => 'shadow-sm',
    'hover' => false,
])

@php
    $baseClasses = 'rounded-lg border bg-white';
    $classes = "{$baseClasses} {$padding} {$shadow}";
    if ($hover) {
        $classes .= ' transition-shadow hover:shadow-md';
    }
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>
