# Blue Team VM Runtime Map

This document explains the runtime state rooted at `.blue-team-vm/` for local checkouts and `/var/lib/blue-team-vm/` for host-style runs.

## Environment Map

- Repo-local checkout
  Canonical local state root is `.blue-team-vm/`.
  Default producer paths come from `bootstrap-env.sh`, `ops/bootstrap/bootstrap-obs.sh`, and `ops/bootstrap/bootstrap-nginx-ssl.sh`.
- Linux VM host runtime
  Canonical host state root is usually `/var/lib/blue-team-vm/`.
  The structure matches the local state tree, but it is used by host bootstrap and live VM runs.
- Container runtime views
  App and auth-service containers usually consume the repo or host runtime through mounted paths such as `/var/www/html/.blue-team-vm/...` or `/var/lib/blue-team-vm/runtime/...`.
  Nginx consumes rendered include and SSL material through container paths such as `/etc/nginx/generated/*.conf` and `/etc/nginx/ssl/*`.
- Deploy target override
  Some deploy flows override the state root, for example `/opt/jobs-borads-demo/state`.
  The structure stays the same; only the root path changes.

## Quick Routing

- If you need shell-safe app or bootstrap values
  Check `.blue-team-vm/runtime/compat.shell.env`.
- If you need obs-plane derived env or rendered artifact paths
  Check `.blue-team-vm/runtime/obs.generated.env`.
- If you need Prometheus or Grafana runtime provisioning
  Check `.blue-team-vm/rendered/`.
- If you need nginx runtime access or SSL includes
  Check `.blue-team-vm/runtime/rendered/`.
- If you need machine-only secrets, audit logs, or cert/key material
  Check `.blue-team-vm/runtime/`, but treat those files as machine-managed state.

## Bootstrap Layer

The compose stack does not expect every long-running service to self-bootstrap. A small set of bootstrap and init services prepares the runtime state first.

- `app-bootstrap-init`
  Prepares a runnable Laravel environment. It runs `bootstrap-env.sh prepare`, prepares Laravel runtime directories, and fills missing dependencies such as `composer install` or the frontend build when needed.
- `obs-bootstrap-init`
  Prepares observability runtime artifacts. It maps directly to `ops/bootstrap/bootstrap-obs.sh`, which validates required observability env, creates runtime and rendered directories, renders `prometheus.web-config.yml`, prepares `grafana.datasources.yml`, and writes derived runtime outputs such as `obs.generated.env`.
- Other one-time init services
  `auth-service-logs-init`, `grafana-data-init`, and `crowdsec-key-init` clear the path for the long-running services by preparing directories, permissions, or security material and then exiting.

## Service Layers

### Core Business

- `nginx`
  External entrypoint. Handles web traffic and mounts runtime-generated SSL and monitoring include files.
- `laravel.test`
  Main application service running the Laravel web app.
- `queue-worker`
  Background job worker for Laravel queues.
- `postgres`
  Primary database.
- `redis`
  Fast storage for cache and queue-related state.

### One-Time Init

- `app-bootstrap-init`
  Business-layer startup prerequisite.
- `obs-bootstrap-init`
  Observability-layer startup prerequisite.
- `auth-service-logs-init`
  Auth-service log-volume permission setup.
- `grafana-data-init`
  Grafana data-volume permission setup.
- `crowdsec-key-init`
  CrowdSec bouncer-key setup.

### Observability

- `prometheus`
  Scrapes metrics from exporters and service endpoints.
- `nginx-exporter`
  Converts nginx status into Prometheus metrics.
- `loki`
  Stores logs.
- `promtail`
  Reads nginx, Laravel, auth-service, and CrowdSec logs and ships them to Loki.
- `grafana`
  Dashboard UI for metrics and logs. Uses Prometheus, Loki, and a Postgres datasource.

### Security/Supporting

- `auth-service`
  Node service for monitoring and auth-related logic. Consumes `.blue-team-vm/runtime/compat.shell.env` and `obs.generated.env`.
- `crowdsec`
  Security analysis service that reads nginx and Laravel logs and drives bouncer or policy behavior.
- `crowdsec-key-init`
  One-time key bootstrap for the CrowdSec security path.

## Service Inventory

| Service | Layer | Compose file | Depends on | Main state files |
| --- | --- | --- | --- | --- |
| `app-bootstrap-init` | One-time init | `compose.yaml` | none | `.env`, `.blue-team-vm/runtime/compat.shell.env`, `.blue-team-vm/runtime/pr2a.generated.json` |
| `obs-bootstrap-init` | One-time init | `compose.yaml`, `compose.obs.yml` | `app-bootstrap-init` in `compose.yaml`; none in `compose.obs.yml` | `.blue-team-vm/runtime/obs.generated.env`, `.blue-team-vm/runtime/obs.generated-secrets.jsonl`, `.blue-team-vm/rendered/prometheus.web-config.yml`, `.blue-team-vm/rendered/grafana.datasources.yml` |
| `nginx` | Core business | `compose.yaml` | `laravel.test`, `crowdsec-key-init` | `.blue-team-vm/runtime/rendered/ssl-mode.conf`, `.blue-team-vm/runtime/rendered/monitoring-geo.conf`, `.blue-team-vm/runtime/rendered/monitoring-access.conf`, `.blue-team-vm/runtime/nginx-ssl/*` |
| `laravel.test` | Core business | `compose.yaml` | `postgres`, `redis` | `storage/`, Laravel runtime dirs, app `.env` |
| `queue-worker` | Core business | `compose.yaml` | `postgres`, `redis` | app `.env`, Laravel runtime dirs |
| `postgres` | Core business | `compose.yaml` | none | Docker volume `sail-postgres` |
| `redis` | Core business | `compose.yaml` | none | Docker volume `sail-redis` |
| `auth-service-logs-init` | One-time init | `compose.yaml`, `compose.obs.yml` | `obs-bootstrap-init` in `compose.yaml`; none in `compose.obs.yml` | Docker volume `auth-service-logs` |
| `auth-service` | Security/supporting | `compose.yaml`, `compose.obs.yml` | `obs-bootstrap-init`, `auth-service-logs-init` | `.blue-team-vm/runtime/compat.shell.env`, `.blue-team-vm/runtime/obs.generated.env`, Docker volume `auth-service-logs` |
| `crowdsec` | Security/supporting | `compose.yaml` | `laravel.test` | Docker volumes `crowdsec-db`, `crowdsec-config`, `crowdsec-keys`, `crowdsec-logs`; nginx and Laravel logs |
| `crowdsec-key-init` | One-time init | `compose.yaml` | `crowdsec` healthy | Docker volume `crowdsec-keys` |
| `prometheus` | Observability | `compose.yaml`, `compose.obs.yml` | `obs-bootstrap-init` | `.blue-team-vm/rendered/prometheus.web-config.yml`, Docker volume `prometheus-data` |
| `nginx-exporter` | Observability | `compose.yaml` | `nginx` | nginx status endpoint |
| `loki` | Observability | `compose.yaml`, `compose.obs.yml` | none | Docker volume `loki-data` |
| `promtail` | Observability | `compose.yaml`, `compose.obs.yml` | `loki` | Docker volume `promtail-positions`, nginx/Laravel/auth-service/CrowdSec logs |
| `grafana-data-init` | One-time init | `compose.yaml`, `compose.obs.yml` | none | Docker volume `grafana-data` |
| `grafana` | Observability | `compose.yaml`, `compose.obs.yml` | `obs-bootstrap-init`, `prometheus`, `grafana-data-init` | `.blue-team-vm/rendered/grafana.datasources.yml`, `.blue-team-vm/runtime/grafana-admin-secret`, Docker volume `grafana-data` |

## Quick Mental Model

- Logs path
  `nginx`, `laravel.test`, `auth-service`, `crowdsec` -> `promtail` -> `loki` -> `grafana`
- Metrics path
  `nginx` status -> `nginx-exporter` -> `prometheus` -> `grafana`
- Security path
  `nginx` and `laravel.test` logs -> `crowdsec` -> bouncer or policy behavior
- Bootstrap path
  `app-bootstrap-init` and `obs-bootstrap-init` -> generated runtime files under `.blue-team-vm/` -> long-running services mount and consume them

## File Index

| Path | Environment role | Produced by | Consumed by | Notes |
| --- | --- | --- | --- | --- |
| `.blue-team-vm/runtime/compat.shell.env` | Repo-local or host state root compatibility layer | `./bootstrap-env.sh prepare <lab|demo|production|reset-demo>` | app bootstrap, container entrypoints, shell consumers that need quoted assignments | Highest-signal file when you want to know what app/runtime values this environment should use. |
| `.blue-team-vm/runtime/obs.generated.env` | Obs-plane derived runtime env | `./ops/bootstrap/bootstrap-obs.sh prepare|apply` | obs compose/bootstrap flows, resolver consumers | Tracks derived values such as rendered file paths and auth hashes. |
| `.blue-team-vm/runtime/pr2a.generated.json` | Machine-readable persisted prepare state | `./bootstrap-env.sh prepare ...` | prepare/bridge logic only | Not the first place for operator inspection; use `compat.shell.env` first. |
| `.blue-team-vm/runtime/grafana-admin-secret` | Secret materialized from monitoring password | `./bootstrap-env.sh prepare ...` and obs bootstrap follow-through | Grafana runtime | Plaintext secret file. No inline comments. |
| `.blue-team-vm/runtime/obs.generated-secrets.jsonl` | Audit/provenance log for generated secrets | `./ops/bootstrap/bootstrap-obs.sh prepare|apply` | proof/audit tooling | JSONL only. No comments. |
| `.blue-team-vm/rendered/prometheus.web-config.yml` | Obs rendered config | `./ops/bootstrap/bootstrap-obs.sh prepare|apply` | Prometheus container | Runtime basic-auth config. |
| `.blue-team-vm/rendered/grafana.datasources.yml` | Obs rendered config | `./ops/bootstrap/bootstrap-obs.sh prepare|apply` | Grafana container | Datasource provisioning for Prometheus, Loki, and Postgres. |
| `.blue-team-vm/runtime/rendered/monitoring-geo.conf` | Nginx generated include | `docker/nginx/entrypoint.sh` at container start | nginx container | CIDR classification for monitoring access. |
| `.blue-team-vm/runtime/rendered/monitoring-access.conf` | Nginx generated include | `docker/nginx/entrypoint.sh` at container start | nginx container | Location-level monitoring policy. |
| `.blue-team-vm/runtime/rendered/monitoring-server-access.conf` | Nginx generated include | `docker/nginx/entrypoint.sh` at container start | nginx container | Server-level deny behavior for monitoring routes. |
| `.blue-team-vm/runtime/rendered/ssl-mode.conf` | Nginx generated include | `./ops/bootstrap/bootstrap-nginx-ssl.sh prepare|switch|status` or `docker/nginx/entrypoint.sh render-ssl-mode-conf` | nginx container (`/etc/nginx/generated/ssl-mode.conf` mount target) | Resolves the active cert/key path inside nginx. |
| `.blue-team-vm/runtime/nginx-ssl/*.crt` | SSL certificate material | `./ops/bootstrap/bootstrap-nginx-ssl.sh ...` | nginx container | Machine-managed certificate material. |
| `.blue-team-vm/runtime/nginx-ssl/*.key` | SSL private key material | `./ops/bootstrap/bootstrap-nginx-ssl.sh ...` | nginx container | Machine-managed private key material. |

## Which File To Read First

- App container starts but env looks wrong
  Read `.blue-team-vm/runtime/compat.shell.env` first.
- Obs stack starts but Grafana or Prometheus paths or auth look wrong
  Read `.blue-team-vm/runtime/obs.generated.env` first.
- You need the monitoring login details
  Use `.blue-team-vm/runtime/obs.generated.env`; reveal the password locally with `grep '^MONITORING_PASSWORD=' .blue-team-vm/runtime/obs.generated.env`. If `MONITORING_ADMIN_USERNAME` is not present, use the default `admin`.
- Grafana datasource or Prometheus auth content looks wrong
  Read files under `.blue-team-vm/rendered/`.
- Monitoring route access or SSL mode looks wrong in nginx
  Read files under `.blue-team-vm/runtime/rendered/`.
- You only need forensic provenance of generated secrets
  Read `.blue-team-vm/runtime/obs.generated-secrets.jsonl`.

## Fast Decision Tree

1. If the problem is `Laravel`, `app`, or `auth-service` started with the wrong env
   Open `.blue-team-vm/runtime/compat.shell.env` first.
2. If the problem is obs stack path, hash, or secret drift
   Open `.blue-team-vm/runtime/obs.generated.env` first.
3. If the problem is Prometheus login or config
   Open `.blue-team-vm/rendered/prometheus.web-config.yml` first.
4. If the problem is Grafana datasource or Postgres datasource wiring
   Open `.blue-team-vm/rendered/grafana.datasources.yml` first.
5. If the problem is nginx monitoring-route access
   Open `.blue-team-vm/runtime/rendered/monitoring-geo.conf` and `.blue-team-vm/runtime/rendered/monitoring-access.conf` first.
6. If the problem is SSL mode or cert path loaded by nginx
   Open `.blue-team-vm/runtime/rendered/ssl-mode.conf` first.
7. If the problem is actual secret material
   Open `.blue-team-vm/runtime/grafana-admin-secret` or the relevant `nginx-ssl/*` file directly, but treat them as machine-managed files.
