<?php

use App\Services\InstallService;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

use function Livewire\Volt\{layout, title};

// Force install layout and prevent Livewire from using app layout
layout('layouts.install');
title('Installation Wizard');

new class extends Component
{
    public int $step = 1;
    public array $checks = [];
    public bool $checksPassed = false;

    // Step 2: Admin user
    #[Validate('required|string|max:255')]
    public string $admin_name = '';

    #[Validate('required|email|unique:users,email')]
    public string $admin_email = '';

    #[Validate('required|string|min:8')]
    public string $admin_password = '';

    #[Validate('required|string|same:admin_password')]
    public string $admin_password_confirmation = '';

    // Step 3: Demo data
    public bool $install_demo_data = false;

    public function mount(InstallService $installService): void
    {
        // Check if installation is already completed
        if ($installService->isInstallationCompleted()) {
            $this->redirect(route('home'), navigate: false);
        }
    }

    public function runChecks(InstallService $installService): void
    {
        $this->checks = $installService->runSystemChecks();
        $this->checksPassed = !in_array(false, $this->checks);
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->runChecks(app(InstallService::class));
            if ($this->checksPassed) {
                $this->step = 2;
            }
        } elseif ($this->step === 2) {
            $this->validate();
            $this->step = 3;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function complete(InstallService $installService): void
    {
        $this->validate();

        try {
            $installService->completeInstallation([
                'admin_name' => $this->admin_name,
                'admin_email' => $this->admin_email,
                'admin_password' => $this->admin_password,
                'install_demo_data' => $this->install_demo_data,
            ]);

            session()->flash('message', 'Installation completed successfully! Please login with your admin credentials.');
            $this->redirect(route('login'), navigate: false);
        } catch (\Exception $e) {
            $this->addError('complete', 'Installation failed: ' . $e->getMessage());
        }
    }
}; ?>

<div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl w-full space-y-8">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-900">Installation Wizard</h1>
            <p class="mt-2 text-gray-600">Welcome! Let's set up your Jobs Board application.</p>
        </div>

        <!-- Progress Steps -->
        <div class="flex items-center justify-center space-x-4 mb-8">
            @foreach([1 => 'Checks', 2 => 'Admin', 3 => 'Complete'] as $stepNum => $stepName)
                <div class="flex items-center">
                    <div class="flex flex-col items-center">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 {{ $step >= $stepNum ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300 text-gray-400' }}">
                            {{ $stepNum }}
                        </div>
                        <span class="mt-2 text-sm font-medium {{ $step >= $stepNum ? 'text-indigo-600' : 'text-gray-400' }}">
                            {{ $stepName }}
                        </span>
                    </div>
                    @if($stepNum < 3)
                        <div class="w-16 h-0.5 {{ $step > $stepNum ? 'bg-indigo-600' : 'bg-gray-300' }} mx-2"></div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Step Content -->
        <x-ui.card padding="p-8">
            @if($step === 1)
                <div>
                    <h2 class="text-2xl font-bold mb-6">System Checks</h2>
                    <p class="text-gray-600 mb-6">Verifying that your system meets the requirements...</p>

                    @if(empty($checks))
                        <div class="text-center py-8">
                            <x-ui.button wire:click="runChecks" variant="primary" size="lg">
                                Run System Checks
                            </x-ui.button>
                        </div>
                    @else
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-lg {{ $checks['database'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                                <span class="font-medium">Database Connection</span>
                                @if($checks['database'])
                                    <x-heroicon-o-check-circle class="h-6 w-6 text-green-600" />
                                @else
                                    <x-heroicon-o-x-circle class="h-6 w-6 text-red-600" />
                                @endif
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-lg {{ $checks['storage'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                                <span class="font-medium">Storage Writable</span>
                                @if($checks['storage'])
                                    <x-heroicon-o-check-circle class="h-6 w-6 text-green-600" />
                                @else
                                    <x-heroicon-o-x-circle class="h-6 w-6 text-red-600" />
                                @endif
                            </div>

                            <div class="flex items-center justify-between p-4 rounded-lg {{ $checks['cache'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                                <span class="font-medium">Cache Driver</span>
                                @if($checks['cache'])
                                    <x-heroicon-o-check-circle class="h-6 w-6 text-green-600" />
                                @else
                                    <x-heroicon-o-x-circle class="h-6 w-6 text-red-600" />
                                @endif
                            </div>
                        </div>

                        @if($checksPassed)
                            <div class="mt-6">
                                <x-ui.button wire:click="nextStep" variant="primary" size="lg" class="w-full">
                                    Continue to Admin Setup
                                </x-ui.button>
                            </div>
                        @else
                            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <p class="text-yellow-800">Please fix the issues above before continuing.</p>
                            </div>
                        @endif
                    @endif
                </div>

            @elseif($step === 2)
                <div>
                    <h2 class="text-2xl font-bold mb-6">Create Admin User</h2>
                    <p class="text-gray-600 mb-6">Create the first administrator account for your application.</p>

                    <form wire:submit="nextStep" class="space-y-6">
                        <x-ui.input
                            label="Admin Name"
                            name="admin_name"
                            wire:model="admin_name"
                            placeholder="Enter admin name"
                            required
                        />

                        <x-ui.input
                            label="Email Address"
                            name="admin_email"
                            type="email"
                            wire:model="admin_email"
                            placeholder="admin@example.com"
                            required
                        />

                        <x-ui.input
                            label="Password"
                            name="admin_password"
                            type="password"
                            wire:model="admin_password"
                            placeholder="Minimum 8 characters"
                            required
                        />

                        <x-ui.input
                            label="Confirm Password"
                            name="admin_password_confirmation"
                            type="password"
                            wire:model="admin_password_confirmation"
                            required
                        />

                        <div class="flex gap-4">
                            <x-ui.button type="button" wire:click="previousStep" variant="outline" class="flex-1">
                                Back
                            </x-ui.button>
                            <x-ui.button type="submit" variant="primary" class="flex-1">
                                Continue
                            </x-ui.button>
                        </div>
                    </form>
                </div>

            @elseif($step === 3)
                <div>
                    <h2 class="text-2xl font-bold mb-6">Complete Installation</h2>
                    <p class="text-gray-600 mb-6">Final step: choose whether to install demo data.</p>

                    <div class="space-y-6">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="install_demo_data"
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                >
                                <span class="ml-3 text-gray-700">
                                    <strong>Install Demo Data</strong>
                                    <p class="text-sm text-gray-600 mt-1">
                                        This will create sample users, job postings, and applications for testing.
                                    </p>
                                </span>
                            </label>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="font-semibold mb-2">Admin Account Summary:</h3>
                            <ul class="text-sm text-gray-700 space-y-1">
                                <li><strong>Name:</strong> {{ $admin_name }}</li>
                                <li><strong>Email:</strong> {{ $admin_email }}</li>
                            </ul>
                        </div>

                        <div class="flex gap-4">
                            <x-ui.button type="button" wire:click="previousStep" variant="outline" class="flex-1">
                                Back
                            </x-ui.button>
                            <x-ui.button wire:click="complete" variant="primary" class="flex-1">
                                Complete Installation
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            @endif
        </x-ui.card>
    </div>
</div>
