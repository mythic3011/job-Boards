#!/bin/sh
set -e

KEY_FILE="/crowdsec-keys/bouncer.key"

echo "Waiting for CrowdSec API..."
until wget -qO- http://crowdsec:8080/health > /dev/null 2>&1; do
    sleep 2
done

until cscli bouncers list > /dev/null 2>&1; do
    echo "Waiting for cscli..."
    sleep 2
done

if [ ! -s "$KEY_FILE" ]; then
    echo "Generating bouncer API key..."
    cscli bouncers delete nginx-bouncer 2>/dev/null || true
    cscli bouncers add nginx-bouncer -o raw > "$KEY_FILE"
    echo "Bouncer key written."
else
    echo "Bouncer key already exists."
fi
