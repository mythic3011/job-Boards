#!/usr/bin/env bash
#
# Internal staged helper executed by pd-cleanvm-proof.sh inside the guest.
# Operators should invoke the host orchestrator only.

set -euo pipefail

BT_PROOF_INPUT_DIR="${BT_PROOF_INPUT_DIR:-${HOME}/proof/input}"
BT_PROOF_WORKDIR="${BT_PROOF_WORKDIR:-${HOME}/proof/workdir}"
BT_PROOF_OUTPUT_DIR="${BT_PROOF_OUTPUT_DIR:-${HOME}/proof/output/current}"
BT_PROOF_LOG_ROOT="${BT_PROOF_LOG_ROOT:-${BT_PROOF_OUTPUT_DIR}/logs}"
BT_PROOF_LOG_FILE="${BT_PROOF_LOG_FILE:-${BT_PROOF_LOG_ROOT}/02-guest-proof-run.log}"
BT_PROOF_ARCHIVE_PATH="${BT_PROOF_ARCHIVE_PATH:-${BT_PROOF_INPUT_DIR}/repo.tgz}"
BT_PROOF_MANIFEST_PATH="${BT_PROOF_MANIFEST_PATH:-${BT_PROOF_INPUT_DIR}/manifest.json}"
BT_PROOF_INSTALLER_PATH="${BT_PROOF_INSTALLER_PATH:-${BT_PROOF_INPUT_DIR}/guest-install-deps.sh}"
BT_PROOF_SEQUENCE_LOG="${BT_PROOF_SEQUENCE_LOG:-${BT_PROOF_OUTPUT_DIR}/sequence.log}"
BT_PROOF_FRAGMENT_PATH="${BT_PROOF_FRAGMENT_PATH:-${BT_PROOF_OUTPUT_DIR}/guest-fragment.json}"
BT_PROOF_STATE_DIR="${BT_PROOF_STATE_DIR:-${BT_STATE_DIR:-/var/lib/blue-team-vm}}"
BT_PROOF_OBS_ENV_PATH="${BT_PROOF_OBS_ENV_PATH:-${BT_PROOF_STATE_DIR}/runtime/obs.generated.env}"
BT_PROOF_OBS_AUDIT_PATH="${BT_PROOF_OBS_AUDIT_PATH:-${BT_PROOF_STATE_DIR}/runtime/obs.generated-secrets.jsonl}"
BT_PROOF_OBS_METADATA_PATH="${BT_PROOF_OBS_METADATA_PATH:-${BT_PROOF_OUTPUT_DIR}/obs-runtime-metadata.json}"
BT_PROOF_REPO_DIR="${BT_PROOF_WORKDIR}/repo"

declare -a COMPLETED_STEPS=()

log() {
    printf '%s\n' "$*" | tee -a "${BT_PROOF_LOG_FILE}"
}

q() {
    printf '%q' "$1"
}

run_privileged() {
    sudo -n env \
        BT_STATE_DIR="${BT_PROOF_STATE_DIR}" \
        BT_PROOF_OUTPUT_DIR="${BT_PROOF_OUTPUT_DIR}" \
        BT_PROOF_SEQUENCE_LOG="${BT_PROOF_SEQUENCE_LOG}" \
        "$@"
}

prepare_dirs() {
    rm -rf "${BT_PROOF_WORKDIR}" "${BT_PROOF_OUTPUT_DIR}"
    mkdir -p "${BT_PROOF_REPO_DIR}" "${BT_PROOF_LOG_ROOT}"
}

prepare_logging() {
    umask 022
    : > "${BT_PROOF_LOG_FILE}"
    chmod 0644 "${BT_PROOF_LOG_FILE}"
}

expected_archive_hash() {
    MANIFEST_PATH="${BT_PROOF_MANIFEST_PATH}" python3 - <<'PY'
import json
import os

with open(os.environ["MANIFEST_PATH"], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

value = payload.get("archive_sha256")
if not value:
    raise SystemExit(1)

print(value)
PY
}

verify_archive_hash() {
    local expected actual

    expected="$(expected_archive_hash)" || {
        log "ERROR [archive_manifest_invalid] Missing archive_sha256 in manifest."
        exit 1
    }

    actual="$(sha256sum "${BT_PROOF_ARCHIVE_PATH}" | awk '{print $1}')" || {
        log "ERROR [archive_hash_failed] Unable to hash ${BT_PROOF_ARCHIVE_PATH}."
        exit 1
    }

    if [[ "${actual}" != "${expected}" ]]; then
        log "ERROR [archive_hash_mismatch] Expected ${expected} but received ${actual}."
        exit 1
    fi
}

extract_archive() {
    tar -xzf "${BT_PROOF_ARCHIVE_PATH}" -C "${BT_PROOF_REPO_DIR}"
}

require_non_interactive_sudo() {
    if sudo -n true >/dev/null 2>&1; then
        return 0
    fi

    log "ERROR [sudo_non_interactive_required] Guest proof runner requires non-interactive sudo."
    exit 1
}

require_docker_daemon_access() {
    if sudo -n docker info >/dev/null 2>&1; then
        return 0
    fi

    log "ERROR [docker_daemon_access_required] Guest proof runner requires sudo-backed Docker daemon access."
    exit 1
}

run_step() {
    local label="$1"
    local log_name="$2"
    shift 2

    local log_path="${BT_PROOF_LOG_ROOT}/${log_name}"
    "$@" >"${log_path}" 2>&1
    COMPLETED_STEPS+=("${label}")
}

run_step_privileged() {
    local label="$1"
    local log_name="$2"
    shift 2

    local log_path="${BT_PROOF_LOG_ROOT}/${log_name}"
    run_privileged "$@" >"${log_path}" 2>&1
    COMPLETED_STEPS+=("${label}")
}

capture_output() {
    local file_name="$1"
    shift

    "$@" >"${BT_PROOF_OUTPUT_DIR}/${file_name}" 2>&1
}

capture_privileged_output() {
    local file_name="$1"
    shift

    run_privileged "$@" >"${BT_PROOF_OUTPUT_DIR}/${file_name}" 2>&1
}

capture_privileged_repo_command() {
    local file_name="$1"
    local command_string="$2"

    run_privileged bash -c "${command_string}" >"${BT_PROOF_OUTPUT_DIR}/${file_name}" 2>&1
}

capture_privileged_compose_ps() {
    local file_name="$1"
    local compose_file="$2"
    local command_string

    command_string="cd $(q "${BT_PROOF_REPO_DIR}") && "
    command_string+="source $(q "${BT_PROOF_REPO_DIR}/ops/lib/common.sh") && "
    command_string+="bt_compose $(q "${compose_file}") ps"

    capture_privileged_repo_command "${file_name}" "${command_string}"
}

write_fragment() {
    local step_lines
    step_lines="$(printf '%s\n' "${COMPLETED_STEPS[@]}")"

    BT_PROOF_FRAGMENT_PATH="${BT_PROOF_FRAGMENT_PATH}" \
    STEP_LINES="${step_lines}" \
    python3 - <<'PY'
import json
import os

steps = [line for line in os.environ["STEP_LINES"].splitlines() if line]
payload = {
    "record_type": "guest_fragment",
    "proof_status": "PASS",
    "steps": steps,
}

with open(os.environ["BT_PROOF_FRAGMENT_PATH"], "w", encoding="utf-8") as handle:
    json.dump(payload, handle, indent=2, sort_keys=True)
    handle.write("\n")
PY
}

project_obs_runtime_metadata() {
    BT_PROOF_OBS_ENV_PATH="${BT_PROOF_OBS_ENV_PATH}" \
    BT_PROOF_OBS_AUDIT_PATH="${BT_PROOF_OBS_AUDIT_PATH}" \
    BT_PROOF_OBS_METADATA_PATH="${BT_PROOF_OBS_METADATA_PATH}" \
    python3 - <<'PY'
import json
import os
from pathlib import Path

ALLOWLIST = [
    "MONITORING_ADMIN_USERNAME",
    "MONITORING_PASSWORD_HASH",
    "SESSION_SECRET",
    "GRAFANA_ADMIN_SECRET_FILE",
    "PROMETHEUS_PASSWORD_HASH",
    "PROMETHEUS_WEB_CONFIG_FILE",
]
FILE_KEYS = {"GRAFANA_ADMIN_SECRET_FILE", "PROMETHEUS_WEB_CONFIG_FILE"}
AUDIT_FIELDS = [
    "record_type",
    "generated_at",
    "generated_by",
    "target_field",
    "source_field",
    "mode",
    "deterministic",
    "user_action_required",
]


def dequote(value: str) -> str:
    if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
        return value[1:-1]
    return value


def parse_env_file(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key] = dequote(value)
    return values


env_path = Path(os.environ["BT_PROOF_OBS_ENV_PATH"])
audit_path = Path(os.environ["BT_PROOF_OBS_AUDIT_PATH"])
payload: dict[str, object] = {
    "record_type": "obs_runtime_metadata",
    "generated_env": {
        "status": "missing",
        "keys": {},
    },
    "generated_secret_audit": {
        "status": "missing",
        "record_count": 0,
        "records": [],
    },
}

if env_path.is_file():
    env_values = parse_env_file(env_path)
    projected_keys: dict[str, object] = {}
    for key in ALLOWLIST:
        entry: dict[str, object] = {"present": key in env_values}
        if key in env_values and key in FILE_KEYS:
            file_path = Path(env_values[key])
            entry["basename"] = file_path.name
            entry["readable"] = file_path.is_file() and os.access(file_path, os.R_OK)
        projected_keys[key] = entry
    payload["generated_env"] = {
        "status": "present",
        "keys": projected_keys,
    }

if audit_path.is_file():
    records = []
    for raw_line in audit_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line:
            continue
        record = json.loads(line)
        records.append({field: record.get(field) for field in AUDIT_FIELDS if field in record})
    payload["generated_secret_audit"] = {
        "status": "present",
        "record_count": len(records),
        "records": records,
    }

metadata_path = Path(os.environ["BT_PROOF_OBS_METADATA_PATH"])
metadata_path.write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8")
PY
}

collect_system_evidence() {
    capture_output "10-os-release.txt" cat /etc/os-release
    capture_output "11-uname.txt" uname -a
    capture_privileged_output "12-docker-version.txt" docker version
    capture_privileged_output "13-docker-compose-version.txt" docker compose version
    capture_privileged_compose_ps "14-compose-app-ps.txt" "${BT_PROOF_REPO_DIR}/compose.app.yml"
    capture_privileged_compose_ps "15-compose-obs-ps.txt" "${BT_PROOF_REPO_DIR}/compose.obs.yml"
    capture_privileged_output "16-systemctl-docker.txt" systemctl status docker --no-pager
}

run_smoke_step() {
    local log_path="${BT_PROOF_LOG_ROOT}/50-smoke.log"
    local smoke_script="${BT_PROOF_REPO_DIR}/ops/smoke/run-all.sh"
    local runner_path="${BT_PROOF_REPO_DIR}/setup-blue-team-vm.sh"
    local app_compose_file="${BT_PROOF_REPO_DIR}/compose.app.yml"
    local obs_compose_file="${BT_PROOF_REPO_DIR}/compose.obs.yml"
    local command_string

    command_string="cd $(q "${BT_PROOF_REPO_DIR}") && "
    command_string+="env "
    command_string+="BT_STATE_DIR=$(q "${BT_PROOF_STATE_DIR}") "
    command_string+="RUNNER=$(q "${runner_path}") "
    command_string+="APP_COMPOSE_FILE=$(q "${app_compose_file}") "
    command_string+="OBS_COMPOSE_FILE=$(q "${obs_compose_file}") "
    command_string+="$(q "${smoke_script}")"

    if docker info >/dev/null 2>&1; then
        bash -c "${command_string}" >"${log_path}" 2>&1
        COMPLETED_STEPS+=("ops/smoke/run-all.sh")
        return 0
    fi

    if command -v sg >/dev/null 2>&1 && sg docker -c "docker info >/dev/null 2>&1"; then
        sg docker -c "${command_string}" >"${log_path}" 2>&1
        COMPLETED_STEPS+=("ops/smoke/run-all.sh")
        return 0
    fi

    run_step_privileged "ops/smoke/run-all.sh" "50-smoke.log" bash -c "${command_string}"
}

main() {
    prepare_dirs
    prepare_logging
    export BT_PROOF_SEQUENCE_LOG
    export BT_PROOF_OUTPUT_DIR
    export BT_STATE_DIR="${BT_PROOF_STATE_DIR}"

    require_non_interactive_sudo
    verify_archive_hash
    extract_archive

    run_step "guest-install-deps" "10-guest-install-deps.log" "${BT_PROOF_INSTALLER_PATH}"
    require_docker_daemon_access

    run_step_privileged "setup-blue-team-vm.sh host" "20-bootstrap-host.log" "${BT_PROOF_REPO_DIR}/setup-blue-team-vm.sh" host
    run_step_privileged "setup-blue-team-vm.sh app" "30-bootstrap-app.log" "${BT_PROOF_REPO_DIR}/setup-blue-team-vm.sh" app
    run_step_privileged "setup-blue-team-vm.sh obs" "40-bootstrap-obs.log" "${BT_PROOF_REPO_DIR}/setup-blue-team-vm.sh" obs
    run_smoke_step
    run_step_privileged "setup-blue-team-vm.sh verify" "60-verify.log" "${BT_PROOF_REPO_DIR}/setup-blue-team-vm.sh" verify

    collect_system_evidence
    project_obs_runtime_metadata
    write_fragment
    log "Guest proof runner completed."
}

main "$@"
