<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\PasswordLifecycleService;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private readonly PasswordLifecycleService $passwordLifecycleService
    ) {}

    public function update(User $user, array $input): void
    {
        $this->passwordLifecycleService->updateViaFortify($user, $input);
    }
}
