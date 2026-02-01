@props([
    'type' => 'info',
    'dismissible' => false,
    'icon' => null,
])

@php
    $typeClasses = [
        'success' => 'border-green-200 bg-green-50 text-green-800',
        'error' => 'border-red-200 bg-red-50 text-red-800',
        'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800',
        'info' => 'border-blue-200 bg-blue-50 text-blue-800',
    ];
    
    $classes = 'rounded-md border px-4 py-3 text-sm ' . ($typeClasses[$type] ?? $typeClasses['info']);
    
    $ariaLive = in_array($type, ['success', 'error']) ? 'polite' : 'off';
    $autoDismiss = in_array($type, ['success', 'error']);
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-transition:leave.opacity.duration.300ms
    @if($autoDismiss)
        x-init="setTimeout(() => { show = false }, 3000)"
    @endif
    {{ $attributes->merge(['class' => $classes]) }}
    role="alert"
    aria-live="{{ $ariaLive }}"
    aria-atomic="true"
>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-2 flex-1">
            @if($icon)
                <div class="flex-shrink-0 mt-0.5">
                    {{ $icon }}
                </div>
            @endif
            <div class="flex-1">{{ $slot }}</div>
        </div>
        @if($dismissible)
            <button
                type="button"
                class="flex-shrink-0 text-current opacity-50 hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 rounded"
                @click="show = false"
                aria-label="Dismiss alert"
            >
                <span class="sr-only">Dismiss</span>
                <span aria-hidden="true">×</span>
            </button>
        @endif
    </div>
</div>
