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

    public function mount(): void
    {
        $this->demo_mode = Setting::getBool('demo_mode', false);
        $this->registrations_open = Setting::getBool('registrations_open', true);
    }

    public function save(): void
    {
        $this->authorize('admin.settings.update');
        $this->validate();

        $demoModeBefore = Setting::getBool('demo_mode', false);
        $registrationsOpenBefore = Setting::getBool('registrations_open', true);

        Setting::setBool('demo_mode', $this->demo_mode);
        Setting::setBool('registrations_open', $this->registrations_open);

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

    public function clearCache(): void
    {
        $this->authorize('admin.system.cache_clear');

        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('view:clear');

        app(\App\Services\AuditLogger::class)->logBusinessEvent(
            eventType: 'system.cache_cleared',
            request: request(),
            targetType: 'system',
            targetIdcode: null,
            meta: [
                'commands' => ['cache:clear', 'config:clear', 'view:clear'],
            ]
        );

        session()->flash('message', 'Cache cleared successfully!');
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">System Settings</h1>
    </div>

    <x-ui.card padding="p-8">
        <form wire:submit="save" class="space-y-6">
            <div>
                <label class="flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="demo_mode"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    >
                    <span class="ml-3 text-gray-700">
                        <strong>Demo Mode</strong>
                        <p class="text-sm text-gray-600 mt-1">
                            Enable demo mode to show sample data and disable certain features.
                        </p>
                    </span>
                </label>
            </div>

            <div>
                <label class="flex items-center cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="registrations_open"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    >
                    <span class="ml-3 text-gray-700">
                        <strong>Registrations Open</strong>
                        <p class="text-sm text-gray-600 mt-1">
                            Allow new users to register accounts.
                        </p>
                    </span>
                </label>
            </div>

            <div class="flex gap-4">
                <x-ui.button type="submit" variant="primary">
                    Save Settings
                </x-ui.button>
                <x-ui.button type="button" wire:click="clearCache" variant="outline">
                    Clear Cache
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>
