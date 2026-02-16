#!/bin/bash
set -e
command -v docker &>/dev/null && docker compose ps laravel.test &>/dev/null 2>&1 && docker compose exec laravel.test php artisan tinker --execute="App\Models\Setting::markSetupCompleted();" || php artisan tinker --execute="App\Models\Setting::markSetupCompleted();"
