# Security Evidence

## ZAP before/after summary

Target used for the latest local proof run:

- `https://jb.mythic3011.com/`
- scanner wrapper: `ops/demo/run-zap-baseline.sh`
- before report timestamp: `Mon, 4 May 2026 04:53:07`
- after report timestamp: `Mon, 4 May 2026 07:21:04`

The before scan reported 17 alert groups. The latest after scan reports 5 alert groups in the raw JSON report, all informational. The ZAP baseline policy demotes the expected informational groups to `INFO`, so the latest baseline run exits with `FAIL-NEW: 0`, `WARN-NEW: 0`, `INFO: 4`, `PASS: 63`.

## Findings fixed by code

| ZAP finding | Before | After | Code fix | How it works |
| --- | ---: | ---: | --- | --- |
| Cookie No HttpOnly Flag | 5 | 0 | `app/Http/Middleware/SuppressXsrfTokenCookie.php`, `bootstrap/app.php` | Laravel's JS-readable `XSRF-TOKEN` cookie is removed from web responses, while the protected `laravel-session` cookie remains `HttpOnly`. |
| X-Content-Type-Options Header Missing | 5 | 0 | `docker/nginx/nginx.conf` | Static and special nginx locations now set `X-Content-Type-Options: nosniff`; application responses already set it in middleware. |
| Strict-Transport-Security Header Not Set | 4 | 0 | `docker/nginx/nginx.conf`, `app/Http/Middleware/SecurityHeaders.php` | HSTS ownership moved to nginx so there is one TLS edge owner and no duplicate Laravel/nginx HSTS headers. |
| Permissions Policy Header Not Set | 4 | 0 | `docker/nginx/nginx.conf`, `app/Http/Middleware/SecurityHeaders.php` | Both app and nginx-served/static responses now include a restrictive `Permissions-Policy`. |
| Cross-Origin-Embedder-Policy Header Missing or Invalid | 3 | 0 | `app/Http/Middleware/SecurityHeaders.php`, `docker/nginx/nginx.conf` | Dynamic and nginx-served responses now include `Cross-Origin-Embedder-Policy: require-corp`. |
| Cross-Origin-Opener-Policy Header Missing or Invalid | 3 | 0 | `app/Http/Middleware/SecurityHeaders.php`, `docker/nginx/nginx.conf` | Dynamic and nginx-served responses now include `Cross-Origin-Opener-Policy: same-origin`. |
| Cross-Origin-Resource-Policy Header Missing or Invalid | 5 | 0 | `app/Http/Middleware/SecurityHeaders.php`, `docker/nginx/nginx.conf` | Dynamic and nginx-served/static responses now include `Cross-Origin-Resource-Policy: same-origin` so browser resources are same-origin by default. |
| CSP wildcard / `unsafe-inline` | 10 | 0 | `app/Http/Middleware/SecurityHeaders.php` | The CSP now uses per-request nonces for scripts/styles and removes broad wildcard-style allowances such as `https:` image sources. |
| Content Security Policy Header Not Set on `/sitemap.xml` 404 | 1 | 0 | `docker/nginx/nginx.conf` | The exact `/sitemap.xml` nginx location now sets a restrictive CSP even when the file is absent and nginx returns 404. |
| Big Redirect Detected | 2 | 0 | `app/Http/Controllers/HomeController.php`, `docker/nginx/nginx.conf` | Incomplete-install redirects now emit an empty redirect body, and sensitive installer query probes fail closed with an empty 404. |
| Information Disclosure - Sensitive Information in URL | 4 | 0 | `docker/nginx/nginx.conf` | Requests to `/install` with sensitive query keys are internally routed to an empty 404 with security headers instead of being redirected with the sensitive query preserved. |
| Re-examine Cache-control Directives | 4 | 1 | `app/Http/Middleware/SecurityHeaders.php` | Dynamic browser pages now use one explicit no-store cache contract: `max-age=0, must-revalidate, no-cache, no-store, private`, plus `Pragma: no-cache` and `Expires: 0`. The only remaining raw JSON instance is cacheable `/robots.txt`. |

## Findings intentionally not treated as code defects

| Remaining ZAP finding | Reason |
| --- | --- |
| Authentication Request Identified | `/login` is intentionally an authentication endpoint. |
| Session Management Response Identified | Laravel web/session behavior is expected on browser pages that need CSRF/session state. Removing the session cookie would break installer and browser flows. |
| Re-examine Cache-control Directives / Non-Storable Content / Storable and Cacheable Content | Dynamic browser pages now fail closed with no-store. Static assets and `/robots.txt` remain intentionally cacheable, so the residual raw JSON entries are scanner interpretation items rather than exploitable findings. |

## ZAP baseline policy

`ops/demo/zap-baseline-policy.conf` keeps expected informational detections visible as `INFO` instead of leaving them as `WARN-NEW`:

- `10015` cache-control review item
- `10049` cacheability classification for no-store pages and cacheable static assets
- `10111` intentional authentication endpoint
- `10112` intentional Laravel session-cookie behavior

## Runtime and scanner fixes

The scanner wrapper now runs ZAP on the same Docker network as nginx and adds a host override for the scanned hostname. This prevents the container from resolving `jb.mythic3011.com` to `127.0.0.1`, which would point back to the ZAP container instead of the app.

The scanner wrapper also copies the tracked ZAP baseline policy into each output directory and passes it to `zap-baseline.py` with `-c zap-baseline-policy.conf`, so the generated `zap.yaml` records the same policy used for the run.

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
