<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\SendPasswordResetLinkWithTwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TwoFactorService $twoFactorService
    ) {
    }

    /**
     * Send a password reset link with 2FA verification.
     */
    public function sendResetLink(Request $request, SendPasswordResetLinkWithTwoFactor $action): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        try {
            $result = $action($request->only('email', 'code', 'recovery_code'));
            $status = is_array($result) ? ($result['status'] ?? Password::RESET_LINK_SENT) : $result;

            if (is_array($result) && ($result['local'] ?? false) && !empty($result['token']) && !empty($result['email'])) {
                $resetUrl = url('/reset-password/' . $result['token']) . '?' . http_build_query([
                    'email' => $result['email'],
                ]);

                return redirect()->to($resetUrl)->with('status', __($status));
            }

            return back()->with('status', __($status));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred. Please try again.');
        }
    }

    /**
     * Reset password with enhanced security.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(3),
            ],
        ]);

        $user = User::where('email', $request->email)->first();

        // If user has 2FA enabled, require 2FA code for password reset
        // Verify 2FA if enabled
        if ($user && $this->twoFactorService->isEnabled($user)) {
            $request->validate([
                'two_factor_code' => ['required', 'string', 'size:6'],
            ]);

            // Verify 2FA code
            if (!$this->twoFactorService->verifyCode($user, $request->two_factor_code)) {
                return back()->withErrors([
                    'two_factor_code' => 'The provided two-factor authentication code is invalid.',
                ]);
            }
        }

        // Reset password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Log password reset completion
                $this->auditLogger->logBusinessEvent(
                    eventType: 'password_reset_completed',
                    request: $request,
                    targetType: 'user',
                    targetIdcode: $user->idcode,
                    meta: [
                        'email' => $user->email,
                        'two_factor_verified' => $user->two_factor_confirmed_at !== null,
                    ]
                );

                Log::info('Password reset completed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
