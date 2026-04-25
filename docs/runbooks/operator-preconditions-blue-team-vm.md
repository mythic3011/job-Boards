# Operator Preconditions: Blue-Team VM

## Purpose

This runbook defines the operator-visible preconditions for rerunning the blue-team VM bootstrap flow.

This is a reusable contract for future runs. It is not a historical log.

## Host Port Ownership

- Host ports `80` and `443` must be free before `./setup-blue-team-vm.sh app`.
- If a host listener already owns `80/443`, app bootstrap is expected to fail loud with `app.frontdoor.host_port_conflicts = FAIL`.
- This is intentional. App bootstrap must not auto-stop or auto-disable host listeners such as `nginx`.
- In a controlled demo VM, the operator must clear the conflicting listener before rerunning app bootstrap.
- Repo-local convenience bootstrap is a separate path: `bootstrap-env.sh` and `install.sh` may rewrite local `.env` values for `APP_PORT`, `APP_SSL_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, and `FORWARD_REDIS_PORT` into `3001-9001` when those local ports are already occupied.
- That local rewrite is not blue-team VM proof evidence and does not relax the requirement that real host `80/443` ownership be cleared before `./setup-blue-team-vm.sh app`.

## Host Baseline Contract

- `app` mode requires a valid host baseline marker before it can proceed.
- The host marker path is `/var/lib/blue-team-vm/host-baseline-v1.json`.
- Missing, unreadable, invalid, or version-mismatched host baseline markers must be treated as baseline missing.
- `app` mode is not allowed to silently repair host firewall, SSH, or honeypot state.

## Obs Secret Policy

- Obs bootstrap may auto-fix only when a safe source already exists.
- `MONITORING_ADMIN_USERNAME` and `MONITORING_PASSWORD` are the canonical operator-facing monitoring inputs.
- `PROMETHEUS_PASSWORD_HASH` is derived from canonical monitoring secret handling and is not an operator input.
- `GRAFANA_ADMIN_SECRET_FILE` is a generated runtime secret-file path, not an operator input.
- Legacy plaintext aliases (`GRAFANA_PASSWORD`, `PROMETHEUS_PASSWORD`) are compatibility-only and must not be used as primary operator contract.
- If no safe source exists, obs bootstrap must fail closed instead of inventing credentials.
- Generated obs runtime values override source-layer `.env` values for the same key during obs bootstrap.
- Runner statuses remain `PASS | DEGRADED | FAIL | SKIPPED`; auto-fix provenance belongs in generated artifacts, not in new status values.

## Generated Runtime Artifacts

Obs bootstrap may materialize runtime artifacts under `/var/lib/blue-team-vm`:

- generated env: `/var/lib/blue-team-vm/runtime/obs.generated.env`
- generated secret audit: `/var/lib/blue-team-vm/runtime/obs.generated-secrets.jsonl`
- generated Grafana admin secret file: `/var/lib/blue-team-vm/runtime/grafana-admin-secret`
- rendered Prometheus web config: `/var/lib/blue-team-vm/rendered/prometheus.web-config.yml`

Operator guidance:

- retain generated audit artifacts while a run is being validated or reviewed
- retain them when an audit trail is required for incident review or demo evidence
- clear or rotate them when credentials rotate, the VM is being reset, or a fresh bootstrap provenance chain is required
- do not treat generated artifacts as a substitute for long-term secret management

Clean-room proof export policy:

- these files are VM-local runtime artifacts, not automatic host proof-bundle exports
- a clean-room proof bundle must not copy raw `obs.generated.env`
- a clean-room proof bundle must not copy raw `obs.generated-secrets.jsonl`
- proof export may include only allowlisted or redacted metadata projections of secret-bearing obs artifacts
- the current proof workflow exports that metadata-safe projection as `guest-output/obs-runtime-metadata.json`
- if a proof run needs final host evidence, the host orchestrator is responsible for projecting and collecting that evidence safely

Local repository note:

- blue-team VM host runs default to `/var/lib/blue-team-vm`
- local repo workflows may override `BT_STATE_DIR` to `${REPO_ROOT}/.blue-team-vm`
- the repo-local convenience stack treats `APP_PORT`, `APP_SSL_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, and `FORWARD_REDIS_PORT` as the host-bound port set that bootstrap/install may populate or rewrite
- normal local operator flow should run `docker compose up` against `compose.yaml` only
- `compose.yaml` runs `obs-bootstrap-init` first and then starts auth-service/Prometheus/Grafana with generated runtime artifacts
- split-plane compose files remain internal/legacy compatibility surfaces; they are not a public operator contract
- the historical split app-plane subnet contract remains `172.29.0.0/24` for compatibility checks
- a missing `PROMETHEUS_WEB_CONFIG_FILE` or `GRAFANA_ADMIN_SECRET_FILE` during local `docker compose` interpolation is a runtime artifact preparation failure, not by itself proof that the obs deployment contract is wrong
- auth-service healthchecks use fixed `127.0.0.1:3000`; host-shell `PORT` is not a supported healthcheck override contract

## Startup Gating Versus Functional Proof

- `depends_on` and Compose healthchecks are startup gates only.
- A healthy Compose graph is necessary but not sufficient evidence that the demo contract holds.
- After successful apply, operators must still run:

```bash
./setup-blue-team-vm.sh app
./setup-blue-team-vm.sh obs
ops/smoke/run-all.sh
./setup-blue-team-vm.sh verify
```

- `smoke` proves the behavioral contracts.
- `verify` proves the structured cross-plane status contract.

## Clean-Room Proof Grading

- the reusable clean-room proof workflow is defined in [docs/plans/2026-04-13-clean-vm-proof-plan.md](../plans/2026-04-13-clean-vm-proof-plan.md)
- `ops/proof/pd-cleanvm-proof.sh` is the only public operator entrypoint for that workflow
- `ops/proof/guest-install-deps.sh` and `ops/proof/guest-blue-team-proof.sh` are staged internal helpers, not standalone operator commands
- an operational clean-room run may use per-run TOFU SSH trust
- a proof-grade clean-room run must use pinned SSH host identity
- the guest SSH user must satisfy `sudo -n true` and `sudo -n docker info` before split-plane proof execution can proceed
- the guest proof flow prepares the current SSH user for least-privilege smoke execution through the `docker` group and falls back to `sg docker` when the refreshed group membership is not yet active in the current shell
- smoke execution must run from the extracted repo root with explicit `RUNNER`, `APP_COMPOSE_FILE`, and `OBS_COMPOSE_FILE`
- smoke scripts that invoke `setup-blue-team-vm.sh` must forward the matching `BT_COMPOSE_*` variable so nested verify/apply calls stay bound to the extracted split-plane compose files
- compose `ps` evidence must be captured through `ops/lib/common.sh` / `bt_compose` so obs runtime interpolation matches the split-plane contract
- the host orchestrator owns the final clean-room `result.json`
- guest proof scripts may emit logs and fragments, but they do not own final overall proof authority

Observable grading contract:

- `result.json` is the operator-visible grading artifact for the clean-room proof workflow
- `guest-output/obs-runtime-metadata.json` is the operator-visible projection for obs runtime artifact evidence
- `guest-output/10-os-release.txt`, `11-uname.txt`, `12-docker-version.txt`, `13-docker-compose-version.txt`, `14-compose-app-ps.txt`, `15-compose-obs-ps.txt`, and `16-systemctl-docker.txt` are the fixed guest evidence files for OS, Docker, and compose state
- `ssh_identity_mode=tofu` maps to `assurance_level=operational`
- `ssh_identity_mode=pinned` maps to `assurance_level=proof-grade`
- a proof-grade pass requires pinned host identity and a passing `proof_status`
- operators should read `proof_status`, `artifact_status`, `restore_status`, and `overall_status` separately rather than collapsing everything into one pass/fail label

## Config Contract Rules

- Compose operator contract for local/dev is `compose.yaml`.
- Normal bring-up and re-bootstrap should use:
  - `docker compose up -d --build`
  - `docker compose up --build app-bootstrap-init obs-bootstrap-init` (init-only refresh)
- `install.sh full dev` is legacy wrapper compatibility and must not be treated as separate runtime truth.
- Split compose files (`compose.app.yml`, `compose.obs.yml`) are internal compatibility/test artifacts and must not be documented as the primary operator path.
- Non-Linux local runtimes may use the child plane verifiers for app/obs readiness evidence, but host-kernel and host-port proofs remain Linux-VM-only:
  - `./ops/bootstrap/bootstrap-app.sh verify` may emit `app.host.local_ports = SKIPPED`
  - `./setup-blue-team-vm.sh verify` still requires the actual Linux VM runtime and may fail preflight outside it
- Source-layer operator inputs and final runtime values are distinct:
  - canonical operator inputs: `MONITORING_ADMIN_USERNAME`, `MONITORING_PASSWORD`
  - legacy compatibility aliases (not normal operator inputs): `GRAFANA_PASSWORD`, `PROMETHEUS_PASSWORD`
  - final runtime values: `MONITORING_PASSWORD_HASH`, `GRAFANA_ADMIN_SECRET_FILE`, `PROMETHEUS_PASSWORD_HASH`, `SESSION_SECRET`
- Combined compose services must consume final runtime values and must not bypass bootstrap by reading plaintext Grafana/Prometheus credentials directly.
- Any change that affects live local runtime truth must land in `compose.yaml` first.

## Repo-first remote deploy profiles

- Use [deploy-profile-guide.md](./deploy-profile-guide.md) as the single operator reference for:
  - when to use `jb.mythic3011.com`, `from-env`, or `lab-env`
  - which values each profile pre-sets
  - which values the operator still must provide
  - what each monitoring access mode means
  - which monitoring credentials are canonical
- `ops/deploy/vps-deploy.sh` remains the reusable SSH deploy entrypoint. It deploys a committed ref via `git archive`, does not clone from the remote host, and refuses a normal deploy from a dirty worktree.
- `ops/deploy/vps-deploy.sh --describe <target>` is the one introspection path for resolved profile output. It prints the normalized contract and exits before SSH/SCP.
- The deploy workflow consumes an already-issued TLS certificate; it does not issue or renew certificates itself.
- Host firewall and TLS behavior stay separate from application deploy profile selection; see [host-tls-modes.md](./host-tls-modes.md).
- In the split blue-team VM contract, the app plane does not promise `/monitoring/*` ingress through the app front door. Monitoring services remain obs-plane internal until a dedicated ingress bridge is designed and verified.
- Loki config changes must be validated against Loki's real config schema. Do not guess field names.
- CrowdSec AppSec changes must keep acquisition and AppSec config contracts aligned. Do not infer bundle/config behavior from container startup alone.
- Front-door and honeypot checks must verify the real serving path, not only static config text.

## Shared Surface Rules

- App plane owns production of shared log and metric artifacts.
- Obs plane owns read-only consumption only.
- Any obs mount of a shared surface must remain read-only.
- Public port publishing from obs-plane services is not allowed.
- `obs.logs.read_only_mount = FAIL` means the shared log surfaces are missing or unreadable; the usual cause is that the app plane did not start and populate the nginx/crowdsec/log producer surfaces first.

## Minimal Rerun Checklist

- host listener ownership on `80/443` checked
- valid host baseline marker present
- Docker and Git available in the VM
- repo present inside the VM at the intended path
- required secret sources present before obs apply
- app apply completed
- obs apply completed
- smoke passed
- verify ended with `overall.summary = PASS`

## Security demo evidence

The course-style security demo has two separate evidence surfaces:

1. HTTPS screenshots on the VPS-facing public domain
   - run the public URL through Why No Padlock
   - run the public URL through SSL Labs
   - keep screenshots with the deployed commit/ref noted beside them
2. Web vulnerability reports
   - run one ZAP baseline container against the "before" revision
   - run one ZAP baseline container against the "after" revision
   - keep both artifact folders so "major" findings can be compared directly

Use [docs/runbooks/security-demo.md](./security-demo.md) as the repeatable evidence checklist.
