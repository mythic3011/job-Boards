{{-- Step 4: Review & Complete --}}
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Review & Complete</h2>
        <p class="text-sm text-gray-600 mt-1">Everything looks good? Let's finish the setup!</p>
    </div>

    <div class="space-y-4">
        <!-- Demo Data Option -->
        <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg p-5 border border-green-200">
            <label class="flex items-start cursor-pointer group">
                <input 
                    type="checkbox" 
                    wire:model="installDemo"
                    class="mt-0.5 mr-3 w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                >
                <div>
                    <span class="block font-medium text-gray-900">Include Demo Data</span>
                    <span class="block text-sm text-gray-600 mt-0.5">Add sample jobs and applications to explore features</span>
                </div>
            </label>
        </div>

        <!-- Admin Account Summary -->
        <div class="bg-white border rounded-lg overflow-hidden">
            <div class="bg-gray-50 px-5 py-3 border-b">
                <h3 class="font-semibold text-gray-900 text-sm">Admin Account</h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Username</span>
                    <span class="font-medium text-gray-900">{{ $username }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Name</span>
                    <span class="font-medium text-gray-900">{{ $name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Email</span>
                    <span class="font-medium text-gray-900">{{ $email }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">2FA</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                        ✓ Enabled
                    </span>
                </div>
            </div>
        </div>

        <!-- System Settings Summary -->
        <div class="bg-white border rounded-lg overflow-hidden">
            <div class="bg-gray-50 px-5 py-3 border-b">
                <h3 class="font-semibold text-gray-900 text-sm">System Settings</h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">App Name</span>
                    <span class="font-medium text-gray-900">{{ $app_name }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Timezone</span>
                    <span class="font-medium text-gray-900">{{ $timezone }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">URL</span>
                    <span class="font-medium text-gray-900 truncate max-w-xs">{{ $app_url }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="flex gap-3 pt-4">
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
            wire:loading.class="opacity-50 cursor-not-allowed"
            class="flex-1 px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg font-medium hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg">
            <span wire:loading.remove wire:target="complete">Complete Installation ✓</span>
            <span wire:loading wire:target="complete">Installing...</span>
        </button>
    </div>
</div>
