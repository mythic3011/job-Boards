<x-layouts.base :title="'Forgot Password'" :show-header="false">
    <x-auth.shell title="Reset your password" subtitle="For security, password reset still requires two-factor verification.">
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.743 5.743L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.586a1 1 0 0 1 .293-.707l5.964-5.964A6 6 0 1 1 21 9Z" />
            </svg>
        </x-slot:icon>

        <x-ui.card padding="p-8" x-data="{ useRecovery: false }">
            <form class="space-y-6" action="{{ route('password.email') }}" method="POST">
                @csrf

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

                <div>
                    <div x-show="!useRecovery">
                        <label for="code" class="theme-text-strong mb-1 block text-sm font-medium">Two-Factor Authentication Code</label>
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
                        <p class="theme-text-muted mt-1 text-xs">Enter the 6-digit code from your authenticator app.</p>
                        @error('code')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-show="useRecovery" x-cloak>
                        <label for="recovery_code" class="theme-text-strong mb-1 block text-sm font-medium">Recovery Code</label>
                        <input
                            id="recovery_code"
                            name="recovery_code"
                            type="text"
                            autocomplete="off"
                            placeholder="XXXX-XXXX"
                            value="{{ old('recovery_code') }}"
                            class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow {{ $errors->has('recovery_code') ? 'theme-input-error' : '' }}"
                        >
                        <p class="theme-text-muted mt-1 text-xs">Enter one of your recovery codes.</p>
                        @error('recovery_code')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="button"
                        @click="useRecovery = !useRecovery"
                        class="theme-link mt-2 text-sm underline underline-offset-2 cursor-pointer"
                    >
                        <span x-show="!useRecovery">Use a recovery code instead</span>
                        <span x-show="useRecovery" x-cloak>Use authentication code</span>
                    </button>
                </div>

                <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                    Verify and Reset Password
                </x-ui.button>

                <div class="text-center">
                    <a href="{{ route('login') }}" class="theme-link text-sm font-medium">
                        Back to login
                    </a>
                </div>
            </form>
        </x-ui.card>

        <div class="theme-info-panel rounded-2xl border p-4">
            <h3 class="text-sm font-semibold">Why do I need 2FA?</h3>
            <p class="mt-1 text-sm">
                Password reset requires two-factor authentication to protect your account. If you no longer have access to your authenticator app or recovery codes, contact support.
            </p>
        </div>
    </x-auth.shell>
</x-layouts.base>
