@props([
    'showNavigation' => true,
])

<header class="border-b bg-white" role="banner">
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
                    <!-- Profile Dropdown -->
                    <div class="relative" id="profile-dropdown">
                        <button class="hidden sm:flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 transition-colors">
                            <x-ui.avatar 
                                :src="auth()->user()->profile_image_path ? route('images.profile', ['path' => base64_encode(auth()->user()->profile_image_path)]) : null"
                                :name="auth()->user()->nickname"
                                size="sm"
                                class="border border-gray-200"
                            />
                            <span class="font-medium">{{ auth()->user()->nickname }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ auth()->user()->user_type }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 border border-gray-200 hidden">
                            <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Profile</a>
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Profile</a>
                            <a href="{{ route('profile.password') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Change Password</a>
                            <a href="{{ route('profile.two-factor') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Security Settings</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="POST" action="{{ route('logout') }}" class="block">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</button>
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
    
    @auth
    <script>
    // Wait for jQuery to be available before initializing dropdown
    function initProfileDropdown() {
        if (typeof $ === 'undefined' || !$.fn) {
            // jQuery not ready yet, try again in 50ms
            setTimeout(initProfileDropdown, 50);
            return;
        }
        
        // Initialize profile dropdown with jQuery - fixed double-click issue
        const $dropdown = $('#profile-dropdown');
        if ($dropdown.length) {
            const $button = $dropdown.find('button'), $menu = $dropdown.find('div'), $arrow = $button.find('svg');
            let isOpen = false;
            
            // Prevent double-click issues by tracking state properly
            $button.off('click dblclick').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                if (isOpen) {
                    // Close dropdown
                    isOpen = false;
                    $menu.fadeOut(150, function() { $(this).addClass('hidden'); });
                    $arrow.css('transform', 'rotate(0deg)');
                } else {
                    // Open dropdown
                    isOpen = true;
                    $menu.removeClass('hidden').hide().fadeIn(150);
                    $arrow.css('transform', 'rotate(180deg)');
                }
            }).on('dblclick', (e) => {
                // Prevent double-click from interfering
                e.preventDefault();
                e.stopPropagation();
            });
            
            $(document).off('click.profileDropdown').on('click.profileDropdown', (e) => {
                if (isOpen && !$dropdown.is(e.target) && !$dropdown.has(e.target).length) {
                    isOpen = false;
                    $menu.fadeOut(150, function() { $(this).addClass('hidden'); });
                    $arrow.css('transform', 'rotate(0deg)');
                }
            });
            
            $(document).off('keydown.profileDropdown').on('keydown.profileDropdown', (e) => {
                if (e.key === 'Escape' && isOpen) {
                    isOpen = false;
                    $menu.fadeOut(150, function() { $(this).addClass('hidden'); });
                    $arrow.css('transform', 'rotate(0deg)');
                    $button.focus();
                }
            });
            
            console.log('Profile dropdown initialized with double-click protection');
        }
    }
    
    // Start trying to initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileDropdown);
    } else {
        initProfileDropdown();
    }
    </script>
    @endauth
</header>
