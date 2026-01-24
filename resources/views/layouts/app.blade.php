<x-layouts.base :title="$title ?? config('app.name', 'Jobs Board')" :show-header="true" :show-navigation="true">
    @yield('content')
</x-layouts.base>
