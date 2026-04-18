#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OPS_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
# shellcheck source=../lib/common.sh
source "${OPS_DIR}/lib/common.sh"

ACTION="${1:-apply}"

host_statuses=()

run_check() {
    local check_id="$1"
    local message="$2"
    local remediation="$3"
    shift 3

    if "$@"; then
        bt_emit_check "${check_id}" "host" "${BT_STATUS_PASS}" "${message}" "${remediation}"
        host_statuses+=("${BT_STATUS_PASS}")
        return 0
    fi

    bt_emit_check "${check_id}" "host" "${BT_STATUS_FAIL}" "${message}" "${remediation}"
    host_statuses+=("${BT_STATUS_FAIL}")
    return 1
}

emit_host_summary() {
    local summary_message="$1"
    local status
    if [[ "${#host_statuses[@]}" -eq 0 ]]; then
        status="${BT_STATUS_SKIPPED}"
    else
        status="$(bt_aggregate_statuses "${host_statuses[@]}")"
    fi
    bt_emit_plane_summary "host" "${status}" "${summary_message}" "Inspect host bootstrap checks."
    [[ "${status}" != "${BT_STATUS_FAIL}" ]]
}

verify_subset() {
    run_check "host.ssh.config_valid" "Managed SSH configuration validates." "Inspect SSH drop-in state and sshd validation output." "${OPS_DIR}/host/01-host-ssh-hardening.sh" verify || true
    run_check "host.ufw.managed_rules" "Managed UFW state is present." "Inspect UFW defaults and managed rule state." "${OPS_DIR}/host/02-host-ufw-base.sh" verify || true
    run_check "host.certbot_renewal.managed_units" "Managed Certbot renewal contract is valid for the selected TLS mode." "Inspect Certbot systemd unit/timer wiring and host TLS mode inputs." env BT_HOST_TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}" BT_CERTBOT_DOMAIN="${BT_CERTBOT_DOMAIN:-}" BT_CERTBOT_EMAIL="${BT_CERTBOT_EMAIL:-}" "${OPS_DIR}/host/05-host-certbot-renewal.sh" verify || true
    run_check "host.docker_firewall.managed_chain" "Managed Docker firewall chain is present." "Inspect DOCKER-USER jump and managed chain rules." "${OPS_DIR}/host/03-docker-firewall.sh" verify || true
    run_check "host.honeypot.source_artifact" "Managed honeypot source artifact is present." "Recreate the honeypot source artifact." test -f "${BT_HONEYPOT_SOURCE}" || true
}

write_marker_after_success() {
    bt_write_marker '["ssh","ufw","docker_firewall","honeypot_source"]'
    if bt_marker_valid; then
        bt_emit_check "host.marker.valid" "host" "${BT_STATUS_PASS}" "Host baseline marker is valid." "No action required."
        host_statuses+=("${BT_STATUS_PASS}")
    else
        bt_emit_check "host.marker.valid" "host" "${BT_STATUS_FAIL}" "Host baseline marker is invalid." "Re-run host bootstrap to recreate the marker."
        host_statuses+=("${BT_STATUS_FAIL}")
        return 1
    fi
}

apply_action() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_emit_check "host.bootstrap.dry_run" "host" "${BT_STATUS_SKIPPED}" "Dry-run does not mutate host-managed surfaces." "Run without --dry-run to apply host changes."
        host_statuses+=("${BT_STATUS_SKIPPED}")
        emit_host_summary "Host bootstrap dry-run completed."
        return 0
    fi

    bt_require_root
    bt_mkdir "${BT_BACKUP_DIR}" "${BT_RUNTIME_DIR}"

    BT_SSH_ALLOW_USER="${BT_SSH_ALLOW_USER:-}" "${OPS_DIR}/host/01-host-ssh-hardening.sh" apply
    BT_MGMT_SSH_CIDR="${BT_MGMT_SSH_CIDR:-}" \
    BT_ALLOW_SSH_ANYWHERE_FOR_DEMO="${BT_ALLOW_SSH_ANYWHERE_FOR_DEMO:-0}" \
    BT_HOST_TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}" \
    BT_ALLOW_HTTP_REDIRECT="${BT_ALLOW_HTTP_REDIRECT:-1}" \
    "${OPS_DIR}/host/02-host-ufw-base.sh" apply
    BT_HOST_TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}" \
    BT_CERTBOT_DOMAIN="${BT_CERTBOT_DOMAIN:-}" \
    BT_CERTBOT_EMAIL="${BT_CERTBOT_EMAIL:-}" \
    "${OPS_DIR}/host/05-host-certbot-renewal.sh" apply
    BT_EXTERNAL_INGRESS_NIC="${BT_EXTERNAL_INGRESS_NIC:-}" "${OPS_DIR}/host/03-docker-firewall.sh" apply
    "${OPS_DIR}/host/04-honeypot-lite.sh" manage-source

    verify_subset
    write_marker_after_success
    emit_host_summary "Host bootstrap completed."
}

verify_action() {
    verify_subset

    if [[ -r "${BT_MARKER_PATH}" ]]; then
        run_check "host.marker.valid" "Host baseline marker is valid." "Re-run host bootstrap to recreate a valid marker." bt_marker_valid || true
    fi

    emit_host_summary "Host verification completed."
}

rollback_action() {
    bt_require_root

    "${OPS_DIR}/host/04-honeypot-lite.sh" rollback
    "${OPS_DIR}/host/03-docker-firewall.sh" rollback
    BT_HOST_TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}" BT_CERTBOT_DOMAIN="${BT_CERTBOT_DOMAIN:-}" BT_CERTBOT_EMAIL="${BT_CERTBOT_EMAIL:-}" "${OPS_DIR}/host/05-host-certbot-renewal.sh" rollback
    "${OPS_DIR}/host/02-host-ufw-base.sh" rollback
    "${OPS_DIR}/host/01-host-ssh-hardening.sh" rollback
    bt_invalidate_marker

    bt_emit_check "host.rollback.managed_surfaces" "host" "${BT_STATUS_PASS}" "Managed host surfaces rolled back." "No action required."
    host_statuses+=("${BT_STATUS_PASS}")
    bt_emit_check "host.marker.invalidated" "host" "${BT_STATUS_PASS}" "Host baseline marker removed or invalidated." "No action required."
    host_statuses+=("${BT_STATUS_PASS}")

    emit_host_summary "Host rollback completed."
}

case "${ACTION}" in
    apply) apply_action ;;
    verify) verify_action ;;
    rollback) rollback_action ;;
    *)
        bt_die "Unsupported bootstrap-host action: ${ACTION}"
        ;;
esac
