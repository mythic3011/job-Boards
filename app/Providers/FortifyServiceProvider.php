<?php

namespace App\Providers;

use App\Actions\Fortify\AuthenticateUser;
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\LoginResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register login and register views
        Fortify::loginView(function () {
            return view('components.auth.login');
        });

        Fortify::registerView(function () {
            return view('components.auth.register');
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Use custom authentication with account locking
        // Resolve from container to get dependency injection
        Fortify::authenticateUsing(function (Request $request) {
            return app(AuthenticateUser::class)->__invoke($request);
        });

        // Rate limiting for login (per username/email + IP)
        RateLimiter::for('login', function (Request $request) {
            $identifier = $request->input(Fortify::username());
            $throttleKey = Str::transliterate(Str::lower($identifier ?? '').'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Rate limiting for registration (per IP)
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        // Rate limiting for password reset (per email + IP)
        RateLimiter::for('password-reset', function (Request $request) {
            $email = $request->input('email');
            $throttleKey = Str::transliterate(Str::lower($email ?? '').'|'.$request->ip());

            return Limit::perHour(3)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
