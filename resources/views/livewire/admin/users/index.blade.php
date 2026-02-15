<?php

use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Users');

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $roleFilter = '';

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
            'users' => $query->with('roles')->latest()->paginate(15),
            'roles' => \Spatie\Permission\Models\Role::all(),
        ];
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
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">User Management</h1>
    </div>

    <!-- Filters -->
    <x-ui.card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-ui.input
                label="Search"
                name="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by username, email, or name"
            />

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Role</label>
                <select wire:model.live="roleFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-ui.card>

    <!-- Users Table -->
    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">2FA</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $user->nickname }}</div>
                                    <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->isLocked())
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Locked
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->two_factor_confirmed_at)
                                    <x-heroicon-o-shield-check class="h-5 w-5 text-green-600" title="2FA Enabled" />
                                @else
                                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-600" title="2FA Not Enabled" />
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-2">
                                    @if($user->isLocked())
                                        <x-ui.button
                                            wire:click="toggleLock('{{ $user->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleLock('{{ $user->id }}')"
                                            variant="outline"
                                            size="sm"
                                        >
                                            <span wire:loading.remove wire:target="toggleLock('{{ $user->id }}')">Unlock</span>
                                            <span wire:loading wire:target="toggleLock('{{ $user->id }}')" class="inline-flex items-center gap-2">
                                                <svg class="h-4 w-4 animate-spin text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                                </svg>
                                                Processing
                                            </span>
                                        </x-ui.button>
                                    @else
                                        <x-ui.button
                                            wire:click="toggleLock('{{ $user->id }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleLock('{{ $user->id }}')"
                                            variant="danger"
                                            size="sm"
                                        >
                                            <span wire:loading.remove wire:target="toggleLock('{{ $user->id }}')">Lock</span>
                                            <span wire:loading wire:target="toggleLock('{{ $user->id }}')" class="inline-flex items-center gap-2">
                                                <svg class="h-4 w-4 animate-spin text-red-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                                </svg>
                                                Processing
                                            </span>
                                        </x-ui.button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </x-ui.card>
</div>
