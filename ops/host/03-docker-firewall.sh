#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
IPTABLES_BIN="${BT_IPTABLES_BIN:-iptables}"
INGRESS_NIC="${BT_EXTERNAL_INGRESS_NIC:-}"

usage() {
    cat <<EOF
Usage: $0 [apply|verify|rollback]
EOF
}

require_iptables() {
    command -v "${IPTABLES_BIN}" >/dev/null 2>&1 || bt_die "iptables is required but was not found."
}

resolve_ingress_nic() {
    if [[ -n "${INGRESS_NIC}" ]]; then
        printf '%s\n' "${INGRESS_NIC}"
        return 0
    fi

    ip route show default 2>/dev/null | awk '/default/ {print $5; exit}'
}

verify_compose_network_drivers() {
    [[ -f "${BT_COMPOSE_APP_FILE}" ]] || bt_die "compose.app.yml is missing: ${BT_COMPOSE_APP_FILE}"

    local bad=()
    while IFS= read -r line; do
        [[ -z "${line}" ]] && continue
        local name="${line%%:*}"
        local driver="${line##*:}"
        if [[ "${driver}" != "bridge" ]]; then
            bad+=("${name}:${driver}")
        fi
    done < <(bt_compose_network_drivers "${BT_COMPOSE_APP_FILE}")

    if [[ "${#bad[@]}" -gt 0 ]]; then
        bt_die "Unsupported app-plane Docker network drivers detected: ${bad[*]}"
    fi
}

ensure_chain() {
    bt_run "${IPTABLES_BIN}" -N "${BT_MANAGED_CHAIN}" 2>/dev/null || true
    bt_run "${IPTABLES_BIN}" -F "${BT_MANAGED_CHAIN}"
}

ensure_jump() {
    if ! "${IPTABLES_BIN}" -C DOCKER-USER -j "${BT_MANAGED_CHAIN}" >/dev/null 2>&1; then
        bt_run "${IPTABLES_BIN}" -I DOCKER-USER 1 -j "${BT_MANAGED_CHAIN}"
    fi
}

apply_rules() {
    local ingress_nic="$1"

    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -m conntrack --ctstate ESTABLISHED,RELATED -j RETURN
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -i lo -j RETURN
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -o lo -j RETURN
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -i docker0 -j RETURN
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -o docker0 -j RETURN
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -i "${ingress_nic}" -p tcp --dport 80 -j RETURN
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -i "${ingress_nic}" -p tcp --dport 443 -j RETURN
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -i "${ingress_nic}" -m conntrack --ctstate NEW -j DROP
    bt_run "${IPTABLES_BIN}" -A "${BT_MANAGED_CHAIN}" -j RETURN
}

apply_action() {
    bt_require_root
    require_iptables
    verify_compose_network_drivers

    local ingress_nic
    ingress_nic="$(resolve_ingress_nic)"
    [[ -n "${ingress_nic}" ]] || bt_die "Unable to determine external ingress NIC."

    ensure_chain
    ensure_jump
    apply_rules "${ingress_nic}"
}

verify_action() {
    require_iptables
    verify_compose_network_drivers
    "${IPTABLES_BIN}" -S "${BT_MANAGED_CHAIN}" >/dev/null
    "${IPTABLES_BIN}" -C DOCKER-USER -j "${BT_MANAGED_CHAIN}" >/dev/null
}

rollback_action() {
    bt_require_root
    require_iptables

    if "${IPTABLES_BIN}" -C DOCKER-USER -j "${BT_MANAGED_CHAIN}" >/dev/null 2>&1; then
        bt_run "${IPTABLES_BIN}" -D DOCKER-USER -j "${BT_MANAGED_CHAIN}"
    fi

    if "${IPTABLES_BIN}" -S "${BT_MANAGED_CHAIN}" >/dev/null 2>&1; then
        bt_run "${IPTABLES_BIN}" -F "${BT_MANAGED_CHAIN}"
        bt_run "${IPTABLES_BIN}" -X "${BT_MANAGED_CHAIN}"
    fi
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
