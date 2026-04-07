#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./assert.sh
source "${SCRIPT_DIR}/assert.sh"

RUNNER="${RUNNER:-./setup-blue-team-vm.sh}"
APP_COMPOSE_FILE="${APP_COMPOSE_FILE:-compose.app.yml}"
CROWDSEC_SERVICE="${CROWDSEC_SERVICE:-crowdsec}"
FRONTDOOR_URL="${FRONTDOOR_URL:-https://127.0.0.1/up}"

require_cmd curl docker python3

smoke_note "Stopping CrowdSec to verify degraded fail-open semantics"
docker compose -f "${APP_COMPOSE_FILE}" stop "${CROWDSEC_SERVICE}" >/dev/null

tmp_output="$(mktemp)"
trap 'docker compose -f "${APP_COMPOSE_FILE}" start "${CROWDSEC_SERVICE}" >/dev/null 2>&1 || true; rm -f "${tmp_output}"' EXIT

status_code="$(curl -k -sS -o /dev/null -w '%{http_code}' "${FRONTDOOR_URL}" || true)"
assert_eq "200" "${status_code}" "App front door must remain reachable when CrowdSec is down"

"${RUNNER}" verify > "${tmp_output}" || true
assert_jsonl_record_type "${tmp_output}" "app.crowdsec.health" "check"
assert_jsonl_status "${tmp_output}" "app.crowdsec.health" "DEGRADED"
assert_jsonl_status "${tmp_output}" "app.summary" "DEGRADED"
assert_jsonl_status "${tmp_output}" "app.frontdoor.health_response" "PASS"

smoke_pass "CrowdSec degraded contract holds"
