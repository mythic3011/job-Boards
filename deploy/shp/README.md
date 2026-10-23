# Job Boards: small deployment with shard-project-helper

This is an **opt-in, parallel deployment path**, not a replacement for the legacy
`install.sh`/Blue Team VM runtime yet. Do not run both stacks on the same ports or
reuse/mutate production database volumes without a rehearsed backup and restore.

## Fresh install

Requires Docker Compose v2, Git, OpenSSL and curl on the host, and GitHub access to
the private [linux_setup](https://github.com/mythic3011/linux_setup) repository.

```bash
git clone git@github.com:mythic3011/linux_setup.git vendor/linux_setup
./deploy/shp/bootstrap.sh
# local-only by default:
curl -kfsS https://localhost/up
```

The bootstrap writes a dedicated `.env` using the template only **if no .env exists**.
It enables `tls-selfsigned`, `job-boards-app` and its PostgreSQL dependency.
It keeps `BIND_ADDRESS=127.0.0.1` for safe initial setup and generates a stable
Laravel key once. `./setup check` tests PHP 8.5, `/up` and the queue worker.

The architecture is:

```text
nginx (SHP edge, TLS/80/443) -> app (PHP 8.5 Apache :80)
                                      |
                                  PostgreSQL
                                      |
                               job-boards-worker
```

No default Redis, local CrowdSec, auth-service, or observability plane. Add those as
separate helper plugins once needed. Laravel uses PostgreSQL for sessions, cache and
database queue. Frontend assets and Composer dependencies are built into the image;
runtime source bind mounts are not required.

## Existing .env / migration from the legacy stack

If `.env` exists, bootstrap **does not overwrite it**. Do not copy legacy `.env`
blindly: `SHP` expects its own `PLUGINS`, `COMPOSE_FILE`, TLS keys and project ID.

1. Take consistent backups of the original DB, uploads, `.env`/APP_KEY,
   `APP_PREVIOUS_KEYS`, TLS files and every persistent volume. Stop old writes.
2. Store the legacy `.env` elsewhere with mode 600, away from the Docker build
   context. Start with the helper's `.env.example`, not the Laravel template.
3. Configure `PLUGINS=tls-selfsigned job-boards-app`, `PROJECT_NAME=job-boards`,
   `BIND_ADDRESS=127.0.0.1`; restore `JOB_BOARDS_APP_KEY` from the old `APP_KEY`
   and `JOB_BOARDS_APP_PREVIOUS_KEYS` from `APP_PREVIOUS_KEYS`.
4. Run `./deploy/shp/bootstrap.sh`. The first `up` adds plugin settings; inspect
   `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, and `APP_URL`.
5. Restore DB and `storage` into the NEW named volumes before accepting traffic,
   or configure a deliberate external migration. Verify user logins, uploaded CVs,
   existing encrypted data, queue jobs, redirects and mail integrations.

**There is no automatic data migration**. Previous Docker Compose volume names may
not match. A newly generated Laravel APP_KEY makes old encrypted data unreadable.
If key or DB state is uncertain, do not run `up` against existing production volumes.

## Day-to-day

```bash
./setup up            # reconcile, run migrations and health checks
./setup check         # health and security checks
./setup logs app
./setup logs job-boards-worker
./setup plugin list
./setup plugin enable observability   # opt in
./setup up
```

Keep `JOB_BOARDS_IMAGE` aligned with the Dockerfile's PHP 8.5 base. PHP 8.5 is
already required by `composer.json`; this deployment switches to the official
`php:8.5-apache-bookworm` image without Ondřej's Ubuntu PPA or Sail/Supervisor.
Use a new branch/PR to update versions and run a clean build; never edit production
`.env` or rotate keys as part of an image update.

## Before exposing publicly

Set `SERVER_NAME`, choose a trusted TLS plugin (e.g. Let's Encrypt), configure
real email delivery, review the /install first-admin flow, allowlist admin endpoints,
and apply any required bot/risk plugins. Test reverse proxy headers and external
authentication and run the application test suite. Use backups and an explicit
rollback plan for schema changes. `APP_DEBUG=false` is always enforced here.
See [plugin security notes](../../plugins/job-boards-app/security.md).
