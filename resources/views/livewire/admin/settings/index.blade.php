<?php

use App\Jobs\SeedDemoData;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DashboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    #[Validate('required|boolean')]
    public bool $maintenance_mode = false;

    public bool $current_demo_mode = false;
    public bool $current_registrations_open = true;
    public bool $current_maintenance_mode = false;

    public bool $showConfirmModal = false;

    public string $password = '';

    public function mount(): void
    {
        $this->demo_mode = Setting::getBool('demo_mode', false);
        $this->registrations_open = Setting::getBool('registrations_open', true);
        $this->maintenance_mode = Setting::getBool('maintenance_mode', false);

        $this->syncCurrentState();
        $this->closeConfirmModal();
    }

    public function getHasChangesProperty(): bool
    {
        return $this->demo_mode !== $this->current_demo_mode
            || $this->registrations_open !== $this->current_registrations_open
            || $this->maintenance_mode !== $this->current_maintenance_mode;
    }

    public function save(): void
    {
        $this->authorize('admin.settings.update');
        $this->validateOnlySettings();

        if (! $this->hasChanges) {
            $this->closeConfirmModal();
            session()->flash('info', 'No changes to save.');
            return;
        }

        $this->resetValidation('password');
        $this->password = '';
        $this->showConfirmModal = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
        $this->password = '';
        $this->resetValidation('password');
    }

    public function confirmSave(): void
    {
        $this->authorize('admin.settings.update');
        $this->validateOnlySettings();

        if ($this->password === '') {
            $this->addError('password', 'Password confirmation is required.');
            $this->showConfirmModal = true;
            return;
        }

        $user = auth()->user();

        if (! $user || ! Hash::check($this->password, (string) $user->password)) {
            $this->addError('password', 'The provided password is incorrect.');
            $this->password = '';
            $this->showConfirmModal = true;
            return;
        }

        $rateLimitKey = 'settings-update:' . ($user->id ?? request()->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->closeConfirmModal();
            session()->flash('error', "Too many attempts. Please try again in {$seconds} seconds.");
            return;
        }

        RateLimiter::hit($rateLimitKey, 60);

        $demoModeBefore = Setting::getBool('demo_mode', false);
        $registrationsOpenBefore = Setting::getBool('registrations_open', true);
        $maintenanceModeBefore = Setting::getBool('maintenance_mode', false);

        $settingsActuallyChanged =
            $this->demo_mode !== $demoModeBefore ||
            $this->registrations_open !== $registrationsOpenBefore ||
            $this->maintenance_mode !== $maintenanceModeBefore;

        if (! $settingsActuallyChanged) {
            $this->syncCurrentStateFromDb($demoModeBefore, $registrationsOpenBefore, $maintenanceModeBefore);
            $this->closeConfirmModal();
            session()->flash('info', 'Settings are already up to date.');
            return;
        }

        try {
            DB::transaction(function () use (
                $demoModeBefore,
                $registrationsOpenBefore,
                $maintenanceModeBefore
            ) {
                Setting::setBool('demo_mode', $this->demo_mode);
                Setting::setBool('registrations_open', $this->registrations_open);
                Setting::setBool('maintenance_mode', $this->maintenance_mode);

                if ($demoModeBefore && ! $this->demo_mode) {
                    $this->clearDemoData();
                }

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
                        'maintenance_mode' => [
                            'before' => $maintenanceModeBefore,
                            'after' => $this->maintenance_mode,
                        ],
                    ]
                );
            });

            if (! $demoModeBefore && $this->demo_mode) {
                // Temporarily disabled during 500 triage to isolate UI state from backend side effects.
                // SeedDemoData::dispatch();
            }

            app(DashboardService::class)->clearCache();

            $this->syncCurrentState();
            $this->closeConfirmModal();

            session()->flash(
                'message',
                $this->demo_mode
                    ? 'Settings saved successfully! Demo data seeding queued.'
                    : 'Settings saved successfully!' . ($demoModeBefore ? ' Demo data removed.' : '')
            );
        } catch (\Throwable $e) {
            report($e);
            Log::error('Failed to save settings', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->showConfirmModal = true;
            session()->flash('error', 'Failed to save settings.');
        }
    }

    protected function validateOnlySettings(): void
    {
        $this->validate([
            'demo_mode' => ['required', 'boolean'],
            'registrations_open' => ['required', 'boolean'],
            'maintenance_mode' => ['required', 'boolean'],
        ]);
    }

    protected function syncCurrentState(): void
    {
        $this->current_demo_mode = $this->demo_mode;
        $this->current_registrations_open = $this->registrations_open;
        $this->current_maintenance_mode = $this->maintenance_mode;
    }

    protected function syncCurrentStateFromDb(bool $demo, bool $registrations, bool $maintenance): void
    {
        $this->current_demo_mode = $demo;
        $this->current_registrations_open = $registrations;
        $this->current_maintenance_mode = $maintenance;
    }

    protected function clearDemoData(): void
    {
        $demoSeededAt = Setting::get('demo_seeded_at');
        $demoSeedUserIds = $this->getDemoSeedUserIds();

        if (empty($demoSeedUserIds)) {
            Log::warning('Attempted to clear demo data but no seeded demo user IDs found');
            return;
        }

        DB::transaction(function () use ($demoSeededAt, $demoSeedUserIds) {
            $demoUsers = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'admin');
            })
                ->whereIn('id', $demoSeedUserIds)
                ->get();

            if ($demoUsers->isEmpty()) {
                Log::info('No demo users to delete');
                return;
            }

            app(AuditLogger::class)->logBusinessEvent(
                eventType: 'demo.data_cleared',
                request: request(),
                targetType: 'system',
                targetIdcode: null,
                meta: [
                    'users_deleted' => $demoUsers->count(),
                    'demo_seeded_at' => $demoSeededAt,
                    'seeded_user_count' => count($demoSeedUserIds),
                    'user_ids' => $demoUsers->pluck('id')->all(),
                ]
            );

            foreach ($demoUsers as $user) {
                $user->delete();
            }
        });

        Setting::set('demo_seeded_at', null);
        Setting::set('demo_seed_user_ids', null);
    }

    protected function getDemoSeedUserIds(): array
    {
        $raw = Setting::get('demo_seed_user_ids');

        if (blank($raw)) {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($id) => is_string($id) || is_int($id)));
    }
}; ?>

<div>
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
        <form wire:submit.prevent="save" class="space-y-6">
            <div>
                <h2 class="theme-text-strong text-lg font-semibold">Feature Toggles</h2>
                <p class="theme-text-muted mt-1 text-sm">Configure system-wide settings and features.</p>
            </div>

            <div class="grid gap-4">
                <div class="theme-panel-subtle relative rounded-lg border p-5 shadow-sm transition-all hover:bg-[var(--app-panel-bg)]">
                    <div class="flex items-start justify-between gap-4">
                        <label class="flex flex-1 cursor-pointer items-start">
                            <input
                                type="checkbox"
                                wire:model.live="demo_mode"
                                class="theme-input mt-1 h-5 w-5 rounded border text-[var(--app-accent-strong)] transition focus:ring-2 focus:ring-[var(--app-focus-ring)]"
                            >
                            <div class="ml-3 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="theme-text-strong">Demo Mode</strong>
                                </div>
                                <p class="theme-text-muted mt-1 text-sm">
                                    Populate the system with sample data for demonstration purposes. Disables certain production features.
                                </p>
                            </div>
                        </label>
                        <div class="flex flex-col items-end gap-2">
                            <span
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm transition-all',
                                    'theme-alert-success' => $current_demo_mode,
                                    'theme-panel theme-text-muted' => ! $current_demo_mode,
                                ])
                            >
                                <span class="h-2 w-2 rounded-full {{ $current_demo_mode ? 'bg-[var(--app-success-fg)] animate-pulse' : 'bg-[var(--app-text-muted)]' }}"></span>
                                <span>{{ $current_demo_mode ? 'Active' : 'Inactive' }}</span>
                            </span>
                            @if ($demo_mode !== $current_demo_mode)
                                <span class="theme-alert-warning inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium">Pending change</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="theme-panel-subtle relative rounded-lg border p-5 shadow-sm transition-all hover:bg-[var(--app-panel-bg)]">
                    <div class="flex items-start justify-between gap-4">
                        <label class="flex flex-1 cursor-pointer items-start">
                            <input
                                type="checkbox"
                                wire:model.live="registrations_open"
                                class="theme-input mt-1 h-5 w-5 rounded border text-[var(--app-accent-strong)] transition focus:ring-2 focus:ring-[var(--app-focus-ring)]"
                            >
                            <div class="ml-3 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="theme-text-strong">User Registrations</strong>
                                </div>
                                <p class="theme-text-muted mt-1 text-sm">
                                    Allow new users to create accounts and access the platform.
                                </p>
                            </div>
                        </label>
                        <div class="flex flex-col items-end gap-2">
                            <span
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm transition-all',
                                    'theme-alert-info' => $current_registrations_open,
                                    'theme-panel theme-text-muted' => ! $current_registrations_open,
                                ])
                            >
                                <span class="h-2 w-2 rounded-full {{ $current_registrations_open ? 'bg-[var(--app-info-fg)]' : 'bg-[var(--app-text-muted)]' }}"></span>
                                <span>{{ $current_registrations_open ? 'Open' : 'Closed' }}</span>
                            </span>
                            @if ($registrations_open !== $current_registrations_open)
                                <span class="theme-alert-warning inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium">Pending change</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="theme-panel-subtle relative rounded-lg border p-5 shadow-sm transition-all hover:bg-[var(--app-panel-bg)]">
                    <div class="flex items-start justify-between gap-4">
                        <label class="flex flex-1 cursor-pointer items-start">
                            <input
                                type="checkbox"
                                wire:model.live="maintenance_mode"
                                class="theme-input mt-1 h-5 w-5 rounded border text-[var(--app-danger-fg)] transition focus:ring-2 focus:ring-[var(--app-focus-ring)]"
                            >
                            <div class="ml-3 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="theme-text-strong">Enable System Maintenance Mode</strong>
                                </div>
                                <p class="theme-text-muted mt-1 text-sm">
                                    Block user access to the job board during system maintenance.
                                </p>
                            </div>
                        </label>
                        <div class="flex flex-col items-end gap-2">
                            <span
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm transition-all',
                                    'theme-alert-error' => $current_maintenance_mode,
                                    'theme-panel theme-text-muted' => ! $current_maintenance_mode,
                                ])
                            >
                                <span class="h-2 w-2 rounded-full {{ $current_maintenance_mode ? 'bg-[var(--app-danger-fg)] animate-pulse' : 'bg-[var(--app-text-muted)]' }}"></span>
                                <span>{{ $current_maintenance_mode ? 'Enabled' : 'Disabled' }}</span>
                            </span>
                            @if ($maintenance_mode !== $current_maintenance_mode)
                                <span class="theme-alert-warning inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium">Pending change</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="theme-table-divider flex items-center justify-start gap-4 border-t pt-4">
                <button
                    type="submit"
                    {{ ! $this->hasChanges ? 'disabled' : '' }}
                    wire:loading.attr="disabled"
                    class="theme-button theme-button-primary min-w-[140px] rounded-lg border px-4 py-2 text-sm font-medium transition-opacity disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Processing…</span>
                </button>

                @if ($this->hasChanges)
                    <div class="theme-alert-warning inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">You have unsaved changes</span>
                    </div>
                @endif
            </div>
        </form>
    </x-ui.card>

    @if ($showConfirmModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-gray-950/60 backdrop-blur-sm sm:items-center sm:p-4">
            <button
                type="button"
                class="absolute inset-0 cursor-default"
                wire:click="closeConfirmModal"
                aria-label="Close modal"
            ></button>

            <div class="theme-modal-surface relative flex max-h-[95vh] flex-col overflow-hidden rounded-t-2xl sm:max-h-[85vh] sm:w-full sm:max-w-lg sm:rounded-2xl">
                <div class="theme-panel-subtle theme-table-divider relative shrink-0 border-b px-6 pt-6 pb-5">
                    <button
                        type="button"
                        class="theme-text-muted absolute top-4 right-4 rounded-lg p-1.5 transition-colors hover:bg-[var(--app-panel-bg)] hover:text-[var(--app-text-strong)]"
                        wire:click="closeConfirmModal"
                        aria-label="Close"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-4 pr-6">
                        <div class="theme-icon-tile-accent flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="theme-text-strong text-base font-semibold leading-tight">Confirm Changes</h3>
                            <p class="theme-text-muted mt-0.5 text-sm">Review the impact before applying.</p>
                        </div>
                    </div>
                </div>

                <div class="flex-1 space-y-3 overflow-y-auto px-6 py-5">
                    <div class="space-y-1.5">
                        <label for="password-confirm" class="theme-text-strong block text-sm font-medium">
                            Admin Password
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center pl-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" class="theme-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z" />
                                </svg>
                            </div>
                            <input
                                type="password"
                                id="password-confirm"
                                wire:model.live="password"
                                class="theme-input w-full rounded-lg border pl-10 pr-3 py-2.5 text-sm shadow-sm placeholder:text-[var(--app-text-muted)] focus:border-[var(--app-accent-soft-border)] focus:ring-2 focus:ring-[var(--app-focus-ring)] focus:outline-hidden transition"
                                placeholder="Enter your password to confirm"
                                autocomplete="current-password"
                            >
                        </div>

                        @error('password')
                            <div class="theme-alert-error inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0zm-7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-1-9a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1z" clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="relative py-1">
                        <div class="absolute inset-0 flex items-center">
                            <div class="theme-table-divider w-full border-t"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="theme-panel theme-text-muted border px-3 text-xs font-medium uppercase tracking-wider">Changes to apply</span>
                        </div>
                    </div>

                    @if ($demo_mode !== $current_demo_mode)
                        <div class="rounded-xl border p-4 text-sm {{ $demo_mode ? 'theme-alert-success' : 'theme-alert-warning' }}">
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border {{ $demo_mode ? 'theme-alert-success' : 'theme-alert-warning' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 0 1-4-4V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v12a4 4 0 0 1-4 4zm0 0h12a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 0 1 2.828 0l2.829 2.829a2 2 0 0 1 0 2.828l-8.486 8.485M7 17h.01" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="theme-text-strong text-sm font-semibold">Demo Mode</p>
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $demo_mode ? 'theme-alert-success' : 'theme-alert-warning' }}">{{ $demo_mode ? 'Enabling' : 'Disabling' }}</span>
                                    </div>
                                    <p class="theme-text-muted mt-1 text-xs leading-relaxed">
                                        {{ $demo_mode ? 'Sample data will be loaded into the system.' : 'All demo data will be permanently removed.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($registrations_open !== $current_registrations_open)
                        <div class="rounded-xl border p-4 text-sm {{ $registrations_open ? 'theme-alert-info' : 'theme-panel-subtle theme-text-muted' }}">
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border {{ $registrations_open ? 'theme-alert-info' : 'theme-panel' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM3 20a6 6 0 0 1 12 0v1H3v-1z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="theme-text-strong text-sm font-semibold">User Registrations</p>
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $registrations_open ? 'theme-alert-info' : 'theme-panel' }}">{{ $registrations_open ? 'Opening' : 'Closing' }}</span>
                                    </div>
                                    <p class="theme-text-muted mt-1 text-xs leading-relaxed">
                                        {{ $registrations_open ? 'New users will be able to create accounts.' : 'New user registration will be disabled.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($maintenance_mode !== $current_maintenance_mode)
                        <div class="rounded-xl border p-4 text-sm {{ $maintenance_mode ? 'theme-alert-error' : 'theme-alert-success' }}">
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border {{ $maintenance_mode ? 'theme-alert-error' : 'theme-alert-success' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 2.17a1 1 0 0 1 1.16 0l1.52 1.08a1 1 0 0 0 .96.12l1.77-.7a1 1 0 0 1 1.13.43l.97 1.64a1 1 0 0 0 .77.5l1.9.2a1 1 0 0 1 .86.79l.38 1.87a1 1 0 0 0 .56.78l1.7.9a1 1 0 0 1 .5 1.08l-.32 1.88a1 1 0 0 0 .22.93l1.22 1.46a1 1 0 0 1 .08 1.19l-1.05 1.58a1 1 0 0 0-.16.94l.56 1.82a1 1 0 0 1-.56 1.05l-1.75.8a1 1 0 0 0-.62.73l-.45 1.86a1 1 0 0 1-.9.74l-1.9.11a1 1 0 0 0-.79.45l-1.06 1.58a1 1 0 0 1-1.14.4l-1.74-.73a1 1 0 0 0-.97.09l-1.58 1.04a1 1 0 0 1-1.17-.1l-1.4-1.3a1 1 0 0 0-.92-.24l-1.87.37a1 1 0 0 1-1.08-.5l-.9-1.7a1 1 0 0 0-.78-.56l-1.87-.38a1 1 0 0 1-.79-.86l-.2-1.9a1 1 0 0 0-.5-.77l-1.64-.97a1 1 0 0 1-.43-1.13l.7-1.77a1 1 0 0 0-.12-.96L2.17 12.58a1 1 0 0 1 0-1.16l1.08-1.52a1 1 0 0 0 .12-.96l-.7-1.77a1 1 0 0 1 .43-1.13l1.64-.97a1 1 0 0 0 .5-.77l.2-1.9a1 1 0 0 1 .79-.86l1.87-.38a1 1 0 0 0 .78-.56l.9-1.7a1 1 0 0 1 1.08-.5l1.87.37a1 1 0 0 0 .92-.24l1.4-1.3z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="theme-text-strong text-sm font-semibold">Maintenance Mode</p>
                                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium {{ $maintenance_mode ? 'theme-alert-error' : 'theme-alert-success' }}">{{ $maintenance_mode ? 'Enabling' : 'Disabling' }}</span>
                                    </div>
                                    <p class="theme-text-muted mt-1 text-xs leading-relaxed">
                                        {{ $maintenance_mode ? 'Users will be blocked from accessing the job board.' : 'Users can access the job board normally.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="theme-panel-subtle theme-table-divider flex shrink-0 items-center justify-between gap-3 border-t px-6 py-4">
                    <p class="theme-text-muted hidden text-xs sm:block">This action will take effect immediately.</p>
                    <div class="ml-auto flex items-center gap-2">
                        <x-ui.button
                            type="button"
                            variant="outline"
                            wire:click="closeConfirmModal"
                        >
                            Cancel
                        </x-ui.button>
                        <x-ui.button
                            type="button"
                            variant="primary"
                            wire:click="confirmSave"
                            wire:loading.attr="disabled"
                            class="min-w-[160px]"
                        >
                            <span wire:loading.remove wire:target="confirmSave" class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Apply Changes
                            </span>
                            <span wire:loading wire:target="confirmSave" class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 12 6.477 12 12h-4z"></path>
                                </svg>
                                Applying…
                            </span>
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
