@props([
    'activeRoute' => null,
])

@php
    $activeRoute = $activeRoute ?? (request()->route() ? request()->route()->getName() : null);
    $isAdminRoute = $activeRoute && str_starts_with($activeRoute, 'admin.');
    
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
            @if($isAdminRoute)
                <div class="ml-1 pl-4 border-l border-gray-200 flex items-center gap-2" aria-label="Admin navigation">
                    <a href="{{ route('admin.dashboard') }}"
                       class="px-2.5 py-1 rounded-md text-sm transition-colors {{ $isActive('admin.dashboard') ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100' }}"
                       aria-current="{{ $isActive('admin.dashboard') ? 'page' : null }}">
                        Dashboard
                    </a>
                    @can('admin.users.view')
                        <a href="{{ route('admin.users.index') }}"
                           class="px-2.5 py-1 rounded-md text-sm transition-colors {{ $isActive('admin.users.index') ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100' }}"
                           aria-current="{{ $isActive('admin.users.index') ? 'page' : null }}">
                            Users
                        </a>
                    @endcan
                    @can('admin.jobs.view')
                        <a href="{{ route('admin.jobs.index') }}"
                           class="px-2.5 py-1 rounded-md text-sm transition-colors {{ $isActive('admin.jobs.index') ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100' }}"
                           aria-current="{{ $isActive('admin.jobs.index') ? 'page' : null }}">
                            Jobs
                        </a>
                    @endcan
                    @can('admin.applications.view')
                        <a href="{{ route('admin.applications.index') }}"
                           class="px-2.5 py-1 rounded-md text-sm transition-colors {{ $isActive('admin.applications.index') ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100' }}"
                           aria-current="{{ $isActive('admin.applications.index') ? 'page' : null }}">
                            Applications
                        </a>
                    @endcan
                    @can('admin.system.view')
                        <a href="{{ route('admin.audit-logs.index') }}"
                           class="px-2.5 py-1 rounded-md text-sm transition-colors {{ $isActive('admin.audit-logs.index') ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100' }}"
                           aria-current="{{ $isActive('admin.audit-logs.index') ? 'page' : null }}">
                            Audit Logs
                        </a>
                    @endcan
                    @can('admin.settings.view')
                        <a href="{{ route('admin.settings.index') }}"
                           class="px-2.5 py-1 rounded-md text-sm transition-colors {{ $isActive('admin.settings.index') ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100' }}"
                           aria-current="{{ $isActive('admin.settings.index') ? 'page' : null }}">
                            Settings
                        </a>
                    @endcan
                </div>
            @else
                <div class="relative" id="admin-nav-dropdown" data-dropdown>
                    <button type="button"
                        class="flex items-center gap-1 {{ $isAdminRoute ? 'text-indigo-600 font-medium' : 'text-gray-700 hover:text-gray-900' }} text-sm transition-colors"
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
                        @endcan
                        @can('admin.settings.view')
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="{{ route('admin.settings.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                        @endcan
                    </div>
                </div>
            @endif
        @endcan
    @endauth
</nav>
