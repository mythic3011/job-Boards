{{-- Step 2: System Configuration --}}
<div class="space-y-6">
    <div>
        <h2 class="theme-text-strong text-2xl font-bold">System Configuration</h2>
        <p class="theme-text-muted text-sm mt-1">Customize your application settings.</p>
    </div>

    <form wire:submit.prevent="nextStep" class="space-y-4">
        <div>
            <label for="app_name" class="theme-text-strong block text-sm font-medium mb-1.5">
                Application Name
            </label>
            <input
                type="text"
                id="app_name"
                wire:model="app_name"
                class="theme-input w-full px-3.5 py-2.5 border rounded-xl @error('app_name') theme-input-error @enderror"
                placeholder="My Job Board"
            >
            <p class="theme-text-muted text-xs mt-1">Shown in headers and emails.</p>
            @error('app_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="app_url" class="theme-text-strong block text-sm font-medium mb-1.5">
                Application URL
            </label>
            <input
                type="url"
                id="app_url"
                wire:model="app_url"
                class="theme-input w-full px-3.5 py-2.5 border rounded-xl @error('app_url') theme-input-error @enderror"
                placeholder="https://jobs.example.com"
            >
            <p class="theme-text-muted text-xs mt-1">Your site's base URL.</p>
            @error('app_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="timezone" class="theme-text-strong block text-sm font-medium mb-1.5">
                Timezone
            </label>
            <select
                id="timezone"
                wire:model="timezone"
                class="theme-input w-full px-3.5 py-2.5 border rounded-xl @error('timezone') theme-input-error @enderror">
                <option value="Asia/Hong_Kong">Hong Kong (HKT)</option>
                <option value="UTC">UTC</option>
                <option value="Asia/Shanghai">Shanghai (CST)</option>
                <option value="Asia/Tokyo">Tokyo (JST)</option>
                <option value="Asia/Singapore">Singapore (SGT)</option>
                <option value="Europe/London">London (GMT)</option>
                <option value="Europe/Paris">Paris (CET)</option>
                <option value="America/New_York">New York (EST)</option>
                <option value="America/Los_Angeles">Los Angeles (PST)</option>
            </select>
            <p class="theme-text-muted text-xs mt-1">All timestamps will use this timezone.</p>
            @error('timezone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button
                type="button"
                wire:click="previousStep"
                class="theme-button theme-button-outline rounded-xl px-6 py-3 font-medium">
                ← Back
            </button>
            <button
                type="submit"
                class="theme-button theme-button-primary flex-1 rounded-xl px-6 py-3 font-medium shadow-sm">
                Continue →
            </button>
        </div>
    </form>
</div>
