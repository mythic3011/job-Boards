@props([
    'type' => 'info',
    'dismissible' => false,
])

@php
    $typeClasses = [
        'success' => 'border-green-200 bg-green-50 text-green-800',
        'error' => 'border-red-200 bg-red-50 text-red-800',
        'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800',
        'info' => 'border-blue-200 bg-blue-50 text-blue-800',
    ];
    
    $classes = 'rounded-md border px-4 py-3 text-sm ' . $typeClasses[$type];
@endphp

<div {{ $attributes->merge(['class' => $classes]) }} role="alert">
    <div class="flex items-center justify-between">
        <div>{{ $slot }}</div>
        @if($dismissible)
            <button type="button" class="ml-4 text-current opacity-50 hover:opacity-75" onclick="this.parentElement.parentElement.remove()">
                <span class="sr-only">Dismiss</span>
                ×
            </button>
        @endif
    </div>
</div>
