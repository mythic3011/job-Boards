<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

class AdminUserSecurityService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * @return array{reset_url: string, reset_user_name: string}
     *
     */
    public function forcePasswordReset(User $actor, string $targetUserId): array
    {
        $target = $this->findUserOrFail($targetUserId);

        Gate::forUser($actor)->authorize('forcePasswordReset', $target);

        $token = Password::broker()->createToken($target);
        Cache::put('admin_reset:'.$token, true, now()->addMinutes(60));

        $this->auditLogger->logBusinessEvent(
            eventType: 'user.force_password_reset',
            request: request(),
            targetType: 'user',
            targetIdcode: $target->idcode,
            meta: [
                'user_email' => $target->email,
                'admin_id' => $actor->id,
                'admin_initiated' => true,
            ],
            actorUserId: $actor->id,
            actorType: 'user',
        );

        return [
            'reset_url' => url('/reset-password/'.$token).'?'.http_build_query(['email' => $target->email]),
            'reset_user_name' => $target->nickname,
        ];
    }

    /**
     */
    public function lockUser(User $actor, string $targetUserId): void
    {
        $target = $this->findUserOrFail($targetUserId);

        Gate::forUser($actor)->authorize('lock', $target);

        $before = $target->locked_until?->toDateTimeString();
        $target->forceFill(['locked_until' => now()->addDays(30)])->save();
        $this->dashboardService->clearCache();

        $this->auditLogger->logBusinessEvent(
            eventType: 'user.locked',
            request: request(),
            targetType: 'user',
            targetIdcode: $target->idcode,
            meta: [
                'before' => $before,
                'after' => $target->locked_until?->toDateTimeString(),
                'locked_until' => $target->locked_until?->toDateTimeString(),
                'user_email' => $target->email,
                'user_nickname' => $target->nickname,
            ],
            actorUserId: $actor->id,
            actorType: 'user',
        );
    }

    public function unlockUser(User $actor, string $targetUserId): void
    {
        $target = $this->findUserOrFail($targetUserId);

        Gate::forUser($actor)->authorize('unlock', $target);

        $before = $target->locked_until?->toDateTimeString();
        $target->forceFill(['locked_until' => null])->save();
        $this->dashboardService->clearCache();

        $this->auditLogger->logBusinessEvent(
            eventType: 'user.unlocked',
            request: request(),
            targetType: 'user',
            targetIdcode: $target->idcode,
            meta: [
                'before' => $before,
                'after' => null,
                'user_email' => $target->email,
                'user_nickname' => $target->nickname,
            ],
            actorUserId: $actor->id,
            actorType: 'user',
        );
    }

    public function toggleLock(User $actor, string $targetUserId): string
    {
        $target = $this->findUserOrFail($targetUserId);

        if ($target->isLocked()) {
            $this->unlockUser($actor, $target->id);

            return 'unlocked';
        }

        $this->lockUser($actor, $target->id);

        return 'locked';
    }

    public function deleteUser(User $actor, string $targetUserId): void
    {
        $target = $this->findUserOrFail($targetUserId);

        Gate::forUser($actor)->authorize('delete', $target);

        $this->auditLogger->logBusinessEvent(
            eventType: 'user.deleted',
            request: request(),
            targetType: 'user',
            targetIdcode: $target->idcode,
            meta: [
                'user_email' => $target->email,
                'user_nickname' => $target->nickname,
                'deleted_at' => now()->toDateTimeString(),
            ],
            actorUserId: $actor->id,
            actorType: 'user',
        );

        $target->delete();
        $this->dashboardService->clearCache();
    }

    private function findUserOrFail(string $targetUserId): User
    {
        return User::query()->findOrFail($targetUserId);
    }
}
