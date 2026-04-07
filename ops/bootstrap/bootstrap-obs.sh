#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
OBS_WAIT_TIMEOUT_SECONDS="${BT_OBS_WAIT_TIMEOUT_SECONDS:-90}"
obs_statuses=()

run_obs_check() {
    local check_id="$1"
    local message="$2"
    local remediation="$3"
    shift 3

    if "$@"; then
        bt_emit_check "${check_id}" "obs" "${BT_STATUS_PASS}" "${message}" "${remediation}"
        obs_statuses+=("${BT_STATUS_PASS}")
        return 0
    fi

    bt_emit_check "${check_id}" "obs" "${BT_STATUS_FAIL}" "${message}" "${remediation}"
    obs_statuses+=("${BT_STATUS_FAIL}")
    return 1
}

emit_obs_summary() {
    local summary_message="$1"
    local status
    if [[ "${#obs_statuses[@]}" -eq 0 ]]; then
        status="${BT_STATUS_SKIPPED}"
    else
        status="$(bt_aggregate_statuses "${obs_statuses[@]}")"
    fi
    bt_emit_plane_summary "obs" "${status}" "${summary_message}" "Inspect obs-plane checks."
    [[ "${status}" != "${BT_STATUS_FAIL}" ]]
}

verify_obs_logs() {
    bt_compose "${BT_COMPOSE_OBS_FILE}" exec -T promtail sh -c 'test -r /var/log/nginx/access.log || test -r /var/log/nginx/error.log'
}

verify_obs_no_ports() {
    python3 - "${BT_COMPOSE_OBS_FILE}" <<'PY'
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
for service in config.get("services", {}).values():
    if service.get("ports"):
        raise SystemExit(1)
raise SystemExit(0)
PY
}

verify_obs_required_env() {
    local required=(
        "MONITORING_ADMIN_USERNAME"
        "MONITORING_PASSWORD_HASH"
        "SESSION_SECRET"
        "GRAFANA_PASSWORD"
        "PROMETHEUS_PASSWORD_HASH"
    )
    local var_name
    local missing=()

    for var_name in "${required[@]}"; do
        if [[ -z "$(bt_env_value "${var_name}" || true)" ]]; then
            missing+=("${var_name}")
        fi
    done

    [[ "${#missing[@]}" -eq 0 ]]
}

apply_action() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_emit_check "obs.bootstrap.dry_run" "obs" "${BT_STATUS_SKIPPED}" "Dry-run does not mutate obs-plane services." "Run without --dry-run to apply obs-plane changes."
        obs_statuses+=("${BT_STATUS_SKIPPED}")
        emit_obs_summary "Obs bootstrap dry-run completed."
        return 0
    fi

    run_obs_check "obs.bootstrap.required_env" "Obs bring-up secrets and required credentials are present." "Populate required obs-plane variables in .env before apply." verify_obs_required_env || {
        emit_obs_summary "Obs bootstrap failed preflight."
        exit 1
    }

    bt_compose "${BT_COMPOSE_OBS_FILE}" up -d
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" grafana running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" loki running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" promtail running "${OBS_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_OBS_FILE}" prometheus running "${OBS_WAIT_TIMEOUT_SECONDS}" || true

    verify_action
}

verify_action() {
    run_obs_check "obs.grafana.healthy" "Grafana is healthy." "Inspect compose obs grafana service health." bt_compose_service_healthy "${BT_COMPOSE_OBS_FILE}" grafana || true
    run_obs_check "obs.loki.running" "Loki is running." "Inspect compose obs loki service state." bt_compose_service_running "${BT_COMPOSE_OBS_FILE}" loki || true
    run_obs_check "obs.promtail.running" "Promtail is running." "Inspect compose obs promtail service state." bt_compose_service_running "${BT_COMPOSE_OBS_FILE}" promtail || true
    run_obs_check "obs.prometheus.healthy" "Prometheus is healthy." "Inspect compose obs prometheus service health." bt_compose_service_healthy "${BT_COMPOSE_OBS_FILE}" prometheus || true
    run_obs_check "obs.auth_service.healthy" "Auth-service is healthy." "Inspect compose obs auth-service health." bt_compose_service_healthy "${BT_COMPOSE_OBS_FILE}" auth-service || true
    run_obs_check "obs.logs.read_only_mount" "Logs are readable through declared read-only shared surfaces." "Inspect promtail read-only mounts and shared artifact permissions." verify_obs_logs || true
    run_obs_check "obs.ports.none_published" "Obs-plane services do not publish host ports." "Remove host port publishing from obs compose." verify_obs_no_ports || true

    emit_obs_summary "Obs verification completed."
}

rollback_action() {
    bt_emit_plane_summary "obs" "${BT_STATUS_FAIL}" "Obs rollback is not implemented." "Obs rollback must remain explicit and separate from host rollback."
    exit 1
}

case "${ACTION}" in
    apply) apply_action ;;
    verify) verify_action ;;
    rollback) rollback_action ;;
    *)
        bt_die "Unsupported bootstrap-obs action: ${ACTION}"
        ;;
esac
