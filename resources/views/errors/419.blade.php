<x-errors.page code="419" title="Page Expired" message="Your session has expired. Please refresh the page and try again.">
    <x-slot:actions>
        <x-ui.button href="javascript:location.reload()" variant="primary">Refresh Page</x-ui.button>
        <x-ui.button href="{{ route('jobs.index') }}" variant="outline">Go to Jobs</x-ui.button>
    </x-slot:actions>
</x-errors.page>
