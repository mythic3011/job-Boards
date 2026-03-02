@props([
    'class' => '',
])

<p {{ $attributes->merge(['class' => 'text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3 ' . $class]) }}>
    {{ $slot }}
</p>
