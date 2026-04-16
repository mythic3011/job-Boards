<x-layouts.base :title="'Forgot Password'" :show-header="false">
    <x-auth.shell
        title="Reset your password"
        subtitle="For security, password reset requires a verified second factor or one of your recovery codes."
        max-width="max-w-5xl"
    >
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.743 5.743L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.586a1 1 0 0 1 .293-.707l5.964-5.964A6 6 0 1 1 21 9Z" />
            </svg>
        </x-slot:icon>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(300px,0.9fr)] lg:items-start">
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
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
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
                                <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
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
                                <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
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

            <div class="space-y-4">
                <x-ui.card tone="subtle" padding="p-6">
                    <x-ui.section-label class="mb-2">Security</x-ui.section-label>
                    <h2 class="theme-text-strong text-xl font-semibold">Why do I need 2FA?</h2>
                    <div class="theme-text-muted mt-4 space-y-3 text-sm leading-6">
                        <p>Password recovery stays tied to the same second-factor posture as your sign-in flow.</p>
                        <p>If you no longer have the authenticator app, switch to a recovery code instead of retrying the same request.</p>
                    </div>
                </x-ui.card>

                <x-ui.card padding="p-6">
                    <x-ui.section-label class="mb-2">Recovery notes</x-ui.section-label>
                    <div class="theme-text-muted space-y-3 text-sm leading-6">
                        <p>Reset links are only issued after the verification step on this screen succeeds.</p>
                        <p>If you have lost both the authenticator app and your recovery codes, contact support before continuing.</p>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </x-auth.shell>
</x-layouts.base>
