#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./assert.sh
source "${SCRIPT_DIR}/assert.sh"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

RUNNER="${RUNNER:-./setup-blue-team-vm.sh}"
APP_COMPOSE_FILE="${APP_COMPOSE_FILE:-compose.app.yml}"
CROWDSEC_SERVICE="${CROWDSEC_SERVICE:-crowdsec}"
FRONTDOOR_URL="${FRONTDOOR_URL:-https://127.0.0.1/up}"
RESTORE_TIMEOUT_SECONDS="${RESTORE_TIMEOUT_SECONDS:-60}"
restored=0

BT_COMPOSE_APP_FILE="${APP_COMPOSE_FILE}"

require_cmd curl docker python3

compose_service_container_id() {
    bt_compose_service_container_id "${APP_COMPOSE_FILE}" "$1"
}

compose_service_runtime_status() {
    local container_id
    container_id="$(compose_service_container_id "$1")"
    [[ -n "${container_id}" ]] || {
        printf 'missing\n'
        return 0
    }
    docker inspect -f '{{.State.Status}}' "${container_id}" 2>/dev/null || printf 'missing\n'
}

compose_service_health_status() {
    local container_id
    container_id="$(compose_service_container_id "$1")"
    [[ -n "${container_id}" ]] || {
        printf 'missing\n'
        return 0
    }
    docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "${container_id}" 2>/dev/null || printf 'missing\n'
}

wait_for_frontdoor_200() {
    local deadline=$((SECONDS + RESTORE_TIMEOUT_SECONDS))
    local code

    while (( SECONDS < deadline )); do
        code="$(curl -k -sS -o /dev/null -w '%{http_code}' "${FRONTDOOR_URL}" || true)"
        if [[ "${code}" == "200" ]]; then
            return 0
        fi
        sleep 2
    done

    return 1
}

restore_crowdsec_state() {
    if [[ "${initial_crowdsec_runtime}" == "running" || "${initial_crowdsec_runtime}" == "restarting" ]]; then
        smoke_note "Restoring CrowdSec service"
        docker compose -f "${APP_COMPOSE_FILE}" start "${CROWDSEC_SERVICE}" >/dev/null
    fi

    if [[ "${initial_crowdsec_runtime}" == "running" || "${initial_crowdsec_runtime}" == "restarting" ]]; then
        bt_wait_for_container_state "${APP_COMPOSE_FILE}" "${CROWDSEC_SERVICE}" running "${RESTORE_TIMEOUT_SECONDS}" \
            || smoke_fail "CrowdSec restoration did not reach running state within ${RESTORE_TIMEOUT_SECONDS}s"
    fi

    if [[ "${initial_crowdsec_health}" == "healthy" ]]; then
        bt_wait_for_container_state "${APP_COMPOSE_FILE}" "${CROWDSEC_SERVICE}" healthy "${RESTORE_TIMEOUT_SECONDS}" \
            || smoke_fail "CrowdSec restoration did not return to healthy state within ${RESTORE_TIMEOUT_SECONDS}s"
    fi

    bt_wait_for_container_state "${APP_COMPOSE_FILE}" nginx healthy "${RESTORE_TIMEOUT_SECONDS}" \
        || smoke_fail "Nginx did not return to healthy state after CrowdSec restoration"
    wait_for_frontdoor_200 \
        || smoke_fail "Front door did not return HTTP 200 after CrowdSec restoration"

    restored=1
}

cleanup() {
    local exit_code=$?
    if [[ "${restored}" != "1" ]]; then
        restore_crowdsec_state
    fi
    rm -f "${tmp_output:-}"
    exit "${exit_code}"
}

smoke_note "Stopping CrowdSec to verify degraded fail-open semantics"
initial_crowdsec_runtime="$(compose_service_runtime_status "${CROWDSEC_SERVICE}")"
initial_crowdsec_health="$(compose_service_health_status "${CROWDSEC_SERVICE}")"
docker compose -f "${APP_COMPOSE_FILE}" stop "${CROWDSEC_SERVICE}" >/dev/null

tmp_output="$(mktemp)"
trap cleanup EXIT

status_code="$(curl -k -sS -o /dev/null -w '%{http_code}' "${FRONTDOOR_URL}" || true)"
assert_eq "200" "${status_code}" "App front door must remain reachable when CrowdSec is down"

"${RUNNER}" verify > "${tmp_output}" || true
assert_jsonl_record_type "${tmp_output}" "app.crowdsec.health" "check"
assert_jsonl_record_type "${tmp_output}" "app.crowdsec.bouncer_mode" "check"
assert_jsonl_status "${tmp_output}" "app.crowdsec.health" "DEGRADED"
assert_jsonl_status "${tmp_output}" "app.crowdsec.bouncer_mode" "DEGRADED"
assert_jsonl_status "${tmp_output}" "app.summary" "DEGRADED"
assert_jsonl_status "${tmp_output}" "app.frontdoor.health_response" "PASS"

restore_crowdsec_state
smoke_pass "CrowdSec degraded contract holds"
