<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Modern Image Service
 *
 * Handles image upload, validation, resizing with alt-text enforcement.
 * Generates multiple size variants for responsive delivery.
 */
final class ImageService
{
    private string $uploadBasePath;
    private string $uploadBaseUrl;
    private array $config;

    public function __construct(string $uploadBasePath, string $uploadBaseUrl = '/uploads')
    {
        $this->uploadBasePath = rtrim($uploadBasePath, '/');
        $this->uploadBaseUrl = rtrim($uploadBaseUrl, '/');

        // Load configuration
        $configFile = dirname(__DIR__, 2) . '/config/images.php';
        $this->config = file_exists($configFile) ? require $configFile : $this->getDefaultConfig();
    }

    /**
     * Upload image with required alt-text
     * Generates multiple size variants for responsive delivery
     *
     * @param array $file Uploaded file array from $_FILES
     * @param string $altText Required alt-text for accessibility
     * @param string $imageType Type: profile, cover, post, featured
     * @param string $entityType Entity: user, event, conversation, community
     * @param int $entityId Entity ID
     * @return array{success: bool, urls?: string, paths?: array, error?: string}
     */
    public function upload(array $file, string $altText, string $imageType, string $entityType, int $entityId): array
    {
        $logFile = dirname(__DIR__, 2) . '/debug.log';

        try {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "ImageService::upload called with imageType={$imageType}, entityType={$entityType}, entityId={$entityId}\n", FILE_APPEND);

            // Enforce alt-text requirement
            if (trim($altText) === '') {
                return ['success' => false, 'error' => 'Alt-text is required for accessibility.'];
            }

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Alt-text validated\n", FILE_APPEND);

            // Validate file
            $validation = $this->validate($file);
            if (!$validation['is_valid']) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Validation failed: " . ($validation['error'] ?? 'unknown') . "\n", FILE_APPEND);
                return ['success' => false, 'error' => $validation['error']];
            }

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "File validated\n", FILE_APPEND);

            // Set up directory
            $uploadDir = $this->getUploadDirectory($entityType, $entityId);
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Upload dir: {$uploadDir}\n", FILE_APPEND);

            if (!$this->ensureDirectoryExists($uploadDir)) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Failed to create directory\n", FILE_APPEND);
                return ['success' => false, 'error' => 'Failed to create upload directory.'];
            }

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Directory ensured\n", FILE_APPEND);

            // Generate all size variants
            $variants = $this->generateVariants($file, $imageType, $entityType, $entityId, $uploadDir);
            if (!$variants['success']) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Variant generation failed\n", FILE_APPEND);
                return $variants;
            }

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Upload successful with " . count($variants['urls']) . " variants\n", FILE_APPEND);

            return [
                'success' => true,
                'urls' => json_encode($variants['urls']),
                'paths' => $variants['paths'],
            ];
        } catch (\Throwable $e) {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "ImageService::upload exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
        }
    }

    /**
     * Validate uploaded file
     *
     * @param array $file Uploaded file from $_FILES
     * @return array{is_valid: bool, error?: string}
     */
    public function validate(array $file): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['is_valid' => false, 'error' => 'No file was uploaded.'];
        }

        $maxSize = $this->config['max_size'] ?? (10 * 1024 * 1024);
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / (1024 * 1024));
            return ['is_valid' => false, 'error' => "File must be less than {$maxMB}MB."];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = $this->config['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes, true)) {
            return ['is_valid' => false, 'error' => 'Only JPEG, PNG, GIF, and WebP images allowed.'];
        }

        return ['is_valid' => true];
    }

    /**
     * Delete image file
     */
    public function delete(string $filePath): bool
    {
        if (file_exists($filePath) && strpos($filePath, $this->uploadBasePath) === 0) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Delete all size variants for an image
     *
     * @param string|array $urlData Either JSON string or array of URLs
     * @return bool True if all files deleted successfully
     */
    public function deleteAllSizes(string|array $urlData): bool
    {
        $urls = is_string($urlData) ? json_decode($urlData, true) : $urlData;

        if (!is_array($urls)) {
            // Single URL fallback
            if (is_string($urlData) && !str_starts_with($urlData, '{')) {
                $path = $this->uploadBasePath . parse_url($urlData, PHP_URL_PATH);
                return $this->delete($path);
            }
            return false;
        }

        $allDeleted = true;
        foreach ($urls as $url) {
            $path = $this->uploadBasePath . parse_url($url, PHP_URL_PATH);
            if (!$this->delete($path)) {
                $allDeleted = false;
            }

            // Also try to delete WebP variant
            $webpPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $path);
            if (file_exists($webpPath)) {
                $this->delete($webpPath);
            }
        }

        return $allDeleted;
    }

    /**
     * Generate all size variants for an image
     *
     * @return array{success: bool, urls: array, paths: array, error?: string}
     */
    private function generateVariants(array $file, string $imageType, string $entityType, int $entityId, string $uploadDir): array
    {
        $sizeConfigs = $this->config['sizes'][$imageType] ?? $this->config['sizes']['post'] ?? [];

        if (empty($sizeConfigs)) {
            return ['success' => false, 'error' => 'No size configurations found for image type.'];
        }

        $sourceImage = $this->loadImage($file['tmp_name']);
        if ($sourceImage === false) {
            return ['success' => false, 'error' => 'Failed to load source image.'];
        }

        $baseFilename = $this->generateFilename($file, $imageType, $entityId, $entityType);
        $ext = pathinfo($baseFilename, PATHINFO_EXTENSION);
        $baseNameWithoutExt = pathinfo($baseFilename, PATHINFO_FILENAME);

        $urls = [];
        $paths = [];
        $relativePath = $this->getRelativePath($entityType, $entityId);

        foreach ($sizeConfigs as $sizeName => $dimensions) {
            $sizeFilename = "{$baseNameWithoutExt}_{$sizeName}.{$ext}";
            $filePath = $uploadDir . '/' . $sizeFilename;
            $fileUrl = $this->uploadBaseUrl . '/' . $relativePath . '/' . $sizeFilename;

            // Resize and save this variant
            $resized = $this->resize($sourceImage, $dimensions['width'], $dimensions['height']);
            $saved = $this->saveImage($resized, $filePath);

            if ($resized !== $sourceImage) {
                imagedestroy($resized);
            }

            if (!$saved) {
                imagedestroy($sourceImage);
                return ['success' => false, 'error' => "Failed to save {$sizeName} variant."];
            }

            $urls[$sizeName] = $fileUrl;
            $paths[$sizeName] = $filePath;

            // Generate WebP variant if configured
            if ($this->config['generate_webp'] ?? false) {
                $this->generateWebPVariant($filePath, $resized ?? $sourceImage);
            }
        }

        imagedestroy($sourceImage);

        return [
            'success' => true,
            'urls' => $urls,
            'paths' => $paths,
        ];
    }

    /**
     * Generate WebP variant alongside original format
     */
    private function generateWebPVariant(string $originalPath, $image): void
    {
        if (!function_exists('imagewebp')) {
            return;
        }

        $webpPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $originalPath);
        $quality = $this->config['quality']['webp'] ?? 85;

        imagewebp($image, $webpPath, $quality);
    }

    /**
     * Load image from file
     */
    private function loadImage(string $path)
    {
        $info = getimagesize($path);
        if ($info === false) {
            return false;
        }

        return match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * Resize image maintaining aspect ratio
     */
    private function resize($source, int $maxWidth, int $maxHeight)
    {
        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);

        // Don't upscale
        if ($ratio > 1) {
            return $source;
        }

        $newWidth = (int)($origWidth * $ratio);
        $newHeight = (int)($origHeight * $ratio);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        return $resized;
    }

    /**
     * Save image to file with configured quality
     */
    private function saveImage($image, string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $quality = $this->config['quality'] ?? [];

        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, $quality['jpeg'] ?? 90),
            'png' => imagepng($image, $path, $quality['png'] ?? 8),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, $quality['webp'] ?? 90),
            default => false,
        };
    }

    private function getUploadDirectory(string $entityType, int $entityId): string
    {
        return $this->uploadBasePath . '/' . $this->getRelativePath($entityType, $entityId);
    }

    private function getRelativePath(string $entityType, int $entityId): string
    {
        // Use hash prefix for better filesystem performance at scale
        // Hash = last 2 digits of entity_id, zero-padded
        $hashPrefix = str_pad((string)($entityId % 100), 2, '0', STR_PAD_LEFT);

        return match ($entityType) {
            'event' => "events/{$hashPrefix}/{$entityId}",
            'conversation' => "conversations/{$hashPrefix}/{$entityId}",
            'community' => "communities/{$hashPrefix}/{$entityId}",
            'user' => "users/{$hashPrefix}/{$entityId}",
            default => "{$entityType}s/{$hashPrefix}/{$entityId}",
        };
    }

    private function ensureDirectoryExists(string $dir): bool
    {
        if (file_exists($dir)) {
            return true;
        }
        return mkdir($dir, 0755, true);
    }

    private function generateFilename(array $file, string $type, int $entityId, string $entityType): string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $timestamp = time();
        $random = substr(bin2hex(random_bytes(4)), 0, 8);
        return sprintf('%s_%s_%s_%s_%s.%s', $entityType, $entityId, $type, $timestamp, $random, $ext);
    }

    /**
     * Get default configuration fallback
     *
     * Used when config/images.php does not exist
     */
    private function getDefaultConfig(): array
    {
        return [
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
        ];
    }
}
