@props([
    'class' => '',
    'id' => null,
])

@if($slot->isNotEmpty())
    <p {{ $attributes->merge(['class' => "theme-text-muted text-sm {$class}", 'id' => $id]) }}>
        {{ $slot }}
    </p>
@endif
