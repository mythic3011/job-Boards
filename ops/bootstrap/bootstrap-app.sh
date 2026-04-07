#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
APP_WAIT_TIMEOUT_SECONDS="${BT_APP_WAIT_TIMEOUT_SECONDS:-90}"
app_statuses=()

run_app_check() {
    local check_id="$1"
    local status_on_success="$2"
    local message="$3"
    local remediation="$4"
    shift 4

    if "$@"; then
        bt_emit_check "${check_id}" "app" "${status_on_success}" "${message}" "${remediation}"
        app_statuses+=("${status_on_success}")
        return 0
    fi

    local failure_status="${BT_STATUS_FAIL}"
    if [[ "${status_on_success}" == "${BT_STATUS_DEGRADED}" ]]; then
        failure_status="${BT_STATUS_DEGRADED}"
    fi
    bt_emit_check "${check_id}" "app" "${failure_status}" "${message}" "${remediation}"
    app_statuses+=("${failure_status}")
    return 1
}

emit_app_summary() {
    local summary_message="$1"
    local status
    if [[ "${#app_statuses[@]}" -eq 0 ]]; then
        status="${BT_STATUS_SKIPPED}"
    else
        status="$(bt_aggregate_statuses "${app_statuses[@]}")"
    fi
    bt_emit_plane_summary "app" "${status}" "${summary_message}" "Inspect app-plane checks."
    [[ "${status}" != "${BT_STATUS_FAIL}" ]]
}

require_baseline_marker() {
    bt_marker_valid || bt_die "Host baseline marker is missing or invalid."
}

verify_local_exposure() {
    local allowed=("22" "80" "443")
    local port
    local listeners=()

    if command -v ss >/dev/null 2>&1; then
        mapfile -t listeners < <(ss -tlnH | awk '{print $4}')
    elif command -v netstat >/dev/null 2>&1; then
        mapfile -t listeners < <(netstat -tln | awk 'NR>2 {print $4}')
    else
        return 1
    fi

    local ports=()
    local listener
    for listener in "${listeners[@]}"; do
        if [[ "${listener}" =~ ^127\. ]] || [[ "${listener}" =~ ^\[::1\]: ]] || [[ "${listener}" =~ ^localhost: ]]; then
            continue
        fi
        ports+=("${listener##*:}")
    done

    for port in "${ports[@]}"; do
        [[ " ${allowed[*]} " == *" ${port} "* ]] || return 1
    done
    return 0
}

verify_frontdoor_ports_available_for_app() {
    if bt_compose_service_running "${BT_COMPOSE_APP_FILE}" nginx; then
        return 0
    fi

    local port
    local listeners
    for port in 80 443; do
        listeners="$(ss -ltnpH "( sport = :${port} )" 2>/dev/null || true)"
        [[ -z "${listeners}" ]] && continue
        if printf '%s\n' "${listeners}" | grep -qv 'docker-proxy'; then
            return 1
        fi
    done

    return 0
}

verify_https_frontdoor() {
    curl -kfsS --max-time "${BT_CROWDSEC_TIMEOUT_SECONDS}" https://127.0.0.1/up >/dev/null
}

verify_app_health_frontdoor() {
    local code
    code="$(curl -kfsS -o /dev/null -w '%{http_code}' --max-time "${BT_CROWDSEC_TIMEOUT_SECONDS}" https://127.0.0.1/up || true)"
    [[ "${code}" == "200" ]]
}

verify_honeypot_integration() {
    "${SCRIPT_DIR}/../host/04-honeypot-lite.sh" verify-integration
}

apply_action() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_emit_check "app.bootstrap.dry_run" "app" "${BT_STATUS_SKIPPED}" "Dry-run does not mutate app-plane services." "Run without --dry-run to apply app-plane changes."
        app_statuses+=("${BT_STATUS_SKIPPED}")
        emit_app_summary "App bootstrap dry-run completed."
        return 0
    fi

    require_baseline_marker

    run_app_check "app.frontdoor.host_port_conflicts" "${BT_STATUS_PASS}" "App front-door ports 80/443 are available for the app plane." "Stop or disable conflicting host listeners on 80/443 before starting app-plane nginx." verify_frontdoor_ports_available_for_app || {
        emit_app_summary "App bootstrap halted before compose apply."
        return 1
    }

    "${SCRIPT_DIR}/../app/05-compose-up.sh"

    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" nginx healthy "${APP_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" laravel.test running "${APP_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" postgres healthy "${APP_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" redis healthy "${APP_WAIT_TIMEOUT_SECONDS}" || true

    verify_action
}

verify_action() {
    run_app_check "app.nginx.running" "${BT_STATUS_PASS}" "App-plane nginx is running." "Inspect compose app nginx service state." bt_compose_service_running "${BT_COMPOSE_APP_FILE}" nginx || true
    run_app_check "app.laravel.running" "${BT_STATUS_PASS}" "Laravel app service is running." "Inspect compose app laravel.test service state." bt_compose_service_running "${BT_COMPOSE_APP_FILE}" laravel.test || true
    run_app_check "app.postgres.healthy" "${BT_STATUS_PASS}" "Postgres is healthy." "Inspect compose app postgres service health." bt_compose_service_healthy "${BT_COMPOSE_APP_FILE}" postgres || true
    run_app_check "app.redis.healthy" "${BT_STATUS_PASS}" "Redis is healthy." "Inspect compose app redis service health." bt_compose_service_healthy "${BT_COMPOSE_APP_FILE}" redis || true
    run_app_check "app.frontdoor.https_response" "${BT_STATUS_PASS}" "HTTPS front door returns a successful application response." "Inspect nginx TLS/front-door configuration and upstream routing." verify_https_frontdoor || true
    run_app_check "app.frontdoor.health_response" "${BT_STATUS_PASS}" "App health is reachable through the front door." "Inspect front-door health endpoint routing." verify_app_health_frontdoor || true
    run_app_check "app.host.local_ports" "${BT_STATUS_PASS}" "Local host exposure is limited to 22/80/443." "Inspect local listeners and published ports." verify_local_exposure || true
    run_app_check "app.honeypot.integration" "${BT_STATUS_PASS}" "Serving-path honeypot integration is active." "Inspect nginx include mounts and decoy routing." verify_honeypot_integration || true

    if bt_compose_service_healthy "${BT_COMPOSE_APP_FILE}" crowdsec; then
        bt_emit_check "app.crowdsec.health" "app" "${BT_STATUS_PASS}" "CrowdSec is healthy." "No action required."
        app_statuses+=("${BT_STATUS_PASS}")
    else
        bt_emit_check "app.crowdsec.health" "app" "${BT_STATUS_DEGRADED}" "CrowdSec is unhealthy but front-door fail-open semantics still apply." "Inspect CrowdSec and bouncer health without blocking app admission."
        app_statuses+=("${BT_STATUS_DEGRADED}")
    fi

    emit_app_summary "App verification completed."
}

rollback_action() {
    bt_emit_plane_summary "app" "${BT_STATUS_FAIL}" "App rollback is not implemented." "Host rollback must not implicitly tear down app compose stacks."
    exit 1
}

case "${ACTION}" in
    apply) apply_action ;;
    verify) verify_action ;;
    rollback) rollback_action ;;
    *)
        bt_die "Unsupported bootstrap-app action: ${ACTION}"
        ;;
esac
