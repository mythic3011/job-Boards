#!/bin/sh
set -e

KEY_FILE="/crowdsec-keys/bouncer.key"

echo "Waiting for bouncer key..."
until [ -s "$KEY_FILE" ]; do
    sleep 2
done

export CROWDSEC_BOUNCER_API_KEY=$(cat "$KEY_FILE")
export CROWDSEC_AGENT_HOST="${CROWDSEC_AGENT_HOST:-crowdsec:8080}"
export GIN_MODE="${GIN_MODE:-release}"
export PORT="${PORT:-8080}"

echo "Starting bouncer..."
exec /app
