{{-- Step 1: Admin Account --}}
<div class="space-y-6">
    <div>
        <h2 class="theme-text-strong text-2xl font-bold">Create Admin Account</h2>
        <p class="theme-text-muted text-sm mt-1">This account will have full access to manage your job board.</p>
    </div>

    <form wire:submit.prevent="nextStep" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="username" class="theme-text-strong block text-sm font-medium mb-1.5">
                    Username <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="username"
                    wire:model="username"
                    class="theme-input w-full px-3.5 py-2.5 border rounded-xl @error('username') theme-input-error @enderror"
                    placeholder="admin"
                    autocomplete="username"
                >
                <p class="theme-text-muted text-xs mt-1">Letters, numbers, underscores (3+ chars)</p>
                @error('username') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="name" class="theme-text-strong block text-sm font-medium mb-1.5">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    wire:model="name"
                    class="theme-input w-full px-3.5 py-2.5 border rounded-xl @error('name') theme-input-error @enderror"
                    placeholder="John Doe"
                    autocomplete="name"
                >
                @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="email" class="theme-text-strong block text-sm font-medium mb-1.5">
                Email Address <span class="text-red-500">*</span>
            </label>
            <input
                type="email"
                id="email"
                wire:model="email"
                class="theme-input w-full px-3.5 py-2.5 border rounded-xl @error('email') theme-input-error @enderror"
                placeholder="admin@example.com"
                autocomplete="email"
            >
            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="password" class="theme-text-strong block text-sm font-medium mb-1.5">
                    Password <span class="text-red-500">*</span>
                </label>
                <input
                    type="password"
                    id="password"
                    wire:model="password"
                    class="theme-input w-full px-3.5 py-2.5 border rounded-xl @error('password') theme-input-error @enderror"
                    placeholder="Min 12 characters"
                    autocomplete="new-password"
                >
                <p class="theme-text-muted text-xs mt-1">Min 12 chars, mixed case + numbers</p>
                @error('password') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="theme-text-strong block text-sm font-medium mb-1.5">
                    Confirm Password <span class="text-red-500">*</span>
                </label>
                <input
                    type="password"
                    id="password_confirmation"
                    wire:model="password_confirmation"
                    class="theme-input w-full px-3.5 py-2.5 border rounded-xl"
                    placeholder="Re-enter password"
                    autocomplete="new-password"
                >
            </div>
        </div>

        <div class="pt-2">
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-60 cursor-not-allowed"
                wire:target="nextStep"
                class="theme-button theme-button-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3 font-medium shadow-sm transition-opacity disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="nextStep">Continue →</span>
                <span wire:loading wire:target="nextStep" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Continuing...
                </span>
            </button>
        </div>
    </form>
</div>
