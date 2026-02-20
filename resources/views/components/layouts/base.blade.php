@props([
    'title' => null,
    'showHeader' => true,
    'showNavigation' => true,
])

@php
    $showHeader = filter_var($showHeader, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $showNavigation = filter_var($showNavigation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Jobs Board') }}</title>
    
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 {{ $showHeader ? 'text-gray-900' : '' }}">
    @if($showHeader)
        <x-layouts.header :show-navigation="$showNavigation" />
    @endif

    <main class="{{ $showHeader ? 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8' : '' }}" role="main">
        @if($showHeader)
            <x-layouts.flash-messages />
        @endif

        {{ $slot }}
    </main>

    @livewireScripts
    @auth
        @livewire('components.maintenance-alert')
    @endauth
    <x-layouts.assets />
</body>
</html>
