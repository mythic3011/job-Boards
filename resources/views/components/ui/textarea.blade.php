@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'placeholder' => null,
    'rows' => 4,
    'value' => null,
    'error' => null,
    'help' => null,
    'disabled' => false,
    'readonly' => false,
])

@php
    $inputId = $name ?? 'textarea-' . uniqid();
    $hasError = $error || (isset($errors) && $errors->has($name));
    $helpId = $help ? "{$inputId}-help" : null;
    $errorId = $hasError ? "{$inputId}-error" : null;
    
    $ariaDescribedBy = collect([$helpId, $errorId])->filter()->implode(' ');
    
    $textareaClasses = 'block w-full rounded-lg border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6';
    
    if ($hasError) {
        $textareaClasses .= ' ring-red-300 focus:ring-red-500';
    }
    
    if ($disabled) {
        $textareaClasses .= ' bg-gray-50 text-gray-500 cursor-not-allowed';
    }
    
    if ($readonly) {
        $textareaClasses .= ' bg-gray-50';
    }
@endphp

<div class="space-y-1">
    @if($label)
        <x-ui.form-label :for="$inputId" :required="$required">
            {{ $label }}
        </x-ui.form-label>
    @endif

    <div class="relative">
        <textarea
            id="{{ $inputId }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $readonly ? 'readonly' : '' }}
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
            {{ $attributes->merge(['class' => $textareaClasses]) }}
        >{{ $value ?? old($name) }}</textarea>
        
        @if($hasError)
            <div class="pointer-events-none absolute top-2 right-0 flex items-start pr-3">
                <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                </svg>
            </div>
        @endif
    </div>

    @if($help)
        <x-ui.form-help id="{{ $helpId }}">{{ $help }}</x-ui.form-help>
    @endif

    <x-ui.form-error :name="$name" :message="$error" :id="$errorId" />
</div>
