@if ($paginator->hasPages())
    <nav
        role="navigation"
        aria-label="{{ __('Pagination Navigation') }}"
        class="theme-panel rounded-[1.75rem] border px-4 py-4"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <p class="theme-text-strong text-sm font-semibold">Browse the next page set</p>
                <p class="theme-text-muted text-xs sm:text-sm">
                    Use previous and next to move through this result set without leaving the current workspace context.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($paginator->onFirstPage())
                    <span class="theme-panel-subtle theme-text-muted inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium cursor-not-allowed">
                        {{ __('pagination.previous') }}
                    </span>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        rel="prev"
                        class="theme-button theme-button-outline inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium"
                    >
                        {{ __('pagination.previous') }}
                    </a>
                @endif

                @if ($paginator->hasMorePages())
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        rel="next"
                        class="theme-button theme-button-primary inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium"
                    >
                        {{ __('pagination.next') }}
                    </a>
                @else
                    <span class="theme-panel-subtle theme-text-muted inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium cursor-not-allowed">
                        {{ __('pagination.next') }}
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
