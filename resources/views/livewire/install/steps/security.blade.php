{{-- Step 3: Two-Factor Authentication --}}
<div class="space-y-6" 
    x-data="{ 
        copying: false,
        verified: @js($testSuccess),
        tryNextStep() {
            if (!this.verified) {
                alert('⚠️ 2FA Verification Required\n\nPlease enter the 6-digit code from your authenticator app and click Verify before continuing to the next step.');
                return;
            }
            $wire.nextStep();
        },
        downloadCodes() {
            const content = @js($this->downloadRecoveryCodes());
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'recovery_codes_' + Date.now() + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
        async copySecret() {
            const secret = '{{ $twoFactorSecret }}';
            try {
                await navigator.clipboard.writeText(secret);
                this.copying = true;
                setTimeout(() => this.copying = false, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        },
        async copyCodes() {
            const codes = @json($recoveryCodes);
            try {
                await navigator.clipboard.writeText(codes.join('\n'));
                this.copying = true;
                setTimeout(() => this.copying = false, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        }
    }"
    x-on:2fa-verified.window="verified = true"
>
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Two-Factor Authentication</h2>
        <p class="text-sm text-gray-600 mt-1">Secure your admin account with 2FA (required for administrators)</p>
    </div>

    <form @submit.prevent="tryNextStep()" class="space-y-5">
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 space-y-5">
            <!-- QR Code -->
            <div class="text-center">
                <div class="inline-block bg-white p-4 rounded-xl shadow-sm">
                    @if($qrCodeDataUrl)
                        {!! $qrCodeDataUrl !!}
                    @else
                        <div class="w-48 h-48 flex items-center justify-center text-gray-400">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                    @endif
                </div>
                <p class="text-sm text-gray-700 mt-3">Scan with Google Authenticator, Authy, or similar app</p>
            </div>

            <!-- Manual Entry Key -->
            <div class="bg-white rounded-lg p-4">
                <label class="block text-xs font-semibold text-gray-700 mb-2">Manual Entry Key</label>
                <div class="flex items-center gap-2">
                    <code class="flex-1 text-sm font-mono bg-gray-50 px-3 py-2 rounded border text-gray-900">
                        {{ $formattedSecret }}
                    </code>
                    <button 
                        type="button"
                        @click="copySecret"
                        class="px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded transition-colors">
                        <span x-show="!copying">Copy</span>
                        <span x-show="copying" class="text-green-600">✓</span>
                    </button>
                </div>
            </div>

            <!-- Test Code -->
            <div class="bg-white rounded-lg p-4 space-y-3">
                <label class="block text-xs font-semibold text-gray-700">Test Your Code</label>
                <div class="flex gap-2">
                    <input 
                        type="text" 
                        wire:model="testCode"
                        inputmode="numeric" 
                        maxlength="6" 
                        placeholder="123456"
                        class="w-32 px-3 py-2 text-center text-lg font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                    >
                    <button 
                        type="button"
                        wire:click="testOTP"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg font-medium hover:bg-indigo-700">
                        Verify
                    </button>
                </div>
                @if($testResult)
                    <p class="text-xs {{ $testSuccess ? 'text-green-600' : 'text-red-600' }}">
                        {{ $testResult }}
                    </p>
                @endif
            </div>
        </div>

        <!-- Recovery Codes -->
        <div class="bg-amber-50 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                <svg class="w-4 h-4 mr-2 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                Recovery Codes
            </h4>
            <p class="text-xs text-gray-700 mb-3">Save these codes securely. Use them if you lose access to your authenticator.</p>
            
            @if(count($recoveryCodes) > 0)
                <div class="grid grid-cols-2 gap-1.5 font-mono text-xs mb-3">
                    @foreach($recoveryCodes as $code)
                        <div class="text-gray-700 bg-white px-2 py-1 rounded">{{ $code }}</div>
                    @endforeach
                </div>
                <div class="flex gap-3 text-xs">
                    <button type="button" @click="copyCodes" class="text-indigo-600 hover:text-indigo-800 font-medium">
                        Copy All
                    </button>
                    <button type="button" @click="downloadCodes" class="text-indigo-600 hover:text-indigo-800 font-medium">
                        Download
                    </button>
                </div>
            @endif
        </div>

        <div x-show="!verified" x-transition class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-yellow-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-yellow-800">You must verify your 2FA code above before continuing to the next step.</p>
        </div>

        <div x-show="verified" x-transition class="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-green-800">✓ 2FA verified successfully! You can now continue.</p>
        </div>

        <div class="flex gap-3 pt-4">
            <button 
                type="button"
                wire:click="previousStep"
                class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors">
                ← Back
            </button>
            <button 
                type="submit"
                x-bind:disabled="!verified"
                x-bind:class="verified ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-gray-300 text-gray-500 cursor-not-allowed'"
                class="flex-1 px-6 py-3 rounded-lg font-medium transition-colors shadow-sm">
                Continue →
            </button>
        </div>
    </form>
</div>
