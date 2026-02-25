#!/bin/sh
set -e

CERT="/etc/nginx/ssl/selfsigned.crt"
KEY="/etc/nginx/ssl/selfsigned.key"

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

exec nginx -g 'daemon off;'
