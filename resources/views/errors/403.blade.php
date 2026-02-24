<x-errors.page code="403" title="Access Forbidden" message="You don't have permission to access this resource.">
    <x-slot:actions>
        <x-ui.button href="{{ route('jobs.index') }}" variant="primary">Go to Jobs</x-ui.button>
        <x-ui.button href="javascript:history.back()" variant="outline">Go Back</x-ui.button>
    </x-slot:actions>
</x-errors.page>
