<?php

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

use function Livewire\Volt\{layout, title};

layout('layouts.app');
title('Admin - Audit Logs');

new class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $quickFilter = '';
    #[Url] public string $eventType = '';
    #[Url] public string $actorType = '';
    #[Url] public string $actorRole = '';
    #[Url] public string $status = '';
    #[Url] public string $dateRange = 'today';

    public function mount(): void
    {
        $this->authorize('admin.system.view');

        if ($this->quickFilter !== '') {
            $this->applyQuickFilter($this->quickFilter);
        }

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
                    'actor_role' => $this->actorRole,
                    'status'     => $this->status,
                    'date_range' => $this->dateRange,
                ],
            ]
        );
    }

    public function updatedSearch(): void    { $this->quickFilter = ''; $this->resetPage(); }
    public function updatedEventType(): void { $this->quickFilter = ''; $this->resetPage(); }
    public function updatedActorType(): void { $this->quickFilter = ''; $this->resetPage(); }
    public function updatedActorRole(): void { $this->quickFilter = ''; $this->resetPage(); }
    public function updatedStatus(): void    { $this->quickFilter = ''; $this->resetPage(); }
    public function updatedDateRange(): void { $this->quickFilter = ''; $this->resetPage(); }

    public function applyQuickFilter(string $filter): void
    {
        $this->quickFilter = $filter;

        match ($filter) {
            'admin_actions' => $this->setRoleQuickFilter('admin'),
            'company_actions' => $this->setRoleQuickFilter('company'),
            'individual_actions' => $this->setRoleQuickFilter('individual'),
            'failed_requests' => $this->setFailedQuickFilter(),
            'application_submitted' => $this->setEventQuickFilter('application.submitted', 'individual'),
            'job_created' => $this->setEventQuickFilter('job.created', 'company'),
            'profile_updated' => $this->setEventQuickFilter('profile_updated'),
            'password_updated' => $this->setEventQuickFilter('password_updated'),
            default => $this->clearQuickFilters(false),
        };

        $this->resetPage();
    }

    public function clearQuickFilters(bool $resetPage = true): void
    {
        $this->quickFilter = '';
        $this->eventType = '';
        $this->actorType = '';
        $this->actorRole = '';
        $this->status = '';

        if ($resetPage) {
            $this->resetPage();
        }
    }

    private function setRoleQuickFilter(string $role): void
    {
        $this->eventType = '';
        $this->status = '';
        $this->actorType = 'user';
        $this->actorRole = $role;
    }

    private function setFailedQuickFilter(): void
    {
        $this->eventType = '';
        $this->actorType = '';
        $this->actorRole = '';
        $this->status = 'failed';
    }

    private function setEventQuickFilter(string $eventType, ?string $role = null): void
    {
        $this->eventType = $eventType;
        $this->status = '';
        $this->actorType = 'user';
        $this->actorRole = $role ?? '';
    }

    public function getStatsProperty(): array
    {
        $today = now()->startOfDay();

        $stats = AuditLog::where('occurred_at', '>=', $today)
            ->selectRaw("
                COUNT(*) as total_today,
                COUNT(CASE WHEN event_type = 'login_failed' THEN 1 END) as failed_logins,
                COUNT(CASE WHEN event_type IN ('suspicious_user_agent', 'suspicious_ua_high_risk_path', 'admin_probe', 'security.route_probe', 'security.route_scan_detected', 'security.unauth_access') THEN 1 END) as suspicious,
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

    public function getActorRolesProperty(): Collection
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name');
    }

    public function with(): array
    {
        $query = AuditLog::with('actor.roles')->orderByDesc('occurred_at');

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

        // Actor role
        $query->when($this->actorRole, fn($q) =>
            $q->whereHas('actor.roles', fn($roleQuery) =>
                $roleQuery->where('name', $this->actorRole)
            )
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

    public function eventLabel(AuditLog $log): string
    {
        return Str::headline(str_replace(['.', '_'], ' ', $log->event_type));
    }

    public function actorPrimaryLabel(AuditLog $log): string
    {
        if (! $log->actor) {
            return Str::headline($log->actor_type ?? 'guest');
        }

        return $log->actor->login_id ?: ($log->actor->nickname ?: 'User');
    }

    public function actorSecondaryLabel(AuditLog $log): ?string
    {
        if (! $log->actor) {
            return null;
        }

        $nickname = trim((string) $log->actor->nickname);
        $loginId = trim((string) $log->actor->login_id);

        if ($nickname === '' || $nickname === $loginId) {
            return null;
        }

        return $nickname;
    }

    public function actorRoles(AuditLog $log): array
    {
        if (! $log->actor || ! $log->actor->relationLoaded('roles')) {
            return [];
        }

        return $log->actor->roles
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
    }

    public function friendlyPath(AuditLog $log): string
    {
        $path = (string) ($log->path ?? '');
        $meta = is_array($log->meta) ? $log->meta : [];

        if (preg_match('/^livewire-[a-z0-9]+\/update$/i', $path)) {
            return match ($log->event_type) {
                'settings.updated' => 'Livewire action: Admin Settings update',
                default => 'Livewire component update',
            };
        }

        if (in_array($log->event_type, ['security.route_probe', 'security.route_scan_detected', 'security.unauth_access'], true)) {
            $targetPath = $log->target_type === 'route' ? $log->target_idcode : null;
            if (is_string($targetPath) && $targetPath !== '') {
                return $targetPath;
            }

            if (isset($meta['route_name']) && is_string($meta['route_name']) && $meta['route_name'] !== '') {
                return 'Route: ' . $meta['route_name'];
            }
        }

        return $path !== '' ? $path : '—';
    }

    public function targetLabel(AuditLog $log): string
    {
        if ($log->target_idcode) {
            return (string) $log->target_idcode;
        }

        return $log->target_type ? Str::headline($log->target_type) : '—';
    }

    public function eventSummary(AuditLog $log): ?string
    {
        $meta = is_array($log->meta) ? $log->meta : [];

        if ($log->event_type === 'security.unauth_access') {
            $routeName = isset($meta['route_name']) && is_string($meta['route_name']) && $meta['route_name'] !== ''
                ? $meta['route_name']
                : 'unknown route';

            return 'Guest tried protected route: ' . $routeName;
        }

        if ($log->event_type === 'security.route_probe') {
            $attempts = (int) ($meta['attempt_count'] ?? 0);
            $unique = (int) ($meta['unique_path_count'] ?? 0);
            $score = (int) ($meta['risk_score'] ?? 0);

            return "Probe signal | attempts: {$attempts} | unique paths: {$unique} | risk: {$score}";
        }

        if ($log->event_type === 'security.route_scan_detected') {
            $attempts = (int) ($meta['attempt_count'] ?? 0);
            $unique = (int) ($meta['unique_path_count'] ?? 0);
            $unauth = (int) ($meta['unauth_protected_hits'] ?? 0);

            return "Route scan threshold exceeded | attempts: {$attempts} | unique: {$unique} | unauth protected hits: {$unauth}";
        }

        if ($log->event_type === 'settings.updated') {
            $keys = ['demo_mode' => 'Demo', 'registrations_open' => 'Registrations', 'maintenance_mode' => 'Maintenance'];
            $changes = [];

            foreach ($keys as $key => $label) {
                if (isset($meta[$key]['before'], $meta[$key]['after']) && $meta[$key]['before'] !== $meta[$key]['after']) {
                    $before = $meta[$key]['before'] ? 'On' : 'Off';
                    $after = $meta[$key]['after'] ? 'On' : 'Off';
                    $changes[] = "{$label}: {$before} -> {$after}";
                }
            }

            if (! empty($changes)) {
                return implode(' | ', $changes);
            }
        }

        if (isset($meta['reason']) && is_string($meta['reason']) && $meta['reason'] !== '') {
            return Str::limit($meta['reason'], 120);
        }

        return null;
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="theme-text-strong text-3xl font-bold">Audit Logs</h1>
            <p class="theme-text-muted mt-1 text-sm">Review request trails, actor behavior, and high-signal security events.</p>
        </div>
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

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @php
            $chipBase = 'inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium transition-colors';
            $inactiveChip = 'theme-panel-subtle theme-text-strong border-[var(--app-panel-border)] hover:bg-[var(--app-panel-bg)]';
            $quickFilterClass = static function (string $current, string $filter, string $activeClass, string $inactiveClass): string {
                return $current === $filter ? $activeClass : $inactiveClass;
            };
        @endphp

        <button type="button" wire:click="applyQuickFilter('admin_actions')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'admin_actions', 'theme-pill', $inactiveChip) }}">Admin Actions</button>
        <button type="button" wire:click="applyQuickFilter('company_actions')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'company_actions', 'theme-pill', $inactiveChip) }}">Company Actions</button>
        <button type="button" wire:click="applyQuickFilter('individual_actions')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'individual_actions', 'theme-pill', $inactiveChip) }}">Individual Actions</button>
        <button type="button" wire:click="applyQuickFilter('failed_requests')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'failed_requests', 'theme-alert-error', $inactiveChip) }}">Failed Requests</button>
        <button type="button" wire:click="applyQuickFilter('application_submitted')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'application_submitted', 'theme-alert-success', $inactiveChip) }}">Application Submitted</button>
        <button type="button" wire:click="applyQuickFilter('job_created')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'job_created', 'theme-alert-success', $inactiveChip) }}">Job Created</button>
        <button type="button" wire:click="applyQuickFilter('profile_updated')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'profile_updated', 'theme-alert-success', $inactiveChip) }}">Profile Updated</button>
        <button type="button" wire:click="applyQuickFilter('password_updated')" class="{{ $chipBase }} {{ $quickFilterClass($quickFilter, 'password_updated', 'theme-alert-success', $inactiveChip) }}">Password Updated</button>

        @if($quickFilter !== '')
            <button type="button" wire:click="clearQuickFilters" class="theme-button theme-button-outline inline-flex items-center rounded-full px-3 py-1.5 text-xs font-medium">Clear</button>
        @endif
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-3">
        {{-- Search --}}
        <div class="relative flex-1 min-w-48">
            <div class="theme-input-shell flex items-center gap-3 rounded-lg border px-4 py-2.5 transition-all duration-150">
                <svg class="theme-text-muted shrink-0" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="IP, user, target…"
                    class="theme-input flex-1 min-w-0 border-0 bg-transparent text-sm outline-none"
                    autocomplete="off"
                />
                @if($search)
                    <button wire:click="$set('search', '')" class="theme-text-muted shrink-0 rounded-full p-0.5 transition-colors cursor-pointer hover:bg-[var(--app-panel-subtle-bg)] hover:text-[var(--app-text-strong)]" aria-label="Clear search">
                        <svg style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        <select wire:model.live="eventType" class="theme-input-shell theme-text-strong shrink-0 rounded-lg border px-3 py-2.5 text-sm outline-none transition-all duration-150 cursor-pointer">
            <option value="">All Events</option>
            @foreach($this->eventTypes as $type)
                <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
            @endforeach
        </select>

        <select wire:model.live="actorType" class="theme-input-shell theme-text-strong shrink-0 rounded-lg border px-3 py-2.5 text-sm outline-none transition-all duration-150 cursor-pointer">
            <option value="">All Actors</option>
            <option value="user">User</option>
            <option value="guest">Guest</option>
            <option value="system">System</option>
        </select>

        <select wire:model.live="actorRole" class="theme-input-shell theme-text-strong shrink-0 rounded-lg border px-3 py-2.5 text-sm outline-none transition-all duration-150 cursor-pointer">
            <option value="">All Roles</option>
            @foreach($this->actorRoles as $role)
                <option value="{{ $role }}">{{ Str::headline($role) }}</option>
            @endforeach
        </select>

        <select wire:model.live="status" class="theme-input-shell theme-text-strong shrink-0 rounded-lg border px-3 py-2.5 text-sm outline-none transition-all duration-150 cursor-pointer">
            <option value="">All Statuses</option>
            <option value="success">Success (2xx)</option>
            <option value="failed">Failed (4xx+)</option>
        </select>

        <select wire:model.live="dateRange" class="theme-input-shell theme-text-strong shrink-0 rounded-lg border px-3 py-2.5 text-sm outline-none transition-all duration-150 cursor-pointer">
            <option value="today">Today</option>
            <option value="last_7_days">Last 7 Days</option>
            <option value="last_30_days">Last 30 Days</option>
            <option value="all">All Time</option>
        </select>
    </div>

    <!-- Results -->
    <x-ui.card>
        <div class="theme-table-shell hidden overflow-x-auto rounded-xl border lg:block">
            <table class="min-w-full text-sm">
                <thead class="theme-table-head theme-table-divider border-b">
                    <tr>
                        <th class="theme-text-muted px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Time</th>
                        <th class="theme-text-muted px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Event</th>
                        <th class="theme-text-muted px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Actor</th>
                        <th class="theme-text-muted px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Request</th>
                        <th class="theme-text-muted px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                        <th class="theme-text-muted px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Inspect</th>
                    </tr>
                </thead>
                <tbody class="theme-table-divider divide-y">
                    @forelse($logs as $log)
                        @php
                            $eventClass = match(true) {
                                str_contains($log->event_type, 'failed') || str_contains($log->event_type, 'locked') => 'theme-alert-error',
                                str_contains($log->event_type, 'suspicious') || str_contains($log->event_type, 'probe') || str_starts_with($log->event_type, 'security.') => 'theme-alert-warning',
                                str_contains($log->event_type, 'login') => 'theme-alert-success',
                                default => 'theme-pill',
                            };
                        @endphp
                        <tr wire:key="log-desktop-{{ $log->id }}" class="align-top transition-colors hover:bg-[var(--app-panel-subtle-bg)]/70">
                            <td class="theme-text-muted px-4 py-3 whitespace-nowrap">
                                <div title="{{ $log->occurred_at->toDateTimeString() }}">{{ $log->occurred_at->diffForHumans() }}</div>
                                <div class="theme-text-muted mt-1 text-[11px]">{{ $log->occurred_at->format('Y-m-d H:i:s') }}</div>
                            </td>
                            <td class="px-4 py-3 min-w-[280px]">
                                <span class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium {{ $eventClass }}">{{ $this->eventLabel($log) }}</span>
                                @if($this->eventSummary($log))
                                    <div class="theme-text-muted mt-1 text-xs leading-relaxed">{{ $this->eventSummary($log) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 min-w-[220px]">
                                @if($log->actor)
                                    <div class="theme-text-strong font-medium" title="{{ $log->actor->login_id }}">{{ $this->actorPrimaryLabel($log) }}</div>
                                    @if($this->actorSecondaryLabel($log))
                                        <div class="theme-text-muted text-xs">{{ $this->actorSecondaryLabel($log) }}</div>
                                    @endif
                                    @if(! empty($this->actorRoles($log)))
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach($this->actorRoles($log) as $role)
                                                <span class="theme-pill inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium">
                                                    {{ Str::headline($role) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <span class="theme-text-muted">{{ $log->actor_type ?? 'guest' }}</span>
                                @endif
                                <div class="theme-text-muted mt-1 font-mono text-[11px]">{{ $log->ip }}</div>
                            </td>
                            <td class="px-4 py-3 min-w-[300px]">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @if($log->method)
                                        <span class="inline-flex items-center rounded border px-1.5 py-0.5 text-xs font-bold {{ match($log->method) {
                                            'GET' => 'theme-alert-info',
                                            'POST' => 'theme-alert-success',
                                            'PUT', 'PATCH' => 'theme-alert-warning',
                                            'DELETE' => 'theme-alert-error',
                                            default => 'theme-pill',
                                        } }}">{{ $log->method }}</span>
                                    @endif
                                    <span class="theme-text-strong break-all text-xs" title="{{ $log->path }}">{{ $this->friendlyPath($log) }}</span>
                                </div>
                                <div class="theme-text-muted mt-1 font-mono text-[11px]">{{ $log->request_id }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($log->status_code)
                                    <span class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium {{ $log->status_code < 300 ? 'theme-alert-success' : ($log->status_code < 500 ? 'theme-alert-warning' : 'theme-alert-error') }}">{{ $log->status_code }}</span>
                                @else
                                    <span class="theme-text-muted">—</span>
                                @endif
                            </td>
                            <td class="theme-text-muted px-4 py-3 min-w-[280px] text-xs">
                                <div class="mb-1 font-mono">target: {{ $this->targetLabel($log) }}</div>
                                <details>
                                    <summary class="theme-link cursor-pointer">View details</summary>
                                    <div class="mt-2 space-y-2">
                                        @if($log->user_agent)
                                            <div>
                                                <div class="theme-text-muted text-[11px] uppercase tracking-wide">User Agent</div>
                                                <div class="theme-text-muted break-all">{{ $log->user_agent }}</div>
                                            </div>
                                        @endif
                                        @if(is_array($log->meta) && ! empty($log->meta))
                                            <div>
                                                <div class="theme-text-muted text-[11px] uppercase tracking-wide">Meta</div>
                                                <pre class="theme-panel theme-text-strong mt-1 max-h-44 overflow-auto rounded border p-2 text-[11px]">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="theme-text-muted px-4 py-8 text-center">No audit logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 lg:hidden">
            @forelse($logs as $log)
                @php
                    $eventClass = match(true) {
                        str_contains($log->event_type, 'failed') || str_contains($log->event_type, 'locked') => 'theme-alert-error',
                        str_contains($log->event_type, 'suspicious') || str_contains($log->event_type, 'probe') || str_starts_with($log->event_type, 'security.') => 'theme-alert-warning',
                        str_contains($log->event_type, 'login') => 'theme-alert-success',
                        default => 'theme-pill',
                    };
                @endphp
                <article wire:key="log-mobile-{{ $log->id }}" class="theme-panel-subtle space-y-3 rounded-xl border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="theme-text-strong text-sm font-medium">{{ $log->occurred_at->diffForHumans() }}</div>
                            <div class="theme-text-muted text-[11px]">{{ $log->occurred_at->format('Y-m-d H:i:s') }}</div>
                        </div>
                        @if($log->status_code)
                            <span class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium {{ $log->status_code < 300 ? 'theme-alert-success' : ($log->status_code < 500 ? 'theme-alert-warning' : 'theme-alert-error') }}">{{ $log->status_code }}</span>
                        @endif
                    </div>

                    <div>
                        <span class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-medium {{ $eventClass }}">{{ $this->eventLabel($log) }}</span>
                        @if($this->eventSummary($log))
                            <div class="theme-text-muted mt-1 text-xs leading-relaxed">{{ $this->eventSummary($log) }}</div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-2 text-xs">
                        <div><span class="theme-text-muted">Actor:</span> <span class="theme-text-strong">{{ $this->actorPrimaryLabel($log) }}</span></div>
                        @if(! empty($this->actorRoles($log)))
                            <div class="flex flex-wrap gap-1">
                                @foreach($this->actorRoles($log) as $role)
                                    <span class="theme-pill inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium">{{ Str::headline($role) }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div><span class="theme-text-muted">IP:</span> <span class="theme-text-strong font-mono">{{ $log->ip }}</span></div>
                        <div>
                            <span class="theme-text-muted">Request:</span>
                            <span class="theme-text-strong">{{ $log->method ? $log->method.' ' : '' }}{{ $this->friendlyPath($log) }}</span>
                        </div>
                        <div><span class="theme-text-muted">Target:</span> <span class="theme-text-strong font-mono">{{ $this->targetLabel($log) }}</span></div>
                        <div><span class="theme-text-muted">Request ID:</span> <span class="theme-text-muted break-all font-mono">{{ $log->request_id }}</span></div>
                    </div>

                    <details>
                        <summary class="theme-link cursor-pointer text-sm">View details</summary>
                        <div class="mt-2 space-y-2 text-xs">
                            @if($log->user_agent)
                                <div>
                                    <div class="theme-text-muted text-[11px] uppercase tracking-wide">User Agent</div>
                                    <div class="theme-text-muted break-all">{{ $log->user_agent }}</div>
                                </div>
                            @endif
                            @if(is_array($log->meta) && ! empty($log->meta))
                                <div>
                                    <div class="theme-text-muted text-[11px] uppercase tracking-wide">Meta</div>
                                    <pre class="theme-panel theme-text-strong mt-1 max-h-40 overflow-auto rounded border p-2 text-[11px]">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                            @endif
                        </div>
                    </details>
                </article>
            @empty
                <div class="theme-panel-subtle theme-text-muted rounded-xl border px-4 py-8 text-center">No audit logs found.</div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </x-ui.card>
</div>
