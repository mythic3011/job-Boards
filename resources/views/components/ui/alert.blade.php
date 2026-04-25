@props([
    'type' => 'info',
    'dismissible' => false,
    'icon' => null,
    'autoDismiss' => null,
    'autoDismissMs' => 3000,
])

@php
    $typeClasses = [
        'success' => 'theme-alert-success',
        'error' => 'theme-alert-error',
        'warning' => 'theme-alert-warning',
        'info' => 'theme-alert-info',
    ];
    
    $classes = 'theme-alert rounded-2xl border px-4 py-3.5 text-sm shadow-sm ' . ($typeClasses[$type] ?? $typeClasses['info']);
    
    $ariaLive = match ($type) {
        'error' => 'assertive',
        'success' => 'polite',
        default => 'off',
    };

    $shouldAutoDismiss = $autoDismiss ?? ($type === 'success');
@endphp

<div
    @if($shouldAutoDismiss)
        data-auto-dismiss-ms="{{ $autoDismissMs }}"
    @endif
    {{ $attributes->merge(['class' => $classes.' transition-opacity duration-300']) }}
    data-alert-surface
    role="alert"
    aria-live="{{ $ariaLive }}"
    aria-atomic="true"
>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3 flex-1">
            @if($icon)
                <div class="mt-0.5 shrink-0">
                    {{ $icon }}
                </div>
            @endif
            <div class="flex-1">{{ $slot }}</div>
        </div>
        @if($dismissible)
            <button
                type="button"
                class="shrink-0 rounded-full p-1 text-current opacity-50 transition-opacity hover:opacity-80 cursor-pointer"
                data-alert-dismiss
                aria-label="Dismiss alert"
            >
                <span class="sr-only">Dismiss</span>
                <span aria-hidden="true">×</span>
            </button>
        @endif
    </div>
</div>
