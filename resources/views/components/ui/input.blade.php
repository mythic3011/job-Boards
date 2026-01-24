@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'required' => false,
    'placeholder' => null,
    'value' => null,
    'error' => null,
    'help' => null,
])

<div>
    @if($label)
        <x-ui.form-label :for="$name" :required="$required">
            {{ $label }}
        </x-ui.form-label>
    @endif

    @if($help)
        <x-ui.form-help class="mb-1">{{ $help }}</x-ui.form-help>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $name }}"
        name="{{ $name }}"
        value="{{ $value ?? old($name) }}"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        aria-invalid="{{ ($error || (isset($errors) && $errors->has($name))) ? 'true' : 'false' }}"
        aria-describedby="{{ $name ? ($help ? "{$name}-help" : '') . ($error || (isset($errors) && $errors->has($name)) ? " {$name}-error" : '') : '' }}"
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-500 disabled:cursor-not-allowed']) }}
    >

    @if($help)
        <p class="mt-1 text-sm text-gray-500" id="{{ $name }}-help">{{ $help }}</p>
    @endif

    <x-ui.form-error :name="$name" :message="$error" />
</div>
