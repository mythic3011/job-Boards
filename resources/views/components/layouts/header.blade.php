@props([
    'showNavigation' => true,
])

<header class="border-b bg-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="font-semibold tracking-tight">
                    {{ config('app.name', 'Jobs Board') }}
                </a>
                @if($showNavigation)
                    <x-layouts.navigation />
                @endif
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
