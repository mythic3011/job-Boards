@if ($paginator->hasPages())
    <nav
        role="navigation"
        aria-label="{{ __('Pagination Navigation') }}"
        class="theme-panel rounded-[1.75rem] border px-4 py-4"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <span class="theme-icon-tile-accent inline-flex h-11 w-11 items-center justify-center rounded-2xl border text-sm font-semibold">
                    {{ $paginator->currentPage() }}
                </span>

                <div class="min-w-0">
                    <p class="theme-text-strong text-sm font-semibold sm:text-base">
                        Page {{ number_format($paginator->currentPage()) }} of {{ number_format($paginator->lastPage()) }}
                    </p>
                    <p class="theme-text-muted text-xs sm:text-sm">
                        @if ($paginator->firstItem())
                            Showing {{ number_format($paginator->firstItem()) }} to {{ number_format($paginator->lastItem()) }}
                        @else
                            Showing {{ number_format($paginator->count()) }}
                        @endif
                        of {{ number_format($paginator->total()) }} results
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                @if ($paginator->onFirstPage())
                    <span
                        aria-disabled="true"
                        aria-label="{{ __('pagination.previous') }}"
                        class="theme-panel-subtle theme-text-muted inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium cursor-not-allowed"
                    >
                        {{ __('pagination.previous') }}
                    </span>
                @else
                    <a
                        href="{{ $paginator->previousPageUrl() }}"
                        rel="prev"
                        aria-label="{{ __('pagination.previous') }}"
                        class="theme-button theme-button-outline inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium"
                    >
                        {{ __('pagination.previous') }}
                    </a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span
                            aria-disabled="true"
                            class="theme-panel-subtle theme-text-muted inline-flex items-center rounded-full border px-3 py-2 text-sm font-medium"
                        >
                            {{ $element }}
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="theme-link-active-chip inline-flex items-center rounded-full px-3 py-2 text-sm font-semibold">
                                    {{ $page }}
                                </span>
                            @else
                                <a
                                    href="{{ $url }}"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    class="theme-button theme-button-outline theme-text-strong inline-flex items-center rounded-full border px-3 py-2 text-sm font-medium"
                                >
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a
                        href="{{ $paginator->nextPageUrl() }}"
                        rel="next"
                        aria-label="{{ __('pagination.next') }}"
                        class="theme-button theme-button-primary inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium"
                    >
                        {{ __('pagination.next') }}
                    </a>
                @else
                    <span
                        aria-disabled="true"
                        aria-label="{{ __('pagination.next') }}"
                        class="theme-panel-subtle theme-text-muted inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium cursor-not-allowed"
                    >
                        {{ __('pagination.next') }}
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
