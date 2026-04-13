# Jobs Boards

Laravel job board application with a split blue-team deployment contract:

- `compose.app.yml` is the app-plane runtime truth.
- `compose.obs.yml` is the obs-plane runtime truth.
- `compose.yaml` is local/dev convenience only. It is not evidence that the blue-team VM runtime contract changed.

## Local Bring-Up

For local testing, prepare the obs runtime artifacts first. The local install flow writes them under `.blue-team-vm/`.

```bash
./install.sh full dev
```

`install.sh full dev` remains the local convenience bring-up path. It now prepares obs runtime artifacts through the shared `ops/bootstrap/bootstrap-obs.sh` chain, but blue-team runtime contract evidence still comes from the split-plane compose files and verifiers below.
The installer starts the local convenience stack with `docker compose -f compose.yaml ...` and intentionally avoids `down --remove-orphans`, so it does not tear down a split-plane stack that is already running in the same workspace.

If you want to bring the split planes up manually, export the correct env files before each compose command.

App plane:

```bash
zsh -lc 'set -a; source .env; export BT_HONEYPOT_SOURCE="$(pwd)/docker/nginx/includes/blue-team-honeypot.conf"; set +a; docker compose -f compose.app.yml up -d'
```

Obs plane:

```bash
zsh -lc 'set -a; source .env; source .blue-team-vm/runtime/obs.generated.env; set +a; docker compose -f compose.obs.yml up -d'
```

The obs plane depends on final runtime values such as `GRAFANA_PASSWORD_FILE` and `PROMETHEUS_WEB_CONFIG_FILE`. A bare `docker compose` invocation without `.blue-team-vm/runtime/obs.generated.env` will fail interpolation and should be treated as missing runtime preparation, not as proof that the deployment contract is broken.

## Runtime Artifact Contract

Obs bootstrap renders and materializes final runtime artifacts before `compose.obs.yml` can be trusted:

- `.blue-team-vm/runtime/obs.generated.env`
- `.blue-team-vm/runtime/grafana-admin-password`
- `.blue-team-vm/rendered/prometheus.web-config.yml`

If those files are missing locally, regenerate them with:

```bash
BT_STATE_DIR="$(pwd)/.blue-team-vm" BT_COMPOSE_OBS_FILE="$(pwd)/compose.obs.yml" ops/bootstrap/bootstrap-obs.sh prepare
```

## Test Verification Paths

This project has two intentional test entrypoints:

- `composer test`: full default verification path
- `composer test:sqlite`: fast local sqlite-safe path

`composer test:sqlite` is not evidence that the full default or PostgreSQL path passed. Read [docs/runbooks/test-verification-paths.md](docs/runbooks/test-verification-paths.md) before treating sqlite-safe output as full verification.

## Verification

SQLite-safe anti-bot shadow review:

```bash
docker compose exec -T laravel.test php artisan anti-bot:shadow-review --hours=24 --json
```

Runbooks:

- [docs/runbooks/anti-bot-shadow-review.md](docs/runbooks/anti-bot-shadow-review.md)
- [docs/runbooks/anti-bot-shadow-review-template.md](docs/runbooks/anti-bot-shadow-review-template.md)

Focused honeypot and fingerprint contract bundle:

```bash
php artisan test \
  tests/Feature/AuthHoneypotContractTest.php \
  tests/Feature/HoneypotProtectionContractTest.php \
  tests/Feature/BannedPageProbeContractTest.php \
  tests/Feature/BotFingerprintTelemetryContractTest.php \
  tests/Feature/BotFingerprintProbeLogContractTest.php
```

Split-plane runtime verification:

```bash
BT_STATE_DIR="$(pwd)/.blue-team-vm" \
BT_HONEYPOT_SOURCE="$(pwd)/docker/nginx/includes/blue-team-honeypot.conf" \
./ops/bootstrap/bootstrap-app.sh verify

BT_STATE_DIR="$(pwd)/.blue-team-vm" \
./ops/bootstrap/bootstrap-obs.sh verify
```

On non-Linux local runtimes, `bootstrap-app.sh verify` will mark `app.host.local_ports` as `SKIPPED`. That is expected. Only the top-level `./setup-blue-team-vm.sh verify` flow is meant to prove host-kernel and host-port constraints inside the actual Linux blue-team VM.

## Deployment Notes

- Security-contract fixes for the blue-team VM must land in `compose.app.yml` or `compose.obs.yml` first.
- Updating `compose.yaml` alone does not update the blue-team VM runtime contract.
- If an operator, bootstrap, or smoke path still depends on `compose.yaml`, treat that as drift and fix the split-file path instead of extending the combined file.
