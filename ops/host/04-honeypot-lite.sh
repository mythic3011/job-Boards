#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-manage-source}"
HONEYPOT_BACKUP_FILE="${BT_BACKUP_DIR}/honeypot/blue-team-honeypot.conf.bak"

usage() {
    cat <<EOF
Usage: $0 [manage-source|verify-integration|rollback]
EOF
}

honeypot_content() {
    cat <<EOF
location = /.env {
    set \$blue_team_trap_name env_probe;
    access_log /var/log/nginx/fp-trap.log blue_team_honeypot;
    return 403;
}

location = /.git/config {
    set \$blue_team_trap_name git_probe;
    access_log /var/log/nginx/fp-trap.log blue_team_honeypot;
    return 403;
}

location = /phpmyadmin {
    set \$blue_team_trap_name phpmyadmin_probe;
    access_log /var/log/nginx/fp-trap.log blue_team_honeypot;
    return 403;
}

location = /wp-login.php {
    set \$blue_team_trap_name wp_probe;
    access_log /var/log/nginx/fp-trap.log blue_team_honeypot;
    return 403;
}

location = /admin-old {
    set \$blue_team_trap_name admin_old_probe;
    access_log /var/log/nginx/fp-trap.log blue_team_honeypot;
    return 403;
}
EOF
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
    local status_code
    status_code="$(curl -k -sS -o /dev/null -w '%{http_code}' --max-time "${BT_CROWDSEC_TIMEOUT_SECONDS}" https://127.0.0.1/.env || true)"
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
