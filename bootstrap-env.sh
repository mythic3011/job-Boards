#!/bin/bash
set -euo pipefail

# ─── Usage ────────────────────────────────────────────────────────────────────
# ./bootstrap-env.sh [dev|production]
# dev        — generate missing/weak secrets only, never overwrite existing strong values
# production — force-regenerate ALL secrets regardless of current value

MODE="${1:-dev}"
[[ "$MODE" != "dev" && "$MODE" != "production" ]] && {
    echo "Usage: $0 [dev|production]"
    echo "  dev        — fill missing/weak secrets only"
    echo "  production — force-regenerate ALL secrets (safe deploy)"
    exit 1
}

# ─── Platform-safe sed ───────────────────────────────────────────────────────
SED_CMD="sed -i"
[[ "$OSTYPE" == "darwin"* ]] && SED_CMD="sed -i ''"

# ─── Generators ──────────────────────────────────────────────────────────────
gen_secret()   { openssl rand -hex 32; }
gen_app_key()  { echo "base64:$(openssl rand -base64 32)"; }
gen_uuid()     { command -v uuidgen &>/dev/null \
    && uuidgen | tr '[:upper:]' '[:lower:]' \
    || printf '%08x-%04x-4%03x-%04x-%012x\n' $RANDOM $RANDOM $RANDOM $RANDOM $RANDOM; }
gen_db_pass()  { openssl rand -base64 32 | tr -d "=+/" | cut -c1-32; }
gen_mon_pass() { openssl rand -base64 32 | tr -d "=+/" | cut -c1-24; }

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

# ─── Env file writer ──────────────────────────────────────────────────────────
set_env() {
    local var="$1" val="$2"
    if grep -q "^${var}=" .env 2>/dev/null; then
        eval "$SED_CMD 's|^${var}=.*\$|${var}=${val}|' .env"
    else
        echo "${var}=${val}" >> .env
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
        CROWDSEC_ENROLL_KEY) return ;; # Always manual
        AWS_*)               set_env "$var" "" ; return ;;
        *SECRET*|*KEY*|*TOKEN*) new=$(gen_secret) ;;
        *UUID*)              new=$(gen_uuid) ;;
    esac
    [[ -n "$new" ]] && set_env "$var" "$new"
}

# ─── Preflight ────────────────────────────────────────────────────────────────
[ ! -f .env.example ] && { echo "Error: .env.example not found"; exit 1; }
[ ! -f .env ] && cp .env.example .env && echo "Created .env from .env.example"

echo ""
echo "═══════════════════════════════════════"
echo " Security Bootstrap — ${MODE} mode"
echo "═══════════════════════════════════════"

if [[ "$MODE" == "production" ]]; then
    echo ""
    echo "⚠ Production mode: all secrets will be regenerated."
    echo "  Existing .env backed up to .env.backup.$(date +%Y%m%d-%H%M%S)"
    cp .env ".env.backup.$(date +%Y%m%d-%H%M%S)"
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
        # Production: force regenerate all secrets
        generate_for_var "$var"
    elif is_weak "$val" "$var"; then
        # Dev: only fix weak/missing values
        generate_for_var "$var"
    fi
done < .env.example

# ── 3. Monitoring vars (ensure always exist) ───────────────────────────────────
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

# ── 4. Production: regenerate monitoring.htpasswd ─────────────────────────────
if [[ "$MODE" == "production" ]]; then
    echo ""
    echo "── Regenerating monitoring.htpasswd ──"
    MONITORING_PWD=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2-)
    if command -v docker &>/dev/null && [[ -n "$MONITORING_PWD" ]]; then
        mkdir -p docker/nginx
        docker run --rm httpd:alpine \
            htpasswd -nb admin "$MONITORING_PWD" \
            > docker/nginx/monitoring.htpasswd
        chmod 600 docker/nginx/monitoring.htpasswd
        echo "  ✔ docker/nginx/monitoring.htpasswd regenerated"
    else
        echo "  ⚠ Docker not available — regenerate htpasswd manually"
    fi
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