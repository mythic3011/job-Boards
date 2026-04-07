#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./assert.sh
source "${SCRIPT_DIR}/assert.sh"

TARGET_URL="${TARGET_URL:-https://127.0.0.1/.env}"
APP_COMPOSE_FILE="${APP_COMPOSE_FILE:-compose.app.yml}"
NGINX_SERVICE="${NGINX_SERVICE:-nginx}"
LOG_PATH="${LOG_PATH:-/var/log/nginx/fp-trap.log}"

require_cmd curl docker python3

smoke_id="smoke-honeypot-$(date +%s)"
target_url="${TARGET_URL}?smoke_id=${smoke_id}"

status_code="$(curl -k -sS -o /dev/null -w '%{http_code}' "${target_url}" || true)"
assert_eq "403" "${status_code}" "Honeypot decoy path must return 403"

tmp_log="$(mktemp)"
trap 'rm -f "${tmp_log}"' EXIT

docker compose -f "${APP_COMPOSE_FILE}" exec -T "${NGINX_SERVICE}" sh -c "test -f '${LOG_PATH}' && tail -n 50 '${LOG_PATH}'" > "${tmp_log}"
assert_file_contains "${tmp_log}" "trap=web_decoy" "Honeypot log must contain trap marker"
assert_file_contains "${tmp_log}" "trap_name=env_probe" "Honeypot log must contain env_probe trap name"
assert_file_contains "${tmp_log}" "request_path=/.env?smoke_id=${smoke_id}" "Honeypot log must contain the fresh decoy request"
assert_file_contains "${tmp_log}" "request_id=" "Honeypot log must contain request_id field"

smoke_pass "Honeypot 403 contract holds"
