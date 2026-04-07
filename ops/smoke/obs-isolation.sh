#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./assert.sh
source "${SCRIPT_DIR}/assert.sh"

RUNNER="${RUNNER:-./setup-blue-team-vm.sh}"
OBS_COMPOSE_FILE="${OBS_COMPOSE_FILE:-compose.obs.yml}"
PROMTAIL_SERVICE="${PROMTAIL_SERVICE:-promtail}"

require_cmd docker python3

tmp_output="$(mktemp)"
trap 'rm -f "${tmp_output}"' EXIT

python3 - "${OBS_COMPOSE_FILE}" <<'PY'
import json
import subprocess
import sys

compose_file = sys.argv[1]
result = subprocess.run(
    ["docker", "compose", "-f", compose_file, "config", "--format", "json"],
    capture_output=True,
    text=True,
    check=True,
)
config = json.loads(result.stdout)

for name, service in config.get("services", {}).items():
    if service.get("ports"):
        raise SystemExit(f"{name} publishes host ports unexpectedly")

promtail = config.get("services", {}).get("promtail")
if not promtail:
    raise SystemExit("promtail service missing from obs compose")

required_targets = {
    "/var/log/nginx",
    "/var/log/laravel",
    "/var/log/crowdsec",
}
seen_targets = set()

for volume in promtail.get("volumes", []):
    if isinstance(volume, str):
        parts = volume.split(":")
        target = parts[1] if len(parts) > 1 else ""
        read_only = len(parts) > 2 and parts[-1] == "ro"
    else:
        target = volume.get("target", "")
        read_only = bool(volume.get("read_only"))

    if target in required_targets:
        seen_targets.add(target)
        if not read_only:
            raise SystemExit(f"{target} is not mounted read-only")

missing = required_targets - seen_targets
if missing:
    raise SystemExit(f"promtail missing shared read-only surfaces: {sorted(missing)}")
PY

"${RUNNER}" verify > "${tmp_output}" || true
assert_jsonl_record_type "${tmp_output}" "obs.logs.read_only_mount" "check"
assert_jsonl_status "${tmp_output}" "obs.logs.read_only_mount" "PASS"
assert_jsonl_status "${tmp_output}" "obs.summary" "PASS"
assert_jsonl_status "${tmp_output}" "obs.ports.none_published" "PASS"

smoke_pass "Obs isolation contract holds"
