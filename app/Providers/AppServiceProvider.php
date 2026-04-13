<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\JobPosting;
use App\Policies\ApplicationPolicy;
use App\Policies\JobPostingPolicy;
use App\Services\AntiBot\ChallengeVerifier;
use App\Services\AntiBot\NullChallengeVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        JobPosting::class => JobPostingPolicy::class,
        Application::class => ApplicationPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ChallengeVerifier::class, NullChallengeVerifier::class);
        $this->app->alias(ChallengeVerifier::class, 'anti-bot.challenge-verifier');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureTrustedProxies();

        // Policies are auto-discovered via $policies array, but we can also register manually if needed
        // Gate::policy() calls are redundant when using $policies array

        // Admin gate - check if user has admin role
        Gate::define('admin.access', function ($user) {
            return $user->hasRole('admin');
        });

        RateLimiter::for('file-upload', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Stricter rate limiting for suspicious user agents
        RateLimiter::for('suspicious-ua', function (Request $request) {
            $path = Str::lower(ltrim($request->path(), '/'));
            $isHighRisk = Str::is(['admin', 'admin/*', 'install', 'install/*', 'login'], $path);

            if ($isHighRisk) {
                // High-risk paths: 5 requests per 10 minutes
                return Limit::perMinutes(10, 5)->by($request->ip());
            }
            
            // Normal paths: 20 requests per 5 minutes
            return Limit::perMinutes(5, 20)->by($request->ip());
        });

        Event::listen(JobFailed::class, function (JobFailed $event) {
            Log::error('Queue job failed', [
                'connection' => $event->connectionName,
                'queue' => $event->job?->getQueue(),
                'job' => $event->job?->resolveName(),
                'payload' => $event->job?->payload(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }

    private function configureTrustedProxies(): void
    {
        // Configure trusted proxies after the config repository is available.
        TrustProxies::flushState();

        $trustedProxies = config('app.trusted_proxies', []);

        if ($trustedProxies !== []) {
            TrustProxies::at($trustedProxies);
        }

        TrustProxies::withHeaders($this->trustedProxyHeaders());
    }

    private function trustedProxyHeaders(): int
    {
        return match (config('app.trusted_proxy_headers', 'x_forwarded')) {
            'forwarded' => SymfonyRequest::HEADER_FORWARDED,
            'aws_elb' => SymfonyRequest::HEADER_X_FORWARDED_AWS_ELB,
            'traefik' => SymfonyRequest::HEADER_X_FORWARDED_TRAEFIK,
            default => SymfonyRequest::HEADER_X_FORWARDED_FOR
                | SymfonyRequest::HEADER_X_FORWARDED_HOST
                | SymfonyRequest::HEADER_X_FORWARDED_PORT
                | SymfonyRequest::HEADER_X_FORWARDED_PROTO,
        };
    }
}
