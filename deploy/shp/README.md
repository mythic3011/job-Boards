# Job Boards: one-command SHP deployment

This is an **opt-in** path. The old Blue Team VM / legacy Compose runtime is
unchanged. First-time local deployment (Docker Compose v2, Git with access to the private
`linux_setup` repository, OpenSSL):

```bash
./deploy.sh up
```

That's it. The script fetches a **reviewed/pinned** `linux_setup` revision into
ignored `.deploy/`, creates an SHP launcher, generates persistent credentials,
builds the PHP runtime, starts nginx + PostgreSQL + Laravel + queue worker,
runs migrations and verifies health. Default binding is **127.0.0.1 only**,
with self-signed TLS and no public DNS requirement.

## Daily operations

```bash
./deploy.sh check             # includes PHP 8.5.12 security floor
./deploy.sh status
./deploy.sh logs              # app
./deploy.sh logs job-boards-worker
./deploy.sh config            # validate merged Compose config, no secrets printed
./deploy.sh security          # Composer audit + Trivy (install trivy on host)
./deploy.sh down              # stop; never delete volumes
./deploy.sh up                # rebuild with --pull, restart, verify
```

`./deploy.sh init` prepares configuration without starting containers. Advanced
SHP commands are still available as `./setup plugin ...`, but are optional.
`deploy/shp/bootstrap.sh` remains a compatibility alias for `./deploy.sh up`.

## Private SHP repository in CI

The default GitHub Actions `GITHUB_TOKEN` cannot read a separate private
repository. To run integration tests, configure a fine-grained, read-only
`SHP_REPO_TOKEN` secret with contents:read for `mythic3011/linux_setup`,
and set repository variable `SHP_INTEGRATION_ENABLED=true`. Without both,
the integration job is skipped (not a passing runtime test); static checks run.

## Security and PHP patches

PHP 8.5.12 (released 21 October 2026) fixes two critical RCE issues:
CVE-2026-109618 and CVE-2026-109619. The Dockerfile uses a reviewed
`php:8.5.12-apache-bookworm` index digest and **fails builds** if PHP is
below 8.5.12 or outside PHP 8.5. `./deploy.sh check` also checks the running
version. A successful check **does not mean all PHP, Apache, Debian, Composer
or npm CVEs are fixed**. Run `./deploy.sh security` (Trivy + Composer audit)
and review its findings before exposing anything.

Patch upgrades are intentional code changes: update the Dockerfile and
`plugins/job-boards-app/env.example` to a verified new image digest, rebuild,
run integration tests, and promote. The pinned SHP revision must likewise be
updated by reviewed code change, not by silently tracking master.

## Migration from the old stack: manual, non-destructive

**Do not run the new deployment against existing production data without a
rehearsed backup and restore.** The new Compose project has separate volumes.
The script refuses to overwrite an existing Laravel `.env`.

1. Stop writes to the old stack. Back up PostgreSQL, uploads/storage, every
   persistent volume, `.env`, `APP_KEY`, `APP_PREVIOUS_KEYS` and TLS material.
2. Create a dedicated SHP `.env` from the template; keep old secrets outside
   the Docker build context. Set `JOB_BOARDS_APP_KEY` to the original
   `APP_KEY` and `JOB_BOARDS_APP_PREVIOUS_KEYS` to original previous keys.
3. Restore DB and storage into the new project volumes **before** accepting
   traffic. The new stack will run forward migrations; rehearse this on a copy.
4. Test existing logins, encrypted records, uploads, queue, email, admin
   installation controls and rollback. Do not publish both stacks on same ports.

**Public deployment is a separate change:** configure real TLS/DNS,
`SERVER_NAME`, email delivery, admin installer restrictions, backups, external
traffic tests and appropriate edge security. The default `MAIL_MAILER=log` is
not suitable for password-reset email delivery.

The application source and Composer dependencies are image-baked. Only the
shared `storage` volume persists; PostgreSQL uses a project-local `postgres-lite` plugin, without the
unnecessary exporter container. The full SHP postgres plugin is optional. Nginx is the only service publishing host ports. Redis, CrowdSec,
monitoring and other integrations remain optional.
