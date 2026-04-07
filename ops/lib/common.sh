#!/usr/bin/env bash

set -euo pipefail

BT_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BT_ROOT_DIR="$(cd "${BT_LIB_DIR}/../.." && pwd)"

: "${BT_STATE_DIR:=/var/lib/blue-team-vm}"
: "${BT_BACKUP_DIR:=${BT_STATE_DIR}/backups}"
: "${BT_RUNTIME_DIR:=${BT_STATE_DIR}/runtime}"
: "${BT_MARKER_PATH:=${BT_STATE_DIR}/host-baseline-v1.json}"
: "${BT_MANAGED_CHAIN:=BLUE_TEAM_DEMO_V1}"
: "${BT_MANAGED_COMMENT:=BLUE_TEAM_MANAGED}"
: "${BT_RECORD_TYPE_CHECK:=check}"
: "${BT_RECORD_TYPE_PLANE_SUMMARY:=plane_summary}"
: "${BT_RECORD_TYPE_OVERALL_SUMMARY:=overall_summary}"
: "${BT_STATUS_PASS:=PASS}"
: "${BT_STATUS_DEGRADED:=DEGRADED}"
: "${BT_STATUS_FAIL:=FAIL}"
: "${BT_STATUS_SKIPPED:=SKIPPED}"
: "${BT_BASELINE_SCHEMA_VERSION:=1}"
: "${BT_BUNDLE_VERSION:=phase1-host-v1}"
: "${BT_BASELINE_SCOPE:=host}"
: "${BT_COMPOSE_APP_FILE:=${BT_ROOT_DIR}/compose.app.yml}"
: "${BT_COMPOSE_OBS_FILE:=${BT_ROOT_DIR}/compose.obs.yml}"
: "${BT_HONEYPOT_SOURCE:=/opt/blue-team/nginx/includes/blue-team-honeypot.conf}"
: "${BT_HONEYPOT_RUNTIME:=/etc/nginx/conf.d/blue-team-honeypot.conf}"
: "${BT_CROWDSEC_TIMEOUT_SECONDS:=5}"
: "${BT_DRY_RUN:=0}"

bt_now_utc() {
    date -u +"%Y-%m-%dT%H:%M:%SZ"
}

bt_log() {
    printf '%s\n' "$*" >&2
}

bt_warn() {
    bt_log "WARN: $*"
}

bt_die() {
    bt_log "ERROR: $*"
    exit 1
}

bt_require_root() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        return 0
    fi

    if [[ "${EUID}" -ne 0 ]]; then
        bt_die "This action requires root."
    fi
}

bt_mkdir() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN mkdir -p $*"
        return 0
    fi
    mkdir -p "$@"
}

bt_run() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN $*"
        return 0
    fi
    "$@"
}

bt_json_escape() {
    python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))'
}

bt_emit_record() {
    local record_type="$1"
    local check_id="$2"
    local plane="$3"
    local status="$4"
    local message="$5"
    local remediation_hint="${6:-}"

    python3 - "$record_type" "$check_id" "$plane" "$status" "$message" "$remediation_hint" <<'PY'
import json
import sys

record = {
    "record_type": sys.argv[1],
    "check_id": sys.argv[2],
    "plane": sys.argv[3],
    "status": sys.argv[4],
    "message": sys.argv[5],
    "remediation_hint": sys.argv[6],
}
print(json.dumps(record, separators=(",", ":")))
PY
}

bt_emit_check() {
    bt_emit_record "${BT_RECORD_TYPE_CHECK}" "$@"
}

bt_emit_plane_summary() {
    local plane="$1"
    local status="$2"
    local message="$3"
    local remediation_hint="${4:-}"
    bt_emit_record "${BT_RECORD_TYPE_PLANE_SUMMARY}" "${plane}.summary" "${plane}" "${status}" "${message}" "${remediation_hint}"
}

bt_emit_overall_summary() {
    local status="$1"
    local message="$2"
    local remediation_hint="${3:-}"
    bt_emit_record "${BT_RECORD_TYPE_OVERALL_SUMMARY}" "overall.summary" "overall" "${status}" "${message}" "${remediation_hint}"
}

bt_status_rank() {
    case "$1" in
        "${BT_STATUS_FAIL}") echo 4 ;;
        "${BT_STATUS_DEGRADED}") echo 3 ;;
        "${BT_STATUS_PASS}") echo 2 ;;
        "${BT_STATUS_SKIPPED}") echo 1 ;;
        *) echo 0 ;;
    esac
}

bt_aggregate_statuses() {
    local statuses=("$@")
    local saw_pass=0
    local saw_degraded=0
    local saw_fail=0
    local saw_non_skipped=0

    for status in "${statuses[@]}"; do
        case "${status}" in
            "${BT_STATUS_FAIL}")
                saw_fail=1
                saw_non_skipped=1
                ;;
            "${BT_STATUS_DEGRADED}")
                saw_degraded=1
                saw_non_skipped=1
                ;;
            "${BT_STATUS_PASS}")
                saw_pass=1
                saw_non_skipped=1
                ;;
            "${BT_STATUS_SKIPPED}")
                ;;
            *)
                saw_fail=1
                saw_non_skipped=1
                ;;
        esac
    done

    if [[ "${saw_fail}" == "1" ]]; then
        echo "${BT_STATUS_FAIL}"
    elif [[ "${saw_degraded}" == "1" ]]; then
        echo "${BT_STATUS_DEGRADED}"
    elif [[ "${saw_non_skipped}" == "0" ]]; then
        echo "${BT_STATUS_SKIPPED}"
    else
        echo "${BT_STATUS_PASS}"
    fi
}

bt_restart_service() {
    local services=("$@")
    local service

    for service in "${services[@]}"; do
        if command -v systemctl >/dev/null 2>&1 && systemctl list-unit-files "${service}.service" >/dev/null 2>&1; then
            bt_run systemctl restart "${service}"
            return 0
        fi
    done

    bt_warn "No matching systemd service found to restart: ${services[*]}"
    return 0
}

bt_write_file() {
    local path="$1"
    local content="$2"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN write ${path}"
        return 0
    fi

    mkdir -p "$(dirname "${path}")"
    printf '%s' "${content}" > "${path}"
}

bt_backup_file() {
    local source="$1"
    local destination="$2"

    if [[ ! -e "${source}" ]]; then
        return 0
    fi

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN backup ${source} -> ${destination}"
        return 0
    fi

    mkdir -p "$(dirname "${destination}")"
    cp -a "${source}" "${destination}"
}

bt_marker_valid() {
    local marker_path="${1:-${BT_MARKER_PATH}}"

    [[ -r "${marker_path}" ]] || return 1

    python3 - "${marker_path}" "${BT_BASELINE_SCHEMA_VERSION}" "${BT_BUNDLE_VERSION}" "${BT_BASELINE_SCOPE}" <<'PY'
import json
import sys

path = sys.argv[1]
expected_schema = sys.argv[2]
expected_bundle = sys.argv[3]
expected_scope = sys.argv[4]

with open(path, "r", encoding="utf-8") as handle:
    marker = json.load(handle)

required = (
    marker.get("baseline_schema_version") == expected_schema
    and marker.get("managed_by_bundle_version") == expected_bundle
    and marker.get("baseline_scope") == expected_scope
    and marker.get("status") == "valid"
)

raise SystemExit(0 if required else 1)
PY
}

bt_write_marker() {
    local managed_surfaces_json="$1"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN write marker ${BT_MARKER_PATH}"
        return 0
    fi

    mkdir -p "$(dirname "${BT_MARKER_PATH}")"

    python3 - "${BT_MARKER_PATH}" "${BT_BASELINE_SCHEMA_VERSION}" "${BT_BUNDLE_VERSION}" "${BT_BASELINE_SCOPE}" "$(bt_now_utc)" "${managed_surfaces_json}" <<'PY'
import json
import sys

path, schema_version, bundle_version, scope, completed_at, managed_surfaces_json = sys.argv[1:]
managed_surfaces = json.loads(managed_surfaces_json)

marker = {
    "version": 1,
    "baseline_schema_version": schema_version,
    "managed_by_bundle_version": bundle_version,
    "baseline_scope": scope,
    "status": "valid",
    "completed_at": completed_at,
    "managed_surfaces": managed_surfaces,
}

with open(path, "w", encoding="utf-8") as handle:
    json.dump(marker, handle, separators=(",", ":"))
PY
}

bt_invalidate_marker() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN remove marker ${BT_MARKER_PATH}"
        return 0
    fi

    rm -f "${BT_MARKER_PATH}"
}

bt_compose() {
    local compose_file="$1"
    shift
    docker compose -f "${compose_file}" "$@"
}

bt_compose_service_container_id() {
    local compose_file="$1"
    local service="$2"
    bt_compose "${compose_file}" ps -q "${service}" 2>/dev/null | head -n 1
}

bt_compose_service_running() {
    local compose_file="$1"
    local service="$2"
    local container_id
    container_id="$(bt_compose_service_container_id "${compose_file}" "${service}")"
    [[ -n "${container_id}" ]] || return 1
    [[ "$(docker inspect -f '{{.State.Status}}' "${container_id}" 2>/dev/null)" == "running" ]]
}

bt_compose_service_healthy() {
    local compose_file="$1"
    local service="$2"
    local container_id
    container_id="$(bt_compose_service_container_id "${compose_file}" "${service}")"
    [[ -n "${container_id}" ]] || return 1

    local health
    health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "${container_id}" 2>/dev/null || echo "missing")"
    [[ "${health}" == "healthy" ]]
}

bt_wait_for_container_state() {
    local compose_file="$1"
    local service="$2"
    local target="$3"
    local timeout_seconds="$4"

    local deadline=$((SECONDS + timeout_seconds))
    while (( SECONDS < deadline )); do
        if [[ "${target}" == "running" ]] && bt_compose_service_running "${compose_file}" "${service}"; then
            return 0
        fi
        if [[ "${target}" == "healthy" ]] && bt_compose_service_healthy "${compose_file}" "${service}"; then
            return 0
        fi
        sleep 2
    done
    return 1
}

bt_local_listen_ports() {
    if command -v ss >/dev/null 2>&1; then
        ss -tlnH | awk '{print $4}' | sed -E 's/.*:([0-9]+)$/\1/' | sort -n | uniq
        return 0
    fi

    if command -v netstat >/dev/null 2>&1; then
        netstat -tln | awk 'NR>2 {print $4}' | sed -E 's/.*:([0-9]+)$/\1/' | sort -n | uniq
        return 0
    fi

    return 1
}

bt_compose_network_drivers() {
    local compose_file="$1"

    docker compose -f "${compose_file}" config --format json | python3 -c '
import json
import sys

config = json.load(sys.stdin)
networks = config.get("networks", {})
for name, definition in networks.items():
    driver = definition.get("driver", "bridge")
    print(f"{name}:{driver}")
'
}

bt_env_value() {
    local var_name="$1"

    if [[ -n "${!var_name:-}" ]]; then
        printf '%s\n' "${!var_name}"
        return 0
    fi

    local env_file="${BT_ROOT_DIR}/.env"
    [[ -r "${env_file}" ]] || return 1

    python3 - "${env_file}" "${var_name}" <<'PY'
import sys

env_file, wanted = sys.argv[1:]

with open(env_file, "r", encoding="utf-8") as handle:
    for raw_line in handle:
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        if key != wanted:
            continue
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"\"", "'"}:
            value = value[1:-1]
        print(value)
        raise SystemExit(0)

raise SystemExit(1)
PY
}

bt_kernel_release() {
    uname -r
}

bt_kernel_version_ge() {
    local minimum_version="$1"
    local current_version
    current_version="$(bt_kernel_release)"

    python3 - "${current_version}" "${minimum_version}" <<'PY'
import re
import sys

current_raw, minimum_raw = sys.argv[1:]

def normalize(raw):
    base = raw.split("-", 1)[0]
    parts = [int(p) for p in re.findall(r"\d+", base)]
    while len(parts) < 3:
        parts.append(0)
    return tuple(parts[:3])

raise SystemExit(0 if normalize(current_raw) >= normalize(minimum_raw) else 1)
PY
}
