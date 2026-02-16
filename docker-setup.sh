#!/bin/bash
set -e
MODE="${1:-full}"
./bootstrap-env.sh
[ -z "$(grep '^DB_PASSWORD=' .env 2>/dev/null | cut -d'=' -f2)" ] && {
    DB_PWD="$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)"
    grep -q '^DB_PASSWORD=' .env && { [[ "$OSTYPE" == "darwin"* ]] && sed -i '' "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PWD|" .env || sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PWD|" .env; } || echo "DB_PASSWORD=$DB_PWD" >> .env
}
case "$MODE" in
    skip) ./skip-install.sh ;;
    quick)
        docker compose exec laravel.test php artisan optimize:clear
        docker compose exec laravel.test npm install
        docker compose exec laravel.test npm run build
        docker compose exec laravel.test composer install
        docker compose restart laravel.test
        docker compose exec laravel.test php artisan migrate
        ./skip-install.sh ;;
    full)
        docker compose exec laravel.test php artisan optimize:clear
        docker compose exec laravel.test composer install
        docker compose exec laravel.test npm install
        docker compose exec laravel.test npm run build
        docker compose restart laravel.test
        sleep 3
        docker compose exec laravel.test php artisan migrate:fresh --force
        docker compose exec laravel.test php artisan db:seed --class=RolePermissionSeeder --force
        ADMIN_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
        TWO_FACTOR_SECRET=$(openssl rand -base64 20 | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-32)
        RECOVERY_CODES=()
        for i in {1..10}; do RECOVERY_CODES+=("$(openssl rand -base64 12 | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-8)"); done
        RECOVERY_CODES_JSON=$(printf '%s\n' "${RECOVERY_CODES[@]}" | jq -R . | jq -s .)
        docker compose exec laravel.test php artisan tinker <<EOF
use App\Models\User; use Spatie\Permission\Models\Role; use Laravel\Fortify\Fortify; use Illuminate\Support\Facades\Hash;
\$admin = User::create(['idcode' => 'user_admin_' . \Illuminate\Support\Str::uuid()->toString(), 'login_id' => 'admin_' . \Illuminate\Support\Str::random(8), 'nickname' => 'System Administrator', 'email' => 'admin@example.com', 'password' => Hash::make('$ADMIN_PASSWORD'), 'user_type' => 'company', 'email_verified_at' => now()]);
\$admin->assignRole(Role::where('name', 'admin')->first());
\$admin->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('$TWO_FACTOR_SECRET'), 'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(json_decode('$RECOVERY_CODES_JSON', true)))])->save();
EOF
        docker compose exec laravel.test php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
        docker compose exec laravel.test php artisan optimize:clear
        APP_NAME=$(grep "^APP_NAME=" .env | cut -d '=' -f2- | tr -d '"' || echo "Jobs Board")
        QR_URL="otpauth://totp/${APP_NAME}:admin@example.com?secret=${TWO_FACTOR_SECRET}&issuer=${APP_NAME// /%20}"
        echo "Admin: admin@example.com | Password: ${ADMIN_PASSWORD} | 2FA: ${TWO_FACTOR_SECRET}"
        for i in "${!RECOVERY_CODES[@]}"; do printf "%2d. %s\n" $((i+1)) "${RECOVERY_CODES[$i]}"; done
        command -v qrencode &>/dev/null && qrencode -t ANSIUTF8 "$QR_URL" || echo "QR: https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=$(echo -n "$QR_URL" | jq -sRr @uri)"
        cat > "admin-$(date +%Y%m%d-%H%M%S).txt" <<CREDS
Email: admin@example.com
Password: ${ADMIN_PASSWORD}
2FA: ${TWO_FACTOR_SECRET}
Recovery: $(for i in "${!RECOVERY_CODES[@]}"; do printf "%s " "${RECOVERY_CODES[$i]}"; done)
QR: ${QR_URL}
CREDS
        ;;
    *) echo "Usage: $0 [full|quick|skip]"; exit 1 ;;
esac
