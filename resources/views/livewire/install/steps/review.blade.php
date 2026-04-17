{{-- Step 4: Review & Complete --}}
<div class="space-y-6">
    <div>
        <h2 class="theme-text-strong text-2xl font-bold">Review & Complete</h2>
        <p class="theme-text-muted text-sm mt-1">Check everything looks right before finishing setup.</p>
    </div>

    <div class="space-y-3">
        {{-- Admin Account Summary --}}
        <div class="theme-panel-subtle border rounded-2xl overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--app-panel-border);">
                <h3 class="theme-text-strong text-sm font-semibold">Admin Account</h3>
                <button type="button" wire:click="$set('currentStep', 1)"
                    class="theme-link text-xs font-medium">Edit</button>
            </div>
            <div class="px-4 py-3 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="theme-text-muted">Username</span>
                    <span class="theme-text-strong font-medium">{{ $username }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="theme-text-muted">Name</span>
                    <span class="theme-text-strong font-medium">{{ $name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="theme-text-muted">Email</span>
                    <span class="theme-text-strong font-medium">{{ $email }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="theme-text-muted">2FA</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Enabled & Verified
                    </span>
                </div>
            </div>
        </div>

        {{-- System Settings Summary --}}
        <div class="theme-panel-subtle border rounded-2xl overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between" style="border-color: var(--app-panel-border);">
                <h3 class="theme-text-strong text-sm font-semibold">System Settings</h3>
                <button type="button" wire:click="$set('currentStep', 2)"
                    class="theme-link text-xs font-medium">Edit</button>
            </div>
            <div class="px-4 py-3 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="theme-text-muted">App Name</span>
                    <span class="theme-text-strong font-medium">{{ $app_name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="theme-text-muted">URL</span>
                    <span class="theme-text-strong font-medium truncate max-w-xs">{{ $app_url }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="theme-text-muted">Timezone</span>
                    <span class="theme-text-strong font-medium">{{ $timezone }}</span>
                </div>
            </div>
        </div>

        {{-- Demo Data Option --}}
        <label class="theme-panel-subtle flex items-start gap-3 p-4 border rounded-2xl cursor-pointer transition-colors hover:bg-[var(--app-panel-bg)]">
            <input
                type="checkbox"
                wire:model="installDemo"
                class="mt-0.5 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            >
            <div>
                <span class="theme-text-strong block text-sm font-medium">Install demo data</span>
                <span class="theme-text-muted block text-xs mt-0.5">Adds sample jobs and applications so you can explore features right away.</span>
            </div>
        </label>
    </div>

    <div class="flex gap-3">
        <button
            type="button"
            wire:click="previousStep"
            wire:loading.attr="disabled"
            class="theme-button theme-button-outline rounded-xl px-6 py-3 font-medium disabled:opacity-50">
            ← Back
        </button>
        <button
            type="button"
            wire:click="complete"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-60 cursor-not-allowed"
            class="theme-button theme-button-primary flex-1 rounded-xl px-6 py-3 font-medium shadow-sm">
            <span wire:loading.remove wire:target="complete">Complete Setup</span>
            <span wire:loading wire:target="complete">Installing...</span>
        </button>
    </div>
</div>
