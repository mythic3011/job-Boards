<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserRegistrationService
{
    public function __construct(
        private readonly ProfileImageService $profileImageService,
        private readonly AuditLogger $auditLogger,
        private readonly TwoFactorService $twoFactorService
    ) {}

    /**
     * Register a new user with validation and security features.
     */
    public function register(array $data, Request $request): User
    {
        if (
            (! isset($data['profile_image']) || ! $data['profile_image'] instanceof UploadedFile)
            && $request->hasFile('profile_image')
        ) {
            $data['profile_image'] = $request->file('profile_image');
        }

        $this->validateRegistrationData($data);

        $storedProfileImagePath = null;

        try {
            return DB::transaction(function () use ($data, $request, &$storedProfileImagePath): User {
                $user = $this->createUser($data);

                $storedProfileImagePath = $this->handleProfileImage($user, $data);
                $this->assignUserRole($user, $data['user_type']);

                if (! empty($data['enable_2fa'])) {
                    $this->twoFactorService->enable($user);
                }

                $this->logRegistration($user, $request);

                return $user;
            });
        } catch (\Throwable $e) {
            if ($storedProfileImagePath !== null) {
                $this->profileImageService->deleteImage($storedProfileImagePath);
            }

            throw $e;
        }
    }

    /**
     * Validate registration data.
     */
    private function validateRegistrationData(array $data): void
    {
        Validator::make($data, [
            'login_id' => [
                'required',
                'string',
                'max:255',
                'alpha_dash', // Only letters, numbers, dashes, and underscores
                Rule::unique(User::class, 'login_id'),
            ],
            'nickname' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_.]+$/', // Alphanumeric with basic punctuation
            ],
            'email' => [
                'required',
                'string',
                'email:rfc', // RFC validation without DNS lookup
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'user_type' => [
                'required',
                'string',
                Rule::in(['company', 'individual']),
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(3), // Check against known breached passwords
            ],
            'profile_image' => [
                'nullable',
                'image',
                'max:2048', // 2MB max
                'mimetypes:'.implode(',', ProfileImageService::ALLOWED_MIME_TYPES),
            ],
            'enable_2fa' => [
                'nullable',
                'boolean',
            ],
        ])->validate();
    }

    /**
     * Create the user record.
     */
    private function createUser(array $data): User
    {
        return User::create([
            'login_id' => $data['login_id'],
            'nickname' => $data['nickname'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'user_type' => $data['user_type'],
            'registration_state' => ! empty($data['enable_2fa'])
                ? User::REGISTRATION_STATE_PENDING_2FA
                : User::REGISTRATION_STATE_ACTIVE,
        ]);
    }

    /**
     * Handle profile image upload if provided.
     */
    private function handleProfileImage(User $user, array $data): ?string
    {
        if (
            isset($data['profile_image'])
            && $data['profile_image'] instanceof UploadedFile
            && $data['profile_image']->isValid()
        ) {
            $path = $this->profileImageService->storeImage($data['profile_image']);

            try {
                $user->update(['profile_image_path' => $path]);
            } catch (\Throwable $e) {
                $this->profileImageService->deleteImage($path);

                throw $e;
            }

            return $path;
        }

        return null;
    }

    /**
     * Assign appropriate role to the user.
     * Best-effort only: registration must not fail if the role is missing or assignment fails.
     */
    private function assignUserRole(User $user, string $userType): void
    {
        try {
            $role = Role::where('name', $userType)->where('guard_name', 'web')->first();
            if ($role) {
                $user->assignRole($role);
            } else {
                Log::warning('Registration: role not found, skipping assignment', [
                    'user_id' => $user->id,
                    'user_type' => $userType,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Registration: failed to assign role', [
                'user_id' => $user->id,
                'user_type' => $userType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log the registration event.
     * Best-effort only: registration must not fail if logging fails.
     */
    private function logRegistration(User $user, Request $request): void
    {
        try {
            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'user_type' => $user->user_type,
                'registration_state' => $user->registration_state,
                'two_factor_enabled' => $user->isRegistrationPending(),
                'has_profile_image' => $user->profile_image_path !== null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $this->auditLogger->logBusinessEvent(
                eventType: 'user_registered',
                request: $request,
                targetType: 'user',
                targetIdcode: $user->idcode,
                meta: [
                    'user_type' => $user->user_type,
                    'registration_state' => $user->registration_state,
                    'two_factor_requested' => $user->isRegistrationPending(),
                    'has_profile_image' => $user->profile_image_path !== null,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Registration: audit logging failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get validation rules for password.
     */
    public static function getPasswordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(3),
        ];
    }
}
