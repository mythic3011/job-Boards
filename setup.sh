#!/bin/bash

# Jobs Board - Quick Setup Script
# This script sets up the application with a default admin user and 2FA

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}   Jobs Board - Quick Setup${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Initialize environment file
echo -e "${YELLOW}Initializing environment file...${NC}"
[ ! -f .env ] && ([ -f .env.example ] && cp .env.example .env || touch .env)
[ -z "$(grep '^APP_KEY=base64:' .env 2>/dev/null)" ] && docker compose exec laravel.test php artisan key:generate --force 2>/dev/null || true
[ -z "$(grep '^DB_PASSWORD=' .env 2>/dev/null | cut -d'=' -f2)" ] && (DB_PWD="${DB_PASSWORD:-$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)}" && grep -q '^DB_PASSWORD=' .env && ([[ "$OSTYPE" == "darwin"* ]] && sed -i '' "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PWD|" .env || sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PWD|" .env) || echo "DB_PASSWORD=$DB_PWD" >> .env) || true
echo -e "${GREEN}✓ Environment initialized${NC}"

# Clear caches and optimize
echo -e "${YELLOW}Clearing caches...${NC}"
docker compose exec laravel.test php artisan optimize:clear
docker compose exec laravel.test php artisan config:clear
docker compose exec laravel.test php artisan route:clear
echo -e "${GREEN}✓ Caches cleared${NC}"

# Install dependencies
echo -e "${YELLOW}Installing dependencies...${NC}"
docker compose exec laravel.test composer install
docker compose exec laravel.test npm install
echo -e "${GREEN}✓ Dependencies installed${NC}"

# Build frontend assets
echo -e "${YELLOW}Building frontend assets...${NC}"
docker compose exec laravel.test npm run build
echo -e "${GREEN}✓ Frontend built${NC}"

# Restart container
echo -e "${YELLOW}Restarting container...${NC}"
docker compose restart laravel.test
sleep 3
echo -e "${GREEN}✓ Container restarted${NC}"

# Run migrations (includes sessions table)
echo -e "${YELLOW}Running database migrations...${NC}"
docker compose exec laravel.test php artisan migrate:fresh --force
echo -e "${GREEN}✓ Database migrated${NC}"

# Seed roles and permissions
echo -e "${YELLOW}Seeding roles and permissions...${NC}"
docker compose exec laravel.test php artisan db:seed --class=RolePermissionSeeder --force
echo -e "${GREEN}✓ Roles and permissions seeded${NC}"

# Generate secure password using openssl
echo -e "${YELLOW}Generating secure admin password...${NC}"
ADMIN_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
echo -e "${GREEN}✓ Password generated${NC}"

# Generate 2FA secret
echo -e "${YELLOW}Generating 2FA secret...${NC}"
TWO_FACTOR_SECRET=$(openssl rand -base64 20 | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-32)
echo -e "${GREEN}✓ 2FA secret generated${NC}"

# Generate recovery codes
echo -e "${YELLOW}Generating recovery codes...${NC}"
RECOVERY_CODES=()
for i in {1..10}; do
    CODE=$(openssl rand -base64 12 | tr -d "=+/" | tr '[:lower:]' '[:upper:]' | cut -c1-8)
    RECOVERY_CODES+=("$CODE")
done
echo -e "${GREEN}✓ Recovery codes generated${NC}"

# Create admin user via PHP
echo -e "${YELLOW}Creating admin user...${NC}"

# Convert recovery codes array to JSON
RECOVERY_CODES_JSON=$(printf '%s\n' "${RECOVERY_CODES[@]}" | jq -R . | jq -s .)

docker compose exec laravel.test php artisan tinker <<EOF
use App\Models\User;
use Spatie\Permission\Models\Role;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;

\$admin = User::create([
    'idcode' => 'user_admin_' . \Illuminate\Support\Str::uuid()->toString(),
    'login_id' => 'admin_' . \Illuminate\Support\Str::random(8),
    'nickname' => 'System Administrator',
    'email' => 'admin@example.com',
    'password' => Hash::make('$ADMIN_PASSWORD'),
    'user_type' => 'company',
    'email_verified_at' => now(),
]);

\$adminRole = Role::where('name', 'admin')->first();
\$admin->assignRole(\$adminRole);

\$recoveryCodes = json_decode('$RECOVERY_CODES_JSON', true);

\$admin->forceFill([
    'two_factor_secret' => Fortify::currentEncrypter()->encrypt('$TWO_FACTOR_SECRET'),
    'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(\$recoveryCodes)),
])->save();

echo "Admin user created successfully\n";
EOF

echo -e "${GREEN}✓ Admin user created${NC}"

# Mark setup as completed
docker compose exec laravel.test php artisan tinker <<EOF
use App\Models\Setting;
Setting::markSetupCompleted();
echo "Setup marked as completed\n";
EOF

echo -e "${GREEN}✓ Setup completed${NC}"

# Final cache clear and optimization
echo -e "${YELLOW}Clearing caches...${NC}"
docker compose exec laravel.test php artisan optimize:clear
docker compose exec laravel.test php artisan config:clear
docker compose exec laravel.test php artisan route:clear
echo -e "${GREEN}✓ Caches cleared${NC}"

# Generate QR code URL for 2FA
APP_NAME=$(grep "^APP_NAME=" .env | cut -d '=' -f2- | tr -d '"' || echo "Jobs Board")
QR_URL="otpauth://totp/${APP_NAME}:admin@example.com?secret=${TWO_FACTOR_SECRET}&issuer=${APP_NAME// /%20}"

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}   Setup Complete!${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Admin Credentials:${NC}"
echo -e "  Email:    ${GREEN}admin@example.com${NC}"
echo -e "  Password: ${GREEN}${ADMIN_PASSWORD}${NC}"
echo ""
echo -e "${YELLOW}Two-Factor Authentication:${NC}"
echo -e "  Secret:   ${GREEN}${TWO_FACTOR_SECRET}${NC}"
echo ""

# Display QR code in terminal if qrencode is available
if command -v qrencode &> /dev/null; then
    echo -e "${YELLOW}Scan this QR code with your authenticator app:${NC}"
    echo ""
    qrencode -t ANSIUTF8 "$QR_URL"
    echo ""
else
    echo -e "${YELLOW}Install 'qrencode' to display QR code in terminal:${NC}"
    echo -e "  ${BLUE}brew install qrencode${NC}  (macOS)"
    echo -e "  ${BLUE}apt-get install qrencode${NC}  (Ubuntu/Debian)"
    echo ""
    echo -e "${YELLOW}Or scan this URL in a QR code generator:${NC}"
    echo -e "  ${BLUE}$QR_URL${NC}"
    echo ""
    echo -e "${YELLOW}Or use this link to generate QR code:${NC}"
    echo -e "  ${BLUE}https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=$(echo -n "$QR_URL" | jq -sRr @uri)${NC}"
fi

echo ""
echo -e "${YELLOW}Recovery Codes (save these securely):${NC}"
for i in "${!RECOVERY_CODES[@]}"; do
    printf "  %2d. ${GREEN}%s${NC}\n" $((i+1)) "${RECOVERY_CODES[$i]}"
done
echo ""

# Save credentials to a file
CREDENTIALS_FILE="admin-credentials-$(date +%Y%m%d-%H%M%S).txt"
cat > "$CREDENTIALS_FILE" <<CREDENTIALS
Jobs Board - Admin Credentials
Generated: $(date)

Email: admin@example.com
Password: ${ADMIN_PASSWORD}

Two-Factor Authentication:
Secret: ${TWO_FACTOR_SECRET}

Recovery Codes:
$(for i in "${!RECOVERY_CODES[@]}"; do printf "%2d. %s\n" $((i+1)) "${RECOVERY_CODES[$i]}"; done)

QR Code URL:
${QR_URL}

IMPORTANT: Keep this file secure and delete it after setting up 2FA!
CREDENTIALS

echo -e "${GREEN}✓ Credentials saved to: ${CREDENTIALS_FILE}${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo -e "  1. Scan the QR code with your authenticator app"
echo -e "  2. Save the recovery codes in a secure location"
echo -e "  3. Log in at: ${BLUE}$(grep "^APP_URL=" .env | cut -d '=' -f2- | tr -d '"')/login${NC}"
echo -e "  4. ${RED}Delete ${CREDENTIALS_FILE} after setup!${NC}"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
