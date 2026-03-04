#!/usr/bin/env bash
set -euo pipefail

# ─── Usage ────────────────────────────────────────────────────────────────────
# ./bootstrap-env.sh [dev|production|test]
# dev        — generate missing/weak secrets only, never overwrite existing strong values
# production — force-regenerate ALL secrets regardless of current value
# test       — sync .env.testing with .env credentials (DB password, APP_KEY)

MODE="${1:-dev}"
[[ "$MODE" != "dev" && "$MODE" != "production" && "$MODE" != "test" ]] && {
    echo "Usage: $0 [dev|production|test]"
    echo "  dev        — fill missing/weak secrets only"
    echo "  production — force-regenerate ALL secrets (safe deploy)"
    echo "  test       — sync .env.testing with .env credentials"
    exit 1
}

# ─── Platform-safe sed ───────────────────────────────────────────────────────
SED_CMD="sed -i"
[[ "$OSTYPE" == "darwin"* ]] && SED_CMD="sed -i ''"

# ─── Generators ──────────────────────────────────────────────────────────────
gen_secret()   { openssl rand -hex 32; }
gen_app_key()  { echo "base64:$(openssl rand -base64 32)"; }

gen_uuid() {
    # Prefer system sources, fallback with correct variant bits
    cat /proc/sys/kernel/random/uuid 2>/dev/null \
    || python3 -c "import uuid; print(uuid.uuid4())" 2>/dev/null \
    || printf '%08x-%04x-4%03x-%04x-%012x\n' \
        $((RANDOM * RANDOM)) \
        $RANDOM \
        $((RANDOM & 0xfff)) \
        $((0x8000 | (RANDOM & 0x3fff))) \
        $((RANDOM * RANDOM * RANDOM))
}

# Unified pass generator — gen_pass <length>
gen_pass() { openssl rand -base64 48 | tr -d "=+/" | cut -c1-"${1:-32}"; }
gen_db_pass()  { gen_pass 32; }
gen_mon_pass() { gen_pass 24; }

# ─── Boolean misconfiguration detection ──────────────────────────────────────
is_misconfigured() {
    local val="$1" var="$2"
    case "$var" in
        SESSION_ENCRYPT)       [[ "$val" != "true"  ]] && return 0 ;;
        APP_DEBUG)             [[ "$val" != "false" ]] && return 0 ;;
        SESSION_SECURE_COOKIE) [[ "$val" != "true"  ]] && return 0 ;;
    esac
    return 1
}

# ─── Weak value detection ─────────────────────────────────────────────────────
is_weak() {
    local val="$1" var="$2"

    [[ -z "$val" ]] && return 0

    # Booleans handled separately
    [[ "$var" =~ ^(APP_DEBUG|SESSION_ENCRYPT|SESSION_SECURE_COOKIE|AWS_USE_PATH_STYLE_ENDPOINT)$ ]] && return 1

    if [[ "$var" =~ (SECRET|KEY|TOKEN|PASSWORD)$ || "$var" == "APP_KEY" ]]; then
        local placeholders=("secret" "password" "null" "changeme"
                            "example" "default" "test"
                            "your_secret" "your_key" "replace_me" "todo")
        local lower_val
        lower_val=$(echo "$val" | tr '[:upper:]' '[:lower:]')
        for p in "${placeholders[@]}"; do
            [[ "$lower_val" == "$p" ]] && return 0
        done
        [[ "$var" == "APP_KEY" && ! "$val" =~ ^base64:.{40,}$ ]] && return 0
        [[ ${#val} -lt 24 ]] && return 0
    fi

    [[ "$var" =~ ^AWS_ \
        && "$var" != "AWS_DEFAULT_REGION" \
        && "$var" != "AWS_USE_PATH_STYLE_ENDPOINT" \
        && -n "$val" ]] && return 0

    return 1
}

# ─── Production-only: always regenerate regardless of current value ───────────
is_secret_var() {
    local var="$1"
    [[ "$var" =~ (SECRET|KEY|TOKEN|PASSWORD)$ ]] && return 0
    [[ "$var" == "APP_KEY" ]] && return 0
    return 1
}

# ─── Env file writer (safe for special chars including base64) ────────────────
set_env() {
    local var="$1" val="$2"
    if grep -q "^${var}=" .env 2>/dev/null; then
        # Use python for safe replacement — avoids sed delimiter collisions
        python3 - <<PYEOF
import re, sys
var  = ${var@Q}
val  = ${val@Q}
path = '.env'
with open(path, 'r') as f:
    content = f.read()
content = re.sub(
    r'^' + re.escape(var) + r'=.*$',
    var + '=' + val,
    content,
    flags=re.MULTILINE
)
with open(path, 'w') as f:
    f.write(content)
PYEOF
    else
        printf '%s=%s\n' "$var" "$val" >> .env
    fi
    echo "  ✔ ${var} updated"
}

generate_for_var() {
    local var="$1"
    local new=""
    case "$var" in
        APP_KEY)             new=$(gen_app_key) ;;
        DB_PASSWORD)         new=$(gen_db_pass) ;;
        REDIS_PASSWORD)      new=$(gen_secret) ;;
        MONITORING_PASSWORD) new=$(gen_mon_pass) ;;
        GRAFANA_PASSWORD)    new=$(gen_mon_pass) ;;
        CROWDSEC_ENROLL_KEY)
            echo "  ⚠ CROWDSEC_ENROLL_KEY — manual setup required (app.crowdsec.net)"
            return ;;
        AWS_*)               set_env "$var" "" ; return ;;
        *SECRET*|*KEY*|*TOKEN*) new=$(gen_secret) ;;
        *UUID*)              new=$(gen_uuid) ;;
    esac
    [[ -n "$new" ]] && set_env "$var" "$new" || true
}

# ─── Preflight ────────────────────────────────────────────────────────────────
[ ! -f .env.example ] && { echo "Error: .env.example not found"; exit 1; }
[ ! -f .env ] && cp .env.example .env && echo "Created .env from .env.example"

# ─── Test mode: sync .env.testing with .env ───────────────────────────────────
if [[ "$MODE" == "test" ]]; then
    echo ""
    echo "═══════════════════════════════════════"
    echo " Test Environment Sync"
    echo "═══════════════════════════════════════"

    # Read critical values from .env
    APP_KEY=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d'=' -f2- || echo "")
    DB_PASSWORD=$(grep "^DB_PASSWORD=" .env 2>/dev/null | cut -d'=' -f2- || echo "")

    if [[ -z "$APP_KEY" ]]; then
        echo "  ✘ APP_KEY not found in .env — run './bootstrap-env.sh dev' first"
        exit 1
    fi

    if [[ -z "$DB_PASSWORD" ]]; then
        echo "  ✘ DB_PASSWORD not found in .env — run './bootstrap-env.sh dev' first"
        exit 1
    fi

    # Create or update .env.testing
    cat > .env.testing <<EOF
APP_NAME=Laravel
APP_ENV=testing
APP_KEY=$APP_KEY
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=testing
DB_USERNAME=jobs_user
DB_PASSWORD=$DB_PASSWORD

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array

# Disable install security checks for testing
INSTALL_ALLOWED_IPS=
INSTALL_TOKEN=

BCRYPT_ROUNDS=4
EOF

    echo "  ✔ .env.testing synced with .env credentials"
    echo ""
    echo "✔ Test environment ready"
    exit 0
fi

# ─── Detect running containers ───────────────────────────────────────────────
# In dev mode, skip DB_PASSWORD regeneration if postgres is already running
# to avoid breaking an existing database instance.
POSTGRES_RUNNING=false
if [[ "$MODE" == "dev" ]] && \
   docker ps --filter "ancestor=postgres" --format '{{.Names}}' 2>/dev/null | grep -q .; then
    POSTGRES_RUNNING=true
fi

echo ""
echo "═══════════════════════════════════════"
echo " Security Bootstrap — ${MODE} mode"
echo "═══════════════════════════════════════"

if [[ "$MODE" == "production" ]]; then
    echo ""
    echo "⚠ Production mode: all secrets will be regenerated."
    BACKUP_DATE=$(date -u +%Y%m%d-%H%M%S 2>/dev/null || date +%Y%m%d-%H%M%S)
    echo "  Existing .env backed up to .env.backup.${BACKUP_DATE}"
    cp .env ".env.backup.${BACKUP_DATE}"
fi

# ── 1. Boolean security settings (both modes) ─────────────────────────────────
echo ""
echo "── Boolean security settings ──"

while IFS= read -r line; do
    [[ "$line" =~ ^#.*$|^$ ]] && continue
    [[ "$line" =~ ^([A-Z_][A-Z0-9_]*)= ]] || continue
    var="${BASH_REMATCH[1]}"
    val=$(grep "^${var}=" .env 2>/dev/null | cut -d'=' -f2- || echo "")
    if is_misconfigured "$val" "$var"; then
        case "$var" in
            SESSION_ENCRYPT|SESSION_SECURE_COOKIE) set_env "$var" "true"  ;;
            APP_DEBUG)                             set_env "$var" "false" ;;
        esac
    fi
done < .env.example

# Production: APP_ENV must be production
if [[ "$MODE" == "production" ]]; then
    set_env "APP_ENV" "production"
    set_env "APP_DEBUG" "false"
    set_env "LOG_LEVEL" "error"
fi

# ── 2. Secret generation ───────────────────────────────────────────────────────
echo ""
echo "── Secret generation ──"

while IFS= read -r line; do
    [[ "$line" =~ ^#.*$|^$ ]] && continue
    [[ "$line" =~ ^([A-Z_][A-Z0-9_]*)= ]] || continue
    var="${BASH_REMATCH[1]}"
    val=$(grep "^${var}=" .env 2>/dev/null | cut -d'=' -f2- || echo "")

    if [[ "$MODE" == "production" ]] && is_secret_var "$var"; then
        generate_for_var "$var"
    elif is_weak "$val" "$var"; then
        if [[ "$var" == "DB_PASSWORD" && "$POSTGRES_RUNNING" == "true" ]]; then
            echo "  ⚠ DB_PASSWORD is weak but postgres is running — skipping (fix manually)"
        else
            generate_for_var "$var"
        fi
    fi
done < .env.example

# ── 3. Monitoring credentials ──────────────────────────────────────────────────
echo ""
echo "── Monitoring credentials ──"

for var in MONITORING_PASSWORD GRAFANA_PASSWORD; do
    val=$(grep "^${var}=" .env 2>/dev/null | cut -d'=' -f2- || echo "")
    if [[ "$MODE" == "production" ]] || is_weak "$val" "$var"; then
        set_env "$var" "$(gen_mon_pass)"
    else
        echo "  ✔ ${var} already set"
    fi
done

# Bcrypt hash via htpasswd -B (method B as requested)
MPW=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2-)
if [[ -n "$MPW" ]]; then
    EXISTING_HASH=$(grep "^MONITORING_PASSWORD_HASH=" .env 2>/dev/null | cut -d'=' -f2- || true)
    if [[ -z "$EXISTING_HASH" || "$MODE" == "production" ]]; then
        echo "  Generating bcrypt hash for MONITORING_PASSWORD (htpasswd -B)..."
        # Extract only the hash part (htpasswd outputs "user:hash", we want hash only)
        HASH=$(docker run --rm httpd:alpine \
            htpasswd -bnB "" "$MPW" | cut -d: -f2 | tr -d '\n\r')
        set_env "MONITORING_PASSWORD_HASH" "$HASH"
    else
        echo "  ✔ MONITORING_PASSWORD_HASH already set"
    fi
fi

# Session secret for auth-service
val=$(grep "^SESSION_SECRET=" .env 2>/dev/null | cut -d'=' -f2- || echo "")
if [[ -z "$val" || "$MODE" == "production" ]]; then
    set_env "SESSION_SECRET" "$(gen_secret)"
else
    echo "  ✔ SESSION_SECRET already set"
fi

# propagate some variables into frontend project (if it exists)
FRONTEND_ENV="docker/auth-service/frontend/.env"
if [[ -d "docker/auth-service/frontend" ]]; then
    echo "VITE_AUTH_VERIFY=/monitoring/auth/verify" > "$FRONTEND_ENV"
    echo "VITE_AUTH_CHECK=/monitoring/auth/check" >> "$FRONTEND_ENV"
    echo "VITE_REDIRECT_DEFAULT=/monitoring/grafana/" >> "$FRONTEND_ENV"
    # also share session secret for client-side use if necessary (beware security)
    echo "VITE_SESSION_SECRET=${val}" >> "$FRONTEND_ENV"
    echo "  ✔ propagated vars to frontend/.env"
fi

# ── 4. Nginx htpasswd ─────────────────────────────────────────────────────────
echo ""
echo "── Nginx htpasswd ──"

MPW=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2-)
if [[ -n "$MPW" ]]; then
    if command -v docker &>/dev/null; then
        mkdir -p docker/nginx/htpasswd
        docker run --rm httpd:alpine \
            htpasswd -bnB admin "$MPW" \
            > docker/nginx/htpasswd/monitoring.htpasswd
        chmod 600 docker/nginx/htpasswd/monitoring.htpasswd
        echo "  ✔ docker/nginx/htpasswd/monitoring.htpasswd regenerated"
    else
        echo "  ⚠ Docker not available — regenerate htpasswd manually:"
        echo "    htpasswd -bnB admin <password> > docker/nginx/htpasswd/monitoring.htpasswd"
    fi
else
    echo "  ⚠ MONITORING_PASSWORD not set — skipping htpasswd"
fi

# ── 5. Final audit ─────────────────────────────────────────────────────────────
echo ""
echo "── Final audit ──"
ISSUES=0

audit_var() {
    local var="$1"
    local val
    val=$(grep "^${var}=" .env 2>/dev/null | cut -d'=' -f2- || echo "")
    if is_weak "$val" "$var" || is_misconfigured "$val" "$var"; then
        echo "  ✘ ${var} — weak or misconfigured (val: '${val}')"
        ISSUES=$((ISSUES + 1))
    else
        echo "  ✔ ${var} — ok (len: ${#val})"
    fi
}

audit_var APP_KEY
audit_var APP_DEBUG
audit_var DB_PASSWORD
audit_var REDIS_PASSWORD
audit_var SESSION_ENCRYPT
audit_var SESSION_SECURE_COOKIE
audit_var MONITORING_PASSWORD
audit_var GRAFANA_PASSWORD
audit_var MONITORING_PASSWORD_HASH
audit_var SESSION_SECRET

# Production extra checks
if [[ "$MODE" == "production" ]]; then
    echo ""
    echo "── Production-only checks ──"
    APP_ENV=$(grep "^APP_ENV=" .env | cut -d'=' -f2- || echo "")
    LOG_LEVEL=$(grep "^LOG_LEVEL=" .env | cut -d'=' -f2- || echo "")

    [[ "$APP_ENV" == "production" ]] \
        && echo "  ✔ APP_ENV=production" \
        || { echo "  ✘ APP_ENV must be 'production'"; ISSUES=$((ISSUES+1)); }

    [[ "$LOG_LEVEL" == "error" || "$LOG_LEVEL" == "warning" ]] \
        && echo "  ✔ LOG_LEVEL=${LOG_LEVEL}" \
        || { echo "  ✘ LOG_LEVEL should be 'error' in production (currently: ${LOG_LEVEL})"; ISSUES=$((ISSUES+1)); }

    CROWDSEC_KEY=$(grep "^CROWDSEC_ENROLL_KEY=" .env | cut -d'=' -f2- || echo "")
    [[ -z "$CROWDSEC_KEY" ]] \
        && echo "  ⚠ CROWDSEC_ENROLL_KEY not set — set manually from app.crowdsec.net" \
        || echo "  ✔ CROWDSEC_ENROLL_KEY set"
fi

echo ""
if [[ $ISSUES -gt 0 ]]; then
    echo "⚠ ${ISSUES} issue(s) remaining — review manually"
    exit 1
else
    echo "✔ All security checks passed"
    if [[ "$MODE" == "production" ]]; then
        echo ""
        echo "Next steps:"
        echo "  1. Set CROWDSEC_ENROLL_KEY manually if using CrowdSec Console"
        echo "  2. Distribute new DB_PASSWORD to any external DB connections"
        echo "  3. Restart all services: docker compose down && docker compose up -d"
        echo "  4. Delete any .env.backup.* files after verifying deployment"
    fi
fi