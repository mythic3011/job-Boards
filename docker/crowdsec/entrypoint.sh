#!/bin/sh
set -e

KEY_FILE="/crowdsec-keys/bouncer.key"

# Wait for CrowdSec to be fully up
echo "Waiting for CrowdSec to be ready..."
until cscli version > /dev/null 2>&1; do
    sleep 2
done

# Generate bouncer key once and persist it
if [ ! -s "$KEY_FILE" ]; then
    echo "Generating bouncer API key..."
    cscli bouncers delete nginx-bouncer 2>/dev/null || true
    cscli bouncers add nginx-bouncer -o raw > "$KEY_FILE"
    echo "Bouncer key written to $KEY_FILE"
else
    echo "Bouncer key already exists."
fi

exec /usr/local/bin/crowdsec -c /etc/crowdsec/config.yaml
