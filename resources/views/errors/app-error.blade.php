<x-errors.page code="Error" title="Something went wrong" message="{{ session('error_message', 'An unexpected error occurred. Please try again.') }}">
    <x-slot:actions>
        <x-ui.button href="{{ route('jobs.index') }}" variant="primary">Go to Jobs</x-ui.button>
        <x-ui.button href="javascript:history.back()" variant="outline">Go Back</x-ui.button>
    </x-slot:actions>
</x-errors.page>
