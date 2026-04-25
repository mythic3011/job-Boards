#!/usr/bin/env bash

deploy_require_value() {
    local key="$1"
    local value="${!key:-}"
    [[ -n "${value}" ]] || {
        printf 'ERROR: missing required deploy target input: %s\n' "${key}" >&2
        return 1
    }
}

resolve_target_monitoring_access_mode() {
    local profile_name="${TARGET_PROFILE_NAME:-from-env}"

    if [[ -n "${TARGET_MONITORING_ACCESS_MODE:-}" ]]; then
        printf '%s\n' "${TARGET_MONITORING_ACCESS_MODE}"
        return 0
    fi

    case "${profile_name}" in
        jb.mythic3011.com)
            printf '%s\n' "auth-only"
            ;;
        *)
            printf '%s\n' "internal-only"
            ;;
    esac
}

resolve_target_monitoring_allowed_cidrs() {
    if [[ -n "${TARGET_MONITORING_ALLOWED_CIDRS:-}" ]]; then
        printf '%s\n' "${TARGET_MONITORING_ALLOWED_CIDRS}"
        return 0
    fi

    printf '%s\n' "127.0.0.1/32,192.168.0.0/16"
}

resolve_target_domain() {
    if [[ -n "${TARGET_DOMAIN:-}" ]]; then
        printf '%s\n' "${TARGET_DOMAIN}"
        return 0
    fi

    if [[ -n "${TARGET_SUBDOMAIN:-}" && -n "${TARGET_ROOT_DOMAIN:-}" ]]; then
        printf '%s.%s\n' "${TARGET_SUBDOMAIN}" "${TARGET_ROOT_DOMAIN}"
        return 0
    fi

    printf 'ERROR: missing deploy domain input: set TARGET_DOMAIN or TARGET_SUBDOMAIN + TARGET_ROOT_DOMAIN\n' >&2
    return 1
}

target_is_ip_literal() {
    local candidate="${1:-}"
    [[ "${candidate}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ || "${candidate}" == *:* ]]
}

target_append_unique_csv_item() {
    local -n items_ref="$1"
    local candidate="${2:-}"
    local existing=""

    candidate="$(printf '%s' "${candidate}" | xargs 2>/dev/null || printf '%s' "${candidate}")"
    [[ -n "${candidate}" ]] || return 0

    for existing in "${items_ref[@]}"; do
        if [[ "${existing}" == "${candidate}" ]]; then
            return 0
        fi
    done

    items_ref+=("${candidate}")
}

target_append_unique_domain_csv_item() {
    local -n items_ref="$1"
    local candidate="${2:-}"
    local existing=""

    candidate="$(printf '%s' "${candidate}" | xargs 2>/dev/null || printf '%s' "${candidate}")"
    [[ -n "${candidate}" ]] || return 0
    target_is_ip_literal "${candidate}" && return 0

    for existing in "${items_ref[@]}"; do
        if [[ "${existing}" == "${candidate}" ]]; then
            return 0
        fi
    done

    items_ref+=("${candidate}")
}

target_join_csv_items() {
    local -a items=("$@")
    local joined=""
    local item=""

    for item in "${items[@]}"; do
        [[ -n "${item}" ]] || continue
        if [[ -n "${joined}" ]]; then
            joined+=","
        fi
        joined+="${item}"
    done

    printf '%s\n' "${joined}"
}

target_join_space_items() {
    local -a items=("$@")
    local joined=""
    local item=""

    for item in "${items[@]}"; do
        [[ -n "${item}" ]] || continue
        if [[ -n "${joined}" ]]; then
            joined+=" "
        fi
        joined+="${item}"
    done

    printf '%s\n' "${joined}"
}

build_lab_deploy_server_names() {
    local -a items=()
    local lan_host=""
    local extra=""
    local candidate=""

    target_append_unique_csv_item items "${DEPLOY_DOMAIN:-}"
    target_append_unique_csv_item items "${LAB_DEPLOY_PUBLIC_HOST:-}"
    target_append_unique_csv_item items "${DEPLOY_HOST:-}"

    lan_host="${LAB_LAN_ADDRESS%%/*}"
    target_append_unique_csv_item items "${lan_host}"

    IFS=',' read -r -a extra <<< "${LAB_DEPLOY_EXTRA_HOSTS:-}"
    for candidate in "${extra[@]:-}"; do
        target_append_unique_csv_item items "${candidate}"
    done

    target_join_space_items "${items[@]}"
}

build_lab_deploy_self_signed_alt_names() {
    local -a items=()
    local lan_host=""
    local extra=""
    local candidate=""

    target_append_unique_csv_item items "${DEPLOY_DOMAIN:-}"
    target_append_unique_csv_item items "${LAB_DEPLOY_PUBLIC_HOST:-}"
    target_append_unique_csv_item items "${DEPLOY_HOST:-}"

    lan_host="${LAB_LAN_ADDRESS%%/*}"
    target_append_unique_csv_item items "${lan_host}"
    target_append_unique_csv_item items "localhost"
    target_append_unique_csv_item items "127.0.0.1"

    IFS=',' read -r -a extra <<< "${LAB_DEPLOY_EXTRA_HOSTS:-}"
    for candidate in "${extra[@]:-}"; do
        target_append_unique_csv_item items "${candidate}"
    done

    target_join_csv_items "${items[@]}"
}

build_lab_deploy_cert_alt_names() {
    local -a items=()
    local extra=""
    local candidate=""

    target_append_unique_domain_csv_item items "${DEPLOY_DOMAIN:-}"
    target_append_unique_domain_csv_item items "${LAB_DEPLOY_PUBLIC_HOST:-}"
    target_append_unique_domain_csv_item items "${DEPLOY_HOST:-}"

    IFS=',' read -r -a extra <<< "${LAB_DEPLOY_EXTRA_HOSTS:-}"
    for candidate in "${extra[@]:-}"; do
        target_append_unique_domain_csv_item items "${candidate}"
    done

    target_join_csv_items "${items[@]}"
}

deploy_expand_path_template() {
    local template="$1"
    local domain="$2"

    DOMAIN_PLACEHOLDER_VALUE="${domain}" python3 - "${template}" <<'PY'
import os
import sys

template = sys.argv[1]
domain = os.environ["DOMAIN_PLACEHOLDER_VALUE"]
sys.stdout.write(template.replace("{domain}", domain))
PY
}

build_reverse_proxy_tls_paths() {
    local tls_mode="$1"
    local cert_domain="$2"
    local cert_template
    local key_template
    local default_cert_template
    local default_key_template

    case "${tls_mode}" in
        letsencrypt)
            default_cert_template='/etc/letsencrypt/live/{domain}/fullchain.pem'
            default_key_template='/etc/letsencrypt/live/{domain}/privkey.pem'
            ;;
        cloudflare-origin|custom)
            default_cert_template='/etc/nginx/cert/{domain}/cert.pem'
            default_key_template='/etc/nginx/cert/{domain}/key.pem'
            ;;
        *)
            printf 'ERROR: unsupported target TLS mode: %s\n' "${tls_mode}" >&2
            return 1
            ;;
    esac

    cert_template="${TARGET_NGINX_CERT_PATH_TEMPLATE:-${default_cert_template}}"
    key_template="${TARGET_NGINX_KEY_PATH_TEMPLATE:-${default_key_template}}"
    DEPLOY_NGINX_CERT_PATH="${TARGET_NGINX_CERT_PATH:-$(deploy_expand_path_template "${cert_template}" "${cert_domain}")}"
    DEPLOY_NGINX_KEY_PATH="${TARGET_NGINX_KEY_PATH:-$(deploy_expand_path_template "${key_template}" "${cert_domain}")}"
}

build_reverse_proxy_target() {
    deploy_require_value TARGET_HOST || return 1
    deploy_require_value TARGET_REMOTE_ROOT || return 1
    deploy_require_value TARGET_COMPOSE_PROJECT_NAME || return 1

    # shellcheck disable=SC2034
    # These DEPLOY_* variables are emitted as the sourced target contract for ops/deploy/vps-deploy.sh.
    DEPLOY_PROFILE_NAME="${TARGET_PROFILE_NAME:-from-env}"
    DEPLOY_PROFILE_KIND="${TARGET_PROFILE_KIND:-reverse-proxy}"
    DEPLOY_DOMAIN="$(resolve_target_domain)" || return 1
    DEPLOY_HOST="${TARGET_HOST}"
    DEPLOY_SSH_PORT="${TARGET_SSH_PORT:-22}"
    DEPLOY_SSH_USER="${TARGET_SSH_USER:-root}"
    DEPLOY_REMOTE_ROOT="${TARGET_REMOTE_ROOT}"
    DEPLOY_APP_PORT="${TARGET_APP_PORT:-127.0.0.1:18080}"
    DEPLOY_APP_SSL_PORT="${TARGET_APP_SSL_PORT:-127.0.0.1:18443}"
    DEPLOY_APP_URL="${TARGET_APP_URL:-https://${DEPLOY_DOMAIN}}"
    DEPLOY_ASSET_URL="${TARGET_ASSET_URL:-${DEPLOY_APP_URL}}"
    DEPLOY_COMPOSE_PROJECT_NAME="${TARGET_COMPOSE_PROJECT_NAME}"
    DEPLOY_BT_STATE_DIR="${TARGET_BT_STATE_DIR:-${DEPLOY_REMOTE_ROOT}/state}"
    DEPLOY_SKIP_HOST_PORT_EXPOSURE_CHECK="${TARGET_SKIP_HOST_PORT_EXPOSURE_CHECK:-false}"
    DEPLOY_NGINX_SITE_NAME="${TARGET_NGINX_SITE_NAME:-${DEPLOY_DOMAIN}.conf}"
    TARGET_TLS_MODE="${TARGET_TLS_MODE:-cloudflare-origin}"
    DEPLOY_NGINX_CERT_DOMAIN="${TARGET_NGINX_CERT_DOMAIN:-${TARGET_ROOT_DOMAIN:-${DEPLOY_DOMAIN}}}"
    DEPLOY_NGINX_CERT_DIR="${TARGET_NGINX_CERT_DIR:-/etc/nginx/cert/${DEPLOY_NGINX_CERT_DOMAIN}}"

    DEPLOY_NGINX_PROXY_PASS="${TARGET_NGINX_PROXY_PASS:-https://127.0.0.1:${DEPLOY_APP_SSL_PORT##*:}/}"
    DEPLOY_SERVER_NAMES="${TARGET_SERVER_NAMES:-${DEPLOY_DOMAIN}}"
    DEPLOY_DB_DATABASE="${TARGET_DB_DATABASE:-jobs_boards}"
    DEPLOY_DB_USERNAME="${TARGET_DB_USERNAME:-jobs_boards}"
    DEPLOY_MONITORING_ADMIN_USERNAME="${TARGET_MONITORING_ADMIN_USERNAME:-admin}"
    DEPLOY_MONITORING_ACCESS_MODE="$(resolve_target_monitoring_access_mode)" || return 1
    DEPLOY_MONITORING_ALLOWED_CIDRS="$(resolve_target_monitoring_allowed_cidrs)" || return 1
    DEPLOY_TIMEZONE="${TARGET_TIMEZONE:-Asia/Hong_Kong}"
    DEPLOY_INSTALL_HOST_NGINX="true"

    build_reverse_proxy_tls_paths "${TARGET_TLS_MODE}" "${DEPLOY_NGINX_CERT_DOMAIN}" || return 1
}
