<x-errors.page code="429" title="Too Many Requests" message="You've made too many requests. Please wait a moment before trying again.">
    <x-slot:actions>
        <x-ui.button href="javascript:location.reload()" variant="primary">Try Again</x-ui.button>
        <x-ui.button href="{{ route('jobs.index') }}" variant="outline">Go to Jobs</x-ui.button>
    </x-slot:actions>
</x-errors.page>
