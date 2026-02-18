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
            href="{{ route('my.applications.index') }}" 
            class="{{ $linkClasses('my.applications.index') }}"
            aria-current="{{ $isActive('my.applications.index') || $isActive('applications.create') ? 'page' : null }}"
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
        @can('admin.system.view')
            <a
                href="{{ route('admin.audit-logs.index') }}"
                class="{{ $linkClasses('admin.audit-logs.index') }}"
                aria-current="{{ $isActive('admin.audit-logs') ? 'page' : null }}"
            >
                Audit Logs
            </a>
        @endcan
    @endauth
</nav>
