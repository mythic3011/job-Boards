#!/usr/bin/env bash
# bootstrap-env.sh — hardened edition
# Usage: ./bootstrap-env.sh [dev|production|test]
#   dev        — fill missing/weak secrets only, never overwrite existing strong values
#   production — force-regenerate ALL secrets regardless of current value
#   test       — sync .env.testing with .env credentials (DB_PASSWORD, APP_KEY)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ops/lib/common.sh
source "${SCRIPT_DIR}/ops/lib/common.sh"

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
    BOOTSTRAP_ENV_SET_KEY="$var" \
    BOOTSTRAP_ENV_SET_VALUE="$val" \
    python3 - <<'PYEOF'
import re, os, tempfile

var = os.environ['BOOTSTRAP_ENV_SET_KEY']
val = os.environ['BOOTSTRAP_ENV_SET_VALUE']
path = '.env'
target_path = os.path.realpath(path) if os.path.islink(path) else path

with open(target_path, 'r') as f:
    content = f.read()

pattern = r'^' + re.escape(var) + r'=.*$'
if re.search(pattern, content, flags=re.MULTILINE):
    content = re.sub(pattern, var + '=' + val, content, flags=re.MULTILINE)
else:
    content = content.rstrip('\n') + '\n' + var + '=' + val + '\n'

# Write to a sibling tmp file then atomically rename — crash-safe
dir_ = os.path.dirname(os.path.abspath(target_path))
fd, tmp = tempfile.mkstemp(dir=dir_)
try:
    with os.fdopen(fd, 'w') as f:
        f.write(content)
    os.replace(tmp, target_path)   # atomic on POSIX; works on macOS + Linux
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

# bt_bcrypt_hash (from common.sh) generates the hash directly — no "user:hash" stripping needed.

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
    [[ "$1" == "PROMETHEUS_PASSWORD_HASH" ]] && return 0
    [[ "$1" == "GRAFANA_ADMIN_SECRET_FILE" ]] && return 0
    return 1
}

# Optional override vars: intentionally empty unless an operator provides them
is_optional_var() {
    case "$1" in
        GRAFANA_PASSWORD|PROMETHEUS_PASSWORD|CROWDSEC_ENROLL_KEY) return 0 ;;
    esac
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

# bt_is_valid_bcrypt_hash, bt_bcrypt_hash_matches, bt_normalize_compose_dollars,
# bt_escape_compose_dollars — all provided by common.sh (sourced above).

# ─── Per-variable generator ──────────────────────────────────────────────────
generate_for_var() {
    local var="$1" new=""
    case "$var" in
        APP_KEY)              new=$(gen_app_key)  ;;
        DB_PASSWORD)          new=$(gen_db_pass)  ;;
        REDIS_PASSWORD)       new=$(gen_secret)   ;;
        SESSION_SECRET)       new=$(gen_secret)   ;;
        MONITORING_PASSWORD)  new=$(gen_mon_pass) ;;
        GRAFANA_PASSWORD|PROMETHEUS_PASSWORD)
            # Advanced monitoring overrides stay unset unless explicitly provided.
            return ;;
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

MONITORING_PASSWORD_VAL=$(get_env MONITORING_PASSWORD)
if [[ "$MODE" == "production" ]] || is_weak "$MONITORING_PASSWORD_VAL" "MONITORING_PASSWORD"; then
    set_env "MONITORING_PASSWORD" "$(gen_mon_pass)"
    MPW=$(get_env MONITORING_PASSWORD)
else
    MPW="$MONITORING_PASSWORD_VAL"
    echo "  ✔ MONITORING_PASSWORD already set"
fi

# Identifier — only set if absent, never randomize
MONITORING_USER=$(get_env MONITORING_ADMIN_USERNAME)
if [[ -z "$MONITORING_USER" ]]; then
    set_env "MONITORING_ADMIN_USERNAME" "admin"
    MONITORING_USER="admin"
else
    echo "  ✔ MONITORING_ADMIN_USERNAME already set (${MONITORING_USER})"
fi

# Bcrypt hash — regenerate if missing, invalid, stale, or production mode
EXISTING_HASH_RAW=$(get_env MONITORING_PASSWORD_HASH)
EXISTING_HASH=$(bt_normalize_compose_dollars "$EXISTING_HASH_RAW")
HASH_MISMATCH=false
if [[ -n "$EXISTING_HASH" ]] && bt_is_valid_bcrypt_hash "$EXISTING_HASH"; then
    if ! bt_bcrypt_hash_matches "$EXISTING_HASH" "$MPW"; then
        HASH_MISMATCH=true
    fi
fi

if [[ -z "$EXISTING_HASH" ]] || ! bt_is_valid_bcrypt_hash "$EXISTING_HASH" || [[ "$MODE" == "production" ]] || [[ "$HASH_MISMATCH" == "true" ]]; then
    echo "  Generating bcrypt hash for MONITORING_PASSWORD..."
    HASH=$(bt_bcrypt_hash "$MONITORING_USER" "$MPW") \
        && set_env "MONITORING_PASSWORD_HASH" "$(bt_escape_compose_dollars "$HASH")" \
        || echo "  ✘ bcrypt hash generation failed — set MONITORING_PASSWORD_HASH manually"
else
    EXPECTED_ESCAPED_HASH=$(bt_escape_compose_dollars "$EXISTING_HASH")
    if [[ "$EXISTING_HASH_RAW" != "$EXPECTED_ESCAPED_HASH" ]]; then
        set_env "MONITORING_PASSWORD_HASH" "$EXPECTED_ESCAPED_HASH"
        echo "  ✔ MONITORING_PASSWORD_HASH normalized for Docker Compose"
    else
        echo "  ✔ MONITORING_PASSWORD_HASH already valid"
    fi
fi

# Prometheus password hash — derive from an explicit override or the canonical monitoring password
PROMETHEUS_SOURCE_KEY="PROMETHEUS_PASSWORD"
PROMETHEUS_PW=$(get_env PROMETHEUS_PASSWORD)
if [[ -z "$PROMETHEUS_PW" ]]; then
    PROMETHEUS_SOURCE_KEY="MONITORING_PASSWORD"
    PROMETHEUS_PW="$MPW"
fi

if [[ -n "$PROMETHEUS_PW" ]]; then
    EXISTING_PROM_HASH_RAW=$(get_env PROMETHEUS_PASSWORD_HASH)
    EXISTING_PROM_HASH=$(bt_normalize_compose_dollars "$EXISTING_PROM_HASH_RAW")
    PROM_HASH_MISMATCH=false
    if [[ -n "$EXISTING_PROM_HASH" ]] && bt_is_valid_bcrypt_hash "$EXISTING_PROM_HASH"; then
        if ! bt_bcrypt_hash_matches "$EXISTING_PROM_HASH" "$PROMETHEUS_PW"; then
            PROM_HASH_MISMATCH=true
        fi
    fi

    if [[ -z "$EXISTING_PROM_HASH" ]] || ! bt_is_valid_bcrypt_hash "$EXISTING_PROM_HASH" || [[ "$MODE" == "production" ]] || [[ "$PROM_HASH_MISMATCH" == "true" ]]; then
        echo "  Generating bcrypt hash for PROMETHEUS_PASSWORD_HASH from ${PROMETHEUS_SOURCE_KEY}..."
        HASH=$(bt_bcrypt_hash "admin" "$PROMETHEUS_PW") \
            && set_env "PROMETHEUS_PASSWORD_HASH" "$(bt_escape_compose_dollars "$HASH")" \
            || echo "  ✘ bcrypt hash generation failed — set PROMETHEUS_PASSWORD_HASH manually"
    else
        EXPECTED_PROM_ESCAPED_HASH=$(bt_escape_compose_dollars "$EXISTING_PROM_HASH")
        if [[ "$EXISTING_PROM_HASH_RAW" != "$EXPECTED_PROM_ESCAPED_HASH" ]]; then
            set_env "PROMETHEUS_PASSWORD_HASH" "$EXPECTED_PROM_ESCAPED_HASH"
            echo "  ✔ PROMETHEUS_PASSWORD_HASH normalized for Docker Compose"
        else
            echo "  ✔ PROMETHEUS_PASSWORD_HASH already valid"
        fi
    fi
else
    echo "  ⚠ no monitoring password source available — skipping PROMETHEUS_PASSWORD_HASH generation"
fi

# Grafana admin secret file — materialize from an explicit override or the canonical monitoring password
GRAFANA_SOURCE_KEY="GRAFANA_PASSWORD"
GRAFANA_SECRET_VALUE=$(get_env GRAFANA_PASSWORD)
if [[ -z "$GRAFANA_SECRET_VALUE" ]]; then
    GRAFANA_SOURCE_KEY="MONITORING_PASSWORD"
    GRAFANA_SECRET_VALUE="$MPW"
fi

GRAFANA_SECRET_FILE=$(get_env GRAFANA_ADMIN_SECRET_FILE)
if [[ -z "$GRAFANA_SECRET_FILE" ]]; then
    GRAFANA_SECRET_FILE=".blue-team-vm/runtime/grafana-admin-secret"
    set_env "GRAFANA_ADMIN_SECRET_FILE" "${GRAFANA_SECRET_FILE}"
fi

if [[ -n "$GRAFANA_SECRET_VALUE" ]]; then
    mkdir -p "$(dirname "${GRAFANA_SECRET_FILE}")"
    CURRENT_GRAFANA_SECRET="$(cat "${GRAFANA_SECRET_FILE}" 2>/dev/null || true)"
    if [[ ! -f "${GRAFANA_SECRET_FILE}" ]] || [[ "$MODE" == "production" ]] || [[ "${CURRENT_GRAFANA_SECRET}" != "${GRAFANA_SECRET_VALUE}" ]]; then
        printf '%s' "${GRAFANA_SECRET_VALUE}" > "${GRAFANA_SECRET_FILE}"
        chmod 600 "${GRAFANA_SECRET_FILE}"
        echo "  ✔ GRAFANA_ADMIN_SECRET_FILE materialized from ${GRAFANA_SOURCE_KEY}"
    else
        echo "  ✔ GRAFANA_ADMIN_SECRET_FILE already matches ${GRAFANA_SOURCE_KEY}"
    fi
else
    echo "  ⚠ no monitoring password source available — skipping GRAFANA_ADMIN_SECRET_FILE materialization"
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

# ── 5. Port conflict auto-fix ─────────────────────────────────────────────────
echo ""
echo "── Port conflict check ──"

_check_port_var() {
    local var="$1" default="$2"
    shift 2

    local current new_port reason=""
    local -a reserved_ports=( "$@" )
    current=$(get_env "$var")
    current="${current:-$default}"

    if bt_port_is_reserved "$current" "${reserved_ports[@]}"; then
        reason="conflicted with another defined port"
    elif bt_port_in_use "$current"; then
        reason="was in use"
    fi

    if [[ -n "${reason}" ]]; then
        if bt_find_free_port new_port "${reserved_ports[@]}"; then
            set_env "$var" "$new_port"
            echo "  ✔ ${var}: ${current} → ${new_port} (${reason}, reassigned)"
        else
            echo "  ✘ ${var}: port ${current} ${reason} — no free port found in 3001-9001"
            ISSUES=$((ISSUES + 1))
        fi
    else
        [[ -z "$(get_env "$var")" ]] && set_env "$var" "$current"
        echo "  ✔ ${var}=${current}"
    fi
}

port_conflict_check() {
    local -a reserved_port_vars=( PORT:3000 )
    local -a port_vars=( APP_PORT:80 APP_SSL_PORT:443 VITE_PORT:5173 FORWARD_DB_PORT:5432 FORWARD_REDIS_PORT:6379 )
    local -a reserved_ports=()
    local entry var default current

    for entry in "${reserved_port_vars[@]}"; do
        var="${entry%%:*}"
        default="${entry##*:}"
        current=$(get_env "$var")
        current="${current:-$default}"
        reserved_ports+=("$current")
    done

    for entry in "${port_vars[@]}"; do
        var="${entry%%:*}"
        default="${entry##*:}"
        _check_port_var "$var" "$default" "${reserved_ports[@]}"
        current=$(get_env "$var")
        current="${current:-$default}"
        reserved_ports+=("$current")
    done
}

port_conflict_check

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
    val=$(bt_normalize_compose_dollars "$(get_env "$var")")
    if bt_is_valid_bcrypt_hash "$val"; then
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

audit_optional_secret() {
    local var="$1"
    local val
    val=$(get_env "$var")
    if [[ -z "$val" ]]; then
        echo "  - ${var} — not set (advanced override inactive)"
        return 0
    fi

    if is_weak "$val" "$var" || is_misconfigured "$val" "$var"; then
        echo "  ✘ ${var} — weak or misconfigured"
        ISSUES=$((ISSUES + 1))
    else
        echo "  ✔ ${var} — ok (len: ${#val})"
    fi
}

audit_secret_file() {
    local var="$1"
    local path
    path=$(get_env "$var")
    if [[ -n "$path" && -r "$path" ]]; then
        echo "  ✔ ${var} — readable file (${path})"
    else
        echo "  ✘ ${var} — missing or unreadable file"
        ISSUES=$((ISSUES + 1))
    fi
}

while IFS= read -r line; do
    [[ "$line" =~ ^#.*$|^$ ]] && continue
    [[ "$line" =~ ^([A-Z_][A-Z0-9_]*)= ]] || continue
    var="${BASH_REMATCH[1]}"
    val=$(get_env "$var")

    if [[ "$var" == "GRAFANA_ADMIN_SECRET_FILE" ]]; then
        audit_secret_file "$var"
    elif is_derived_var "$var"; then
        audit_bcrypt "$var"
    elif is_identifier_var "$var"; then
        audit_identifier "$var"
    elif is_optional_var "$var"; then
        audit_optional_secret "$var"
    elif is_secret_var "$var"; then
        audit_secret "$var"
    elif is_misconfigured "$val" "$var"; then
        audit_secret "$var"
    fi
done < .env.example

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
