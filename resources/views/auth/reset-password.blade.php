<x-layouts.base :title="'Reset Password'" :show-header="false">
    @php
        $token = $request->route('token');
        $adminInitiated = \Illuminate\Support\Facades\Cache::has('admin_reset:' . $token);
    @endphp

    <x-auth.shell title="Set a new password" subtitle="Finish the recovery flow by choosing a strong replacement password.">
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.743 5.743L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.586a1 1 0 0 1 .293-.707l5.964-5.964A6 6 0 1 1 21 9Z" />
            </svg>
        </x-slot:icon>

        <x-ui.card padding="p-8">
            <form class="space-y-5" action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-honeypot />

                <div>
                    <label for="email" class="theme-text-strong mb-1 block text-sm font-medium">Email address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        required
                        value="{{ old('email', $request->email) }}"
                        class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow {{ $errors->has('email') ? 'theme-input-error' : '' }}"
                    >
                    @error('email')
                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="theme-text-strong mb-1 block text-sm font-medium">New password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow {{ $errors->has('password') ? 'theme-input-error' : '' }}"
                    >
                    @error('password')
                        <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="theme-text-strong mb-1 block text-sm font-medium">Confirm password</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow"
                    >
                </div>

                @unless($adminInitiated)
                    <div>
                        <label for="two_factor_code" class="theme-text-strong mb-1 block text-sm font-medium">Two-Factor Authentication Code</label>
                        <input
                            id="two_factor_code"
                            name="two_factor_code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="000000"
                            value="{{ old('two_factor_code') }}"
                            class="theme-input block w-full rounded-xl border px-3 py-2.5 text-sm transition-shadow {{ $errors->has('two_factor_code') ? 'theme-input-error' : '' }}"
                        >
                        <p class="theme-text-muted mt-1 text-xs">Required if your account has two-factor authentication enabled.</p>
                        @error('two_factor_code')
                            <p class="mt-1.5 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endunless

                <div class="theme-panel-subtle rounded-2xl border p-4">
                    <h3 class="theme-text-strong mb-2 text-sm font-semibold">Password Requirements</h3>
                    <ul class="theme-text-muted space-y-1 text-xs">
                        <li>At least 12 characters long</li>
                        <li>Contains uppercase and lowercase letters</li>
                        <li>Contains at least one number</li>
                        <li>Contains at least one special character</li>
                        <li>Not found in common password breaches</li>
                    </ul>
                </div>

                <x-ui.button type="submit" variant="primary" class="w-full justify-center">
                    Reset password
                </x-ui.button>
            </form>
        </x-ui.card>
    </x-auth.shell>
</x-layouts.base>
