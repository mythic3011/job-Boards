<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\PasswordLifecycleService;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(
        private readonly PasswordLifecycleService $passwordLifecycleService
    ) {}

    public function reset(User $user, array $input): void
    {
        $this->passwordLifecycleService->resetViaFortify($user, $input);
    }
}
