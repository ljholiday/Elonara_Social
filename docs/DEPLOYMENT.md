# Elonara Social - Deployment Guide

## Initial Setup (New Server/Environment)

### 1. Clone Repository
```bash
git clone https://github.com/your-org/social.elonara.git
cd social.elonara
```

### 2. Create Configuration File
```bash
# Copy the sample configuration
cp config/config.sample.php config/config.php

# Edit with your environment settings
nano config/config.php
```

**Required Changes:**
- Set `environment` to `local`, `staging`, or `production`
- Update `database` section with your database credentials
- Update `mail` section with your SMTP settings
- Update `app` section URLs to match your domain

**Example for Production:**
```php
'environment' => 'production',
'app' => [
    'domain' => 'social.elonara.com',
    'url' => 'https://social.elonara.com',
],
'database' => [
    'name' => 'your_db_name',
    'username' => 'your_db_user',
    'password' => 'your_secure_password',
],
'mail' => [
    'host' => 'smtp.yourprovider.com',
    'port' => 587,
    'auth' => true,
    'username' => 'your_smtp_user',
    'password' => 'your_smtp_password',
    'encryption' => 'tls',
],
```

### 3. Set File Permissions
```bash
chmod 600 config/config.php
chown www-data:www-data config/config.php
chmod 755 public/uploads
chown -R www-data:www-data public/uploads
```

### 4. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 5. Initialize Database
```bash
./install.sh
```

### 6. Configure Web Server

Point your web server document root to the repository root and restart.

### 7. Verify Installation
```bash
php -r "require 'src/bootstrap.php'; echo 'Environment: ' . app_env() . PHP_EOL;"
```

## Updating Existing Installation

When deploying code updates:

```bash
# 1. Pull latest code
git pull origin main

# 2. Update dependencies if composer.json changed
composer install --no-dev --optimize-autoloader

# 3. Check if config needs updates
diff config/config.php config/config.sample.php
# Add any new configuration sections if needed

# 4. Run database migrations if any exist
# (Check config/migrations/ for new files)

# 5. Restart PHP
sudo systemctl reload php-fpm
# OR
sudo systemctl reload apache2

# 6. Verify
curl -I https://your-domain.com
```

## Common Issues

### "Missing config/config.php"
- Copy `config/config.production.template.php` to `config/config.php`
- Update credentials

### Database Connection Failed
- Verify database credentials in `config/config.php`
- Ensure MySQL/MariaDB is running
- Check firewall allows connection

### Mail Not Sending
- Verify SMTP credentials in `config/config.php`
- Test SMTP connection from server
- Check mail provider allows SMTP from your server IP

### 500 Internal Server Error
- Check web server error logs: `tail -f /var/log/nginx/error.log`
- Check PHP-FPM logs: `tail -f /var/log/php-fpm/www-error.log`
- Verify file permissions on uploads directory
- Ensure config/config.php exists and is readable

## Environment Differences

### Local
- `environment` = `'local'`
- Debug mode enabled
- MailPit for email testing
- Local database

### Production
- `environment` = `'production'`
- Debug mode **disabled**
- Real SMTP for emails
- Production database
- Secure file permissions

## Security Checklist

- [ ] `config/config.php` has 600 permissions
- [ ] Debug mode is **false** in production
- [ ] Database password is strong
- [ ] SMTP credentials are secure
- [ ] Security salts are unique (auto-generated)
- [ ] `uploads/` directory has proper permissions
- [ ] `.git/` directory not accessible via web
- [ ] Error display disabled in production PHP settings
