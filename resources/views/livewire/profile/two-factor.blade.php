

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
                            <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
                        {{ decrypt($user->two_factor_secret) }}
                    </code>
                </div>

                <div class="max-w-xs mx-auto">
                    <x-ui.input
                        label="Verification Code"
                        name="verificationCode"
                        wire:model="verificationCode"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        placeholder="000000"
                        autocomplete="one-time-code"
                        help="Enter the 6-digit code from your authenticator app"
                    />
                </div>

                <div class="flex justify-center gap-3">
                    <x-ui.button wire:click="confirm2FA" variant="primary">
                        Verify & Enable
                    </x-ui.button>
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
                <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span>Use a strong, unique password for your account</span>
            </div>
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span>Keep your recovery codes in a safe place, separate from your device</span>
            </div>
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
                <span>Never share your authentication codes with anyone</span>
            </div>
        </div>
    </x-ui.card>
</div>
