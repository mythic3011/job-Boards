@props([
    'label',
    'value',
    'iconColor' => 'text-indigo-600',
    'trend' => null,
])

<x-ui.card>
    <div class="flex items-center justify-between">
        <div class="flex-1">
            <p class="theme-text-muted text-sm font-medium">{{ $label }}</p>
            <p class="theme-text-strong text-3xl font-bold" aria-label="{{ $label }}: {{ $value }}">
                {{ $value }}
            </p>
            @if($trend)
                <div class="mt-1 text-xs {{ $trend['positive'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $trend['label'] }}
                </div>
            @endif
        </div>
        @isset($icon)
            <div class="{{ $iconColor }} shrink-0 ml-4" aria-hidden="true">
                {{ $icon }}
            </div>
        @endisset
    </div>
</x-ui.card>
