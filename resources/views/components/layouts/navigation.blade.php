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
                <div class="relative" id="admin-nav-dropdown" data-dropdown data-open="false">
                    <button type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-transparent px-2.5 py-1.5 text-sm font-medium transition-colors {{ $isAdminRoute ? 'text-indigo-600' : 'text-gray-700 hover:border-gray-200 hover:bg-gray-50 hover:text-gray-900' }}"
                        data-dropdown-button aria-expanded="false" aria-haspopup="true">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-gray-500">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 6.75h15m-15 5.25h10.5m-10.5 5.25h15" />
                            </svg>
                        </span>
                        Admin
                        <svg class="h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="absolute left-0 top-full z-[100] mt-3 w-60 rounded-2xl border border-gray-200/80 bg-white/95 p-2 shadow-xl shadow-slate-900/10 backdrop-blur-sm opacity-0 translate-y-1 scale-[0.98] pointer-events-none transition-all duration-150 ease-out" data-dropdown-menu data-dropdown-panel>
                        <div class="px-3 pb-2 pt-1">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400">Admin tools</p>
                            <p class="mt-1 text-sm text-gray-500">Jump into protected management views.</p>
                        </div>
                        <div class="space-y-1">
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 3h16.5v5.25H3.75zM3.75 11.25h7.5V21h-7.5zM13.5 11.25h6.75V21H13.5z" />
                                    </svg>
                                </span>
                                <span>Dashboard</span>
                            </a>
                        @can('admin.users.view')
                            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V10.5L12 4 2 10.5V20h5m10 0v-5a5 5 0 10-10 0v5m10 0H7" />
                                    </svg>
                                </span>
                                <span>Users</span>
                            </a>
                        @endcan
                        @can('admin.jobs.view')
                            <a href="{{ route('admin.jobs.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                                <span>Jobs</span>
                            </a>
                        @endcan
                        @can('admin.applications.view')
                            <a href="{{ route('admin.applications.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-8.25A2.25 2.25 0 0017.25 3.75H6.75A2.25 2.25 0 004.5 6v12A2.25 2.25 0 006.75 20.25h6.75" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 8.25h7.5M8.25 12h4.5M16.5 18.75l2.25 2.25L21 18.75" />
                                    </svg>
                                </span>
                                <span>Applications</span>
                            </a>
                        @endcan
                        @can('admin.system.view')
                            <a href="{{ route('admin.audit-logs.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m-7.5 4.5h9A2.25 2.25 0 0018.75 18V6A2.25 2.25 0 0016.5 3.75h-9A2.25 2.25 0 005.25 6v12A2.25 2.25 0 007.5 20.25z" />
                                    </svg>
                                </span>
                                <span>Audit Logs</span>
                            </a>
                        @endcan
                        @can('admin.settings.view')
                            <div class="my-2 border-t border-gray-100"></div>
                            <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h3m-7.72 1.78l2.12-.61a1.5 1.5 0 001.02-.94l.78-2.07h4.6l.78 2.07a1.5 1.5 0 001.02.94l2.12.61 2.3 3.98-1.33 1.74a1.5 1.5 0 000 1.8l1.33 1.74-2.3 3.98-2.12.61a1.5 1.5 0 00-1.02.94l-.78 2.07h-4.6l-.78-2.07a1.5 1.5 0 00-1.02-.94l-2.12-.61-2.3-3.98 1.33-1.74a1.5 1.5 0 000-1.8L3.48 11.76l2.3-3.98z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15.75A3.75 3.75 0 1112 8.25a3.75 3.75 0 010 7.5z" />
                                    </svg>
                                </span>
                                <span>Settings</span>
                            </a>
                        @endcan
                        </div>
                    </div>
                </div>
            @endif
        @endcan
    @endauth
</nav>
