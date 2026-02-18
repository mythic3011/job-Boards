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
            'total_today'       => AuditLog::where('occurred_at', '>=', $today)->count(),
            'failed_logins'     => AuditLog::where('occurred_at', '>=', $today)
                                           ->where('event_type', 'login_failed')->count(),
            'suspicious'        => AuditLog::where('occurred_at', '>=', $today)
                                           ->whereIn('event_type', ['suspicious_user_agent', 'suspicious_ua_high_risk_path', 'admin_probe'])
                                           ->count(),
            'locked_accounts'   => AuditLog::where('occurred_at', '>=', $today)
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
    {{-- Blade template in Task 3 --}}
</div>
