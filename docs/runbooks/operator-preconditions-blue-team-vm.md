# Operator Preconditions: Blue-Team VM

## Purpose

This runbook defines the operator-visible preconditions for rerunning the blue-team VM bootstrap flow.

This is a reusable contract for future runs. It is not a historical log.

## Host Port Ownership

- Host ports `80` and `443` must be free before `./setup-blue-team-vm.sh app`.
- If a host listener already owns `80/443`, app bootstrap is expected to fail loud with `app.frontdoor.host_port_conflicts = FAIL`.
- This is intentional. App bootstrap must not auto-stop or auto-disable host listeners such as `nginx`.
- In a controlled demo VM, the operator must clear the conflicting listener before rerunning app bootstrap.

## Host Baseline Contract

- `app` mode requires a valid host baseline marker before it can proceed.
- The host marker path is `/var/lib/blue-team-vm/host-baseline-v1.json`.
- Missing, unreadable, invalid, or version-mismatched host baseline markers must be treated as baseline missing.
- `app` mode is not allowed to silently repair host firewall, SSH, or honeypot state.

## Obs Secret Policy

- Obs bootstrap may auto-fix only when a safe source already exists.
- `PROMETHEUS_PASSWORD_HASH` may be derived from `PROMETHEUS_PASSWORD`.
- `GRAFANA_PASSWORD` is a plaintext source only. The final runtime contract for Grafana is `GRAFANA_PASSWORD_FILE`.
- If `GRAFANA_PASSWORD_FILE` is not provided, obs bootstrap may materialize it from `GRAFANA_PASSWORD`.
- If no safe source exists, obs bootstrap must fail closed instead of inventing credentials.
- Generated obs runtime values override source-layer `.env` values for the same key during obs bootstrap.
- Runner statuses remain `PASS | DEGRADED | FAIL | SKIPPED`; auto-fix provenance belongs in generated artifacts, not in new status values.

## Generated Runtime Artifacts

Obs bootstrap may materialize runtime artifacts under `/var/lib/blue-team-vm`:

- generated env: `/var/lib/blue-team-vm/runtime/obs.generated.env`
- generated secret audit: `/var/lib/blue-team-vm/runtime/obs.generated-secrets.jsonl`
- generated Grafana admin password file: `/var/lib/blue-team-vm/runtime/grafana-admin-password`
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
- when local developers start `compose.obs.yml` manually, they must export both source-layer `.env` values and `${BT_STATE_DIR}/runtime/obs.generated.env`
- a missing `PROMETHEUS_WEB_CONFIG_FILE` or `GRAFANA_PASSWORD_FILE` during local `docker compose` interpolation is a runtime artifact preparation failure, not by itself proof that the obs deployment contract is wrong

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

- the reusable clean-room proof workflow is defined in [docs/plans/2026-04-13-clean-vm-proof-plan.md](/Users/mythic3014/PhpstormProjects/jobs-borads/docs/plans/2026-04-13-clean-vm-proof-plan.md)
- `ops/proof/pd-cleanvm-proof.sh` is the only public operator entrypoint for that workflow
- `ops/proof/guest-install-deps.sh` and `ops/proof/guest-blue-team-proof.sh` are staged internal helpers, not standalone operator commands
- an operational clean-room run may use per-run TOFU SSH trust
- a proof-grade clean-room run must use pinned SSH host identity
- the guest SSH user must satisfy `sudo -n true` and `sudo -n docker info` before split-plane proof execution can proceed
- the guest proof flow prepares the current SSH user for non-root smoke execution through the `docker` group
- smoke execution must run from the extracted repo root with explicit `RUNNER`, `APP_COMPOSE_FILE`, and `OBS_COMPOSE_FILE`
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

- Compose ownership for the blue-team VM flow is fixed:
  - `compose.app.yml` is the app-plane source of truth.
  - `compose.obs.yml` is the obs-plane source of truth.
  - `compose.yaml` is a combined local/dev Compose surface and is not the bootstrap source of truth.
- The runner and smoke flow use the split files:
  - `./setup-blue-team-vm.sh app` uses `compose.app.yml`
  - `./setup-blue-team-vm.sh obs` uses `compose.obs.yml`
  - `./setup-blue-team-vm.sh verify` evaluates app and obs state through those split files
- app smokes default to `compose.app.yml`
- obs isolation smoke defaults to `compose.obs.yml`
- Manual local compose commands must mirror that split truth:
  - export `.env` and a repo-local `BT_HONEYPOT_SOURCE` before `docker compose -f compose.app.yml ...`
  - export `.env` and `${BT_STATE_DIR}/runtime/obs.generated.env` before `docker compose -f compose.obs.yml ...`
- `install.sh full dev` is a local convenience path only:
  - it uses `docker compose -f compose.yaml ...` for the combined local stack
  - it must not be treated as blue-team VM runtime contract evidence
  - it intentionally avoids `down --remove-orphans` so it does not tear down split-plane services that may already be running in the same workspace
- Non-Linux local runtimes may use the child plane verifiers for app/obs readiness evidence, but host-kernel and host-port proofs remain Linux-VM-only:
  - `./ops/bootstrap/bootstrap-app.sh verify` may emit `app.host.local_ports = SKIPPED`
  - `./setup-blue-team-vm.sh verify` still requires the actual Linux VM runtime and may fail preflight outside it
- Source-layer operator inputs and final runtime values are distinct:
  - plaintext sources: `MONITORING_PASSWORD`, `GRAFANA_PASSWORD`, `PROMETHEUS_PASSWORD`
  - final runtime values: `MONITORING_PASSWORD_HASH`, `GRAFANA_PASSWORD_FILE`, `PROMETHEUS_PASSWORD_HASH`, `SESSION_SECRET`
- `compose.obs.yml` must consume final runtime values only. It must not bypass bootstrap by reading plaintext Grafana or Prometheus credentials directly.
- Any change that affects live blue-team VM runtime truth must land in `compose.app.yml` or `compose.obs.yml` first.
- Security-contract fixes for the blue-team VM must be validated against `compose.app.yml` and `compose.obs.yml`; updating `compose.yaml` alone is not runtime contract evidence.
- Do not treat `compose.yaml` as evidence that the blue-team VM bootstrap contract has been updated.
- If an operator, smoke, or verify command still references `compose.yaml`, treat that as drift and correct the split-file path instead of extending the combined file.
- If local combined developer behavior needs to mirror the split-file security contract, open a dedicated `compose.yaml` reconcile slice instead of mixing it into bootstrap work.
- In the split blue-team VM contract, the app plane does not promise `/monitoring/*` ingress through the app front door. Monitoring services remain obs-plane internal until a dedicated ingress bridge is designed and verified.
- Loki config changes must be validated against Loki's real config schema. Do not guess field names.
- CrowdSec AppSec changes must keep acquisition and AppSec config contracts aligned. Do not infer bundle/config behavior from container startup alone.
- Front-door and honeypot checks must verify the real serving path, not only static config text.

## Shared Surface Rules

- App plane owns production of shared log and metric artifacts.
- Obs plane owns read-only consumption only.
- Any obs mount of a shared surface must remain read-only.
- Public port publishing from obs-plane services is not allowed.

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
