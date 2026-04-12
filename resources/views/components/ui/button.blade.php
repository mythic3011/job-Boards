@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $baseClasses = 'theme-button inline-flex items-center justify-center rounded-lg border font-medium disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer';
    
    $variantClasses = [
        'primary' => 'theme-button-primary',
        'secondary' => 'theme-button-secondary',
        'outline' => 'theme-button-outline',
        'warning' => 'theme-button-warning',
        'danger' => 'theme-button-danger',
    ];
    
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-sm',
    ];
    
    $classes = $baseClasses . ' ' . ($variantClasses[$variant] ?? $variantClasses['primary']) . ' ' . ($sizeClasses[$size] ?? $sizeClasses['md']);
@endphp

@if($href)
    <a 
        href="{{ $href }}" 
        {{ $attributes->merge(['class' => $classes]) }}
        @if($disabled) aria-disabled="true" tabindex="-1" @endif
    >
        {{ $slot }}
    </a>
@else
    <button 
        type="{{ $type }}" 
        {{ $attributes->merge(['class' => $classes]) }}
        @if($disabled) disabled @endif
    >
        {{ $slot }}
    </button>
@endif
