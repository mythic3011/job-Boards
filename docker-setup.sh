#!/bin/bash
set -euo pipefail

# ─── Usage ────────────────────────────────────────────────────────────────────
# ./docker-setup.sh [full|quick|skip] [dev|production]
SETUP_MODE="${1:-full}"
ENV_MODE="${2:-dev}"

[[ "$ENV_MODE" != "dev" && "$ENV_MODE" != "production" ]] && {
    echo "Usage: $0 [full|quick|skip] [dev|production]"
    exit 1
}

# ── Bootstrap secrets first (mode-aware) ──────────────────────────────────────
./bootstrap-env.sh "$ENV_MODE"

case "$SETUP_MODE" in

    skip)
        ./skip-install.sh
        ;;

    quick)
        docker compose exec laravel.test php artisan migrate:fresh --force
        docker compose exec laravel.test php artisan optimize:clear
        docker compose exec laravel.test npm install
        docker compose exec laravel.test npm run build
        docker compose exec laravel.test composer install
        docker compose restart laravel.test
        ./skip-install.sh
        ;;

    full)
        docker compose exec laravel.test php artisan optimize:clear
        docker compose exec laravel.test composer install
        docker compose exec laravel.test npm install
        docker compose exec laravel.test npm run build
        docker compose restart laravel.test
        sleep 3
        docker compose exec laravel.test php artisan migrate:fresh --force
        docker compose exec laravel.test php artisan db:seed \
            --class=RolePermissionSeeder --force

        # ── Admin credentials ─────────────────────────────────────────────
        ADMIN_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
        TWO_FACTOR_SECRET=$(openssl rand -base64 20 \
            | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-32)

        RECOVERY_CODES=()
        for i in {1..10}; do
            RECOVERY_CODES+=("$(openssl rand -base64 12 \
                | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-8)")
        done
        RECOVERY_CODES_JSON=$(printf '%s\n' "${RECOVERY_CODES[@]}" \
            | jq -R . | jq -s .)

        docker compose exec laravel.test php artisan tinker <<EOF
use App\Models\User;
use Spatie\Permission\Models\Role;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;

\$admin = User::create([
    'idcode'            => 'user_admin_' . \Illuminate\Support\Str::uuid()->toString(),
    'login_id'          => 'admin_' . \Illuminate\Support\Str::random(8),
    'nickname'          => 'System Administrator',
    'email'             => 'admin@example.com',
    'password'          => Hash::make('${ADMIN_PASSWORD}'),
    'user_type'         => 'company',
    'email_verified_at' => now(),
]);
\$admin->assignRole(Role::where('name', 'admin')->first());
\$admin->forceFill([
    'two_factor_secret' =>
        Fortify::currentEncrypter()->encrypt('${TWO_FACTOR_SECRET}'),
    'two_factor_recovery_codes' =>
        Fortify::currentEncrypter()->encrypt(
            json_encode(json_decode('${RECOVERY_CODES_JSON}', true))
        ),
])->save();
EOF

        docker compose exec laravel.test php artisan tinker \
            --execute="App\Models\Setting::markSetupCompleted();"
        docker compose exec laravel.test php artisan optimize:clear

        # ── Output credentials ────────────────────────────────────────────
        APP_NAME=$(grep "^APP_NAME=" .env | cut -d'=' -f2- | tr -d '"')
        APP_NAME="${APP_NAME:-Jobs Board}"
        QR_URL="otpauth://totp/${APP_NAME}:admin@example.com?secret=${TWO_FACTOR_SECRET}&issuer=${APP_NAME// /%20}"

        echo ""
        echo "═══════════════════════════════════════"
        echo " Admin Credentials"
        echo "═══════════════════════════════════════"
        echo "  Email:    admin@example.com"
        echo "  Password: ${ADMIN_PASSWORD}"
        echo "  2FA:      ${TWO_FACTOR_SECRET}"
        echo ""
        echo "  Recovery codes:"
        for i in "${!RECOVERY_CODES[@]}"; do
            printf "    %2d. %s\n" $((i+1)) "${RECOVERY_CODES[$i]}"
        done
        echo ""

        if command -v qrencode &>/dev/null; then
            qrencode -t ANSIUTF8 "$QR_URL"
        else
            ENCODED_URL=$(python3 -c \
                "import urllib.parse; print(urllib.parse.quote('${QR_URL}'))" \
                2>/dev/null || echo "")
            [[ -n "$ENCODED_URL" ]] && \
                echo "  QR: https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${ENCODED_URL}"
        fi

        # ── Save credentials ──────────────────────────────────────────────
        CRED_FILE="admin-$(date +%Y%m%d-%H%M%S).txt"
        {
            echo "Email:    admin@example.com"
            echo "Password: ${ADMIN_PASSWORD}"
            echo "2FA:      ${TWO_FACTOR_SECRET}"
            echo "Recovery: ${RECOVERY_CODES[*]}"
            echo "QR:       ${QR_URL}"
            echo ""
            MONITORING_PWD=$(grep "^MONITORING_PASSWORD=" .env | cut -d'=' -f2- || echo "")
            GRAFANA_PWD=$(grep "^GRAFANA_PASSWORD=" .env | cut -d'=' -f2- || echo "")
            [[ -n "$MONITORING_PWD" ]] && echo "Monitoring: ${MONITORING_PWD}"
            [[ -n "$GRAFANA_PWD"    ]] && echo "Grafana:    ${GRAFANA_PWD}"
        } > "$CRED_FILE"
        chmod 600 "$CRED_FILE"
        echo "  ✔ Credentials saved to ${CRED_FILE}"
        echo "  ⚠ Delete this file after setup"

        # ── Production reminder ───────────────────────────────────────────
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

    *)
        echo "Usage: $0 [full|quick|skip] [dev|production]"
        exit 1
        ;;
esac