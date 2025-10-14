# Elonara Social Installation Guide

This guide will help you install Elonara Social on your server from scratch.

## Prerequisites

- **Web Server**: Apache with mod_rewrite enabled
- **PHP**: Version 8.1 or higher
- **MySQL**: Version 5.7 or higher (or MariaDB equivalent)
- **Git**: For cloning the repository
- **Command Line Access**: To run the installation script

## Installation Steps

### 1. Create Database

First, create a new MySQL database for Elonara Social:

```bash
mysql -u root -p -e "DROP DATABASE IF EXISTS social_elonara; CREATE DATABASE social_elonara;"
```

**Explanation of this command:**
- `mysql -u root -p`: Connect to MySQL as root user (you'll be prompted for password)
- `DROP DATABASE IF EXISTS social_elonara`: Remove any existing database with this name (safe to run)
- `CREATE DATABASE social_elonara`: Create a fresh, empty database named "social_elonara"

You can replace `social_elonara` with any database name you prefer.

### 2. Clone Repository

Clone the Elonara Social repository to your web server:

```bash
cd /path/to/your/webroot
git clone https://github.com/ljholiday/social_elonara.git
cd social_elonara
```

### 3. Configure Database Connection

Create your database configuration file:

```bash
cp config/database.php.sample config/database.php
```

Edit `config/database.php` with your database credentials:

```php
<?php
/**
 * Elonara Social Database Configuration
 * Copy this file to database.php and update with your credentials
 */

return [
    'host' => 'localhost',
    'dbname' => 'social_elonara',
    'username' => 'your_db_username',
    'password' => 'your_db_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

**Update these values:**
- `host`: Your MySQL server hostname (usually 'localhost')
- `dbname`: The database name you created in step 1
- `username`: Your MySQL username
- `password`: Your MySQL password

### 4. Run Installation Script

Execute the automated installation script:

```bash
chmod +x install.sh
./install.sh
```

The installation script will:
- Test your database connection
- Import the database schema (create all required tables)
- Run any necessary migrations
- Set secure file permissions
- Verify the installation

### 5. Configure Web Server

#### Apache Configuration

Create a virtual host configuration for your domain. Example for `/etc/apache2/sites-available/social_elonara.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /path/to/social_elonara

    <Directory /path/to/social_elonara>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/social_elonara_error.log
    CustomLog ${APACHE_LOG_DIR}/social_elonara_access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite social_elonara
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### 6. SSL Certificate (Recommended)

Set up SSL for production use:

```bash
# Using Let's Encrypt (example)
sudo certbot --apache -d yourdomain.com
```

## Post-Installation

### Create Admin User

Visit your site and create the first admin account through the registration process.

### File Permissions

The installation script sets these permissions automatically:
- Directory permissions: 755
- File permissions: 644
- Upload directory: 775 (web server writable)

### Security Considerations

- Ensure `config/database.php` has restricted permissions (640)
- Set up regular database backups
- Monitor application logs
- Keep the application updated

## Troubleshooting

### Database Connection Issues

If you see database connection errors:
1. Verify your credentials in `config/database.php`
2. Ensure the database exists
3. Check that your MySQL user has proper permissions

### Permission Errors

If you see file permission errors:
```bash
# Reset permissions
sudo chown -R www-data:www-data /path/to/social_elonara
sudo chmod -R 755 /path/to/social_elonara
sudo chmod 775 uploads/
```

### Missing Tables

If you see "table doesn't exist" errors, the schema import failed:
1. Drop and recreate the database
2. Run the installation script again
3. Check MySQL error logs for specific issues

## Development Setup

For development environments:
- The `/dev` directory contains development files and is ignored in production
- Use the same installation process but consider using a local database
- Review the XML standards files in `/dev` for coding guidelines

## Support

For issues and questions:
- Check the application logs
- Review the troubleshooting section above
- Report bugs via the project's issue tracker