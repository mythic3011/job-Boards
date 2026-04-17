<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Password;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Users');

new class extends Component
{
    use WithPagination;

    private const PAGE_SIZE = 15;

    public string $search = '';
    public string $roleFilter = '';
    public int $visibleCount = self::PAGE_SIZE;
    public ?string $confirmingUserDeletion = null;
    public ?string $resetUrl = null;
    public ?string $resetUserName = null;

    public function updatedSearch(): void
    {
        $this->resetInfinitePagination();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetInfinitePagination();
    }

    public function with(): array
    {
        $query = User::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('login_id', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('nickname', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->role($this->roleFilter);
        }

        return [
            'users' => $query->with('roles')->latest()->paginate($this->visibleCount),
            'roles' => \Spatie\Permission\Models\Role::all(),
            'stats' => [
                'total_users' => User::count(),
                'admin_users' => User::where('user_type', 'admin')->count(),
                'locked_users' => User::whereNotNull('locked_until')->where('locked_until', '>', now())->count(),
                'two_factor_users' => User::whereNotNull('two_factor_confirmed_at')->count(),
            ],
        ];
    }

    public function forcePasswordReset(string $userId): void
    {
        $this->authorize('admin.users.force_password_reset');

        $user = User::findOrFail($userId);

        $token = Password::broker()->createToken($user);

        // Mark this token as admin-initiated so the reset form skips 2FA check
        \Illuminate\Support\Facades\Cache::put('admin_reset:' . $token, true, now()->addMinutes(60));

        $this->resetUrl = url('/reset-password/' . $token) . '?' . http_build_query(['email' => $user->email]);
        $this->resetUserName = $user->nickname;

        app(\App\Services\AuditLogger::class)->logBusinessEvent(
            eventType: 'user.force_password_reset',
            request: request(),
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'user_email' => $user->email,
                'admin_id' => auth()->id(),
                'admin_initiated' => true,
            ]
        );
    }

    public function lockUser(string $userId): void
    {
        $this->authorize('admin.users.lock');

        $user = User::findOrFail($userId);
        $before = $user->locked_until?->toDateTimeString();
        
        $user->update(['locked_until' => now()->addDays(30)]);

        app(\App\Services\DashboardService::class)->clearCache();

        $this->dispatch('$refresh');
        
        app(\App\Services\AuditLogger::class)->logBusinessEvent(
            eventType: 'user.locked',
            request: request(),
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'before' => $before,
                'after' => $user->locked_until->toDateTimeString(),
                'locked_until' => $user->locked_until->toDateTimeString(),
                'user_email' => $user->email,
                'user_nickname' => $user->nickname,
            ]
        );

        session()->flash('message', 'User locked successfully.');
    }

    public function unlockUser(string $userId): void
    {
        $this->authorize('admin.users.unlock');

        $user = User::findOrFail($userId);
        $before = $user->locked_until?->toDateTimeString();
        
        $user->update(['locked_until' => null]);

        app(\App\Services\DashboardService::class)->clearCache();

        $this->dispatch('$refresh');
        
        app(\App\Services\AuditLogger::class)->logBusinessEvent(
            eventType: 'user.unlocked',
            request: request(),
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'before' => $before,
                'after' => null,
                'user_email' => $user->email,
                'user_nickname' => $user->nickname,
            ]
        );

        session()->flash('message', 'User unlocked successfully.');
    }

    public function toggleLock(string $userId): void
    {
        $user = User::findOrFail($userId);

        if ($user->isLocked()) {
            $this->unlockUser($userId);
            return;
        }

        $this->lockUser($userId);
    }

    public function confirmUserDeletion(string $userId): void
    {
       $this->confirmingUserDeletion = $userId;
    }

    public function deleteUser(): void
    {
        // Add permission check
        $this->authorize('admin.users.delete'); 
        
        if (! $this->confirmingUserDeletion) {
             return;
        }

        $user = User::findOrFail($this->confirmingUserDeletion);

        // Prevent deleting self
        if ($user->id === auth()->id()) {
             $this->addError('delete', 'You cannot delete your own account.');
             $this->confirmingUserDeletion = null;
             return;
        }

        app(\App\Services\AuditLogger::class)->logBusinessEvent(
            eventType: 'user.deleted',
            request: request(),
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'user_email' => $user->email,
                'user_nickname' => $user->nickname,
                'deleted_at' => now()->toDateTimeString(),
            ]
        );

        $user->delete();

        app(\App\Services\DashboardService::class)->clearCache();
        $this->confirmingUserDeletion = null;
        $this->dispatch('$refresh');
        
        session()->flash('message', 'User deleted successfully.');
    }

    public function loadMore(): void
    {
        $this->visibleCount += self::PAGE_SIZE;
    }

    private function resetInfinitePagination(): void
    {
        $this->visibleCount = self::PAGE_SIZE;
        $this->resetPage();
    }
}; ?>

@php
    $activeFilterCount = collect([$search, $roleFilter])->filter(fn ($value) => filled($value))->count();
@endphp

<div class="space-y-8">
    <div class="theme-hero-surface rounded-3xl border px-6 py-7 sm:px-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="theme-hero-eyebrow text-xs font-semibold uppercase tracking-[0.18em]">Admin Users</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight">User operations</h1>
                <p class="theme-text-muted mt-3 text-sm leading-6">
                    Search the user base, review access posture, and take account actions without leaving the directory.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 lg:min-w-[420px]">
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Total Users</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['total_users']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">All accounts currently on the platform.</p>
                </div>
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">2FA Enabled</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['two_factor_users']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Accounts with confirmed authenticator setup.</p>
                </div>
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Locked Accounts</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['locked_users']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Users currently prevented from signing in.</p>
                </div>
                <div class="theme-hero-card rounded-2xl border px-4 py-4">
                    <p class="theme-text-muted text-xs uppercase tracking-[0.16em]">Admin Accounts</p>
                    <p class="mt-3 text-3xl font-semibold">{{ number_format($stats['admin_users']) }}</p>
                    <p class="theme-text-muted mt-2 text-sm">Accounts with elevated platform access.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
        <div class="space-y-6">
            <div class="theme-panel rounded-2xl border p-5 shadow-sm">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <x-ui.section-label class="mb-2">Directory</x-ui.section-label>
                        <p class="theme-text-muted text-sm">Search the user base by login ID, email, or display name.</p>
                    </div>
                    <div class="theme-pill inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold">
                        <span>{{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</span>
                        @if($activeFilterCount > 0)
                            <span class="theme-panel rounded-full border px-2 py-0.5 text-[11px] font-semibold">
                                {{ $activeFilterCount }} filter{{ $activeFilterCount > 1 ? 's' : '' }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <div class="relative min-w-64 flex-1">
                        <div class="theme-input-shell flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all duration-150">
                            <svg class="theme-text-muted h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search by username, email, or name"
                                class="theme-input flex-1 min-w-0 border-0 bg-transparent px-0 py-0 text-sm shadow-none outline-none"
                                autocomplete="off"
                            />
                            @if($search)
                                <button wire:click="$set('search', '')" class="theme-text-muted shrink-0 rounded-full p-0.5 transition-colors hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)] cursor-pointer" aria-label="Clear search">
                                    <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    <select wire:model.live="roleFilter" class="theme-input shrink-0 rounded-lg border px-3 py-2.5 text-sm shadow-sm outline-none transition-all duration-150 cursor-pointer">
                        <option value="">All Roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="theme-table-shell rounded-2xl border shadow-sm">
                <div class="theme-table-head flex items-center justify-between border-b px-6 py-4">
                    <h2 class="theme-text-muted text-sm font-semibold uppercase tracking-wider">User Directory</h2>
                    <span class="theme-panel rounded-full border px-3 py-0.5 text-xs font-semibold">
                        {{ $users->count() }} on this page
                    </span>
                </div>

                <div class="overflow-x-auto overflow-y-visible">
                    <table class="min-w-[760px] w-full table-fixed">
                        <colgroup>
                            <col class="w-[36%]">
                            <col class="w-[24%]">
                            <col class="w-[22%]">
                            <col class="w-[18%]">
                        </colgroup>
                        <thead>
                            <tr class="theme-table-head theme-table-divider border-b">
                                <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Account</th>
                                <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Role & Access</th>
                                <th class="theme-text-muted px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider">Security</th>
                                <th class="theme-text-muted px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="--tw-divide-opacity: 1; border-color: var(--app-panel-border);">
                            @forelse($users as $user)
                                <tr wire:key="user-{{ $user->id }}" class="group transition-colors duration-150 hover:bg-[var(--app-panel-subtle-bg)]">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <x-ui.avatar
                                                :src="$user->profile_image_path ? app(\App\Services\ProfileImageService::class)->getImageUrl($user->profile_image_path) : null"
                                                :name="$user->nickname"
                                                size="sm"
                                                class="shrink-0 border border-[var(--app-panel-border)]"
                                            />
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <div class="theme-text-strong truncate text-sm font-semibold">{{ $user->nickname }}</div>
                                                    @if($user->user_type === 'admin')
                                                        <span class="theme-pill inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide">
                                                            Admin
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="theme-text-muted truncate text-sm">{{ $user->email }}</div>
                                                <div class="theme-text-muted mt-1 text-xs font-mono">{{ $user->login_id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="space-y-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach($user->roles as $role)
                                                    <span class="theme-pill inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                                                        {{ $role->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                            <div>
                                                @if($user->isLocked())
                                                    <span class="theme-alert-error inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                                        Locked
                                                    </span>
                                                @else
                                                    <span class="theme-alert-success inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                                        Active
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <div class="space-y-2">
                                            @if($user->two_factor_confirmed_at)
                                                <span class="theme-alert-success inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                                    <x-heroicon-o-shield-check class="h-4 w-4" />
                                                    Enabled
                                                </span>
                                            @else
                                                <span class="theme-alert-warning inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium">
                                                    <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                                                    Not enabled
                                                </span>
                                            @endif
                                            <p class="theme-text-muted text-xs">2FA posture for sign-in review.</p>
                                        </div>
                                    </td>
                                    <td class="overflow-visible px-6 py-4 align-top text-right text-sm font-medium">
                                        <div class="flex justify-end">
                                            <div class="relative" data-dropdown data-open="false">
                                                <button
                                                    type="button"
                                                    class="theme-dropdown-trigger inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                                                    data-dropdown-button
                                                    aria-expanded="false"
                                                    aria-haspopup="true"
                                                >
                                                    <span>Quick actions</span>
                                                    <svg class="h-4 w-4 transition-transform duration-200" data-dropdown-arrow fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
                                                    </svg>
                                                </button>

                                                <div
                                                    class="theme-dropdown-panel absolute right-0 top-full z-[100] mt-3 w-56 rounded-2xl border p-2 text-left opacity-0 translate-y-1 scale-[0.98] pointer-events-none transition-all duration-150 ease-out"
                                                    data-dropdown-menu
                                                    data-dropdown-panel
                                                >
                                                    <div class="theme-table-divider border-b px-3 py-2">
                                                        <p class="theme-text-muted text-[11px] font-semibold uppercase tracking-[0.16em]">User operations</p>
                                                        <p class="theme-text-strong mt-1 truncate text-sm font-semibold">{{ $user->nickname }}</p>
                                                    </div>
                                                    <div class="space-y-1 p-2">
                                                        @if($user->isLocked())
                                                            <button
                                                                type="button"
                                                                wire:click="toggleLock('{{ $user->id }}')"
                                                                wire:loading.attr="disabled"
                                                                wire:target="toggleLock('{{ $user->id }}')"
                                                                class="theme-text-strong flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)] disabled:opacity-60"
                                                            >
                                                                <span wire:loading.remove wire:target="toggleLock('{{ $user->id }}')">Unlock user</span>
                                                                <span wire:loading wire:target="toggleLock('{{ $user->id }}')">Processing…</span>
                                                            </button>
                                                        @else
                                                        <button
                                                            type="button"
                                                            wire:click="toggleLock('{{ $user->id }}')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="toggleLock('{{ $user->id }}')"
                                                            class="theme-alert-warning flex w-full items-center justify-between rounded-xl border px-3 py-2 text-left text-sm transition-colors hover:brightness-95 disabled:opacity-60"
                                                        >
                                                            <span wire:loading.remove wire:target="toggleLock('{{ $user->id }}')">Lock user</span>
                                                            <span wire:loading wire:target="toggleLock('{{ $user->id }}')">Processing…</span>
                                                            </button>
                                                        @endif

                                                        <button
                                                            type="button"
                                                            wire:click="forcePasswordReset('{{ $user->id }}')"
                                                            wire:loading.attr="disabled"
                                                            wire:target="forcePasswordReset('{{ $user->id }}')"
                                                            class="theme-text-strong flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-sm transition-colors hover:bg-[var(--app-panel-subtle-bg)] disabled:opacity-60"
                                                        >
                                                            <span wire:loading.remove wire:target="forcePasswordReset('{{ $user->id }}')">Reset password</span>
                                                            <span wire:loading wire:target="forcePasswordReset('{{ $user->id }}')">Preparing…</span>
                                                        </button>

                                                        <button
                                                            type="button"
                                                            wire:click="confirmUserDeletion('{{ $user->id }}')"
                                                            class="theme-alert-error flex w-full items-center justify-between rounded-xl border px-3 py-2 text-left text-sm transition-colors hover:brightness-95"
                                                        >
                                                            <span>Delete user</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-20 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="theme-icon-tile rounded-full p-4">
                                                <svg class="theme-text-muted h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 11-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275" />
                                                </svg>
                                            </div>
                                            <p class="theme-text-strong text-sm font-semibold">No users found</p>
                                            <p class="theme-text-muted text-xs">Try adjusting your search or role filter.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.infinite-scroll-pagination
                    :paginator="$users"
                    action="loadMore"
                    label="user"
                    key-name="admin-users"
                />
            </div>
        </div>

        <div class="space-y-6">
            <div>
                <div class="mb-4">
                    <x-ui.section-label class="mb-2">Access & Risk</x-ui.section-label>
                    <p class="theme-text-muted text-sm">High-level account posture before you drill into the directory.</p>
                </div>
                <x-ui.card class="space-y-4">
                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">2FA Enabled</p>
                            <p class="theme-text-strong text-2xl font-semibold">{{ number_format($stats['two_factor_users']) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Users who have confirmed authenticator-based sign-in protection.</p>
                    </div>

                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">Locked Accounts</p>
                            <p class="text-2xl font-semibold {{ $stats['locked_users'] > 0 ? 'theme-signal-warning' : 'theme-text-strong' }}">{{ number_format($stats['locked_users']) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Accounts currently blocked from sign-in and likely needing moderation review.</p>
                    </div>

                    <div class="theme-panel-subtle rounded-2xl border px-4 py-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="theme-text-strong text-sm font-medium">Admin Accounts</p>
                            <p class="theme-text-strong text-2xl font-semibold">{{ number_format($stats['admin_users']) }}</p>
                        </div>
                        <p class="theme-text-muted mt-2 text-sm">Accounts with elevated permissions on the platform.</p>
                    </div>
                </x-ui.card>
            </div>

            <x-ui.card>
                <h2 class="theme-text-strong text-lg font-semibold">Operator Notes</h2>
                <div class="theme-text-muted mt-4 space-y-3 text-sm">
                    <p>Use <span class="theme-text-strong font-medium">Lock</span> for temporary access suspension and <span class="theme-text-strong font-medium">Reset Password</span> only when you control the handoff channel.</p>
                    <p>Admin-initiated reset links bypass email delivery. Treat the copied URL as sensitive until it expires or is used.</p>
                    <p>Deletion is permanent and should only be used when moderation or data-retention policy explicitly allows it.</p>
                </div>
            </x-ui.card>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
        x-data="{ show: @entangle('confirmingUserDeletion') }"
        x-show="show"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
        x-on:keydown.escape.window="show = null"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div 
            class="theme-modal-surface w-full max-w-lg rounded-xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="show = null"
        >
            <div class="space-y-4 px-6 py-6">
                <div class="flex items-start gap-4">
                    <div class="theme-alert-error shrink-0 rounded-full border p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="theme-text-strong text-lg font-medium">Confirm Deletion</h3>
                        <p class="theme-text-muted mt-2 text-sm">
                            Are you sure you want to delete this user? This action cannot be undone.
                            <span class="theme-alert-error mt-2 inline-flex rounded-full border px-2 py-0.5 font-medium">All user data will be permanently removed.</span>
                        </p>
                        @error('delete') 
                            <p class="theme-alert-error mt-2 inline-flex rounded-lg border px-3 py-2 text-sm font-bold">{{ $message }}</p> 
                        @enderror
                    </div>
                </div>
            </div>
    
            <div class="theme-table-head flex justify-end gap-x-4 border-t px-6 py-4 rounded-b-xl">
                <x-ui.button variant="outline" type="button" x-on:click="show = null">Cancel</x-ui.button>
                <x-ui.button variant="danger" type="button" wire:click="deleteUser">Delete User</x-ui.button>
            </div>
        </div>
    </div>

    <!-- Reset Password URL Modal -->
    <div
        x-data="{ show: @entangle('resetUrl'), copied: false }"
        x-show="show"
        x-cloak
        style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-sm"
        x-on:keydown.escape.window="$wire.set('resetUrl', null)"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            class="theme-modal-surface w-full max-w-lg rounded-xl"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.outside="$wire.set('resetUrl', null)"
        >
            <div class="px-6 py-6 space-y-4">
                <div class="flex items-start gap-4">
                    <div class="theme-alert-info shrink-0 rounded-full border p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="theme-text-strong text-lg font-medium">Password Reset Link</h3>
                        <p class="theme-text-muted mt-1 text-sm">
                            Share this link with <span class="theme-text-strong font-medium">{{ $resetUserName }}</span>. It expires after use or 60 minutes.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ $resetUrl }}"
                        class="theme-input flex-1 truncate rounded-lg border px-3 py-2 text-xs font-mono"
                        x-ref="resetUrlInput"
                        @click="$refs.resetUrlInput.select()"
                    >
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText('{{ $resetUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="theme-input shrink-0 min-w-[64px] rounded-lg border px-3 py-2 text-center text-xs font-medium transition-colors hover:bg-[var(--app-panel-subtle-bg)]"
                    >
                        <span x-show="!copied">Copy</span>
                        <span x-show="copied" class="theme-text-strong">Copied!</span>
                    </button>
                </div>

                <p class="theme-alert-warning rounded-lg border px-3 py-2 text-xs">
                    This link bypasses email. Do not share it publicly.
                </p>
            </div>

            <div class="theme-table-head flex justify-end border-t px-6 py-4 rounded-b-xl">
                <x-ui.button variant="outline" type="button" wire:click="$set('resetUrl', null)">Close</x-ui.button>
            </div>
        </div>
    </div>
</div>
