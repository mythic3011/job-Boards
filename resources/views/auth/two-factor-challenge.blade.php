@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="max-w-md mx-auto" x-data="{ useRecovery: false }">
    <h1 class="theme-text-strong mb-6 text-center text-2xl font-bold">Two-Factor Authentication</h1>

    <x-ui.card padding="p-8">
        <div class="mb-6 text-center">
            <div class="theme-auth-emblem mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <p class="theme-text-muted text-sm leading-relaxed">
                Please confirm access to your account by entering the authentication code provided by your authenticator application.
            </p>
        </div>

        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
            @csrf

            {{-- Authentication Code --}}
            <div x-show="!useRecovery" x-cloak>
                <x-ui.input
                    label="Authentication Code"
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="000000"
                    :value="old('code')"
                />
                <p class="theme-text-muted mt-1.5 text-xs">Enter the 6-digit code from your authenticator app.</p>
            </div>

            {{-- Recovery Code --}}
            <div x-show="useRecovery" x-cloak>
                <x-ui.input
                    label="Recovery Code"
                    id="recovery_code"
                    name="recovery_code"
                    type="text"
                    autocomplete="off"
                    placeholder="XXXX-XXXX"
                    :value="old('recovery_code')"
                />
                <p class="theme-text-muted mt-1.5 text-xs">Enter one of your recovery codes.</p>
            </div>

            {{-- Toggle --}}
            <button
                type="button"
                @click="useRecovery = !useRecovery"
                class="theme-link text-sm underline underline-offset-2 cursor-pointer"
            >
                <span x-show="!useRecovery">Use a recovery code instead</span>
                <span x-show="useRecovery" x-cloak>Use authentication code</span>
            </button>

            {{-- Trust device --}}
            <div class="flex items-start gap-3 pt-1">
                <input
                    id="trust_device"
                    name="trust_device"
                    type="checkbox"
                    value="1"
                    class="mt-0.5 h-4 w-4 rounded border-[var(--app-panel-border)] text-[var(--app-accent-strong)] focus:ring-[var(--app-focus-ring)] cursor-pointer"
                >
                <div>
                    <label for="trust_device" class="theme-text-strong text-sm font-medium cursor-pointer">
                        Trust this device for 30 days
                    </label>
                    <p class="theme-text-muted mt-0.5 text-xs">You won't be asked for a code on this device for 30 days.</p>
                </div>
            </div>

            <x-ui.button type="submit" variant="primary" class="w-full">
                Verify and Continue
            </x-ui.button>
        </form>

        {{-- Cancel / logout --}}
        <div class="mt-5 text-center">
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="theme-link text-sm underline underline-offset-2 cursor-pointer">
                    Cancel and log out
                </button>
            </form>
        </div>
    </x-ui.card>

    <div class="theme-panel-subtle mt-6 rounded-xl border p-4">
        <h3 class="theme-text-strong mb-1 text-sm font-semibold">Having trouble?</h3>
        <p class="theme-text-muted text-sm">
            If you've lost access to your authenticator app, use a recovery code.
            If you don't have your recovery codes, please contact support.
        </p>
    </div>
</div>
@endsection
