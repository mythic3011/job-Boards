<?php

namespace App\Services\AntiBot;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;

readonly class RiskContext
{
    public const PENDING_LOGIN_MISSING = 'missing';
    public const PENDING_LOGIN_VALID = 'valid';
    public const PENDING_LOGIN_MALFORMED = 'malformed';
    public const PENDING_LOGIN_EXPIRED = 'expired';
    public const PENDING_LOGIN_NOT_APPLICABLE = 'not_applicable';

    public function __construct(
        public string $surface,
        public ?string $routeName,
        public string $method,
        public string $path,
        public string $ip,
        public bool $secure,
        public string $host,
        public ?string $sessionId,
        public ?string $userAgent,
        public ?string $actorUserId,
        public ?string $actorUserIdcode,
        public bool $pendingLoginExpected,
        public bool $pendingLoginFlow,
        public string $pendingLoginState,
        public ?string $pendingLoginUserId,
    ) {}

    public static function fromRequest(Request $request, string $surface): self
    {
        [$pendingLoginExpected, $pendingLoginFlow, $pendingLoginState, $pendingLoginUserId] = self::resolvePendingLoginState($request, $surface);

        return new self(
            surface: $surface,
            routeName: $request->route()?->getName(),
            method: $request->method(),
            path: $request->path(),
            ip: (string) $request->ip(),
            secure: $request->secure(),
            host: (string) $request->getHost(),
            sessionId: $request->hasSession() ? $request->session()->getId() : null,
            userAgent: $request->userAgent(),
            actorUserId: $request->user()?->getKey(),
            actorUserIdcode: $request->user()?->idcode,
            pendingLoginExpected: $pendingLoginExpected,
            pendingLoginFlow: $pendingLoginFlow,
            pendingLoginState: $pendingLoginState,
            pendingLoginUserId: $pendingLoginUserId,
        );
    }

    public function toAuditMeta(): array
    {
        return array_filter([
            'surface' => $this->surface,
            'route_name' => $this->routeName,
            'method' => $this->method,
            'path' => $this->path,
            'request_ip' => $this->ip,
            'request_secure' => $this->secure,
            'request_host' => $this->host,
            'session_id' => $this->sessionId,
            'pending_login_expected' => $this->pendingLoginExpected,
            'pending_login_flow' => $this->pendingLoginFlow,
            'pending_login_state' => $this->pendingLoginState,
            'pending_login_user_id' => $this->pendingLoginUserId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{bool, bool, string, ?string}
     */
    private static function resolvePendingLoginState(Request $request, string $surface): array
    {
        if (! in_array($surface, ['login', 'two_factor'], true)) {
            return [false, false, self::PENDING_LOGIN_NOT_APPLICABLE, null];
        }

        $pendingLoginExpected = $surface === 'two_factor';

        if (! $request->hasSession()) {
            return [$pendingLoginExpected, $pendingLoginExpected, self::PENDING_LOGIN_MISSING, null];
        }

        $pendingLoginId = $request->session()->get('login.id');
        if ($pendingLoginId === null) {
            return [$pendingLoginExpected, $pendingLoginExpected, self::PENDING_LOGIN_MISSING, null];
        }

        if (! is_string($pendingLoginId) || trim($pendingLoginId) === '') {
            return [$pendingLoginExpected, true, self::PENDING_LOGIN_MALFORMED, null];
        }

        $model = app(StatefulGuard::class)->getProvider()->getModel();
        $pendingUser = $model::find($pendingLoginId);

        if ($pendingUser === null) {
            return [$pendingLoginExpected, true, self::PENDING_LOGIN_EXPIRED, null];
        }

        return [$pendingLoginExpected, true, self::PENDING_LOGIN_VALID, (string) $pendingUser->getKey()];
    }
}
