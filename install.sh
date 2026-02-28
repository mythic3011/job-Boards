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

CONTAINER="jobs-borads-laravel.test-1"

# ── Helpers ───────────────────────────────────────────────────────────────────
app() { docker exec "$CONTAINER" "$@"; }

wait_for_container() {
    echo "Waiting for $CONTAINER to be ready..."
    local attempts=0
    until docker exec "$CONTAINER" php artisan --version &>/dev/null; do
        attempts=$((attempts + 1))
        [[ $attempts -ge 30 ]] && { echo "Container did not become ready in time."; exit 1; }
        sleep 2
    done
    echo "Container ready."
}

start_containers() {
    if ! docker exec "$CONTAINER" true &>/dev/null 2>&1; then
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
    qr_url="otpauth://totp/${app_name}:admin@example.com?secret=${two_factor_secret}&issuer=${app_name// /%20}"

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
            "import urllib.parse; print(urllib.parse.quote('${qr_url}'))" \
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
./bootstrap-env.sh "$ENV_MODE"

case "$SETUP_MODE" in

    skip)
        app php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
        ;;

    quick)
        wait_for_container
        build_assets
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
