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

    public function forcePasswordReset(User $actor, User $target): bool
    {
        return $this->hasAdminUserPermission($actor, 'admin.users.force_password_reset');
    }

    public function lock(User $actor, User $target): bool
    {
        return $this->hasAdminUserPermission($actor, 'admin.users.lock');
    }

    public function unlock(User $actor, User $target): bool
    {
        return $this->hasAdminUserPermission($actor, 'admin.users.unlock');
    }

    public function delete(User $actor, User $target): Response
    {
        if (! $this->hasAdminUserPermission($actor, 'admin.users.delete')) {
            return Response::deny('This action is unauthorized.');
        }

        if ($actor->is($target)) {
            return Response::deny('You cannot delete your own account.');
        }

        return Response::allow();
    }

    private function hasAdminUserPermission(User $actor, string $permission): bool
    {
        return $actor->isAdmin() && $actor->hasPermissionTo($permission);
    }
}
