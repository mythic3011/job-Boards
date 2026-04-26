<x-layouts.base :title="'Forgot Password'" :show-header="false">
    <x-auth.shell
        title="Reset your password"
        subtitle="For security, password reset requires a verified second factor or one of your recovery codes."
        max-width="max-w-3xl"
    >
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.743 5.743L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.586a1 1 0 0 1 .293-.707l5.964-5.964A6 6 0 1 1 21 9Z" />
            </svg>
        </x-slot:icon>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.75fr)] lg:items-start">
            <x-ui.card padding="p-8">
                <form class="space-y-6" action="{{ route('password.email') }}" method="POST" x-data="{ useRecovery: false }">
                    @csrf
                    <x-honeypot />

                    <div>
                        <label for="email" class="theme-text-strong mb-1 block text-sm font-medium">Email address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            value="{{ old('email') }}"
                            class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow {{ $errors->has('email') ? 'theme-input-error' : '' }}"
                        >
                        @error('email')
                            <p class="theme-error-text mt-1.5 text-sm">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-3">
                        <div x-show="!useRecovery">
                            <label for="code" class="theme-text-strong mb-1 block text-sm font-medium">Two-factor code</label>
                            <input
                                id="code"
                                name="code"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                autocomplete="one-time-code"
                                placeholder="000000"
                                value="{{ old('code') }}"
                                class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow {{ $errors->has('code') ? 'theme-input-error' : '' }}"
                            >
                            <p class="theme-text-muted mt-1 text-xs">Enter the six-digit code from your authenticator app.</p>
                            @error('code')
                                <p class="theme-error-text mt-1.5 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="useRecovery" x-cloak>
                            <label for="recovery_code" class="theme-text-strong mb-1 block text-sm font-medium">Recovery code</label>
                            <input
                                id="recovery_code"
                                name="recovery_code"
                                type="text"
                                autocomplete="off"
                                placeholder="XXXX-XXXX"
                                value="{{ old('recovery_code') }}"
                                class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow {{ $errors->has('recovery_code') ? 'theme-input-error' : '' }}"
                            >
                            <p class="theme-text-muted mt-1 text-xs">Use one of your saved recovery codes if the authenticator app is unavailable.</p>
                            @error('recovery_code')
                                <p class="theme-error-text mt-1.5 text-sm">{{ $message }}</p>
                            @enderror
                        </div>

                        <button
                            type="button"
                            @click="useRecovery = !useRecovery"
                            class="theme-link text-sm font-medium"
                        >
                            <span x-show="!useRecovery">Use a recovery code instead</span>
                            <span x-show="useRecovery" x-cloak>Use authentication code</span>
                        </button>
                    </div>

                    <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                        Verify and reset password
                    </x-ui.button>

                    <div class="text-center">
                        <a href="{{ route('login') }}" class="theme-link text-sm font-medium">
                            Back to login
                        </a>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card tone="subtle" padding="p-6">
                <x-ui.section-label class="mb-2">Guidance</x-ui.section-label>
                <h2 class="theme-text-strong text-lg font-semibold">Recovery Notes</h2>
                <details class="mt-4 rounded-2xl border border-[var(--app-panel-border)] bg-[var(--app-panel-bg)]">
                    <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold theme-text-strong">
                        Why verification is required
                    </summary>
                    <div class="theme-text-muted space-y-2 px-4 pb-4 text-sm leading-6">
                        <p>Password recovery follows the same second-factor posture as sign-in.</p>
                        <p>Switch to a recovery code if your authenticator app is unavailable.</p>
                        <p>Reset links are issued only after verification succeeds.</p>
                    </div>
                </details>
            </x-ui.card>
        </div>
    </x-auth.shell>
</x-layouts.base>
