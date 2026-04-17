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
        <span class="text-red-500" aria-label="required">*</span>
    @endif
</label>
