# Jobs Boards

Laravel job board application.

Local default flow:

- run `docker compose up -d --build` from repo root
- `compose.yaml` runs `app-bootstrap-init` to ensure PHP deps (`vendor/`) and frontend build artifacts (`public/build/manifest.json`) exist before app services start
- `compose.yaml` runs `obs-bootstrap-init` and prepares required obs runtime artifacts before starting auth-service/Prometheus/Grafana
- `compose.app.yml` and `compose.obs.yml` remain advanced/debug split-plane surfaces

## Remote Deployment Targets

Repo-first remote deployment now lives under `ops/deploy/`:

- `ops/deploy/vps-deploy.sh jb.mythic3011.com [git-ref]`
  - production-style VPS target
  - keeps the app behind host nginx on loopback high ports
- `ops/deploy/vps-deploy.sh from-env [git-ref]`
  - generic reverse-proxy target
  - builds a production-style profile from `TARGET_*` environment variables
- `ops/deploy/vps-deploy.sh lab-env [git-ref]`
  - reusable lab target
  - keeps the app nginx on loopback high ports so host-level reverse proxying can own `80/443`
  - supports DHCP or static subnet provisioning through env overrides

Example generic reverse-proxy deploy:

```bash
export TARGET_DOMAIN=demo.example.com
export TARGET_HOST=203.0.113.10
export TARGET_REMOTE_ROOT=/opt/jobs-boards-demo
export TARGET_COMPOSE_PROJECT_NAME=jobs-boards-demo
export TARGET_SSH_PORT=22
./ops/deploy/vps-deploy.sh from-env main
```

The `from-env` target uses the shared builder and derives these defaults unless overridden:

- `DEPLOY_APP_URL=https://${TARGET_DOMAIN}`
- `DEPLOY_ASSET_URL=https://${TARGET_DOMAIN}`
- `DEPLOY_NGINX_CERT_DOMAIN=${TARGET_NGINX_CERT_DOMAIN:-${TARGET_DOMAIN}}`
- `DEPLOY_NGINX_CERT_DIR=/etc/nginx/cert/${DEPLOY_NGINX_CERT_DOMAIN}`
- `DEPLOY_NGINX_CERT_PATH=${TARGET_NGINX_CERT_PATH:-/etc/nginx/cert/${DEPLOY_NGINX_CERT_DOMAIN}/cert.pem}`
- `DEPLOY_NGINX_KEY_PATH=${TARGET_NGINX_KEY_PATH:-/etc/nginx/cert/${DEPLOY_NGINX_CERT_DOMAIN}/key.pem}`
- `DEPLOY_NGINX_PROXY_PASS=https://127.0.0.1:${TARGET_APP_SSL_PORT##*:}/`

Reverse-proxy TLS consumption modes:

- `TARGET_TLS_MODE=cloudflare-origin` (default)
  - consumes origin cert/key from `/etc/nginx/cert/${TARGET_NGINX_CERT_DOMAIN:-${TARGET_DOMAIN}}/`
  - suitable when Cloudflare terminates the public certificate and the VPS only needs an origin certificate
- `TARGET_TLS_MODE=letsencrypt`
  - consumes `/etc/letsencrypt/live/${TARGET_NGINX_CERT_DOMAIN:-${TARGET_DOMAIN}}/fullchain.pem`
  - consumes `/etc/letsencrypt/live/${TARGET_NGINX_CERT_DOMAIN:-${TARGET_DOMAIN}}/privkey.pem`
  - suitable when the host itself presents the public certificate and Certbot/systemd handles renewal outside the app deploy workflow
- `TARGET_TLS_MODE=custom`
  - provide `TARGET_NGINX_CERT_PATH` and `TARGET_NGINX_KEY_PATH` explicitly when neither default layout applies

Optional builder overrides:

- `TARGET_NGINX_CERT_DOMAIN`
  - use when the deploy hostname and certificate hostname differ
  - example: deploy `jb.mythic3011.com` while consuming `/etc/nginx/cert/mythic3011.com/...`
- `TARGET_NGINX_CERT_PATH_TEMPLATE`
- `TARGET_NGINX_KEY_PATH_TEMPLATE`
  - support reusable path layouts via `{domain}` placeholder expansion
  - example: `TARGET_NGINX_CERT_PATH_TEMPLATE=/srv/tls/{domain}/fullchain.pem`

Host firewall / TLS policy inputs:

- `BT_HOST_TLS_MODE=cloudflare-origin|letsencrypt-http01|letsencrypt-dns01|custom`
- `BT_ALLOW_HTTP_REDIRECT=1` keeps `80/tcp` open for redirect unless the chosen TLS mode already requires it
- `letsencrypt-http01` requires `80/tcp` for Certbot renewal
- `letsencrypt-dns01` can run with `BT_ALLOW_HTTP_REDIRECT=0` if you do not want public HTTP exposure

Lab target inputs:

- `LAB_DEPLOY_HOST`
- `LAB_DEPLOY_DOMAIN=jobs-board.lab`
- `LAB_CONFIGURE_NETPLAN=true|false`
- `LAB_WAN_IFACE=eth0`
- `LAB_WAN_MODE=dhcp|static`
- `LAB_WAN_ADDRESS=158.132.209.50/24` when using `static`
- `LAB_WAN_GATEWAY=158.132.209.28` when using `static`
- `LAB_WAN_DNS=1.1.1.1,8.8.8.8`
- `LAB_LAN_IFACE=eth1`
- `LAB_LAN_ADDRESS=192.168.153.2/24`
- `LAB_NETPLAN_APPLY=true` only when you explicitly want the deploy workflow to rewrite/apply guest netplan

Example lab runs:

```bash
LAB_DEPLOY_HOST=192.168.153.2 \
LAB_DEPLOY_DOMAIN=jobs-board.lab \
LAB_CONFIGURE_NETPLAN=true \
ops/deploy/vps-deploy.sh lab-env main

LAB_DEPLOY_HOST=192.168.153.2 \
LAB_DEPLOY_DOMAIN=jobs-board.lab \
LAB_CONFIGURE_NETPLAN=true \
LAB_WAN_MODE=static \
LAB_WAN_IFACE=ens18 \
LAB_WAN_ADDRESS=192.168.153.2/24 \
LAB_WAN_GATEWAY=192.168.153.1 \
LAB_NETPLAN_APPLY=true \
ops/deploy/vps-deploy.sh lab-env main
```

`LAB_NETPLAN_APPLY=true` is intentionally explicit because applying a new subnet/gateway can interrupt the active SSH session.

## Security Demo Workflow

Keep deployment and demo evidence separate:

- VPS HTTPS evidence:
  - deploy a reverse-proxy target such as `jb.mythic3011.com` or `from-env`
  - verify the public URL through `https://www.whynopadlock.com/index.html`
  - verify the public certificate grade through `https://www.ssllabs.com/ssltest`
  - if the site is fronted by Cloudflare CDN, those public checks validate the Cloudflare edge certificate; the origin cert mode still has to match the VPS reverse-proxy contract
- Web vulnerability evidence:
  - use external ZAP containers, not app deploy bootstrap
  - capture one baseline report for the pre-remediation revision and one report for the remediated revision
  - the reusable wrapper is `ops/demo/run-zap-baseline.sh`

Example before/after ZAP run:

```bash
ops/demo/collect-security-demo-evidence.sh deployed https://jb.mythic3011.com demo-artifacts/security-demo
ops/demo/run-zap-baseline.sh before https://jb.mythic3011.com demo-artifacts/zap
ops/demo/run-zap-baseline.sh after https://jb.mythic3011.com demo-artifacts/zap
```

See [docs/runbooks/security-demo.md](docs/runbooks/security-demo.md) for the full evidence checklist.
Host TLS/firewall mode details live in [docs/runbooks/host-tls-modes.md](docs/runbooks/host-tls-modes.md).

## Local Bring-Up

For local testing, use:

```bash
docker compose up -d --build
```

`install.sh full dev` remains available as a wrapper path, but the normal local operator flow is direct `docker compose up`.

The local convenience bootstrap now treats these five values as the host-bound port contract:

- `APP_PORT`
- `APP_SSL_PORT`
- `VITE_PORT`
- `FORWARD_DB_PORT`
- `FORWARD_REDIS_PORT`

`bootstrap-env.sh` persists missing defaults for that full set and silently reassigns any occupied local port into `3001-9001` before its final audit. `install.sh` checks the same five variables before starting the combined stack and offers to rewrite blocked `.env` entries into the same range. That rewrite is local convenience only. It does not relax the blue-team VM proof requirement that real host `80/443` ownership be cleared before app-plane bootstrap.

For normal local bring-up, use the combined compose surface only:

```bash
docker compose up -d --build
```

`compose.yaml` now includes an `obs-bootstrap-init` one-shot service that prepares obs runtime artifacts before auth-service, Prometheus, and Grafana start.

Split-plane manual commands remain an advanced/debug path only. They are not the normal local operator flow.
The split-plane shell entrypoints now treat `app-plane` as a shared external network. `./ops/app/05-compose-up.sh` and `./ops/bootstrap/bootstrap-obs.sh apply` will create the default `${COMPOSE_PROJECT_NAME:-jobs-borads}_app-plane` when it is absent and only auto-detect an existing `*_app-plane` network when its subnet matches the fixed `172.29.0.0/24` contract used by the compose files. Raw `docker compose -f compose.app.yml ...` and `docker compose -f compose.obs.yml ...` still do not perform that detection or creation for you.
If your local app plane is owned by another compose project or worktree, export `BT_APP_PLANE_NETWORK_NAME=<existing_app_plane_network>` before manual split-plane compose commands. Non-default app-plane subnets are not supported by the current compose contract because nginx and trusted-proxy bindings are pinned to `172.29.0.x`.
Auth-service healthchecks in both `compose.yaml` and `compose.obs.yml` probe `http://127.0.0.1:3000/health` as a single fixed container-side authority. Host-shell `PORT` no longer affects the compose startup gate.
Resolver-consumer precedence applies to shell/bootstrap wrappers only: explicit shell env wins, generated obs runtime env overrides source-layer `.env`, and source-layer `.env` only fills missing values. Compose keeps Docker's own interpolation and container-environment precedence rules.

## Runtime Artifact Contract

Obs bootstrap renders and materializes final runtime artifacts before `compose.obs.yml` can be trusted:

- `.blue-team-vm/runtime/obs.generated.env`
- `.blue-team-vm/runtime/grafana-admin-secret`
- `.blue-team-vm/rendered/prometheus.web-config.yml`

If those files are missing locally, regenerate them with:

```bash
BT_STATE_DIR="$(pwd)/.blue-team-vm" BT_COMPOSE_OBS_FILE="$(pwd)/compose.obs.yml" ./ops/bootstrap/bootstrap-obs.sh prepare
```

Obs path derivation authority now lives at `ops/config/config-contract.yml`.
That file keeps a `.yml` path for contract stability, but its contents must be strict JSON text because `ops/bin/resolve-config-contract` parses it with Python stdlib `json`.
YAML comments, unquoted keys, and other YAML-only syntax are invalid and will fail the resolver.

## Test Verification Paths

This project has three intentional test entrypoints across two authority levels:

- `composer test`: full default verification path, using direct PHPUnit
- `composer test:worktree`: full default verification path for a git worktree, with a guard that rejects symlinked or missing `vendor/`
- `composer test:sqlite`: fast local sqlite-safe path

`composer test:sqlite` is not evidence that the full default or PostgreSQL path passed. Read [docs/runbooks/test-verification-paths.md](docs/runbooks/test-verification-paths.md) before treating sqlite-safe output as full verification.
If you are working in a git worktree, do not symlink `vendor/` from another checkout; use a real worktree-local dependency install and `composer test:worktree` instead.

For a running local stack, prefer `docker exec jobs-boards-laravel.test composer test` over bare `docker compose exec ...`; it avoids re-interpolating the combined compose file when obs runtime artifacts are already mounted. When project shell wrappers do invoke Compose, that resolver-consumer precedence remains explicit shell exports first, generated obs runtime artifacts second, and source-layer `.env` last for missing values only.

## Verification

SQLite-safe anti-bot shadow review:

```bash
docker exec jobs-boards-laravel.test php artisan anti-bot:shadow-review --hours=24 --json
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
./ops/bootstrap/bootstrap-app.sh verify

BT_STATE_DIR="$(pwd)/.blue-team-vm" \
./ops/bootstrap/bootstrap-obs.sh verify
```

On non-Linux local runtimes, `bootstrap-app.sh verify` will mark `app.host.local_ports` as `SKIPPED`. That is expected. Only the top-level `./setup-blue-team-vm.sh verify` flow is meant to prove host-kernel and host-port constraints inside the actual Linux blue-team VM.
`bootstrap-obs.sh verify` expects the app plane to have already produced the shared nginx/log surfaces. If app services are down, `obs.logs.read_only_mount = FAIL` is a real precondition failure rather than an obs-plane compose regression.

## Clean VM Proof Planning

The clean-room proof workflow is specified in [docs/plans/2026-04-13-clean-vm-proof-plan.md](docs/plans/2026-04-13-clean-vm-proof-plan.md). That plan is intentionally stricter than local bring-up:

- the only public operator entrypoint is `ops/proof/pd-cleanvm-proof.sh`
- `guest-install-deps.sh` and `guest-blue-team-proof.sh` are staged internal helpers from the resolved commit only
- proof source is a commit-only archive, not the current mutable workspace
- host and guest responsibilities are split; the host owns snapshot control and final `result.json`
- proof bundles must export only metadata-safe projections of generated obs artifacts
- an operational run may use TOFU SSH trust, but a proof-grade pass requires pinned SSH host identity

The host-authored clean-room `result.json` is the grading surface. At minimum it records:

- `ssh_identity_mode`
- `ssh_host_key_algorithm`
- `assurance_level`
- `proof_status`
- `artifact_status`
- `restore_status`
- `overall_status`

Guest-side proof execution now assumes a sudo-capable SSH user and fails fast if the VM cannot satisfy:

- `sudo -n true`
- `sudo -n docker info`

The guest-side flow also normalizes the smoke/runtime boundary:

- `guest-install-deps.sh` prepares the current SSH user for non-root smoke execution through the `docker` group
- `ops/smoke/run-all.sh` is executed from the extracted repo root with explicit `RUNNER`, `APP_COMPOSE_FILE`, and `OBS_COMPOSE_FILE`
- compose state evidence is collected through `ops/lib/common.sh` / `bt_compose`, not by bypassing the split-plane runtime env contract with raw `docker compose`

The host proof bundle now collects metadata-safe guest evidence under `guest-output/`, including:

- `guest-output/guest-fragment.json`
- `guest-output/obs-runtime-metadata.json`
- `guest-output/10-os-release.txt`
- `guest-output/11-uname.txt`
- `guest-output/12-docker-version.txt`
- `guest-output/13-docker-compose-version.txt`
- `guest-output/14-compose-app-ps.txt`
- `guest-output/15-compose-obs-ps.txt`
- `guest-output/16-systemctl-docker.txt`

Raw VM-local obs runtime files such as `obs.generated.env` and `obs.generated-secrets.jsonl` remain inside the guest and must not be copied into the host bundle.

## Deployment Notes

- Security-contract fixes for the blue-team VM must land in `compose.app.yml` or `compose.obs.yml` first.
- Updating `compose.yaml` alone does not update the blue-team VM runtime contract.
- If an operator, bootstrap, or smoke path still depends on `compose.yaml`, treat that as drift and fix the split-file path instead of extending the combined file.
