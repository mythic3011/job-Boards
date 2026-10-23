# SHP Job Boards security boundary

## Patched PHP baseline

PHP 8.5.12 (21 October 2026) patches CVE-2026-109618 and CVE-2026-109619,
both critical remote-code-execution vulnerabilities affecting earlier 8.5
patch versions. Official advisory:
https://www.php.net/archive/2026.php

- The runtime base is `php:8.5.12-apache-bookworm` with a reviewed index digest.
- Build fails on PHP <8.5.12, or outside the PHP 8.5 branch.
- SHP `check` rejects a running container below the same patch floor.
- `deploy.sh security` runs Composer audit and a Trivy HIGH/CRITICAL scan.
- Rebuild with `--pull` when deploying reviewed patches. Pinned digests
  require a deliberate version update for later PHP or Debian fixes.

**Not a clean-CVE guarantee.** OS libraries, Apache, Composer dependencies,
npm packages and the pinned base image may still have vulnerabilities. A
version floor proves only that the named PHP fixes are included.

## Operational controls and residual risk

- Only SHP nginx publishes ports. PHP/Apache and PostgreSQL use the internal
  Compose edge network; no direct host port for the application.
- The document root is `public/`; directory listing is disabled.
- Laravel `APP_DEBUG=false`, secure cookies and a persistent 256-bit APP_KEY.
- Sessions/cache/queue use PostgreSQL; no Redis requirement.
- `TRUSTED_PROXIES=REMOTE_ADDR` trusts the direct peer. Only nginx should
  reach the app; other containers on the edge network remain in the trust
  boundary. Review this before enabling untrusted plugins.
- Composer dependencies are built into the image. App migrations run at
  startup and fail closed, but are not zero-downtime or automatically reversible.
- Docker admins can inspect container environment secrets. Protect Docker
  access and the chmod-600 SHP `.env`; consider Docker secrets for production.
- The default local-only self-signed TLS and `MAIL_MAILER=log` are **not**
  production public-site configuration.
- No automatic migration or backup of legacy PostgreSQL/storage/APP_KEY.
- Previous CrowdSec, auth-service, external mail, and observability controls
  are not automatically reproduced. Enable explicitly after threat review.
