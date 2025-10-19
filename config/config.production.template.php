<?php
/**
 * Elonara Social - Production Configuration Template
 *
 * This is a template for production deployment.
 * DO NOT commit this with real credentials.
 *
 * DEPLOYMENT STEPS:
 * 1. Copy this file to config/config.php on your production server
 * 2. Update all CHANGE_ME values with real production credentials
 * 3. Ensure file permissions are secure: chmod 600 config/config.php
 * 4. Restart PHP-FPM/Apache
 */

declare(strict_types=1);

return [
    // =========================================================================
    // ENVIRONMENT - Set to 'production'
    // =========================================================================
    'environment' => 'production',

    // =========================================================================
    // APPLICATION
    // =========================================================================
    'app' => [
        'name' => 'Elonara Social',
        'domain' => 'social.elonara.com',
        'url' => 'https://social.elonara.com',
        'asset_url' => '/assets',
        'support_email' => 'support@social.elonara.com',
        'noreply_email' => 'noreply@social.elonara.com',
        'debug' => false,  // MUST be false in production
    ],

    // =========================================================================
    // DATABASE - UPDATE WITH PRODUCTION CREDENTIALS
    // =========================================================================
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'CHANGE_ME_production_db_name',
        'username' => 'CHANGE_ME_production_db_user',
        'password' => 'CHANGE_ME_production_db_password',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    // =========================================================================
    // MAIL TRANSPORT - UPDATE WITH PRODUCTION SMTP
    // =========================================================================
    'mail' => [
        'transport' => 'smtp',
        'host' => 'CHANGE_ME_smtp_host',
        'port' => 587,
        'auth' => true,
        'username' => 'CHANGE_ME_smtp_username',
        'password' => 'CHANGE_ME_smtp_password',
        'encryption' => 'tls',
        'timeout' => 30,
        'sendmail_path' => '/usr/sbin/sendmail -bs',
        'from_address' => 'noreply@social.elonara.com',
        'from_name' => 'Elonara Social',
        'reply_to_address' => 'support@social.elonara.com',
        'reply_to_name' => 'Elonara Social Support',
        'debug' => 0,  // MUST be 0 in production
    ],

    // =========================================================================
    // SECURITY - Auto-generated on first run
    // =========================================================================
    'security' => [
        'salts' => [
            'auth' => '',      // Will auto-generate
            'nonce' => '',     // Will auto-generate
            'session' => '',   // Will auto-generate
        ],
    ],

    // =========================================================================
    // USER SETTINGS
    // =========================================================================
    'users' => [
        'username_min_length' => 2,
        'username_max_length' => 30,
    ],

    // =========================================================================
    // IMAGE PROCESSING
    // =========================================================================
    'images' => [
        'sizes' => [
            'profile' => [
                'original' => ['width' => 400, 'height' => 400],
                'medium'   => ['width' => 200, 'height' => 200],
                'small'    => ['width' => 100, 'height' => 100],
                'thumb'    => ['width' => 48, 'height' => 48],
            ],
            'cover' => [
                'original' => ['width' => 1200, 'height' => 400],
                'tablet'   => ['width' => 768, 'height' => 256],
                'mobile'   => ['width' => 640, 'height' => 213],
            ],
            'post' => [
                'original' => ['width' => 800, 'height' => 600],
                'mobile'   => ['width' => 640, 'height' => 480],
                'thumb'    => ['width' => 320, 'height' => 240],
            ],
            'featured' => [
                'original' => ['width' => 1200, 'height' => 630],
                'mobile'   => ['width' => 640, 'height' => 336],
            ],
        ],
        'quality' => [
            'jpeg' => 90,
            'png' => 8,
            'webp' => 85,
        ],
        'max_size' => 10 * 1024 * 1024,
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],
        'upload_base' => '/uploads',
        'generate_webp' => true,
    ],
];
