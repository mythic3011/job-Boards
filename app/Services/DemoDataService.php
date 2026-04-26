<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DemoDataService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function clearDemoData(Request $request): bool
    {
        $demoSeededAt = Setting::get('demo_seeded_at');
        $demoSeedUserIds = $this->getDemoSeedUserIds();

        if ($demoSeedUserIds === []) {
            Log::warning('Attempted to clear demo data but no seeded demo user IDs found');

            return false;
        }

        $demoUsers = User::whereDoesntHave('roles', function ($query): void {
            $query->where('name', 'admin');
        })
            ->whereIn('id', $demoSeedUserIds)
            ->get();

        if ($demoUsers->isEmpty()) {
            Log::info('No demo users to delete');

            return false;
        }

        $this->auditLogger->logBusinessEvent(
            eventType: 'demo.data_cleared',
            request: $request,
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

        Setting::set('demo_seeded_at', null);
        Setting::set('demo_seed_user_ids', null);

        return true;
    }

    /**
     * @return list<int|string>
     */
    private function getDemoSeedUserIds(): array
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
}
