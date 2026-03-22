#!/usr/bin/env bash
# bootstrap-env.sh — hardened edition
# Usage: ./bootstrap-env.sh [dev|production|test]
#   dev        — fill missing/weak secrets only, never overwrite existing strong values
#   production — force-regenerate ALL secrets regardless of current value
#   test       — sync .env.testing with .env credentials (DB_PASSWORD, APP_KEY)
set -euo pipefail

# ─── Mode validation ──────────────────────────────────────────────────────────
MODE="${1:-dev}"
[[ "$MODE" == "dev" || "$MODE" == "production" || "$MODE" == "test" ]] || {
    echo "Usage: $0 [dev|production|test]"
    echo "  dev        — fill missing/weak secrets only"
    echo "  production — force-regenerate ALL secrets (safe deploy)"
    echo "  test       — sync .env.testing with .env credentials"
    exit 1
}

# ─── Env helpers (safe for all special chars) ─────────────────────────────────
# get_env: always returns empty string on missing key (never exits under set -e)
get_env() {
    grep -m1 "^${1}=" .env 2>/dev/null | cut -d'=' -f2- || true
}

# set_env: atomic read-modify-write — portable across macOS and Linux.
# All logic delegated to Python so we avoid sed delimiter collisions (base64
# contains /+=) and partial-write on crash (write tmp → os.replace is atomic).
set_env() {
    local var="$1" val="$2"
    python3 - <<PYEOF
import re, os, tempfile

var  = ${var@Q}
val  = ${val@Q}
path = '.env'

with open(path, 'r') as f:
    content = f.read()

pattern = r'^' + re.escape(var) + r'=.*$'
if re.search(pattern, content, flags=re.MULTILINE):
    content = re.sub(pattern, var + '=' + val, content, flags=re.MULTILINE)
else:
    content = content.rstrip('\n') + '\n' + var + '=' + val + '\n'

# Write to a sibling tmp file then atomically rename — crash-safe
dir_ = os.path.dirname(os.path.abspath(path))
fd, tmp = tempfile.mkstemp(dir=dir_)
try:
    with os.fdopen(fd, 'w') as f:
        f.write(content)
    os.replace(tmp, path)   # atomic on POSIX; works on macOS + Linux
except Exception:
    os.unlink(tmp)
    raise
PYEOF
    echo "  ✔ ${var} updated"
}

# ─── Generators ───────────────────────────────────────────────────────────────
gen_secret()  { openssl rand -hex 32; }
gen_app_key() { echo "base64:$(openssl rand -base64 32)"; }
gen_pass()    { openssl rand -base64 48 | tr -d '=+/' | cut -c1-"${1:-32}"; }
gen_db_pass() { gen_pass 32; }
gen_mon_pass(){ gen_pass 24; }

gen_uuid() {
    cat /proc/sys/kernel/random/uuid 2>/dev/null \
    || python3 -c "import uuid; print(uuid.uuid4())" 2>/dev/null \
    || printf '%08x-%04x-4%03x-%04x-%012x\n' \
        $((RANDOM * RANDOM)) $RANDOM $((RANDOM & 0xfff)) \
        $((0x8000 | (RANDOM & 0x3fff))) $((RANDOM * RANDOM * RANDOM))
}

# ─── Bcrypt helper: prefer host htpasswd, fall back to python3-bcrypt, then manual ──
gen_htpasswd() {
    local username="$1" password="$2"
    if command -v htpasswd &>/dev/null; then
        htpasswd -bnB "$username" "$password"
    elif python3 -c "import bcrypt" &>/dev/null 2>&1; then
        local hash
        hash=$(python3 -c "
import bcrypt, sys
pw = sys.argv[1].encode()
print(sys.argv[2] + ':' + bcrypt.hashpw(pw, bcrypt.gensalt(rounds=12)).decode())
" "$password" "$username")
        echo "$hash"
    elif command -v docker &>/dev/null; then
        docker run --rm httpd:alpine htpasswd -bnB "$username" "$password"
    else
        echo "  ✘ Cannot generate htpasswd: install 'apache2-utils' (htpasswd), 'python3-bcrypt', or Docker" >&2
        return 1
    fi
}

# Extract only hash portion from "user:hash" htpasswd output
htpasswd_hash_only() {
    gen_htpasswd "$1" "$2" | cut -d: -f2 | tr -d $'\n\r'
}

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

# ─── Variable classification ──────────────────────────────────────────────────
# Secret vars: generated/rotated by this script
is_secret_var() {
    [[ "$1" =~ (SECRET|KEY|TOKEN|PASSWORD)$ ]] && return 0
    [[ "$1" == "APP_KEY" ]] && return 0
    return 1
}

# Identifier vars: human-readable, never randomly generated
is_identifier_var() {
    [[ "$1" == "MONITORING_ADMIN_USERNAME" ]] && return 0
    return 1
}

# Derived vars: produced from other secrets, skip standard weak-check
is_derived_var() {
    [[ "$1" == "MONITORING_PASSWORD_HASH" ]] && return 0
    return 1
}

# AWS credential vars that should remain empty for local stacks
is_local_only_aws_var() {
    [[ "$1" == AWS_* ]] \
    && [[ "$1" != "AWS_DEFAULT_REGION" ]] \
    && [[ "$1" != "AWS_USE_PATH_STYLE_ENDPOINT" ]] \
    && return 0
    return 1
}

# ─── Weak value detection ─────────────────────────────────────────────────────
is_weak() {
    local val="$1" var="$2"

    # Never check derived or identifier vars here
    is_derived_var "$var"    && return 1
    is_identifier_var "$var" && return 1

    [[ -z "$val" ]] && return 0

    # Booleans handled by is_misconfigured
    [[ "$var" =~ ^(APP_DEBUG|SESSION_ENCRYPT|SESSION_SECURE_COOKIE|AWS_USE_PATH_STYLE_ENDPOINT)$ ]] && return 1

    # AWS local-stack vars should be empty
    if is_local_only_aws_var "$var"; then
        [[ -n "$val" ]] && return 0
        return 1
    fi

    if is_secret_var "$var"; then
        local placeholders=(secret password null changeme example default test
                            your_secret your_key replace_me todo)
        local lower_val
        lower_val=$(printf '%s' "$val" | tr '[:upper:]' '[:lower:]')
        for p in "${placeholders[@]}"; do
            [[ "$lower_val" == "$p" ]] && return 0
        done
        [[ "$var" == "APP_KEY" && ! "$val" =~ ^base64:.{40,}$ ]] && return 0
        [[ ${#val} -lt 24 ]] && return 0
    fi

    return 1
}

# ─── Bcrypt hash validator ────────────────────────────────────────────────────
is_valid_bcrypt() {
    [[ "$1" =~ ^\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}$ ]]
}

# Verify plaintext password against an existing bcrypt hash.
# Returns 0 for match, 1 for mismatch.
bcrypt_matches() {
    local username="$1" password="$2" hash="$3"

    if command -v htpasswd &>/dev/null; then
        local tmp
        tmp=$(mktemp)
        printf '%s:%s\n' "$username" "$hash" > "$tmp"
        if htpasswd -vb "$tmp" "$username" "$password" >/dev/null 2>&1; then
            rm -f "$tmp"
            return 0
        fi
        rm -f "$tmp"
        return 1
    elif python3 -c "import bcrypt" &>/dev/null 2>&1; then
        python3 - "$password" "$hash" <<'PYEOF'
import bcrypt, sys
pw = sys.argv[1].encode()
hashed = sys.argv[2].encode()
raise SystemExit(0 if bcrypt.checkpw(pw, hashed) else 1)
PYEOF
        return $?
    elif command -v docker &>/dev/null; then
        local tmp
        tmp=$(mktemp)
        printf '%s:%s\n' "$username" "$hash" > "$tmp"
        if docker run --rm -v "$tmp:/tmp/htpasswd:ro" httpd:alpine \
            htpasswd -vb /tmp/htpasswd "$username" "$password" >/dev/null 2>&1; then
            rm -f "$tmp"
            return 0
        fi
        rm -f "$tmp"
        return 1
    fi

    return 1
}

# Docker Compose treats $ in .env values as interpolation markers.
# Store bcrypt hashes escaped ("$$") and normalize back before validation/use.
normalize_compose_dollars() {
    local val="$1"
    printf '%s' "${val//\$\$/\$}"
}

escape_compose_dollars() {
    local val="$1"
    printf '%s' "${val//\$/\$\$}"
}

# ─── Per-variable generator ──────────────────────────────────────────────────
generate_for_var() {
    local var="$1" new=""
    case "$var" in
        APP_KEY)              new=$(gen_app_key)  ;;
        DB_PASSWORD)          new=$(gen_db_pass)  ;;
        REDIS_PASSWORD)       new=$(gen_secret)   ;;
        SESSION_SECRET)       new=$(gen_secret)   ;;
        MONITORING_PASSWORD)  new=$(gen_mon_pass) ;;
        GRAFANA_PASSWORD)     new=$(gen_mon_pass) ;;
        MONITORING_ADMIN_USERNAME)
            # Identifier: set to 'admin' only if currently empty; never randomize
            local cur
            cur=$(get_env "$var")
            if [[ -z "$cur" ]]; then
                set_env "$var" "admin"
            else
                echo "  ✔ ${var} already set (${cur})"
            fi
            return ;;
        MONITORING_PASSWORD_HASH)
            # Derived — regenerated separately after MONITORING_PASSWORD is set
            return ;;
        CROWDSEC_ENROLL_KEY)
            echo "  ⚠ CROWDSEC_ENROLL_KEY — manual setup required (app.crowdsec.net)"
            return ;;
        AWS_*)
            set_env "$var" ""
            return ;;
        *UUID*)               new=$(gen_uuid)     ;;
        *SECRET*|*KEY*|*TOKEN*|*PASSWORD*) new=$(gen_secret) ;;
    esac
    [[ -n "$new" ]] && set_env "$var" "$new" || true
}

# ─── Preflight ────────────────────────────────────────────────────────────────
[[ -f .env.example ]] || { echo "Error: .env.example not found"; exit 1; }
[[ -f .env ]] || { cp .env.example .env; echo "Created .env from .env.example"; }

# ─── Test mode ────────────────────────────────────────────────────────────────
if [[ "$MODE" == "test" ]]; then
    echo ""
    echo "═══════════════════════════════════════"
    echo " Test Environment Sync"
    echo "═══════════════════════════════════════"

    APP_KEY=$(get_env APP_KEY)
    DB_PASSWORD=$(get_env DB_PASSWORD)

    [[ -z "$APP_KEY"    ]] && { echo "  ✘ APP_KEY not found in .env — run './bootstrap-env.sh dev' first"; exit 1; }
    [[ -z "$DB_PASSWORD" ]] && { echo "  ✘ DB_PASSWORD not found in .env — run './bootstrap-env.sh dev' first"; exit 1; }

    # NOTE: .env.testing reuses APP_KEY (required for consistent encryption) and
    # DB_PASSWORD (required for the shared ephemeral test container).
    # If your CI uses isolated containers, inject separate credentials there instead.
    cat > .env.testing <<EOF
APP_NAME=Laravel
APP_ENV=testing
APP_KEY=${APP_KEY}
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=testing
DB_USERNAME=jobs_user
DB_PASSWORD=${DB_PASSWORD}

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array

INSTALL_ALLOWED_IPS=
INSTALL_TOKEN=

BCRYPT_ROUNDS=4
EOF

    echo "  ✔ .env.testing synced"
    echo ""
    echo "✔ Test environment ready"
    exit 0
fi

# ─── Detect running postgres (dev only) ──────────────────────────────────────
# Check by compose project label rather than image name to avoid false positives
# from unrelated postgres containers.
POSTGRES_RUNNING=false
if [[ "$MODE" == "dev" ]]; then
    PROJECT_NAME=$(get_env COMPOSE_PROJECT_NAME)
    PROJECT_NAME="${PROJECT_NAME:-jobboard}"
    if docker ps \
        --filter "label=com.docker.compose.project=${PROJECT_NAME}" \
        --filter "label=com.docker.compose.service=postgres" \
        --format '{{.Names}}' 2>/dev/null | grep -q .; then
        POSTGRES_RUNNING=true
    fi
fi

echo ""
echo "═══════════════════════════════════════"
echo " Security Bootstrap — ${MODE} mode"
echo "═══════════════════════════════════════"

if [[ "$MODE" == "production" ]]; then
    echo ""
    echo "⚠ Production mode: all secrets will be regenerated."
    BACKUP_DATE=$(date -u +%Y%m%d-%H%M%S 2>/dev/null || date +%Y%m%d-%H%M%S)
    cp .env ".env.backup.${BACKUP_DATE}"
    echo "  Existing .env backed up to .env.backup.${BACKUP_DATE}"
fi

# ── 1. Boolean security settings ──────────────────────────────────────────────
echo ""
echo "── Boolean security settings ──"

while IFS= read -r line; do
    [[ "$line" =~ ^#.*$|^$ ]] && continue
    [[ "$line" =~ ^([A-Z_][A-Z0-9_]*)= ]] || continue
    var="${BASH_REMATCH[1]}"
    val=$(get_env "$var")
    if is_misconfigured "$val" "$var"; then
        case "$var" in
            SESSION_ENCRYPT|SESSION_SECURE_COOKIE) set_env "$var" "true"  ;;
            APP_DEBUG)                             set_env "$var" "false" ;;
        esac
    fi
done < .env.example

if [[ "$MODE" == "production" ]]; then
    set_env "APP_ENV"   "production"
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

    is_derived_var "$var" && continue  # handled separately

    val=$(get_env "$var")

    if [[ "$MODE" == "production" ]] && is_secret_var "$var"; then
        generate_for_var "$var"
    elif is_weak "$val" "$var"; then
        if [[ "$var" == "DB_PASSWORD" && "$POSTGRES_RUNNING" == "true" ]]; then
            echo "  ⚠ DB_PASSWORD is weak but project postgres is running — skipping (fix manually)"
        else
            generate_for_var "$var"
        fi
    fi
done < .env.example

# ── 3. Monitoring credentials ──────────────────────────────────────────────────
echo ""
echo "── Monitoring credentials ──"

for var in MONITORING_PASSWORD GRAFANA_PASSWORD; do
    val=$(get_env "$var")
    if [[ "$MODE" == "production" ]] || is_weak "$val" "$var"; then
        set_env "$var" "$(gen_mon_pass)"
    else
        echo "  ✔ ${var} already set"
    fi
done

# Identifier — only set if absent, never randomize
MONITORING_USER=$(get_env MONITORING_ADMIN_USERNAME)
if [[ -z "$MONITORING_USER" ]]; then
    set_env "MONITORING_ADMIN_USERNAME" "admin"
    MONITORING_USER="admin"
else
    echo "  ✔ MONITORING_ADMIN_USERNAME already set (${MONITORING_USER})"
fi

# Re-read password now that it may have been regenerated
MPW=$(get_env MONITORING_PASSWORD)

# Bcrypt hash — regenerate if missing, invalid, stale, or production mode
EXISTING_HASH_RAW=$(get_env MONITORING_PASSWORD_HASH)
EXISTING_HASH=$(normalize_compose_dollars "$EXISTING_HASH_RAW")
HASH_MISMATCH=false
if [[ -n "$EXISTING_HASH" ]] && is_valid_bcrypt "$EXISTING_HASH"; then
    if ! bcrypt_matches "$MONITORING_USER" "$MPW" "$EXISTING_HASH"; then
        HASH_MISMATCH=true
    fi
fi

if [[ -z "$EXISTING_HASH" ]] || ! is_valid_bcrypt "$EXISTING_HASH" || [[ "$MODE" == "production" ]] || [[ "$HASH_MISMATCH" == "true" ]]; then
    echo "  Generating bcrypt hash for MONITORING_PASSWORD..."
    HASH=$(htpasswd_hash_only "$MONITORING_USER" "$MPW") \
        && set_env "MONITORING_PASSWORD_HASH" "$(escape_compose_dollars "$HASH")" \
        || echo "  ✘ bcrypt hash generation failed — set MONITORING_PASSWORD_HASH manually"
else
    EXPECTED_ESCAPED_HASH=$(escape_compose_dollars "$EXISTING_HASH")
    if [[ "$EXISTING_HASH_RAW" != "$EXPECTED_ESCAPED_HASH" ]]; then
        set_env "MONITORING_PASSWORD_HASH" "$EXPECTED_ESCAPED_HASH"
        echo "  ✔ MONITORING_PASSWORD_HASH normalized for Docker Compose"
    else
        echo "  ✔ MONITORING_PASSWORD_HASH already valid"
    fi
fi

# Session secret — server-side only, never exposed to frontend
SESSION_SECRET_VAL=$(get_env SESSION_SECRET)
if [[ -z "$SESSION_SECRET_VAL" || "$MODE" == "production" ]]; then
    set_env "SESSION_SECRET" "$(gen_secret)"
else
    echo "  ✔ SESSION_SECRET already set"
fi

# ── 4. Frontend public config (no secrets) ────────────────────────────────────
FRONTEND_ENV="docker/auth-service/frontend/.env"
if [[ -d "docker/auth-service/frontend" ]]; then
    echo ""
    echo "── Frontend public config ──"
    # Only public, non-secret vars. SESSION_SECRET stays server-side.
    cat > "$FRONTEND_ENV" <<'EOF'
VITE_AUTH_VERIFY=/monitoring/auth/verify
VITE_AUTH_CHECK=/monitoring/auth/check
VITE_REDIRECT_DEFAULT=/monitoring/grafana/
EOF
    echo "  ✔ docker/auth-service/frontend/.env written (public vars only)"
fi

# ── 5. Nginx htpasswd ─────────────────────────────────────────────────────────
echo ""
echo "── Nginx htpasswd ──"

MPW=$(get_env MONITORING_PASSWORD)
MONITORING_USER=$(get_env MONITORING_ADMIN_USERNAME)
MONITORING_USER="${MONITORING_USER:-admin}"

if [[ -n "$MPW" ]]; then
    mkdir -p docker/nginx/htpasswd
    if gen_htpasswd "$MONITORING_USER" "$MPW" \
        > docker/nginx/htpasswd/monitoring.htpasswd 2>/dev/null; then
        chmod 600 docker/nginx/htpasswd/monitoring.htpasswd
        echo "  ✔ docker/nginx/htpasswd/monitoring.htpasswd regenerated (user: ${MONITORING_USER})"
    else
        echo "  ✘ htpasswd generation failed — see instructions above"
        echo "    Manual: htpasswd -bnB ${MONITORING_USER} '<password>' > docker/nginx/htpasswd/monitoring.htpasswd"
    fi
else
    echo "  ⚠ MONITORING_PASSWORD not set — skipping htpasswd"
fi

# ── 6. Final audit ─────────────────────────────────────────────────────────────
echo ""
echo "── Final audit ──"
ISSUES=0

audit_secret() {
    local var="$1"
    local val
    val=$(get_env "$var")
    if is_weak "$val" "$var" || is_misconfigured "$val" "$var"; then
        echo "  ✘ ${var} — weak or misconfigured"
        ISSUES=$((ISSUES + 1))
    else
        echo "  ✔ ${var} — ok (len: ${#val})"
    fi
}

audit_bcrypt() {
    local var="$1"
    local val
    val=$(normalize_compose_dollars "$(get_env "$var")")
    if is_valid_bcrypt "$val"; then
        echo "  ✔ ${var} — valid bcrypt hash"
    else
        echo "  ✘ ${var} — missing or invalid bcrypt hash"
        ISSUES=$((ISSUES + 1))
    fi
}

audit_identifier() {
    local var="$1"
    local val
    val=$(get_env "$var")
    if [[ -n "$val" ]]; then
        echo "  ✔ ${var} — set (${val})"
    else
        echo "  ✘ ${var} — not set"
        ISSUES=$((ISSUES + 1))
    fi
}

audit_secret APP_KEY
audit_secret APP_DEBUG
audit_secret DB_PASSWORD
audit_secret REDIS_PASSWORD
audit_secret SESSION_ENCRYPT
audit_secret SESSION_SECURE_COOKIE
audit_secret SESSION_SECRET
audit_identifier MONITORING_ADMIN_USERNAME
audit_secret MONITORING_PASSWORD
audit_bcrypt  MONITORING_PASSWORD_HASH
audit_secret GRAFANA_PASSWORD

if [[ "$MODE" == "production" ]]; then
    echo ""
    echo "── Production-only checks ──"
    APP_ENV_VAL=$(get_env APP_ENV)
    LOG_LEVEL_VAL=$(get_env LOG_LEVEL)

    [[ "$APP_ENV_VAL" == "production" ]] \
        && echo "  ✔ APP_ENV=production" \
        || { echo "  ✘ APP_ENV must be 'production' (currently: '${APP_ENV_VAL}')"; ISSUES=$((ISSUES+1)); }

    [[ "$LOG_LEVEL_VAL" == "error" || "$LOG_LEVEL_VAL" == "warning" ]] \
        && echo "  ✔ LOG_LEVEL=${LOG_LEVEL_VAL}" \
        || { echo "  ✘ LOG_LEVEL should be 'error' in production (currently: '${LOG_LEVEL_VAL}')"; ISSUES=$((ISSUES+1)); }

    CROWDSEC_KEY=$(get_env CROWDSEC_ENROLL_KEY)
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
        echo "  4. Delete .env.backup.* after verifying deployment"
    fi
fi