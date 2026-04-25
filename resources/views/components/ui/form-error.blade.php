@props([
    'name' => null,
    'message' => null,
    'id' => null,
])

@php
    $errorMessage = $message 
        ?? (isset($errors) && $name && $errors->has($name) ? $errors->first($name) : null)
        ?? null;
    
    $errorId = $id ?? ($name ? "{$name}-error" : 'form-error');
@endphp

@if($errorMessage)
    <p class="theme-error-text mt-1 text-sm" role="alert" id="{{ $errorId }}">
        {{ $errorMessage }}
    </p>
@endif
