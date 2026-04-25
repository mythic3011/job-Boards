<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\SendPasswordResetLinkWithTwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PasswordLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PasswordLifecycleService $passwordLifecycleService
    ) {}

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

        // Skip 2FA check if this reset was admin-initiated
        $adminInitiated = $user && Cache::pull('admin_reset:'.$request->token);

        if ($user) {
            try {
                $this->passwordLifecycleService->assertResetAllowed(
                    $user,
                    $request->input('two_factor_code'),
                    $adminInitiated
                );
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors());
            }
        }

        // Reset password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request, $adminInitiated) {
                $this->passwordLifecycleService->setPassword($user, $password);

                $twoFactorVerified = $user->two_factor_confirmed_at !== null;

                // Log password reset completion
                $this->auditLogger->logBusinessEvent(
                    eventType: 'password_reset_completed',
                    request: $request,
                    targetType: 'user',
                    targetIdcode: $user->idcode,
                    meta: [
                        'admin_initiated' => $adminInitiated,
                        'two_factor_verified' => $twoFactorVerified,
                    ]
                );

                Log::info('Password reset completed', [
                    'user_id' => $user->id,
                    'admin_initiated' => $adminInitiated,
                    'two_factor_verified' => $twoFactorVerified,
                    'ip' => $request->ip(),
                ]);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
