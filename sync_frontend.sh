#!/bin/bash

# Initialize environment file
echo "initializing environment file"
[ ! -f .env ] && ([ -f .env.example ] && cp .env.example .env || touch .env)
[ -z "$(grep '^APP_KEY=base64:' .env 2>/dev/null)" ] && docker compose exec laravel.test php artisan key:generate --force 2>/dev/null || true
[ -z "$(grep '^DB_PASSWORD=' .env 2>/dev/null | cut -d'=' -f2)" ] && (DB_PWD="${DB_PASSWORD:-$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)}" && grep -q '^DB_PASSWORD=' .env && ([[ "$OSTYPE" == "darwin"* ]] && sed -i '' "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PWD|" .env || sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PWD|" .env) || echo "DB_PASSWORD=$DB_PWD" >> .env) || true

# fuck OnL9 not sync
echo "resyncing frontend"
docker compose exec laravel.test php artisan optimize:clear
docker compose exec laravel.test php artisan config:clear
docker compose exec laravel.test npm install
docker compose exec laravel.test npm run build
docker compose restart laravel.test

echo "sync packages"
docker compose exec laravel.test composer install
docker compose restart laravel.test

docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"

docker compose exec laravel.test php artisan route:list --path='/'
docker compose exec laravel.test php artisan route:list --path=home
docker compose exec laravel.test php artisan route:clear
docker compose exec laravel.test php artisan config:clear
