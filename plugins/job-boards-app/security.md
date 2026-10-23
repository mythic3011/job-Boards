# Job Boards application (PHP 8.5)

## Mitigates

- Keeps PHP 8.5 on an official, supported Docker image instead of adding an Ubuntu PHP PPA.
- Only the helper's nginx publishes ports. Apache is reachable on the internal edge network.
- The Laravel document root is `public/`; private project code and .env are not web-served.
- Application key is generated once and stored in the project-owned, chmod-600 .env.
- Debug mode is disabled, secure cookies are required and migrations fail closed.
- Database, session, cache and queue share PostgreSQL; no default Redis dependency.

## Trade-offs and gaps

- The starter flow binds nginx to loopback for first install. Set up trusted TLS/DNS and restrict
  `/install` before making the stack public; the installer itself is application-owned.
- `MAIL_MAILER=log` is for local setup, not delivery of real password-reset emails.
- The edge network is a shared Docker bridge, and in-network PostgreSQL TLS is disabled.
- Docker Compose environment variables (including APP_KEY and database credentials) are
  accessible to Docker administrators through inspect; host/Docker access is privileged.
- `TRUSTED_PROXIES=REMOTE_ADDR` trusts the immediate peer only. Never publish the app port directly.
- Automatic `migrate --force` is forward-only, NOT rollback or zero-downtime schema migration.
- Volume backup, uploads restore, payment/email integrations, app-specific security checks, and
  real production traffic validation remain the operator's responsibility.
- The legacy CrowdSec/Auth/observability components are not automatically reproduced.
  Enable equivalent helper plugins after reviewing their behavior and risk contracts.
