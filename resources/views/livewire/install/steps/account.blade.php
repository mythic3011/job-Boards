{{-- Step 1: Admin Account --}}
<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Create Admin Account</h2>
        <p class="text-sm text-gray-600 mt-1">This account will have full access to manage your job board</p>
    </div>

    <form wire:submit.prevent="nextStep" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Username <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="username"
                    wire:model="username"
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('username') border-red-300 @enderror"
                    placeholder="admin"
                    autocomplete="username"
                >
                <p class="text-xs text-gray-500 mt-1">Letters, numbers, underscores (3+ chars)</p>
                @error('username') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    wire:model="name"
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('name') border-red-300 @enderror"
                    placeholder="John Doe"
                    autocomplete="name"
                >
                @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                Email Address <span class="text-red-500">*</span>
            </label>
            <input
                type="email"
                id="email"
                wire:model="email"
                class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('email') border-red-300 @enderror"
                placeholder="admin@example.com"
                autocomplete="email"
            >
            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Password <span class="text-red-500">*</span>
                </label>
                <input
                    type="password"
                    id="password"
                    wire:model="password"
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('password') border-red-300 @enderror"
                    placeholder="Min 12 characters"
                    autocomplete="new-password"
                >
                <p class="text-xs text-gray-500 mt-1">Min 12 chars, mixed case + numbers</p>
                @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Confirm Password <span class="text-red-500">*</span>
                </label>
                <input
                    type="password"
                    id="password_confirmation"
                    wire:model="password_confirmation"
                    class="w-full px-3.5 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Re-enter password"
                    autocomplete="new-password"
                >
            </div>
        </div>

        <div class="pt-2">
            <button type="submit"
                class="w-full px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                Continue →
            </button>
        </div>
    </form>
</div>
