<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\UserRegistrationService;
use Illuminate\Http\UploadedFile;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly UserRegistrationService $registrationService
    ) {
    }

    /**
     * Validate and create a newly registered user.
     */
    public function create(array $input): User
    {
        // Ensure profile image is an UploadedFile (supports Livewire temp uploads and classic forms)
        if (
            !isset($input['profile_image'])
            || !$input['profile_image'] instanceof UploadedFile
        ) {
            $input['profile_image'] = request()->file('profile_image');
        }

        return $this->registrationService->register($input, request());
    }
}
