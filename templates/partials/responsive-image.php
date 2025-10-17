<?php
/**
 * Responsive Image Partial
 *
 * Displays a responsive image with srcset and multiple size variants.
 * Supports both new multi-variant JSON format and legacy single-URL format.
 *
 * Required variables:
 * - $url_data: string|array Image URL(s) - JSON string, array, or single URL
 * - $alt: string Alt text for accessibility
 *
 * Optional variables:
 * - $default_size: string Size to use for main src attribute (default: 'original')
 * - $class: string CSS classes to apply (default: 'app-img')
 * - $lazy: bool Enable lazy loading (default: true)
 * - $sizes: array Custom sizes attribute (default: responsive breakpoints)
 * - $use_picture: bool Use picture element with WebP (default: false)
 *
 * Example usage:
 * ```php
 * $url_data = $user->avatar_url;
 * $alt = 'Profile photo of ' . e($user->display_name);
 * $default_size = 'medium';
 * $class = 'app-avatar app-avatar-lg';
 * include __DIR__ . '/partials/responsive-image.php';
 * ```
 */

// Set defaults
$url_data = $url_data ?? null;
$alt = $alt ?? '';
$default_size = $default_size ?? 'original';
$class = $class ?? 'app-img';
$lazy = $lazy ?? true;
$sizes = $sizes ?? [];
$use_picture = $use_picture ?? false;

// Early return if no image data
if (empty($url_data)) {
    return;
}

// Use picture element with WebP if requested
if ($use_picture) {
    echo responsivePicture($url_data, $alt, $class, $lazy);
    return;
}

// Standard responsive img element
echo responsiveImage($url_data, $alt, $default_size, $class, $lazy, $sizes);
