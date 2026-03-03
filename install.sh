#!/bin/bash
set -euo pipefail

# Usage: ./install.sh [full|demo|quick|skip|setupAdmin] [dev|production]
#
#   full        — start containers + build assets + migrate (web wizard completes setup)
#   demo        — full + seed admin account + mock data (no web wizard needed)
#   quick       — rebuild assets + migrate:FRESH ⚠ WIPES ALL DATA (containers must be running)
#   skip        — mark setup complete only
#   setupAdmin  — create admin account interactively (containers must be running)

SETUP_MODE="${1:-full}"
ENV_MODE="${2:-dev}"

[[ "$SETUP_MODE" != "full" && "$SETUP_MODE" != "demo" && "$SETUP_MODE" != "quick" && "$SETUP_MODE" != "skip" && "$SETUP_MODE" != "setupAdmin" ]] && {
    echo "Usage: $0 [full|demo|quick|skip|setupAdmin] [dev|production]"
    exit 1
}
[[ "$ENV_MODE" != "dev" && "$ENV_MODE" != "production" ]] && {
    echo "Usage: $0 [full|demo|quick|skip] [dev|production]"
    exit 1
}

check_deps

# ── Fix: typo "borads" → "boards" ─────────────────────────────────────────────
CONTAINER="jobs-boards-laravel.test"

# ── Host user identity (required by Docker Compose / Sail) ────────────────────
export WWWUSER="${WWWUSER:-$(id -u)}"
export WWWGROUP="${WWWGROUP:-$(id -g)}"

# ── Track last assigned port to avoid assigning same free port twice ──────────
LAST_ASSIGNED_PORT=3000

# ── Helpers ───────────────────────────────────────────────────────────────────
# -T: non-TTY safe for CI/CD environments
app() { docker exec -T "$CONTAINER" "$@"; }
err() { echo "$*" >&2; }

check_deps() {
    local missing=()
    local hints=(
        "docker:https://docs.docker.com/get-docker/"
        "python3:https://www.python.org/downloads/"
        "openssl:brew install openssl / apt install openssl"
        "jq:brew install jq / apt install jq"
    )

    for entry in "${hints[@]}"; do
        local cmd="${entry%%:*}"
        if ! command -v "$cmd" &>/dev/null; then
            missing+=("$entry")
        fi
    done

    # docker compose v2 check (plugin, not standalone)
    if ! docker compose version &>/dev/null 2>&1; then
        missing+=("docker compose (plugin):https://docs.docker.com/compose/install/")
    fi

    if [[ ${#missing[@]} -gt 0 ]]; then
        err "Missing required tools:"
        for entry in "${missing[@]}"; do
            err "  ${entry%%:*} — ${entry#*:}"
        done
        exit 1
    fi
}

wait_for() {
    local label="$1" max_attempts="$2"
    shift 2
    local attempts=0 elapsed=0
    printf "%s " "$label"
    until "$@" &>/dev/null 2>&1; do
        attempts=$((attempts + 1))
        elapsed=$((attempts * 2))
        if [[ $attempts -ge $max_attempts ]]; then
            echo ""
            err "$label timed out after ${elapsed}s."
            exit 1
        fi
        printf "."
        sleep 2
    done
    echo " done (${elapsed}s)"
}

port_in_use() {
    local port="$1"
    if command -v ss &>/dev/null; then
        ss -tlnp | grep -qE ":${port}([^0-9]|$)" && return 0
    fi
    if command -v netstat &>/dev/null; then
        netstat -tlnp | grep -qE ":${port}([^0-9]|$)" && return 0
    fi
    # Last resort: attempt TCP connection
    (echo >/dev/tcp/localhost/"$port") &>/dev/null 2>&1 && return 0
    return 1
}

find_free_port() {
    local p=$(( LAST_ASSIGNED_PORT + 1 ))
    while [[ $p -le 9001 ]]; do
        if ! port_in_use "$p"; then
            LAST_ASSIGNED_PORT=$p
            echo "$p"
            return
        fi
        p=$((p + 1))
    done
    echo ""
}

patch_env_port() {
    local key="$1" value="$2"
    if grep -qE "^${key}=" .env 2>/dev/null; then
        python3 - "$key" "$value" <<'PYEOF'
import re, sys
key   = sys.argv[1]
value = sys.argv[2]
path  = '.env'
with open(path, 'r') as f:
    content = f.read()
content = re.sub(
    r'^' + re.escape(key) + r'=.*$',
    key + '=' + value,
    content,
    flags=re.MULTILINE
)
with open(path, 'w') as f:
    f.write(content)
PYEOF
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

check_ports() {
    local app_port ssl_port
    app_port=$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d'=' -f2- || echo "80")
    ssl_port=$(grep -E '^APP_SSL_PORT=' .env 2>/dev/null | cut -d'=' -f2- || echo "443")
    app_port="${app_port:-80}"
    ssl_port="${ssl_port:-443}"

    local blocked=()
    port_in_use "$app_port" && blocked+=("APP_PORT:$app_port")
    port_in_use "$ssl_port" && blocked+=("APP_SSL_PORT:$ssl_port")

    [[ ${#blocked[@]} -eq 0 ]] && return 0

    echo ""
    echo "Port conflict detected:"
    for entry in "${blocked[@]}"; do
        echo "  ${entry%%:*} = ${entry##*:} is already in use"
    done
    echo ""
    read -r -p "Auto-assign free ports in range 3001-9001? [Y/n] " _ans
    if [[ "${_ans,,}" == "n" ]]; then
        err "Aborted. Free the ports or update APP_PORT / APP_SSL_PORT in .env manually."
        exit 1
    fi

    for entry in "${blocked[@]}"; do
        local key="${entry%%:*}"
        local old_port="${entry##*:}"
        local new_port
        new_port=$(find_free_port)
        if [[ -z "$new_port" ]]; then
            err "ERROR: No free port found in range 3001-9001 for ${key}."
            exit 1
        fi
        echo "  ${key}: ${old_port} -> ${new_port}"
        patch_env_port "$key" "$new_port"
        export "$key"="$new_port"
    done
    echo ""
}

start_containers() {
    if ! docker exec -T "$CONTAINER" true &>/dev/null 2>&1; then
        check_ports
        echo "Building laravel.test image..."
        docker compose build laravel.test
        echo "Starting containers..."
        docker compose down --remove-orphans 2>/dev/null || true
        docker compose up -d
        wait_for "Container starting" 30 docker exec -T "$CONTAINER" true
        echo "Installing composer dependencies..."
        app composer install --no-interaction --prefer-dist
    fi
    wait_for "Container ready" 60 app php artisan --version
}

build_assets() {
    app php artisan optimize:clear
    # composer install already run in start_containers — skip duplicate
    app npm install
    app npm run build
    docker compose restart laravel.test
    wait_for "Container ready" 60 app php artisan --version
}

print_monitoring_credentials() {
    local mon_pwd grafana_pwd
    mon_pwd=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2- || echo "")
    grafana_pwd=$(grep "^GRAFANA_PASSWORD=" .env | cut -d'=' -f2- || echo "")

    echo ""
    echo "======================================="
    echo " Monitoring Credentials"
    echo "======================================="
    [[ -n "$mon_pwd" ]]     && echo "  Monitoring: ${mon_pwd}"
    [[ -n "$grafana_pwd" ]] && echo "  Grafana:    ${grafana_pwd}"
    echo ""

    local cred_file="/tmp/monitoring-$(date +%Y%m%d-%H%M%S).txt"
    {
        echo "Monitoring: ${mon_pwd}"
        echo "Grafana:    ${grafana_pwd}"
    } > "$cred_file"
    chmod 600 "$cred_file"
    echo "  Warning: Saved to ${cred_file} — copy and delete after setup"
}

prompt_admin_info() {
    local default_email="admin@example.com"
    local default_nickname="System Administrator"

    echo ""
    echo "======================================="
    echo " Admin Account Setup"
    echo "======================================="
    read -r -p "Use default admin info? [Y/n] " _use_defaults

    if [[ "${_use_defaults,,}" == "n" ]]; then
        read -r -p "  Email [$default_email]: " _input_email
        ADMIN_EMAIL="${_input_email:-$default_email}"

        read -r -p "  Nickname [$default_nickname]: " _input_nickname
        ADMIN_NICKNAME="${_input_nickname:-$default_nickname}"

        while true; do
            read -r -s -p "  Password (min 12 chars, leave blank to auto-generate): " _input_password
            echo ""
            if [[ -z "$_input_password" ]]; then
                ADMIN_PASSWORD=""
                break
            elif [[ ${#_input_password} -lt 12 ]]; then
                echo "  Password must be at least 12 characters."
            else
                read -r -s -p "  Confirm password: " _confirm_password
                echo ""
                if [[ "$_input_password" == "$_confirm_password" ]]; then
                    ADMIN_PASSWORD="$_input_password"
                    break
                else
                    echo "  Passwords do not match, try again."
                fi
            fi
        done
    else
        ADMIN_EMAIL="$default_email"
        ADMIN_NICKNAME="$default_nickname"
        ADMIN_PASSWORD=""
    fi
}

seed_admin() {
    if ! command -v jq &>/dev/null; then
        err "ERROR: 'jq' is required for seed_admin but is not installed."
        exit 1
    fi

    prompt_admin_info

    local admin_password
    if [[ -z "$ADMIN_PASSWORD" ]]; then
        admin_password=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
    else
        admin_password="$ADMIN_PASSWORD"
    fi
    unset ADMIN_PASSWORD

    # Proper base32 TOTP secret (RFC 6238 compliant)
    local two_factor_secret
    two_factor_secret=$(openssl rand 20 | base32 | tr -d '=' | cut -c1-32)

    local recovery_codes=()
    for _ in {1..10}; do
        recovery_codes+=("$(openssl rand -base64 12 | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-8)")
    done

    # Pass sensitive values via environment to avoid heredoc injection
    export _ADMIN_PASSWORD="$admin_password"
    export _ADMIN_EMAIL="$ADMIN_EMAIL"
    export _ADMIN_NICKNAME="$ADMIN_NICKNAME"
    export _2FA_SECRET="$two_factor_secret"
    local recovery_codes_json
    recovery_codes_json=$(printf '%s\n' "${recovery_codes[@]}" | jq -R . | jq -s .)
    export _RECOVERY_JSON="$recovery_codes_json"

    app php artisan tinker --execute="
use App\Models\User;
use Spatie\Permission\Models\Role;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;

\$admin = User::create([
    'idcode'            => 'user_admin_' . \Illuminate\Support\Str::uuid()->toString(),
    'login_id'          => 'admin_' . \Illuminate\Support\Str::random(8),
    'nickname'          => env('_ADMIN_NICKNAME'),
    'email'             => env('_ADMIN_EMAIL'),
    'password'          => Hash::make(env('_ADMIN_PASSWORD')),
    'user_type'         => 'company',
    'email_verified_at' => now(),
]);
\$admin->assignRole(Role::where('name', 'admin')->first());
\$admin->forceFill([
    'two_factor_secret' =>
        Fortify::currentEncrypter()->encrypt(env('_2FA_SECRET')),
    'two_factor_recovery_codes' =>
        Fortify::currentEncrypter()->encrypt(
            json_encode(json_decode(env('_RECOVERY_JSON'), true))
        ),
])->save();
"

    # Clean up sensitive env vars immediately after use
    unset _ADMIN_PASSWORD _ADMIN_EMAIL _ADMIN_NICKNAME _2FA_SECRET _RECOVERY_JSON

    app php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
    app php artisan optimize:clear

    # ── Output credentials ─────────────────────────────────────────────────────
    local app_name encoded_issuer qr_url
    app_name=$(grep "^APP_NAME=" .env | cut -d'=' -f2- | tr -d '"')
    app_name="${app_name:-Jobs Board}"
    encoded_issuer=$(python3 -c \
        "import urllib.parse, sys; print(urllib.parse.quote(sys.argv[1]))" \
        "$app_name" 2>/dev/null || echo "${app_name// /%20}")
    qr_url="otpauth://totp/${encoded_issuer}:${ADMIN_EMAIL}?secret=${two_factor_secret}&issuer=${encoded_issuer}"

    echo ""
    echo "======================================="
    echo " Admin Credentials"
    echo "======================================="
    echo "  Email:    ${ADMIN_EMAIL}"
    echo "  Password: ${admin_password}"
    echo "  2FA:      ${two_factor_secret}"
    echo ""
    echo "  Recovery codes:"
    for i in "${!recovery_codes[@]}"; do
        printf "    %2d. %s\n" $((i+1)) "${recovery_codes[$i]}"
    done
    echo ""

    if command -v qrencode &>/dev/null; then
        qrencode -t ANSIUTF8 "$qr_url"
    else
        local encoded_url
        encoded_url=$(python3 -c \
            "import urllib.parse, sys; print(urllib.parse.quote(sys.argv[1], safe=''))" \
            "$qr_url" 2>/dev/null || echo "")
        [[ -n "$encoded_url" ]] && \
            echo "  QR: https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encoded_url}"
    fi

    # ── Save to /tmp (avoids accidental git commit) ────────────────────────────
    local mon_pwd grafana_pwd
    mon_pwd=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2- || echo "")
    grafana_pwd=$(grep "^GRAFANA_PASSWORD=" .env | cut -d'=' -f2- || echo "")

    local cred_file
    cred_file="/tmp/admin-$(date +%Y%m%d-%H%M%S).txt"
    {
        echo "======================================="
        echo " Generated: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
        echo "======================================="
        echo "Email:    ${ADMIN_EMAIL}"
        echo "Password: ${admin_password}"
        echo "2FA:      ${two_factor_secret}"
        printf 'Recovery: '
        printf '%s ' "${recovery_codes[@]}"
        printf '\n'
        echo "QR:       ${qr_url}"
        echo ""
        [[ -n "$mon_pwd" ]]     && echo "Monitoring: ${mon_pwd}"
        [[ -n "$grafana_pwd" ]] && echo "Grafana:    ${grafana_pwd}"
    } > "$cred_file"
    chmod 600 "$cred_file"

    echo "  Warning: Credentials saved to ${cred_file}"
    echo "    (stored in /tmp -- will be lost on reboot, copy now)"
}

# ── Bootstrap secrets ─────────────────────────────────────────────────────────
# Only regenerate secrets for modes that start fresh
if [[ "$SETUP_MODE" == "full" || "$SETUP_MODE" == "demo" || "$SETUP_MODE" == "setupAdmin" ]]; then
    if ! ./bootstrap-env.sh "$ENV_MODE"; then
        echo "WARNING: bootstrap-env.sh reported an error — continuing, but verify .env is correct."
    fi
fi

# ── Main ──────────────────────────────────────────────────────────────────────
case "$SETUP_MODE" in

    setupAdmin)
        wait_for "Container ready" 60 app php artisan --version
        seed_admin
        ;;

    skip)
        wait_for "Container ready" 60 app php artisan --version
        app php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
        echo "Setup marked as complete."
        ;;

    quick)
        echo ""
        echo "WARNING: 'quick' mode runs migrate:fresh -- ALL existing data will be wiped."
        read -r -p "Continue? [y/N] " _confirm
        [[ "${_confirm,,}" == "y" ]] || { echo "Aborted."; exit 0; }
        wait_for "Container ready" 60 app php artisan --version
        build_assets
        app php artisan migrate:fresh --force
        echo "Quick setup complete."
        ;;

    full)
        start_containers
        build_assets
        app php artisan migrate:fresh --force
        app php artisan db:seed --class=RolePermissionSeeder --force
        print_monitoring_credentials
        echo "Setup complete. Visit the app to finish via the web wizard."
        ;;

    demo)
        start_containers
        build_assets
        app php artisan migrate:fresh --force
        app php artisan db:seed --class=RolePermissionSeeder --force

        echo ""
        echo "-- Seeding demo data --"
        app php artisan db:seed --class=DemoDataSeeder --force

        seed_admin

        if [[ "$ENV_MODE" == "production" ]]; then
            echo ""
            echo "======================================="
            echo " Production Checklist"
            echo "======================================="
            echo "  1. Verify docker compose ps -- all services healthy"
            echo "  2. Set CROWDSEC_ENROLL_KEY if using CrowdSec Console"
            echo "  3. Delete /tmp/admin-*.txt after saving credentials"
            echo "  4. Delete /tmp/monitoring-*.txt after saving credentials"
            echo "  5. Confirm monitoring accessible via Tailscale only"
        fi
        ;;

esac