@props([
    'class' => '',
    'id' => null,
])

@if($slot->isNotEmpty())
    <p {{ $attributes->merge(['class' => "text-sm text-gray-500 {$class}", 'id' => $id]) }}>
        {{ $slot }}
    </p>
@endif
