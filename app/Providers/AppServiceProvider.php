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
            return $user->isAdmin();
        });

        RateLimiter::for('file-upload', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('bot-fingerprint-probe', function (Request $request) {
            $probe = (string) $request->query('probe', 'unknown');

            return Limit::perMinute(12)->by($request->ip().'|'.$probe);
        });

        Event::listen(JobFailed::class, function (JobFailed $event) {
            Log::error('Queue job failed', $this->queueFailureLogContext($event));
        });
    }

    /**
     * Keep queue failure logs operationally useful without dumping raw payloads.
     *
     * @return array<string, mixed>
     */
    private function queueFailureLogContext(JobFailed $event): array
    {
        $payload = $event->job?->payload();

        return [
            'connection' => $event->connectionName,
            'queue' => $event->job?->getQueue(),
            'job' => $event->job?->resolveName(),
            'job_uuid' => is_array($payload) ? ($payload['uuid'] ?? null) : null,
            'job_display_name' => is_array($payload) ? ($payload['displayName'] ?? null) : null,
            'job_max_tries' => is_array($payload) ? ($payload['maxTries'] ?? null) : null,
            'job_payload_keys' => is_array($payload) ? array_keys($payload) : [],
            'job_data_keys' => is_array($payload['data'] ?? null) ? array_keys($payload['data']) : [],
            'exception' => $event->exception->getMessage(),
        ];
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
