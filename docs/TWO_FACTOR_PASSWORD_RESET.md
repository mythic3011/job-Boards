# Two-Factor Authentication for Password Reset

## Overview

This document describes the enhanced password reset flow that requires two-factor authentication (2FA) verification for added security. Users with 2FA enabled must verify their identity using either an OTP code or recovery code before receiving a password reset link.

## Security Rationale

Password reset is a critical security operation that could allow unauthorized access if compromised. By requiring 2FA verification:

1. **Prevents unauthorized password resets**: Attackers cannot reset passwords even if they know the email address
2. **Protects against email compromise**: Even if an attacker has access to the user's email, they still need the 2FA device
3. **Maintains account security**: Ensures only the legitimate account owner can initiate password resets
4. **Audit trail**: All password reset attempts are logged with 2FA verification status

## User Flow

### 1. Request Password Reset

User visits [`/forgot-password`](resources/views/auth/forgot-password.blade.php:1) and provides:

- Email address
- 2FA authentication code (6-digit OTP) OR recovery code

### 2. Verification Process

The system:

1. Validates the email exists
2. Checks if user has 2FA enabled (required)
3. Verifies the provided OTP code or recovery code
4. Sends password reset link if verification succeeds
5. Logs the attempt for security audit

### 3. Password Reset

After successful 2FA verification:

1. User receives password reset email
2. Clicks the link in the email
3. Sets new password on [`/reset-password`](resources/views/auth/reset-password.blade.php:1)
4. Can log in with new password

## Implementation Details

### Files Created

**[`app/Actions/Fortify/SendPasswordResetLinkWithTwoFactor.php`](app/Actions/Fortify/SendPasswordResetLinkWithTwoFactor.php:1)**

- Custom action that handles password reset with 2FA verification
- Validates OTP codes using Google2FA library
- Validates recovery codes against encrypted stored codes
- Implements rate limiting (3 attempts per 5 minutes)
- Logs all verification attempts for security audit

**[`app/Http/Controllers/Auth/PasswordResetController.php`](app/Http/Controllers/Auth/PasswordResetController.php:1)**

- Controller that handles the password reset request
- Validates input (email, code, or recovery_code)
- Calls the custom action to process the request
- Returns appropriate success/error messages

**[`routes/auth.php`](routes/auth.php:1)**

- Custom authentication routes
- Overrides Fortify's default `password.email` route
- Ensures our custom controller is used

### Files Modified

**[`resources/views/auth/forgot-password.blade.php`](resources/views/auth/forgot-password.blade.php:1)**

- Enhanced UI with 2FA code input
- Toggle between OTP code and recovery code
- Clear instructions and help text
- Alpine.js for smooth transitions

**[`routes/web.php`](routes/web.php:27)**

- Includes custom auth routes before other routes
- Ensures route override takes precedence

**[`app/Providers/FortifyServiceProvider.php`](app/Providers/FortifyServiceProvider.php:50)**

- Registers custom password reset views
- Maintains other Fortify configurations

## UI Features

### Forgot Password Page

The enhanced forgot password page includes:

1. **Email Input**: User enters their email address
2. **2FA Code Input**:
    - 6-digit numeric input for OTP codes
    - Formatted input with placeholder "000000"
    - Clear instructions: "Enter the 6-digit code from your authenticator app"
3. **Recovery Code Input**:
    - Text input for recovery codes
    - Formatted placeholder "XXXXX-XXXXX"
    - Clear instructions: "Enter one of your recovery codes"
4. **Toggle Button**: Switch between OTP and recovery code input
5. **Help Section**: Explains why 2FA is required
6. **Visual Feedback**: Success/error messages with appropriate styling

### Two-Factor Challenge Page

The existing two-factor challenge page ([`resources/views/components/auth/two-factor-challenge.blade.php`](resources/views/components/auth/two-factor-challenge.blade.php:1)) already provides excellent UX:

1. **OTP Code Input**: 6-digit numeric input with auto-focus
2. **Recovery Code Input**: Alternative authentication method
3. **Toggle Button**: Easy switching between methods
4. **Trust Device Option**: Remember device for 30 days
5. **Help Section**: Guidance for users having trouble
6. **Cancel Option**: Logout if needed

## Security Features

### Rate Limiting

**Password Reset Attempts**:

- 3 attempts per email per 5 minutes
- Separate tracking for failed 2FA verifications
- Automatic lockout after limit exceeded
- Clear error messages about wait time

**2FA Verification**:

- Invalid codes increment rate limit counter
- Separate counters for OTP and recovery codes
- Prevents brute force attacks

### Audit Logging

All password reset attempts are logged with:

- Event type (e.g., `password_reset.link_sent`, `password_reset.invalid_2fa_code`)
- User ID and email
- IP address
- Verification method (OTP or recovery code)
- Timestamp

### Code Verification

**OTP Codes**:

- Verified using Google2FA library
- 2-window tolerance for time drift
- Encrypted secret storage
- Automatic validation of 6-digit format

**Recovery Codes**:

- Constant-time comparison to prevent timing attacks
- Encrypted storage in database
- Case-insensitive matching
- Hyphen-insensitive (accepts with or without hyphens)

## Error Handling

### User Not Found

```
"We could not find a user with that email address."
```

- Generic message to prevent email enumeration
- Rate limit applied

### 2FA Not Enabled

```
"Two-factor authentication is not enabled for this account. Please contact support."
```

- Informs user that 2FA is required
- Directs to support for assistance

### Invalid OTP Code

```
"The authentication code is invalid."
```

- Clear, specific error message
- Rate limit applied

### Invalid Recovery Code

```
"The recovery code is invalid."
```

- Clear, specific error message
- Rate limit applied

### Rate Limit Exceeded

```
"Too many attempts. Please try again in X seconds."
```

- Shows exact wait time
- Prevents abuse

## Testing

### Test Password Reset with OTP

1. Navigate to `/forgot-password`
2. Enter email address of user with 2FA enabled
3. Open authenticator app and get current 6-digit code
4. Enter code in "Two-Factor Authentication Code" field
5. Click "Verify and Send Reset Link"
6. Verify success message appears
7. Check email for password reset link

### Test Password Reset with Recovery Code

1. Navigate to `/forgot-password`
2. Enter email address
3. Click "Use a recovery code instead"
4. Enter one of your recovery codes
5. Click "Verify and Send Reset Link"
6. Verify success message appears
7. Check email for password reset link

### Test Error Cases

**Invalid OTP Code**:

```bash
# Enter wrong 6-digit code
# Expected: "The authentication code is invalid."
```

**Invalid Recovery Code**:

```bash
# Enter wrong recovery code
# Expected: "The recovery code is invalid."
```

**User Without 2FA**:

```bash
# Try to reset password for user without 2FA
# Expected: "Two-factor authentication is not enabled..."
```

**Rate Limiting**:

```bash
# Make 4 failed attempts
# Expected: "Too many attempts. Please try again in X seconds."
```

## Configuration

### Rate Limiting

Adjust rate limits in [`SendPasswordResetLinkWithTwoFactor.php`](app/Actions/Fortify/SendPasswordResetLinkWithTwoFactor.php:17):

```php
// Current: 3 attempts per 5 minutes
if (RateLimiter::tooManyAttempts($key, 3)) {
    $seconds = RateLimiter::availableIn($key);
    // ...
}

// To change: modify the second parameter (attempts) and hit() duration
RateLimiter::hit($key, 300); // 300 seconds = 5 minutes
```

### 2FA Window Tolerance

Adjust time window tolerance in [`SendPasswordResetLinkWithTwoFactor.php`](app/Actions/Fortify/SendPasswordResetLinkWithTwoFactor.php:127):

```php
// Current: 2 windows (±60 seconds)
return $google2fa->verifyKey($secret, $code, 2);

// To change: modify the third parameter
// 0 = exact time only
// 1 = ±30 seconds
// 2 = ±60 seconds
// 4 = ±120 seconds
```

## Troubleshooting

### User Can't Receive Reset Email

**Check**:

1. Is 2FA enabled for the account?
2. Is the OTP code current (not expired)?
3. Is the recovery code valid (not already used)?
4. Has rate limit been exceeded?
5. Check audit logs for failed attempts

**Solution**:

```bash
# Check user's 2FA status
php artisan tinker
>>> $user = User::where('email', 'user@example.com')->first();
>>> $user->two_factor_secret ? 'Enabled' : 'Disabled';

# Check audit logs
tail -f storage/logs/laravel.log | grep password_reset
```

### OTP Codes Always Invalid

**Possible Causes**:

1. Time synchronization issue on server or device
2. Wrong secret key
3. Authenticator app not synced

**Solution**:

```bash
# Check server time
date

# Sync server time if needed
sudo ntpdate -s time.nist.gov

# User should resync authenticator app
```

### Recovery Codes Not Working

**Possible Causes**:

1. Code already used
2. Typo in code entry
3. Encrypted codes corrupted

**Solution**:

```bash
# Regenerate recovery codes for user
php artisan tinker
>>> $user = User::find(1);
>>> $codes = collect(range(1, 10))->map(fn() => \Laravel\Fortify\RecoveryCode::generate())->all();
>>> $user->forceFill([
...     'two_factor_recovery_codes' => encrypt(json_encode($codes))
... ])->save();
>>> print_r($codes);
```

## Best Practices

### For Users

1. **Keep Recovery Codes Safe**: Store recovery codes in a secure location
2. **Use Password Manager**: Store recovery codes in password manager
3. **Test Recovery Codes**: Verify at least one recovery code works
4. **Sync Authenticator**: Ensure authenticator app time is synced
5. **Contact Support**: If locked out, contact support immediately

### For Administrators

1. **Monitor Audit Logs**: Regularly review password reset attempts
2. **Investigate Patterns**: Look for suspicious patterns (multiple failures)
3. **User Education**: Educate users about 2FA importance
4. **Backup Procedures**: Have process for users who lose 2FA access
5. **Rate Limit Tuning**: Adjust rate limits based on abuse patterns

## Related Documentation

- [`docs/FRONTEND_REFACTORING.md`](docs/FRONTEND_REFACTORING.md:1) - Frontend asset loading and installation
- [`docs/HTTP_LOGGING.md`](docs/HTTP_LOGGING.md:1) - HTTP request/response logging
- [`docs/SETUP_SCRIPT.md`](docs/SETUP_SCRIPT.md:1) - Automated setup script

## Future Enhancements

1. **SMS Backup**: Add SMS as alternative 2FA method
2. **Email Verification**: Send verification code to email as backup
3. **Admin Override**: Allow admins to reset passwords with approval
4. **Temporary Codes**: Generate temporary codes for support
5. **Biometric Support**: Add WebAuthn/FIDO2 support
