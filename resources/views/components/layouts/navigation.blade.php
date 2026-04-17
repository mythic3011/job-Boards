@props([
    'activeRoute' => null,
])

@php
    $activeRoute = $activeRoute ?? (request()->route() ? request()->route()->getName() : null);
    $isAdminRoute = $activeRoute && str_starts_with($activeRoute, 'admin.');
    $adminDestinations = auth()->check() && auth()->user()->isAdmin()
        ? app(\App\Services\AdminNavigationService::class)->destinationsFor(auth()->user())
        : [];
    
    $isActive = function($routeName) use ($activeRoute) {
        return $activeRoute === $routeName || ($activeRoute && str_starts_with($activeRoute, $routeName . '.'));
    };

    $adminSummary = match (true) {
        $isActive('admin.users.index') => 'Users',
        $isActive('admin.jobs.index') => 'Jobs',
        $isActive('admin.applications.index') => 'Applications',
        $isActive('admin.audit-logs.index') => 'Audit',
        $isActive('admin.settings.index') => 'Settings',
        $isActive('admin.dashboard') => 'Dashboard',
        default => $adminDestinations[0]['summary'] ?? 'Admin',
    };
    
    $linkClasses = function($routeName) use ($isActive) {
        $base = 'text-sm transition-colors';
        return $isActive($routeName)
            ? $base . ' theme-nav-link-active font-medium'
            : $base . ' theme-nav-link';
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
        @if($adminDestinations !== [])
            <div class="relative" id="admin-nav-dropdown" data-dropdown data-open="false">
                <button type="button"
                    class="theme-dropdown-trigger inline-flex items-center gap-2 rounded-full border px-2.5 py-1.5 text-sm font-medium transition-colors {{ $isAdminRoute ? 'theme-link-active-chip' : 'theme-nav-link border-transparent' }}"
                    data-dropdown-button aria-expanded="false" aria-haspopup="true">
                    <span class="theme-icon-tile inline-flex h-6 w-6 items-center justify-center rounded-full border">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 6.75h15m-15 5.25h10.5m-10.5 5.25h15" />
                        </svg>
                    </span>
                    <span class="theme-text-strong">Admin</span>
                    <span class="theme-pill hidden lg:inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" data-admin-nav-trigger-summary>
                        {{ $adminSummary }}
                    </span>
                    <svg class="h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div class="theme-dropdown-panel absolute left-0 top-full z-[100] mt-3 w-60 rounded-2xl border p-2 opacity-0 translate-y-1 scale-[0.98] pointer-events-none transition-all duration-150 ease-out" data-dropdown-menu data-dropdown-panel>
                    <div class="px-3 pb-2 pt-1">
                        <p class="theme-text-muted text-[11px] font-semibold uppercase tracking-[0.14em]">Admin tools</p>
                        <p class="theme-text-muted mt-1 text-sm">Jump into protected management views.</p>
                    </div>
                    <div class="space-y-1">
                        @foreach($adminDestinations as $destination)
                            @php($isSettingsLink = $destination['route_name'] === 'admin.settings.index')
                            @if($isSettingsLink)
                                <div class="my-2 border-t" style="border-color: var(--app-panel-border);"></div>
                            @endif
                            <a href="{{ $destination['href'] }}" class="theme-text-strong flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)]" data-dropdown-item>
                                <span class="{{ $destination['route_name'] === 'admin.dashboard' ? 'theme-icon-tile-accent' : 'theme-icon-tile' }} inline-flex h-8 w-8 items-center justify-center rounded-lg">
                                    @switch($destination['route_name'])
                                        @case('admin.dashboard')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 3h16.5v5.25H3.75zM3.75 11.25h7.5V21h-7.5zM13.5 11.25h6.75V21H13.5z" />
                                            </svg>
                                            @break
                                        @case('admin.users.index')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5V10.5L12 4 2 10.5V20h5m10 0v-5a5 5 0 10-10 0v5m10 0H7" />
                                            </svg>
                                            @break
                                        @case('admin.jobs.index')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            @break
                                        @case('admin.applications.index')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-8.25A2.25 2.25 0 0017.25 3.75H6.75A2.25 2.25 0 004.5 6v12A2.25 2.25 0 006.75 20.25h6.75" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 8.25h7.5M8.25 12h4.5M16.5 18.75l2.25 2.25L21 18.75" />
                                            </svg>
                                            @break
                                        @case('admin.audit-logs.index')
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m-7.5 4.5h9A2.25 2.25 0 0018.75 18V6A2.25 2.25 0 0016.5 3.75h-9A2.25 2.25 0 005.25 6v12A2.25 2.25 0 007.5 20.25z" />
                                            </svg>
                                            @break
                                        @default
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 6h3m-7.72 1.78l2.12-.61a1.5 1.5 0 001.02-.94l.78-2.07h4.6l.78 2.07a1.5 1.5 0 001.02.94l2.12.61 2.3 3.98-1.33 1.74a1.5 1.5 0 000 1.8l1.33 1.74-2.3 3.98-2.12.61a1.5 1.5 0 00-1.02.94l-.78 2.07h-4.6l-.78-2.07a1.5 1.5 0 00-1.02-.94l-2.12-.61-2.3-3.98 1.33-1.74a1.5 1.5 0 000-1.8L3.48 11.76l2.3-3.98z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15.75A3.75 3.75 0 1112 8.25a3.75 3.75 0 010 7.5z" />
                                            </svg>
                                    @endswitch
                                </span>
                                <span>{{ $destination['nav_label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endauth
</nav>
