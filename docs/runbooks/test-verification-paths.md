# Test Verification Paths

This repository has three intentional verification entrypoints across two authority levels.

- `composer test` and `composer test:worktree` are full-default verification paths.
- `composer test:sqlite` is the sqlite-safe verification path.

## Full Default Verification

`composer test` is the authoritative full-default path.

Contract:

- clears Laravel config cache first
- runs `vendor/bin/phpunit` directly
- uses the default database/runtime from `phpunit.xml`
- is the required path before claiming full verification

Use this path when:

- validating production-relevant runtime contracts
- checking PostgreSQL-backed behavior
- preparing merge or reviewer-ready verification evidence

## SQLite-Safe Verification

`composer test:sqlite` is a fast local safety path.

Contract:

- runs a curated sqlite-safe suite only
- does not stand in for full PostgreSQL/default-runtime verification
- is useful for tight local iteration on suites that explicitly opt into sqlite-safe setup

`composer test:sqlite` passing does not imply `composer test` passing.

## Worktree Container Verification

When running the full-default path from a git worktree inside a one-off Docker container, this repository's symlink layout matters:

- `.env` in the worktree resolves to `../../.env`
- `public/build` in the worktree resolves to `../../../public/build`
- `vendor/` in the worktree must be a real worktree-local directory, not a symlink to another checkout

If those symlink targets are not mounted into the container, verification will fail for environment/bootstrap reasons instead of product behavior.

Do not symlink `vendor/` from the root checkout into a worktree. Composer resolves `App\\` from the checkout that owns `vendor/`, so a shared `vendor/` tree can make `vendor/bin/phpunit` (or any command using that `vendor/autoload.php`) load the wrong app tree. Use `composer test:worktree` after installing dependencies in the worktree itself.

Use this shape for worktree-local full-default verification:

```bash
ROOT=$(cd ../.. && pwd)
COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME:-jobs-borads}

docker run --rm \
  --network "${COMPOSE_PROJECT_NAME}_app-plane" \
  --add-host postgres:172.29.0.2 \
  --add-host redis:172.29.0.4 \
  --env-file .env \
  -e APP_ENV=testing \
  -e DB_CONNECTION=pgsql \
  -e DB_DATABASE=testing \
  -e DB_HOST=postgres \
  -e REDIS_HOST=redis \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e PULSE_ENABLED=false \
  -e TELESCOPE_ENABLED=false \
  -e NIGHTWATCH_ENABLED=false \
  -e BCRYPT_ROUNDS=4 \
  -v "$PWD":/var/www/html \
  -v "$ROOT/.env":/var/.env:ro \
  -v "$ROOT/public/build":/var/public/build:ro \
  -w /var/www/html \
  --entrypoint composer \
  sail-8.5/app \
  test
```

Notes:

- the extra `/var/.env` mount satisfies the worktree `.env` symlink target
- the extra `/var/public/build` mount satisfies the worktree `public/build` symlink target
- if your local Docker network uses different IPs or service discovery, adjust `--add-host` or prefer `docker compose exec` from the runtime project that owns the app-plane network

## Review Rule

When reporting verification status, use one of these exact buckets:

- `full default verification passed`
- `sqlite-safe verification passed`
- `full default verification pending`

Do not collapse sqlite-safe results into full verification claims.
