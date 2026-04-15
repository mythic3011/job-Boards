<div class="max-w-5xl mx-auto space-y-6">
    <nav class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('profile.show') }}" class="hover:text-indigo-600 transition-colors">Profile</a>
        <svg style="width:14px;height:14px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-900 font-medium">Security Settings</span>
    </nav>

    <div>
        <h1 class="text-2xl font-bold text-gray-900">Security Settings</h1>
        <p class="text-gray-600 mt-1">Manage your account security and two-factor authentication</p>
    </div>

    @include('profile.partials.workspace-nav', [
        'active' => 'two-factor',
        'twoFactorEnabled' => $is2FAEnabled,
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
                            <h2 class="text-lg font-semibold text-gray-900">Two-Factor Authentication</h2>
                            @if($is2FAEnabled)
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Active
                                </span>
                            @elseif($isSettingUp2FA)
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                    Setup in progress
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                    Inactive
                                </span>
                            @endif
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-4">
                            @if($is2FAEnabled)
                                Your account is protected with two-factor authentication. Sign-in requires your authenticator app, such as Google Authenticator, Authy, or 1Password.
                            @elseif($isSettingUp2FA)
                                Complete setup by scanning the QR code and confirming a valid 6-digit code from your authenticator app.
                            @else
                                Add an extra layer of security to your account with two-factor authentication.
                            @endif
                        </p>
                    </div>
                </div>

                @if($is2FAEnabled)
                    <div class="space-y-4">
                        @if(count($recoveryCodes) > 0)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-amber-600 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1">
                                        <h3 class="text-sm font-medium text-amber-800 mb-1">Recovery Codes</h3>
                                        <p class="text-sm text-amber-700 mb-3">
                                            Save these codes in a safe place. Use them to access your account if you lose your authenticator device.
                                        </p>
                                        <div class="grid grid-cols-2 gap-2 mb-3">
                                            @foreach($recoveryCodes as $code)
                                                <code class="rounded border bg-white px-3 py-2 text-xs font-mono">{{ $code }}</code>
                                            @endforeach
                                        </div>
                                        <button wire:click="regenerateRecoveryCodes" 
                                                wire:confirm="This will invalidate your current recovery codes. Continue?"
                                                class="text-sm text-amber-700 hover:text-amber-800 font-medium underline">
                                            Generate New Codes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-3">
                            <x-ui.button 
                                wire:click="disable2FA" 
                                wire:confirm="Are you sure? This will make your account less secure."
                                variant="danger"
                                size="sm">
                                Disable 2FA
                            </x-ui.button>
                        </div>
                    </div>

                @elseif($isSettingUp2FA)
                    <div class="space-y-6">
                        <div class="text-center">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Scan QR Code</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Use your authenticator app (Google Authenticator, Authy, 1Password, etc.) to scan this code:
                            </p>
                            
                            <div class="inline-block rounded-lg border-2 border-gray-200 bg-white p-4">
                                {!! $user->twoFactorQrCodeSvg() !!}
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <p class="text-sm font-medium text-gray-700 mb-2">Can't scan? Enter this code manually:</p>
                            <code class="block rounded border bg-white px-3 py-2 text-sm font-mono break-all">
                                {{ $secret }}
                            </code>
                        </div>

                        <div class="max-w-xs mx-auto">
                            <div class="space-y-1">
                                <label for="verificationCode" class="block text-sm font-medium text-gray-700">
                                    Verification Code
                                </label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        id="verificationCode"
                                        name="verificationCode"
                                        wire:model.live.debounce.300ms="verificationCode"
                                        maxlength="6"
                                        pattern="[0-9]{6}"
                                        placeholder="000000"
                                        autocomplete="one-time-code"
                                        class="block w-full rounded-lg border-0 py-1.5 px-3 text-gray-900 shadow-sm ring-1 ring-inset {{ $codeIsValid ? 'ring-green-500 focus:ring-green-600' : ($errors->has('verificationCode') ? 'ring-red-300 focus:ring-red-500' : 'ring-gray-300 focus:ring-indigo-600') }} placeholder:text-gray-400 focus:ring-2 focus:ring-inset sm:text-sm sm:leading-6"
                                    >
                                    @if($errors->has('verificationCode'))
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                            <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @elseif($codeIsValid)
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                            <svg class="h-5 w-5 text-green-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                @if($errors->has('verificationCode'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('verificationCode') }}</p>
                                @else
                                    <p class="mt-1 text-sm text-gray-500">Enter the 6-digit code. It will verify automatically.</p>
                                @endif
                            </div>
                            @if($codeIsValid)
                                <p class="mt-2 text-xs text-green-600 text-center">✓ Code verified! Redirecting...</p>
                            @endif
                        </div>

                        <div class="flex justify-center gap-3">
                            <x-ui.button wire:click="cancel2FA" variant="outline">
                                Cancel
                            </x-ui.button>
                        </div>
                    </div>

                @else
                    <div class="py-6 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Secure Your Account</h3>
                        <p class="mx-auto mb-6 max-w-md text-gray-600">
                            Two-factor authentication adds an extra layer of security by requiring a code from your phone in addition to your password.
                        </p>
                        <x-ui.button wire:click="enable2FA" variant="primary">
                            Set Up Two-Factor Authentication
                        </x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card>
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Security Tips</h3>
                <div class="space-y-3 text-sm text-gray-600">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Use a strong, unique password for your account</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Keep your recovery codes in a safe place, separate from your device</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span>Never share your authentication codes with anyone</span>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card>
                <h2 class="text-lg font-semibold text-gray-900">Security Overview</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-gray-500">Account</dt>
                        <dd class="text-right font-medium text-gray-900">{{ $user?->nickname ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-gray-500">Status</dt>
                        <dd class="font-medium {{ $is2FAEnabled ? 'text-green-700' : ($isSettingUp2FA ? 'text-blue-700' : 'text-yellow-700') }}">
                            {{ $is2FAEnabled ? 'Protected' : ($isSettingUp2FA ? 'Setup in progress' : 'Not protected') }}
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-gray-500">Recovery codes</dt>
                        <dd class="font-medium text-gray-900">{{ count($recoveryCodes) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    @if($is2FAEnabled)
                        Sign-in now requires both your password and a fresh code from your authenticator app.
                    @elseif($isSettingUp2FA)
                        Finish code verification to activate protection and generate recovery codes.
                    @else
                        Enable 2FA here before you try to use the password change flow.
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                <div class="mt-4 space-y-3">
                    <a href="{{ route('profile.show') }}" class="flex items-start justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm transition-colors hover:border-indigo-200 hover:bg-indigo-50">
                        <div>
                            <p class="font-medium text-gray-900">Profile Overview</p>
                            <p class="mt-1 text-xs text-gray-500">Review account details and shortcuts.</p>
                        </div>
                        <span class="text-indigo-600">Open</span>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="flex items-start justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm transition-colors hover:border-indigo-200 hover:bg-indigo-50">
                        <div>
                            <p class="font-medium text-gray-900">Edit Profile</p>
                            <p class="mt-1 text-xs text-gray-500">Update your photo, display name, and email address.</p>
                        </div>
                        <span class="text-indigo-600">Open</span>
                    </a>
                    @if($is2FAEnabled)
                        <a href="{{ route('profile.password') }}" class="flex items-start justify-between rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm transition-colors hover:border-indigo-200 hover:bg-indigo-50">
                            <div>
                                <p class="font-medium text-gray-900">Change Password</p>
                                <p class="mt-1 text-xs text-gray-500">Continue to the 2FA-protected password flow.</p>
                            </div>
                            <span class="text-indigo-600">Open</span>
                        </a>
                    @else
                        <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                            <p class="font-medium">Change Password is unavailable</p>
                            <p class="mt-1 text-xs">Enable and confirm 2FA first, then the password route becomes available.</p>
                        </div>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
