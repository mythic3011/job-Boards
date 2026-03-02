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
            <div class="relative" id="admin-nav-dropdown" data-dropdown>
                <button type="button"
                    class="flex items-center gap-1 {{ $isActive('admin.') ? 'text-indigo-600 font-medium' : 'text-gray-700 hover:text-gray-900' }} text-sm transition-colors"
                    data-dropdown-button aria-expanded="false" aria-haspopup="true">
                    Admin
                    <svg class="h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-[100] border border-gray-200 opacity-0 scale-95 pointer-events-none transition-all duration-100" data-dropdown-menu>
                    <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Dashboard</a>
                    <div class="border-t border-gray-100 my-1"></div>
                    @can('admin.users.view')
                        <a href="{{ route('admin.users.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Users</a>
                    @endcan
                    @can('admin.jobs.view')
                        <a href="{{ route('admin.jobs.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Jobs</a>
                    @endcan
                    @can('admin.applications.view')
                        <a href="{{ route('admin.applications.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Applications</a>
                    @endcan
                    @can('admin.system.view')
                        <a href="{{ route('admin.audit-logs.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Audit Logs</a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <a href="{{ route('admin.settings.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                    @endcan
                </div>
            </div>
        @endcan
    @endauth
</nav>
