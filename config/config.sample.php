<?php
/**
 * Elonara Social - Master Configuration File
 *
 * This is the SINGLE SOURCE OF TRUTH for all application configuration.
 *
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to config/config.php
 * 2. Set 'environment' to your deployment type (local, staging, or production)
 * 3. Update database credentials for your environment
 * 4. Update mail settings for your environment
 * 5. Optionally customize other settings as needed
 *
 * SECURITY:
 * - config/config.php is gitignored and should NEVER be committed
 * - This .sample.php file is tracked in git as a template
 * - Security salts are auto-generated if not provided
 */

declare(strict_types=1);

return [
    // =========================================================================
    // ENVIRONMENT
    // =========================================================================
    // Valid values: 'local', 'staging', 'production'
    // This affects debugging, error display, and default behaviors
    'environment' => 'production',

    // =========================================================================
    // APPLICATION
    // =========================================================================
    'app' => [
        'name' => 'Elonara Social',
        'domain' => 'social.elonara.com',
        'url' => 'https://social.elonara.com',
        'asset_url' => '/assets',

        // Email addresses for system emails
        'support_email' => 'support@social.elonara.com',
        'noreply_email' => 'noreply@social.elonara.com',

        // Debug mode (auto-enabled for 'local' environment)
        'debug' => false,
    ],

    // =========================================================================
    // DATABASE
    // =========================================================================
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'social_elonara',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8mb4',

        // PDO connection options (advanced - don't change unless you know what you're doing)
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    // =========================================================================
    // MAIL TRANSPORT
    // =========================================================================
    'mail' => [
        // Transport method: 'smtp', 'sendmail', or 'mail'
        'transport' => 'smtp',

        // SMTP Settings (only used if transport is 'smtp')
        'host' => '127.0.0.1',
        'port' => 1025,
        'auth' => false,
        'username' => '',
        'password' => '',
        'encryption' => '', // 'tls', 'ssl', or empty string for none
        'timeout' => 30,

        // Sendmail path (only used if transport is 'sendmail')
        'sendmail_path' => '/usr/sbin/sendmail -bs',

        // From/Reply-To addresses (leave empty to use app settings)
        'from_address' => '',
        'from_name' => '',
        'reply_to_address' => '',
        'reply_to_name' => '',

        // Debug level (0 = off, 1 = client, 2 = server, 3 = connection, 4 = lowlevel)
        'debug' => 0,
    ],

    // =========================================================================
    // SECURITY
    // =========================================================================
    'security' => [
        // Cryptographic salts for hashing
        // IMPORTANT: These will be auto-generated if left empty
        // Once generated, DO NOT change them or existing sessions/tokens will break
        'salts' => [
            'auth' => '',
            'nonce' => '',
            'session' => '',
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
        // Size variants for each image type
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

        // Quality settings per format
        'quality' => [
            'jpeg' => 90,
            'png' => 8,
            'webp' => 85,
        ],

        // Maximum upload file size in bytes (10MB default)
        'max_size' => 10 * 1024 * 1024,

        // Allowed MIME types
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ],

        // Base upload directory (relative to public directory)
        'upload_base' => '/uploads',

        // Generate WebP versions alongside originals
        'generate_webp' => true,
    ],

    // =========================================================================
    // ENVIRONMENT-SPECIFIC PRESETS
    // =========================================================================
    // These are common configurations for different environments.
    // Copy the values you need to the main sections above.

    '_presets' => [
        'local' => [
            'app' => [
                'domain' => 'social.elonara.local',
                'url' => 'http://social.elonara.local',
                'support_email' => 'support@social.elonara.local',
                'noreply_email' => 'noreply@social.elonara.local',
                'debug' => true,
            ],
            'database' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'social_elonara',
                'username' => 'root',
                'password' => 'root',
            ],
            'mail' => [
                'transport' => 'smtp',
                'host' => '127.0.0.1',
                'port' => 1025, // MailPit default
                'auth' => false,
                'encryption' => '',
            ],
        ],

        'staging' => [
            'app' => [
                'domain' => 'staging.social.elonara.com',
                'url' => 'https://staging.social.elonara.com',
                'support_email' => 'support@social.elonara.com',
                'noreply_email' => 'noreply@social.elonara.com',
                'debug' => true,
            ],
            'database' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'social_elonara_staging',
                'username' => 'staging_user',
                'password' => 'CHANGE_ME',
            ],
            'mail' => [
                'transport' => 'smtp',
                'host' => 'smtp.yourprovider.com',
                'port' => 587,
                'auth' => true,
                'username' => 'your-smtp-user',
                'password' => 'CHANGE_ME',
                'encryption' => 'tls',
            ],
        ],

        'production' => [
            'app' => [
                'domain' => 'social.elonara.com',
                'url' => 'https://social.elonara.com',
                'support_email' => 'support@social.elonara.com',
                'noreply_email' => 'noreply@social.elonara.com',
                'debug' => false,
            ],
            'database' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'name' => 'social_elonara_production',
                'username' => 'production_user',
                'password' => 'CHANGE_ME',
            ],
            'mail' => [
                'transport' => 'smtp',
                'host' => 'smtp.yourprovider.com',
                'port' => 587,
                'auth' => true,
                'username' => 'your-smtp-user',
                'password' => 'CHANGE_ME',
                'encryption' => 'tls',
            ],
        ],
    ],
];
