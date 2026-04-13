#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

VM_NAME=""
SNAPSHOT_NAME=""
SSH_HOST=""
SSH_USER=""
SSH_PORT="22"
REMOTE_PROOF_ROOT=""
OUTPUT_DIR="${TMPDIR:-/tmp}/jobs-boards-clean-vm-proof"
REPO_REF="HEAD"
RUN_ID=""
DRY_RUN=0

EXPECTED_HOST_FINGERPRINT=""
EXPECTED_HOST_KEY_ALGORITHM=""
SSH_IDENTITY_MODE="tofu"
SSH_HOST_KEY_ALGORITHM=""
ASSURANCE_LEVEL="operational"

SSH_WAIT_ATTEMPTS="${BT_SSH_WAIT_ATTEMPTS:-30}"
SSH_WAIT_DELAY_SECONDS="${BT_SSH_WAIT_DELAY_SECONDS:-2}"
HOST_KEY_FETCH_ATTEMPTS="${BT_HOST_KEY_FETCH_ATTEMPTS:-5}"
HOST_KEY_FETCH_DELAY_SECONDS="${BT_HOST_KEY_FETCH_DELAY_SECONDS:-1}"

ARTIFACT_DIR=""
RESULT_PATH=""
MANIFEST_PATH=""
ARCHIVE_PATH=""
GUEST_OUTPUT_DIR=""
SSH_KNOWN_HOSTS_PATH=""
REMOTE_INPUT_DIR=""
REMOTE_WORKDIR=""
REMOTE_OUTPUT_DIR=""
STAGED_GUEST_INSTALLER_PATH=""
STAGED_GUEST_PROOF_RUNNER_PATH=""

RESOLVED_COMMIT_SHA=""
SNAPSHOT_ID=""
ARCHIVE_SHA256=""
PRIMARY_FAILURE_PHASE=""
PRIMARY_FAILURE_CODE=""
PROOF_STATUS="SKIPPED"
ARTIFACT_STATUS="SKIPPED"
RESTORE_STATUS="SKIPPED"
OVERALL_STATUS="SKIPPED"

RESTORE_REQUIRED=0
FINAL_EXIT_CODE=0

usage() {
    cat <<'EOF'
Usage: pd-cleanvm-proof.sh [options]

Required options:
  --vm-name <name>
  --snapshot-name <name>
  --ssh-host <host>
  --ssh-user <user>

Optional:
  --ssh-port <port>
  --remote-proof-root <path>
  --output-dir <path>
  --repo-ref <ref>
  --run-id <id>
  --expected-host-key-algorithm <algorithm>
  --expected-host-fingerprint <fingerprint>
  --dry-run
EOF
}

log() {
    printf '%s\n' "$*" >&2
}

q() {
    printf '%q' "$1"
}

require_command() {
    local command_name="$1"
    command -v "${command_name}" >/dev/null 2>&1 || fail "preflight" "missing_command_${command_name}" "Required command not found: ${command_name}"
}

record_failure() {
    local phase="$1"
    local code="$2"
    local message="$3"

    if [[ -z "${PRIMARY_FAILURE_CODE}" ]]; then
        PRIMARY_FAILURE_PHASE="${phase}"
        PRIMARY_FAILURE_CODE="${code}"
    fi

    case "${phase}" in
        execution)
            PROOF_STATUS="FAIL"
            ;;
        artifact)
            ARTIFACT_STATUS="FAIL"
            ;;
        restore)
            RESTORE_STATUS="FAIL"
            ;;
    esac

    log "ERROR [${phase}:${code}] ${message}"
}

fail() {
    local phase="$1"
    local code="$2"
    local message="$3"

    record_failure "${phase}" "${code}" "${message}"
    FINAL_EXIT_CODE=1
    exit 1
}

prepare_paths() {
    if [[ -z "${RUN_ID}" ]]; then
        RUN_ID="$(python3 - <<'PY'
import secrets
from datetime import datetime, timezone

stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
print(f"{stamp}-{secrets.token_hex(4)}")
PY
)"
    fi

    ARTIFACT_DIR="${OUTPUT_DIR}/${RUN_ID}"
    RESULT_PATH="${ARTIFACT_DIR}/result.json"
    MANIFEST_PATH="${ARTIFACT_DIR}/manifest.json"
    ARCHIVE_PATH="${ARTIFACT_DIR}/repo.tgz"
    GUEST_OUTPUT_DIR="${ARTIFACT_DIR}/guest-output"
    SSH_KNOWN_HOSTS_PATH="${ARTIFACT_DIR}/ssh-known_hosts"
    STAGED_GUEST_INSTALLER_PATH="${ARTIFACT_DIR}/guest-install-deps.sh"
    STAGED_GUEST_PROOF_RUNNER_PATH="${ARTIFACT_DIR}/guest-blue-team-proof.sh"

    rm -rf "${ARTIFACT_DIR}"
    mkdir -p "${ARTIFACT_DIR}"
    : > "${SSH_KNOWN_HOSTS_PATH}"
    chmod 0600 "${SSH_KNOWN_HOSTS_PATH}"
}

write_result() {
    RESULT_PATH="${RESULT_PATH}" \
    RUN_ID="${RUN_ID}" \
    ARTIFACT_DIR="${ARTIFACT_DIR}" \
    VM_NAME="${VM_NAME}" \
    SNAPSHOT_NAME="${SNAPSHOT_NAME}" \
    SNAPSHOT_ID="${SNAPSHOT_ID}" \
    REPO_REF="${REPO_REF}" \
    RESOLVED_COMMIT_SHA="${RESOLVED_COMMIT_SHA}" \
    ARCHIVE_SHA256="${ARCHIVE_SHA256}" \
    SSH_IDENTITY_MODE="${SSH_IDENTITY_MODE}" \
    SSH_HOST_KEY_ALGORITHM="${SSH_HOST_KEY_ALGORITHM}" \
    ASSURANCE_LEVEL="${ASSURANCE_LEVEL}" \
    PROOF_STATUS="${PROOF_STATUS}" \
    ARTIFACT_STATUS="${ARTIFACT_STATUS}" \
    RESTORE_STATUS="${RESTORE_STATUS}" \
    OVERALL_STATUS="${OVERALL_STATUS}" \
    PRIMARY_FAILURE_PHASE="${PRIMARY_FAILURE_PHASE}" \
    PRIMARY_FAILURE_CODE="${PRIMARY_FAILURE_CODE}" \
    python3 - <<'PY'
import json
import os

payload = {
    "run_id": os.environ["RUN_ID"],
    "artifact_dir": os.environ["ARTIFACT_DIR"],
    "vm_name": os.environ["VM_NAME"],
    "snapshot_name": os.environ["SNAPSHOT_NAME"],
    "snapshot_id": os.environ["SNAPSHOT_ID"],
    "repo_ref": os.environ["REPO_REF"],
    "resolved_commit_sha": os.environ["RESOLVED_COMMIT_SHA"],
    "archive_sha256": os.environ["ARCHIVE_SHA256"],
    "ssh_identity_mode": os.environ["SSH_IDENTITY_MODE"],
    "ssh_host_key_algorithm": os.environ["SSH_HOST_KEY_ALGORITHM"],
    "assurance_level": os.environ["ASSURANCE_LEVEL"],
    "proof_status": os.environ["PROOF_STATUS"],
    "artifact_status": os.environ["ARTIFACT_STATUS"],
    "restore_status": os.environ["RESTORE_STATUS"],
    "overall_status": os.environ["OVERALL_STATUS"],
    "primary_failure_phase": os.environ["PRIMARY_FAILURE_PHASE"],
    "primary_failure_code": os.environ["PRIMARY_FAILURE_CODE"],
}

with open(os.environ["RESULT_PATH"], "w", encoding="utf-8") as handle:
    json.dump(payload, handle, indent=2, sort_keys=True)
    handle.write("\n")
PY
}

set_overall_status() {
    if [[ -n "${PRIMARY_FAILURE_CODE}" ]]; then
        OVERALL_STATUS="FAIL"
        return 0
    fi

    if [[ "${PROOF_STATUS}" == "PASS" && "${ARTIFACT_STATUS}" == "PASS" && "${RESTORE_STATUS}" == "PASS" ]]; then
        OVERALL_STATUS="PASS"
        return 0
    fi

    if [[ "${PROOF_STATUS}" == "SKIPPED" && "${ARTIFACT_STATUS}" == "SKIPPED" && "${RESTORE_STATUS}" == "SKIPPED" ]]; then
        OVERALL_STATUS="SKIPPED"
        return 0
    fi

    OVERALL_STATUS="FAIL"
}

finalize() {
    local trap_exit=$?

    set +e

    if [[ "${RESTORE_REQUIRED}" == "1" ]]; then
        perform_final_restore
    fi

    set_overall_status
    write_result

    if [[ "${FINAL_EXIT_CODE}" -eq 0 && "${trap_exit}" -ne 0 ]]; then
        FINAL_EXIT_CODE="${trap_exit}"
    fi

    if [[ -n "${PRIMARY_FAILURE_CODE}" && "${FINAL_EXIT_CODE}" -eq 0 ]]; then
        FINAL_EXIT_CODE=1
    fi

    trap - EXIT
    exit "${FINAL_EXIT_CODE}"
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --vm-name)
                VM_NAME="${2:-}"
                shift 2
                ;;
            --snapshot-name)
                SNAPSHOT_NAME="${2:-}"
                shift 2
                ;;
            --ssh-host)
                SSH_HOST="${2:-}"
                shift 2
                ;;
            --ssh-user)
                SSH_USER="${2:-}"
                shift 2
                ;;
            --ssh-port)
                SSH_PORT="${2:-}"
                shift 2
                ;;
            --remote-proof-root)
                REMOTE_PROOF_ROOT="${2:-}"
                shift 2
                ;;
            --output-dir)
                OUTPUT_DIR="${2:-}"
                shift 2
                ;;
            --repo-ref)
                REPO_REF="${2:-}"
                shift 2
                ;;
            --run-id)
                RUN_ID="${2:-}"
                shift 2
                ;;
            --expected-host-key-algorithm)
                EXPECTED_HOST_KEY_ALGORITHM="${2:-}"
                shift 2
                ;;
            --expected-host-fingerprint)
                EXPECTED_HOST_FINGERPRINT="${2:-}"
                shift 2
                ;;
            --dry-run)
                DRY_RUN=1
                shift
                ;;
            -h|--help)
                usage
                exit 0
                ;;
            *)
                usage
                exit 1
                ;;
        esac
    done

    [[ -n "${VM_NAME}" ]] || { usage; exit 1; }
    [[ -n "${SNAPSHOT_NAME}" ]] || { usage; exit 1; }
    [[ -n "${SSH_HOST}" ]] || { usage; exit 1; }
    [[ -n "${SSH_USER}" ]] || { usage; exit 1; }

    if [[ -n "${EXPECTED_HOST_FINGERPRINT}" || -n "${EXPECTED_HOST_KEY_ALGORITHM}" ]]; then
        [[ -n "${EXPECTED_HOST_FINGERPRINT}" && -n "${EXPECTED_HOST_KEY_ALGORITHM}" ]] || {
            usage
            exit 1
        }
        SSH_IDENTITY_MODE="pinned"
        SSH_HOST_KEY_ALGORITHM="${EXPECTED_HOST_KEY_ALGORITHM}"
        ASSURANCE_LEVEL="proof-grade"
    fi

    if [[ -z "${REMOTE_PROOF_ROOT}" ]]; then
        REMOTE_PROOF_ROOT="/home/${SSH_USER}/proof"
    fi

    REMOTE_INPUT_DIR="${REMOTE_PROOF_ROOT}/input"
    REMOTE_WORKDIR="${REMOTE_PROOF_ROOT}/workdir"
    REMOTE_OUTPUT_DIR="${REMOTE_PROOF_ROOT}/output/current"
}

verify_clean_worktree() {
    local status
    status="$(git status --porcelain --ignore-submodules=none)"
    [[ -z "${status}" ]] || fail "preflight" "dirty_worktree" "Commit-only proof requires a clean git worktree."
}

resolve_commit() {
    RESOLVED_COMMIT_SHA="$(git rev-parse --verify "${REPO_REF}^{commit}")" || fail "preflight" "invalid_repo_ref" "Unable to resolve repo ref ${REPO_REF}."
}

resolve_snapshot_id() {
    local snapshot_json
    local match_output
    local match_count

    snapshot_json="$(prlctl snapshot-list "${VM_NAME}" --json)" || fail "preflight" "snapshot_list_failed" "Unable to list snapshots for ${VM_NAME}."
    match_output="$(
        SNAPSHOT_JSON="${snapshot_json}" SNAPSHOT_NAME="${SNAPSHOT_NAME}" python3 - <<'PY'
import json
import os

target = os.environ["SNAPSHOT_NAME"]
payload = json.loads(os.environ["SNAPSHOT_JSON"])
matches = []

def walk(node):
    if isinstance(node, dict):
        if node.get("name") == target and node.get("id"):
            matches.append(node["id"])
        for value in node.values():
            walk(value)
        return
    if isinstance(node, list):
        for item in node:
            walk(item)

walk(payload)
print(json.dumps(matches))
PY
    )" || fail "preflight" "snapshot_parse_failed" "Unable to parse snapshot-list output for ${VM_NAME}."

    match_count="$(MATCH_OUTPUT="${match_output}" python3 - <<'PY'
import json
import os
print(len(json.loads(os.environ["MATCH_OUTPUT"])))
PY
)"

    if [[ "${match_count}" == "0" ]]; then
        fail "preflight" "snapshot_missing" "Snapshot ${SNAPSHOT_NAME} was not found for ${VM_NAME}."
    fi

    if [[ "${match_count}" != "1" ]]; then
        fail "preflight" "snapshot_ambiguous" "Snapshot ${SNAPSHOT_NAME} resolved to more than one id for ${VM_NAME}."
    fi

    SNAPSHOT_ID="$(MATCH_OUTPUT="${match_output}" python3 - <<'PY'
import json
import os
print(json.loads(os.environ["MATCH_OUTPUT"])[0])
PY
)"
}

create_archive() {
    git archive --format=tar.gz --output "${ARCHIVE_PATH}" "${RESOLVED_COMMIT_SHA}" || fail "preflight" "archive_creation_failed" "Unable to create commit archive for ${RESOLVED_COMMIT_SHA}."
    ARCHIVE_SHA256="$(shasum -a 256 "${ARCHIVE_PATH}" | awk '{print $1}')" || fail "preflight" "archive_hash_failed" "Unable to hash archive ${ARCHIVE_PATH}."
}

stage_helper_from_ref() {
    local ref_path="$1"
    local destination_path="$2"

    git show "${RESOLVED_COMMIT_SHA}:${ref_path}" > "${destination_path}" || fail "preflight" "helper_stage_failed" "Unable to stage ${ref_path} from ${RESOLVED_COMMIT_SHA}."
    chmod 0755 "${destination_path}" || fail "preflight" "helper_stage_failed" "Unable to chmod staged helper ${destination_path}."
}

stage_guest_helpers() {
    stage_helper_from_ref "ops/proof/guest-install-deps.sh" "${STAGED_GUEST_INSTALLER_PATH}"
    stage_helper_from_ref "ops/proof/guest-blue-team-proof.sh" "${STAGED_GUEST_PROOF_RUNNER_PATH}"
}

write_manifest() {
    MANIFEST_PATH="${MANIFEST_PATH}" \
    RUN_ID="${RUN_ID}" \
    VM_NAME="${VM_NAME}" \
    SNAPSHOT_NAME="${SNAPSHOT_NAME}" \
    SNAPSHOT_ID="${SNAPSHOT_ID}" \
    REPO_REF="${REPO_REF}" \
    RESOLVED_COMMIT_SHA="${RESOLVED_COMMIT_SHA}" \
    ARCHIVE_FILENAME="$(basename "${ARCHIVE_PATH}")" \
    ARCHIVE_SHA256="${ARCHIVE_SHA256}" \
    python3 - <<'PY'
import json
import os

payload = {
    "run_id": os.environ["RUN_ID"],
    "vm_name": os.environ["VM_NAME"],
    "snapshot_name": os.environ["SNAPSHOT_NAME"],
    "snapshot_id": os.environ["SNAPSHOT_ID"],
    "repo_ref": os.environ["REPO_REF"],
    "resolved_commit_sha": os.environ["RESOLVED_COMMIT_SHA"],
    "archive_filename": os.environ["ARCHIVE_FILENAME"],
    "archive_sha256": os.environ["ARCHIVE_SHA256"],
}

with open(os.environ["MANIFEST_PATH"], "w", encoding="utf-8") as handle:
    json.dump(payload, handle, indent=2, sort_keys=True)
    handle.write("\n")
PY
}

ssh_target() {
    printf '%s@%s' "${SSH_USER}" "${SSH_HOST}"
}

ssh_strict_mode() {
    if [[ "${SSH_IDENTITY_MODE}" == "pinned" ]]; then
        printf 'yes'
        return 0
    fi

    printf 'accept-new'
}

run_ssh() {
    local command_string="$1"
    local strict
    strict="$(ssh_strict_mode)"

    ssh \
        -p "${SSH_PORT}" \
        -o "BatchMode=yes" \
        -o "UserKnownHostsFile=${SSH_KNOWN_HOSTS_PATH}" \
        -o "StrictHostKeyChecking=${strict}" \
        -o "ConnectTimeout=5" \
        "$(ssh_target)" \
        "${command_string}"
}

run_scp() {
    local source="$1"
    local destination="$2"
    local strict
    strict="$(ssh_strict_mode)"

    scp \
        -P "${SSH_PORT}" \
        -o "BatchMode=yes" \
        -o "UserKnownHostsFile=${SSH_KNOWN_HOSTS_PATH}" \
        -o "StrictHostKeyChecking=${strict}" \
        "${source}" \
        "${destination}"
}

run_scp_recursive() {
    local source="$1"
    local destination="$2"
    local strict
    strict="$(ssh_strict_mode)"

    scp \
        -r \
        -P "${SSH_PORT}" \
        -o "BatchMode=yes" \
        -o "UserKnownHostsFile=${SSH_KNOWN_HOSTS_PATH}" \
        -o "StrictHostKeyChecking=${strict}" \
        "${source}" \
        "${destination}"
}

restore_to_snapshot_for_run() {
    prlctl stop "${VM_NAME}" --kill >/dev/null 2>&1 || true
    prlctl snapshot-switch "${VM_NAME}" --id "${SNAPSHOT_ID}" --skip-resume || fail "execution" "snapshot_switch_failed" "Unable to restore snapshot ${SNAPSHOT_ID} before run."
    prlctl start "${VM_NAME}" || fail "execution" "vm_start_failed" "Unable to start VM ${VM_NAME}."
    RESTORE_REQUIRED=1
}

perform_final_restore() {
    prlctl stop "${VM_NAME}" --kill >/dev/null 2>&1 || true
    if ! prlctl snapshot-switch "${VM_NAME}" --id "${SNAPSHOT_ID}" --skip-resume; then
        record_failure "restore" "restore_failed" "Unable to restore snapshot ${SNAPSHOT_ID} after run."
        FINAL_EXIT_CODE=1
        return 1
    fi

    RESTORE_STATUS="PASS"
    return 0
}

fetch_pinned_host_key() {
    local keyscan_output=""
    local attempt=1
    local known_hosts_source="${ARTIFACT_DIR}/ssh-keyscan.tmp"
    local actual_fingerprint

    while [[ "${attempt}" -le "${HOST_KEY_FETCH_ATTEMPTS}" ]]; do
        if keyscan_output="$(ssh-keyscan -t "${EXPECTED_HOST_KEY_ALGORITHM}" -p "${SSH_PORT}" "${SSH_HOST}" 2>/dev/null)" && [[ -n "${keyscan_output}" ]]; then
            printf '%s\n' "${keyscan_output}" > "${known_hosts_source}"
            break
        fi
        if [[ "${attempt}" -lt "${HOST_KEY_FETCH_ATTEMPTS}" ]]; then
            sleep "${HOST_KEY_FETCH_DELAY_SECONDS}"
        fi
        attempt="$((attempt + 1))"
    done

    if [[ -z "${keyscan_output}" ]]; then
        fail "execution" "host_key_fetch_failed" "Unable to fetch SSH host key for ${SSH_HOST}."
    fi

    actual_fingerprint="$(ssh-keygen -lf "${known_hosts_source}" -E sha256 | awk 'NR==1 {print $2}')" || fail "execution" "host_key_fetch_failed" "Unable to fingerprint fetched SSH host key for ${SSH_HOST}."
    if [[ "${actual_fingerprint}" != "${EXPECTED_HOST_FINGERPRINT}" ]]; then
        fail "execution" "host_key_fingerprint_mismatch" "SSH host key fingerprint mismatch for ${SSH_HOST}."
    fi

    cp "${known_hosts_source}" "${SSH_KNOWN_HOSTS_PATH}"
}

wait_for_ssh() {
    local attempt=1

    while [[ "${attempt}" -le "${SSH_WAIT_ATTEMPTS}" ]]; do
        if run_ssh "true" >/dev/null 2>&1; then
            return 0
        fi
        if [[ "${attempt}" -lt "${SSH_WAIT_ATTEMPTS}" ]]; then
            sleep "${SSH_WAIT_DELAY_SECONDS}"
        fi
        attempt="$((attempt + 1))"
    done

    fail "execution" "ssh_unreachable" "Unable to establish SSH connectivity to ${SSH_HOST}."
}

establish_ssh_identity() {
    if [[ "${SSH_IDENTITY_MODE}" == "pinned" ]]; then
        fetch_pinned_host_key
    fi

    wait_for_ssh
}

prepare_remote_input() {
    local command_string
    command_string="rm -rf $(q "${REMOTE_INPUT_DIR}") && mkdir -p $(q "${REMOTE_INPUT_DIR}")"
    run_ssh "${command_string}" || fail "execution" "remote_input_prepare_failed" "Unable to prepare remote input directory."
}

copy_inputs() {
    local target
    target="$(ssh_target)"

    run_scp "${ARCHIVE_PATH}" "${target}:${REMOTE_INPUT_DIR}/repo.tgz" || fail "execution" "remote_copy_failed" "Unable to copy repo archive to remote input."
    run_scp "${MANIFEST_PATH}" "${target}:${REMOTE_INPUT_DIR}/manifest.json" || fail "execution" "remote_copy_failed" "Unable to copy manifest to remote input."
    run_scp "${STAGED_GUEST_INSTALLER_PATH}" "${target}:${REMOTE_INPUT_DIR}/guest-install-deps.sh" || fail "execution" "remote_copy_failed" "Unable to copy guest installer to remote input."
    run_scp "${STAGED_GUEST_PROOF_RUNNER_PATH}" "${target}:${REMOTE_INPUT_DIR}/guest-blue-team-proof.sh" || fail "execution" "remote_copy_failed" "Unable to copy guest proof runner to remote input."
}

run_remote_proof() {
    local command_string
    local remote_state_dir

    remote_state_dir="${BT_STATE_DIR:-/var/lib/blue-team-vm}"
    command_string="chmod 755 $(q "${REMOTE_INPUT_DIR}/guest-install-deps.sh") $(q "${REMOTE_INPUT_DIR}/guest-blue-team-proof.sh") && "
    command_string+="BT_STATE_DIR=$(q "${remote_state_dir}") "
    command_string+="BT_PROOF_INPUT_DIR=$(q "${REMOTE_INPUT_DIR}") "
    command_string+="BT_PROOF_WORKDIR=$(q "${REMOTE_WORKDIR}") "
    command_string+="BT_PROOF_OUTPUT_DIR=$(q "${REMOTE_OUTPUT_DIR}") "
    command_string+="$(q "${REMOTE_INPUT_DIR}/guest-blue-team-proof.sh")"

    if ! run_ssh "${command_string}"; then
        record_failure "execution" "proof_command_failed" "Guest proof runner exited non-zero."
        FINAL_EXIT_CODE=1
        return 1
    fi

    PROOF_STATUS="PASS"
    return 0
}

collect_artifacts() {
    mkdir -p "${GUEST_OUTPUT_DIR}"
    if ! run_scp_recursive "$(ssh_target):${REMOTE_OUTPUT_DIR}" "${GUEST_OUTPUT_DIR}"; then
        fail "artifact" "artifact_collection_failed" "Unable to collect guest proof output."
    fi

    ARTIFACT_STATUS="PASS"
}

run_workflow() {
    restore_to_snapshot_for_run
    establish_ssh_identity
    prepare_remote_input
    copy_inputs

    run_remote_proof || true
    collect_artifacts
}

main() {
    cd "${REPO_ROOT}"
    parse_args "$@"
    prepare_paths
    trap finalize EXIT

    require_command git
    require_command prlctl
    require_command python3
    require_command shasum
    require_command ssh
    require_command scp
    require_command tar
    if [[ "${SSH_IDENTITY_MODE}" == "pinned" ]]; then
        require_command ssh-keyscan
        require_command ssh-keygen
    fi

    verify_clean_worktree
    resolve_commit
    resolve_snapshot_id
    create_archive
    stage_guest_helpers
    write_manifest

    if [[ "${DRY_RUN}" == "1" ]]; then
        log "Dry-run completed through host preflight and provenance capture."
        return 0
    fi

    run_workflow
}

main "$@"
