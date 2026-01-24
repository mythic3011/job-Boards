<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Jobs Board') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <header class="border-b bg-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center gap-6">
                    <a href="{{ route('jobs.index') }}" class="font-semibold tracking-tight">
                        {{ config('app.name', 'Jobs Board') }}
                    </a>
                    <nav class="hidden sm:flex items-center gap-4 text-sm">
                        <a href="{{ route('jobs.index') }}" class="text-gray-700 hover:text-gray-900">Jobs</a>
                        @auth
                            <a href="{{ route('applications.index') }}" class="text-gray-700 hover:text-gray-900">Applications</a>
                            <a href="{{ route('profile.two-factor') }}" class="text-gray-700 hover:text-gray-900">2FA</a>
                        @endauth
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    @auth
                        <div class="hidden sm:flex items-center gap-2 text-sm text-gray-700">
                            <span class="font-medium">{{ auth()->user()->nickname }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">
                                {{ auth()->user()->user_type }}
                            </span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="outline" size="sm">Logout</x-ui.button>
                        </form>
                    @else
                        <x-ui.button href="{{ route('login') }}" variant="outline" size="sm">Login</x-ui.button>
                        <x-ui.button href="{{ route('register') }}" variant="primary" size="sm">Register</x-ui.button>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        @if (session('status') || session('message'))
            <x-ui.alert type="success" class="mb-6">
                {{ session('status') ?? session('message') }}
            </x-ui.alert>
        @endif

        @if (session('error'))
            <x-ui.alert type="error" class="mb-6">
                {{ session('error') }}
            </x-ui.alert>
        @endif

        @if ($errors->any())
            <x-ui.alert type="error" class="mb-6">
                <div class="font-medium">Please fix the errors below.</div>
            </x-ui.alert>
        @endif

        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
