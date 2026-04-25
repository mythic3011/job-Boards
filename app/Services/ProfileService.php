<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileService
{
    private const SENSITIVE_AUDIT_FIELDS = ['nickname', 'email'];

    private const REDACTED_AUDIT_CHANGE = [
        'changed' => true,
        'sensitive' => true,
    ];

    public function __construct(
        private readonly ProfileImageService $profileImageService,
        private readonly TwoFactorService $twoFactorService,
        private readonly AuditLogger $auditLogger,
        private readonly PasswordLifecycleService $passwordLifecycleService
    ) {}

    /**
     * Update user profile information.
     */
    public function updateProfile(User $user, array $data, Request $request): User
    {
        // Prepare data for validation - remove empty profile_image
        $validationData = $data;
        if (isset($validationData['profile_image']) && empty($validationData['profile_image'])) {
            unset($validationData['profile_image']);
        }

        $this->validateProfileData($user, $validationData);

        $originalData = $user->only(['nickname', 'email', 'profile_image_path']);

        // Handle profile image upload
        if (isset($data['profile_image']) && $data['profile_image'] instanceof UploadedFile && $data['profile_image']->isValid()) {
            $this->handleProfileImageUpdate($user, $data['profile_image']);
        }

        // Update basic profile information
        $user->update([
            'nickname' => $data['nickname'],
            'email' => $data['email'],
        ]);

        $this->logProfileUpdate($user, $originalData, $request);

        return $user->fresh();
    }

    /**
     * Update user password with security checks.
     */
    public function updatePassword(User $user, array $data, Request $request): void
    {
        $this->passwordLifecycleService->updateViaProfile($user, $data);

        $this->logPasswordUpdate($user, $request);
    }

    /**
     * Delete user profile image.
     */
    public function deleteProfileImage(User $user, Request $request): void
    {
        if ($user->profile_image_path) {
            $this->profileImageService->deleteImage($user->profile_image_path);
            $user->update(['profile_image_path' => null]);

            $this->auditLogger->logBusinessEvent(
                eventType: 'profile_image_deleted',
                request: $request,
                targetType: 'user',
                targetIdcode: $user->idcode,
                meta: ['user_id' => $user->id]
            );

            Log::info('Profile image deleted', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Get user profile data for display.
     */
    public function getProfileData(User $user): array
    {
        $userData = $user->only([
            'id', 'idcode', 'login_id', 'nickname', 'email',
            'user_type', 'profile_image_path', 'created_at',
        ]);

        // Add formatted user type label
        $userData['user_type_label'] = $user->getUserTypeLabel();

        return [
            'user' => $userData,
            'two_factor_enabled' => $this->twoFactorService->isEnabled($user),
            'has_profile_image' => ! empty($user->profile_image_path),
            'profile_image_url' => $user->profile_image_path
                ? $this->profileImageService->getImageUrl($user->profile_image_path)
                : null,
        ];
    }

    /**
     * Validate profile update data.
     */
    private function validateProfileData(User $user, array $data): void
    {
        $rules = [
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
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ];

        // Only add profile_image validation if it's present in the data
        if (isset($data['profile_image'])) {
            $rules['profile_image'] = [
                'nullable',
                'image',
                'max:2048', // 2MB max
                'mimetypes:'.implode(',', ProfileImageService::ALLOWED_MIME_TYPES),
            ];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            \Log::warning('Profile validation failed', [
                'user_id' => $user->id,
                'errors' => $validator->errors()->toArray(),
                'data_keys' => array_keys($data),
            ]);
        }

        $validator->validate();
    }

    /**
     * Handle profile image upload and deletion of old image.
     */
    private function handleProfileImageUpdate(User $user, UploadedFile $image): void
    {
        $oldPath = $user->profile_image_path;

        try {
            $newPath = $this->profileImageService->storeImage($image);
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile_image' => [$e->getMessage()],
            ]);
        }

        try {
            $user->update(['profile_image_path' => $newPath]);
        } catch (\Throwable $e) {
            $this->profileImageService->deleteImage($newPath);
            throw $e;
        }

        if ($oldPath) {
            $this->profileImageService->deleteImage($oldPath);
        }
    }

    /**
     * Log profile update event.
     */
    private function logProfileUpdate(User $user, array $originalData, Request $request): void
    {
        $changes = [];
        $currentData = $user->only(['nickname', 'email', 'profile_image_path']);

        foreach ($currentData as $key => $value) {
            if ($originalData[$key] !== $value) {
                $changes[$key] = $this->formatAuditChange(
                    field: $key,
                    from: $originalData[$key],
                    to: $value,
                );
            }
        }

        if (! empty($changes)) {
            $this->auditLogger->logBusinessEvent(
                eventType: 'profile_updated',
                request: $request,
                targetType: 'user',
                targetIdcode: $user->idcode,
                meta: [
                    'user_id' => $user->id,
                    'changes' => $changes,
                ]
            );

            Log::info('User profile updated', [
                'user_id' => $user->id,
                'changes' => array_keys($changes),
                'ip' => $request->ip(),
            ]);
        }
    }

    /**
     * Keep audit semantics for changed fields without persisting raw PII.
     */
    private function formatAuditChange(string $field, mixed $from, mixed $to): array
    {
        if (in_array($field, self::SENSITIVE_AUDIT_FIELDS, true)) {
            return self::REDACTED_AUDIT_CHANGE;
        }

        return [
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Log password update event.
     */
    private function logPasswordUpdate(User $user, Request $request): void
    {
        $this->auditLogger->logBusinessEvent(
            eventType: 'password_updated',
            request: $request,
            targetType: 'user',
            targetIdcode: $user->idcode,
            meta: [
                'user_id' => $user->id,
                'two_factor_verified' => $this->twoFactorService->isEnabled($user),
            ]
        );

        Log::info('User password updated', [
            'user_id' => $user->id,
            'two_factor_verified' => $this->twoFactorService->isEnabled($user),
            'ip' => $request->ip(),
        ]);
    }
}
