# Live Proof: Blue-Team VM on 2026-04-07

## Scope

This note records the live evidence chain that validated the blue-team VM bootstrap flow on a controlled Parallels VM after commit `4ed1853` (`fix(ops): stabilize live app and obs bootstrap flows`).

This is a historical proof note, not a reusable operator runbook.

## Environment

- VM platform: Parallels Desktop
- VM identity: `357f89b8-6f2e-405c-8b49-513d423a28cf`
- Guest OS: Ubuntu 24.04
- Kernel: `6.8.0-40-generic`
- Repo path inside VM: `/media/psf/jobs-borads`
- Verification date: `2026-04-07`
- Verified milestone commit: `4ed1853`
- Prior app-plane stabilization commit in the same line of work: `217ce12`

## External Preconditions Used For This Proof

- Host `nginx` was manually stopped and disabled before the app-plane apply.
- This was required because app front-door port ownership on `80/443` is an external host precondition.
- The runner correctly kept `app.frontdoor.host_port_conflicts = FAIL` as fail-loud behavior and did not auto-fix the host listener.
- Host baseline already existed before this proof sequence. This note does not claim that `host` apply was rerun as part of the same final sequence below.

## Commands Executed

Manual host precondition handling on the VM:

```bash
systemctl stop nginx
systemctl disable nginx
```

Live proof sequence run from the repo root inside the VM:

```bash
cd /media/psf/jobs-borads
./setup-blue-team-vm.sh app
./setup-blue-team-vm.sh obs
ops/smoke/run-all.sh
./setup-blue-team-vm.sh verify
```

## Results

### App apply

- `./setup-blue-team-vm.sh app` ended with:
  - `app.summary = PASS`
  - `overall.summary = PASS`
- Front door evidence:
  - `curl -k https://127.0.0.1/up` succeeded
  - `curl -k https://127.0.0.1/.env` returned `403`
- Local non-loopback exposure was limited to `22/80/443`

### Obs apply

- `./setup-blue-team-vm.sh obs` ended with:
  - `obs.summary = PASS`
  - `overall.summary = PASS`
- Passing obs checks included:
  - `obs.bootstrap.required_env`
  - `obs.grafana.healthy`
  - `obs.loki.running`
  - `obs.promtail.running`
  - `obs.prometheus.healthy`
  - `obs.auth_service.healthy`
  - `obs.logs.read_only_mount`
  - `obs.ports.none_published`

### Smoke

- `ops/smoke/run-all.sh` passed:
  - Honeypot `403` contract
  - CrowdSec degraded fail-open contract
  - Obs isolation contract

### Verify

- `./setup-blue-team-vm.sh verify` ended with:
  - `host.summary = PASS`
  - `app.summary = PASS`
  - `obs.summary = PASS`
  - `overall.summary = PASS`

## Generated Runtime Artifacts Observed

The obs bootstrap used generated runtime artifacts under `/var/lib/blue-team-vm`:

- generated env: `/var/lib/blue-team-vm/runtime/obs.generated.env`
- generated secret audit: `/var/lib/blue-team-vm/runtime/obs.generated-secrets.jsonl`
- rendered Prometheus web config: `/var/lib/blue-team-vm/rendered/prometheus.web-config.yml`

These artifacts existed to support derived runtime values such as `PROMETHEUS_PASSWORD_HASH` and to preserve provenance outside the runner status vocabulary.

## Key Conditions Proven By This Run

- App bootstrap stays fail-loud for host `80/443` conflicts.
- App front door remains functional when CrowdSec is degraded during smoke.
- Honeypot rules are present in the real serving path, not only in static config.
- Obs-plane services can come up without publishing public ports.
- Obs-plane log consumption works through declared read-only shared surfaces.
- Verify emits a clean cross-plane `overall.summary = PASS` only after live app, obs, and smoke proof succeeds.

