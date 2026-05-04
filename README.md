# Jobs Boards

A Laravel job board hardened as a blue-team security deployment project, with Docker split-plane deployment, Nginx perimeter controls, CrowdSec, observability, OWASP ZAP evidence, and reproducible lab deployment.

## What this project shows

This project demonstrates:
- secure deployment workflow
- reproducible Docker setup
- evidence-based security testing
- observability and logging
- CI and verification guardrails

## My role

I designed and implemented the deployment flow, security controls, runtime verification paths, observability wiring, and evidence-oriented documentation for this project.

## Stack

Laravel, PHP, Docker Compose, Nginx, CrowdSec, Prometheus, Grafana, Loki, Promtail, PostgreSQL, Redis, OWASP ZAP

## Key evidence

- [Portfolio summary](docs/portfolio-summary.md)
- [Architecture and topology](docs/architecture.md)
- [Security evidence](docs/security-evidence.md)
- [Deployment guide](docs/deployment.md)
- [Lessons learned](docs/lessons-learned.md)

## Proof points

- Dockerized Laravel deployment with explicit bootstrap and runtime artifact preparation
- Security controls around perimeter traffic, monitoring access, and log-driven analysis
- Observability stack with Prometheus, Grafana, Loki, and Promtail
- Before/after OWASP ZAP evidence flow kept separate from deployment
- Verification paths that distinguish local convenience checks from proof-grade VM checks

## Evidence snapshots

- Architecture diagram
  Code diagram: [docs/architecture.md](docs/architecture.md)
- Deployment or topology screenshot
  Code diagram: [docs/deployment.md](docs/deployment.md)
- Grafana or observability screenshot
  Add: `docs/assets/grafana-dashboard.png`
- Security evidence screenshot
  Code diagram: [docs/security-evidence.md](docs/security-evidence.md)

## Why this repo matters

This is not just a coursework app or a CRUD demo. The repo is structured to show how an application can be deployed and defended as an operational system:

- application traffic is fronted by Nginx with explicit perimeter behavior
- observability is treated as part of the deployment contract, not an afterthought
- runtime artifacts are generated and verified instead of assumed
- security evidence is captured separately from deployment so before/after comparisons stay reproducible
- local convenience paths are kept distinct from proof-grade or VM-grade verification paths

## Repository guide

### Portfolio docs

- [docs/portfolio-summary.md](docs/portfolio-summary.md)
- [docs/architecture.md](docs/architecture.md)
- [docs/security-evidence.md](docs/security-evidence.md)
- [docs/deployment.md](docs/deployment.md)
- [docs/lessons-learned.md](docs/lessons-learned.md)

### Technical runbooks

- [docs/runbooks/blue-team-vm-runtime-map.md](docs/runbooks/blue-team-vm-runtime-map.md)
- [docs/runbooks/deploy-profile-guide.md](docs/runbooks/deploy-profile-guide.md)
- [docs/runbooks/security-demo.md](docs/runbooks/security-demo.md)
- [docs/runbooks/operator-preconditions-blue-team-vm.md](docs/runbooks/operator-preconditions-blue-team-vm.md)
- [docs/runbooks/test-verification-paths.md](docs/runbooks/test-verification-paths.md)

## Local Bring-Up

For normal local bring-up:

```bash
docker compose up -d --build
```

The combined `compose.yaml` is the main local operator surface.

- `app-bootstrap-init` prepares the Laravel runtime and missing dependencies
- `obs-bootstrap-init` prepares observability runtime artifacts before auth-service, Prometheus, and Grafana start
- local bring-up does not require the operator to prefill every root `.env` value for first boot

If runtime artifacts are missing locally, refresh only the init services:

```bash
docker compose up --build app-bootstrap-init obs-bootstrap-init
```

## Deployment

This repo supports three deployment shapes:

- reverse-proxy VPS deployment
- profile-driven deployment from environment variables
- lab deployment for isolated security environments

See [docs/deployment.md](docs/deployment.md) and [docs/runbooks/deploy-profile-guide.md](docs/runbooks/deploy-profile-guide.md).

## Security evidence

Security evidence is intentionally separated from deployment logic.

- public HTTPS evidence is collected from the deployed domain
- OWASP ZAP baseline reports are captured externally against the deployed target
- before/after evidence is kept side-by-side instead of overwritten

See [docs/security-evidence.md](docs/security-evidence.md) and [docs/runbooks/security-demo.md](docs/runbooks/security-demo.md).

## Verification

This project keeps separate verification paths for different levels of confidence.

- `composer test`
- `composer test:worktree`
- `composer test:sqlite`
- `docker compose exec -T laravel.test composer test`
- `./ops/bootstrap/bootstrap-app.sh verify`
- `./ops/bootstrap/bootstrap-obs.sh verify`
- `./setup-blue-team-vm.sh verify`

The key boundary is that local convenience checks are not automatically proof-grade evidence for the Linux blue-team VM flow.

## Runtime artifact contract

Observability bootstrap materializes runtime artifacts before monitoring services can be trusted. The most important generated files are:

- `.blue-team-vm/runtime/obs.generated.env`
- `.blue-team-vm/runtime/grafana-admin-secret`
- `.blue-team-vm/rendered/prometheus.web-config.yml`

The runtime-state guide lives in [docs/runbooks/blue-team-vm-runtime-map.md](docs/runbooks/blue-team-vm-runtime-map.md).

## Clean VM proof

The clean-room proof workflow is documented separately because it is intentionally stricter than normal local bring-up or standard VPS deployment.

- [docs/plans/2026-04-13-clean-vm-proof-plan.md](docs/plans/2026-04-13-clean-vm-proof-plan.md)
- [docs/runbooks/operator-preconditions-blue-team-vm.md](docs/runbooks/operator-preconditions-blue-team-vm.md)
