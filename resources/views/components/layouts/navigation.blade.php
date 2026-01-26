@props([
    'activeRoute' => null,
])

@php
    $activeRoute = $activeRoute ?? (request()->route() ? request()->route()->getName() : null);
    
    $isActive = function($routeName) use ($activeRoute) {
        return $activeRoute === $routeName || ($activeRoute && str_starts_with($activeRoute, $routeName . '.'));
    };
    
    $linkClasses = function($routeName) use ($isActive) {
        $base = 'text-sm transition-colors';
        return $isActive($routeName)
            ? $base . ' text-indigo-600 font-medium'
            : $base . ' text-gray-700 hover:text-gray-900';
    };
@endphp

<nav class="hidden sm:flex items-center gap-4" role="navigation" aria-label="Main navigation">
    <a 
        href="{{ route('home') }}" 
        class="{{ $linkClasses('home') }}"
        aria-current="{{ $isActive('home') ? 'page' : null }}"
    >
        Home
    </a>
    <a 
        href="{{ route('jobs.index') }}" 
        class="{{ $linkClasses('jobs.index') }}"
        aria-current="{{ $isActive('jobs.index') || $isActive('jobs.show') || $isActive('jobs.create') ? 'page' : null }}"
    >
        Jobs
    </a>
    @auth
        <a 
            href="{{ route('applications.index') }}" 
            class="{{ $linkClasses('applications.index') }}"
            aria-current="{{ $isActive('applications.index') || $isActive('applications.create') ? 'page' : null }}"
        >
            Applications
        </a>
        @can('admin.users.view')
            <a 
                href="{{ route('admin.dashboard') }}" 
                class="{{ $linkClasses('admin.dashboard') }}"
                aria-current="{{ $isActive('admin.') ? 'page' : null }}"
            >
                Admin
            </a>
        @endcan
        <a 
            href="{{ route('profile.two-factor') }}" 
            class="{{ $linkClasses('profile.two-factor') }}"
            aria-current="{{ $isActive('profile.') ? 'page' : null }}"
        >
            2FA
        </a>
    @endauth
</nav>
