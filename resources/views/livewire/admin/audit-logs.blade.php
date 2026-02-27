<?php

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Audit Logs');

new class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $eventType = '';
    #[Url] public string $actorType = '';
    #[Url] public string $status = '';
    #[Url] public string $dateRange = 'today';

    public function mount(): void
    {
        $this->authorize('admin.system.view');

        app(AuditLogger::class)->logBusinessEvent(
            eventType: 'audit_log.viewed',
            request: request(),
            targetType: 'audit_log',
            targetIdcode: null,
            meta: [
                'filters' => [
                    'search'     => $this->search,
                    'event_type' => $this->eventType,
                    'actor_type' => $this->actorType,
                    'status'     => $this->status,
                    'date_range' => $this->dateRange,
                ],
            ]
        );
    }

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedEventType(): void { $this->resetPage(); }
    public function updatedActorType(): void { $this->resetPage(); }
    public function updatedStatus(): void    { $this->resetPage(); }
    public function updatedDateRange(): void { $this->resetPage(); }

    public function getStatsProperty(): array
    {
        $today = now()->startOfDay();

        $stats = AuditLog::where('occurred_at', '>=', $today)
            ->selectRaw("
                COUNT(*) as total_today,
                COUNT(CASE WHEN event_type = 'login_failed' THEN 1 END) as failed_logins,
                COUNT(CASE WHEN event_type IN ('suspicious_user_agent', 'suspicious_ua_high_risk_path', 'admin_probe') THEN 1 END) as suspicious,
                COUNT(CASE WHEN event_type = 'account_locked' THEN 1 END) as locked_accounts
            ")
            ->first();

        return [
            'total_today'     => $stats?->total_today ?? 0,
            'failed_logins'   => $stats?->failed_logins ?? 0,
            'suspicious'      => $stats?->suspicious ?? 0,
            'locked_accounts' => $stats?->locked_accounts ?? 0,
        ];
    }

    public function getEventTypesProperty(): Collection
    {
        return AuditLog::select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');
    }

    public function with(): array
    {
        $query = AuditLog::with('actor')->orderByDesc('occurred_at');

        // Date range
        $query->when($this->dateRange !== 'all', function ($q) {
            $hours = match ($this->dateRange) {
                'last_7_days'  => 168,
                'last_30_days' => 720,
                default        => 24, // today
            };
            $q->where('occurred_at', '>=', now()->subHours($hours));
        });

        // Search: actor nickname/login_id, IP, target idcode
        $query->when($this->search, function ($q) {
            $term = '%' . $this->search . '%';
            $q->where(function ($inner) use ($term) {
                $inner->where('ip', 'ilike', $term)
                      ->orWhere('target_idcode', 'ilike', $term)
                      ->orWhereHas('actor', fn($u) =>
                          $u->where('nickname', 'ilike', $term)
                            ->orWhere('login_id', 'ilike', $term)
                      );
            });
        });

        // Event type
        $query->when($this->eventType, fn($q) =>
            $q->where('event_type', $this->eventType)
        );

        // Actor type
        $query->when($this->actorType, fn($q) =>
            $q->where('actor_type', $this->actorType)
        );

        // Status
        $query->when($this->status === 'success', fn($q) =>
            $q->whereBetween('status_code', [200, 299])
        );
        $query->when($this->status === 'failed', fn($q) =>
            $q->where('status_code', '>=', 400)
        );

        return [
            'logs' => $query->paginate(25),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Audit Logs</h1>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card label="Events Today" :value="$this->stats['total_today']" icon-color="text-indigo-600">
            <x-slot:icon><x-heroicon-o-chart-bar class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Failed Logins" :value="$this->stats['failed_logins']" icon-color="text-yellow-600">
            <x-slot:icon><x-heroicon-o-exclamation-triangle class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Suspicious" :value="$this->stats['suspicious']" icon-color="text-orange-600">
            <x-slot:icon><x-heroicon-o-shield-exclamation class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>

        <x-ui.stat-card label="Locked Accounts" :value="$this->stats['locked_accounts']" icon-color="text-red-600">
            <x-slot:icon><x-heroicon-o-lock-closed class="h-12 w-12" /></x-slot:icon>
        </x-ui.stat-card>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-3">
        {{-- Search --}}
        <div class="relative flex-1 min-w-48">
            <div class="flex items-center gap-3 rounded-lg border border-gray-300 bg-white px-4 py-2.5 shadow-sm transition-all duration-150 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-100">
                <svg style="width:18px;height:18px;flex-shrink:0;color:#9ca3af" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="IP, user, target…"
                    class="flex-1 bg-transparent text-sm text-gray-800 placeholder-gray-400 outline-none min-w-0"
                    autocomplete="off"
                />
                @if($search)
                    <button wire:click="$set('search', '')" class="shrink-0 rounded-full p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors cursor-pointer" aria-label="Clear search">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <select wire:model.live="eventType" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="">All Events</option>
            @foreach($this->eventTypes as $type)
                <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
            @endforeach
        </select>

        <select wire:model.live="actorType" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="">All Actors</option>
            <option value="user">User</option>
            <option value="guest">Guest</option>
            <option value="system">System</option>
        </select>

        <select wire:model.live="status" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="">All Statuses</option>
            <option value="success">Success (2xx)</option>
            <option value="failed">Failed (4xx+)</option>
        </select>

        <select wire:model.live="dateRange" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all duration-150 cursor-pointer">
            <option value="today">Today</option>
            <option value="last_7_days">Last 7 Days</option>
            <option value="last_30_days">Last 30 Days</option>
            <option value="all">All Time</option>
        </select>
    </div>

    <!-- Table -->
    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method / Path</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                        <tr wire:key="log-{{ $log->id }}" class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500">
                                <span title="{{ $log->occurred_at->toDateTimeString() }}">
                                    {{ $log->occurred_at->diffForHumans() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php
                                    $eventClass = match(true) {
                                        str_contains($log->event_type, 'failed') || str_contains($log->event_type, 'locked') => 'bg-red-100 text-red-800',
                                        str_contains($log->event_type, 'suspicious') || str_contains($log->event_type, 'probe') => 'bg-orange-100 text-orange-800',
                                        str_contains($log->event_type, 'login') => 'bg-green-100 text-green-800',
                                        default => 'bg-gray-100 text-gray-700',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $eventClass }}">
                                    {{ str_replace('_', ' ', $log->event_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($log->actor)
                                    <div class="font-medium text-gray-900">{{ $log->actor->nickname }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->actor->login_id }}</div>
                                @else
                                    <span class="text-gray-400">{{ $log->actor_type ?? 'guest' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono text-gray-600">
                                {{ $log->ip }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    @if($log->method)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-bold
                                            {{ match($log->method) {
                                                'GET'    => 'bg-blue-100 text-blue-700',
                                                'POST'   => 'bg-green-100 text-green-700',
                                                'PUT', 'PATCH' => 'bg-yellow-100 text-yellow-700',
                                                'DELETE' => 'bg-red-100 text-red-700',
                                                default  => 'bg-gray-100 text-gray-700',
                                            } }}">
                                            {{ $log->method }}
                                        </span>
                                    @endif
                                    <span class="text-gray-600 truncate max-w-xs" title="{{ $log->path }}">{{ $log->path }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($log->status_code)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ $log->status_code < 300 ? 'bg-green-100 text-green-800' : ($log->status_code < 500 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $log->status_code }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 font-mono text-xs">
                                {{ $log->target_idcode ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">No audit logs found.</td>
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
