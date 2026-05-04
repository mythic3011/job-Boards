#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODE="${1:-${SETUP_MODE:-reset-demo}}"
SSL_SWITCH_TARGET_MODE="${2:-${SETUP_SSL_SWITCH_TARGET:-}}"
if [[ "${MODE}" == "ssl-switch" ]]; then
    ENV_MODE="${3:-${SETUP_ENV_MODE:-dev}}"
else
    ENV_MODE="${2:-${SETUP_ENV_MODE:-dev}}"
fi

usage() {
    cat <<'EOF'
Usage:
  ./setup.sh [reset-demo|deploy|up|bootstrap|reset|seed-admin|mark-installed|test-prepare|verify|demo|full|quick|setupAdmin|skip|test] [dev|production]
  ./setup.sh ssl-switch <self-signed|cloudflare-origin|letsencrypt|custom> [dev|production]

Defaults:
  ./setup.sh
    -> ./install.sh reset-demo dev

Examples:
  ./setup.sh
  INSTALL_ADMIN_EMAIL=admin@example.com INSTALL_ADMIN_PASSWORD='StrongPass123!45' INSTALL_ASSUME_YES=true ./setup.sh
  ./setup.sh demo dev
  ./setup.sh deploy production
  ./setup.sh ssl-switch letsencrypt production
EOF
}

case "${MODE}" in
    -h|--help|help)
        usage
        exit 0
        ;;
esac

case "${ENV_MODE}" in
    dev|production)
        ;;
    *)
        usage
        exit 1
        ;;
esac

cd "${ROOT_DIR}"

export INSTALL_SAVE_CREDS="${INSTALL_SAVE_CREDS:-true}"
export INSTALL_OUTPUT_DIR="${INSTALL_OUTPUT_DIR:-${ROOT_DIR}/.blue-team-vm/runtime/install-artifacts}"
export INSTALL_SSL_SWITCH_TARGET="${INSTALL_SSL_SWITCH_TARGET:-${SSL_SWITCH_TARGET_MODE}}"

if [[ "${MODE}" == "ssl-switch" ]]; then
    exec "${ROOT_DIR}/install.sh" "${MODE}" "${INSTALL_SSL_SWITCH_TARGET}" "${ENV_MODE}"
fi

exec "${ROOT_DIR}/install.sh" "${MODE}" "${ENV_MODE}"
