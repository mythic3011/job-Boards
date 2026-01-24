@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Installation' }} - {{ config('app.name', 'Jobs Board') }}</title>
    
    <x-layouts.assets />
    
    @livewireStyles
    @livewireScriptConfig
</head>
<body class="min-h-screen bg-gray-50">
    {{ $slot }}
    @livewireScripts
</body>
</html>
