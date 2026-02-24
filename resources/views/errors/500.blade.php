<x-errors.page code="500" title="Server Error" message="Something went wrong on our end. We've been notified and are working to fix it.">
    <x-slot:actions>
        <x-ui.button href="{{ route('jobs.index') }}" variant="primary">Go to Jobs</x-ui.button>
        <x-ui.button href="javascript:history.back()" variant="outline">Go Back</x-ui.button>
    </x-slot:actions>
</x-errors.page>
