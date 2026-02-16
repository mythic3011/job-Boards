<?php

use App\Models\Setting;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

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

    public bool $can_save = false;

    public function mount(): void
    {
        $this->demo_mode = Setting::getBool('demo_mode', false);
        $this->registrations_open = Setting::getBool('registrations_open', true);
        $this->current_demo_mode = $this->demo_mode;
        $this->current_registrations_open = $this->registrations_open;
        $this->can_save = false;
    }

    public function updatedDemoMode(): void
    {
        $this->can_save = false;
    }

    public function updatedRegistrationsOpen(): void
    {
        $this->can_save = false;
    }

    public function save(): void
    {
        $this->authorize('admin.settings.update');
        $this->validate();

        $demoModeBefore = Setting::getBool('demo_mode', false);
        $registrationsOpenBefore = Setting::getBool('registrations_open', true);

        Setting::setBool('demo_mode', $this->demo_mode);
        Setting::setBool('registrations_open', $this->registrations_open);

        $this->current_demo_mode = $this->demo_mode;
        $this->current_registrations_open = $this->registrations_open;

        app(\App\Services\AuditLogger::class)->logBusinessEvent(
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

        session()->flash('message', 'Settings saved successfully!');
    }

    public function confirmApply(): void
    {
        $this->authorize('admin.settings.update');
        $this->validate();

        $demoModeBefore = Setting::getBool('demo_mode', false);
        $registrationsOpenBefore = Setting::getBool('registrations_open', true);

        Setting::setBool('demo_mode', $this->demo_mode);
        Setting::setBool('registrations_open', $this->registrations_open);

        if ($demoModeBefore === false && $this->demo_mode === true) {
            // Enable demo mode: seed demo data
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder']);
            Setting::set('demo_seeded_at', now()->toDateTimeString());
        }

        if ($demoModeBefore === true && $this->demo_mode === false) {
            // Disable demo mode: remove demo data
            $this->clearDemoData();
        }

        app(\App\Services\DashboardService::class)->clearCache();

        $this->current_demo_mode = $this->demo_mode;
        $this->current_registrations_open = $this->registrations_open;
        $this->can_save = true;

        app(\App\Services\AuditLogger::class)->logBusinessEvent(
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
            ? 'Settings saved. Demo data loaded successfully!'
            : 'Settings saved. Demo data removed successfully!'
        );
    }

    protected function clearDemoData(): void
    {
        // Remove all non-admin users and their related demo data
        \Illuminate\Support\Facades\DB::transaction(function () {
            $nonAdminUsers = \App\Models\User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })->get();

            foreach ($nonAdminUsers as $user) {
                $user->delete();
            }
        });

        Setting::set('demo_seeded_at', null);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">System Settings</h1>
    </div>

    @if (session('message'))
        <x-ui.alert type="success" class="mb-6">
            {{ session('message') }}
        </x-ui.alert>
    @endif

    <x-ui.card padding="p-8">
        <form
            wire:submit="save"
            class="space-y-6"
            x-data="{ demoMode: @entangle('demo_mode'), registrationsOpen: @entangle('registrations_open'), currentDemoMode: @entangle('current_demo_mode'), currentRegistrationsOpen: @entangle('current_registrations_open'), showConfirm: false }"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Feature Toggles</h2>
                    <p class="text-sm text-gray-600">Confirm changes before saving settings.</p>
                </div>
                <span
                    class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold"
                    :class="$wire.can_save ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                >
                    <span class="h-1.5 w-1.5 rounded-full" :class="$wire.can_save ? 'bg-green-600' : 'bg-yellow-600'"></span>
                    <span x-text="$wire.can_save ? 'Confirmed' : 'Pending confirmation'"></span>
                </span>
            </div>
            <div class="grid gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <label class="flex items-start cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="demo_mode"
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            >
                            <span class="ml-3 text-gray-700">
                                <strong>Demo Mode</strong>
                                <p class="text-sm text-gray-600 mt-1">
                                    Enable demo mode to show sample data and disable certain features.
                                </p>
                            </span>
                        </label>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="currentDemoMode ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                            x-text="currentDemoMode ? 'Enabled' : 'Disabled'"
                        ></span>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <label class="flex items-start cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="registrations_open"
                                class="mt-1 h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            >
                            <span class="ml-3 text-gray-700">
                                <strong>Registrations Open</strong>
                                <p class="text-sm text-gray-600 mt-1">
                                    Allow new users to register accounts.
                                </p>
                            </span>
                        </label>
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="currentRegistrationsOpen ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'"
                            x-text="currentRegistrationsOpen ? 'Open' : 'Closed'"
                        ></span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary" :disabled="!$can_save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Save Settings</span>
                    <span wire:loading>Saving…</span>
                </x-ui.button>
                <x-ui.button
                    type="button"
                    variant="outline"
                    x-on:click.prevent="showConfirm = true"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Confirm</span>
                    <span wire:loading>Processing…</span>
                </x-ui.button>
            </div>

            <div
                x-show="showConfirm"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
                x-on:keydown.escape.window="showConfirm = false"
            >
                <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Confirm Changes</h3>
                        <p class="text-sm text-gray-600 mt-1">Review the impact before applying.</p>
                    </div>
                    <div class="px-6 py-4 space-y-3">
                        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                            <p class="font-medium">Demo Mode</p>
                            <p class="mt-1" x-text="demoMode ? 'Enabled — this will load sample data.' : 'Disabled — this will remove sample data.'"></p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                            <p class="font-medium">Registrations</p>
                            <p class="mt-1" x-text="registrationsOpen ? 'Open — new users can register.' : 'Closed — registration is disabled.'"></p>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t px-6 py-4">
                        <x-ui.button type="button" variant="outline" x-on:click="showConfirm = false">
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            variant="primary"
                            wire:click="confirmApply"
                            x-on:click="showConfirm = false"
                            wire:loading.attr="disabled"
                        >
                            Apply Changes
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    </x-ui.card>
</div>
