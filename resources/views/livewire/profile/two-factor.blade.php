<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Security Settings</h1>
        <p class="text-gray-600 mt-1">Manage your account security and two-factor authentication</p>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
        <x-ui.alert type="success">
            {{ session('success') }}
        </x-ui.alert>
    @endif



    <!-- 2FA Auth Status -->
    <x-ui.card>
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                    <h2 class="text-lg font-semibold text-gray-900">Two-Factor Authentication</h2>
                    @if($is2FAEnabled)
                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                            Inactive
                        </span>
                    @endif
                </div>
                
                <p class="text-sm text-gray-600 mb-4">
                    @if($is2FAEnabled)
                        Your account is protected with two-factor authentication. You'll need your authenticator app to sign in. such as Google Authenticator, Authy, 1Password, etc.
                    @else
                        Add an extra layer of security to your account with two-factor authentication.
                    @endif
                </p>
            </div>
        </div>

        @if($is2FAEnabled)
            <!-- 2FA is Active -->
            <div class="space-y-4">
                @if(count($recoveryCodes) > 0)
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
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
                                        <code class="bg-white px-3 py-2 rounded border text-xs font-mono">{{ $code }}</code>
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
            <!-- Setting Up 2FA -->
            <div class="space-y-6">
                <div class="text-center">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Scan QR Code</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Use your authenticator app (Google Authenticator, Authy, 1Password, etc.) to scan this code:
                    </p>
                    
                    <div class="inline-block p-4 bg-white border-2 border-gray-200 rounded-lg">
                        {!! $user->twoFactorQrCodeSvg() !!}
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Can't scan? Enter this code manually:</p>
                    <code class="block bg-white px-3 py-2 rounded border text-sm font-mono break-all">
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
            <!-- 2FA Not Set Up -->
            <div class="text-center py-6">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Secure Your Account</h3>
                <p class="text-gray-600 mb-6 max-w-md mx-auto">
                    Two-factor authentication adds an extra layer of security by requiring a code from your phone in addition to your password.
                </p>
                <x-ui.button wire:click="enable2FA" variant="primary">
                    Set Up Two-Factor Authentication
                </x-ui.button>
            </div>
        @endif
    </x-ui.card>

    <!-- Security Tips -->
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