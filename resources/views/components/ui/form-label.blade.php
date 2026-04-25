@props([
    'for' => null,
    'required' => false,
    'class' => '',
])

<label 
    for="{{ $for }}" 
    {{ $attributes->merge(['class' => "theme-text-strong block text-sm font-medium {$class}"]) }}
>
    {{ $slot }}
    @if($required)
        <span class="theme-required-marker" aria-label="required">*</span>
    @endif
</label>
