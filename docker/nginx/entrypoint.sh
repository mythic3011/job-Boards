#!/bin/sh
set -e

CERT="/etc/nginx/ssl/selfsigned.crt"
KEY="/etc/nginx/ssl/selfsigned.key"
HTPASSWD="/etc/nginx/htpasswd/monitoring.htpasswd"

if [ ! -f "$CERT" ] || [ ! -f "$KEY" ]; then
    echo "Generating self-signed SSL certificate..."
    mkdir -p /etc/nginx/ssl
    apk add --no-cache openssl > /dev/null 2>&1 || true
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$KEY" \
        -out "$CERT" \
        -subj "/C=HK/ST=HK/L=HongKong/O=JobBoard/OU=Dev/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
        2>/dev/null
    chmod 644 "$CERT"
    chmod 600 "$KEY"
    echo "SSL certificate generated."
else
    echo "SSL certificate already exists, skipping."
fi

if [ ! -f "$HTPASSWD" ] && [ -n "$MONITORING_PASSWORD" ]; then
    echo "Generating monitoring htpasswd..."
    mkdir -p /etc/nginx/htpasswd
    apk add --no-cache apache2-utils > /dev/null 2>&1 || true
    htpasswd -cb "$HTPASSWD" admin "$MONITORING_PASSWORD"
    chmod 600 "$HTPASSWD"
    echo "htpasswd generated."
elif [ -f "$HTPASSWD" ]; then
    echo "htpasswd already exists, skipping."
else
    echo "MONITORING_PASSWORD not set, skipping htpasswd generation."
fi

exec nginx -g 'daemon off;'
