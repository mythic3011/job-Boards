# Login Troubleshooting Guide

## Common Login Issues and Solutions

### Issue: Can't Login After Setup

If you're unable to log in after running the setup script, follow these troubleshooting steps:

#### 1. Clear All Caches

```bash
# Clear Laravel caches
docker compose exec laravel.test php artisan optimize:clear
docker compose exec laravel.test php artisan config:clear
docker compose exec laravel.test php artisan route:clear
docker compose exec laravel.test php artisan view:clear

# Restart container
docker compose restart laravel.test
```

#### 2. Verify Database Connection

```bash
# Check database connection
docker compose exec laravel.test php artisan tinker
>>> DB::connection()->getPdo();
>>> \App\Models\User::count();
```

#### 3. Check User Exists

```bash
docker compose exec laravel.test php artisan tinker
>>> $user = \App\Models\User::where('email', 'admin@example.com')->first();
>>> $user ? 'User exists' : 'User not found';
>>> $user->login_id ?? 'No login_id';
```

#### 4. Verify Fortify Configuration

Check [`config/fortify.php`](config/fortify.php:47):

```php
'username' => 'login_id',  // Should match login form field
```

Check login form [`resources/views/components/auth/login.blade.php`](resources/views/components/auth/login.blade.php:22):

```blade
<input name="login_id" ...>  // Should match Fortify config
```

#### 5. Check Route Registration

```bash
# List all routes
docker compose exec laravel.test php artisan route:list

# Check for login route
docker compose exec laravel.test php artisan route:list | grep login

# Expected routes:
# POST      | login                | login
# GET|HEAD  | login                | login
# POST      | two-factor-challenge | two-factor.login
```

#### 6. Verify Session Configuration

Check [`config/session.php`](config/session.php:1):

```php
'driver' => env('SESSION_DRIVER', 'database'),
```

Check sessions table exists:

```bash
docker compose exec laravel.test php artisan tinker
>>> Schema::hasTable('sessions');  // Should return true
```

#### 7. Test Authentication Manually

```bash
docker compose exec laravel.test php artisan tinker
>>> $user = \App\Models\User::where('email', 'admin@example.com')->first();
>>> Hash::check('YOUR_PASSWORD_HERE', $user->password);  // Should return true
```

#### 8. Check for JavaScript Errors

1. Open browser developer tools (F12)
2. Go to Console tab
3. Try to log in
4. Look for any JavaScript errors
5. Check Network tab for failed requests

#### 9. Verify CSRF Token

The login form should have a CSRF token:

```blade
@csrf
```

Check in browser:

```html
<input type="hidden" name="_token" value="..." />
```

#### 10. Check Middleware Stack

Ensure [`EnsureSetupCompleted`](app/Http/Middleware/EnsureSetupCompleted.php:1) middleware allows login routes:

```php
// These routes should be excluded from setup check:
if ($request->is('login', 'logout', 'two-factor-challenge')) {
    return $next($request);
}
```

### Specific Error Messages

#### "These credentials do not match our records"

**Possible Causes**:

1. Wrong username/password
2. User not created properly
3. Password not hashed correctly

**Solution**:

```bash
# Reset admin password
docker compose exec laravel.test php artisan tinker
>>> $user = \App\Models\User::where('email', 'admin@example.com')->first();
>>> $user->password = Hash::make('new-password-here');
>>> $user->save();
```

#### "419 | Page Expired"

**Possible Causes**:

1. CSRF token mismatch
2. Session expired
3. Session driver misconfigured

**Solution**:

```bash
# Clear sessions
docker compose exec laravel.test php artisan session:table  # Verify table exists
docker compose exec laravel.test php artisan migrate        # Recreate if needed

# Clear application cache
docker compose exec laravel.test php artisan cache:clear
docker compose exec laravel.test php artisan config:clear

# Restart
docker compose restart laravel.test
```

#### "Too many login attempts"

**Possible Causes**:

1. Rate limiting triggered
2. Too many failed attempts

**Solution**:

```bash
# Clear rate limiter cache
docker compose exec laravel.test php artisan cache:clear

# Or wait 5 minutes for rate limit to reset
```

#### Redirected to `/install` after login

**Possible Causes**:

1. Setup not marked as completed
2. Settings table missing

**Solution**:

```bash
# Mark setup as completed
docker compose exec laravel.test php artisan tinker
>>> \App\Models\Setting::markSetupCompleted();

# Verify
>>> \App\Models\Setting::isSetupCompleted();  // Should return true
```

### Complete Reset and Reinstall

If all else fails, start fresh:

```bash
# 1. Stop containers
docker compose down

# 2. Clear volumes (WARNING: This deletes all data!)
docker compose down -v

# 3. Remove .env file
rm .env

# 4. Start containers
docker compose up -d

# 5. Wait for containers to be ready
sleep 10

# 6. Run setup script
./setup.sh
```

### Check Application Logs

```bash
# View Laravel logs
docker compose exec laravel.test tail -f storage/logs/laravel.log

# View web server logs
docker compose logs -f laravel.test
```

### Manual Login Test

Create a simple test script:

```bash
docker compose exec laravel.test php artisan tinker
```

```php
// Test user lookup
$user = \App\Models\User::where('login_id', 'admin_XXXXXXXX')->first();
dd($user->toArray());

// Test password
$user = \App\Models\User::where('email', 'admin@example.com')->first();
Hash::check('YOUR_PASSWORD', $user->password);  // Should be true

// Test 2FA
$user->two_factor_secret ? 'Enabled' : 'Disabled';

// Test role
$user->hasRole('admin') ? 'Has admin role' : 'Missing admin role';
```

### Environment Variables

Ensure these are set in `.env`:

```env
APP_KEY=base64:...                    # Must be set
APP_DEBUG=true                        # For development
APP_URL=http://localhost             # Match your setup

SESSION_DRIVER=database              # Or file/redis
SESSION_LIFETIME=120                 # Minutes

DB_CONNECTION=mysql
DB_HOST=mysql                        # Or 127.0.0.1
DB_PORT=3306
DB_DATABASE=jobs_board
DB_USERNAME=sail
DB_PASSWORD=...                      # Must match

CACHE_STORE=database                 # Or file/redis
```

### File Permissions

Ensure storage directories are writable:

```bash
docker compose exec laravel.test chmod -R 775 storage
docker compose exec laravel.test chmod -R 775 bootstrap/cache
```

### Get Help

If you're still having issues:

1. Check [`storage/logs/laravel.log`](storage/logs/) for error messages
2. Enable debug mode in `.env`: `APP_DEBUG=true`
3. Check browser console for JavaScript errors
4. Verify all migrations ran: `php artisan migrate:status`
5. Check audit logs for failed login attempts

### Quick Diagnostic Command

Run this comprehensive check:

```bash
docker compose exec laravel.test php artisan tinker <<'EOF'
echo "=== Environment Check ===\n";
echo "APP_ENV: " . config('app.env') . "\n";
echo "APP_DEBUG: " . (config('app.debug') ? 'true' : 'false') . "\n";
echo "APP_URL: " . config('app.url') . "\n\n";

echo "=== Database Check ===\n";
try {
    DB::connection()->getPdo();
    echo "✓ Database connected\n";
} catch (\Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

echo "Users count: " . \App\Models\User::count() . "\n";
echo "Settings table exists: " . (Schema::hasTable('settings') ? 'yes' : 'no') . "\n";
echo "Sessions table exists: " . (Schema::hasTable('sessions') ? 'yes' : 'no') . "\n\n";

echo "=== Setup Status ===\n";
echo "Setup completed: " . (\App\Models\Setting::isSetupCompleted() ? 'yes' : 'no') . "\n\n";

echo "=== Admin User ===\n";
$admin = \App\Models\User::where('email', 'admin@example.com')->first();
if ($admin) {
    echo "✓ Admin user exists\n";
    echo "  Login ID: " . $admin->login_id . "\n";
    echo "  Email: " . $admin->email . "\n";
    echo "  2FA enabled: " . ($admin->two_factor_secret ? 'yes' : 'no') . "\n";
    echo "  Roles: " . $admin->roles->pluck('name')->join(', ') . "\n";
} else {
    echo "✗ Admin user not found\n";
}

echo "\n=== Session Configuration ===\n";
echo "Driver: " . config('session.driver') . "\n";
echo "Lifetime: " . config('session.lifetime') . " minutes\n";
echo "Cookie: " . config('session.cookie') . "\n";

echo "\n=== Fortify Configuration ===\n";
echo "Username field: " . config('fortify.username') . "\n";
echo "Home path: " . config('fortify.home') . "\n";
EOF
```

This will output a comprehensive diagnostic report to help identify the issue.
