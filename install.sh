#!/bin/bash
set -euo pipefail

# Usage: ./install.sh [full|demo|quick|skip] [dev|production]
#
#   full  — start containers + build assets + migrate (web wizard completes setup)
#   demo  — full + seed admin account + mock data (no web wizard needed)
#   quick — migrate + rebuild assets (containers must be running)
#   skip  — mark setup complete only
SETUP_MODE="${1:-full}"
ENV_MODE="${2:-dev}"

[[ "$SETUP_MODE" != "full" && "$SETUP_MODE" != "demo" && "$SETUP_MODE" != "quick" && "$SETUP_MODE" != "skip" ]] && {
    echo "Usage: $0 [full|demo|quick|skip] [dev|production]"
    exit 1
}
[[ "$ENV_MODE" != "dev" && "$ENV_MODE" != "production" ]] && {
    echo "Usage: $0 [full|demo|quick|skip] [dev|production]"
    exit 1
}

CONTAINER="jobs-borads-laravel.test"

# ── Host user identity (required by Docker Compose / Sail) ────────────────────
export WWWUSER="${WWWUSER:-$(id -u)}"
export WWWGROUP="${WWWGROUP:-$(id -g)}"

# ── Helpers ───────────────────────────────────────────────────────────────────
app() { docker exec "$CONTAINER" "$@"; }

wait_for_container() {
    echo "Waiting for $CONTAINER to be ready..."
    local attempts=0
    until docker exec "$CONTAINER" php artisan --version &>/dev/null; do
        attempts=$((attempts + 1))
        [[ $attempts -ge 60 ]] && { echo "Container did not become ready in time."; exit 1; }
        sleep 2
    done
    echo "Container ready."
}

port_in_use() {
    local port="$1"
    ss -tlnp 2>/dev/null | grep -qE ":${port}([^0-9]|$)" || \
    netstat -tlnp 2>/dev/null | grep -qE ":${port}([^0-9]|$)"
}

find_free_port() {
    local start="$1"
    local p="$start"
    while [[ $p -le 9001 ]]; do
        port_in_use "$p" || { echo "$p"; return; }
        p=$((p + 1))
    done
    echo ""
}

patch_env_port() {
    local key="$1" value="$2"
    if grep -qE "^${key}=" .env 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

check_ports() {
    local app_port ssl_port
    app_port=$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d'=' -f2- || echo "80")
    ssl_port=$(grep -E '^APP_SSL_PORT=' .env 2>/dev/null | cut -d'=' -f2- || echo "443")
    app_port="${app_port:-80}"
    ssl_port="${ssl_port:-443}"

    local blocked=()
    port_in_use "$app_port"  && blocked+=("APP_PORT:$app_port")
    port_in_use "$ssl_port"  && blocked+=("APP_SSL_PORT:$ssl_port")

    [[ ${#blocked[@]} -eq 0 ]] && return 0

    echo ""
    echo "Port conflict detected:"
    for entry in "${blocked[@]}"; do
        echo "  ${entry%%:*} = ${entry##*:} is already in use"
    done
    echo ""
    read -r -p "Auto-assign free ports in range 3001–9001? [Y/n] " _ans
    if [[ "${_ans,,}" == "n" ]]; then
        echo "Aborted. Free the ports or update APP_PORT / APP_SSL_PORT in .env manually."
        exit 1
    fi

    for entry in "${blocked[@]}"; do
        local key="${entry%%:*}"
        local old_port="${entry##*:}"
        local new_port
        new_port=$(find_free_port 3001)
        if [[ -z "$new_port" ]]; then
            echo "ERROR: No free port found in range 3001–9001 for ${key}."
            exit 1
        fi
        echo "  ${key}: ${old_port} → ${new_port}"
        patch_env_port "$key" "$new_port"
        # Avoid reusing the same port for SSL
        export "$key"="$new_port"
    done
    echo ""
}

start_containers() {
    if ! docker exec "$CONTAINER" true &>/dev/null 2>&1; then
        check_ports
        echo "Building laravel.test image..."
        docker compose build laravel.test
        echo "Starting containers..."
        docker compose up -d
    fi
    wait_for_container
}

build_assets() {
    app php artisan optimize:clear
    app composer install
    app npm install
    app npm run build
    docker compose restart laravel.test
    wait_for_container
}

seed_admin() {
    if ! command -v jq &>/dev/null; then
        echo "ERROR: 'jq' is required for seed_admin but is not installed."
        exit 1
    fi
    local admin_password two_factor_secret recovery_codes_json
    admin_password=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
    two_factor_secret=$(openssl rand -base64 20 \
        | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-32)

    local recovery_codes=()
    for i in {1..10}; do
        recovery_codes+=("$(openssl rand -base64 12 \
            | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-8)")
    done
    recovery_codes_json=$(printf '%s\n' "${recovery_codes[@]}" | jq -R . | jq -s .)

    app php artisan tinker <<EOF
use App\Models\User;
use Spatie\Permission\Models\Role;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;

\$admin = User::create([
    'idcode'            => 'user_admin_' . \Illuminate\Support\Str::uuid()->toString(),
    'login_id'          => 'admin_' . \Illuminate\Support\Str::random(8),
    'nickname'          => 'System Administrator',
    'email'             => 'admin@example.com',
    'password'          => Hash::make('${admin_password}'),
    'user_type'         => 'company',
    'email_verified_at' => now(),
]);
\$admin->assignRole(Role::where('name', 'admin')->first());
\$admin->forceFill([
    'two_factor_secret' =>
        Fortify::currentEncrypter()->encrypt('${two_factor_secret}'),
    'two_factor_recovery_codes' =>
        Fortify::currentEncrypter()->encrypt(
            json_encode(json_decode('${recovery_codes_json}', true))
        ),
])->save();
EOF

    app php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
    app php artisan optimize:clear

    # ── Output credentials ─────────────────────────────────────────────
    local app_name qr_url
    app_name=$(grep "^APP_NAME=" .env | cut -d'=' -f2- | tr -d '"')
    app_name="${app_name:-Jobs Board}"
    local encoded_issuer
    encoded_issuer=$(python3 -c "import urllib.parse; print(urllib.parse.quote('${app_name}'))" 2>/dev/null || echo "${app_name// /%20}")
    qr_url="otpauth://totp/${encoded_issuer}:admin@example.com?secret=${two_factor_secret}&issuer=${encoded_issuer}"

    echo ""
    echo "═══════════════════════════════════════"
    echo " Admin Credentials"
    echo "═══════════════════════════════════════"
    echo "  Email:    admin@example.com"
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
            "import urllib.parse; print(urllib.parse.quote('${qr_url}', safe=''))" \
            2>/dev/null || echo "")
        [[ -n "$encoded_url" ]] && \
            echo "  QR: https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encoded_url}"
    fi

    # ── Save credentials ───────────────────────────────────────────────
    local cred_file
    cred_file="admin-$(date +%Y%m%d-%H%M%S).txt"
    {
        echo "Email:    admin@example.com"
        echo "Password: ${admin_password}"
        echo "2FA:      ${two_factor_secret}"
        echo "Recovery: ${recovery_codes[*]}"
        echo "QR:       ${qr_url}"
        echo ""
        local mon_pwd grafana_pwd
        mon_pwd=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2- || echo "")
        grafana_pwd=$(grep "^GRAFANA_PASSWORD=" .env | cut -d'=' -f2- || echo "")
        [[ -n "$mon_pwd" ]]     && echo "Monitoring: ${mon_pwd}"
        [[ -n "$grafana_pwd" ]] && echo "Grafana:    ${grafana_pwd}"
    } > "$cred_file"
    chmod 600 "$cred_file"
    echo "  Credentials saved to ${cred_file} — delete after setup"
}

# ── Bootstrap secrets ─────────────────────────────────────────────────────────
# Only regenerate secrets for modes that start fresh; skip/quick reuse existing env.
if [[ "$SETUP_MODE" == "full" || "$SETUP_MODE" == "demo" ]]; then
    if ! ./bootstrap-env.sh "$ENV_MODE"; then
        echo "WARNING: bootstrap-env.sh reported an error — continuing, but verify .env is correct."
    fi
fi

case "$SETUP_MODE" in

    skip)
        wait_for_container
        app php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
        ;;

    quick)
        wait_for_container
        build_assets
        echo "WARNING: 'quick' mode runs migrate:fresh — all existing data will be wiped."
        read -r -p "Continue? [y/N] " _confirm
        [[ "${_confirm,,}" == "y" ]] || { echo "Aborted."; exit 0; }
        app php artisan migrate:fresh --force
        ;;

    full)
        start_containers
        build_assets
        app php artisan migrate:fresh --force
        app php artisan db:seed --class=RolePermissionSeeder --force

        echo ""
        echo "═══════════════════════════════════════"
        echo " Monitoring Credentials"
        echo "═══════════════════════════════════════"
        MONITORING_PWD=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2- || echo "")
        GRAFANA_PWD=$(grep "^GRAFANA_PASSWORD=" .env | cut -d'=' -f2- || echo "")
        [[ -n "$MONITORING_PWD" ]] && echo "  Monitoring: ${MONITORING_PWD}"
        [[ -n "$GRAFANA_PWD" ]]    && echo "  Grafana:    ${GRAFANA_PWD}"
        echo ""
        echo "Setup complete. Visit the app to finish via the web wizard."
        ;;

    demo)
        start_containers
        build_assets
        app php artisan migrate:fresh --force
        app php artisan db:seed --class=RolePermissionSeeder --force

        echo ""
        echo "── Seeding demo data ──"
        app php artisan db:seed --class=DemoDataSeeder --force

        seed_admin

        if [[ "$ENV_MODE" == "production" ]]; then
            echo ""
            echo "═══════════════════════════════════════"
            echo " Production Checklist"
            echo "═══════════════════════════════════════"
            echo "  1. Verify docker compose ps — all services healthy"
            echo "  2. Set CROWDSEC_ENROLL_KEY if using CrowdSec Console"
            echo "  3. Delete admin-*.txt and .env.backup.* after saving creds"
            echo "  4. Confirm monitoring accessible via Tailscale only"
        fi
        ;;
esac
