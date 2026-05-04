# Security Evidence

## ZAP before/after summary

Target used for the latest local proof run:

- `https://jb.mythic3011.com/`
- scanner wrapper: `ops/demo/run-zap-baseline.sh`
- before report timestamp: `Mon, 4 May 2026 04:53:07`
- after report timestamp: `Mon, 4 May 2026 06:45:57`

The before scan reported 17 alert groups. The after scan reports 7 alert groups. The remaining groups are either expected application behavior for public auth/session pages or scanner-policy items that need explicit demo policy handling.

## Findings fixed by code

| ZAP finding | Before | After | Code fix | How it works |
| --- | ---: | ---: | --- | --- |
| Cookie No HttpOnly Flag | 5 | 0 | `app/Http/Middleware/SuppressXsrfTokenCookie.php`, `bootstrap/app.php` | Laravel's JS-readable `XSRF-TOKEN` cookie is removed from web responses, while the protected `laravel-session` cookie remains `HttpOnly`. |
| X-Content-Type-Options Header Missing | 5 | 0 | `docker/nginx/nginx.conf` | Static and special nginx locations now set `X-Content-Type-Options: nosniff`; application responses already set it in middleware. |
| Strict-Transport-Security Header Not Set | 4 | 0 | `docker/nginx/nginx.conf`, `app/Http/Middleware/SecurityHeaders.php` | HSTS ownership moved to nginx so there is one TLS edge owner and no duplicate Laravel/nginx HSTS headers. |
| Permissions Policy Header Not Set | 4 | 0 | `docker/nginx/nginx.conf`, `app/Http/Middleware/SecurityHeaders.php` | Both app and nginx-served/static responses now include a restrictive `Permissions-Policy`. |
| Cross-Origin-Embedder-Policy Header Missing or Invalid | 3 | 0 | `app/Http/Middleware/SecurityHeaders.php`, `docker/nginx/nginx.conf` | Dynamic and nginx-served responses now include `Cross-Origin-Embedder-Policy: require-corp`. |
| Cross-Origin-Opener-Policy Header Missing or Invalid | 3 | 0 | `app/Http/Middleware/SecurityHeaders.php`, `docker/nginx/nginx.conf` | Dynamic and nginx-served responses now include `Cross-Origin-Opener-Policy: same-origin`. |
| CSP wildcard / `unsafe-inline` | 10 | 0 | `app/Http/Middleware/SecurityHeaders.php` | The CSP now uses per-request nonces for scripts/styles and removes broad wildcard-style allowances such as `https:` image sources. |
| Big Redirect Detected | 2 | 0 | `app/Http/Controllers/HomeController.php`, `docker/nginx/nginx.conf` | Incomplete-install redirects now emit an empty redirect body, and sensitive installer query probes fail closed with an empty 404. |
| Information Disclosure - Sensitive Information in URL | 4 | 0 | `docker/nginx/nginx.conf` | Requests to `/install` with sensitive query keys are internally routed to an empty 404 with security headers instead of being redirected with the sensitive query preserved. |

## Findings intentionally not treated as code defects

| Remaining ZAP finding | Reason |
| --- | --- |
| Authentication Request Identified | `/login` is intentionally an authentication endpoint. |
| Session Management Response Identified | Laravel web/session behavior is expected on browser pages that need CSRF/session state. Removing the session cookie would break installer and browser flows. |
| Re-examine Cache-control Directives / Non-Storable Content / Storable and Cacheable Content | These are scanner interpretation items for mixed public pages, auth pages, and static assets. They should be governed by demo policy thresholds rather than blindly changing cache semantics. |
| Content Security Policy Header Not Set on `/sitemap.xml` 404 | This is an nginx/app 404 edge case; it is low-value for the demo target but can be handled by adding CSP to all generated 404 responses if needed. |
| Cross-Origin-Resource-Policy Header Missing or Invalid | The app already sets COEP/COOP. CORP can be added if the demo policy requires it, but it can affect asset embedding behavior and should be treated as a compatibility decision. |

## Runtime and scanner fixes

The scanner wrapper now runs ZAP on the same Docker network as nginx and adds a host override for the scanned hostname. This prevents the container from resolving `jb.mythic3011.com` to `127.0.0.1`, which would point back to the ZAP container instead of the app.

The local TLS path now supports `SSL_MODE=custom` for externally issued certificates such as ZeroSSL. The custom mode copies an operator-provided fullchain and key into the nginx runtime SSL directory and renders the nginx SSL include to that path.

## Verification commands

```bash
php artisan test tests/Feature/SecurityHeadersCspContractTest.php
php artisan test tests/Feature/NginxMonitoringAccessContractTest.php
php artisan test tests/Feature/HomeDashboardUiContractTest.php --filter=incomplete_setup_home_redirect
php artisan test tests/Feature/NginxSslModeContractTest.php tests/Feature/NginxSslBootstrapRuntimeContractTest.php
php artisan test tests/Feature/SecurityDemoShellContractsTest.php
node --test docker/auth-service/test/packaging-contract.test.js
ops/demo/run-zap-baseline.sh after https://jb.mythic3011.com/ demo-artifacts/zap
```
