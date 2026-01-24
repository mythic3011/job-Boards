@props([
    'name' => null,
    'message' => null,
])

@php
    $errorMessage = $message 
        ?? (isset($errors) && $name && $errors->has($name) ? $errors->first($name) : null)
        ?? null;
@endphp

@if($errorMessage)
    <p class="mt-1 text-sm text-red-600" role="alert" id="{{ $name ? "{$name}-error" : 'form-error' }}">
        {{ $errorMessage }}
    </p>
@endif
