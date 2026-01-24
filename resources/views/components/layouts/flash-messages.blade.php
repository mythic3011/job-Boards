@if (session('status') || session('message'))
    <x-ui.alert type="success" class="mb-6">
        {{ session('status') ?? session('message') }}
    </x-ui.alert>
@endif

@if (session('error'))
    <x-ui.alert type="error" class="mb-6">
        {{ session('error') }}
    </x-ui.alert>
@endif

@if(isset($errors) && $errors->any())
    <x-ui.alert type="error" class="mb-6">
        <div class="font-medium">Please fix the errors below.</div>
        <ul class="mt-2 list-disc list-inside text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
