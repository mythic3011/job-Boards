@props([
    'code',
    'title',
    'message',
    'actions',
])

<x-layouts.base :title="$code . ' - ' . $title">
    <div class="flex min-h-[70vh] flex-col items-center justify-center px-4 text-center" data-error-shell>
        <div class="theme-panel max-w-xl rounded-[2rem] border px-8 py-10 sm:px-10" data-error-surface>
            <div class="mx-auto mb-4 inline-flex rounded-full border px-4 py-1.5 text-xs font-semibold uppercase tracking-[0.18em]" style="border-color: var(--app-accent-soft-border); background: var(--app-accent-soft-bg); color: var(--app-accent-soft-fg);">
                Error State
            </div>
            <h1 class="theme-error-code text-6xl font-bold tracking-tight sm:text-7xl">{{ $code }}</h1>
            <h2 class="theme-text-strong mt-4 text-2xl font-semibold sm:text-3xl">{{ $title }}</h2>
            <p class="theme-text-muted mt-4 text-base leading-7 sm:text-lg">{{ $message }}</p>
            @if(isset($extra))
                <div class="theme-text-muted mt-3">{{ $extra }}</div>
            @endif
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                {{ $actions }}
            </div>
        </div>
    </div>
</x-layouts.base>
