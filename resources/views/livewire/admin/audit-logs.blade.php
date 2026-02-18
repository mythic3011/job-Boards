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

        return [
            'total_today'     => AuditLog::where('occurred_at', '>=', $today)->count(),
            'failed_logins'   => AuditLog::where('occurred_at', '>=', $today)
                                         ->where('event_type', 'login_failed')->count(),
            'suspicious'      => AuditLog::where('occurred_at', '>=', $today)
                                         ->whereIn('event_type', ['suspicious_user_agent', 'suspicious_ua_high_risk_path', 'admin_probe'])
                                         ->count(),
            'locked_accounts' => AuditLog::where('occurred_at', '>=', $today)
                                         ->where('event_type', 'account_locked')->count(),
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

        $query->when($this->dateRange !== 'all', function ($q) {
            $hours = match ($this->dateRange) {
                'last_7_days'  => 168,
                'last_30_days' => 720,
                default        => 24,
            };
            $q->where('occurred_at', '>=', now()->subHours($hours));
        });

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

        $query->when($this->eventType, fn($q) =>
            $q->where('event_type', $this->eventType)
        );

        $query->when($this->actorType, fn($q) =>
            $q->where('actor_type', $this->actorType)
        );

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
};
 ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Audit Logs</h1>
    </div>

    {{-- Stats bar --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach([
            ['label' => 'Events today',    'value' => $this->stats['total_today']],
            ['label' => 'Failed logins',   'value' => $this->stats['failed_logins']],
            ['label' => 'Suspicious',      'value' => $this->stats['suspicious']],
            ['label' => 'Accounts locked', 'value' => $this->stats['locked_accounts']],
        ] as $stat)
            <div class="rounded-lg border border-gray-200 bg-white px-5 py-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <x-ui.card class="mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search actor, IP, target…"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                />
            </div>

            <select wire:model.live="eventType" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                <option value="">All event types</option>
                @foreach($this->eventTypes as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>

            <select wire:model.live="actorType" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                <option value="">All actors</option>
                <option value="user">User</option>
                <option value="guest">Guest</option>
                <option value="system">System</option>
            </select>

            <select wire:model.live="status" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100">
                <option value="">All statuses</option>
                <option value="success">Success (2xx)</option>
                <option value="failed">Failed (4xx/5xx)</option>
            </select>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            @foreach(['today' => 'Today', 'last_7_days' => 'Last 7 days', 'last_30_days' => 'Last 30 days', 'all' => 'All time'] as $value => $label)
                <button
                    wire:click="$set('dateRange', '{{ $value }}')"
                    class="rounded-full px-3 py-1 text-xs font-medium transition-colors
                        {{ $dateRange === $value
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </x-ui.card>

    {{-- Log table --}}
    <x-ui.card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($logs as $log)
                        @php
                            $isFailed   = $log->status_code >= 400;
                            $isSecurity = in_array($log->event_type, ['suspicious_user_agent','suspicious_ua_high_risk_path','admin_probe','login_failed','account_locked','account_locked_attempt']);
                            $rowClass   = $isFailed ? 'bg-red-50' : ($isSecurity ? 'bg-amber-50' : '');
                        @endphp
                        <tr wire:key="log-{{ $log->id }}" class="{{ $rowClass }}">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500">
                                <span title="{{ $log->occurred_at->toDateTimeString() }}">
                                    {{ $log->occurred_at->diffForHumans() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($log->actor)
                                    <div class="font-medium text-gray-900">{{ $log->actor->nickname }}</div>
                                    <div class="text-xs text-gray-400">{{ $log->actor_type }}</div>
                                @else
                                    <span class="text-gray-400 italic">{{ $log->actor_type }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs {{ $isSecurity ? 'text-amber-700 font-semibold' : 'text-gray-700' }}">
                                    {{ $log->event_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 font-mono text-xs">
                                @if($log->target_type)
                                    {{ $log->target_type }}
                                    @if($log->target_idcode)
                                        <span class="text-gray-400">· {{ Str::limit($log->target_idcode, 20) }}</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-500 font-mono text-xs">
                                {{ $log->ip ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                                    {{ $log->status_code >= 500 ? 'bg-red-100 text-red-800' :
                                       ($log->status_code >= 400 ? 'bg-orange-100 text-orange-800' :
                                       'bg-green-100 text-green-800') }}">
                                    {{ $log->status_code }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No audit logs found.</td>
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
