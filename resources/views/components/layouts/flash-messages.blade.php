@if (session('status') || session('message') || session('success'))
    <x-ui.alert type="success" class="mb-6" dismissible>
        {{ session('status') ?? session('message') ?? session('success') }}
    </x-ui.alert>
@endif

@if (session('error'))
    <x-ui.alert type="error" class="mb-6" dismissible>
        {{ session('error') }}
    </x-ui.alert>
@endif

@if(isset($errors) && $errors->any())
    <x-ui.alert type="error" class="mb-6">
        <div class="font-medium">Please fix the errors below.</div>
        <ul class="mt-2 list-disc list-inside text-sm space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
