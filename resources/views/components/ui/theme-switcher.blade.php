@props([
    'placement' => 'header',
])

@php
    $isFloating = $placement === 'floating';
    $triggerClasses = $isFloating
        ? 'theme-switcher-trigger inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium shadow-sm backdrop-blur'
        : 'theme-switcher-trigger inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-medium';
    $panelClasses = 'theme-dropdown-panel absolute right-0 top-full z-[100] mt-3 w-[min(24rem,calc(100vw-1.5rem))] sm:w-[26rem] max-h-[min(75vh,34rem)] overflow-y-auto overscroll-contain origin-top-right rounded-[1.75rem] border p-4 opacity-0 translate-y-1 scale-[0.98] pointer-events-none transition-all duration-150 ease-out';
    $modeOptions = [
        'system' => ['label' => 'System', 'icon' => 'M12 18.75A6.75 6.75 0 1 0 12 5.25a6.75 6.75 0 0 0 0 13.5Zm0 0V21m0-18V3m8.25 9H21m-18 0h.75m14.364 6.364.53.53M5.356 5.356l.53.53m12.258-1.06-.53.53M5.886 18.114l-.53.53'],
        'light' => ['label' => 'Light', 'icon' => 'M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m14.864 6.364-1.06-1.06M7.196 7.196 6.136 6.136m11.728 0-1.06 1.06M7.196 16.804l-1.06 1.06M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z'],
        'dark' => ['label' => 'Dark', 'icon' => 'M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 1 0 21.752 15.002Z'],
    ];
    $accentOptions = [
        'indigo' => ['label' => 'Default', 'hint' => 'Balanced studio accent', 'swatch' => 'bg-indigo-500'],
        'graphite' => ['label' => 'Graphite', 'hint' => 'Neutral and restrained', 'swatch' => 'bg-slate-600'],
        'forest' => ['label' => 'Forest', 'hint' => 'Calm and operational', 'swatch' => 'bg-teal-600'],
    ];
@endphp

<div class="relative" data-dropdown data-open="false" data-theme-switcher>
    <button
        type="button"
        class="{{ $triggerClasses }}"
        data-dropdown-button
        aria-expanded="false"
        aria-haspopup="true"
        aria-label="Open appearance controls"
    >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.813 15.904 9 18.75l-2.846.813a1.125 1.125 0 0 0 0 2.174L9 22.55l.813 2.846a1.125 1.125 0 0 0 2.174 0L12.8 22.55l2.846-.813a1.125 1.125 0 0 0 0-2.174L12.8 18.75l-.813-2.846a1.125 1.125 0 0 0-2.174 0Z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.75 3v2.25M19.875 4.125h-2.25M4.5 9.75V12m1.125-1.125h-2.25" />
        </svg>
        <span class="sr-only">Appearance</span>
        <span class="theme-pill hidden md:inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold" data-theme-current-summary>
            System · Default
        </span>
        <svg class="h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div class="{{ $panelClasses }}" data-dropdown-menu data-dropdown-panel data-theme-switcher-panel>
        <div class="px-1 pb-3">
            <p class="theme-text-muted text-[11px] font-semibold uppercase tracking-[0.14em]">Appearance</p>
            <p class="theme-text-muted mt-1 max-w-[24rem] text-sm leading-6">Set your preferred mode and palette across the workspace.</p>
        </div>

        <div class="space-y-4">
            <section aria-label="Theme mode options">
                <p class="theme-text-muted px-1 text-[11px] font-semibold uppercase tracking-[0.14em]">Mode</p>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    @foreach($modeOptions as $value => $option)
                        <button
                            type="button"
                            class="theme-switcher-option flex min-h-11 items-center justify-center gap-2 rounded-2xl px-3 py-2.5 text-sm font-medium"
                            data-theme-preference-option="{{ $value }}"
                            aria-pressed="false"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $option['icon'] }}" />
                            </svg>
                            <span>{{ $option['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </section>

            <div class="border-t" style="border-color: var(--app-panel-border);"></div>

            <section aria-label="Theme palette options">
                <p class="theme-text-muted px-1 text-[11px] font-semibold uppercase tracking-[0.14em]">Palette</p>
                <div class="mt-2 grid gap-2.5" data-theme-palette-grid>
                    @foreach($accentOptions as $value => $option)
                        <button
                            type="button"
                            class="theme-switcher-option flex w-full items-start justify-between gap-4 rounded-2xl px-3.5 py-3 text-left"
                            data-theme-accent-option="{{ $value }}"
                            data-theme-palette-card
                            aria-pressed="false"
                        >
                            <span class="flex min-w-0 items-start gap-3">
                                <span class="theme-swatch inline-flex h-10 w-10 shrink-0 rounded-full {{ $option['swatch'] }}"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="theme-text-strong block text-sm font-semibold">{{ $option['label'] }}</span>
                                    <span class="theme-text-muted block text-xs leading-5">{{ $option['hint'] }}</span>
                                </span>
                            </span>
                            <span class="theme-text-muted mt-1 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-[11px]" data-theme-selected-mark aria-hidden="true">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="m5 13 4 4L19 7" />
                                </svg>
                            </span>
                        </button>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</div>
