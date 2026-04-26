<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AdminSettingsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * @return array{
     *     app_name: string,
     *     app_url: string,
     *     timezone: string,
     *     demo_mode: bool,
     *     registrations_open: bool,
     *     maintenance_mode: bool
     * }
     */
    public function getCurrentState(): array
    {
        return [
            'app_name' => (string) Setting::get('app_name', config('app.name', 'Jobs Board')),
            'app_url' => (string) (Setting::get('app_url') ?? ''),
            'timezone' => (string) Setting::get('timezone', config('app.timezone', 'UTC')),
            'demo_mode' => Setting::getBool('demo_mode', false),
            'registrations_open' => Setting::getBool('registrations_open', true),
            'maintenance_mode' => Setting::getBool('maintenance_mode', false),
        ];
    }

    /**
     * @return array{
     *     allowed: bool,
     *     error: ?string,
     *     close_modal: bool
     * }
     */
    public function verifyUpdateIntent(User $actor, string $password, Request $request): array
    {
        Gate::forUser($actor)->authorize('admin.settings.update');

        if ($password === '') {
            return [
                'allowed' => false,
                'error' => 'Password confirmation is required.',
                'close_modal' => false,
            ];
        }

        if (! Hash::check($password, (string) $actor->password)) {
            return [
                'allowed' => false,
                'error' => 'The provided password is incorrect.',
                'close_modal' => false,
            ];
        }

        $rateLimitKey = 'settings-update:' . ($actor->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return [
                'allowed' => false,
                'error' => "Too many attempts. Please try again in {$seconds} seconds.",
                'close_modal' => true,
            ];
        }

        RateLimiter::hit($rateLimitKey, 60);

        return [
            'allowed' => true,
            'error' => null,
            'close_modal' => false,
        ];
    }

    /**
     * @param  array{
     *     app_name: string,
     *     app_url: string,
     *     timezone: string,
     *     demo_mode: bool,
     *     registrations_open: bool,
     *     maintenance_mode: bool
     * }  $input
     * @return array{
     *     changed: bool,
     *     demo_mode_activated: bool,
     *     demo_data_removed: bool,
     *     state: array{
     *         app_name: string,
     *         app_url: string,
     *         timezone: string,
     *         demo_mode: bool,
     *         registrations_open: bool,
     *         maintenance_mode: bool
     *     }
     * }
     */
    public function updateSettings(array $input, Request $request): array
    {
        $before = $this->getCurrentState();
        $after = $this->normalizeState($input);

        if (! $this->hasChanges($before, $after)) {
            return [
                'changed' => false,
                'demo_mode_activated' => false,
                'demo_data_removed' => false,
                'state' => $before,
            ];
        }

        $demoDataRemoved = false;

        DB::transaction(function () use ($before, $after, $request, &$demoDataRemoved): void {
            Setting::set('app_name', $after['app_name']);
            Setting::set('app_url', $after['app_url'] !== '' ? $after['app_url'] : null);
            Setting::set('timezone', $after['timezone']);
            Setting::setBool('demo_mode', $after['demo_mode']);
            Setting::setBool('registrations_open', $after['registrations_open']);
            Setting::setBool('maintenance_mode', $after['maintenance_mode']);

            if ($before['demo_mode'] && ! $after['demo_mode']) {
                $demoDataRemoved = $this->clearDemoData($request);
            }

            $this->auditLogger->logBusinessEvent(
                eventType: 'settings.updated',
                request: $request,
                targetType: 'setting',
                targetIdcode: null,
                meta: [
                    'app_name' => [
                        'before' => $before['app_name'],
                        'after' => $after['app_name'],
                    ],
                    'app_url' => [
                        'before' => $before['app_url'],
                        'after' => $after['app_url'] !== '' ? $after['app_url'] : null,
                    ],
                    'timezone' => [
                        'before' => $before['timezone'],
                        'after' => $after['timezone'],
                    ],
                    'demo_mode' => [
                        'before' => $before['demo_mode'],
                        'after' => $after['demo_mode'],
                    ],
                    'registrations_open' => [
                        'before' => $before['registrations_open'],
                        'after' => $after['registrations_open'],
                    ],
                    'maintenance_mode' => [
                        'before' => $before['maintenance_mode'],
                        'after' => $after['maintenance_mode'],
                    ],
                ]
            );
        });

        if (! $before['demo_mode'] && $after['demo_mode']) {
            // Temporarily disabled during 500 triage to isolate UI state from backend side effects.
            // SeedDemoData::dispatch();
        }

        $this->dashboardService->clearCache();

        return [
            'changed' => true,
            'demo_mode_activated' => ! $before['demo_mode'] && $after['demo_mode'],
            'demo_data_removed' => $demoDataRemoved,
            'state' => $after,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     app_name: string,
     *     app_url: string,
     *     timezone: string,
     *     demo_mode: bool,
     *     registrations_open: bool,
     *     maintenance_mode: bool
     * }
     */
    private function normalizeState(array $input): array
    {
        return [
            'app_name' => trim((string) ($input['app_name'] ?? '')),
            'app_url' => trim((string) ($input['app_url'] ?? '')),
            'timezone' => trim((string) ($input['timezone'] ?? '')),
            'demo_mode' => (bool) ($input['demo_mode'] ?? false),
            'registrations_open' => (bool) ($input['registrations_open'] ?? true),
            'maintenance_mode' => (bool) ($input['maintenance_mode'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function hasChanges(array $before, array $after): bool
    {
        return $before !== $after;
    }

    private function clearDemoData(Request $request): bool
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
