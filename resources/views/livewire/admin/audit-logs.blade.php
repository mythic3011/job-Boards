<?php

use App\Models\AuditLog;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Audit Logs');

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $eventTypeFilter = '';
    public string $actorTypeFilter = '';

    public function with(): array
    {
        $query = AuditLog::query()->with('actor');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('event_type', 'like', '%' . $this->search . '%')
                  ->orWhere('ip', 'like', '%' . $this->search . '%')
                  ->orWhere('path', 'like', '%' . $this->search . '%')
                  ->orWhere('target_idcode', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->eventTypeFilter) {
            $query->where('event_type', $this->eventTypeFilter);
        }

        if ($this->actorTypeFilter) {
            $query->where('actor_type', $this->actorTypeFilter);
        }

        return [
            'logs' => $query->orderByDesc('occurred_at')->paginate(25),
            'eventTypes' => AuditLog::select('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEventTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActorTypeFilter(): void
    {
        $this->resetPage();
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-3xl font-bold">Audit Logs</h1>
        <p class="text-gray-600 mt-1">Security and business event history</p>
    </div>

    <!-- Filters -->
    <x-ui.card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-ui.input
                label="Search"
                name="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Event type, IP, path, or target"
            />

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Event Type</label>
                <select wire:model.live="eventTypeFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Events</option>
                    @foreach($eventTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Actor Type</label>
                <select wire:model.live="actorTypeFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All Actors</option>
                    <option value="user">User</option>
                    <option value="guest">Guest</option>
                </select>
            </div>
        </div>
    </x-ui.card>

    <!-- Logs Table -->
    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occurred At</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Path</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $log->occurred_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    {{ $log->event_type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($log->actor)
                                    <div class="font-medium text-gray-900">{{ $log->actor->nickname }}</div>
                                    <div class="text-gray-500 text-xs">{{ $log->actor_type }}</div>
                                @else
                                    <span class="text-gray-400">{{ $log->actor_type }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 max-w-xs truncate">
                                <span class="font-mono text-xs">{{ $log->method }} {{ $log->path }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $code = $log->status_code;
                                    $color = match(true) {
                                        $code >= 500 => 'bg-red-100 text-red-800',
                                        $code >= 400 => 'bg-yellow-100 text-yellow-800',
                                        $code >= 300 => 'bg-blue-100 text-blue-800',
                                        default      => 'bg-green-100 text-green-800',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                    {{ $code }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">
                                {{ $log->ip }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($log->target_idcode)
                                    <span class="font-mono text-xs">{{ $log->target_idcode }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No audit log entries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </x-ui.card>
</div>
