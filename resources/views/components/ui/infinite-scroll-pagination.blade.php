@props([
    'paginator',
    'action' => 'loadMore',
    'label' => 'record',
    'keyName' => 'default',
])

@php
    $loadedCount = $paginator->count();
    $totalCount = $paginator->total();
    $hasMorePages = $paginator->hasMorePages();
    $pluralLabel = \Illuminate\Support\Str::plural($label, $totalCount);
    $remainingCount = max($totalCount - $loadedCount, 0);
    $progressPercentage = $totalCount > 0
        ? (int) min(100, round(($loadedCount / $totalCount) * 100))
        : 100;
    $progressWidth = $loadedCount > 0 ? max($progressPercentage, 8) : 0;
    $nextLoadCount = max(1, min((int) ($paginator->perPage() ?: $remainingCount ?: 1), max($remainingCount, 1)));
@endphp

<div
    wire:key="infinite-scroll-{{ $keyName }}-{{ $loadedCount }}-{{ $totalCount }}-{{ $hasMorePages ? 'more' : 'end' }}"
    data-infinite-pagination
    data-has-more="{{ $hasMorePages ? 'true' : 'false' }}"
    class="theme-table-divider border-t px-6 py-5"
>
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="min-w-0 flex-1 space-y-3" data-pagination-progress aria-live="polite">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 space-y-1">
                    <p class="theme-text-strong text-sm font-semibold sm:text-base">
                        Showing
                        <span class="font-bold">{{ number_format($loadedCount) }}</span>
                        of
                        <span class="font-bold">{{ number_format($totalCount) }}</span>
                        {{ $pluralLabel }}
                    </p>
                    <p class="theme-text-muted text-xs sm:text-sm">
                        New pages load into the current list automatically as you scroll, with manual loading kept available as a fallback.
                    </p>
                </div>

                <span class="theme-pill inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em]">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-[var(--app-accent)]"></span>
                    <span>{{ $progressPercentage }}% loaded</span>
                </span>
            </div>

            <div class="space-y-2">
                <div
                    data-pagination-progress-bar
                    class="theme-panel-subtle overflow-hidden rounded-full border p-1"
                    role="presentation"
                >
                    <div
                        class="h-2 rounded-full bg-[var(--app-accent)] transition-all duration-300"
                        style="width: {{ $progressWidth }}%;"
                    ></div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                    <span class="theme-text-muted" data-pagination-remaining>
                        @if($hasMorePages)
                            {{ number_format($remainingCount) }} more {{ $pluralLabel }} available before you reach the end of this list.
                        @else
                            You have reached the end of this list.
                        @endif
                    </span>

                    @if($hasMorePages)
                        <span class="theme-panel-subtle theme-text-strong inline-flex items-center gap-2 rounded-full border px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.18em]">
                            <span class="inline-flex h-2 w-2 rounded-full bg-[var(--app-accent)]"></span>
                            Auto-fetch near page end
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if($hasMorePages)
            <div class="theme-panel-subtle flex w-full flex-col gap-3 self-start rounded-[1.5rem] border px-4 py-3 sm:max-w-sm sm:self-auto">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="theme-text-strong text-sm font-semibold">Need the next batch now?</p>
                        <p class="theme-text-muted text-xs sm:text-sm">
                            Load the next {{ number_format($nextLoadCount) }} {{ \Illuminate\Support\Str::plural($label, $nextLoadCount) }} immediately without waiting for scroll.
                        </p>
                    </div>

                    <span class="theme-icon-tile-accent inline-flex h-10 w-10 items-center justify-center rounded-2xl border text-sm font-semibold">
                        {{ $nextLoadCount }}
                    </span>
                </div>

                <button
                    type="button"
                    wire:click="{{ $action }}"
                    wire:loading.attr="disabled"
                    wire:target="{{ $action }}"
                    data-infinite-pagination-button
                    class="theme-button theme-button-primary inline-flex items-center justify-center gap-2 rounded-full border px-4 py-2.5 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span aria-hidden="true">+</span>
                    <span wire:loading.remove wire:target="{{ $action }}">Load more</span>
                    <span wire:loading wire:target="{{ $action }}">Loading more…</span>
                </button>
            </div>
        @else
            <div class="theme-panel-subtle theme-text-strong inline-flex items-center gap-2 self-start rounded-full border px-3 py-1 text-sm font-medium sm:self-auto">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                <span>All caught up</span>
            </div>
        @endif
    </div>

    @if($hasMorePages)
        <div data-infinite-pagination-sentinel class="h-1 w-full" aria-hidden="true"></div>
    @endif

    <noscript>
        <div class="mt-4">
            {{ $paginator->links() }}
        </div>
    </noscript>
</div>
