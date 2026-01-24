@props([
    'padding' => 'p-6',
    'shadow' => 'shadow-sm',
])

<div {{ $attributes->merge(['class' => "rounded-lg border bg-white {$padding} {$shadow}"]) }}>
    {{ $slot }}
</div>
