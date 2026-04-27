<div class="max-w-5xl mx-auto space-y-6">
    @php($registrationPending = $user?->isRegistrationPending() ?? false)

    <nav class="theme-text-muted mb-4 flex items-center gap-2 text-sm">
        @if($registrationPending)
            <span class="theme-text-muted">Account Activation</span>
        @else
            <a href="{{ route('profile.show') }}" class="theme-link transition-colors">Profile</a>
        @endif
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="theme-text-strong font-medium">Security Settings</span>
    </nav>

    <div>
        <h1 class="theme-text-strong text-2xl font-bold">{{ $registrationPending ? 'Finish Account Activation' : 'Security Settings' }}</h1>
        <p class="theme-text-muted mt-1">
            {{ $registrationPending ? 'Complete two-factor setup to finish activating your account.' : 'Manage sign-in protection, recovery access, and high-risk security actions.' }}
        </p>
    </div>

    @include('profile.partials.workspace-nav', [
        'active' => 'two-factor',
        'twoFactorEnabled' => $is2FAEnabled,
        'registrationPending' => $registrationPending,
    ])

    <!-- Flash Messages -->
    @if(session('success'))
        <x-ui.alert type="success">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="mb-2 flex items-center gap-3">
                            <h2 class="theme-text-strong text-lg font-semibold">Two-Factor Authentication</h2>
                            @if($is2FAEnabled)
                                <span class="theme-alert-success inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Active
                                </span>
                            @elseif($isSettingUp2FA)
                                <span class="theme-alert-info inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                    Setup in progress
                                </span>
                            @else
                                <span class="theme-panel-subtle theme-text-muted inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                    Inactive
                                </span>
                            @endif
                        </div>
                        
                        <p class="theme-text-muted mb-4 text-sm">
                            @if($is2FAEnabled)
                                Your account is protected with two-factor authentication. Sign-in requires your password plus a valid app code (Google Authenticator, Authy, 1Password, and similar apps).
                            @elseif($isSettingUp2FA)
                                Complete setup by scanning the QR code and confirming a valid 6-digit code from your authenticator app.
                            @else
                                Add an extra layer of security to your account with two-factor authentication before performing sensitive profile actions.
                            @endif
                        </p>
                    </div>
                </div>

                @if($is2FAEnabled)
                    <div class="space-y-4">
                        @if(count($recoveryCodes) > 0)
                            <div class="theme-alert-warning rounded-lg border p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1">
                                        <h3 class="mb-1 text-sm font-medium">Recovery Codes</h3>
                                        <p class="mb-3 text-sm">
                                            Save these codes in a secure offline location. Each code can be used to recover access if your authenticator device is unavailable.
                                        </p>
                                        <div class="grid grid-cols-2 gap-2 mb-3">
                                            @foreach($recoveryCodes as $code)
                                                <code class="theme-panel inline-flex rounded border px-3 py-2 text-xs font-mono theme-text-strong">{{ $code }}</code>
                                            @endforeach
                                        </div>
                                        <p class="mb-3 text-xs">
                                            Generating new codes immediately invalidates all codes shown above.
                                        </p>
                                        @if(! $showRegenerateConfirmation)
                                            <button
                                                wire:click="promptRegenerateRecoveryCodes"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="opacity-60 cursor-not-allowed"
                                                wire:target="promptRegenerateRecoveryCodes"
                                                class="inline-flex items-center gap-2 text-sm font-medium underline transition-opacity disabled:cursor-not-allowed"
                                            >
                                                Create New Recovery Codes
                                            </button>
                                        @else
                                            <div class="mt-2 rounded-lg border border-[var(--app-warning-border)] bg-[var(--app-warning-bg)]/40 p-3">
                                                <p class="text-sm font-medium">Replace your current recovery codes?</p>
                                                <p class="mt-1 text-xs">
                                                    Your existing codes will stop working immediately. Save the new set before leaving this page.
                                                </p>
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    <x-ui.button
                                                        wire:click="regenerateRecoveryCodes"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="opacity-60 cursor-not-allowed"
                                                        wire:target="regenerateRecoveryCodes"
                                                        variant="warning"
                                                        size="sm">
                                                        <span wire:loading.remove wire:target="regenerateRecoveryCodes">Yes, replace codes</span>
                                                        <span wire:loading wire:target="regenerateRecoveryCodes" class="inline-flex items-center gap-2">
                                                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                                                            </svg>
                                                            Replacing...
                                                        </span>
                                                    </x-ui.button>
                                                    <x-ui.button
                                                        wire:click="cancelRegenerateRecoveryCodes"
                                                        wire:loading.attr="disabled"
                                                        wire:loading.class="opacity-60 cursor-not-allowed"
                                                        wire:target="cancelRegenerateRecoveryCodes"
                                                        variant="outline"
                                                        size="sm">
                                                        Keep current codes
                                                    </x-ui.button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(! $showDisableConfirmation)
                            <div class="space-y-2">
                                <x-ui.button
                                    wire:click="promptDisable2FA"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-60 cursor-not-allowed"
                                    wire:target="promptDisable2FA"
                                    variant="danger"
                                    size="sm">
                                    Disable 2FA
                                </x-ui.button>
                                <p class="theme-text-muted text-xs">
                                    Disabling 2FA lowers account protection right away and removes the extra sign-in check.
                                </p>
                            </div>
                        @else
                            <div class="theme-alert-warning rounded-xl border p-4">
                                <p class="font-medium">Disable two-factor authentication?</p>
                                <p class="mt-1 text-sm">
                                    You will only need your password to sign in after this. If you are sure, confirm below.
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-ui.button
                                        wire:click="disable2FA"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-60 cursor-not-allowed"
                                        wire:target="disable2FA"
                                        variant="danger"
                                        size="sm">
                                        <span wire:loading.remove wire:target="disable2FA">Yes, disable 2FA</span>
                                        <span wire:loading wire:target="disable2FA" class="inline-flex items-center gap-2">
                                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                                            </svg>
                                            Disabling...
                                        </span>
                                    </x-ui.button>
                                    <x-ui.button
                                        wire:click="cancelDisable2FA"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-60 cursor-not-allowed"
                                        wire:target="cancelDisable2FA"
                                        variant="outline"
                                        size="sm">
                                        Keep 2FA enabled
                                    </x-ui.button>
                                </div>
                            </div>
                        @endif
                    </div>

                @elseif($isSettingUp2FA)
                    <div
                        class="space-y-6"
                        x-data="twoFactorSetup"
                        data-secret="{{ $secret }}"
                    >
                        <div class="theme-panel-subtle rounded-2xl border p-4">
                            <h3 class="theme-text-strong text-base font-semibold">Finish setup in 3 steps</h3>
                            <ol class="theme-text-muted mt-2 space-y-1 text-sm">
                                <li>1. Scan the QR code with your authenticator app.</li>
                                <li>2. Enter the latest 6-digit code.</li>
                                <li>3. Verify and enable 2FA.</li>
                            </ol>
                        </div>

                        <div class="text-center">
                            <h3 class="theme-text-strong mb-2 text-lg font-medium">Step 1: Scan QR code</h3>
                            <p class="theme-text-muted mb-4 text-sm">
                                Open Google Authenticator, Authy, 1Password, or another authenticator app and scan this code.
                            </p>

                            <div class="theme-panel-subtle inline-block rounded-2xl border p-4">
                                {!! $user->twoFactorQrCodeSvg() !!}
                            </div>
                        </div>

                        <div class="theme-panel-subtle rounded-2xl border p-4">
                            <button
                                type="button"
                                x-on:click="toggleSecret()"
                                class="theme-link inline-flex items-center gap-2 text-sm font-medium"
                            >
                                <span x-show="!showSecret">Use manual setup code instead</span>
                                <span x-show="showSecret">Hide manual setup code</span>
                            </button>

                            <div x-show="showSecret" x-transition class="mt-3 space-y-3">
                                <p class="theme-text-muted text-xs">If scanning is unavailable, copy this setup code into your authenticator app.</p>
                                <code id="manual-secret" class="theme-panel theme-text-strong block rounded border px-3 py-2 text-sm font-mono break-all">{{ $secret }}</code>
                                <div class="flex items-center gap-3">
                                    <x-ui.button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        x-on:click="copySecret()"
                                    >
                                        Copy code
                                    </x-ui.button>
                                    <span x-show="copied" class="theme-signal-success text-xs">Copied</span>
                                </div>
                            </div>
                        </div>

                        <div class="max-w-sm mx-auto">
                            @php($canVerifyCode = strlen($verificationCode) === 6 && ctype_digit($verificationCode))

                            <div class="space-y-2">
                                <label for="verificationCode" class="theme-text-strong block text-sm font-medium">
                                    Step 2: Enter verification code
                                </label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        id="verificationCode"
                                        name="verificationCode"
                                        wire:model.live.debounce.200ms="verificationCode"
                                        wire:keydown.enter="verifyCode"
                                        maxlength="6"
                                        pattern="[0-9]{6}"
                                        inputmode="numeric"
                                        placeholder="6-digit code"
                                        autocomplete="one-time-code"
                                        class="theme-input block w-full rounded-lg border-0 px-3 py-2 text-center font-mono tracking-[0.28em] shadow-sm ring-1 ring-inset {{ $verificationState === 'success' ? 'ring-[var(--app-success-border)] focus:ring-[var(--app-success-fg)]' : (($verificationState === 'error' || $errors->has('verificationCode')) ? 'ring-[var(--app-danger-border)] focus:ring-[var(--app-danger-fg)]' : 'ring-[var(--app-input-border)] focus:ring-[var(--app-accent)]') }} placeholder:text-[var(--app-text-muted)] focus:ring-2 focus:ring-inset sm:text-base"
                                    >
                                    @if($verificationState === 'success')
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                            <svg class="theme-signal-success h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @elseif($verificationState === 'error' || $errors->has('verificationCode'))
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                            <svg class="theme-signal-danger h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                @if($verificationState === 'checking')
                                    <p class="theme-signal-info mt-1 flex items-center gap-2 text-sm">
                                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                                        </svg>
                                        {{ $verificationFeedback ?? 'Verifying your code...' }}
                                    </p>
                                @elseif($verificationState === 'error' || $errors->has('verificationCode'))
                                    <p class="theme-error-text mt-1 text-sm">{{ $verificationFeedback ?? $errors->first('verificationCode') }}</p>
                                @elseif($verificationState === 'success')
                                    <p class="theme-signal-success mt-1 text-sm">{{ $verificationFeedback ?? 'Success. Two-factor authentication is enabled. Redirecting...' }}</p>
                                @elseif($verificationState === 'ready')
                                    <p class="theme-signal-info mt-1 text-sm">{{ $verificationFeedback ?? 'Step 3: Click "Verify and enable 2FA" to finish setup.' }}</p>
                                @elseif($verificationState === 'incomplete')
                                    <p class="theme-text-muted mt-1 text-sm">{{ $verificationFeedback ?? 'Keep typing until all 6 digits are filled.' }}</p>
                                @else
                                    <p class="theme-text-muted mt-1 text-sm">{{ $verificationFeedback ?? 'Step 2: Enter a 6-digit code from your authenticator app.' }}</p>
                                @endif

                                @if($verificationState !== 'success')
                                    <p class="theme-text-muted mt-1 text-xs">
                                        If this code fails, wait for the next code in <span class="theme-text-strong" x-text="otpCountdown"></span>s and try again.
                                    </p>
                                @endif
                            </div>

                            <div class="mt-4 flex items-center justify-center gap-2">
                                <x-ui.button
                                    wire:click="verifyCode"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-60 cursor-not-allowed"
                                    wire:target="verifyCode"
                                    variant="primary"
                                    size="md"
                                    :disabled="! $canVerifyCode || $verificationState === 'checking' || $verificationState === 'success'"
                                >
                                    <span wire:loading.remove wire:target="verifyCode">Verify and enable 2FA</span>
                                    <span wire:loading wire:target="verifyCode" class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                                        </svg>
                                        Verifying...
                                    </span>
                                </x-ui.button>
                                <x-ui.button
                                    wire:click="cancel2FA"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-60 cursor-not-allowed"
                                    wire:target="cancel2FA"
                                    variant="outline"
                                    size="sm"
                                >
                                    <span wire:loading.remove wire:target="cancel2FA">Cancel setup</span>
                                    <span wire:loading wire:target="cancel2FA" class="inline-flex items-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                                        </svg>
                                        Cancelling...
                                    </span>
                                </x-ui.button>
                            </div>
                        </div>
                    </div>

                @else
                    <div class="py-6 text-center">
                        <div class="theme-auth-emblem mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="theme-text-strong mb-2 text-lg font-medium">Secure Your Account</h3>
                        <p class="theme-text-muted mx-auto mb-6 max-w-md">
                            Two-factor authentication adds an extra layer of security by requiring a code from your phone in addition to your password, reducing risk from leaked passwords.
                        </p>
                        <x-ui.button
                            wire:click="enable2FA"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-60 cursor-not-allowed"
                            wire:target="enable2FA"
                            variant="primary">
                            <span wire:loading.remove wire:target="enable2FA">Set Up Two-Factor Authentication</span>
                            <span wire:loading wire:target="enable2FA" class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                                </svg>
                                Setting up...
                            </span>
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card>
                <h3 class="theme-text-strong mb-3 text-lg font-semibold">Security Tips</h3>
                <div class="theme-text-muted space-y-3 text-sm">
                    <div class="flex items-start gap-3">
                        <svg class="theme-signal-success mt-0.5 h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Use a strong, unique password for your account</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="theme-signal-success mt-0.5 h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Keep your recovery codes in a safe place, separate from your device</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="theme-signal-success mt-0.5 h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Never share your authentication codes with anyone</span>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Security Overview</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Account</dt>
                        <dd class="theme-text-strong text-right font-medium">{{ $user?->nickname ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Status</dt>
                        <dd class="font-medium {{ $is2FAEnabled ? 'theme-signal-success' : ($isSettingUp2FA ? 'theme-signal-info' : 'theme-signal-warning') }}">
                            {{ $is2FAEnabled ? 'Protected' : ($isSettingUp2FA ? 'Setup in progress' : 'Not protected') }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="theme-text-muted">Recovery codes</dt>
                        <dd class="theme-text-strong font-medium">{{ count($recoveryCodes) }}</dd>
                    </div>
                </dl>

                <div class="theme-panel-subtle theme-text-muted mt-4 rounded-xl border px-4 py-3 text-sm">
                    @if($is2FAEnabled)
                        Sign-in now requires both your password and a fresh code from your authenticator app.
                    @elseif($isSettingUp2FA)
                        Finish code verification to activate protection and generate recovery codes.
                    @else
                        Enable 2FA here first to unlock sensitive account flows such as password changes.
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Quick Actions</h2>
                <div class="mt-4 space-y-3">
                    @if($registrationPending)
                        <div class="theme-panel-subtle rounded-xl border px-4 py-3 text-sm">
                            <p class="theme-text-strong font-medium">Finish activation here</p>
                            <p class="theme-text-muted mt-1 text-xs">Your account stays limited until this two-factor verification is completed.</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="theme-panel-subtle flex w-full items-start justify-between rounded-xl border px-4 py-3 text-left text-sm transition-colors hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-panel-bg)]">
                                <span>
                                    <span class="theme-text-strong block font-medium">Sign Out</span>
                                    <span class="theme-text-muted mt-1 block text-xs">Leave this session and come back when you are ready to finish setup.</span>
                                </span>
                                <span class="theme-link">Open</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('profile.show') }}" class="theme-panel-subtle flex items-start justify-between rounded-xl border px-4 py-3 text-sm transition-colors hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-panel-bg)]">
                            <div>
                                <p class="theme-text-strong font-medium">Profile Overview</p>
                                <p class="theme-text-muted mt-1 text-xs">Review account details, connected methods, and recent changes.</p>
                            </div>
                            <span class="theme-link">Open</span>
                        </a>
                        <a href="{{ route('profile.edit') }}" class="theme-panel-subtle flex items-start justify-between rounded-xl border px-4 py-3 text-sm transition-colors hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-panel-bg)]">
                            <div>
                                <p class="theme-text-strong font-medium">Edit Profile</p>
                                <p class="theme-text-muted mt-1 text-xs">Update profile details that affect how your account is identified.</p>
                            </div>
                            <span class="theme-link">Open</span>
                        </a>
                        @if($is2FAEnabled)
                            <a href="{{ route('profile.password') }}" class="theme-panel-subtle flex items-start justify-between rounded-xl border px-4 py-3 text-sm transition-colors hover:border-[var(--app-accent-soft-border)] hover:bg-[var(--app-panel-bg)]">
                                <div>
                                    <p class="theme-text-strong font-medium">Change Password</p>
                                    <p class="theme-text-muted mt-1 text-xs">Continue to the 2FA-protected password flow.</p>
                                </div>
                                <span class="theme-link">Open</span>
                            </a>
                        @else
                            <div class="theme-alert-warning rounded-xl border px-4 py-3 text-sm">
                                <p class="font-medium">Change Password is unavailable</p>
                                <p class="mt-1 text-xs">Enable and confirm 2FA first, then the password route becomes available.</p>
                            </div>
                        @endif
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
