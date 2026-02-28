#!/bin/sh
set -e

CERT="/etc/nginx/ssl/selfsigned.crt"
KEY="/etc/nginx/ssl/selfsigned.key"
HTPASSWD="/etc/nginx/htpasswd/monitoring.htpasswd"

if [ ! -f "$CERT" ] || [ ! -f "$KEY" ]; then
    echo "Generating self-signed SSL certificate..."
    mkdir -p /etc/nginx/ssl
    apk add --no-cache openssl > /dev/null 2>&1 || true
    # detect primary LAN IP for SAN
    LAN_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
    [ -z "$LAN_IP" ] && LAN_IP=127.0.0.1
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$KEY" \
        -out "$CERT" \
        -subj "/C=HK/ST=HK/L=HongKong/O=JobBoard/OU=Dev/CN=jobboard.local" \
        -addext "subjectAltName=DNS:localhost,DNS:jobboard.local,IP:127.0.0.1,IP:${LAN_IP}" \
        2>/dev/null
    chmod 644 "$CERT"
    chmod 600 "$KEY"
    echo "SSL certificate generated for $LAN_IP."
else
    echo "SSL certificate already exists, skipping."
fi

if [ ! -f "$HTPASSWD" ] && [ -n "$MONITORING_PASSWORD" ]; then
    echo "Generating monitoring htpasswd..."
    mkdir -p /etc/nginx/htpasswd
    apk add --no-cache apache2-utils > /dev/null 2>&1 || true
    htpasswd -Bcb "$HTPASSWD" admin "$MONITORING_PASSWORD"
    chmod 644 "$HTPASSWD"
    echo "htpasswd generated with bcrypt."
elif [ -f "$HTPASSWD" ]; then
    echo "htpasswd already exists, skipping."
else
    echo "MONITORING_PASSWORD not set, skipping htpasswd generation."
fi

mkdir -p /var/log/nginx
touch /var/log/nginx/access.log /var/log/nginx/error.log
chmod 644 /var/log/nginx/*.log

if [ -L /var/log/nginx/access.log ] || [ -L /var/log/nginx/error.log ]; then
    echo "log symlinks detected, leaving in place"
else
    tail -n+1 -F /var/log/nginx/access.log /var/log/nginx/error.log &
fi

exec nginx -g 'daemon off;'
