<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\SendPasswordResetLinkWithTwoFactor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Send a password reset link with 2FA verification.
     */
    public function sendResetLink(Request $request, SendPasswordResetLinkWithTwoFactor $action)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        try {
            $status = $action($request->only('email', 'code', 'recovery_code'));

            return back()->with('status', $status);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred. Please try again.');
        }
    }
}
