@props([
    'title' => null,
    'description' => null,
    'showHeader' => true,
    'showNavigation' => true,
    'bodyClass' => '',
    'mainClass' => '',
])

@php
    $showHeader = filter_var($showHeader, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $showNavigation = filter_var($showNavigation, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    $appName = config('app.name', 'Jobs Board');
    $resolvedTitle = filled($title) ? trim((string) $title) : null;
    $fullTitle = $resolvedTitle && $resolvedTitle !== $appName
        ? "{$resolvedTitle} · {$appName}"
        : $appName;
    $resolvedDescription = trim((string) ($description ?? "Secure hiring workspace for jobs, applications, profile management, and operator flows in {$appName}."));;
    $bodyClass = trim("theme-page-shell min-h-screen {$bodyClass}");
    $mainClass = trim(($showHeader ? 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8' : '') . " {$mainClass}");
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="h-full"
    data-theme-root
    data-theme-preference="system"
    data-theme-mode="light"
    data-theme-accent="indigo"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $resolvedDescription }}">
    <meta name="application-name" content="{{ $appName }}">
    <meta name="color-scheme" content="light dark">
    <meta name="format-detection" content="telephone=no">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f4f7fb">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#09111f">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>{{ $fullTitle }}</title>

    <script nonce="{{ csp_nonce() }}">
        (() => {
            const root = document.documentElement;
            const preferenceKey = 'jobs-board.theme.preference';
            const accentKey = 'jobs-board.theme.accent';
            const validPreferences = new Set(['system', 'light', 'dark']);
            const validAccents = new Set(['indigo', 'graphite', 'forest']);

            let preference = 'system';
            let accent = 'indigo';

            try {
                const storedPreference = window.localStorage.getItem(preferenceKey);
                const storedAccent = window.localStorage.getItem(accentKey);

                if (validPreferences.has(storedPreference)) {
                    preference = storedPreference;
                }

                if (validAccents.has(storedAccent)) {
                    accent = storedAccent;
                }
            } catch {
                // Continue with defaults when storage is unavailable.
            }

            const mode = preference === 'system'
                ? (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : preference;

            root.dataset.themePreference = preference;
            root.dataset.themeMode = mode;
            root.dataset.themeAccent = accent;
            root.style.colorScheme = mode;
        })();
    </script>

    @stack('meta')
    <x-layouts.assets />
    @livewireStyles(['nonce' => csp_nonce()])
    @stack('head')
</head>
<body class="{{ $bodyClass }}">
    <a
        href="#main-content"
        class="theme-panel theme-text-strong sr-only rounded-xl border px-4 py-2 text-sm font-medium shadow-sm focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[120]"
    >
        Skip to content
    </a>
    @if($showHeader)
        <x-layouts.header :show-navigation="$showNavigation" />
    @else
        <div class="fixed right-4 top-4 z-[70] sm:right-6 sm:top-6">
            <x-ui.theme-switcher placement="floating" />
        </div>
    @endif

    <main id="main-content" class="{{ $mainClass }}" role="main">
        @if($showHeader)
            <x-layouts.flash-messages />
        @endif

        {{ $slot }}
    </main>

    @livewireScripts(['nonce' => csp_nonce()])
    @auth
        @livewire('components.maintenance-alert')
    @endauth
    <div class="theme-floating-stack" data-floating-controls>
        <button
            type="button"
            class="theme-floating-action inline-flex items-center gap-2 rounded-full px-3 py-2.5 text-sm font-semibold"
            data-back-to-top
            data-visible="false"
            aria-label="Back to top"
            aria-controls="main-content"
            title="Back to top"
            tabindex="-1"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 10 7-7 7 7M12 3v18" />
            </svg>
            <span class="hidden sm:inline">Back to top</span>
        </button>
    </div>
    <div id="modal-root" class="relative z-[9999]"></div>
</body>
</html>
