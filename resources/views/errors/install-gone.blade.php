<x-errors.page code="404" title="Page Not Found" message="The page you're looking for doesn't exist or has been moved.">
    <x-slot:actions>
        <x-ui.button href="{{ route('jobs.index') }}" variant="primary">Go to Jobs</x-ui.button>
        <x-ui.button href="javascript:history.back()" variant="outline">Go Back</x-ui.button>
    </x-slot:actions>
</x-errors.page>

