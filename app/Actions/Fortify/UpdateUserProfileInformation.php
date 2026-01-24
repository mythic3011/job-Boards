<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\ProfileImageService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private readonly ProfileImageService $profileImageService
    ) {
    }

    /**
     * Update the user's profile information.
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'nickname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'profile_image' => [
                'nullable',
                'image',
                'max:2048',
                'mimetypes:' . implode(',', ProfileImageService::ALLOWED_MIME_TYPES),
            ],
        ])->validateWithBag('updateProfileInformation');

        // Delete old profile image if new one is being uploaded
        if (isset($input['profile_image']) && $input['profile_image']->isValid()) {
            $this->profileImageService->deleteImage($user->profile_image_path);
            $path = $this->profileImageService->storeImage($input['profile_image']);
            $input['profile_image_path'] = $path;
        }

        $user->forceFill([
            'nickname' => $input['nickname'],
            'email' => $input['email'],
            'profile_image_path' => $input['profile_image_path'] ?? $user->profile_image_path,
        ])->save();
    }
}
