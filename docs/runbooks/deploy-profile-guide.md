# Deploy Profile Guide

This runbook is the operator source of truth for choosing a deploy profile, understanding its defaults, and checking the resolved contract before opening SSH.

## One Introspection Path

Use `ops/deploy/vps-deploy.sh --describe <target>` before a deploy:

```bash
ops/deploy/vps-deploy.sh --describe jb.mythic3011.com
ops/deploy/vps-deploy.sh --describe from-env
ops/deploy/vps-deploy.sh --describe lab-env
```

The describe mode:

- sources the target profile
- resolves the normalized deploy contract
- prints profile name, profile kind, domain, host-nginx install mode, monitoring policy, and canonical operator-facing monitoring credentials
- exits before `git archive`, `ssh`, or `scp`

Profiles still need their minimum required inputs in order to resolve cleanly:

- `jb.mythic3011.com`: `TARGET_HOST`
- `from-env`: `TARGET_HOST`, `TARGET_REMOTE_ROOT`, `TARGET_COMPOSE_PROJECT_NAME`, and either `TARGET_DOMAIN` or `TARGET_SUBDOMAIN + TARGET_ROOT_DOMAIN`
- `lab-env`: `LAB_DEPLOY_HOST`

## Remote Bind Ports Versus Local Convenience Ports

The deploy profiles in this runbook describe the remote application listener contract only.

- The profile bind ports shown below are the remote-facing app-plane ports that host nginx or the lab VM consume.
- `VITE_PORT`, `FORWARD_DB_PORT`, and `FORWARD_REDIS_PORT` are not deploy-target inputs. They belong to the repo-local `compose.yaml` convenience surface for local tooling only.
- The repo-local `bootstrap-env.sh` and `install.sh` paths may rewrite the local host-bound set (`APP_PORT`, `APP_SSL_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`) into `3001-9001` when those local ports are occupied.
- That local rewrite is convenience-only. It does not change the remote deploy profile contract described by `ops/deploy/vps-deploy.sh --describe ...`.

## Profile Selection

### `jb.mythic3011.com`

Use this for the named public VPS deployment where a host nginx reverse proxy owns `80/443` and forwards to loopback high ports.

Pre-set by the profile:

- profile kind: `reverse-proxy`
- domain: `jb.mythic3011.com`
- remote root: `/opt/jobs-boards-jb`
- compose project: `jobs-boards-jb`
- app bind ports: `127.0.0.1:18080` and `127.0.0.1:18443`
- host-nginx install: `true`
- monitoring access mode: `auth-only`
- certificate domain default: `mythic3011.com`

Operator must still provide:

- `TARGET_HOST`

Common optional overrides:

- `TARGET_TLS_MODE=cloudflare-origin|letsencrypt|custom`
- `TARGET_NGINX_CERT_DOMAIN`
- `TARGET_NGINX_CERT_PATH` and `TARGET_NGINX_KEY_PATH`
- `TARGET_MONITORING_ACCESS_MODE`

Example:

```bash
export TARGET_HOST=203.0.113.10
ops/deploy/vps-deploy.sh --describe jb.mythic3011.com
ops/deploy/vps-deploy.sh jb.mythic3011.com main
```

### `from-env`

Use this for a production-style reverse-proxy deployment when you want the shared builder behavior but do not want to add a dedicated target file yet.

Pre-set by the profile:

- profile kind: `reverse-proxy`
- host-nginx install: `true`
- monitoring access mode default: `internal-only`
- app URL default: `https://${TARGET_DOMAIN}`
- asset URL default: `https://${TARGET_DOMAIN}`
- nginx upstream default: `https://127.0.0.1:${TARGET_APP_SSL_PORT##*:}/`

Operator must still provide:

- `TARGET_HOST`
- `TARGET_REMOTE_ROOT`
- `TARGET_COMPOSE_PROJECT_NAME`
- either `TARGET_DOMAIN` or `TARGET_SUBDOMAIN + TARGET_ROOT_DOMAIN`

Common optional overrides:

- `TARGET_MONITORING_ACCESS_MODE=auth-only|internal-only|disabled`
- `TARGET_MONITORING_ALLOWED_CIDRS`
- `TARGET_TLS_MODE=cloudflare-origin|letsencrypt|custom`
- `TARGET_NGINX_CERT_DOMAIN`
- `TARGET_NGINX_CERT_PATH_TEMPLATE`
- `TARGET_NGINX_KEY_PATH_TEMPLATE`

Example:

```bash
export TARGET_DOMAIN=demo.example.com
export TARGET_HOST=203.0.113.10
export TARGET_REMOTE_ROOT=/opt/jobs-boards-demo
export TARGET_COMPOSE_PROJECT_NAME=jobs-boards-demo
ops/deploy/vps-deploy.sh --describe from-env
ops/deploy/vps-deploy.sh from-env main
```

### `lab-env`

Use this for isolated lab or classroom topologies where the application VM is the front door and should bind directly on `80/443`.

Pre-set by the profile:

- profile kind: `lab`
- domain default: `jobs-board.lab`
- remote root: `/opt/jobs-boards-lab`
- compose project default: `jobs-boards-lab`
- app bind ports default: `80` and `443`
- host-nginx install: `false`
- monitoring access mode default: `internal-only`
- lab network knobs: `LAB_WAN_*`, `LAB_LAN_*`, `LAB_NETPLAN_APPLY`

Operator must still provide:

- `LAB_DEPLOY_HOST`

Common additional inputs:

- `JB_INSTALL_ADMIN_EMAIL`
- `JB_INSTALL_ADMIN_PASSWORD`
- `LAB_CONFIGURE_NETPLAN=true` when the deploy should render a lab netplan
- `LAB_WAN_MODE=dhcp|static`

Example:

```bash
export LAB_DEPLOY_HOST=192.168.153.2
export LAB_CONFIGURE_NETPLAN=true
export LAB_WAN_MODE=dhcp
export LAB_LAN_IFACE=eth1
export LAB_LAN_ADDRESS=192.168.153.2/24
export JB_INSTALL_ADMIN_EMAIL=admin@lab.local
export JB_INSTALL_ADMIN_PASSWORD='ChangeMe123!'
ops/deploy/vps-deploy.sh --describe lab-env
ops/deploy/vps-deploy.sh lab-env main
```

## Monitoring Access Modes

- `internal-only`: monitoring routes remain auth-protected and restricted to the allowlisted CIDR set
- `auth-only`: monitoring routes remain auth-protected but the internal CIDR gate is removed
- `disabled`: monitoring ingress is denied entirely at the front door

Defaults:

- `jb.mythic3011.com` defaults to `auth-only`
- `from-env` defaults to `internal-only` unless overridden
- `lab-env` defaults to `internal-only`

`TARGET_MONITORING_ALLOWED_CIDRS` and `LAB_DEPLOY_MONITORING_ALLOWED_CIDRS` override the default CIDR contract when a profile stays in `internal-only`.

## Canonical Monitoring Credentials

These are the primary operator-facing monitoring inputs:

- `MONITORING_ADMIN_USERNAME`
- `MONITORING_PASSWORD`

`GRAFANA_PASSWORD` and `PROMETHEUS_PASSWORD` are legacy compatibility aliases and should not be treated as normal operator inputs.

Derived runtime outputs such as `GRAFANA_ADMIN_SECRET_FILE` and `PROMETHEUS_PASSWORD_HASH` are not the primary operator contract.

## TLS Boundary

The deploy workflow consumes certificate files. It does not issue or renew them.

- use `TARGET_TLS_MODE=cloudflare-origin` when Cloudflare terminates public TLS and the VPS uses an origin certificate
- use `TARGET_TLS_MODE=letsencrypt` when the VPS presents the public certificate directly
- use `TARGET_TLS_MODE=custom` when the cert and key live outside the default layouts

For host firewall and TLS posture details, see [host-tls-modes.md](./host-tls-modes.md).
