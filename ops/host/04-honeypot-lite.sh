#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-manage-source}"
HONEYPOT_BACKUP_FILE="${BT_BACKUP_DIR}/honeypot/blue-team-honeypot.conf.bak"
HONEYPOT_TEMPLATE_FILE="${SCRIPT_DIR}/../../docker/nginx/includes/blue-team-honeypot.conf"

usage() {
    cat <<EOF
Usage: $0 [manage-source|verify-integration|rollback]
EOF
}

honeypot_content() {
    [[ -f "${HONEYPOT_TEMPLATE_FILE}" ]] || bt_die "Honeypot template is missing: ${HONEYPOT_TEMPLATE_FILE}"
    cat "${HONEYPOT_TEMPLATE_FILE}"
}

manage_source() {
    bt_require_root
    bt_backup_file "${BT_HONEYPOT_SOURCE}" "${HONEYPOT_BACKUP_FILE}"
    bt_write_file "${BT_HONEYPOT_SOURCE}" "$(honeypot_content)"
}

verify_integration() {
    [[ -f "${BT_HONEYPOT_SOURCE}" ]] || bt_die "Honeypot source artifact is missing: ${BT_HONEYPOT_SOURCE}"
    command -v docker >/dev/null 2>&1 || bt_die "docker is required for verify-integration."
    docker compose -f "${BT_COMPOSE_APP_FILE}" exec -T nginx nginx -t >/dev/null
    docker compose -f "${BT_COMPOSE_APP_FILE}" exec -T nginx sh -c "[ -f '${BT_HONEYPOT_RUNTIME}' ] && grep -F 'location = /.env' '${BT_HONEYPOT_RUNTIME}' >/dev/null"
    local binding host port
    binding="${APP_SSL_PORT:-443}"
    host="127.0.0.1"
    port="${binding##*:}"
    if [[ "${binding}" == *:* ]]; then
        host="${binding%:*}"
        case "${host}" in
            0.0.0.0|::|'')
                host="127.0.0.1"
                ;;
        esac
    fi
    local status_code
    status_code="$(curl -k -sS -o /dev/null -w '%{http_code}' --max-time "${BT_CROWDSEC_TIMEOUT_SECONDS}" "https://${host}:${port}/.env" || true)"
    [[ "${status_code}" == "403" ]] || bt_die "Expected honeypot decoy path /.env to return 403, got ${status_code:-none}."
}

rollback_action() {
    bt_require_root

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN rollback honeypot source"
        return 0
    fi

    if [[ -f "${HONEYPOT_BACKUP_FILE}" ]]; then
        mkdir -p "$(dirname "${BT_HONEYPOT_SOURCE}")"
        cp -a "${HONEYPOT_BACKUP_FILE}" "${BT_HONEYPOT_SOURCE}"
    else
        rm -f "${BT_HONEYPOT_SOURCE}"
    fi
}

case "${ACTION}" in
    manage-source) manage_source ;;
    verify-integration) verify_integration ;;
    rollback) rollback_action ;;
    *)
        usage
        exit 1
        ;;
esac
