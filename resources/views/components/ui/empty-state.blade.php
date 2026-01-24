@props([
    'message' => 'No items found.',
    'icon' => null,
    'title' => null,
])

<div class="rounded-lg border bg-white p-8 text-center text-gray-600" role="status" aria-live="polite">
    @if($icon)
        <div class="mb-4 flex justify-center text-4xl text-gray-400" aria-hidden="true">
            {{ $icon }}
        </div>
    @endif
    @if($title)
        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ $title }}</h3>
    @endif
    <p>{{ $message }}</p>
    @if(isset($action))
        <div class="mt-4">
            {{ $action }}
        </div>
    @endif
</div>
