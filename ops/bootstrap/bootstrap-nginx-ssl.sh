#!/usr/bin/env bash

set -euo pipefail

PRESERVED_ENV_KEYS="$(env | cut -d= -f1)"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
# shellcheck source=../lib/common.sh
source "${SCRIPT_DIR}/../lib/common.sh"

COMMON_DEFAULT_STATE_DIR="/var/lib/blue-team-vm"
COMMON_DEFAULT_RUNTIME_DIR="${COMMON_DEFAULT_STATE_DIR}/runtime"

repo_path() {
    local path="${1:-}"

    case "${path}" in
        "")
            printf '%s\n' ""
            ;;
        "~")
            printf '%s\n' "${HOME}"
            ;;
        "~/"*)
            printf '%s\n' "${HOME}/${path#"~/"}"
            ;;
        /*)
            printf '%s\n' "${path}"
            ;;
        ./*)
            printf '%s\n' "${REPO_ROOT}/${path#./}"
            ;;
        *)
            printf '%s\n' "${REPO_ROOT}/${path}"
            ;;
    esac
}

maybe_repo_path() {
    local path="${1:-}"

    [[ -n "${path}" ]] || return 0

    case "${path}" in
        /*|~|~/*|./*|../*)
            repo_path "${path}"
            ;;
        *)
            if [[ "${path}" == *"/"* ]]; then
                repo_path "${path}"
            else
                printf '%s\n' "${path}"
            fi
            ;;
    esac
}

load_repo_env() {
    local env_file="${BT_SSL_ENV_FILE:-${REPO_ROOT}/.env}"

    [[ -r "${env_file}" ]] || return 0
    bt_export_env_file_unless_preserved "${env_file}" "${PRESERVED_ENV_KEYS}"
}

resolve_state_dirs() {
    if ! bt_env_snapshot_has_key "${PRESERVED_ENV_KEYS}" "BT_STATE_DIR" && [[ "${BT_STATE_DIR}" == "${COMMON_DEFAULT_STATE_DIR}" ]]; then
        BT_STATE_DIR="${REPO_ROOT}/.blue-team-vm"
    fi

    if ! bt_env_snapshot_has_key "${PRESERVED_ENV_KEYS}" "BT_RUNTIME_DIR" && [[ "${BT_RUNTIME_DIR}" == "${COMMON_DEFAULT_RUNTIME_DIR}" ]]; then
        BT_RUNTIME_DIR="${BT_STATE_DIR}/runtime"
    fi

    BT_STATE_DIR="$(repo_path "${BT_STATE_DIR}")"
    BT_RUNTIME_DIR="$(repo_path "${BT_RUNTIME_DIR}")"
}

load_repo_env
resolve_state_dirs

ACTION="${1:-prepare}"
ACTION_MODE_INPUT="${2:-}"

: "${SSL_CERT_DOMAIN:=localhost}"
: "${SSL_CERT_ALT_NAMES:=}"
: "${SSL_SELF_SIGNED_ALT_NAMES:=}"
: "${SSL_MODE:=self-signed}"
: "${SSL_ACME_CLIENT:=acme.sh}"
: "${SSL_LETSENCRYPT_CHALLENGE:=dns-cloudflare}"
: "${SSL_SELF_SIGNED_DAYS:=825}"
: "${SSL_SELF_SIGNED_SUBJECT:=/C=HK/ST=HK/L=HongKong/O=JobsBoards/OU=Dev/CN=${SSL_CERT_DOMAIN}}"
: "${BT_NGINX_CONTAINER_NAME:=${BT_NGINX_CONTAINER:-jobs-boards-nginx}}"
: "${BT_NGINX_SSL_RUNTIME_DIR:=${BT_RUNTIME_DIR}/nginx-ssl}"
: "${BT_NGINX_SSL_ARCHIVE_DIR:=${BT_NGINX_SSL_RUNTIME_DIR}/modes}"
: "${BT_NGINX_SSL_STATE_DIR:=${BT_NGINX_SSL_RUNTIME_DIR}/state}"
: "${BT_NGINX_SSL_RENDERED_INCLUDE_FILE:=${BT_RUNTIME_DIR}/rendered/ssl-mode.conf}"
: "${BT_NGINX_SSL_TEMPLATE_FILE:=${REPO_ROOT}/docker/nginx/templates/ssl-mode.conf.tpl}"
: "${BT_NGINX_SSL_MODE_FILE:=${BT_NGINX_SSL_STATE_DIR}/current-mode}"
: "${BT_NGINX_SSL_MODE_ENV_FILE:=${BT_NGINX_SSL_STATE_DIR}/ssl-mode.env}"
: "${BT_NGINX_SSL_MODE_JSON_FILE:=${BT_NGINX_SSL_STATE_DIR}/ssl-mode.json}"
: "${BT_NGINX_SSL_RENEW_HELPER:=${BT_NGINX_SSL_STATE_DIR}/renew-nginx-ssl.sh}"
: "${BT_NGINX_SSL_RENEW_CRON_FILE:=${BT_NGINX_SSL_STATE_DIR}/renew-nginx-ssl.cron}"
: "${BT_NGINX_SSL_ACTIVE_CERT_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/active.crt}"
: "${BT_NGINX_SSL_ACTIVE_KEY_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/active.key}"
: "${BT_NGINX_SSL_COMPAT_CERT_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/selfsigned.crt}"
: "${BT_NGINX_SSL_COMPAT_KEY_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/selfsigned.key}"
: "${BT_NGINX_SSL_GENERIC_CERT_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/cert.pem}"
: "${BT_NGINX_SSL_GENERIC_KEY_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/key.pem}"
: "${BT_NGINX_SSL_FULLCHAIN_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/fullchain.pem}"
: "${BT_NGINX_SSL_PRIVKEY_FILE:=${BT_NGINX_SSL_RUNTIME_DIR}/privkey.pem}"
: "${SSL_CLOUDFLARE_ORIGIN_CERT:=${SSL_CLOUDFLARE_ORIGIN_CERT_FILE:-/etc/nginx/cert/${SSL_CERT_DOMAIN}/cert.pem}}"
: "${SSL_CLOUDFLARE_ORIGIN_KEY:=${SSL_CLOUDFLARE_ORIGIN_KEY_FILE:-/etc/nginx/cert/${SSL_CERT_DOMAIN}/key.pem}}"
: "${SSL_LETSENCRYPT_CERT_PATH:=/etc/letsencrypt/live/${SSL_CERT_DOMAIN}/fullchain.pem}"
: "${SSL_LETSENCRYPT_KEY_PATH:=/etc/letsencrypt/live/${SSL_CERT_DOMAIN}/privkey.pem}"
: "${SSL_CUSTOM_CERT_PATH:=}"
: "${SSL_CUSTOM_CA_BUNDLE_PATH:=}"
: "${SSL_CUSTOM_KEY_PATH:=}"
: "${SSL_ACME_WEBROOT:=${REPO_ROOT}/public}"

BT_NGINX_SSL_RUNTIME_DIR="$(repo_path "${BT_NGINX_SSL_RUNTIME_DIR}")"
BT_NGINX_SSL_ARCHIVE_DIR="$(repo_path "${BT_NGINX_SSL_ARCHIVE_DIR}")"
BT_NGINX_SSL_STATE_DIR="$(repo_path "${BT_NGINX_SSL_STATE_DIR}")"
BT_NGINX_SSL_RENDERED_INCLUDE_FILE="$(repo_path "${BT_NGINX_SSL_RENDERED_INCLUDE_FILE}")"
BT_NGINX_SSL_TEMPLATE_FILE="$(repo_path "${BT_NGINX_SSL_TEMPLATE_FILE}")"
BT_NGINX_SSL_MODE_FILE="$(repo_path "${BT_NGINX_SSL_MODE_FILE}")"
BT_NGINX_SSL_MODE_ENV_FILE="$(repo_path "${BT_NGINX_SSL_MODE_ENV_FILE}")"
BT_NGINX_SSL_MODE_JSON_FILE="$(repo_path "${BT_NGINX_SSL_MODE_JSON_FILE}")"
BT_NGINX_SSL_RENEW_HELPER="$(repo_path "${BT_NGINX_SSL_RENEW_HELPER}")"
BT_NGINX_SSL_RENEW_CRON_FILE="$(repo_path "${BT_NGINX_SSL_RENEW_CRON_FILE}")"
BT_NGINX_SSL_ACTIVE_CERT_FILE="$(repo_path "${BT_NGINX_SSL_ACTIVE_CERT_FILE}")"
BT_NGINX_SSL_ACTIVE_KEY_FILE="$(repo_path "${BT_NGINX_SSL_ACTIVE_KEY_FILE}")"
BT_NGINX_SSL_COMPAT_CERT_FILE="$(repo_path "${BT_NGINX_SSL_COMPAT_CERT_FILE}")"
BT_NGINX_SSL_COMPAT_KEY_FILE="$(repo_path "${BT_NGINX_SSL_COMPAT_KEY_FILE}")"
BT_NGINX_SSL_GENERIC_CERT_FILE="$(repo_path "${BT_NGINX_SSL_GENERIC_CERT_FILE}")"
BT_NGINX_SSL_GENERIC_KEY_FILE="$(repo_path "${BT_NGINX_SSL_GENERIC_KEY_FILE}")"
BT_NGINX_SSL_FULLCHAIN_FILE="$(repo_path "${BT_NGINX_SSL_FULLCHAIN_FILE}")"
BT_NGINX_SSL_PRIVKEY_FILE="$(repo_path "${BT_NGINX_SSL_PRIVKEY_FILE}")"
SSL_ACME_WEBROOT="$(repo_path "${SSL_ACME_WEBROOT}")"
SSL_CLOUDFLARE_ORIGIN_CERT="$(maybe_repo_path "${SSL_CLOUDFLARE_ORIGIN_CERT}")"
SSL_CLOUDFLARE_ORIGIN_KEY="$(maybe_repo_path "${SSL_CLOUDFLARE_ORIGIN_KEY}")"
SSL_LETSENCRYPT_CERT_PATH="$(maybe_repo_path "${SSL_LETSENCRYPT_CERT_PATH}")"
SSL_LETSENCRYPT_KEY_PATH="$(maybe_repo_path "${SSL_LETSENCRYPT_KEY_PATH}")"
SSL_CUSTOM_CERT_PATH="$(maybe_repo_path "${SSL_CUSTOM_CERT_PATH}")"
SSL_CUSTOM_CA_BUNDLE_PATH="$(maybe_repo_path "${SSL_CUSTOM_CA_BUNDLE_PATH}")"
SSL_CUSTOM_KEY_PATH="$(maybe_repo_path "${SSL_CUSTOM_KEY_PATH}")"

CURRENT_MODE=""
TARGET_MODE=""
PROVISIONED_CERT_PATH=""
PROVISIONED_KEY_PATH=""
PROVISION_SOURCE=""
PROVISION_CLIENT=""
PROVISION_CHALLENGE=""

usage() {
    cat <<'EOF'
Usage:
  ./ops/bootstrap/bootstrap-nginx-ssl.sh prepare [self-signed|cloudflare-origin|letsencrypt|custom]
  ./ops/bootstrap/bootstrap-nginx-ssl.sh switch <self-signed|cloudflare-origin|letsencrypt|custom>
  ./ops/bootstrap/bootstrap-nginx-ssl.sh status

Description:
  Materialize the host-side nginx SSL runtime under ${BT_STATE_DIR}/runtime/nginx-ssl,
  maintain a fast mode switch path, and reload the nginx container when it is already
  running.

Important inputs:
  SSL_MODE=self-signed|cloudflare-origin|letsencrypt|custom
  SSL_CERT_DOMAIN=localhost

  SSL_CLOUDFLARE_ORIGIN_CERT=/etc/nginx/cert/<domain>/cert.pem
  SSL_CLOUDFLARE_ORIGIN_KEY=/etc/nginx/cert/<domain>/key.pem
  SSL_CLOUDFLARE_ORIGIN_GENERATE_HOOK=/path/to/executable

  SSL_LETSENCRYPT_CERT_PATH=/etc/letsencrypt/live/<domain>/fullchain.pem
  SSL_LETSENCRYPT_KEY_PATH=/etc/letsencrypt/live/<domain>/privkey.pem
  SSL_CUSTOM_CERT_PATH=/secure/path/fullchain.pem
  SSL_CUSTOM_CA_BUNDLE_PATH=/secure/path/ca_bundle.crt
  SSL_CUSTOM_KEY_PATH=/secure/path/private.key
  SSL_LETSENCRYPT_GENERATE_HOOK=/path/to/executable
  SSL_ACME_CLIENT=acme.sh|certbot
  SSL_LETSENCRYPT_CHALLENGE=dns-cloudflare|http-01
  SSL_ACME_EMAIL=ops@example.com
  SSL_ACME_WEBROOT=./public
  SSL_CERTBOT_CREDENTIALS_FILE=/path/to/cloudflare.ini

  CF_Token=<cloudflare-api-token>
  CF_Zone_ID=<cloudflare-zone-id>

Notes:
  - self-signed uses built-in OpenSSL generation.
  - cloudflare-origin supports copy-from-source or an external generate hook.
  - letsencrypt supports copy-from-source, an external generate hook, or built-in
    acme.sh / certbot flows when the required tooling and credentials exist.
  - custom copies an externally managed PEM certificate/fullchain and key. If
    SSL_CUSTOM_CA_BUNDLE_PATH is set, custom mode builds cert.pem from
    certificate + CA bundle for vendors such as ZeroSSL.
EOF
}

normalize_mode() {
    local raw="${1:-}"

    case "${raw}" in
        self-signed|selfsigned|"")
            printf '%s\n' "self-signed"
            ;;
        cloudflare-origin|cloudflare|origin)
            printf '%s\n' "cloudflare-origin"
            ;;
        letsencrypt)
            printf '%s\n' "letsencrypt"
            ;;
        custom|external|zerossl|zero-ssl)
            printf '%s\n' "custom"
            ;;
        letsencrypt-http01)
            if [[ -z "${SSL_LETSENCRYPT_CHALLENGE:-}" || "${SSL_LETSENCRYPT_CHALLENGE}" == "dns-cloudflare" ]]; then
                SSL_LETSENCRYPT_CHALLENGE="http-01"
            fi
            printf '%s\n' "letsencrypt"
            ;;
        letsencrypt-dns01)
            if [[ -z "${SSL_LETSENCRYPT_CHALLENGE:-}" || "${SSL_LETSENCRYPT_CHALLENGE}" == "http-01" ]]; then
                SSL_LETSENCRYPT_CHALLENGE="dns-cloudflare"
            fi
            printf '%s\n' "letsencrypt"
            ;;
        *)
            bt_die "Unsupported SSL mode '${raw}'. Expected: self-signed, cloudflare-origin, letsencrypt, custom"
            ;;
    esac
}

current_mode() {
    if [[ -r "${BT_NGINX_SSL_MODE_FILE}" ]]; then
        tr -d '[:space:]' < "${BT_NGINX_SSL_MODE_FILE}"
        return 0
    fi

    printf '%s\n' ""
}

mode_dir() {
    local mode="$1"

    case "${mode}" in
        self-signed)
            printf '%s\n' "${BT_NGINX_SSL_RUNTIME_DIR}"
            ;;
        cloudflare-origin|letsencrypt|custom)
            printf '%s\n' "${BT_NGINX_SSL_RUNTIME_DIR}/${mode}/${SSL_CERT_DOMAIN}"
            ;;
        *)
            bt_die "Unsupported SSL mode '${mode}'."
            ;;
    esac
}

mode_cert_path() {
    local mode="$1"

    if [[ "${mode}" == "self-signed" ]]; then
        printf '%s\n' "${BT_NGINX_SSL_COMPAT_CERT_FILE}"
        return 0
    fi

    printf '%s\n' "$(mode_dir "${mode}")/cert.pem"
}

mode_key_path() {
    local mode="$1"

    if [[ "${mode}" == "self-signed" ]]; then
        printf '%s\n' "${BT_NGINX_SSL_COMPAT_KEY_FILE}"
        return 0
    fi

    printf '%s\n' "$(mode_dir "${mode}")/key.pem"
}

mode_fullchain_path() {
    local mode="$1"

    if [[ "${mode}" == "self-signed" ]]; then
        printf '%s\n' "${BT_NGINX_SSL_COMPAT_CERT_FILE}"
        return 0
    fi

    printf '%s\n' "$(mode_dir "${mode}")/fullchain.pem"
}

mode_privkey_path() {
    local mode="$1"

    if [[ "${mode}" == "self-signed" ]]; then
        printf '%s\n' "${BT_NGINX_SSL_COMPAT_KEY_FILE}"
        return 0
    fi

    printf '%s\n' "$(mode_dir "${mode}")/privkey.pem"
}

mode_source_env_path() {
    local mode="$1"
    printf '%s\n' "$(mode_dir "${mode}")/source.env"
}

require_command() {
    local candidate="$1"
    local label="$2"

    if command -v "${candidate}" >/dev/null 2>&1; then
        return 0
    fi

    bt_die "${label} is required but '${candidate}' is not available."
}

validate_domain() {
    local domain="${SSL_CERT_DOMAIN:-}"

    [[ -n "${domain}" ]] || bt_die "SSL_CERT_DOMAIN must not be empty."
    [[ ! "${domain}" =~ [[:space:]] ]] || bt_die "SSL_CERT_DOMAIN must not contain whitespace."
    [[ "${domain}" != */* ]] || bt_die "SSL_CERT_DOMAIN must not contain path separators."
}

normalize_file_target_path() {
    local path="$1"

    if [[ -d "${path}" ]]; then
        bt_log "Removing legacy directory at file target ${path}"
        bt_run rm -rf "${path}"
    fi
}

ensure_runtime_layout() {
    bt_mkdir "${BT_NGINX_SSL_RUNTIME_DIR}" "${BT_NGINX_SSL_ARCHIVE_DIR}" "${BT_NGINX_SSL_STATE_DIR}" "$(dirname "${BT_NGINX_SSL_RENDERED_INCLUDE_FILE}")"
    bt_mkdir "$(mode_dir "cloudflare-origin")" "$(mode_dir "letsencrypt")" "$(mode_dir "custom")"
}

nginx_runtime_cert_path() {
    local mode="$1"

    case "${mode}" in
        self-signed)
            printf '%s\n' "/etc/nginx/ssl/selfsigned.crt"
            ;;
        cloudflare-origin)
            printf '/etc/nginx/ssl/cloudflare-origin/%s/fullchain.pem\n' "${SSL_CERT_DOMAIN}"
            ;;
        letsencrypt)
            printf '/etc/nginx/ssl/letsencrypt/%s/fullchain.pem\n' "${SSL_CERT_DOMAIN}"
            ;;
        custom)
            printf '/etc/nginx/ssl/custom/%s/fullchain.pem\n' "${SSL_CERT_DOMAIN}"
            ;;
        *)
            bt_die "Unsupported SSL mode '${mode}'."
            ;;
    esac
}

nginx_runtime_key_path() {
    local mode="$1"

    case "${mode}" in
        self-signed)
            printf '%s\n' "/etc/nginx/ssl/selfsigned.key"
            ;;
        cloudflare-origin)
            printf '/etc/nginx/ssl/cloudflare-origin/%s/key.pem\n' "${SSL_CERT_DOMAIN}"
            ;;
        letsencrypt)
            printf '/etc/nginx/ssl/letsencrypt/%s/privkey.pem\n' "${SSL_CERT_DOMAIN}"
            ;;
        custom)
            printf '/etc/nginx/ssl/custom/%s/key.pem\n' "${SSL_CERT_DOMAIN}"
            ;;
        *)
            bt_die "Unsupported SSL mode '${mode}'."
            ;;
    esac
}

render_ssl_mode_conf() {
    local mode="$1"
    local cert_path
    local key_path

    cert_path="$(nginx_runtime_cert_path "${mode}")"
    key_path="$(nginx_runtime_key_path "${mode}")"
    [[ -r "${BT_NGINX_SSL_TEMPLATE_FILE}" ]] || bt_die "Missing nginx SSL template: ${BT_NGINX_SSL_TEMPLATE_FILE}"
    normalize_file_target_path "${BT_NGINX_SSL_RENDERED_INCLUDE_FILE}"

    python3 - "${BT_NGINX_SSL_TEMPLATE_FILE}" "${BT_NGINX_SSL_RENDERED_INCLUDE_FILE}" "${cert_path}" "${key_path}" <<'PY'
from pathlib import Path
import sys

template_path = Path(sys.argv[1])
output_path = Path(sys.argv[2])
cert_path = sys.argv[3]
key_path = sys.argv[4]

header = "\n".join([
    "# Generated file: ssl-mode.conf",
    "# Purpose: nginx include that points the app-plane container at the active SSL certificate and key.",
    "# Repo path: .blue-team-vm/runtime/rendered/ssl-mode.conf",
    "# Regenerate via: ./ops/bootstrap/bootstrap-nginx-ssl.sh prepare|switch|status",
    "",
])

rendered = template_path.read_text(encoding="utf-8")
rendered = rendered.replace("__SSL_CERT_PATH__", cert_path)
rendered = rendered.replace("__SSL_KEY_PATH__", key_path)
output_path.parent.mkdir(parents=True, exist_ok=True)
output_path.write_text(header + rendered, encoding="utf-8")
PY
}

assert_valid_cert_material() {
    local cert_path="$1"
    local key_path="$2"
    local cert_pubkey_hash=""
    local key_pubkey_hash=""

    [[ -r "${cert_path}" ]] || bt_die "Certificate file is missing or unreadable: ${cert_path}"
    [[ -r "${key_path}" ]] || bt_die "Private key file is missing or unreadable: ${key_path}"

    require_command "openssl" "openssl"

    openssl x509 -noout -in "${cert_path}" >/dev/null 2>&1 || bt_die "Invalid certificate file: ${cert_path}"
    openssl pkey -noout -in "${key_path}" >/dev/null 2>&1 || bt_die "Invalid private key file: ${key_path}"

    cert_pubkey_hash="$(
        openssl x509 -in "${cert_path}" -pubkey -noout \
            | openssl pkey -pubin -outform der 2>/dev/null \
            | openssl dgst -sha256 2>/dev/null \
            | awk '{print $NF}'
    )"
    key_pubkey_hash="$(
        openssl pkey -in "${key_path}" -pubout -outform der 2>/dev/null \
            | openssl dgst -sha256 2>/dev/null \
            | awk '{print $NF}'
    )"

    [[ -n "${cert_pubkey_hash}" && "${cert_pubkey_hash}" == "${key_pubkey_hash}" ]] || {
        bt_die "Certificate and key do not match: ${cert_path} ${key_path}"
    }
}

write_text_file() {
    local path="$1"
    local mode="${2:-0644}"
    local content="${3:-}"

    normalize_file_target_path "${path}"
    bt_write_file "${path}" "${content}"

    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        chmod "${mode}" "${path}"
    fi
}

atomic_copy_file() {
    local source="$1"
    local destination="$2"
    local mode="$3"
    local temp_file=""

    [[ -r "${source}" ]] || bt_die "Source file is missing or unreadable: ${source}"
    normalize_file_target_path "${destination}"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN install ${source} -> ${destination} (${mode})"
        return 0
    fi

    if [[ "${source}" == "${destination}" ]]; then
        chmod "${mode}" "${destination}"
        return 0
    fi

    mkdir -p "$(dirname "${destination}")"
    temp_file="$(mktemp "${destination}.tmp.XXXXXX")"
    cp "${source}" "${temp_file}"
    chmod "${mode}" "${temp_file}"
    mv -f "${temp_file}" "${destination}"
}

atomic_write_custom_fullchain() {
    local certificate="$1"
    local ca_bundle="$2"
    local destination="$3"
    local temp_file=""

    [[ -r "${certificate}" ]] || bt_die "Certificate file is missing or unreadable: ${certificate}"
    [[ -r "${ca_bundle}" ]] || bt_die "CA bundle file is missing or unreadable: ${ca_bundle}"

    require_command "openssl" "openssl"
    openssl x509 -noout -in "${certificate}" >/dev/null 2>&1 || bt_die "Invalid certificate file: ${certificate}"
    openssl x509 -noout -in "${ca_bundle}" >/dev/null 2>&1 || bt_die "Invalid CA bundle file: ${ca_bundle}"

    normalize_file_target_path "${destination}"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN concatenate ${certificate} + ${ca_bundle} -> ${destination} (0644)"
        return 0
    fi

    mkdir -p "$(dirname "${destination}")"
    temp_file="$(mktemp "${destination}.tmp.XXXXXX")"
    cat "${certificate}" "${ca_bundle}" > "${temp_file}"
    chmod 0644 "${temp_file}"
    mv -f "${temp_file}" "${destination}"
}

sync_archive_aliases() {
    local mode="$1"
    local cert_path="$2"
    local key_path="$3"

    atomic_copy_file "${cert_path}" "$(mode_fullchain_path "${mode}")" 0644
    atomic_copy_file "${key_path}" "$(mode_privkey_path "${mode}")" 0600
}

sync_active_aliases() {
    local cert_path="$1"
    local key_path="$2"

    atomic_copy_file "${cert_path}" "${BT_NGINX_SSL_ACTIVE_CERT_FILE}" 0644
    atomic_copy_file "${key_path}" "${BT_NGINX_SSL_ACTIVE_KEY_FILE}" 0600
    atomic_copy_file "${cert_path}" "${BT_NGINX_SSL_GENERIC_CERT_FILE}" 0644
    atomic_copy_file "${key_path}" "${BT_NGINX_SSL_GENERIC_KEY_FILE}" 0600
    atomic_copy_file "${cert_path}" "${BT_NGINX_SSL_FULLCHAIN_FILE}" 0644
    atomic_copy_file "${key_path}" "${BT_NGINX_SSL_PRIVKEY_FILE}" 0600
    atomic_copy_file "${cert_path}" "${BT_NGINX_SSL_COMPAT_CERT_FILE}" 0644
    atomic_copy_file "${key_path}" "${BT_NGINX_SSL_COMPAT_KEY_FILE}" 0600
}

run_external_hook() {
    local hook="$1"
    local mode="$2"
    local cert_path="$3"
    local key_path="$4"
    local resolved_hook=""

    [[ -n "${hook}" ]] || bt_die "No hook configured for ${mode} generation."

    if [[ "${hook}" == *"/"* ]]; then
        resolved_hook="$(repo_path "${hook}")"
    else
        resolved_hook="$(command -v "${hook}" 2>/dev/null || true)"
    fi

    [[ -n "${resolved_hook}" && -x "${resolved_hook}" ]] || bt_die "Hook for ${mode} is not executable: ${hook}"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN run ${mode} generation hook: ${resolved_hook}"
        return 0
    fi

    bt_log "Running ${mode} generation hook: ${resolved_hook}"
    SSL_MODE="${mode}" \
    SSL_CERT_DOMAIN="${SSL_CERT_DOMAIN}" \
    SSL_TARGET_CERT_PATH="${cert_path}" \
    SSL_TARGET_KEY_PATH="${key_path}" \
    CF_Token="${CF_Token:-}" \
    CF_Zone_ID="${CF_Zone_ID:-}" \
    "${resolved_hook}" "${cert_path}" "${key_path}" "${mode}"
}

is_ip_literal() {
    local candidate="${1:-}"
    [[ "${candidate}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ || "${candidate}" == *:* ]]
}

normalize_self_signed_alt_name() {
    local candidate="${1:-}"

    candidate="$(printf '%s' "${candidate}" | xargs 2>/dev/null || printf '%s' "${candidate}")"
    [[ -n "${candidate}" ]] || return 1

    case "${candidate}" in
        DNS:*|IP:*)
            printf '%s\n' "${candidate}"
            return 0
            ;;
    esac

    if is_ip_literal "${candidate}"; then
        printf 'IP:%s\n' "${candidate}"
        return 0
    fi

    printf 'DNS:%s\n' "${candidate}"
}

append_unique_self_signed_alt_name() {
    local -n entries_ref="$1"
    local raw_candidate="${2:-}"
    local normalized=""
    local existing=""

    normalized="$(normalize_self_signed_alt_name "${raw_candidate}")" || return 0

    for existing in "${entries_ref[@]}"; do
        if [[ "${existing}" == "${normalized}" ]]; then
            return 0
        fi
    done

    entries_ref+=("${normalized}")
}

build_self_signed_alt_names() {
    local extra="${SSL_SELF_SIGNED_ALT_NAMES:-${SSL_CERT_ALT_NAMES:-}}"
    local entries=()
    local extra_entries=()
    local candidate=""

    append_unique_self_signed_alt_name entries "${SSL_CERT_DOMAIN}"
    append_unique_self_signed_alt_name entries "localhost"
    append_unique_self_signed_alt_name entries "nginx"
    append_unique_self_signed_alt_name entries "127.0.0.1"

    if [[ -n "${extra}" ]]; then
        IFS=',' read -r -a extra_entries <<< "${extra}"
        for candidate in "${extra_entries[@]}"; do
            append_unique_self_signed_alt_name entries "${candidate}"
        done
    fi

    printf '%s' "${entries[*]}" | tr ' ' ','
}

provision_self_signed() {
    local cert_path key_path temp_dir temp_cert temp_key san_list

    cert_path="$(mode_cert_path "self-signed")"
    key_path="$(mode_key_path "self-signed")"

    if [[ -r "${cert_path}" && -r "${key_path}" ]]; then
        assert_valid_cert_material "${cert_path}" "${key_path}"
        PROVISIONED_CERT_PATH="${cert_path}"
        PROVISIONED_KEY_PATH="${key_path}"
        PROVISION_SOURCE="runtime-cache"
        PROVISION_CLIENT="openssl"
        PROVISION_CHALLENGE="local"
        sync_archive_aliases "self-signed" "${cert_path}" "${key_path}"
        return 0
    fi

    require_command "openssl" "openssl"
    san_list="$(build_self_signed_alt_names)"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN generate self-signed certificate for ${SSL_CERT_DOMAIN} with SANs ${san_list}"
        PROVISIONED_CERT_PATH="${cert_path}"
        PROVISIONED_KEY_PATH="${key_path}"
        PROVISION_SOURCE="generated"
        PROVISION_CLIENT="openssl"
        PROVISION_CHALLENGE="local"
        return 0
    fi

    temp_dir="$(mktemp -d)"
    temp_cert="${temp_dir}/self-signed.crt"
    temp_key="${temp_dir}/self-signed.key"

    openssl req -x509 -nodes -days "${SSL_SELF_SIGNED_DAYS}" -newkey rsa:2048 \
        -keyout "${temp_key}" \
        -out "${temp_cert}" \
        -subj "${SSL_SELF_SIGNED_SUBJECT}" \
        -addext "subjectAltName=${san_list}" >/dev/null 2>&1

    assert_valid_cert_material "${temp_cert}" "${temp_key}"
    atomic_copy_file "${temp_cert}" "${cert_path}" 0644
    atomic_copy_file "${temp_key}" "${key_path}" 0600
    sync_archive_aliases "self-signed" "${cert_path}" "${key_path}"

    PROVISIONED_CERT_PATH="${cert_path}"
    PROVISIONED_KEY_PATH="${key_path}"
    PROVISION_SOURCE="generated"
    PROVISION_CLIENT="openssl"
    PROVISION_CHALLENGE="local"

    rm -rf "${temp_dir}"
}

have_cloudflare_origin_sources() {
    [[ -r "${SSL_CLOUDFLARE_ORIGIN_CERT}" && -r "${SSL_CLOUDFLARE_ORIGIN_KEY}" ]]
}

validate_cloudflare_origin_prereqs() {
    if have_cloudflare_origin_sources; then
        return 0
    fi

    [[ -n "${SSL_CLOUDFLARE_ORIGIN_GENERATE_HOOK:-}" ]] || bt_die \
        "cloudflare-origin requires readable source files (${SSL_CLOUDFLARE_ORIGIN_CERT}, ${SSL_CLOUDFLARE_ORIGIN_KEY}) or SSL_CLOUDFLARE_ORIGIN_GENERATE_HOOK. If the hook uses Cloudflare APIs, export CF_Token and CF_Zone_ID."

    [[ -n "${CF_Token:-}" ]] || bt_die "CF_Token is required for cloudflare-origin generation hooks."
    [[ -n "${CF_Zone_ID:-}" ]] || bt_die "CF_Zone_ID is required for cloudflare-origin generation hooks."
}

provision_cloudflare_origin() {
    local cert_path key_path

    cert_path="$(mode_cert_path "cloudflare-origin")"
    key_path="$(mode_key_path "cloudflare-origin")"

    if have_cloudflare_origin_sources; then
        assert_valid_cert_material "${SSL_CLOUDFLARE_ORIGIN_CERT}" "${SSL_CLOUDFLARE_ORIGIN_KEY}"
        atomic_copy_file "${SSL_CLOUDFLARE_ORIGIN_CERT}" "${cert_path}" 0644
        atomic_copy_file "${SSL_CLOUDFLARE_ORIGIN_KEY}" "${key_path}" 0600
        sync_archive_aliases "cloudflare-origin" "${cert_path}" "${key_path}"
        PROVISION_SOURCE="source-copy"
        PROVISION_CLIENT="copy"
        PROVISION_CHALLENGE="cloudflare-origin"
    else
        run_external_hook "${SSL_CLOUDFLARE_ORIGIN_GENERATE_HOOK:-}" "cloudflare-origin" "${cert_path}" "${key_path}"
        if [[ "${BT_DRY_RUN}" != "1" ]]; then
            assert_valid_cert_material "${cert_path}" "${key_path}"
            sync_archive_aliases "cloudflare-origin" "${cert_path}" "${key_path}"
        fi
        PROVISION_SOURCE="generate-hook"
        PROVISION_CLIENT="hook"
        PROVISION_CHALLENGE="cloudflare-origin"
    fi

    PROVISIONED_CERT_PATH="${cert_path}"
    PROVISIONED_KEY_PATH="${key_path}"
}

have_letsencrypt_sources() {
    [[ -r "${SSL_LETSENCRYPT_CERT_PATH}" && -r "${SSL_LETSENCRYPT_KEY_PATH}" ]]
}

resolve_acme_email() {
    local value="${SSL_ACME_EMAIL:-${BT_CERTBOT_EMAIL:-}}"
    printf '%s\n' "${value}"
}

generate_letsencrypt_with_acme_sh() {
    local acme_bin="${SSL_ACME_SH_BIN:-acme.sh}"
    local cert_path="$1"
    local key_path="$2"
    local challenge="${SSL_LETSENCRYPT_CHALLENGE}"
    local -a issue_args
    local -a install_args

    require_command "${acme_bin}" "acme.sh"

    issue_args=("--issue" "-d" "${SSL_CERT_DOMAIN}")
    install_args=("--install-cert" "-d" "${SSL_CERT_DOMAIN}" "--fullchain-file" "${cert_path}" "--key-file" "${key_path}")

    if [[ "${SSL_ACME_STAGING:-0}" == "1" ]]; then
        issue_args+=("--staging")
    fi

    case "${challenge}" in
        dns-cloudflare)
            [[ -n "${CF_Token:-}" ]] || bt_die "CF_Token is required for letsencrypt dns-cloudflare with acme.sh."
            [[ -n "${CF_Zone_ID:-}" ]] || bt_die "CF_Zone_ID is required for letsencrypt dns-cloudflare with acme.sh."
            export CF_Token CF_Zone_ID
            issue_args+=("--dns" "dns_cf")
            ;;
        http-01)
            [[ -d "${SSL_ACME_WEBROOT}" ]] || bt_die "SSL_ACME_WEBROOT must exist for letsencrypt http-01: ${SSL_ACME_WEBROOT}"
            issue_args+=("-w" "${SSL_ACME_WEBROOT}")
            ;;
        *)
            bt_die "Unsupported SSL_LETSENCRYPT_CHALLENGE '${challenge}'. Expected dns-cloudflare or http-01"
            ;;
    esac

    bt_log "Issuing letsencrypt certificate via acme.sh (${challenge})"
    bt_run "${acme_bin}" "${issue_args[@]}"
    bt_run "${acme_bin}" "${install_args[@]}"
}

generate_letsencrypt_with_certbot() {
    local certbot_bin="${SSL_CERTBOT_BIN:-certbot}"
    local email credentials_file challenge
    local -a certbot_args

    require_command "${certbot_bin}" "certbot"

    email="$(resolve_acme_email)"
    [[ -n "${email}" ]] || bt_die "SSL_ACME_EMAIL or BT_CERTBOT_EMAIL is required for certbot flows."

    challenge="${SSL_LETSENCRYPT_CHALLENGE}"
    certbot_args=("certonly" "--non-interactive" "--agree-tos" "--email" "${email}" "-d" "${SSL_CERT_DOMAIN}")

    case "${challenge}" in
        dns-cloudflare)
            credentials_file="${SSL_CERTBOT_CREDENTIALS_FILE:-${CERTBOT_CREDENTIALS_FILE:-}}"
            [[ -n "${credentials_file}" ]] || bt_die "SSL_CERTBOT_CREDENTIALS_FILE is required for certbot dns-cloudflare flows."
            credentials_file="$(repo_path "${credentials_file}")"
            [[ -r "${credentials_file}" ]] || bt_die "Certbot Cloudflare credentials file is unreadable: ${credentials_file}"
            certbot_args+=("--dns-cloudflare" "--dns-cloudflare-credentials" "${credentials_file}")
            ;;
        http-01)
            [[ -d "${SSL_ACME_WEBROOT}" ]] || bt_die "SSL_ACME_WEBROOT must exist for letsencrypt http-01: ${SSL_ACME_WEBROOT}"
            certbot_args+=("--webroot" "-w" "${SSL_ACME_WEBROOT}")
            ;;
        *)
            bt_die "Unsupported SSL_LETSENCRYPT_CHALLENGE '${challenge}'. Expected dns-cloudflare or http-01"
            ;;
    esac

    bt_log "Issuing letsencrypt certificate via certbot (${challenge})"
    bt_run "${certbot_bin}" "${certbot_args[@]}"
}

validate_letsencrypt_prereqs() {
    local challenge="${SSL_LETSENCRYPT_CHALLENGE}"

    if have_letsencrypt_sources; then
        return 0
    fi

    if [[ -n "${SSL_LETSENCRYPT_GENERATE_HOOK:-}" ]]; then
        return 0
    fi

    case "${SSL_ACME_CLIENT}" in
        acme.sh)
            require_command "${SSL_ACME_SH_BIN:-acme.sh}" "acme.sh"
            ;;
        certbot)
            require_command "${SSL_CERTBOT_BIN:-certbot}" "certbot"
            ;;
        *)
            bt_die "Unsupported SSL_ACME_CLIENT '${SSL_ACME_CLIENT}'. Expected acme.sh or certbot"
            ;;
    esac

    case "${challenge}" in
        dns-cloudflare)
            [[ -n "${CF_Token:-}" ]] || bt_die "CF_Token is required for letsencrypt dns-cloudflare flows."
            [[ -n "${CF_Zone_ID:-}" ]] || bt_die "CF_Zone_ID is required for letsencrypt dns-cloudflare flows."
            ;;
        http-01)
            [[ -d "${SSL_ACME_WEBROOT}" ]] || bt_die "SSL_ACME_WEBROOT must exist for letsencrypt http-01: ${SSL_ACME_WEBROOT}"
            ;;
        *)
            bt_die "Unsupported SSL_LETSENCRYPT_CHALLENGE '${challenge}'. Expected dns-cloudflare or http-01"
            ;;
    esac
}

provision_letsencrypt() {
    local cert_path key_path

    cert_path="$(mode_cert_path "letsencrypt")"
    key_path="$(mode_key_path "letsencrypt")"

    if have_letsencrypt_sources; then
        assert_valid_cert_material "${SSL_LETSENCRYPT_CERT_PATH}" "${SSL_LETSENCRYPT_KEY_PATH}"
        atomic_copy_file "${SSL_LETSENCRYPT_CERT_PATH}" "${cert_path}" 0644
        atomic_copy_file "${SSL_LETSENCRYPT_KEY_PATH}" "${key_path}" 0600
        sync_archive_aliases "letsencrypt" "${cert_path}" "${key_path}"
        PROVISION_SOURCE="source-copy"
        PROVISION_CLIENT="copy"
        PROVISION_CHALLENGE="${SSL_LETSENCRYPT_CHALLENGE}"
    elif [[ -n "${SSL_LETSENCRYPT_GENERATE_HOOK:-}" ]]; then
        run_external_hook "${SSL_LETSENCRYPT_GENERATE_HOOK}" "letsencrypt" "${cert_path}" "${key_path}"
        if [[ "${BT_DRY_RUN}" != "1" ]]; then
            assert_valid_cert_material "${cert_path}" "${key_path}"
            sync_archive_aliases "letsencrypt" "${cert_path}" "${key_path}"
        fi
        PROVISION_SOURCE="generate-hook"
        PROVISION_CLIENT="hook"
        PROVISION_CHALLENGE="${SSL_LETSENCRYPT_CHALLENGE}"
    else
        case "${SSL_ACME_CLIENT}" in
            acme.sh)
                generate_letsencrypt_with_acme_sh "${cert_path}" "${key_path}"
                ;;
            certbot)
                generate_letsencrypt_with_certbot
                assert_valid_cert_material "${SSL_LETSENCRYPT_CERT_PATH}" "${SSL_LETSENCRYPT_KEY_PATH}"
                atomic_copy_file "${SSL_LETSENCRYPT_CERT_PATH}" "${cert_path}" 0644
                atomic_copy_file "${SSL_LETSENCRYPT_KEY_PATH}" "${key_path}" 0600
                ;;
            *)
                bt_die "Unsupported SSL_ACME_CLIENT '${SSL_ACME_CLIENT}'. Expected acme.sh or certbot"
                ;;
        esac

        if [[ "${BT_DRY_RUN}" != "1" ]]; then
            assert_valid_cert_material "${cert_path}" "${key_path}"
            sync_archive_aliases "letsencrypt" "${cert_path}" "${key_path}"
        fi
        PROVISION_SOURCE="generated"
        PROVISION_CLIENT="${SSL_ACME_CLIENT}"
        PROVISION_CHALLENGE="${SSL_LETSENCRYPT_CHALLENGE}"
    fi

    PROVISIONED_CERT_PATH="${cert_path}"
    PROVISIONED_KEY_PATH="${key_path}"
}

validate_custom_prereqs() {
    [[ -n "${SSL_CUSTOM_CERT_PATH}" ]] || bt_die "SSL_CUSTOM_CERT_PATH is required for custom SSL mode."
    [[ -n "${SSL_CUSTOM_KEY_PATH}" ]] || bt_die "SSL_CUSTOM_KEY_PATH is required for custom SSL mode."
    if [[ -n "${SSL_CUSTOM_CA_BUNDLE_PATH}" ]]; then
        [[ -r "${SSL_CUSTOM_CA_BUNDLE_PATH}" ]] || bt_die "SSL_CUSTOM_CA_BUNDLE_PATH is missing or unreadable: ${SSL_CUSTOM_CA_BUNDLE_PATH}"
        openssl x509 -noout -in "${SSL_CUSTOM_CA_BUNDLE_PATH}" >/dev/null 2>&1 || bt_die "Invalid custom CA bundle file: ${SSL_CUSTOM_CA_BUNDLE_PATH}"
    fi
    assert_valid_cert_material "${SSL_CUSTOM_CERT_PATH}" "${SSL_CUSTOM_KEY_PATH}"
}

provision_custom() {
    local cert_path key_path

    cert_path="$(mode_cert_path "custom")"
    key_path="$(mode_key_path "custom")"

    assert_valid_cert_material "${SSL_CUSTOM_CERT_PATH}" "${SSL_CUSTOM_KEY_PATH}"
    if [[ -n "${SSL_CUSTOM_CA_BUNDLE_PATH}" ]]; then
        atomic_write_custom_fullchain "${SSL_CUSTOM_CERT_PATH}" "${SSL_CUSTOM_CA_BUNDLE_PATH}" "${cert_path}"
    else
        atomic_copy_file "${SSL_CUSTOM_CERT_PATH}" "${cert_path}" 0644
    fi
    atomic_copy_file "${SSL_CUSTOM_KEY_PATH}" "${key_path}" 0600
    assert_valid_cert_material "${cert_path}" "${key_path}"
    sync_archive_aliases "custom" "${cert_path}" "${key_path}"

    PROVISIONED_CERT_PATH="${cert_path}"
    PROVISIONED_KEY_PATH="${key_path}"
    PROVISION_SOURCE="source-copy"
    PROVISION_CLIENT="custom"
    PROVISION_CHALLENGE="external"
}

validate_target_mode_prereqs() {
    case "${TARGET_MODE}" in
        self-signed)
            validate_domain
            require_command "openssl" "openssl"
            ;;
        cloudflare-origin)
            validate_domain
            validate_cloudflare_origin_prereqs
            ;;
        letsencrypt)
            validate_domain
            validate_letsencrypt_prereqs
            ;;
        custom)
            validate_domain
            validate_custom_prereqs
            ;;
        *)
            bt_die "Unsupported SSL mode '${TARGET_MODE}'."
            ;;
    esac
}

provision_target_mode() {
    case "${TARGET_MODE}" in
        self-signed)
            provision_self_signed
            ;;
        cloudflare-origin)
            provision_cloudflare_origin
            ;;
        letsencrypt)
            provision_letsencrypt
            ;;
        custom)
            provision_custom
            ;;
        *)
            bt_die "Unsupported SSL mode '${TARGET_MODE}'."
            ;;
    esac

    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        assert_valid_cert_material "${PROVISIONED_CERT_PATH}" "${PROVISIONED_KEY_PATH}"
    fi
}

write_mode_source_env() {
    local mode="$1"
    local output_path

    output_path="$(mode_source_env_path "${mode}")"
    write_text_file "${output_path}" 0644 "$(cat <<EOF
SSL_MODE=${mode}
SSL_CERT_DOMAIN=${SSL_CERT_DOMAIN}
SSL_PROVISION_SOURCE=${PROVISION_SOURCE}
SSL_PROVISION_CLIENT=${PROVISION_CLIENT}
SSL_PROVISION_CHALLENGE=${PROVISION_CHALLENGE}
SSL_PROVISION_CERT_PATH=${PROVISIONED_CERT_PATH}
SSL_PROVISION_KEY_PATH=${PROVISIONED_KEY_PATH}
EOF
)"
}

write_runtime_state() {
    local mode="$1"
    local renew_cron_file=""

    renew_cron_file="${BT_NGINX_SSL_RENEW_CRON_FILE}"

    write_text_file "${BT_NGINX_SSL_MODE_FILE}" 0644 "${mode}"$'\n'
    write_text_file "${BT_NGINX_SSL_MODE_ENV_FILE}" 0644 "$(cat <<EOF
export SSL_MODE=${mode}
export SSL_CERT_DOMAIN=${SSL_CERT_DOMAIN}
export SSL_ARCHIVE_CERT_PATH=${PROVISIONED_CERT_PATH}
export SSL_ARCHIVE_KEY_PATH=${PROVISIONED_KEY_PATH}
export SSL_ACTIVE_CERT_PATH=${BT_NGINX_SSL_ACTIVE_CERT_FILE}
export SSL_ACTIVE_KEY_PATH=${BT_NGINX_SSL_ACTIVE_KEY_FILE}
export SSL_NGINX_CERT_PATH=/etc/nginx/ssl/active.crt
export SSL_NGINX_KEY_PATH=/etc/nginx/ssl/active.key
export SSL_NGINX_COMPAT_CERT_PATH=/etc/nginx/ssl/selfsigned.crt
export SSL_NGINX_COMPAT_KEY_PATH=/etc/nginx/ssl/selfsigned.key
export SSL_NGINX_FULLCHAIN_PATH=/etc/nginx/ssl/fullchain.pem
export SSL_NGINX_PRIVKEY_PATH=/etc/nginx/ssl/privkey.pem
export SSL_RENEW_CRON_FILE=${renew_cron_file}
EOF
)"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN write ${BT_NGINX_SSL_MODE_JSON_FILE}"
        return 0
    fi

    python3 - "${BT_NGINX_SSL_MODE_JSON_FILE}" "${mode}" "${SSL_CERT_DOMAIN}" \
        "${PROVISIONED_CERT_PATH}" "${PROVISIONED_KEY_PATH}" \
        "${BT_NGINX_SSL_ACTIVE_CERT_FILE}" "${BT_NGINX_SSL_ACTIVE_KEY_FILE}" \
        "${BT_NGINX_SSL_COMPAT_CERT_FILE}" "${BT_NGINX_SSL_COMPAT_KEY_FILE}" \
        "${PROVISION_SOURCE}" "${PROVISION_CLIENT}" "${PROVISION_CHALLENGE}" \
        "${renew_cron_file}" "$(bt_now_utc)" <<'PY'
import json
import sys

(
    output_path,
    mode,
    domain,
    archive_cert,
    archive_key,
    active_cert,
    active_key,
    compat_cert,
    compat_key,
    source,
    client,
    challenge,
    renew_cron,
    updated_at,
) = sys.argv[1:]

payload = {
    "mode": mode,
    "domain": domain,
    "archive": {
        "cert": archive_cert,
        "key": archive_key,
    },
    "active": {
        "cert": active_cert,
        "key": active_key,
    },
    "compat": {
        "cert": compat_cert,
        "key": compat_key,
    },
    "provision": {
        "source": source,
        "client": client,
        "challenge": challenge,
    },
    "renew_cron_file": renew_cron,
    "updated_at": updated_at,
}

with open(output_path, "w", encoding="utf-8") as handle:
    json.dump(payload, handle, indent=2)
    handle.write("\n")
PY

    chmod 0644 "${BT_NGINX_SSL_MODE_JSON_FILE}"
}

configure_renew_cron() {
    local mode="$1"
    local helper_body cron_body renewal_comment cron_entry
    local marker="# jobs-borads ssl-switch ${mode}"

    if [[ "${mode}" == "self-signed" ]]; then
        renewal_comment="# self-signed mode does not require automated renewal"
        cron_entry="# 17 3 * * * '${BT_NGINX_SSL_RENEW_HELPER}' >> '${BT_NGINX_SSL_STATE_DIR}/renew-nginx-ssl.log' 2>&1"
    else
        renewal_comment="# install manually if you want ${mode} refresh on a schedule"
        cron_entry="17 3 * * * '${BT_NGINX_SSL_RENEW_HELPER}' >> '${BT_NGINX_SSL_STATE_DIR}/renew-nginx-ssl.log' 2>&1"
    fi

    helper_body="$(cat <<EOF
#!/usr/bin/env bash
set -euo pipefail

export BT_STATE_DIR='${BT_STATE_DIR}'
export BT_RUNTIME_DIR='${BT_RUNTIME_DIR}'
export SSL_CERT_DOMAIN='${SSL_CERT_DOMAIN}'
export SSL_CUSTOM_CERT_PATH='${SSL_CUSTOM_CERT_PATH}'
export SSL_CUSTOM_CA_BUNDLE_PATH='${SSL_CUSTOM_CA_BUNDLE_PATH}'
export SSL_CUSTOM_KEY_PATH='${SSL_CUSTOM_KEY_PATH}'
export SSL_ACME_CLIENT='${SSL_ACME_CLIENT}'
export SSL_LETSENCRYPT_CHALLENGE='${SSL_LETSENCRYPT_CHALLENGE}'
exec '${REPO_ROOT}/ops/bootstrap/bootstrap-nginx-ssl.sh' switch '${mode}'
EOF
)"
    write_text_file "${BT_NGINX_SSL_RENEW_HELPER}" 0755 "${helper_body}"

    cron_body="$(cat <<EOF
${renewal_comment}
# Generated by ops/bootstrap/bootstrap-nginx-ssl.sh
# Tooling references: acme.sh, certbot, CF_Token, CF_Zone_ID
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
${cron_entry} ${marker}
EOF
)"
    write_text_file "${BT_NGINX_SSL_RENEW_CRON_FILE}" 0644 "${cron_body}"

    if ! command -v crontab >/dev/null 2>&1; then
        bt_warn "crontab is not available; wrote ${BT_NGINX_SSL_RENEW_CRON_FILE} but did not install it."
        return 0
    fi

    if [[ "${mode}" == "custom" ]]; then
        bt_log "custom SSL mode uses externally managed certificate material; wrote ${BT_NGINX_SSL_RENEW_CRON_FILE} but did not install a cron entry."
        return 0
    fi

    {
        crontab -l 2>/dev/null | grep -Fv '# jobs-borads ssl-switch ' || true
        if [[ "${mode}" != "self-signed" ]]; then
            tail -n 1 "${BT_NGINX_SSL_RENEW_CRON_FILE}"
        fi
    } | crontab -
}

reload_nginx_if_running() {
    local container_name="${BT_NGINX_CONTAINER_NAME}"
    local container_status=""
    local nginx_bin="/usr/local/openresty/bin/openresty"

    if [[ "${BT_DRY_RUN}" == "1" ]]; then
        bt_log "DRY-RUN reload nginx if container ${container_name} is running"
        return 0
    fi

    if ! command -v docker >/dev/null 2>&1; then
        bt_warn "docker is not available; skipping nginx reload."
        return 0
    fi

    container_status="$(docker inspect -f '{{.State.Status}}' "${container_name}" 2>/dev/null || true)"
    if [[ "${container_status}" != "running" ]]; then
        bt_log "nginx container ${container_name} is not running; runtime state is ready for the next start."
        return 0
    fi

    if ! docker exec "${container_name}" "${nginx_bin}" -t -c /etc/nginx/nginx.conf >/dev/null 2>&1; then
        bt_warn "nginx -t failed in ${container_name}; runtime files were updated but reload was skipped."
        return 0
    fi

    bt_log "Reloading nginx container ${container_name}"
    docker exec "${container_name}" "${nginx_bin}" -s reload -c /etc/nginx/nginx.conf >/dev/null 2>&1 || {
        bt_warn "nginx reload failed in ${container_name}; runtime files were updated but the running container still needs attention."
        return 0
    }

    return 0
}

activate_target_mode() {
    write_mode_source_env "${TARGET_MODE}"

    if [[ "${BT_DRY_RUN}" != "1" ]]; then
        sync_active_aliases "${PROVISIONED_CERT_PATH}" "${PROVISIONED_KEY_PATH}"
        render_ssl_mode_conf "${TARGET_MODE}"
    fi

    configure_renew_cron "${TARGET_MODE}"
    write_runtime_state "${TARGET_MODE}"
}

apply_mode() {
    CURRENT_MODE="$(current_mode)"

    ensure_runtime_layout
    validate_target_mode_prereqs
    provision_target_mode
    activate_target_mode
    reload_nginx_if_running

    bt_log "nginx SSL runtime prepared for mode ${TARGET_MODE}"
}

prepare_action() {
    local input_mode="${ACTION_MODE_INPUT:-${SSL_MODE:-}}"

    if [[ -z "${input_mode}" ]]; then
        input_mode="$(current_mode)"
    fi

    TARGET_MODE="$(normalize_mode "${input_mode:-self-signed}")"
    apply_mode
}

switch_action() {
    local input_mode="${ACTION_MODE_INPUT:-${SSL_MODE:-}}"

    [[ -n "${input_mode}" ]] || bt_die "switch requires a target mode: self-signed, cloudflare-origin, or letsencrypt"
    TARGET_MODE="$(normalize_mode "${input_mode}")"
    apply_mode
}

status_action() {
    local mode=""

    mode="$(current_mode)"
    if [[ -z "${mode}" ]]; then
        printf 'mode=uninitialized\n'
        printf 'runtime_dir=%s\n' "${BT_NGINX_SSL_RUNTIME_DIR}"
        exit 0
    fi

    printf 'mode=%s\n' "${mode}"
    printf 'domain=%s\n' "${SSL_CERT_DOMAIN}"
    printf 'runtime_dir=%s\n' "${BT_NGINX_SSL_RUNTIME_DIR}"
    printf 'active_cert=%s\n' "${BT_NGINX_SSL_ACTIVE_CERT_FILE}"
    printf 'active_key=%s\n' "${BT_NGINX_SSL_ACTIVE_KEY_FILE}"
}

main() {
    case "${ACTION}" in
        prepare)
            prepare_action
            ;;
        switch)
            switch_action
            ;;
        status)
            status_action
            ;;
        -h|--help|help)
            usage
            ;;
        *)
            usage >&2
            bt_die "Unsupported action '${ACTION}'. Expected prepare, switch, or status."
            ;;
    esac
}

main "$@"
