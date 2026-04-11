@props([
    'showNavigation' => true,
])

<header class="relative z-50 border-b bg-white" role="banner">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between">
            <div class="flex items-center gap-6">
                <a
                    href="{{ route('home') }}"
                    class="font-semibold tracking-tight hover:text-indigo-600 transition-colors"
                    aria-label="{{ config('app.name', 'Jobs Board') }} - Home"
                >
                    {{ config('app.name', 'Jobs Board') }}
                </a>
                @if($showNavigation)
                    <x-layouts.navigation />
                @endif
            </div>
            <div class="flex items-center gap-3">
                @auth
                    @php
                        $twoFactorEnabled = auth()->user()?->two_factor_confirmed_at !== null;
                    @endphp
                    <!-- Profile Dropdown (uses data-dropdown so resources/js/components/dropdown.js handles it) -->
                    <div class="relative z-10" id="profile-dropdown" data-dropdown data-open="false">
                        <button type="button" class="hidden sm:flex items-center gap-2 rounded-full border border-transparent bg-white py-1 pl-1.5 pr-3 text-sm text-gray-700 transition-colors hover:border-gray-200 hover:bg-gray-50 hover:text-gray-900" data-dropdown-button aria-expanded="false" aria-haspopup="true">
                            <x-ui.avatar
                                :src="auth()->user()->profile_image_path ? app(\App\Services\ProfileImageService::class)->getImageUrl(auth()->user()->profile_image_path) : null"
                                :name="auth()->user()->nickname"
                                size="sm"
                                class="border border-gray-200"
                            />
                            <span class="font-medium">{{ auth()->user()->nickname }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ auth()->user()->getUserTypeLabel() }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <div class="absolute right-0 top-full z-[100] mt-3 w-72 rounded-2xl border border-gray-200/80 bg-white/95 p-2 shadow-xl shadow-slate-900/10 backdrop-blur-sm opacity-0 translate-y-1 scale-[0.98] pointer-events-none transition-all duration-150 ease-out" data-dropdown-menu data-dropdown-panel id="profile-dropdown-menu">
                            <div class="mb-2 rounded-xl bg-gray-50 px-3 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400">Signed in as</p>
                                <div class="mt-2 flex items-center gap-3">
                                    <x-ui.avatar
                                        :src="auth()->user()->profile_image_path ? app(\App\Services\ProfileImageService::class)->getImageUrl(auth()->user()->profile_image_path) : null"
                                        :name="auth()->user()->nickname"
                                        size="sm"
                                        class="border border-gray-200"
                                    />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ auth()->user()->nickname }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                            <a href="{{ route('profile.show') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6.75a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 19.5a7.5 7.5 0 0115 0" />
                                    </svg>
                                </span>
                                <span>View Profile</span>
                            </a>
                            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487a2.25 2.25 0 113.182 3.182L8.25 19.463 3.75 20.25l.787-4.5L16.862 4.487z" />
                                    </svg>
                                </span>
                                <span>Edit Profile</span>
                            </a>
                            @if($twoFactorEnabled)
                                <a href="{{ route('profile.password') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V7.875a4.125 4.125 0 10-8.25 0V10.5m-1.5 0h11.25A2.25 2.25 0 0120.25 12.75v6A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25v-6A2.25 2.25 0 016 10.5z" />
                                        </svg>
                                    </span>
                                    <span>Change Password</span>
                                    <span class="ml-auto rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-700">2FA</span>
                                </a>
                            @else
                                <span class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-400 cursor-not-allowed"
                                    aria-disabled="true"
                                    title="Enable two-factor authentication to change your password."
                                >
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-50 text-yellow-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V7.875a4.125 4.125 0 10-8.25 0V10.5m-1.5 0h11.25A2.25 2.25 0 0120.25 12.75v6A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25v-6A2.25 2.25 0 016 10.5z" />
                                        </svg>
                                    </span>
                                    <span>Change Password</span>
                                    <span class="ml-auto rounded-full bg-yellow-50 px-2 py-0.5 text-[11px] font-semibold text-yellow-700">Locked</span>
                                </span>
                            @endif
                            <a href="{{ route('profile.two-factor') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50 hover:text-gray-900" data-dropdown-item>
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </span>
                                <span>Security Settings</span>
                            </a>
                            <div class="my-2 border-t border-gray-100"></div>
                            <form method="POST" action="{{ route('logout') }}" class="block" id="header-logout-form">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm text-red-600 transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset" data-dropdown-item>
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-7.5a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 006 21h7.5a2.25 2.25 0 002.25-2.25V15m-6-3h11.25m0 0-3-3m3 3-3 3" />
                                        </svg>
                                    </span>
                                    <span>Sign Out</span>
                                </button>
                            </form>
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
