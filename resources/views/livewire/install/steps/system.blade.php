{{-- Step 2: System Configuration --}}
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">System Configuration</h2>
        <p class="text-sm text-gray-600 mt-1">Customize your application settings</p>
    </div>

    <form wire:submit.prevent="nextStep" class="space-y-4">
        <div>
            <label for="app_name" class="block text-sm font-medium text-gray-700 mb-1.5">
                Application Name
            </label>
            <input
                type="text"
                id="app_name"
                wire:model="app_name"
                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('app_name') border-red-300 @enderror"
                placeholder="My Job Board"
            >
            <p class="text-xs text-gray-500 mt-1">Shown in headers and emails</p>
            @error('app_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="app_url" class="block text-sm font-medium text-gray-700 mb-1.5">
                Application URL
            </label>
            <input
                type="url"
                id="app_url"
                wire:model="app_url"
                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('app_url') border-red-300 @enderror"
                placeholder="https://jobs.example.com"
            >
            <p class="text-xs text-gray-500 mt-1">Your site's base URL</p>
            @error('app_url') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1.5">
                Timezone
            </label>
            <select
                id="timezone"
                wire:model="timezone"
                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white @error('timezone') border-red-300 @enderror">
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
            <p class="text-xs text-gray-500 mt-1">All timestamps will use this timezone</p>
            @error('timezone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex gap-3 pt-2">
            <button
                type="button"
                wire:click="previousStep"
                class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors">
                ← Back
            </button>
            <button
                type="submit"
                class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                Continue →
            </button>
        </div>
    </form>
</div>
