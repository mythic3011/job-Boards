#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

ACTION="${1:-apply}"
UFW_STATE_DIR="${BT_RUNTIME_DIR}/ufw"
UFW_RULES_FILE="${UFW_STATE_DIR}/managed-rules.txt"
UFW_DEFAULTS_FILE="${UFW_STATE_DIR}/defaults.env"
SSH_CIDR="${BT_MGMT_SSH_CIDR:-}"
ALLOW_ANYWHERE="${BT_ALLOW_SSH_ANYWHERE_FOR_DEMO:-0}"
TLS_MODE="${BT_HOST_TLS_MODE:-cloudflare-origin}"
ALLOW_HTTP_REDIRECT="${BT_ALLOW_HTTP_REDIRECT:-1}"

usage() {
    cat <<EOF
Usage: $0 [apply|verify|rollback]
EOF
}

validate_tls_policy_input() {
    case "${TLS_MODE}" in
        cloudflare-origin|letsencrypt-http01|letsencrypt-dns01|custom)
            ;;
        *)
            bt_die "BT_HOST_TLS_MODE must be one of: cloudflare-origin, letsencrypt-http01, letsencrypt-dns01, custom"
            ;;
    esac

    case "${ALLOW_HTTP_REDIRECT}" in
        0|1|true|false)
            ;;
        *)
            bt_die "BT_ALLOW_HTTP_REDIRECT must be 0, 1, true, or false"
            ;;
    esac
}

should_allow_http_port() {
    case "${TLS_MODE}" in
        letsencrypt-http01)
            return 0
            ;;
    esac

    case "${ALLOW_HTTP_REDIRECT}" in
        1|true)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

require_ssh_policy_input() {
    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        return 0
    fi

    if [[ -n "${SSH_CIDR}" || "${ALLOW_ANYWHERE}" == "1" ]]; then
        return 0
    fi

    bt_die "Missing SSH source policy. Provide --mgmt-ssh-cidr or --allow-ssh-anywhere-for-demo."
}

capture_defaults() {
    [[ "${BT_DRY_RUN}" == "1" ]] && return 0

    bt_mkdir "${UFW_STATE_DIR}"
    python3 - "${UFW_DEFAULTS_FILE}" <<'PY'
import subprocess
import sys

path = sys.argv[1]
incoming = "deny"
outgoing = "allow"

try:
    result = subprocess.run(
        ["ufw", "status", "verbose"],
        capture_output=True,
        check=False,
        text=True,
    )
    for line in result.stdout.splitlines():
        if line.startswith("Default:"):
            if "deny (incoming)" in line:
                incoming = "deny"
            elif "allow (incoming)" in line:
                incoming = "allow"
            if "allow (outgoing)" in line:
                outgoing = "allow"
            elif "deny (outgoing)" in line:
                outgoing = "deny"
            break
except FileNotFoundError:
    pass

with open(path, "w", encoding="utf-8") as handle:
    handle.write(f"INCOMING={incoming}\n")
    handle.write(f"OUTGOING={outgoing}\n")
PY
}

record_rules() {
    local rules=("$@")

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN write ${UFW_RULES_FILE}"
        return 0
    fi

    bt_mkdir "${UFW_STATE_DIR}"
    printf '%s\n' "${rules[@]}" > "${UFW_RULES_FILE}"
}

delete_recorded_rules() {
    command -v python3 >/dev/null 2>&1 || bt_die "python3 is required but was not found."

    mapfile -t numbers < <(ufw status numbered | python3 -c '
import re
import sys

comment = sys.argv[1]
lines = sys.stdin.read().splitlines()
numbers = []
for line in lines:
    if comment in line:
        match = re.search(r"\[\s*(\d+)\]", line)
        if match:
            numbers.append(int(match.group(1)))

for number in sorted(numbers, reverse=True):
    print(number)
' "${BT_MANAGED_COMMENT}")

    local number
    for number in "${numbers[@]}"; do
        bt_run ufw --force delete "${number}"
    done

    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        rm -f "${UFW_RULES_FILE}"
    fi
}

apply_action() {
    bt_require_root
    require_ssh_policy_input
    validate_tls_policy_input

    command -v ufw >/dev/null 2>&1 || bt_die "ufw is required but was not found."

    capture_defaults
    delete_recorded_rules

    bt_run ufw default deny incoming
    bt_run ufw default allow outgoing

    local ssh_rule
    if [[ -n "${SSH_CIDR}" ]]; then
        ssh_rule="allow from ${SSH_CIDR} to any port 22 proto tcp comment ${BT_MANAGED_COMMENT}:ssh"
    else
        ssh_rule="allow 22/tcp comment ${BT_MANAGED_COMMENT}:ssh-anywhere"
    fi

    local managed_rules=("${ssh_rule}")
    local https_rule="allow 443/tcp comment ${BT_MANAGED_COMMENT}:https"

    bt_run ufw ${ssh_rule}
    if should_allow_http_port; then
        local http_rule="allow 80/tcp comment ${BT_MANAGED_COMMENT}:http"
        bt_run ufw ${http_rule}
        managed_rules+=("${http_rule}")
    fi
    bt_run ufw ${https_rule}
    managed_rules+=("${https_rule}")
    bt_run ufw --force enable

    record_rules "${managed_rules[@]}"
}

verify_action() {
    validate_tls_policy_input
    command -v ufw >/dev/null 2>&1 || bt_die "ufw is required but was not found."
    [[ -f "${UFW_RULES_FILE}" ]] || bt_die "Managed UFW rules record is missing: ${UFW_RULES_FILE}"
    ufw status >/dev/null
}

rollback_action() {
    bt_require_root
    command -v ufw >/dev/null 2>&1 || bt_die "ufw is required but was not found."

    delete_recorded_rules

    if [[ -f "${UFW_DEFAULTS_FILE}" ]]; then
        # shellcheck disable=SC1090
        source "${UFW_DEFAULTS_FILE}"
        bt_run ufw default "${INCOMING:-deny}" incoming
        bt_run ufw default "${OUTGOING:-allow}" outgoing
        if [[ "${BT_DRY_RUN}" != "1" ]]; then
            rm -f "${UFW_DEFAULTS_FILE}"
        fi
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
