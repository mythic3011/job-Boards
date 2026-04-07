#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
ALLOW_USER="${BT_SSH_ALLOW_USER:-}"
SSH_DROPIN_DIR="/etc/ssh/sshd_config.d"
SSH_MANAGED_FILE="${SSH_DROPIN_DIR}/99-blue-team-managed.conf"
SSH_BACKUP_FILE="${BT_BACKUP_DIR}/ssh/99-blue-team-managed.conf.bak"

usage() {
    cat <<EOF
Usage: $0 [apply|verify|rollback]
EOF
}

validate_ssh_config() {
    if ! command -v sshd >/dev/null 2>&1; then
        bt_die "sshd is required but was not found."
    fi

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN sshd -t"
        return 0
    fi

    sshd -t
}

managed_content() {
    {
        printf 'PubkeyAuthentication yes\n'
        printf 'PasswordAuthentication no\n'
        printf 'PermitRootLogin no\n'
        if [[ -n "${ALLOW_USER}" ]]; then
            printf 'AllowUsers %s\n' "${ALLOW_USER}"
        fi
    }
}

apply_action() {
    bt_require_root
    bt_backup_file "${SSH_MANAGED_FILE}" "${SSH_BACKUP_FILE}"
    bt_mkdir "${SSH_DROPIN_DIR}"
    bt_write_file "${SSH_MANAGED_FILE}" "$(managed_content)"
    validate_ssh_config
    bt_restart_service ssh sshd
}

verify_action() {
    [[ -f "${SSH_MANAGED_FILE}" ]] || bt_die "Managed SSH drop-in is missing: ${SSH_MANAGED_FILE}"
    validate_ssh_config
}

rollback_action() {
    bt_require_root

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN rollback SSH managed file"
        return 0
    fi

    if [[ -f "${SSH_BACKUP_FILE}" ]]; then
        mkdir -p "$(dirname "${SSH_MANAGED_FILE}")"
        cp -a "${SSH_BACKUP_FILE}" "${SSH_MANAGED_FILE}"
    else
        rm -f "${SSH_MANAGED_FILE}"
    fi

    validate_ssh_config
    bt_restart_service ssh sshd
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
