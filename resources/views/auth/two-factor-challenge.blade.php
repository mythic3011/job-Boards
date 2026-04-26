<x-layouts.base :title="'Two-Factor Authentication'" :show-header="false">
    <x-auth.shell
        title="Two-Factor Authentication"
        subtitle="Confirm access with your authenticator code or one of your recovery codes."
        max-width="max-w-3xl"
    >
        <x-slot:icon>
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 1 0-8 0v4h8Z" />
            </svg>
        </x-slot:icon>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.75fr)] lg:items-start" x-data="{ useRecovery: false }">
            <x-ui.card padding="p-8">
                <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
                    @csrf
                    <x-honeypot />

                    <div x-show="!useRecovery" x-cloak>
                        <x-ui.input
                            label="Authentication Code"
                            id="code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            autofocus
                            autocomplete="one-time-code"
                            placeholder="000000"
                            :value="old('code')"
                        />
                        <p class="theme-text-muted mt-1.5 text-xs">Enter the 6-digit code from your authenticator app.</p>
                    </div>

                    <div x-show="useRecovery" x-cloak>
                        <x-ui.input
                            label="Recovery Code"
                            id="recovery_code"
                            name="recovery_code"
                            type="text"
                            autocomplete="off"
                            placeholder="XXXX-XXXX"
                            :value="old('recovery_code')"
                        />
                        <p class="theme-text-muted mt-1.5 text-xs">Enter one saved recovery code.</p>
                    </div>

                    <button
                        type="button"
                        @click="useRecovery = !useRecovery"
                        class="theme-link text-sm font-medium cursor-pointer"
                    >
                        <span x-show="!useRecovery">Use a recovery code instead</span>
                        <span x-show="useRecovery" x-cloak>Use authentication code</span>
                    </button>

                    <div class="flex items-start gap-3 pt-1">
                        <input
                            id="trust_device"
                            name="trust_device"
                            type="checkbox"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-[var(--app-panel-border)] text-[var(--app-accent-strong)] focus:ring-[var(--app-focus-ring)] cursor-pointer"
                        >
                        <div>
                            <label for="trust_device" class="theme-text-strong text-sm font-medium cursor-pointer">
                                Trust this device for 30 days
                            </label>
                            <p class="theme-text-muted mt-0.5 text-xs">Skip code prompts on this device during the next 30 days.</p>
                        </div>
                    </div>

                    <x-ui.button type="submit" variant="primary" class="w-full">
                        Verify and continue
                    </x-ui.button>
                </form>

                <div class="mt-5 text-center">
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="theme-link text-sm font-medium cursor-pointer">
                            Cancel and log out
                        </button>
                    </form>
                </div>
            </x-ui.card>

            <x-ui.card tone="subtle" padding="p-6">
                <x-ui.section-label class="mb-2">Need Help?</x-ui.section-label>
                <h2 class="theme-text-strong text-lg font-semibold">Verification Notes</h2>
                <ul class="theme-text-muted mt-4 space-y-3 text-sm leading-6">
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                        <span>Authenticator code is the default verification method.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                        <span>Recovery codes are for backup access only.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-1 h-2 w-2 rounded-full bg-[var(--app-accent-strong)]"></span>
                        <span>If both methods are unavailable, stop and contact support.</span>
                    </li>
                </ul>
            </x-ui.card>
        </div>
    </x-auth.shell>
</x-layouts.base>
