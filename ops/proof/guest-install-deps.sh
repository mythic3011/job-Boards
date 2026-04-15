#!/usr/bin/env bash
#
# Internal staged helper for pd-cleanvm-proof.sh and guest-blue-team-proof.sh.
# This script is not a public operator entrypoint.

set -euo pipefail

BT_PROOF_OUTPUT_DIR="${BT_PROOF_OUTPUT_DIR:-${HOME}/proof/output/current}"
BT_PROOF_LOG_ROOT="${BT_PROOF_LOG_ROOT:-${BT_PROOF_OUTPUT_DIR}/logs}"
BT_PROOF_LOG_FILE="${BT_PROOF_LOG_FILE:-${BT_PROOF_LOG_ROOT}/01-guest-install-deps.log}"

log() {
    printf '%s\n' "$*" | tee -a "${BT_PROOF_LOG_FILE}"
}

run_privileged() {
    if [[ "${EUID}" -eq 0 ]]; then
        "$@"
        return 0
    fi

    sudo -n "$@"
}

run_logged_privileged() {
    if [[ "${EUID}" -eq 0 ]]; then
        "$@" 2>&1 | tee -a "${BT_PROOF_LOG_FILE}"
        return "${PIPESTATUS[0]}"
    fi

    sudo -n "$@" 2>&1 | tee -a "${BT_PROOF_LOG_FILE}"
    return "${PIPESTATUS[0]}"
}

prepare_logging() {
    umask 022
    mkdir -p "${BT_PROOF_LOG_ROOT}"
    : > "${BT_PROOF_LOG_FILE}"
    chmod 0644 "${BT_PROOF_LOG_FILE}"
}

prepare_current_user_docker_access() {
    local current_user

    current_user="$(id -un)"
    if [[ "${current_user}" == "root" ]]; then
        return 0
    fi

    run_logged_privileged groupadd -f docker
    run_logged_privileged usermod -aG docker "${current_user}"
}

command_ready() {
    local command_name="$1"

    case "${command_name}" in
        docker)
            docker --version >/dev/null 2>&1 && docker compose version >/dev/null 2>&1
            ;;
        *)
            "${command_name}" --version >/dev/null 2>&1
            ;;
    esac
}

install_packages() {
    export DEBIAN_FRONTEND=noninteractive

    run_logged_privileged apt-get update
    run_logged_privileged apt-get install -y \
        ca-certificates \
        coreutils \
        curl \
        docker.io \
        docker-compose-plugin \
        git \
        jq \
        python3
}

verify_toolchain() {
    local command_name
    local required=(
        docker
        git
        curl
        jq
        python3
        sha256sum
    )

    hash -r

    for command_name in "${required[@]}"; do
        if ! command_ready "${command_name}"; then
            log "Required tool check failed: ${command_name}"
            return 1
        fi
    done

    return 0
}

main() {
    prepare_logging

    if verify_toolchain; then
        log "Guest dependency toolchain already present."
        return 0
    fi

    log "Installing guest proof dependencies."
    install_packages
    prepare_current_user_docker_access

    if ! verify_toolchain; then
        log "Guest dependency toolchain verification failed after install."
        return 1
    fi

    log "Guest dependency toolchain ready."
}

main "$@"
