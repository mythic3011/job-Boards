#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}"
CERTBOT_DOMAIN="${BT_CERTBOT_DOMAIN:-}"
CERTBOT_EMAIL="${BT_CERTBOT_EMAIL:-}"
CERTBOT_TIMER_NAME="${BT_CERTBOT_TIMER_NAME:-certbot-renew@${CERTBOT_DOMAIN}.timer}"
CERTBOT_SERVICE_NAME="${BT_CERTBOT_SERVICE_NAME:-certbot-renew@${CERTBOT_DOMAIN}.service}"
SYSTEMD_DIR="/etc/systemd/system"
SERVICE_FILE="${SYSTEMD_DIR}/certbot-renew@.service"
TIMER_FILE="${SYSTEMD_DIR}/certbot-renew@.timer"
BACKUP_DIR="${BT_BACKUP_DIR}/certbot"
SERVICE_BACKUP_FILE="${BACKUP_DIR}/certbot-renew@.service.bak"
TIMER_BACKUP_FILE="${BACKUP_DIR}/certbot-renew@.timer.bak"

usage() {
    cat <<EOF
Usage: $0 [apply|verify|rollback]
EOF
}

requires_certbot_management() {
    case "${TLS_MODE}" in
        letsencrypt-http01|letsencrypt-dns01)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

ensure_certbot_inputs() {
    [[ -n "${CERTBOT_DOMAIN}" ]] || bt_die "BT_CERTBOT_DOMAIN is required for ${TLS_MODE}"
    [[ -n "${CERTBOT_EMAIL}" ]] || bt_die "BT_CERTBOT_EMAIL is required for ${TLS_MODE}"
}

ensure_systemd_available() {
    command -v systemctl >/dev/null 2>&1 || bt_die "systemctl is required but was not found."
}

service_content() {
    cat <<'EOF'
[Unit]
Description=Renew Let's Encrypt certificate for %I
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
EnvironmentFile=-/etc/default/certbot-renew-%i
ExecStart=/usr/bin/certbot renew --quiet --cert-name %I --deploy-hook "systemctl reload nginx"
EOF
}

timer_content() {
    cat <<'EOF'
[Unit]
Description=Daily Let's Encrypt renewal check for %I

[Timer]
OnCalendar=*-*-* 03:15:00
RandomizedDelaySec=1800
Persistent=true

[Install]
WantedBy=timers.target
EOF
}

skip_action() {
    bt_emit_check "host.certbot_renewal.mode" "host" "${BT_STATUS_SKIPPED}" "Certbot renewal is skipped for TLS mode ${TLS_MODE}." "Use letsencrypt-http01 or letsencrypt-dns01 if the host should manage public certificate renewal."
    return 0
}

apply_action() {
    if ! requires_certbot_management; then
        skip_action
        return 0
    fi

    bt_require_root
    ensure_systemd_available
    ensure_certbot_inputs

    bt_backup_file "${SERVICE_FILE}" "${SERVICE_BACKUP_FILE}"
    bt_backup_file "${TIMER_FILE}" "${TIMER_BACKUP_FILE}"
    bt_write_file "${SERVICE_FILE}" "$(service_content)"
    bt_write_file "${TIMER_FILE}" "$(timer_content)"
    bt_run systemctl daemon-reload
    bt_run systemctl enable --now "${CERTBOT_TIMER_NAME}"
}

verify_action() {
    if ! requires_certbot_management; then
        skip_action
        return 0
    fi

    ensure_systemd_available
    ensure_certbot_inputs
    [[ -f "${SERVICE_FILE}" ]] || bt_die "Managed Certbot service is missing: ${SERVICE_FILE}"
    [[ -f "${TIMER_FILE}" ]] || bt_die "Managed Certbot timer is missing: ${TIMER_FILE}"
    systemctl status "${CERTBOT_TIMER_NAME}" >/dev/null
}

rollback_action() {
    bt_require_root
    ensure_systemd_available

    if [[ -n "${CERTBOT_DOMAIN}" ]]; then
        bt_run systemctl disable --now "${CERTBOT_TIMER_NAME}" 2>/dev/null || true
    fi

    if [[ -f "${SERVICE_BACKUP_FILE}" ]]; then
        mkdir -p "${SYSTEMD_DIR}"
        cp -a "${SERVICE_BACKUP_FILE}" "${SERVICE_FILE}"
    else
        rm -f "${SERVICE_FILE}"
    fi

    if [[ -f "${TIMER_BACKUP_FILE}" ]]; then
        mkdir -p "${SYSTEMD_DIR}"
        cp -a "${TIMER_BACKUP_FILE}" "${TIMER_FILE}"
    else
        rm -f "${TIMER_FILE}"
    fi

    bt_run systemctl daemon-reload
}

case "${ACTION}" in
    apply) apply_action ;;
    verify) verify_action ;;
    rollback) rollback_action ;;
    *)
        usage
        exit 1
        ;;
esac
