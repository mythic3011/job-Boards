@props([
    'for' => null,
    'required' => false,
    'class' => '',
])

<label 
    for="{{ $for }}" 
    {{ $attributes->merge(['class' => "block text-sm font-medium text-gray-700 {$class}"]) }}
>
    {{ $slot }}
    @if($required)
        <span class="text-red-500" aria-label="required">*</span>
    @endif
</label>
