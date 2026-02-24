<x-errors.page code="503" title="Service Unavailable" message="We're temporarily down for maintenance. Please check back soon.">
    <x-slot:actions>
        <x-ui.button href="javascript:location.reload()" variant="primary">Refresh Page</x-ui.button>
        <x-ui.button href="{{ route('jobs.index') }}" variant="outline">Go to Jobs</x-ui.button>
    </x-slot:actions>
</x-errors.page>
