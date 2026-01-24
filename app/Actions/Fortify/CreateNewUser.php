<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\ProfileImageService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private readonly ProfileImageService $profileImageService
    ) {
    }

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'login_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'login_id'),
            ],
            'nickname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'user_type' => ['required', 'string', Rule::in(['company', 'individual'])],
            'password' => $this->passwordRules(),
            // optional: profile image upload
            // mime types: jpeg, png, webp, gif that for avoid fake file extension attack
            'profile_image' => [
                'nullable',
                'image',
                'max:2048',
                'mimetypes:' . implode(',', ProfileImageService::ALLOWED_MIME_TYPES),
            ],
        ])->validate();

        $user = User::create([
            'idcode' => 'user_' . Str::uuid()->toString(),
            'login_id' => $input['login_id'],
            'nickname' => $input['nickname'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'user_type' => $input['user_type'],
        ]);

        // Store the profile image in private storage (not public)
        if (isset($input['profile_image']) && $input['profile_image']->isValid()) {
            $path = $this->profileImageService->storeImage($input['profile_image']);
            $user->update(['profile_image_path' => $path]);
        }

        // Assign role based on user type
        $user->assignRole($input['user_type']);

        return $user;
    }
}
