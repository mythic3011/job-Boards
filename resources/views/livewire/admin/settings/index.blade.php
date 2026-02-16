<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\layout;
use function Livewire\Volt\title;

layout('layouts.app');
title('Admin - Settings');

new class extends Component
{
    #[Validate('required|boolean')]
    public bool $demo_mode = false;

    #[Validate('required|boolean')]
    public bool $registrations_open = true;

    public bool $current_demo_mode = false;

    public bool $current_registrations_open = true;

    public bool $showConfirmModal = false;

    public string $password = '';

    public function mount(): void
    {
        $this->demo_mode = Setting::getBool('demo_mode', false);
        $this->registrations_open = Setting::getBool('registrations_open', true);
        $this->current_demo_mode = $this->demo_mode;
        $this->current_registrations_open = $this->registrations_open;
    }

    public function save(): void
    {
        $this->authorize('admin.settings.update');
        $this->validate();

        // NEW: Server-side validation for changes
        if (!$this->hasChanges()) {
            session()->flash('info', 'No changes to save.');
            return;
        }

        // Show confirmation modal if there are changes
        $this->showConfirmModal = true;
    }

    public function confirmSave(): void
    {
        $this->authorize('admin.settings.update');
        $this->validate();

        // Password confirmation for security
        if (empty($this->password)) {
            $this->addError('password', 'Password confirmation is required for security.');

            return;
        }

        if (! \Illuminate\Support\Facades\Hash::check($this->password, auth()->user()->password)) {
            $this->addError('password', 'The provided password is incorrect.');
            $this->password = '';

            return;
        }

        $this->password = '';

        // Rate limiting to prevent abuse
        $rateLimitKey = 'settings-update:' . (auth()->id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->showConfirmModal = false;
            session()->flash('error', "Too many attempts. Please try again in {$seconds} seconds.");
            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        // Verify changes again on server side
        if (!$this->hasChanges()) {
            $this->showConfirmModal = false;
            session()->flash('info', 'No changes to save.');
            return;
        }

        // Get current database state
        $demoModeBefore = Setting::getBool('demo_mode', false);
        $registrationsOpenBefore = Setting::getBool('registrations_open', true);

        // NEW: Double check against actual DB state
        if ($this->demo_mode === $demoModeBefore && 
            $this->registrations_open === $registrationsOpenBefore) {
            $this->current_demo_mode = $demoModeBefore;
            $this->current_registrations_open = $registrationsOpenBefore;
            $this->showConfirmModal = false;
            session()->flash('info', 'Settings are already up to date.');
            return;
        }

        // Save settings
        Setting::setBool('demo_mode', $this->demo_mode);
        Setting::setBool('registrations_open', $this->registrations_open);

        if ($demoModeBefore === false && $this->demo_mode === true) {
            // Enable demo mode: seed demo data
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
            Setting::set('demo_seeded_at', now()->toDateTimeString());
        }

        if ($demoModeBefore === true && $this->demo_mode === false) {
            // Disable demo mode: remove demo data
            $this->clearDemoData();
        }

        app(DashboardService::class)->clearCache();

        $this->current_demo_mode = $this->demo_mode;
        $this->current_registrations_open = $this->registrations_open;
        $this->showConfirmModal = false;

        app(AuditLogger::class)->logBusinessEvent(
            eventType: 'settings.updated',
            request: request(),
            targetType: 'setting',
            targetIdcode: null,
            meta: [
                'demo_mode' => [
                    'before' => $demoModeBefore,
                    'after' => $this->demo_mode,
                ],
                'registrations_open' => [
                    'before' => $registrationsOpenBefore,
                    'after' => $this->registrations_open,
                ],
            ]
        );

        session()->flash('message', $this->demo_mode
            ? 'Settings saved successfully! Demo data loaded.'
            : 'Settings saved successfully!' . ($demoModeBefore ? ' Demo data removed.' : '')
        );
    }

    protected function clearDemoData(): void
    {
        $demoSeededAt = Setting::get('demo_seeded_at');

        if (! $demoSeededAt) {
            \Log::warning('Attempted to clear demo data but no demo_seeded_at timestamp found');
            session()->flash('error', 'No demo data to clear.');

            return;
        }

        // Only delete users created AFTER demo seeding
        \Illuminate\Support\Facades\DB::transaction(function () use ($demoSeededAt) {
            $demoUsers = \App\Models\User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })
                ->where('created_at', '>=', $demoSeededAt)
                ->get();

            $userCount = $demoUsers->count();

            if ($userCount === 0) {
                \Log::info('No demo users to delete');

                return;
            }

            // Audit log BEFORE deletion
            app(\App\Services\AuditLogger::class)->logBusinessEvent(
                eventType: 'demo.data_cleared',
                request: request(),
                targetType: 'system',
                targetIdcode: null,
                meta: [
                    'users_deleted' => $userCount,
                    'demo_seeded_at' => $demoSeededAt,
                    'user_ids' => $demoUsers->pluck('id')->toArray(),
                ]
            );

            foreach ($demoUsers as $user) {
                $user->delete();
            }
        });

        Setting::set('demo_seeded_at', null);
    }

    // NEW: Server-side method to check for changes
    protected function hasChanges(): bool
    {
        // Use loose comparison to handle type juggling if needed, though boolean properties should be fine
        return $this->demo_mode !== $this->current_demo_mode || 
               $this->registrations_open !== $this->current_registrations_open;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">System Settings</h1>
    </div>

    {{-- NEW: Support for multiple alert types --}}
    @if (session('message'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('message') }}
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert type="error" class="mb-6">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    @if (session('info'))
        <x-ui.alert type="info" class="mb-6">
            {{ session('info') }}
        </x-ui.alert>
    @endif

    <x-ui.card padding="p-8">
        <form
            wire:submit="save"
            class="space-y-6"
            x-data="{ 
                demoMode: @entangle('demo_mode'), 
                registrationsOpen: @entangle('registrations_open'), 
                currentDemoMode: @entangle('current_demo_mode'), 
                currentRegistrationsOpen: @entangle('current_registrations_open'),
                showConfirm: @entangle('showConfirmModal'),
                get hasChanges() {
                    return this.demoMode !== this.currentDemoMode || this.registrationsOpen !== this.currentRegistrationsOpen;
                }
            }"
        >
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Feature Toggles</h2>
                <p class="text-sm text-gray-600 mt-1">Configure system-wide settings and features.</p>
            </div>

            <div class="grid gap-4">
                <!-- Demo Mode Setting -->
                <div class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <label class="flex items-start cursor-pointer flex-1">
                            <input
                                type="checkbox"
                                wire:model="demo_mode"
                                class="mt-1 h-5 w-5 text-indigo-600 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 border-gray-300 rounded transition"
                            >
                            <div class="ml-3 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="text-gray-900">Demo Mode</strong>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">
                                    Populate the system with sample data for demonstration purposes. Disables certain production features.
                                </p>
                            </div>
                        </label>
                        <div class="flex flex-col items-end gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold shadow-sm transition-all"
                                :class="currentDemoMode 
                                    ? 'bg-gradient-to-r from-emerald-50 to-emerald-100 text-emerald-700 ring-1 ring-emerald-600/20' 
                                    : 'bg-gradient-to-r from-gray-50 to-gray-100 text-gray-600 ring-1 ring-gray-300/50'"
                            >
                                <span class="h-2 w-2 rounded-full" :class="currentDemoMode ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400'"></span>
                                <span x-text="currentDemoMode ? 'Active' : 'Inactive'"></span>
                            </span>
                            <span
                                x-show="demoMode !== currentDemoMode"
                                x-transition
                                class="text-xs font-medium text-amber-600"
                            >
                                Pending change
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Registrations Open Setting -->
                <div class="relative rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-start justify-between gap-4">
                        <label class="flex items-start cursor-pointer flex-1">
                            <input
                                type="checkbox"
                                wire:model="registrations_open"
                                class="mt-1 h-5 w-5 text-indigo-600 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 border-gray-300 rounded transition"
                            >
                            <div class="ml-3 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="text-gray-900">User Registrations</strong>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">
                                    Allow new users to create accounts and access the platform.
                                </p>
                            </div>
                        </label>
                        <div class="flex flex-col items-end gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold shadow-sm transition-all"
                                :class="currentRegistrationsOpen 
                                    ? 'bg-gradient-to-r from-blue-50 to-blue-100 text-blue-700 ring-1 ring-blue-600/20' 
                                    : 'bg-gradient-to-r from-gray-50 to-gray-100 text-gray-600 ring-1 ring-gray-300/50'"
                            >
                                <span class="h-2 w-2 rounded-full" :class="currentRegistrationsOpen ? 'bg-blue-500' : 'bg-gray-400'"></span>
                                <span x-text="currentRegistrationsOpen ? 'Open' : 'Closed'"></span>
                            </span>
                            <span
                                x-show="registrationsOpen !== currentRegistrationsOpen"
                                x-transition
                                class="text-xs font-medium text-amber-600"
                            >
                                Pending change
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t">
                <div x-show="hasChanges" x-transition class="flex items-center gap-2 text-sm text-amber-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-medium">You have unsaved changes</span>
                </div>
                <x-ui.button 
                    type="submit" 
                    variant="primary" 
                    x-bind:disabled="!hasChanges"
                    wire:loading.attr="disabled"
                    class="min-w-[140px] transition-opacity"
                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !hasChanges }"
                >
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Processing…</span>
                </x-ui.button>
            </div>

            <!-- Confirmation Modal -->
            <div
                x-show="showConfirm"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
                x-on:keydown.escape.window="showConfirm = false"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
            >
                <div 
                    class="w-full max-w-lg rounded-xl bg-white shadow-2xl"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    @click.outside="showConfirm = false"
                >
                    <div class="border-b px-6 py-4 bg-gradient-to-r from-indigo-50 to-blue-50">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Confirm Changes</h3>
                                <p class="text-sm text-gray-600">Review the impact before applying.</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="password-confirm" class="block text-sm font-medium text-gray-700">
                                Confirm Password
                            </label>
                            <input
                                type="password"
                                id="password-confirm"
                                wire:model="password"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Enter your password to confirm"
                                required
                            >
                            @error('password')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="px-6 py-5 space-y-3">
                        <div 
                            x-show="demoMode !== currentDemoMode"
                            class="rounded-lg p-4 text-sm"
                            :class="demoMode ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200'"
                        >
                            <div class="flex items-start gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5" :class="demoMode ? 'text-emerald-600' : 'text-amber-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                                </svg>
                                <div>
                                    <p class="font-semibold" :class="demoMode ? 'text-emerald-900' : 'text-amber-900'">Demo Mode</p>
                                    <p class="mt-1" :class="demoMode ? 'text-emerald-700' : 'text-amber-700'" x-text="demoMode ? 'Will be enabled — sample data will be loaded into the system.' : 'Will be disabled — all demo data will be permanently removed.'"></p>
                                </div>
                            </div>
                        </div>
                        <div 
                            x-show="registrationsOpen !== currentRegistrationsOpen"
                            class="rounded-lg p-4 text-sm"
                            :class="registrationsOpen ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 border border-gray-200'"
                        >
                            <div class="flex items-start gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mt-0.5" :class="registrationsOpen ? 'text-blue-600' : 'text-gray-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                <div>
                                    <p class="font-semibold" :class="registrationsOpen ? 'text-blue-900' : 'text-gray-900'">User Registrations</p>
                                    <p class="mt-1" :class="registrationsOpen ? 'text-blue-700' : 'text-gray-700'" x-text="registrationsOpen ? 'Will be opened — new users can create accounts.' : 'Will be closed — registration will be disabled.'"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t bg-gray-50 px-6 py-4">
                        <x-ui.button type="button" variant="outline" x-on:click="showConfirm = false">
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            variant="primary"
                            wire:click="confirmSave"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="confirmSave">Apply Changes</span>
                            <span wire:loading wire:target="confirmSave">Applying…</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    </x-ui.card>
</div>