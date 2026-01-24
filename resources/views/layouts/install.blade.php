@php
    $title = ($title ?? 'Installation') . ' - ' . config('app.name', 'Jobs Board');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>

    <!-- Force HTTPS for assets in production -->
    @if(config('app.env') === 'production' && !request()->secure())
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif

    <x-layouts.assets />
    
    @livewireStyles
    @livewireScriptConfig
</head>
<body class="min-h-screen bg-gray-50" style="display: block; visibility: visible;">
    {{ $slot }}
    @livewireScripts
</body>
</html>
