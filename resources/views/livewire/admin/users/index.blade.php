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
    public ?string $confirmingUserDeletion = null;

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
                                            variant="warning"
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

                                    <x-ui.button
                                        wire:click="confirmUserDeletion('{{ $user->id }}')"
                                        variant="danger"
                                        size="sm"
                                        class="text-white"
                                    >
                                        Delete
                                    </x-ui.button>
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
            class="w-full max-w-lg rounded-xl bg-white shadow-2xl"
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
                    <div class="rounded-full bg-red-100 p-2 shrink-0">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Confirm Deletion</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Are you sure you want to delete this user? This action cannot be undone.
                            <span class="block mt-2 font-medium text-red-600">All user data will be permanently removed.</span>
                        </p>
                        @error('delete') 
                            <p class="mt-2 text-sm font-bold text-red-600">{{ $message }}</p> 
                        @enderror
                    </div>
                </div>
            </div>
    
            <div class="flex justify-end gap-x-4 border-t bg-gray-50 px-6 py-4 rounded-b-xl">
                <x-ui.button variant="outline" type="button" x-on:click="show = null">Cancel</x-ui.button>
                <x-ui.button variant="danger" type="button" wire:click="deleteUser">Delete User</x-ui.button>
            </div>
        </div>
    </div>
</div>
