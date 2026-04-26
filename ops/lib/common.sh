#!/usr/bin/env bash

set -euo pipefail

BT_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BT_ROOT_DIR="$(cd "${BT_LIB_DIR}/../.." && pwd)"
# shellcheck source=./config-contract.sh
source "${BT_LIB_DIR}/config-contract.sh"

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
: "${BT_HONEYPOT_RUNTIME:=/etc/nginx/includes/blue-team-honeypot.conf}"
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

bt_run_plane_check() {
    local plane="$1"
    local -n _bt_rpc_statuses="$2"
    local check_id="$3"
    local status_on_success="$4"
    local message="$5"
    local remediation="$6"
    shift 6

    if "$@"; then
        bt_emit_check "${check_id}" "${plane}" "${status_on_success}" "${message}" "${remediation}"
        _bt_rpc_statuses+=("${status_on_success}")
        return 0
    fi

    local failure_status="${BT_STATUS_FAIL}"
    if [[ "${status_on_success}" == "${BT_STATUS_DEGRADED}" ]]; then
        failure_status="${BT_STATUS_DEGRADED}"
    fi
    bt_emit_check "${check_id}" "${plane}" "${failure_status}" "${message}" "${remediation}"
    _bt_rpc_statuses+=("${failure_status}")
    return 1
}

bt_emit_plane_check_summary() {
    local plane="$1"
    local -n _bt_epcs_statuses="$2"
    local message="$3"
    local status

    if [[ "${#_bt_epcs_statuses[@]}" -eq 0 ]]; then
        status="${BT_STATUS_SKIPPED}"
    else
        status="$(bt_aggregate_statuses "${_bt_epcs_statuses[@]}")"
    fi
    bt_emit_plane_summary "${plane}" "${status}" "${message}" "Inspect ${plane}-plane checks."
    [[ "${status}" != "${BT_STATUS_FAIL}" ]]
}

: "${BT_LAST_ASSIGNED_PORT:=3000}"

bt_port_is_reserved() {
    local port="$1"
    shift

    local reserved
    for reserved in "$@"; do
        [[ -n "${reserved}" && "${reserved}" == "${port}" ]] && return 0
    done

    return 1
}

bt_find_free_port() {
    local out_var=""
    if [[ $# -gt 0 && ! "${1}" =~ ^[0-9]+$ ]]; then
        out_var="$1"
        shift
    fi

    local p=$(( BT_LAST_ASSIGNED_PORT + 1 ))
    while [[ $p -le 9001 ]]; do
        if ! bt_port_in_use "$p" && ! bt_port_is_reserved "$p" "$@"; then
            BT_LAST_ASSIGNED_PORT=$p
            if [[ -n "${out_var}" ]]; then
                printf -v "${out_var}" '%s' "$p"
            else
                printf '%s\n' "$p"
            fi
            return 0
        fi
        p=$((p + 1))
    done
    return 1
}

bt_port_in_use() {
    local port="$1"
    local has_local_probe=0

    if command -v ss >/dev/null 2>&1; then
        has_local_probe=1
        ss -tlnH 2>/dev/null | awk '{print $4}' | grep -qE "(^|[:.])${port}$" && return 0
    fi
    if command -v lsof >/dev/null 2>&1; then
        has_local_probe=1
        lsof -nP -iTCP:"${port}" -sTCP:LISTEN >/dev/null 2>&1 && return 0
    fi
    if command -v netstat >/dev/null 2>&1; then
        has_local_probe=1
        netstat -an 2>/dev/null | awk '/LISTEN/ {print $4}' | grep -qE "(^|[:.])${port}$" && return 0
    fi

    if [[ "${has_local_probe}" == "1" ]]; then
        return 1
    fi

    (echo >/dev/tcp/localhost/"${port}") >/dev/null 2>&1 && return 0
    return 1
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
    bt_preload_compose_env "${compose_file}"
    docker compose -f "${compose_file}" "$@"
}

bt_default_app_plane_network_name() {
    printf '%s_app-plane\n' "${COMPOSE_PROJECT_NAME:-jobs-borads}"
}

bt_default_app_plane_subnet() {
    printf '%s\n' "172.29.0.0/24"
}

bt_docker_network_exists() {
    local network_name="$1"
    [[ -n "${network_name}" ]] || return 1
    docker network inspect "${network_name}" >/dev/null 2>&1
}

bt_docker_network_subnets() {
    local network_name="$1"
    docker network inspect -f '{{range .IPAM.Config}}{{println .Subnet}}{{end}}' "${network_name}" 2>/dev/null || true
}

bt_app_plane_network_matches_contract() {
    local network_name="$1"
    local expected_subnet
    local subnet

    expected_subnet="$(bt_default_app_plane_subnet)"
    bt_docker_network_exists "${network_name}" || return 1

    while IFS= read -r subnet; do
        [[ -n "${subnet}" ]] || continue
        [[ "${subnet}" == "${expected_subnet}" ]] && return 0
    done < <(bt_docker_network_subnets "${network_name}")

    return 1
}

bt_create_app_plane_network() {
    local network_name="$1"
    local expected_subnet

    expected_subnet="$(bt_default_app_plane_subnet)"
    docker network create --driver bridge --subnet "${expected_subnet}" "${network_name}" >/dev/null
}

bt_container_network_names() {
    local container_name="$1"
    docker inspect -f '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "${container_name}" 2>/dev/null || true
}

bt_auto_detect_app_plane_network_name() {
    local candidate
    local container_name
    local candidates=()
    local seen=""

    for container_name in jobs-boards-nginx jobs-boards-postgres jobs-boards-laravel.test jobs-boards-redis; do
        while IFS= read -r candidate; do
            [[ -n "${candidate}" ]] || continue
            [[ "${candidate}" == *_app-plane ]] || continue
            bt_app_plane_network_matches_contract "${candidate}" || continue
            case " ${seen} " in
                *" ${candidate} "*) continue ;;
            esac
            candidates+=("${candidate}")
            seen+=" ${candidate}"
        done < <(bt_container_network_names "${container_name}")
    done

    if [[ "${#candidates[@]}" == 1 ]]; then
        printf '%s\n' "${candidates[0]}"
        return 0
    fi

    while IFS= read -r candidate; do
        [[ -n "${candidate}" ]] || continue
        [[ "${candidate}" == *_app-plane ]] || continue
        bt_app_plane_network_matches_contract "${candidate}" || continue
        case " ${seen} " in
            *" ${candidate} "*) continue ;;
        esac
        candidates+=("${candidate}")
        seen+=" ${candidate}"
    done < <(docker network ls --format '{{.Name}}' 2>/dev/null || true)

    if [[ "${#candidates[@]}" == 1 ]]; then
        printf '%s\n' "${candidates[0]}"
        return 0
    fi

    return 1
}

bt_ensure_app_plane_network() {
    local configured="${BT_APP_PLANE_NETWORK_NAME:-}"
    local requested=""
    local detected=""
    local expected_subnet=""

    expected_subnet="$(bt_default_app_plane_subnet)"

    if [[ -n "${configured}" ]]; then
        if bt_docker_network_exists "${configured}"; then
            if ! bt_app_plane_network_matches_contract "${configured}"; then
                bt_warn "Configured BT_APP_PLANE_NETWORK_NAME ${configured} does not match required app-plane subnet ${expected_subnet}."
                return 1
            fi

            export BT_APP_PLANE_NETWORK_NAME="${configured}"
            return 0
        fi

        bt_create_app_plane_network "${configured}" || return 1
        export BT_APP_PLANE_NETWORK_NAME="${configured}"
        return 0
    fi

    requested="$(bt_default_app_plane_network_name)"
    if bt_docker_network_exists "${requested}"; then
        if ! bt_app_plane_network_matches_contract "${requested}"; then
            bt_warn "Default app-plane network ${requested} does not match required app-plane subnet ${expected_subnet}."
            return 1
        fi

        export BT_APP_PLANE_NETWORK_NAME="${requested}"
        return 0
    fi

    detected="$(bt_auto_detect_app_plane_network_name || true)"
    if [[ -n "${detected}" ]]; then
        export BT_APP_PLANE_NETWORK_NAME="${detected}"
        bt_warn "Using detected compatible app-plane network ${detected} because ${requested} does not exist."
        return 0
    fi

    bt_create_app_plane_network "${requested}" || return 1
    export BT_APP_PLANE_NETWORK_NAME="${requested}"
    bt_warn "Created shared app-plane network ${requested} with subnet ${expected_subnet}."
    return 0
}

bt_compose_uses_shared_app_plane_network() {
    local compose_file="$1"

    [[ -r "${compose_file}" ]] || return 1
    grep -Fq 'BT_APP_PLANE_NETWORK_NAME' "${compose_file}" || return 1
    grep -Fq 'external: true' "${compose_file}"
}

bt_preload_compose_app_plane_network() {
    local compose_file="${1:-}"
    local preserved_env_keys="${2:-}"
    local detected=""

    [[ -n "${compose_file}" ]] || return 0
    bt_compose_uses_shared_app_plane_network "${compose_file}" || return 0

    if [[ -n "${preserved_env_keys}" ]] && bt_env_snapshot_has_key "${preserved_env_keys}" "BT_APP_PLANE_NETWORK_NAME"; then
        return 0
    fi

    if [[ -n "${BT_APP_PLANE_NETWORK_NAME:-}" ]]; then
        return 0
    fi

    detected="$(bt_auto_detect_app_plane_network_name || true)"
    if [[ -n "${detected}" ]]; then
        export BT_APP_PLANE_NETWORK_NAME="${detected}"
    fi
}

bt_preload_compose_env() {
    local compose_file="${1:-}"
    local root_env_file="${BT_ROOT_DIR}/.env"
    local compat_shell_env_file="${BT_COMPAT_SHELL_ENV_FILE:-${BT_RUNTIME_DIR}/compat.shell.env}"
    local obs_generated_env_file="${BT_OBS_GENERATED_ENV_FILE:-${BT_RUNTIME_DIR}/obs.generated.env}"
    local preserved_env_keys

    preserved_env_keys="$(bt_runtime_bridge_preserved_keys_snapshot)"

    if [[ -r "${obs_generated_env_file}" ]]; then
        bt_export_env_file_unless_preserved "${obs_generated_env_file}" "${preserved_env_keys}"
    fi

    if [[ -r "${root_env_file}" ]]; then
        bt_export_env_file_unless_preserved "${root_env_file}" "${preserved_env_keys}"
    fi

    if [[ -r "${compat_shell_env_file}" ]]; then
        bt_export_shell_assignment_file_unless_preserved "${compat_shell_env_file}" "${preserved_env_keys}"
    fi

    bt_preload_compose_app_plane_network "${compose_file}" "${preserved_env_keys}"
    bt_preload_compose_honeypot_source "${preserved_env_keys}"
}

bt_managed_honeypot_source() {
    printf '%s\n' "/opt/blue-team/nginx/includes/blue-team-honeypot.conf"
}

bt_repo_honeypot_source() {
    printf '%s\n' "${BT_ROOT_DIR}/docker/nginx/includes/blue-team-honeypot.conf"
}

bt_normalize_honeypot_source() {
    local honeypot_source="$1"

    case "${honeypot_source}" in
        /*)
            printf '%s\n' "${honeypot_source}"
            ;;
        ./*)
            printf '%s\n' "${BT_ROOT_DIR}/${honeypot_source#./}"
            ;;
        *)
            printf '%s\n' "${BT_ROOT_DIR}/${honeypot_source}"
            ;;
    esac
}

bt_resolve_honeypot_source() {
    local mode="${1:-host}"
    local repo_honeypot_source

    if [[ -n "${BT_HONEYPOT_SOURCE:-}" ]]; then
        bt_normalize_honeypot_source "${BT_HONEYPOT_SOURCE}"
        return 0
    fi

    case "${mode}" in
        compose)
            repo_honeypot_source="$(bt_repo_honeypot_source)"
            if [[ -f "${repo_honeypot_source}" ]]; then
                printf '%s\n' "${repo_honeypot_source}"
                return 0
            fi

            bt_managed_honeypot_source
            return 0
            ;;
        host)
            bt_managed_honeypot_source
            return 0
            ;;
        *)
            bt_die "Unsupported honeypot source resolution mode: ${mode}"
            ;;
    esac
}

bt_resolve_compose_honeypot_source() {
    bt_resolve_honeypot_source compose
}

bt_preload_compose_honeypot_source() {
    local preserved_env_keys="$1"
    local resolved_honeypot_source

    if bt_env_snapshot_has_key "${preserved_env_keys}" "BT_HONEYPOT_SOURCE"; then
        BT_HONEYPOT_SOURCE="$(bt_normalize_honeypot_source "${BT_HONEYPOT_SOURCE}")"
        export BT_HONEYPOT_SOURCE
        return 0
    fi

    if [[ -n "${BT_HONEYPOT_SOURCE:-}" ]]; then
        BT_HONEYPOT_SOURCE="$(bt_normalize_honeypot_source "${BT_HONEYPOT_SOURCE}")"
        export BT_HONEYPOT_SOURCE
        return 0
    fi

    resolved_honeypot_source="$(bt_resolve_honeypot_source compose)"
    export BT_HONEYPOT_SOURCE="${resolved_honeypot_source}"
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
    container_id="$(bt_compose_service_container_id "${compose_file}" "${service}" 2>/dev/null || true)"
    [[ -n "${container_id}" ]] || return 1
    [[ "$(docker inspect -f '{{.State.Status}}' "${container_id}" 2>/dev/null)" == "running" ]]
}

bt_compose_service_publishes_host_port() {
    local compose_file="$1"
    local service="$2"
    local expected_port="$3"
    local container_id=""
    local state=""
    local bindings_json=""

    container_id="$(bt_compose_service_container_id "${compose_file}" "${service}" 2>/dev/null || true)"
    [[ -n "${container_id}" ]] || return 1

    state="$(docker inspect -f '{{.State.Status}}' "${container_id}" 2>/dev/null || true)"
    case "${state}" in
        running|restarting|paused|created)
            ;;
        *)
            return 1
            ;;
    esac

    bindings_json="$(docker inspect -f '{{json .HostConfig.PortBindings}}' "${container_id}" 2>/dev/null || true)"
    [[ -n "${bindings_json}" && "${bindings_json}" != "null" ]] || return 1

    python3 - "${expected_port}" "${bindings_json}" <<'PY'
import json
import sys

expected_port = sys.argv[1]
bindings = json.loads(sys.argv[2])

for entries in bindings.values():
    if not entries:
        continue
    for entry in entries:
        if entry and entry.get("HostPort") == expected_port:
            raise SystemExit(0)

raise SystemExit(1)
PY

    return $?
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
    bt_preload_compose_env "${compose_file}"

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

bt_env_file_value() {
    local env_file="$1"
    local var_name="$2"

    [[ -r "${env_file}" ]] || return 1

    python3 - "${env_file}" "${var_name}" <<'PY'
import sys

env_file, wanted = sys.argv[1:]

with open(env_file, "r", encoding="utf-8") as handle:
    for raw_line in handle:
        line = raw_line.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("export "):
            line = line[7:]
        if "=" not in line:
            continue
        key, value = line.split("=", 1)
        if key.strip() != wanted:
            continue
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"\"", "'"}:
            value = value[1:-1]
        print(value)
        raise SystemExit(0)

raise SystemExit(1)
PY
}

bt_upsert_env_file_value() {
    local env_file="$1"
    local var_name="$2"
    local value="$3"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN set ${var_name} in ${env_file}"
        return 0
    fi

    mkdir -p "$(dirname "${env_file}")"

    python3 - "${env_file}" "${var_name}" "${value}" <<'PY'
from pathlib import Path
import sys

env_path = Path(sys.argv[1])
target = sys.argv[2]
value = sys.argv[3]

lines = []
replaced = False
if env_path.exists():
    lines = env_path.read_text(encoding="utf-8").splitlines()

new_lines = []
for line in lines:
    stripped = line.strip()
    payload = stripped[7:] if stripped.startswith("export ") else stripped
    if payload and "=" in payload and payload.split("=", 1)[0].strip() == target:
        new_lines.append(f"{target}={value}")
        replaced = True
    else:
        new_lines.append(line)

if not replaced:
    new_lines.append(f"{target}={value}")

env_path.write_text("\n".join(new_lines) + "\n", encoding="utf-8")
PY
}

bt_export_env_file() {
    local env_file="$1"
    local line
    local key
    local value

    [[ -r "${env_file}" ]] || return 0

    while IFS= read -r line; do
        [[ -n "${line}" ]] || continue
        [[ "${line}" =~ ^[[:space:]]*# ]] && continue
        line="${line#export }"
        [[ "${line}" == *=* ]] || continue
        key="${line%%=*}"
        value="${line#*=}"
        key="${key#"${key%%[![:space:]]*}"}"
        key="${key%"${key##*[![:space:]]}"}"
        export "${key}=${value}"
    done < "${env_file}"
}

bt_export_env_file_if_unset() {
    local env_file="$1"
    local line
    local key
    local value

    [[ -r "${env_file}" ]] || return 0

    while IFS= read -r line; do
        [[ -n "${line}" ]] || continue
        [[ "${line}" =~ ^[[:space:]]*# ]] && continue
        line="${line#export }"
        [[ "${line}" == *=* ]] || continue
        key="${line%%=*}"
        value="${line#*=}"
        key="${key#"${key%%[![:space:]]*}"}"
        key="${key%"${key##*[![:space:]]}"}"

        if [[ -n "${!key+x}" ]]; then
            continue
        fi

        export "${key}=${value}"
    done < "${env_file}"
}

bt_export_shell_assignment_file_unless_preserved() {
    local env_file="$1"
    local preserved_env_keys="$2"
    local parsed_file=""
    local key=""
    local value=""

    [[ -r "${env_file}" ]] || return 0

    parsed_file="$(mktemp)"
    python3 - "${env_file}" > "${parsed_file}" <<'PY'
import pathlib
import re
import shlex
import sys

path = pathlib.Path(sys.argv[1])
key_pattern = re.compile(r"^[A-Z_][A-Z0-9_]*$")

for raw_line in path.read_text(encoding="utf-8").splitlines():
    line = raw_line.strip()
    if not line or line.startswith("#"):
        continue
    try:
        lexer = shlex.shlex(line, posix=True)
        lexer.whitespace_split = True
        lexer.commenters = ""
        parts = list(lexer)
    except ValueError as exc:
        raise SystemExit(f"Invalid shell assignment in {path}: {raw_line} ({exc})")

    if parts and parts[0] == "export":
        parts = parts[1:]

    if len(parts) > 1 and parts[1].startswith("#"):
        parts = parts[:1]

    if len(parts) != 1 or "=" not in parts[0]:
        raise SystemExit(f"Invalid shell assignment in {path}: {raw_line}")

    key, value = parts[0].split("=", 1)
    if not key_pattern.match(key):
        raise SystemExit(f"Invalid key in {path}: {key}")

    sys.stdout.write(key)
    sys.stdout.write("\0")
    sys.stdout.write(value)
    sys.stdout.write("\0")
PY

    while IFS= read -r -d '' key && IFS= read -r -d '' value; do
        if bt_env_snapshot_has_key "${preserved_env_keys}" "${key}"; then
            continue
        fi

        export "${key}=${value}"
    done < "${parsed_file}"

    rm -f "${parsed_file}"
}

bt_env_snapshot_has_key() {
    local snapshot="$1"
    local key="$2"

    grep -Fqx -- "${key}" <<< "${snapshot}"
}

bt_runtime_bridge_preserved_keys_snapshot() {
    local defaults=("BT_APP_PLANE_NETWORK_NAME" "BT_HONEYPOT_SOURCE")
    local extra_raw="${BT_RUNTIME_BRIDGE_PRESERVE_KEYS:-}"
    local key=""
    local snapshot=""

    for key in "${defaults[@]}"; do
        if [[ -n "${!key+x}" ]]; then
            snapshot+="${key}"$'\n'
        fi
    done

    if [[ -n "${extra_raw}" ]]; then
        extra_raw="${extra_raw//,/ }"
        for key in ${extra_raw}; do
            [[ "${key}" =~ ^[A-Z_][A-Z0-9_]*$ ]] || continue
            if [[ -n "${!key+x}" ]]; then
                snapshot+="${key}"$'\n'
            fi
        done
    fi

    printf '%s' "${snapshot}"
}

bt_export_env_file_unless_preserved() {
    local env_file="$1"
    local preserved_env_keys="$2"
    local line
    local key
    local value

    [[ -r "${env_file}" ]] || return 0

    while IFS= read -r line; do
        [[ -n "${line}" ]] || continue
        [[ "${line}" =~ ^[[:space:]]*# ]] && continue
        line="${line#export }"
        [[ "${line}" == *=* ]] || continue
        key="${line%%=*}"
        value="${line#*=}"
        key="${key#"${key%%[![:space:]]*}"}"
        key="${key%"${key##*[![:space:]]}"}"

        if bt_env_snapshot_has_key "${preserved_env_keys}" "${key}"; then
            continue
        fi

        export "${key}=${value}"
    done < "${env_file}"
}

bt_generate_secret() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
        return 0
    fi

    python3 - <<'PY'
import secrets
print(secrets.token_hex(32))
PY
}

bt_normalize_compose_dollars() {
    local value="$1"
    printf '%s' "${value//\$\$/\$}"
}

bt_escape_compose_dollars() {
    local value="$1"
    printf '%s' "${value//\$/\$\$}"
}

bt_is_valid_bcrypt_hash() {
    [[ "${1:-}" =~ ^\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}$ ]]
}

bt_bcrypt_hash_runtime_usable() {
    local hash="${1:-}"
    bt_is_valid_bcrypt_hash "${hash}" || return 1

    if python3 -c "import bcrypt" >/dev/null 2>&1; then
        python3 - "${hash}" <<'PY'
import bcrypt
import sys

try:
    bcrypt.checkpw(b"bootstrap-probe", sys.argv[1].encode("utf-8"))
except ValueError:
    raise SystemExit(1)
raise SystemExit(0)
PY
        return $?
    fi

    if command -v php >/dev/null 2>&1; then
        # shellcheck disable=SC2016
        php -r '
$hash = $argv[1];
$info = password_get_info($hash);
$algoName = $info["algoName"] ?? "";
exit($algoName === "bcrypt" ? 0 : 1);
' "${hash}"
        return $?
    fi

    if command -v htpasswd >/dev/null 2>&1; then
        local tmp rc
        tmp="$(mktemp)"
        printf '%s:%s\n' "admin" "${hash}" > "${tmp}"
        set +e
        htpasswd -vb "${tmp}" admin bootstrap-probe >/dev/null 2>&1
        rc=$?
        set -e
        rm -f "${tmp}"
        [[ "${rc}" -eq 0 || "${rc}" -eq 3 ]]
        return $?
    fi

    if command -v docker >/dev/null 2>&1; then
        local tmp rc
        tmp="$(mktemp)"
        printf '%s:%s\n' "admin" "${hash}" > "${tmp}"
        set +e
        docker run --rm -v "${tmp}:/tmp/htpasswd:ro" httpd:alpine \
            htpasswd -vb /tmp/htpasswd admin bootstrap-probe >/dev/null 2>&1
        rc=$?
        set -e
        rm -f "${tmp}"
        [[ "${rc}" -eq 0 || "${rc}" -eq 3 ]]
        return $?
    fi

    return 1
}

bt_bcrypt_hash_matches() {
    local hash="${1:-}"
    local password="${2:-}"
    bt_is_valid_bcrypt_hash "${hash}" || return 1

    if python3 -c "import bcrypt" >/dev/null 2>&1; then
        python3 - "${hash}" "${password}" <<'PY'
import bcrypt
import sys

raise SystemExit(0 if bcrypt.checkpw(sys.argv[2].encode("utf-8"), sys.argv[1].encode("utf-8")) else 1)
PY
        return $?
    fi

    if command -v php >/dev/null 2>&1; then
        php -r '
$hash = $argv[1];
$password = $argv[2];
exit(password_verify($password, $hash) ? 0 : 1);
' "${hash}" "${password}"
        return $?
    fi

    if command -v htpasswd >/dev/null 2>&1; then
        local tmp rc
        tmp="$(mktemp)"
        printf '%s:%s\n' "admin" "${hash}" > "${tmp}"
        set +e
        htpasswd -vb "${tmp}" admin "${password}" >/dev/null 2>&1
        rc=$?
        set -e
        rm -f "${tmp}"
        [[ "${rc}" -eq 0 ]]
        return $?
    fi

    if command -v docker >/dev/null 2>&1; then
        local tmp rc
        tmp="$(mktemp)"
        printf '%s:%s\n' "admin" "${hash}" > "${tmp}"
        set +e
        docker run --rm -v "${tmp}:/tmp/htpasswd:ro" httpd:alpine \
            htpasswd -vb /tmp/htpasswd admin "${password}" >/dev/null 2>&1
        rc=$?
        set -e
        rm -f "${tmp}"
        [[ "${rc}" -eq 0 ]]
        return $?
    fi

    return 1
}

bt_bcrypt_hash() {
    local user_name="$1"
    local password="$2"

    if command -v htpasswd >/dev/null 2>&1; then
        htpasswd -nbB "${user_name}" "${password}" | cut -d: -f2-
        return 0
    fi

    if python3 -c "import bcrypt" >/dev/null 2>&1; then
        python3 - "${password}" <<'PY'
import bcrypt
import sys

password = sys.argv[1].encode("utf-8")
print(bcrypt.hashpw(password, bcrypt.gensalt(rounds=12)).decode("utf-8"))
PY
        return 0
    fi

    if command -v docker >/dev/null 2>&1; then
        docker run --rm --entrypoint htpasswd httpd:2.4-alpine -nbB "${user_name}" "${password}" 2>/dev/null | cut -d: -f2-
        return 0
    fi

    return 1
}

bt_append_json_record() {
    local target_file="$1"
    local json_payload="$2"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN append JSON record to ${target_file}"
        return 0
    fi

    mkdir -p "$(dirname "${target_file}")"
    printf '%s\n' "${json_payload}" >> "${target_file}"
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
