<?php

namespace App\Http\Controllers;

use App\Services\ProfileService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly TwoFactorService $twoFactorService
    ) {
    }

    /**
     * Show the user profile page.
     */
    public function show(): View
    {
        $user = Auth::user();
        $profileData = $this->profileService->getProfileData($user);

        return view('profile.show', $profileData);
    }

    /**
     * Show the profile edit form.
     */
    public function edit(): View
    {
        $user = Auth::user();
        $profileData = $this->profileService->getProfileData($user);

        return view('profile.edit', $profileData);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $payload = [
                'nickname' => $request->input('nickname'),
                'email' => $request->input('email'),
            ];

            if ($request->hasFile('profile_image')) {
                $payload['profile_image'] = $request->file('profile_image');
            }

            // Log the incoming data for debugging
            \Log::info('Profile update attempt', [
                'user_id' => $user->id,
                'data_keys' => array_keys($payload),
                'nickname' => $payload['nickname'],
                'email' => $payload['email'],
                'has_profile_image' => array_key_exists('profile_image', $payload),
            ]);

            $this->profileService->updateProfile($user, $payload, $request);

            return redirect()->route('profile.show')
                ->with('success', 'Profile updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Profile update validation failed', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
            ]);
            
            // NOT: keep profile img if fail
            return back()
                ->withErrors($e->errors())
                ->withInput($request->except(['profile_image']));
        } catch (\Exception $e) {
            \Log::error('Profile update failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withErrors(['error' => 'Profile update failed. Please try again.'])
                ->withInput($request->except(['profile_image']));
        }
    }

    /**
     * Show the password change form.
     */
    public function showPasswordForm(): View
    {
        $user = Auth::user();
        $profileData = $this->profileService->getProfileData($user);

        return view('profile.password', $profileData);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $payload = [
                'current_password' => $request->input('current_password'),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
            ];

            if ($request->has('two_factor_code')) {
                $payload['two_factor_code'] = $request->input('two_factor_code');
            }

            $this->profileService->updatePassword($user, $payload, $request);

            return redirect()->route('profile.show')
                ->with('success', 'Password updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors(), 'updatePassword');
        } catch (\Exception $e) {
            \Log::error('Password update failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withErrors(['error' => 'Password update failed. Please try again.'], 'updatePassword');
        }
    }

    /**
     * Delete the user's profile image.
     */
    public function deleteProfileImage(Request $request): RedirectResponse
    {
        try {
            $user = Auth::user();
            $this->profileService->deleteProfileImage($user, $request);

            return back()->with('success', 'Profile image deleted successfully.');

        } catch (\Exception $e) {
            \Log::error('Profile image deletion failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete profile image.']);
        }
    }
}
