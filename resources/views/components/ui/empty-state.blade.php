@props([
    'message' => 'No items found.',
    'icon' => null,
    'title' => null,
])

<x-ui.card tone="subtle" padding="p-8 sm:p-10" class="text-center" data-empty-state role="status" aria-live="polite">
    <div class="max-w-2xl mx-auto">
        @if($icon)
            <div class="mb-5 flex justify-center" aria-hidden="true">
                <div class="theme-empty-icon rounded-full p-4">
                    <div class="text-4xl theme-text-muted">
                        {{ $icon }}
                    </div>
                </div>
            </div>
        @endif

        @if($title)
            <h3 class="theme-text-strong mb-3 text-xl font-semibold tracking-tight">{{ $title }}</h3>
        @endif

        <p class="theme-text-muted text-base leading-7">{{ $message }}</p>

        @if(isset($action))
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                {{ $action }}
            </div>
        @endif
    </div>
</x-ui.card>
