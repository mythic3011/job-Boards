#!/bin/sh
set -e


CERT="/etc/nginx/ssl/selfsigned.crt"
KEY="/etc/nginx/ssl/selfsigned.key"
MONITORING_GENERATED_DIR="/etc/nginx/generated"
MONITORING_GEO_CONF="/etc/nginx/generated/monitoring-geo.conf"
MONITORING_ACCESS_CONF="/etc/nginx/generated/monitoring-access.conf"
MONITORING_SERVER_ACCESS_CONF="/etc/nginx/generated/monitoring-server-access.conf"
MONITORING_ACCESS_MODE="${MONITORING_ACCESS_MODE:-internal-only}"
MONITORING_ALLOWED_CIDRS="${MONITORING_ALLOWED_CIDRS:-127.0.0.1/32,192.168.0.0/16}"

# ensure critical binaries available (nginx:alpine minimal)
if ! command -v openssl >/dev/null 2>&1; then
    echo "Installing missing dependencies..."
    apk add --no-cache openssl >/dev/null 2>&1 || true
fi

render_monitoring_geo_conf() {
    mkdir -p "${MONITORING_GENERATED_DIR}"

    {
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
            cat > "${MONITORING_ACCESS_CONF}" <<'EOF'
if ($is_internal = 0) { return 403; }
EOF
            ;;
        auth-only)
            : > "${MONITORING_ACCESS_CONF}"
            ;;
        disabled)
            cat > "${MONITORING_ACCESS_CONF}" <<'EOF'
return 404;
EOF
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
            : > "${MONITORING_SERVER_ACCESS_CONF}"
            ;;
        disabled)
            cat > "${MONITORING_SERVER_ACCESS_CONF}" <<'EOF'
if ($request_uri ~ "^/monitoring/") { return 404; }
EOF
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


# SSL cert renewal logic
LAN_IP=$(hostname -i 2>/dev/null | awk '{print $1}')
[ -z "$LAN_IP" ] && LAN_IP="127.0.0.1"

# Build dynamic SAN list
SAN_LIST="DNS:localhost,DNS:jobboard.local,IP:127.0.0.1,IP:${LAN_IP}"

# Auto-detect OrbStack: container hostname resolves as *.orb.local
CONTAINER_HOST=$(hostname 2>/dev/null)
ORB_DOMAIN="${CONTAINER_HOST}.orb.local"
if nslookup "$ORB_DOMAIN" 127.0.0.11 >/dev/null 2>&1; then
    SAN_LIST="${SAN_LIST},DNS:${ORB_DOMAIN}"
    echo "OrbStack detected, adding ${ORB_DOMAIN} to cert SANs."
fi

# Check if cert needs renewal
RENEW_CERT=0
if [ ! -f "$CERT" ] || [ ! -f "$KEY" ]; then
    RENEW_CERT=1
elif ! openssl x509 -noout -in "$CERT" >/dev/null 2>&1; then
    RENEW_CERT=1
elif ! openssl x509 -noout -text -in "$CERT" 2>/dev/null | grep -q "IP Address:${LAN_IP}"; then
    echo "LAN IP $LAN_IP not in cert SANs, regenerating..."
    RENEW_CERT=1
elif echo "$SAN_LIST" | grep -q "orb.local" && \
     ! openssl x509 -noout -text -in "$CERT" 2>/dev/null | grep -q "DNS:${ORB_DOMAIN}"; then
    echo "OrbStack domain ${ORB_DOMAIN} not in cert SANs, regenerating..."
    RENEW_CERT=1
fi

if [ "$RENEW_CERT" -eq 1 ]; then
    echo "Generating self-signed SSL certificate..."
    mkdir -p /etc/nginx/ssl

    openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
        -keyout "$KEY" \
        -out "$CERT" \
        -subj "/C=HK/ST=HK/L=HongKong/O=JobBoard/OU=Dev/CN=jobboard.local" \
        -addext "subjectAltName=${SAN_LIST}" \
        2>/dev/null
    chmod 644 "$CERT"
    chmod 600 "$KEY"
    echo "SSL certificate generated (SANs: ${SAN_LIST})."
fi

render_monitoring_policy

mkdir -p /var/log/nginx
touch /var/log/nginx/access.log /var/log/nginx/error.log /var/log/nginx/fp-trap.log
chmod 644 /var/log/nginx/*.log

# Write CrowdSec bouncer config from template
KEY_FILE="/crowdsec-keys/bouncer.key"
if [ -s "$KEY_FILE" ]; then
    CROWDSEC_BOUNCER_API_KEY=$(cat "$KEY_FILE")
    mkdir -p /etc/crowdsec/bouncers
    sed "s|\${CROWDSEC_BOUNCER_API_KEY}|${CROWDSEC_BOUNCER_API_KEY}|g" \
        /etc/nginx/crowdsec-bouncer.conf.template \
        > /etc/crowdsec/bouncers/crowdsec-nginx-bouncer.conf
    echo "CrowdSec bouncer config written."
else
    echo "WARNING: /crowdsec-keys/bouncer.key not found, CrowdSec bouncer disabled."
fi


cleanup() {
    [ -n "$TAIL_PID" ] && kill "$TAIL_PID" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

if [ -L /var/log/nginx/access.log ] || [ -L /var/log/nginx/error.log ]; then
    echo "log symlinks detected, leaving in place"
else
    tail -n+1 -F /var/log/nginx/access.log /var/log/nginx/error.log &
    TAIL_PID=$!
fi

exec nginx -g 'daemon off;' -c /etc/nginx/nginx.conf
