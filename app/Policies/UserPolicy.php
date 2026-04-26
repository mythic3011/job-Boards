<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->hasAdminUserPermission($actor, 'admin.users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $this->viewAny($actor);
    }

    public function forcePasswordReset(User $actor, User $target): Response
    {
        if (! $this->hasAdminUserPermission($actor, 'admin.users.force_password_reset')) {
            return Response::deny('This action is unauthorized.');
        }

        if ($actor->is($target)) {
            return Response::deny('You cannot force reset your own account.');
        }

        if ($this->isProtectedAdminTarget($actor, $target)) {
            return Response::deny('Privileged admin accounts require direct owner recovery.');
        }

        return Response::allow();
    }

    public function lock(User $actor, User $target): Response
    {
        if (! $this->hasAdminUserPermission($actor, 'admin.users.lock')) {
            return Response::deny('This action is unauthorized.');
        }

        if ($actor->is($target)) {
            return Response::deny('You cannot lock your own account.');
        }

        if ($this->isProtectedAdminTarget($actor, $target)) {
            return Response::deny('Privileged admin accounts cannot be locked from this flow.');
        }

        if ($this->isLastAdminAccount($target)) {
            return Response::deny('You cannot lock the last admin account.');
        }

        return Response::allow();
    }

    public function unlock(User $actor, User $target): Response
    {
        if (! $this->hasAdminUserPermission($actor, 'admin.users.unlock')) {
            return Response::deny('This action is unauthorized.');
        }

        return Response::allow();
    }

    public function delete(User $actor, User $target): Response
    {
        if (! $this->hasAdminUserPermission($actor, 'admin.users.delete')) {
            return Response::deny('This action is unauthorized.');
        }

        if ($actor->is($target)) {
            return Response::deny('You cannot delete your own account.');
        }

        if ($this->isProtectedAdminTarget($actor, $target)) {
            return Response::deny('Privileged admin accounts cannot be deleted from this flow.');
        }

        if ($this->isLastAdminAccount($target)) {
            return Response::deny('You cannot delete the last admin account.');
        }

        return Response::allow();
    }

    private function hasAdminUserPermission(User $actor, string $permission): bool
    {
        return $actor->isAdmin() && $actor->hasPermissionTo($permission);
    }

    private function isLastAdminAccount(User $target): bool
    {
        if (! $target->isAdmin()) {
            return false;
        }

        $adminCount = User::query()
            ->where('user_type', 'admin')
            ->count();

        return $adminCount <= 1;
    }

    private function isProtectedAdminTarget(User $actor, User $target): bool
    {
        return $target->isAdmin() && ! $actor->is($target);
    }
}
