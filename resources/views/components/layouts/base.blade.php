@props([
    'title' => null,
    'showHeader' => true,
    'showNavigation' => true,
])

@php
    // Convert string "false" to boolean false - Blade passes props as strings
    $showHeader = ($showHeader === 'false' || $showHeader === false || $showHeader === 0 || $showHeader === '0') ? false : true;
    $showNavigation = ($showNavigation === 'false' || $showNavigation === false || $showNavigation === 0 || $showNavigation === '0') ? false : true;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Jobs Board') }}</title>
    
    <x-layouts.assets />
    
    @livewireStyles
    @livewireScriptConfig
</head>
<body class="min-h-screen bg-gray-50 {{ $showHeader ? 'text-gray-900' : '' }}">
    @if($showHeader)
        <x-layouts.header :show-navigation="$showNavigation" />
    @endif

    <main class="{{ $showHeader ? 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8' : '' }}">
        @if($showHeader)
            <x-layouts.flash-messages />
        @endif

        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
