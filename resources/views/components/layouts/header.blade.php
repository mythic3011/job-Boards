@props([
    'showNavigation' => true,
])

<header class="theme-header-surface relative z-50 border-b" role="banner">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-6">
                <a
                    href="{{ route('home') }}"
                    class="theme-text-strong theme-link font-semibold tracking-tight transition-colors"
                    aria-label="{{ config('app.name', 'Jobs Board') }} - Home"
                >
                    {{ config('app.name', 'Jobs Board') }}
                </a>
                @if($showNavigation)
                    <x-layouts.navigation />
                @endif
            </div>
            <div class="flex items-center gap-3">
                <x-ui.theme-switcher />
                @auth
                    @php
                        $twoFactorEnabled = auth()->user()?->two_factor_confirmed_at !== null;
                        $dashboardLabel = auth()->user()->isAdmin()
                            ? 'Admin dashboard'
                            : (auth()->user()->isCompany() ? 'Hiring dashboard' : 'Career dashboard');
                    @endphp
                    <!-- Profile Dropdown (uses data-dropdown so resources/js/components/dropdown.js handles it) -->
                    <div class="relative z-10" id="profile-dropdown" data-dropdown data-open="false">
                        <button type="button" class="theme-dropdown-trigger hidden sm:flex items-center gap-2 rounded-full border border-transparent py-1 pl-1.5 pr-3 text-sm transition-colors" data-dropdown-button aria-expanded="false" aria-haspopup="true">
                            <x-ui.avatar
                                :src="auth()->user()->profile_image_path ? app(\App\Services\ProfileImageService::class)->getImageUrl(auth()->user()->profile_image_path) : null"
                                :name="auth()->user()->nickname"
                                size="sm"
                                class="border border-gray-200"
                            />
                            <span class="max-w-24 truncate font-medium lg:max-w-32">{{ auth()->user()->nickname }}</span>
                            <span class="theme-pill rounded-full px-2 py-0.5 text-xs">{{ auth()->user()->getUserTypeLabel() }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div class="theme-dropdown-panel absolute right-0 top-full z-[100] mt-3 w-[min(24rem,calc(100vw-1.5rem))] sm:w-[23rem] max-h-[min(75vh,32rem)] overflow-y-auto overscroll-contain rounded-[1.75rem] border p-3 opacity-0 translate-y-1 scale-[0.98] pointer-events-none transition-all duration-150 ease-out" data-dropdown-menu data-dropdown-panel data-profile-dropdown-panel id="profile-dropdown-menu">
                            <div class="theme-panel-subtle mb-3 rounded-2xl px-4 py-4">
                                <p class="theme-text-muted text-[11px] font-semibold uppercase tracking-[0.14em]">Signed in as</p>
                                <div class="mt-2 flex items-center gap-3">
                                    <x-ui.avatar
                                        :src="auth()->user()->profile_image_path ? app(\App\Services\ProfileImageService::class)->getImageUrl(auth()->user()->profile_image_path) : null"
                                        :name="auth()->user()->nickname"
                                        size="sm"
                                        class="border border-gray-200"
                                    />
                                    <div class="min-w-0">
                                        <p class="theme-text-strong truncate text-sm font-semibold">{{ auth()->user()->nickname }}</p>
                                        <p class="theme-text-muted truncate text-xs">{{ auth()->user()->email }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="theme-pill rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]">{{ auth()->user()->getUserTypeLabel() }}</span>
                                    <span class="theme-text-muted text-xs">{{ $dashboardLabel }}</span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <section>
                                    <p class="theme-text-muted px-1 text-[11px] font-semibold uppercase tracking-[0.14em]">Workspace</p>
                                    <div class="mt-2 space-y-1">
                                        <a href="{{ route('home') }}" class="theme-text-strong flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)]" data-dropdown-item>
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 12 12 4.5 20.25 12M5.25 10.636V19.5h13.5v-8.864" />
                                                </svg>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium">Open Dashboard</span>
                                                <span class="theme-text-muted block text-xs">{{ $dashboardLabel }}</span>
                                            </span>
                                        </a>
                                        <a href="{{ route('profile.show') }}" class="theme-text-strong flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)]" data-dropdown-item>
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 19.5a7.5 7.5 0 0115 0" />
                                                </svg>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium">View Profile</span>
                                                <span class="theme-text-muted block text-xs">Identity snapshot and workspace overview.</span>
                                            </span>
                                        </a>
                                        <a href="{{ route('profile.edit') }}" class="theme-text-strong flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)]" data-dropdown-item>
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487a2.25 2.25 0 113.182 3.182L8.25 19.463 3.75 20.25l.787-4.5L16.862 4.487z" />
                                                </svg>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium">Edit Profile</span>
                                                <span class="theme-text-muted block text-xs">Refresh display name, avatar, and profile details.</span>
                                            </span>
                                        </a>
                                    </div>
                                </section>

                                <section>
                                    <p class="theme-text-muted px-1 text-[11px] font-semibold uppercase tracking-[0.14em]">Security</p>
                                    <div class="mt-2 space-y-1">
                                        @if($twoFactorEnabled)
                                            <a href="{{ route('profile.password') }}" class="theme-text-strong flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)]" data-dropdown-item>
                                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V7.875a4.125 4.125 0 10-8.25 0V10.5m-1.5 0h11.25A2.25 2.25 0 0120.25 12.75v6A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25v-6A2.25 2.25 0 016 10.5z" />
                                                    </svg>
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-medium">Change Password</span>
                                                    <span class="theme-text-muted block text-xs">Protected password workflow is unlocked.</span>
                                                </span>
                                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-700">2FA</span>
                                            </a>
                                        @else
                                            <span class="theme-text-muted flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm cursor-not-allowed" aria-disabled="true" title="Enable two-factor authentication to change your password.">
                                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-yellow-50 text-yellow-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V7.875a4.125 4.125 0 10-8.25 0V10.5m-1.5 0h11.25A2.25 2.25 0 0120.25 12.75v6A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25v-6A2.25 2.25 0 016 10.5z" />
                                                    </svg>
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-medium">Change Password</span>
                                                    <span class="block text-xs">Enable two-factor first to unlock this action.</span>
                                                </span>
                                                <span class="rounded-full bg-yellow-50 px-2 py-0.5 text-[11px] font-semibold text-yellow-700">Locked</span>
                                            </span>
                                        @endif
                                        <a href="{{ route('profile.two-factor') }}" class="theme-text-strong flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)]" data-dropdown-item>
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gray-100 text-gray-600">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium">Security Settings</span>
                                                <span class="theme-text-muted block text-xs">Review authenticator, recovery, and session posture.</span>
                                            </span>
                                        </a>
                                    </div>
                                </section>

                                <section>
                                    <p class="theme-text-muted px-1 text-[11px] font-semibold uppercase tracking-[0.14em]">Session</p>
                                    <div class="mt-2 border-t" style="border-color: var(--app-panel-border);"></div>
                                    <form method="POST" action="{{ route('logout') }}" class="mt-2 block" id="header-logout-form">
                                        @csrf
                                        <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-3 py-2.5 text-left text-sm text-red-600 transition-colors hover:bg-red-50" data-dropdown-item>
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-500">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-7.5a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 006 21h7.5a2.25 2.25 0 002.25-2.25V15m-6-3h11.25m0 0-3-3m3 3-3 3" />
                                                </svg>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-medium">Sign Out</span>
                                                <span class="block text-xs text-red-400">End this session on the current device.</span>
                                            </span>
                                        </button>
                                    </form>
                                </section>
                            </div>
                        </div>
                    </div>
                @else
                    <x-ui.button href="{{ route('login') }}" variant="outline" size="sm">
                        Login
                    </x-ui.button>
                    <x-ui.button href="{{ route('register') }}" variant="primary" size="sm">
                        Register
                    </x-ui.button>
                @endauth
            </div>
        </div>
    </div>

</header>
