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
        <h2 class="theme-text-strong text-2xl font-bold">Secure Your Account</h2>
        <p class="theme-text-muted text-sm mt-1">Two-factor authentication is required for admin accounts.</p>
    </div>

    <div class="space-y-4">
        {{-- Step A: Scan or enter key --}}
        <div class="theme-panel-subtle border rounded-2xl overflow-hidden">
            <div class="px-4 py-3 border-b" style="border-color: var(--app-panel-border);">
                <p class="theme-text-strong text-sm font-semibold">1. Add to your authenticator app</p>
                <p class="theme-text-muted text-xs mt-0.5">Google Authenticator, Authy, 1Password, and similar apps.</p>
            </div>
            <div class="p-4 flex flex-col sm:flex-row items-center gap-6">
                {{-- QR Code --}}
                <div class="shrink-0">
                    @if($qrCodeDataUrl)
                        <div class="theme-panel inline-block rounded-xl border p-3">
                            {!! $qrCodeDataUrl !!}
                        </div>
                    @else
                        <div class="theme-panel-subtle theme-text-muted w-40 h-40 rounded-xl flex items-center justify-center text-xs text-center px-4">
                            QR code unavailable
                        </div>
                    @endif
                </div>

                {{-- Manual key --}}
                <div class="flex-1 w-full">
                    <p class="theme-text-muted text-xs font-medium mb-2">Or enter this key manually:</p>
                    <div class="flex items-center gap-2">
                        <code class="theme-panel-subtle theme-text-strong flex-1 text-sm font-mono border rounded-xl px-3 py-2 break-all">{{ $formattedSecret }}</code>
                        <button type="button" @click="copySecret"
                            class="theme-button theme-button-outline shrink-0 px-3 py-2 text-xs font-medium rounded-xl min-w-[56px] text-center">
                            <span x-show="!copying">Copy</span>
                            <span x-show="copying" class="text-green-600">Copied!</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Step B: Verify code --}}
        <div class="{{ $testSuccess ? 'border-green-300 bg-green-50/60' : 'theme-panel-subtle' }} border rounded-2xl overflow-hidden transition-colors">
            <div class="{{ $testSuccess ? 'border-green-200' : '' }} px-4 py-3 border-b" style="{{ $testSuccess ? '' : 'border-color: var(--app-panel-border);' }}">
                <p class="theme-text-strong text-sm font-semibold">2. Verify it works</p>
                <p class="theme-text-muted text-xs mt-0.5">Enter the 6-digit code from your app to confirm setup.</p>
            </div>
            <div class="p-4">
                @if($testSuccess)
                    <div class="flex items-center gap-2 text-green-700">
                        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
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
                            class="theme-input w-32 px-3 py-2 text-center text-lg font-mono border rounded-xl"
                        >
                        <button type="button" wire:click="testOTP"
                            class="theme-button theme-button-primary rounded-xl px-4 py-2 text-sm font-medium">
                            Verify
                        </button>
                        @if($testResult && !$testSuccess)
                            <p class="text-sm text-red-500">{{ $testResult }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Step C: Save recovery codes --}}
        <div class="theme-alert theme-alert-warning border rounded-2xl overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--app-warning-border);">
                <div>
                    <p class="theme-text-strong text-sm font-semibold">3. Save your recovery codes</p>
                    <p class="theme-text-muted text-xs mt-0.5">Store these somewhere safe. You will need them if you lose your device.</p>
                </div>
                <button type="button" @click="showCodes = !showCodes"
                    class="text-xs font-medium underline shrink-0 ml-4" style="color: var(--app-warning-fg);">
                    <span x-show="!showCodes">Show</span>
                    <span x-show="showCodes">Hide</span>
                </button>
            </div>
            <div x-show="showCodes" x-transition class="p-4 space-y-3">
                @if(count($recoveryCodes) > 0)
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach($recoveryCodes as $code)
                            <code class="theme-panel text-xs font-mono border px-2 py-1.5 rounded-xl">{{ $code }}</code>
                        @endforeach
                    </div>
                    <button type="button" @click="downloadCodes"
                        class="text-xs font-medium underline" style="color: var(--app-warning-fg);">
                        Download as text file
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="flex gap-3 pt-2">
        <button type="button" wire:click="previousStep"
            class="theme-button theme-button-outline rounded-xl px-6 py-3 font-medium">
            ← Back
        </button>
        <button type="button" @click="tryNextStep"
            :disabled="!verified"
            :class="verified ? 'theme-button-primary' : 'theme-button-outline theme-text-muted cursor-not-allowed opacity-70'"
            class="theme-button flex-1 rounded-xl px-6 py-3 font-medium">
            Continue →
        </button>
    </div>
</div>
