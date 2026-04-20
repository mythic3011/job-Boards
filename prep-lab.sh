#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${ROOT_DIR}"

LAB_HOSTNAME="${LAB_HOSTNAME:-jobboard.lab}"
LAB_IP="${LAB_IP:-}"
LAB_EXTRA_HOSTS="${LAB_EXTRA_HOSTS:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@lab.local}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-ChangeMe123!ChangeMe123!}"
INSTALL_MODE="${INSTALL_MODE:-reset-demo}"
INSTALL_ASSUME_YES="${INSTALL_ASSUME_YES:-true}"
INSTALL_SAVE_CREDS="${INSTALL_SAVE_CREDS:-true}"
INSTALL_OUTPUT_DIR="${INSTALL_OUTPUT_DIR:-${ROOT_DIR}/.blue-team-vm/runtime/install-artifacts}"

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

# shellcheck source=ops/lib/common.sh
source "${ROOT_DIR}/ops/lib/common.sh"

detect_primary_ip() {
    local detected=""

    if command -v hostname >/dev/null 2>&1; then
        detected="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
    fi

    if [[ -z "${detected}" ]] && command -v ip >/dev/null 2>&1; then
        detected="$(ip route get 1.1.1.1 2>/dev/null | awk '/src/ {for (i = 1; i <= NF; i++) if ($i == "src") { print $(i + 1); exit }}' || true)"
    fi

    printf '%s\n' "${detected}"
}

append_unique_csv_item() {
    local -n items_ref="$1"
    local candidate="${2:-}"

    candidate="$(printf '%s' "${candidate}" | xargs 2>/dev/null || printf '%s' "${candidate}")"
    [[ -n "${candidate}" ]] || return 0

    local existing=""
    for existing in "${items_ref[@]}"; do
        if [[ "${existing}" == "${candidate}" ]]; then
            return 0
        fi
    done

    items_ref+=("${candidate}")
}

build_ssl_alt_names() {
    local -a items=()
    local detected_ip=""
    local host_name=""
    local host_fqdn=""
    local extra=""

    append_unique_csv_item items "${LAB_HOSTNAME}"

    if [[ -n "${LAB_IP}" ]]; then
        append_unique_csv_item items "${LAB_IP}"
    else
        detected_ip="$(detect_primary_ip)"
        append_unique_csv_item items "${detected_ip}"
    fi

    append_unique_csv_item items "localhost"
    append_unique_csv_item items "127.0.0.1"

    if command -v hostname >/dev/null 2>&1; then
        host_name="$(hostname 2>/dev/null || true)"
        host_fqdn="$(hostname -f 2>/dev/null || true)"
        append_unique_csv_item items "${host_name}"
        append_unique_csv_item items "${host_fqdn}"
    fi

    IFS=',' read -r -a extra <<< "${LAB_EXTRA_HOSTS}"
    for item in "${extra[@]:-}"; do
        append_unique_csv_item items "${item}"
    done

    local joined=""
    local item=""
    for item in "${items[@]}"; do
        if [[ -n "${joined}" ]]; then
            joined+=","
        fi
        joined+="${item}"
    done

    printf '%s\n' "${joined}"
}

SSL_ALT_NAMES="$(build_ssl_alt_names)"
CANONICAL_APP_URL="https://${LAB_HOSTNAME}"

bt_upsert_env_file_value .env APP_NAME '"Jobs Boards"'
bt_upsert_env_file_value .env APP_ENV "production"
bt_upsert_env_file_value .env APP_DEBUG "false"
bt_upsert_env_file_value .env APP_URL "${CANONICAL_APP_URL}"
bt_upsert_env_file_value .env ASSET_URL "/"

bt_upsert_env_file_value .env APP_PORT "80"
bt_upsert_env_file_value .env APP_SSL_PORT "443"

bt_upsert_env_file_value .env SSL_MODE "self-signed"
bt_upsert_env_file_value .env SSL_CERT_DOMAIN "${LAB_HOSTNAME}"
bt_upsert_env_file_value .env SSL_CERT_ALT_NAMES "${SSL_ALT_NAMES}"

bt_upsert_env_file_value .env TRUSTED_PROXIES "REMOTE_ADDR"
bt_upsert_env_file_value .env TRUSTED_PROXY_HEADERS "x_forwarded"

bt_upsert_env_file_value .env SESSION_DRIVER "database"
bt_upsert_env_file_value .env SESSION_ENCRYPT "true"
bt_upsert_env_file_value .env SESSION_DOMAIN "null"
bt_upsert_env_file_value .env SESSION_SECURE_COOKIE "true"

bt_upsert_env_file_value .env INSTALL_GUARD_ENABLED "false"

bt_upsert_env_file_value .env MONITORING_ACCESS_MODE "internal-only"
bt_upsert_env_file_value .env MONITORING_ALLOWED_CIDRS "127.0.0.1/32,192.168.0.0/16,192.168.153.0/24"

bt_upsert_env_file_value .env BT_STATE_DIR ".blue-team-vm"
bt_upsert_env_file_value .env BT_HONEYPOT_SOURCE "./docker/nginx/includes/blue-team-honeypot.conf"
bt_upsert_env_file_value .env BT_APP_PLANE_NETWORK_NAME "jobs-borads_app-plane"

echo "Prepared .env for lab host:"
echo "  APP_URL=${CANONICAL_APP_URL}"
echo "  SSL_CERT_DOMAIN=${LAB_HOSTNAME}"
echo "  SSL_CERT_ALT_NAMES=${SSL_ALT_NAMES}"
echo ""

INSTALL_ASSUME_YES="${INSTALL_ASSUME_YES}" \
INSTALL_APP_URL="${CANONICAL_APP_URL}" \
INSTALL_ADMIN_EMAIL="${ADMIN_EMAIL}" \
INSTALL_ADMIN_PASSWORD="${ADMIN_PASSWORD}" \
INSTALL_SAVE_CREDS="${INSTALL_SAVE_CREDS}" \
INSTALL_OUTPUT_DIR="${INSTALL_OUTPUT_DIR}" \
./install.sh "${INSTALL_MODE}" production

docker exec \
    -e CANONICAL_APP_URL="${CANONICAL_APP_URL}" \
    jobs-boards-laravel.test \
    php artisan tinker --execute="if (\Illuminate\Support\Facades\Schema::hasTable('settings')) { App\Models\Setting::set('app_url', getenv('CANONICAL_APP_URL')); }"

docker exec jobs-boards-laravel.test php artisan optimize:clear

echo ""
echo "Lab prep complete."
echo "Open: ${CANONICAL_APP_URL}"
