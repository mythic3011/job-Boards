{{-- Step 4: Review & Complete --}}
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Review & Complete</h2>
        <p class="text-sm text-gray-600 mt-1">Check everything looks right before finishing setup</p>
    </div>

    <div class="space-y-3">
        {{-- Admin Account Summary --}}
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Admin Account</h3>
                <button type="button" wire:click="$set('currentStep', 1)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
            </div>
            <div class="px-4 py-3 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Username</span>
                    <span class="font-medium text-gray-900">{{ $username }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Name</span>
                    <span class="font-medium text-gray-900">{{ $name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Email</span>
                    <span class="font-medium text-gray-900">{{ $email }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">2FA</span>
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
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">System Settings</h3>
                <button type="button" wire:click="$set('currentStep', 2)"
                    class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
            </div>
            <div class="px-4 py-3 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">App Name</span>
                    <span class="font-medium text-gray-900">{{ $app_name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">URL</span>
                    <span class="font-medium text-gray-900 truncate max-w-xs">{{ $app_url }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Timezone</span>
                    <span class="font-medium text-gray-900">{{ $timezone }}</span>
                </div>
            </div>
        </div>

        {{-- Demo Data Option --}}
        <label class="flex items-start gap-3 p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
            <input
                type="checkbox"
                wire:model="installDemo"
                class="mt-0.5 w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
            >
            <div>
                <span class="block text-sm font-medium text-gray-900">Install demo data</span>
                <span class="block text-xs text-gray-500 mt-0.5">Adds sample jobs and applications so you can explore features right away</span>
            </div>
        </label>
    </div>

    <div class="flex gap-3">
        <button
            type="button"
            wire:click="previousStep"
            wire:loading.attr="disabled"
            class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors disabled:opacity-50">
            ← Back
        </button>
        <button
            type="button"
            wire:click="complete"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-60 cursor-not-allowed"
            class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
            <span wire:loading.remove wire:target="complete">Complete Setup</span>
            <span wire:loading wire:target="complete">Installing...</span>
        </button>
    </div>
</div>
