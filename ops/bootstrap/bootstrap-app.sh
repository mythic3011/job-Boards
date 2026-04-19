#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
APP_WAIT_TIMEOUT_SECONDS="${BT_APP_WAIT_TIMEOUT_SECONDS:-90}"
APP_HEALTH_PROBE_TIMEOUT_SECONDS="${BT_APP_HEALTH_PROBE_TIMEOUT_SECONDS:-420}"
ROOT_ENV_FILE="${BT_ROOT_ENV_FILE:-${SCRIPT_DIR}/../../.env}"
app_statuses=()

load_app_runtime_env() {
    bt_export_env_file_if_unset "${ROOT_ENV_FILE}"
}

app_frontdoor_binding() {
    printf '%s\n' "${APP_SSL_PORT:-443}"
}

app_health_binding() {
    printf '%s\n' "${APP_PORT:-80}"
}

binding_host() {
    local binding="$1"

    if [[ "${binding}" == *:* ]]; then
        local host_part="${binding%:*}"
        case "${host_part}" in
            0.0.0.0|::|'')
                printf '%s\n' "127.0.0.1"
                return 0
                ;;
            *)
                printf '%s\n' "${host_part}"
                return 0
                ;;
        esac
    fi

    printf '%s\n' "127.0.0.1"
}

binding_port() {
    local binding="$1"
    printf '%s\n' "${binding##*:}"
}

app_frontdoor_host() {
    local binding
    binding="$(app_frontdoor_binding)"
    binding_host "${binding}"
}

app_frontdoor_port() {
    local binding
    binding="$(app_frontdoor_binding)"
    binding_port "${binding}"
}

app_health_host() {
    local binding
    binding="$(app_health_binding)"
    binding_host "${binding}"
}

app_health_port() {
    local binding
    binding="$(app_health_binding)"
    binding_port "${binding}"
}

app_frontdoor_https_url() {
    printf 'https://%s:%s/up\n' "$(app_frontdoor_host)" "$(app_frontdoor_port)"
}

app_frontdoor_server_name() {
    local app_url="${APP_URL:-}"
    app_url="${app_url#*://}"
    app_url="${app_url%%/*}"
    app_url="${app_url%%:*}"

    if [[ -n "${app_url}" ]]; then
        printf '%s\n' "${app_url}"
        return 0
    fi

    printf '%s\n' "$(app_frontdoor_host)"
}

app_frontdoor_published_ports() {
    local binding
    local port

    for binding in "${APP_PORT:-80}" "${APP_SSL_PORT:-443}"; do
        port="${binding##*:}"
        [[ -n "${port}" ]] && printf '%s\n' "${port}"
    done
}

run_app_check() {
    bt_run_plane_check "app" app_statuses "$@"
}

emit_app_summary() {
    bt_emit_plane_check_summary "app" app_statuses "$1"
}

require_baseline_marker() {
    bt_marker_valid || bt_die "Host baseline marker is missing or invalid."
}

verify_local_exposure() {
    local allowed=("22" "80" "443")
    local port
    local listeners=()
    local listener

    if command -v ss >/dev/null 2>&1; then
        while IFS= read -r listener; do
            [[ -n "${listener}" ]] && listeners+=("${listener}")
        done < <(ss -tlnH | awk '{print $4}')
    elif command -v netstat >/dev/null 2>&1; then
        while IFS= read -r listener; do
            [[ -n "${listener}" ]] && listeners+=("${listener}")
        done < <(netstat -tln | awk 'NR>2 {print $4}')
    else
        return 1
    fi

    local ports=()
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

host_port_exposure_evidence_available() {
    [[ "${BT_SKIP_HOST_LOCAL_PORTS_CHECK:-false}" == "true" ]] && return 1
    [[ "$(uname -s)" == "Linux" ]]
}

verify_frontdoor_ports_available_for_app() {
    if bt_compose_service_running "${BT_COMPOSE_APP_FILE}" nginx; then
        return 0
    fi

    local port
    local listeners
    while IFS= read -r port; do
        [[ -n "${port}" ]] || continue
        listeners="$(ss -ltnpH "( sport = :${port} )" 2>/dev/null || true)"
        [[ -z "${listeners}" ]] && continue
        if printf '%s\n' "${listeners}" | grep -qv 'docker-proxy'; then
            return 1
        fi
    done < <(app_frontdoor_published_ports)

    return 0
}

verify_https_frontdoor() {
    local origin_host
    local port
    local server_name

    origin_host="$(app_frontdoor_host)"
    port="$(app_frontdoor_port)"
    server_name="$(app_frontdoor_server_name)"

    curl -kfsS --resolve "${server_name}:${port}:${origin_host}" --max-time "${BT_CROWDSEC_TIMEOUT_SECONDS}" "https://${server_name}:${port}/up" >/dev/null
}

verify_app_health_frontdoor() {
    local code
    local deadline
    local origin_host
    local port
    local url

    origin_host="$(app_health_host)"
    port="$(app_health_port)"
    url="http://${origin_host}:${port}/up"
    deadline=$((SECONDS + APP_HEALTH_PROBE_TIMEOUT_SECONDS))

    while (( SECONDS < deadline )); do
        code="$(curl -fsS -o /dev/null -w '%{http_code}' --max-time "${BT_CROWDSEC_TIMEOUT_SECONDS}" "${url}" || true)"
        if [[ "${code}" == "200" ]]; then
            return 0
        fi
        sleep 1
    done

    return 1
}

crowdsec_frontdoor_mode() {
    local origin_host
    local port
    local server_name

    origin_host="$(app_frontdoor_host)"
    port="$(app_frontdoor_port)"
    server_name="$(app_frontdoor_server_name)"

    curl -kfsSI --resolve "${server_name}:${port}:${origin_host}" --max-time "${BT_CROWDSEC_TIMEOUT_SECONDS}" "https://${server_name}:${port}/up" \
        | grep -i '^x-crowdsec-mode:' \
        | head -n 1 \
        | sed -E 's/^[^:]+:[[:space:]]*//; s/\r$//' \
        | tr '[:upper:]' '[:lower:]'
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

    load_app_runtime_env
    require_baseline_marker

    run_app_check "app.frontdoor.host_port_conflicts" "${BT_STATUS_PASS}" "App front-door ports 80/443 are available for the app plane." "Stop or disable conflicting host listeners on 80/443 before starting app-plane nginx." verify_frontdoor_ports_available_for_app || {
        emit_app_summary "App bootstrap halted before compose apply."
        return 1
    }

    if "${SCRIPT_DIR}/../app/05-compose-up.sh"; then
        bt_emit_check "app.compose.apply" "app" "${BT_STATUS_PASS}" "App compose apply completed." "No action required."
        app_statuses+=("${BT_STATUS_PASS}")
    else
        bt_emit_check "app.compose.apply" "app" "${BT_STATUS_DEGRADED}" "App compose apply completed with degraded optional services; continuing to verify front-door readiness." "Inspect optional service failures and confirm the front door remains available."
        app_statuses+=("${BT_STATUS_DEGRADED}")
    fi

    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" nginx healthy "${APP_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" laravel.test running "${APP_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" postgres healthy "${APP_WAIT_TIMEOUT_SECONDS}" || true
    bt_wait_for_container_state "${BT_COMPOSE_APP_FILE}" redis healthy "${APP_WAIT_TIMEOUT_SECONDS}" || true

    verify_action
}

verify_action() {
    load_app_runtime_env
    run_app_check "app.nginx.running" "${BT_STATUS_PASS}" "App-plane nginx is running." "Inspect compose app nginx service state." bt_compose_service_running "${BT_COMPOSE_APP_FILE}" nginx || true
    run_app_check "app.laravel.running" "${BT_STATUS_PASS}" "Laravel app service is running." "Inspect compose app laravel.test service state." bt_compose_service_running "${BT_COMPOSE_APP_FILE}" laravel.test || true
    run_app_check "app.postgres.healthy" "${BT_STATUS_PASS}" "Postgres is healthy." "Inspect compose app postgres service health." bt_compose_service_healthy "${BT_COMPOSE_APP_FILE}" postgres || true
    run_app_check "app.redis.healthy" "${BT_STATUS_PASS}" "Redis is healthy." "Inspect compose app redis service health." bt_compose_service_healthy "${BT_COMPOSE_APP_FILE}" redis || true
    run_app_check "app.frontdoor.https_response" "${BT_STATUS_PASS}" "HTTPS front door returns a successful application response." "Inspect nginx TLS/front-door configuration and upstream routing." verify_https_frontdoor || true
    run_app_check "app.frontdoor.health_response" "${BT_STATUS_PASS}" "App health is reachable through the front door." "Inspect front-door health endpoint routing." verify_app_health_frontdoor || true
    if host_port_exposure_evidence_available; then
        run_app_check "app.host.local_ports" "${BT_STATUS_PASS}" "Local host exposure is limited to 22/80/443." "Inspect local listeners and published ports." verify_local_exposure || true
    else
        bt_emit_check "app.host.local_ports" "app" "${BT_STATUS_SKIPPED}" "Linux VM host exposure evidence is unavailable in this runtime." "Run app verification inside the target Linux VM to prove host port exposure constraints."
        app_statuses+=("${BT_STATUS_SKIPPED}")
    fi
    run_app_check "app.honeypot.integration" "${BT_STATUS_PASS}" "Serving-path honeypot integration is active." "Inspect nginx include mounts and decoy routing." verify_honeypot_integration || true

    local crowdsec_mode
    crowdsec_mode="$(crowdsec_frontdoor_mode || true)"
    case "${crowdsec_mode}" in
        active)
            bt_emit_check "app.crowdsec.bouncer_mode" "app" "${BT_STATUS_PASS}" "CrowdSec bouncer is active in the front door request path." "No action required."
            app_statuses+=("${BT_STATUS_PASS}")
            ;;
        degraded)
            bt_emit_check "app.crowdsec.bouncer_mode" "app" "${BT_STATUS_DEGRADED}" "CrowdSec bouncer is running in degraded pass-through mode." "Inspect CrowdSec bouncer config, LAPI reachability, and request-path fallback state."
            app_statuses+=("${BT_STATUS_DEGRADED}")
            ;;
        *)
            bt_emit_check "app.crowdsec.bouncer_mode" "app" "${BT_STATUS_FAIL}" "CrowdSec bouncer mode is unavailable from the front door response." "Inspect nginx CrowdSec integration and front-door response headers."
            app_statuses+=("${BT_STATUS_FAIL}")
            ;;
    esac

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
    load_app_runtime_env
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
