#!/bin/sh
set -e


CERT="/etc/nginx/ssl/selfsigned.crt"
KEY="/etc/nginx/ssl/selfsigned.key"
HTPASSWD="/etc/nginx/htpasswd/monitoring.htpasswd"

# ensure critical binaries available (nginx:alpine minimal)
if ! command -v openssl >/dev/null 2>&1 || ! command -v htpasswd >/dev/null 2>&1; then
    echo "Installing missing dependencies..."
    apk add --no-cache openssl apache2-utils >/dev/null 2>&1 || true
fi


# SSL cert renewal logic (simplified to avoid BusyBox date issues)
RENEW_CERT=0
if [ ! -f "$CERT" ] || [ ! -f "$KEY" ]; then
    RENEW_CERT=1
else
    # if certificate file is unreadable/regenerates incorrectly, renew
    if ! openssl x509 -noout -in "$CERT" >/dev/null 2>&1; then
        RENEW_CERT=1
    fi
fi

if [ "$RENEW_CERT" -eq 1 ]; then
    echo "Generating self-signed SSL certificate..."
    mkdir -p /etc/nginx/ssl

    # improved LAN IP detection: use -i fallback
    LAN_IP=$(hostname -i 2>/dev/null | awk '{print $1}')
    [ -z "$LAN_IP" ] && LAN_IP="127.0.0.1"

    openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
        -keyout "$KEY" \
        -out "$CERT" \
        -subj "/C=HK/ST=HK/L=HongKong/O=JobBoard/OU=Dev/CN=jobboard.local" \
        -addext "subjectAltName=DNS:localhost,DNS:jobboard.local,IP:127.0.0.1,IP:${LAN_IP}" \
        2>/dev/null
    chmod 644 "$CERT"
    chmod 600 "$KEY"
    echo "SSL certificate generated for $LAN_IP."
fi

if [ -n "$MONITORING_PASSWORD" ]; then
    if [ ${#MONITORING_PASSWORD} -lt 16 ]; then
        echo "WARNING: MONITORING_PASSWORD is less than 16 characters. This is not recommended for security reasons."
    fi
fi

if [ -n "$MONITORING_PASSWORD" ] && [ ! -f "$HTPASSWD" ]; then
    echo "Generating monitoring htpasswd..."
    mkdir -p /etc/nginx/htpasswd
    htpasswd -Bcb "$HTPASSWD" admin "$MONITORING_PASSWORD"
    chmod 644 "$HTPASSWD"
    echo "htpasswd generated with bcrypt."
fi

mkdir -p /var/log/nginx
touch /var/log/nginx/access.log /var/log/nginx/error.log
chmod 644 /var/log/nginx/*.log


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

exec nginx -g 'daemon off;'
