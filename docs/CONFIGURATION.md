# Elonara Social - Configuration Guide

## Overview

Elonara Social uses a **single configuration file** as the source of truth for all application settings. This eliminates the confusion of having settings scattered across multiple files.

**Single Source of Truth:** `config/config.php`

This file contains all configuration for:
- Application settings (name, URL, emails)
- Database credentials
- Mail transport settings
- Security salts
- User settings
- Image processing settings

## Quick Start

### New Installation

1. **Copy the sample configuration:**
   ```bash
   cp config/config.sample.php config/config.php
   ```

2. **Edit `config/config.php`:**
   - Set `environment` to `local`, `staging`, or `production`
   - Update database credentials
   - Update mail settings (use preset examples in the file)
   - Optionally customize other settings

3. **Security salts will auto-generate** on first run if left empty

4. **Test your setup:**
   ```bash
   php -r "require 'src/bootstrap.php'; echo 'Config loaded: ' . app_config('app.name') . PHP_EOL;"
   ```

### Existing Installation Migration

If you're migrating from the old multi-file config system:

1. **Create new unified config:**
   ```bash
   cp config/config.sample.php config/config.php
   ```

2. **Merge your existing settings:**
   - Copy database settings from `config/database.php`
   - Copy app settings from `config/app.php`
   - Copy security salts from `config/security_salts.php`
   - Mail settings are already centralized in the new format

3. **Delete old config files** (optional, after confirming everything works):
   ```bash
   rm config/app.php config/database.php config/users.php config/mail.php
   # Keep security_salts.php as backup until confirmed working
   ```

## Environment Configuration

### Environment Types

Set the `environment` key in `config/config.php`:

- **`local`** - Local development
  - Debug mode auto-enabled
  - Use MailPit for email testing
  - Local database

- **`staging`** - Pre-production testing
  - Debug mode enabled
  - Separate staging database
  - Real or test SMTP

- **`production`** - Live environment
  - Debug mode disabled
  - Production database
  - Real SMTP for emails

### Environment Helper Functions

```php
app_env()           // Returns 'local', 'staging', or 'production'
is_production()     // true if production
is_local()          // true if local development
is_staging()        // true if staging
```

Use these in your code for environment-specific behavior:
```php
if (is_production()) {
    // Production-only code
}

if (is_local()) {
    // Development-only code
}
```

## Configuration Structure

### Application Settings

```php
'app' => [
    'name' => 'Elonara Social',
    'domain' => 'social.elonara.com',
    'url' => 'https://social.elonara.com',
    'asset_url' => '/assets',
    'support_email' => 'support@social.elonara.com',
    'noreply_email' => 'noreply@social.elonara.com',
    'debug' => false,  // Auto-enabled for 'local' environment
],
```

**Access in code:**
```php
app_config('app.name')           // "Elonara Social"
app_config('app.url')            // "https://social.elonara.com"
app_config('app.support_email')  // "support@social.elonara.com"
```

### Database Settings

```php
'database' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'social_elonara',
    'username' => 'root',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
],
```

**Access in code:**
```php
$dbConfig = app_config('database');
$db = new Database($dbConfig);
```

### Mail Settings

```php
'mail' => [
    'transport' => 'smtp',  // 'smtp', 'sendmail', or 'mail'
    'host' => 'smtp.example.com',
    'port' => 587,
    'auth' => true,
    'username' => 'your-smtp-user',
    'password' => 'your-smtp-password',
    'encryption' => 'tls',  // 'tls', 'ssl', or ''
    'from_address' => '',   // Empty = use app.noreply_email
    'from_name' => '',      // Empty = use app.name
],
```

**Common Mail Configurations:**

**Local Development (MailPit):**
```php
'mail' => [
    'transport' => 'smtp',
    'host' => '127.0.0.1',
    'port' => 1025,
    'auth' => false,
    'encryption' => '',
],
```

**Production (Standard SMTP):**
```php
'mail' => [
    'transport' => 'smtp',
    'host' => 'smtp.yourprovider.com',
    'port' => 587,
    'auth' => true,
    'username' => 'your-smtp-username',
    'password' => 'your-smtp-password',
    'encryption' => 'tls',
],
```

### Security Salts

```php
'security' => [
    'salts' => [
        'auth' => '',      // Auto-generated if empty
        'nonce' => '',     // Auto-generated if empty
        'session' => '',   // Auto-generated if empty
    ],
],
```

**IMPORTANT:**
- Salts are auto-generated on first run if left empty
- Once generated, **DO NOT CHANGE** or existing sessions/tokens will break
- Use 64-character hexadecimal strings (generated via `bin2hex(random_bytes(32))`)

## Deployment Guide

### Local Development Setup

1. Copy config sample:
   ```bash
   cp config/config.sample.php config/config.php
   ```

2. Edit `config/config.php`:
   ```php
   'environment' => 'local',
   'database' => [
       'host' => '127.0.0.1',
       'name' => 'social_elonara',
       'username' => 'root',
       'password' => 'root',
   ],
   'mail' => [
       'host' => '127.0.0.1',
       'port' => 1025,  // MailPit
       'auth' => false,
   ],
   ```

3. Run database installation:
   ```bash
   ./install.sh
   ```

### Production Deployment

1. **On your development machine**, create production config template:
   ```bash
   cp config/config.sample.php config/config.production.php
   ```

2. **Edit `config.production.php` with production values:**
   ```php
   'environment' => 'production',
   'app' => [
       'domain' => 'social.elonara.com',
       'url' => 'https://social.elonara.com',
       'debug' => false,
   ],
   'database' => [
       'host' => '127.0.0.1',
       'name' => 'social_elonara_production',
       'username' => 'prod_user',
       'password' => 'SECURE_PASSWORD_HERE',
   ],
   'mail' => [
       'host' => 'smtp.yourprovider.com',
       'port' => 587,
       'auth' => true,
       'username' => 'your-smtp-user',
       'password' => 'your-smtp-password',
       'encryption' => 'tls',
   ],
   ```

3. **Deploy to production server:**
   ```bash
   # Push code to repository (config.php is gitignored)
   git add .
   git commit -m "Update configuration system"
   git push origin main
   ```

4. **On production server:**
   ```bash
   # Pull latest code
   git pull origin main

   # Copy your production config
   cp config.production.php config/config.php

   # Or upload via SFTP to config/config.php

   # Restart PHP-FPM or web server
   sudo systemctl reload php-fpm
   # OR
   sudo systemctl reload apache2
   ```

5. **Verify production config:**
   ```bash
   php -r "require 'src/bootstrap.php'; echo app_env() . PHP_EOL;"
   # Should output: production
   ```

### Staging Environment

Same as production, but use:
```php
'environment' => 'staging',
'database' => [
    'name' => 'social_elonara_staging',
    // ... staging credentials
],
```

## Environment Variables (Optional)

While `config/config.php` is the primary source, you can still override settings via environment variables if needed:

```bash
# In .env file or server environment
DB_HOST=127.0.0.1
DB_NAME=social_elonara
DB_USERNAME=root
DB_PASSWORD=root
MAIL_HOST=smtp.example.com
```

The application will prefer config file values, but environment variables can override them.

## Troubleshooting

### "Missing config/config.php" Error

**Solution:** Copy the sample file:
```bash
cp config/config.sample.php config/config.php
```

### Database Connection Failed

1. Verify credentials in `config/config.php` → `database` section
2. Test connection:
   ```bash
   php -r "require 'src/bootstrap.php'; \$db = app_service('database.connection'); echo 'Connected!' . PHP_EOL;"
   ```

### Mail Not Sending

1. Check `config/config.php` → `mail` section
2. For local dev, ensure MailPit is running:
   ```bash
   mailpit
   # Visit http://localhost:8025 to see captured emails
   ```
3. For production, verify SMTP credentials with your provider

### Environment Not Detected

Check the `environment` value in `config/config.php`:
```bash
php -r "require 'src/bootstrap.php'; echo 'Environment: ' . app_env() . PHP_EOL;"
```

## Security Best Practices

1. **Never commit `config/config.php`** to version control (it's gitignored)
2. **Use strong database passwords** in production
3. **Don't share security salts** between environments
4. **Keep `config.sample.php` generic** - no real credentials
5. **Set proper file permissions** on production:
   ```bash
   chmod 600 config/config.php
   chown www-data:www-data config/config.php
   ```

## Migration Checklist

Migrating from old multi-file config? Check these off:

- [ ] Created `config/config.php` from sample
- [ ] Copied database credentials from old `config/database.php`
- [ ] Copied app settings from old `config/app.php`
- [ ] Copied security salts from old `config/security_salts.php`
- [ ] Verified mail settings
- [ ] Tested database connection
- [ ] Tested mail sending
- [ ] Verified environment detection (`app_env()`)
- [ ] Deleted old config files (optional)
- [ ] Updated production server config
- [ ] Restarted web server

## Support

For issues or questions:
- Check this documentation first
- Review `config/config.sample.php` for examples
- Test with `php -r "require 'src/bootstrap.php'; var_dump(app_config());"`
- Check logs in `debug.log` if enabled
