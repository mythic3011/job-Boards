#!/bin/sh
set -eu

SSL_MODE="${SSL_MODE:-self-signed}"
SSL_CERT_DOMAIN="${SSL_CERT_DOMAIN:-localhost}"
SSL_CERT_PATH="${SSL_CERT_PATH:-}"
SSL_KEY_PATH="${SSL_KEY_PATH:-}"
SSL_MODE_TEMPLATE="/etc/nginx/templates/ssl-mode.conf.tpl"
SSL_MODE_TEMPLATE_REPO_FALLBACK="/var/www/html/docker/nginx/templates/ssl-mode.conf.tpl"
SSL_MODE_TEMPLATE_EMBEDDED_FALLBACK="/tmp/ssl-mode.conf.tpl"
SSL_GENERATED_DIR="/etc/nginx/generated"
SSL_MODE_CONF="${SSL_GENERATED_DIR}/ssl-mode.conf"
SSL_RUNTIME_DIR="/etc/nginx/ssl"
OPENRESTY_BIN="/usr/local/openresty/bin/openresty"
SELF_SIGNED_CERT="${SSL_RUNTIME_DIR}/selfsigned.crt"
SELF_SIGNED_KEY="${SSL_RUNTIME_DIR}/selfsigned.key"
CLOUDFLARE_ORIGIN_CERT_DIR="${SSL_RUNTIME_DIR}/cloudflare-origin"
LETSENCRYPT_CERT_DIR="${SSL_RUNTIME_DIR}/letsencrypt"
ACTIVE_SSL_MODE=""
ACTIVE_SSL_MODE_TEMPLATE=""
RESOLVED_SSL_CERT_PATH=""
RESOLVED_SSL_KEY_PATH=""
TAIL_PID=""

MONITORING_GENERATED_DIR="${SSL_GENERATED_DIR}"
MONITORING_GEO_CONF="${MONITORING_GENERATED_DIR}/monitoring-geo.conf"
MONITORING_ACCESS_CONF="${MONITORING_GENERATED_DIR}/monitoring-access.conf"
MONITORING_SERVER_ACCESS_CONF="${MONITORING_GENERATED_DIR}/monitoring-server-access.conf"
MONITORING_ACCESS_MODE="${MONITORING_ACCESS_MODE:-internal-only}"
MONITORING_ALLOWED_CIDRS="${MONITORING_ALLOWED_CIDRS:-127.0.0.1/32,192.168.0.0/16}"
PRIVATE_NETWORK_ALLOW_CONF="/etc/nginx/includes/private-network-allow.conf"

generated_conf_header() {
    conf_name="$1"
    purpose="$2"

    printf '%s\n' "# Generated file: ${conf_name}"
    printf '%s\n' "# Purpose: ${purpose}"
    printf '%s\n' "# Repo path: .blue-team-vm/runtime/rendered/${conf_name}"
    printf '%s\n' "# Regenerate via: docker/nginx/entrypoint.sh start|render-ssl-mode-conf|reload-ssl|ssl-switch"
    printf '\n'
}

if ! command -v openssl >/dev/null 2>&1; then
    echo "ERROR: openssl is required in the nginx image." >&2
    exit 1
fi

normalize_ssl_mode() {
    if [ "${1:-}" != "" ]; then
        SSL_MODE="$1"
    fi

    case "${SSL_MODE}" in
        ""|selfsigned|self-signed)
            ACTIVE_SSL_MODE="self-signed"
            ;;
        cloudflare-origin)
            ACTIVE_SSL_MODE="cloudflare-origin"
            ;;
        letsencrypt)
            ACTIVE_SSL_MODE="letsencrypt"
            ;;
        custom)
            ACTIVE_SSL_MODE="custom"
            ;;
        *)
            echo "ERROR: unsupported SSL_MODE: ${SSL_MODE}" >&2
            exit 1
            ;;
    esac
}

use_explicit_ssl_material_paths() {
    if [ -z "${SSL_CERT_PATH}" ] && [ -z "${SSL_KEY_PATH}" ]; then
        return 1
    fi

    if [ -z "${SSL_CERT_PATH}" ] || [ -z "${SSL_KEY_PATH}" ]; then
        echo "ERROR: SSL_CERT_PATH and SSL_KEY_PATH must be set together." >&2
        exit 1
    fi

    RESOLVED_SSL_CERT_PATH="${SSL_CERT_PATH}"
    RESOLVED_SSL_KEY_PATH="${SSL_KEY_PATH}"
    return 0
}

resolve_ssl_material_paths() {
    normalize_ssl_mode "${1:-${SSL_MODE}}"

    if use_explicit_ssl_material_paths; then
        return 0
    fi

    case "${ACTIVE_SSL_MODE}" in
        self-signed)
            RESOLVED_SSL_CERT_PATH="${SELF_SIGNED_CERT}"
            RESOLVED_SSL_KEY_PATH="${SELF_SIGNED_KEY}"
            ;;
        cloudflare-origin)
            RESOLVED_SSL_CERT_PATH="${CLOUDFLARE_ORIGIN_CERT_DIR}/${SSL_CERT_DOMAIN}/fullchain.pem"
            RESOLVED_SSL_KEY_PATH="${CLOUDFLARE_ORIGIN_CERT_DIR}/${SSL_CERT_DOMAIN}/key.pem"
            ;;
        letsencrypt)
            RESOLVED_SSL_CERT_PATH="${LETSENCRYPT_CERT_DIR}/${SSL_CERT_DOMAIN}/fullchain.pem"
            RESOLVED_SSL_KEY_PATH="${LETSENCRYPT_CERT_DIR}/${SSL_CERT_DOMAIN}/privkey.pem"
            ;;
        custom)
            RESOLVED_SSL_CERT_PATH="${SSL_RUNTIME_DIR}/custom/${SSL_CERT_DOMAIN}/fullchain.pem"
            RESOLVED_SSL_KEY_PATH="${SSL_RUNTIME_DIR}/custom/${SSL_CERT_DOMAIN}/key.pem"
            ;;
    esac
}

resolve_ssl_mode_template() {
    ACTIVE_SSL_MODE_TEMPLATE="${SSL_MODE_TEMPLATE}"

    if [ -f "${ACTIVE_SSL_MODE_TEMPLATE}" ]; then
        return 0
    fi

    if [ -f "${SSL_MODE_TEMPLATE_REPO_FALLBACK}" ]; then
        ACTIVE_SSL_MODE_TEMPLATE="${SSL_MODE_TEMPLATE_REPO_FALLBACK}"
        return 0
    fi

    cat > "${SSL_MODE_TEMPLATE_EMBEDDED_FALLBACK}" <<'EOF'
ssl_certificate __SSL_CERT_PATH__;
ssl_certificate_key __SSL_KEY_PATH__;
EOF
    ACTIVE_SSL_MODE_TEMPLATE="${SSL_MODE_TEMPLATE_EMBEDDED_FALLBACK}"
}

ensure_file_present() {
    required_path="$1"
    label="$2"

    if [ ! -s "${required_path}" ]; then
        echo "ERROR: ${label} is missing or empty: ${required_path}" >&2
        exit 1
    fi
}

build_self_signed_san_list() {
    LAN_IP="$(hostname -i 2>/dev/null | awk '{print $1}')"
    [ -n "${LAN_IP}" ] || LAN_IP="127.0.0.1"

    SAN_LIST="DNS:localhost,DNS:jobboard.local,DNS:nginx,DNS:${SSL_CERT_DOMAIN},IP:127.0.0.1,IP:${LAN_IP}"
    CONTAINER_HOST="$(hostname 2>/dev/null || true)"
    ORB_DOMAIN=""

    if [ -n "${CONTAINER_HOST}" ]; then
        ORB_DOMAIN="${CONTAINER_HOST}.orb.local"
        if nslookup "${ORB_DOMAIN}" 127.0.0.11 >/dev/null 2>&1; then
            SAN_LIST="${SAN_LIST},DNS:${ORB_DOMAIN}"
            echo "OrbStack detected, adding ${ORB_DOMAIN} to cert SANs."
        fi
    fi
}

self_signed_cert_needs_renewal() {
    build_self_signed_san_list

    if [ ! -f "${RESOLVED_SSL_CERT_PATH}" ] || [ ! -f "${RESOLVED_SSL_KEY_PATH}" ]; then
        return 0
    fi

    if ! openssl x509 -noout -in "${RESOLVED_SSL_CERT_PATH}" >/dev/null 2>&1; then
        return 0
    fi

    if ! openssl x509 -noout -text -in "${RESOLVED_SSL_CERT_PATH}" 2>/dev/null | grep -q "IP Address:${LAN_IP}"; then
        echo "LAN IP ${LAN_IP} not present in the self-signed certificate SAN list, regenerating..."
        return 0
    fi

    if ! openssl x509 -noout -text -in "${RESOLVED_SSL_CERT_PATH}" 2>/dev/null | grep -q "DNS:${SSL_CERT_DOMAIN}"; then
        echo "SSL_CERT_DOMAIN ${SSL_CERT_DOMAIN} not present in the self-signed certificate SAN list, regenerating..."
        return 0
    fi

    if ! openssl x509 -noout -text -in "${RESOLVED_SSL_CERT_PATH}" 2>/dev/null | grep -q "DNS:nginx"; then
        echo "Internal nginx DNS name not present in the self-signed certificate SAN list, regenerating..."
        return 0
    fi

    if [ -n "${ORB_DOMAIN}" ] && \
        ! openssl x509 -noout -text -in "${RESOLVED_SSL_CERT_PATH}" 2>/dev/null | grep -q "DNS:${ORB_DOMAIN}"; then
        echo "OrbStack domain ${ORB_DOMAIN} not present in the self-signed certificate SAN list, regenerating..."
        return 0
    fi

    return 1
}

ensure_self_signed_cert() {
    self_signed_cert_needs_renewal || return 0

    echo "Generating self-signed SSL certificate for ${SSL_CERT_DOMAIN}..."
    mkdir -p "$(dirname "${RESOLVED_SSL_CERT_PATH}")" "$(dirname "${RESOLVED_SSL_KEY_PATH}")"

    openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
        -keyout "${RESOLVED_SSL_KEY_PATH}" \
        -out "${RESOLVED_SSL_CERT_PATH}" \
        -subj "/C=HK/ST=HK/L=HongKong/O=JobBoard/OU=Dev/CN=${SSL_CERT_DOMAIN}" \
        -addext "subjectAltName=${SAN_LIST}" \
        2>/dev/null

    chmod 0644 "${RESOLVED_SSL_CERT_PATH}"
    chmod 0600 "${RESOLVED_SSL_KEY_PATH}"
    echo "Self-signed SSL certificate ready (SANs: ${SAN_LIST})."
}

render_ssl_mode_conf() {
    resolve_ssl_material_paths "${1:-${SSL_MODE}}"

    case "${ACTIVE_SSL_MODE}" in
        self-signed)
            ensure_self_signed_cert
            ;;
        cloudflare-origin)
            ensure_file_present "${RESOLVED_SSL_CERT_PATH}" "Cloudflare origin certificate"
            ensure_file_present "${RESOLVED_SSL_KEY_PATH}" "Cloudflare origin private key"
            ;;
        letsencrypt)
            ensure_file_present "${RESOLVED_SSL_CERT_PATH}" "Let's Encrypt fullchain"
            ensure_file_present "${RESOLVED_SSL_KEY_PATH}" "Let's Encrypt private key"
            ;;
        custom)
            ensure_file_present "${RESOLVED_SSL_CERT_PATH}" "custom certificate"
            ensure_file_present "${RESOLVED_SSL_KEY_PATH}" "custom private key"
            ;;
    esac

    resolve_ssl_mode_template
    mkdir -p "${SSL_GENERATED_DIR}"

    if [ -d "${SSL_MODE_CONF}" ]; then
        echo "WARNING: ${SSL_MODE_CONF} is a directory; removing it so SSL mode config can be rendered." >&2
        rmdir "${SSL_MODE_CONF}" 2>/dev/null || rm -rf "${SSL_MODE_CONF}"
    fi

    {
        generated_conf_header "ssl-mode.conf" "nginx include that resolves the active runtime SSL certificate and key inside the container."
        sed \
        -e "s|__SSL_CERT_PATH__|${RESOLVED_SSL_CERT_PATH}|g" \
        -e "s|__SSL_KEY_PATH__|${RESOLVED_SSL_KEY_PATH}|g" \
        "${ACTIVE_SSL_MODE_TEMPLATE}"
    } > "${SSL_MODE_CONF}"

    chmod 0644 "${SSL_MODE_CONF}"
    echo "Rendered ${SSL_MODE_CONF} for SSL_MODE=${ACTIVE_SSL_MODE}."
}

reload_ssl_mode() {
    render_ssl_mode_conf "${1:-${SSL_MODE}}"
    "${OPENRESTY_BIN}" -t -c /etc/nginx/nginx.conf
    "${OPENRESTY_BIN}" -s reload -c /etc/nginx/nginx.conf
}

run_ssl_helper() {
    helper_command="$1"
    shift || true

    if [ "${1:-}" != "" ]; then
        SSL_MODE="$1"
        shift
    fi

    if [ "${1:-}" != "" ]; then
        SSL_CERT_DOMAIN="$1"
        shift
    fi

    if [ "${1:-}" != "" ]; then
        SSL_CERT_PATH="$1"
        shift
    fi

    if [ "${1:-}" != "" ]; then
        SSL_KEY_PATH="$1"
        shift
    fi

    case "${helper_command}" in
        render-ssl-mode-conf)
            render_ssl_mode_conf "${SSL_MODE}"
            ;;
        reload-ssl|ssl-switch)
            reload_ssl_mode "${SSL_MODE}"
            ;;
        *)
            echo "ERROR: unsupported SSL helper command: ${helper_command}" >&2
            exit 1
            ;;
    esac
}

render_monitoring_geo_conf() {
    mkdir -p "${MONITORING_GENERATED_DIR}"

    {
        generated_conf_header "monitoring-geo.conf" "nginx geo map that marks internal monitoring clients from MONITORING_ALLOWED_CIDRS."
        echo 'geo $is_internal {'
        echo '    default 0;'

        old_ifs="${IFS}"
        IFS=','
        for cidr in ${MONITORING_ALLOWED_CIDRS}; do
            cidr="$(printf '%s' "${cidr}" | tr -d '[:space:]')"
            [ -n "${cidr}" ] || continue
            printf '    %s 1;\n' "${cidr}"
        done
        IFS="${old_ifs}"

        echo '}'
    } > "${MONITORING_GEO_CONF}"
}

render_monitoring_access_conf() {
    case "${MONITORING_ACCESS_MODE}" in
        internal-only)
            {
                generated_conf_header "monitoring-access.conf" "nginx location-level access guard for monitoring routes."
                cat <<'EOF'
if ($is_internal = 0) { rewrite ^ /_error/403 last; }
EOF
            } > "${MONITORING_ACCESS_CONF}"
            ;;
        auth-only)
            generated_conf_header "monitoring-access.conf" "nginx location-level access guard for monitoring routes." > "${MONITORING_ACCESS_CONF}"
            ;;
        disabled)
            {
                generated_conf_header "monitoring-access.conf" "nginx location-level access guard for monitoring routes."
                cat <<'EOF'
return 404;
EOF
            } > "${MONITORING_ACCESS_CONF}"
            ;;
        *)
            echo "ERROR: unsupported MONITORING_ACCESS_MODE: ${MONITORING_ACCESS_MODE}" >&2
            exit 1
            ;;
    esac
}

render_monitoring_server_access_conf() {
    case "${MONITORING_ACCESS_MODE}" in
        internal-only|auth-only)
            generated_conf_header "monitoring-server-access.conf" "nginx server-level monitoring route deny rules used before location dispatch." > "${MONITORING_SERVER_ACCESS_CONF}"
            ;;
        disabled)
            {
                generated_conf_header "monitoring-server-access.conf" "nginx server-level monitoring route deny rules used before location dispatch."
                cat <<'EOF'
if ($request_uri ~ "^/monitoring/") { return 404; }
EOF
            } > "${MONITORING_SERVER_ACCESS_CONF}"
            ;;
        *)
            echo "ERROR: unsupported MONITORING_ACCESS_MODE: ${MONITORING_ACCESS_MODE}" >&2
            exit 1
            ;;
    esac
}

render_monitoring_policy() {
    render_monitoring_geo_conf
    render_monitoring_access_conf
    render_monitoring_server_access_conf
    chmod 0644 "${MONITORING_GEO_CONF}" "${MONITORING_ACCESS_CONF}" "${MONITORING_SERVER_ACCESS_CONF}"
}

ensure_private_network_allow_include() {
    mkdir -p /etc/nginx/includes

    if [ -s "${PRIVATE_NETWORK_ALLOW_CONF}" ]; then
        return 0
    fi

    {
        echo 'allow 127.0.0.1;'

        old_ifs="${IFS}"
        IFS=','
        for cidr in ${MONITORING_ALLOWED_CIDRS}; do
            cidr="$(printf '%s' "${cidr}" | tr -d '[:space:]')"
            [ -n "${cidr}" ] || continue
            printf 'allow %s;\n' "${cidr}"
        done
        IFS="${old_ifs}"

        echo 'deny all;'
    } > "${PRIVATE_NETWORK_ALLOW_CONF}"

    chmod 0644 "${PRIVATE_NETWORK_ALLOW_CONF}"
    echo "Rendered ${PRIVATE_NETWORK_ALLOW_CONF} from MONITORING_ALLOWED_CIDRS fallback."
}

render_crowdsec_bouncer_config() {
    KEY_FILE="/crowdsec-keys/bouncer.key"

    if [ -s "${KEY_FILE}" ]; then
        CROWDSEC_BOUNCER_API_KEY="$(cat "${KEY_FILE}")"
        mkdir -p /etc/crowdsec/bouncers
        sed "s|\${CROWDSEC_BOUNCER_API_KEY}|${CROWDSEC_BOUNCER_API_KEY}|g" \
            /etc/nginx/crowdsec-bouncer.conf.template \
            > /etc/crowdsec/bouncers/crowdsec-nginx-bouncer.conf
        echo "CrowdSec bouncer config written."
        return
    fi

    echo "WARNING: /crowdsec-keys/bouncer.key not found, CrowdSec bouncer disabled."
}

cleanup() {
    if [ -n "${TAIL_PID}" ]; then
        kill "${TAIL_PID}" 2>/dev/null || true
    fi
}

case "${1:-}" in
    render-ssl-mode-conf|reload-ssl|ssl-switch)
        helper_command="$1"
        shift
        run_ssl_helper "${helper_command}" "$@"
        exit 0
        ;;
    start)
        shift
        ;;
    "")
        ;;
    *)
        exec "$@"
        ;;
esac

render_monitoring_policy
ensure_private_network_allow_include
render_ssl_mode_conf "${SSL_MODE}"

mkdir -p /var/log/nginx
touch /var/log/nginx/access.log /var/log/nginx/error.log /var/log/nginx/fp-trap.log
chmod 0644 /var/log/nginx/*.log

render_crowdsec_bouncer_config

trap cleanup EXIT INT TERM

if [ -L /var/log/nginx/access.log ] || [ -L /var/log/nginx/error.log ]; then
    echo "log symlinks detected, leaving in place"
else
    tail -n 0 -F /var/log/nginx/access.log /var/log/nginx/error.log &
    TAIL_PID=$!
fi

exec "${OPENRESTY_BIN}" -g 'daemon off;' -c /etc/nginx/nginx.conf
