# Quick Setup Script

This script provides a fast way to set up the Jobs Board application with a default admin user and 2FA configuration.

## Features

- ✅ Runs database migrations (including sessions table)
- ✅ Seeds roles and permissions
- ✅ Creates admin user with secure password (generated via OpenSSL)
- ✅ Generates 2FA secret and recovery codes
- ✅ Displays QR code in terminal (if qrencode is installed)
- ✅ Saves credentials to a timestamped file
- ✅ Marks setup as completed

## Prerequisites

```bash
# Required
php >= 8.2
composer
mysql/postgresql

# Optional (for QR code display in terminal)
qrencode
```

## Installation

### 1. Install qrencode (optional but recommended)

**macOS:**

```bash
brew install qrencode
```

**Ubuntu/Debian:**

```bash
sudo apt-get install qrencode
```

**Without qrencode:** The script will provide a URL to generate the QR code online.

### 2. Configure Environment

Make sure your `.env` file has correct database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jobs_board
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Run Setup

```bash
./setup.sh
```

## What the Script Does

1. **Creates .env** (if not exists) and generates application key
2. **Runs migrations** with `migrate:fresh` (⚠️ destroys existing data)
3. **Seeds roles** and permissions
4. **Generates secure credentials:**
    - Password: 16-character random string via OpenSSL
    - 2FA Secret: 32-character Base32 string
    - Recovery Codes: 10 unique 8-character codes
5. **Creates admin user:**
    - Email: `admin@example.com`
    - Role: `admin`
    - 2FA: Enabled
6. **Displays QR code** in terminal (if qrencode available)
7. **Saves credentials** to `admin-credentials-YYYYMMDD-HHMMSS.txt`

## Output Example

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   Setup Complete!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Admin Credentials:
  Email:    admin@example.com
  Password: Xy9kL2mN4pQ8rT6v

Two-Factor Authentication:
  Secret:   JBSWY3DPEHPK3PXP

Scan this QR code with your authenticator app:

[QR CODE DISPLAYED HERE]

Recovery Codes (save these securely):
   1. A2B4C6D8
   2. E9F1G3H5
   ...

✓ Credentials saved to: admin-credentials-20260126-123456.txt
```

## Security Notes

⚠️ **IMPORTANT:**

1. **Delete the credentials file** after setting up 2FA:

    ```bash
    rm admin-credentials-*.txt
    ```

2. **Change the default email** after first login

3. **Store recovery codes** in a secure password manager

4. **Never commit** credentials files to version control

## Troubleshooting

### Sessions Table Not Found

The script runs `migrate:fresh` which recreates all tables including sessions. If you still get this error:

```bash
# Manually run migrations
php artisan migrate:fresh --force
```

### QR Code Not Displaying

If qrencode is not installed, use the provided URL:

```
https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=[URL]
```

Or manually enter the secret in your authenticator app.

### Permission Denied

Make the script executable:

```bash
chmod +x setup.sh
```

## Alternative: Manual Setup

If you prefer not to use the script:

```bash
# 1. Run migrations
php artisan migrate:fresh

# 2. Seed roles
php artisan db:seed --class=RolePermissionSeeder

# 3. Use the web installer
# Visit: http://your-domain/install
```

## Files Created

- `admin-credentials-YYYYMMDD-HHMMSS.txt` - Admin credentials (delete after use!)

## Related Documentation

- [Installation Guide](../README.md)
- [2FA Setup](./2FA_SETUP.md)
- [Security Best Practices](./SECURITY.md)
