<?php
declare(strict_types=1);

/**
 * Image Processing Configuration
 *
 * Defines size variants, quality settings, and constraints for the ImageService.
 * All image uploads will generate variants according to these specifications.
 */

return [
    /**
     * Size variants for each image type
     * Each type can have multiple size variants (original, mobile, thumb, etc.)
     * Dimensions define maximum width/height - aspect ratio is preserved
     */
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

    /**
     * Quality settings per format
     * JPEG: 0-100 (higher is better quality, larger file size)
     * PNG: 0-9 (compression level, lower is better quality, larger file size)
     * WebP: 0-100 (higher is better quality, larger file size)
     */
    'quality' => [
        'jpeg' => 90,
        'png' => 8,
        'webp' => 85,
    ],

    /**
     * Maximum upload file size in bytes
     * Default: 10MB
     */
    'max_size' => 10 * 1024 * 1024,

    /**
     * Allowed MIME types for upload
     * Only these types will be accepted
     */
    'allowed_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ],

    /**
     * Base upload directory
     * Relative to public directory
     */
    'upload_base' => '/uploads',

    /**
     * Enable WebP generation alongside original format
     * Requires GD library with WebP support (PHP 8.1+)
     */
    'generate_webp' => true,
];
