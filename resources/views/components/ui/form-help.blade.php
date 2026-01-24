@props([
    'class' => '',
])

@if($slot->isNotEmpty())
    <p {{ $attributes->merge(['class' => "text-sm text-gray-500 {$class}"]) }}>
        {{ $slot }}
    </p>
@endif
