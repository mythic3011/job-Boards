<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __construct(
        private readonly UserRegistrationService $registrationService
    ) {
    }

    /**
     * Handle user registration.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $user = $this->registrationService->register($request->all(), $request);
            
            // Log the user in automatically
            Auth::login($user);
            
            // If 2FA was enabled during registration, redirect to 2FA setup
            if (!empty($request->input('enable_2fa'))) {
                return redirect()->route('profile.two-factor')
                    ->with('success', 'Welcome! Your account has been created. Please complete your two-factor authentication setup.');
            }
            
            return redirect()->intended('/')
                ->with('success', 'Welcome to the platform, ' . $user->nickname . '! Your account has been created successfully.');
                
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput($request->except('password', 'password_confirmation'));
        } catch (\Exception $e) {
            \Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except('password', 'password_confirmation'),
            ]);
            
            return back()
                ->withErrors(['error' => 'Registration failed. Please try again.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }
}

