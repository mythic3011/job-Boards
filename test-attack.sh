#!/bin/bash
set -e

TARGET="https://localhost"
ATTACKER_IP="203.0.113.42"
COOKIE_FILE="/tmp/crowdsec-test-cookies.txt"

echo "=== CrowdSec Attack Testing Script ==="
echo "Target: $TARGET"
echo "Simulated attacker IP: $ATTACKER_IP"
echo ""

# Cleanup function
cleanup() {
    rm -f "$COOKIE_FILE"
}
trap cleanup EXIT

# Test 1: Brute Force Attack
echo "[Test 1] Brute Force Attack - 10 login attempts with invalid credentials"
echo "Expected: Ban after 8 attempts (capacity in laravel-bf.yaml)"
echo ""

for i in {1..10}; do
    echo -n "Attempt $i/10: "

    # Fetch CSRF token from Livewire data-csrf attribute
    CSRF=$(curl -sk -c "$COOKIE_FILE" "$TARGET/login" 2>/dev/null | grep -o 'data-csrf="[^"]*"' | cut -d'"' -f2 | head -1)

    if [ -z "$CSRF" ]; then
        echo "Failed to extract CSRF token"
        continue
    fi

    # Submit login with invalid credentials
    HTTP_CODE=$(curl -sk -b "$COOKIE_FILE" -w "%{http_code}" -o /dev/null \
        -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
        -H "X-Forwarded-For: $ATTACKER_IP" \
        -X POST "$TARGET/login" \
        -d "_token=$CSRF&login_id=attacker&password=wrongpass123")

    echo "HTTP $HTTP_CODE"

    # Clean cookies for next attempt
    rm -f "$COOKIE_FILE"

    sleep 2
done

echo ""
echo "[Test 1] Complete. Checking for ban..."
docker exec jobs-boards-crowdsec cscli decisions list | grep -E "$ATTACKER_IP|No active"
echo ""

# Test 2: Path Traversal Attack
echo "[Test 2] Path Traversal Attack - 15 requests for sensitive files"
echo "Expected: Ban after 15 attempts (capacity in path-scanner or http-path-traversal-probing)"
echo ""

SCANNER_IP="203.0.113.50"
PATHS=(
    "/.env"
    "/.git/config"
    "/admin/.env"
    "/wp-admin/install.php"
    "/phpmyadmin/index.php"
    "/config.php"
    "/backup.sql"
    "/.env.backup"
    "/admin.php"
    "/shell.php"
    "/upload.asp"
    "/.git/HEAD"
    "/web.config.bak"
    "/database.env"
    "/credentials.php"
)

for path in "${PATHS[@]}"; do
    echo -n "Scanning $path: "
    HTTP_CODE=$(curl -sk -w "%{http_code}" -o /dev/null \
        -H "User-Agent: Mozilla/5.0 (compatible; Scanner/1.0)" \
        -H "X-Forwarded-For: $SCANNER_IP" \
        "$TARGET$path")
    echo "HTTP $HTTP_CODE"
    sleep 1
done

echo ""
echo "[Test 2] Complete. Checking for ban..."
docker exec jobs-boards-crowdsec cscli decisions list | grep -E "$SCANNER_IP|No active"
echo ""

# Test 3: Rate Limit Test
echo "[Test 3] Rate Limit Test - 30 rapid requests"
echo "Expected: 429 responses after burst limit"
echo ""

RATELIMIT_IP="203.0.113.60"
echo "Sending 30 concurrent requests to /jobs..."

for i in {1..30}; do
    curl -sk -H "X-Forwarded-For: $RATELIMIT_IP" "$TARGET/jobs" > /dev/null 2>&1 &
done
wait

echo "Complete. Checking nginx logs for 429 responses..."
docker exec jobs-boards-nginx tail -30 /var/log/nginx/access.log | grep -c "429" || echo "No 429 responses found"
echo ""

# Summary
echo "=== Test Summary ==="
echo ""
echo "Active bans:"
docker exec jobs-boards-crowdsec cscli decisions list
echo ""
echo "Recent alerts:"
docker exec jobs-boards-crowdsec cscli alerts list -l 5
echo ""
echo "To remove all test bans:"
echo "  docker exec jobs-boards-crowdsec cscli decisions delete --all"
