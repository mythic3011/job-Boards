@props([
    'message' => 'No items found.',
    'icon' => null,
])

<div class="rounded-lg border bg-white p-8 text-center text-gray-600">
    @if($icon)
        <div class="mb-4 text-4xl">{{ $icon }}</div>
    @endif
    <p>{{ $message }}</p>
    @if(isset($action))
        <div class="mt-4">
            {{ $action }}
        </div>
    @endif
</div>
