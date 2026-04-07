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
- The current implementation also accepts `LOKI_PASSWORD` as a fallback source for `PROMETHEUS_PASSWORD_HASH`.
- If no safe source exists, obs bootstrap must fail closed instead of inventing credentials.
- Runner statuses remain `PASS | DEGRADED | FAIL | SKIPPED`; auto-fix provenance belongs in generated artifacts, not in new status values.

## Generated Runtime Artifacts

Obs bootstrap may materialize runtime artifacts under `/var/lib/blue-team-vm`:

- generated env: `/var/lib/blue-team-vm/runtime/obs.generated.env`
- generated secret audit: `/var/lib/blue-team-vm/runtime/obs.generated-secrets.jsonl`
- rendered Prometheus web config: `/var/lib/blue-team-vm/rendered/prometheus.web-config.yml`

Operator guidance:

- retain generated audit artifacts while a run is being validated or reviewed
- retain them when an audit trail is required for incident review or demo evidence
- clear or rotate them when credentials rotate, the VM is being reset, or a fresh bootstrap provenance chain is required
- do not treat generated artifacts as a substitute for long-term secret management

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

## Config Contract Rules

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
