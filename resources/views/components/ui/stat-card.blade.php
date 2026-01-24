@props([
    'label',
    'value',
    'iconColor' => 'text-indigo-600',
])

<x-ui.card>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-600">{{ $label }}</p>
            <p class="text-3xl font-bold text-gray-900">{{ $value }}</p>
        </div>
        @isset($icon)
            <div class="{{ $iconColor }}">
                {{ $icon }}
            </div>
        @endisset
    </div>
</x-ui.card>
