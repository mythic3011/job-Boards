{{-- Step 2: System Configuration --}}
@php
    $systemRequirements = [
        'database' => 'Database connection',
        'storage' => 'Writable local storage',
        'cache' => 'Working cache store',
    ];
@endphp

<div class="space-y-6">
    <div>
        <h2 class="theme-text-strong text-2xl font-bold">System Configuration</h2>
        <p class="theme-text-muted text-sm mt-1">Customize your application settings.</p>
    </div>

    <div class="theme-panel-subtle border rounded-2xl overflow-hidden">
        <div class="px-4 py-3 border-b flex items-center justify-between gap-4" style="border-color: var(--app-panel-border);">
            <div>
                <h3 class="theme-text-strong text-sm font-semibold">System Requirements</h3>
                <p class="theme-text-muted text-xs mt-0.5">All checks must pass before you can continue to the security step.</p>
            </div>
            <button
                type="button"
                wire:click="refreshSystemChecks"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-60 cursor-not-allowed"
                wire:target="refreshSystemChecks"
                class="theme-button theme-button-outline inline-flex items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-medium transition-opacity disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="refreshSystemChecks">Re-check</span>
                <span wire:loading wire:target="refreshSystemChecks" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                    </svg>
                    Checking...
                </span>
            </button>
        </div>

        <div class="p-4 space-y-3">
            @if($checksError)
                <div class="theme-alert theme-alert-error rounded-xl border px-3 py-2 text-xs">
                    {{ $checksError }}
                </div>
            @endif

            @foreach($systemRequirements as $key => $label)
                @php
                    $loaded = $checksLoaded;
                    $passed = $loaded && (($systemChecks[$key] ?? false) === true);
                    $statusClasses = ! $loaded
                        ? 'bg-slate-100 text-slate-700'
                        : ($passed ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
                    $statusLabel = ! $loaded
                        ? 'Pending'
                        : ($passed ? 'Passed' : 'Action required');
                @endphp

                <div class="theme-panel flex items-center justify-between gap-3 rounded-xl border px-3 py-3">
                    <div>
                        <p class="theme-text-strong text-sm font-medium">{{ $label }}</p>
                        <p class="theme-text-muted text-xs mt-0.5">{{ ucfirst($key) }} must be available during installation.</p>
                    </div>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                        {{ $statusLabel }}
                    </span>
                </div>
            @endforeach

            @unless($this->systemRequirementsPassing)
                <p class="text-xs font-medium text-amber-700">
                    Fix any failed requirement and run the checks again before continuing.
                </p>
            @endunless
        </div>
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
                wire:loading.attr="disabled"
                wire:loading.class="opacity-60 cursor-not-allowed"
                wire:target="nextStep"
                @disabled(! $this->systemRequirementsPassing)
                class="theme-button inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-6 py-3 font-medium shadow-sm transition-opacity disabled:cursor-not-allowed {{ $this->systemRequirementsPassing ? 'theme-button-primary' : 'theme-button-outline theme-text-muted cursor-not-allowed opacity-70' }}">
                <span wire:loading.remove wire:target="nextStep">Continue →</span>
                <span wire:loading wire:target="nextStep" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                    </svg>
                    Continuing...
                </span>
            </button>
        </div>
    </form>
</div>
