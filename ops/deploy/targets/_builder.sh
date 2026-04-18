#!/usr/bin/env bash

deploy_require_value() {
    local key="$1"
    local value="${!key:-}"
    [[ -n "${value}" ]] || {
        printf 'ERROR: missing required deploy target input: %s\n' "${key}" >&2
        return 1
    }
}

build_reverse_proxy_target() {
    deploy_require_value TARGET_DOMAIN || return 1
    deploy_require_value TARGET_HOST || return 1
    deploy_require_value TARGET_REMOTE_ROOT || return 1
    deploy_require_value TARGET_COMPOSE_PROJECT_NAME || return 1

    DEPLOY_DOMAIN="${TARGET_DOMAIN}"
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
    DEPLOY_NGINX_CERT_DIR="${TARGET_NGINX_CERT_DIR:-/etc/nginx/cert/${DEPLOY_DOMAIN}}"

    if [[ "${TARGET_TLS_MODE}" == "letsencrypt" ]]; then
        DEPLOY_NGINX_CERT_PATH="${TARGET_NGINX_CERT_PATH:-/etc/letsencrypt/live/${DEPLOY_DOMAIN}/fullchain.pem}"
        DEPLOY_NGINX_KEY_PATH="${TARGET_NGINX_KEY_PATH:-/etc/letsencrypt/live/${DEPLOY_DOMAIN}/privkey.pem}"
    else
        DEPLOY_NGINX_CERT_PATH="${TARGET_NGINX_CERT_PATH:-${DEPLOY_NGINX_CERT_DIR}/cert.pem}"
        DEPLOY_NGINX_KEY_PATH="${TARGET_NGINX_KEY_PATH:-${DEPLOY_NGINX_CERT_DIR}/key.pem}"
    fi

    DEPLOY_NGINX_PROXY_PASS="${TARGET_NGINX_PROXY_PASS:-https://127.0.0.1:${DEPLOY_APP_SSL_PORT##*:}/}"
    DEPLOY_DB_DATABASE="${TARGET_DB_DATABASE:-jobs_boards}"
    DEPLOY_DB_USERNAME="${TARGET_DB_USERNAME:-jobs_boards}"
    DEPLOY_MONITORING_ADMIN_USERNAME="${TARGET_MONITORING_ADMIN_USERNAME:-admin}"
    DEPLOY_TIMEZONE="${TARGET_TIMEZONE:-Asia/Hong_Kong}"
    DEPLOY_INSTALL_HOST_NGINX="true"
}
