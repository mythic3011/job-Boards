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
                    <!-- Profile Dropdown (uses data-dropdown so resources/js/components/dropdown.js handles it) -->
                    <div class="relative z-10" id="profile-dropdown" data-dropdown>
                        <button type="button" class="hidden sm:flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 transition-colors" data-dropdown-button aria-expanded="false" aria-haspopup="true">
                            <x-ui.avatar 
                                :src="auth()->user()->profile_image_path ? route('images.profile', ['path' => base64_encode(auth()->user()->profile_image_path)]) : null"
                                :name="auth()->user()->nickname"
                                size="sm"
                                class="border border-gray-200"
                            />
                            <span class="font-medium">{{ auth()->user()->nickname }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ auth()->user()->user_type }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-[100] border border-gray-200 opacity-0 scale-95 pointer-events-none transition-all duration-100" data-dropdown-menu id="profile-dropdown-menu">
                            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Profile</a>
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Profile</a>
                            <a href="{{ route('profile.password') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Change Password</a>
                            <a href="{{ route('profile.two-factor') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Security Settings</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}" class="block" id="header-logout-form">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-inset">Sign Out</button>
                            </form>
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
