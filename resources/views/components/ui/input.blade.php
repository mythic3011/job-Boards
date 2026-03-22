@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'autocomplete' => null,
    'required' => false,
    'placeholder' => null,
    'value' => null,
    'error' => null,
    'help' => null,
    'disabled' => false,
    'readonly' => false,
])

@php
    $inputId = $name ?? 'input-' . uniqid();
    $hasError = $error || (isset($errors) && $errors->has($name));
    $helpId = $help ? "{$inputId}-help" : null;
    $errorId = $hasError ? "{$inputId}-error" : null;
    $autocompleteHints = [
        'email' => 'email',
        'name' => 'name',
        'nickname' => 'name',
        'username' => 'username',
        'login_id' => 'username',
        'current_password' => 'current-password',
        'password_confirmation' => 'new-password',
    ];
    $resolvedAutocomplete = $autocomplete
        ?? ($name ? ($autocompleteHints[$name] ?? null) : null)
        ?? ($type === 'email' ? 'email' : null);
    
    $ariaDescribedBy = collect([$helpId, $errorId])->filter()->implode(' ');
    
    $inputClasses = 'block w-full rounded-lg border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6';
    
    if ($hasError) {
        $inputClasses .= ' ring-red-300 focus:ring-red-500';
    }
    
    if ($disabled) {
        $inputClasses .= ' bg-gray-50 text-gray-500 cursor-not-allowed';
    }
    
    if ($readonly) {
        $inputClasses .= ' bg-gray-50';
    }
@endphp

<div class="space-y-1">
    @if($label)
        <x-ui.form-label :for="$inputId" :required="$required">
            {{ $label }}
        </x-ui.form-label>
    @endif

    <div class="relative">
        <input
            type="{{ $type }}"
            id="{{ $inputId }}"
            name="{{ $name }}"
            value="{{ $value ?? old($name) }}"
            placeholder="{{ $placeholder }}"
            @if($resolvedAutocomplete) autocomplete="{{ $resolvedAutocomplete }}" @endif
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $readonly ? 'readonly' : '' }}
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if($ariaDescribedBy) aria-describedby="{{ $ariaDescribedBy }}" @endif
            {{ $attributes->merge(['class' => $inputClasses]) }}
        >
        
        @if($hasError)
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
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
