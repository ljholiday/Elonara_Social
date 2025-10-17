<?php
declare(strict_types=1);

/**
 * Image Helper Functions
 *
 * Provides utilities for responsive image delivery with backward compatibility.
 * Handles both legacy single-URL format and new multi-variant JSON format.
 */

/**
 * Parse image URL data into array of sizes
 *
 * @param string|array|null $urlData JSON string, array, or single URL
 * @return array Associative array of size => URL
 */
function getImageSizes(string|array|null $urlData): array
{
    if (empty($urlData)) {
        return [];
    }

    // Already an array
    if (is_array($urlData)) {
        return $urlData;
    }

    // Try to decode as JSON
    $decoded = json_decode($urlData, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    // Legacy single URL - return as 'original' size
    if (is_string($urlData) && !empty($urlData)) {
        return ['original' => $urlData];
    }

    return [];
}

/**
 * Generate responsive image HTML with srcset
 *
 * @param string|array|null $urlData Image URL(s) - JSON, array, or single URL
 * @param string $alt Alt text for accessibility
 * @param string $defaultSize Size to use for main src attribute
 * @param string|null $class CSS classes to apply
 * @param bool $lazy Enable lazy loading
 * @param array $sizes Sizes attribute for responsive sizing
 * @return string HTML img element with srcset
 */
function responsiveImage(
    string|array|null $urlData,
    string $alt,
    string $defaultSize = 'original',
    ?string $class = null,
    bool $lazy = true,
    array $sizes = []
): string {
    $imageSizes = getImageSizes($urlData);

    if (empty($imageSizes)) {
        return '';
    }

    // Get main src
    $src = $imageSizes[$defaultSize] ?? $imageSizes['original'] ?? reset($imageSizes);

    // Build srcset
    $srcsetParts = [];
    $widthMap = [
        'thumb' => 320,
        'small' => 640,
        'mobile' => 640,
        'medium' => 768,
        'tablet' => 768,
        'original' => 1200,
    ];

    foreach ($imageSizes as $sizeName => $url) {
        if (isset($widthMap[$sizeName])) {
            $srcsetParts[] = htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . ' ' . $widthMap[$sizeName] . 'w';
        }
    }

    $srcset = !empty($srcsetParts) ? implode(', ', $srcsetParts) : '';

    // Build sizes attribute
    $sizesAttr = '';
    if (!empty($sizes)) {
        $sizesParts = [];
        foreach ($sizes as $condition => $size) {
            if (is_numeric($condition)) {
                $sizesParts[] = $size;
            } else {
                $sizesParts[] = "({$condition}) {$size}";
            }
        }
        $sizesAttr = implode(', ', $sizesParts);
    } else {
        // Default sizes
        $sizesAttr = '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 800px';
    }

    // Build attributes
    $attrs = [
        'src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"',
        'alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"',
    ];

    if ($srcset) {
        $attrs[] = 'srcset="' . $srcset . '"';
        $attrs[] = 'sizes="' . htmlspecialchars($sizesAttr, ENT_QUOTES, 'UTF-8') . '"';
    }

    if ($class) {
        $attrs[] = 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"';
    }

    if ($lazy) {
        $attrs[] = 'loading="lazy"';
    }

    return '<img ' . implode(' ', $attrs) . '>';
}

/**
 * Generate picture element with WebP support
 *
 * @param string|array|null $urlData Image URL(s)
 * @param string $alt Alt text
 * @param string|null $class CSS classes
 * @param bool $lazy Enable lazy loading
 * @return string HTML picture element
 */
function responsivePicture(
    string|array|null $urlData,
    string $alt,
    ?string $class = null,
    bool $lazy = true
): string {
    $imageSizes = getImageSizes($urlData);

    if (empty($imageSizes)) {
        return '';
    }

    $sources = [];

    // Try to find WebP variants
    foreach ($imageSizes as $sizeName => $url) {
        $webpUrl = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $url);
        // Note: In production, you'd verify WebP file exists
        $sources[] = '<source type="image/webp" srcset="' . htmlspecialchars($webpUrl, ENT_QUOTES, 'UTF-8') . '">';
    }

    // Fallback img
    $img = responsiveImage($urlData, $alt, 'original', $class, $lazy);

    return '<picture>' . implode('', $sources) . $img . '</picture>';
}

/**
 * Get specific size URL from image data
 *
 * @param string|array|null $urlData Image URL(s)
 * @param string $size Size name to retrieve
 * @param string $fallback Fallback size if requested not found
 * @return string URL or empty string
 */
function getImageUrl(string|array|null $urlData, string $size = 'original', string $fallback = 'original'): string
{
    $imageSizes = getImageSizes($urlData);

    if (empty($imageSizes)) {
        return '';
    }

    return $imageSizes[$size] ?? $imageSizes[$fallback] ?? reset($imageSizes) ?: '';
}
