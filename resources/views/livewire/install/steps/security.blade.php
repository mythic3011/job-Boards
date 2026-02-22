{{-- Step 3: Two-Factor Authentication --}}
<div class="space-y-6"
    x-data="{
        verified: @js($testSuccess),
        copying: false,
        showCodes: false,
        tryNextStep() {
            if (!this.verified) return;
            $wire.nextStep();
        },
        downloadCodes() {
            const content = @js($this->downloadRecoveryCodes());
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'recovery_codes.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },
        async copySecret() {
            await navigator.clipboard.writeText('{{ $twoFactorSecret }}');
            this.copying = true;
            setTimeout(() => this.copying = false, 2000);
        }
    }"
    x-on:2fa-verified.window="verified = true"
>
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Secure Your Account</h2>
        <p class="text-sm text-gray-600 mt-1">Two-factor authentication is required for admin accounts</p>
    </div>

    <div class="space-y-4">
        {{-- Step A: Scan or enter key --}}
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                <p class="text-sm font-semibold text-gray-700">1. Add to your authenticator app</p>
                <p class="text-xs text-gray-500 mt-0.5">Google Authenticator, Authy, 1Password, etc.</p>
            </div>
            <div class="p-4 flex flex-col sm:flex-row items-center gap-6">
                {{-- QR Code --}}
                <div class="flex-shrink-0">
                    @if($qrCodeDataUrl)
                        <div class="bg-white p-3 border border-gray-200 rounded-lg inline-block">
                            {!! $qrCodeDataUrl !!}
                        </div>
                    @else
                        <div class="w-40 h-40 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 text-xs text-center px-4">
                            QR code unavailable
                        </div>
                    @endif
                </div>

                {{-- Manual key --}}
                <div class="flex-1 w-full">
                    <p class="text-xs font-medium text-gray-600 mb-2">Or enter this key manually:</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-sm font-mono bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-gray-900 break-all">{{ $formattedSecret }}</code>
                        <button type="button" @click="copySecret"
                            class="flex-shrink-0 px-3 py-2 text-xs font-medium bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors min-w-[56px] text-center">
                            <span x-show="!copying">Copy</span>
                            <span x-show="copying" class="text-green-600">Copied!</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step B: Verify code --}}
        <div class="border {{ $testSuccess ? 'border-green-300 bg-green-50' : 'border-gray-200' }} rounded-xl overflow-hidden transition-colors">
            <div class="bg-gray-50 {{ $testSuccess ? 'bg-green-50' : '' }} px-4 py-3 border-b {{ $testSuccess ? 'border-green-200' : 'border-gray-200' }}">
                <p class="text-sm font-semibold text-gray-700">2. Verify it works</p>
                <p class="text-xs text-gray-500 mt-0.5">Enter the 6-digit code from your app to confirm setup</p>
            </div>
            <div class="p-4">
                @if($testSuccess)
                    <div class="flex items-center gap-2 text-green-700">
                        <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm font-medium">Verified — your authenticator app is working correctly</span>
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <input
                            type="text"
                            wire:model="testCode"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="000000"
                            class="w-32 px-3 py-2 text-center text-lg font-mono border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        >
                        <button type="button" wire:click="testOTP"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                            Verify
                        </button>
                        @if($testResult && !$testSuccess)
                            <p class="text-sm text-red-600">{{ $testResult }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Step C: Save recovery codes --}}
        <div class="border border-amber-200 bg-amber-50 rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-amber-200 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-700">3. Save your recovery codes</p>
                    <p class="text-xs text-gray-500 mt-0.5">Store these somewhere safe — you'll need them if you lose your device</p>
                </div>
                <button type="button" @click="showCodes = !showCodes"
                    class="text-xs text-amber-700 font-medium hover:text-amber-900 underline flex-shrink-0 ml-4">
                    <span x-show="!showCodes">Show</span>
                    <span x-show="showCodes">Hide</span>
                </button>
            </div>
            <div x-show="showCodes" x-transition class="p-4 space-y-3">
                @if(count($recoveryCodes) > 0)
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach($recoveryCodes as $code)
                            <code class="text-xs font-mono bg-white border border-amber-200 px-2 py-1.5 rounded text-gray-800">{{ $code }}</code>
                        @endforeach
                    </div>
                    <button type="button" @click="downloadCodes"
                        class="text-xs font-medium text-amber-700 hover:text-amber-900 underline">
                        Download as text file
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="flex gap-3 pt-2">
        <button type="button" wire:click="previousStep"
            class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors">
            ← Back
        </button>
        <button type="button" @click="tryNextStep"
            :disabled="!verified"
            :class="verified ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
            class="flex-1 px-6 py-3 rounded-lg font-medium transition-colors">
            Continue →
        </button>
    </div>
</div>
