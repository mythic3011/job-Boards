@props([
    'code',
    'title',
    'message',
    'actions',
])

<x-layouts.base :title="$code . ' - ' . $title">
    <div class="flex min-h-[60vh] flex-col items-center justify-center text-center">
        <div class="max-w-md">
            <h1 class="text-6xl font-bold text-gray-900">{{ $code }}</h1>
            <h2 class="mt-4 text-2xl font-semibold text-gray-800">{{ $title }}</h2>
            <p class="mt-4 text-gray-600">{{ $message }}</p>
            @if(isset($extra))
                <div class="mt-2">{{ $extra }}</div>
            @endif
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                {{ $actions }}
            </div>
        </div>
    </div>
</x-layouts.base>
