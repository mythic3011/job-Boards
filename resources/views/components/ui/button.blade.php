@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
    
    $variantClasses = [
        'primary' => 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500 disabled:hover:bg-indigo-600',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500 disabled:hover:bg-gray-600',
        'outline' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-indigo-500 disabled:hover:bg-white',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 disabled:hover:bg-red-600',
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
