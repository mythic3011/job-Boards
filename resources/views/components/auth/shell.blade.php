@props([
    'title',
    'subtitle' => null,
    'maxWidth' => 'max-w-md',
])

<div class="theme-auth-shell" data-auth-shell>
    <div class="w-full {{ $maxWidth }} space-y-8">
        <x-layouts.flash-messages />

        <div class="mx-auto max-w-lg text-center" data-auth-panel-copy>
            @if(isset($icon))
                <div class="theme-auth-emblem mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl shadow-sm">
                    {{ $icon }}
                </div>
            @endif

            <h1 class="theme-text-strong text-3xl font-bold tracking-tight">{{ $title }}</h1>

            @if($subtitle)
                <div class="theme-text-muted mt-2 text-sm leading-6">
                    {{ $subtitle }}
                </div>
            @endif
        </div>

        {{ $slot }}

        @if(isset($footer))
            <div class="theme-text-muted text-center text-sm">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
