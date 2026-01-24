#!/bin/bash

# fuck OnL9 not sync
echo "resyncing frontend"
docker compose exec laravel.test php artisan optimize:clear
docker compose exec laravel.test npm install
docker compose exec laravel.test npm run build
docker compose restart laravel.test

echo "sync packages"
docker compose exec laravel.test composer install
docker compose restart laravel.test
