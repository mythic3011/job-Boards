<nav class="hidden sm:flex items-center gap-4 text-sm">
    <a href="{{ route('home') }}" class="text-gray-700 hover:text-gray-900">Home</a>
    <a href="{{ route('jobs.index') }}" class="text-gray-700 hover:text-gray-900">Jobs</a>
    @auth
        <a href="{{ route('applications.index') }}" class="text-gray-700 hover:text-gray-900">Applications</a>
        @can('admin.users.view')
            <a href="{{ route('admin.dashboard') }}" class="text-gray-700 hover:text-gray-900">Admin</a>
        @endcan
        <a href="{{ route('profile.two-factor') }}" class="text-gray-700 hover:text-gray-900">2FA</a>
    @endauth
</nav>
