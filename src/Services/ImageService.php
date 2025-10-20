<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

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
    private Database $db;

    public function __construct(Database $db, string $uploadBasePath, string $uploadBaseUrl = '/uploads')
    {
        $this->db = $db;
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
     * @param string $imageType Type: profile, cover, post, featured, reply
     * @param string $entityType Entity: user, event, conversation, community
     * @param int $entityId Entity ID
     * @param int $uploaderId User ID who is uploading the image (optional)
     * @param array $context Optional context allocation (community_id, event_id, conversation_id, reply_id)
     * @return array{success: bool, image_id?: int, urls?: string, paths?: array, error?: string}
     */
    public function upload(
        array $file,
        string $altText,
        string $imageType,
        string $entityType,
        int $entityId,
        int $uploaderId = 0,
        array $context = []
    ): array
    {
        try {
            if (trim($altText) === '') {
                return ['success' => false, 'error' => 'Alt-text is required for accessibility.'];
            }

            $validation = $this->validate($file);
            if (!$validation['is_valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }

            $uploadDir = $this->getUploadDirectory($entityType, $entityId);
            if (!$this->ensureDirectoryExists($uploadDir)) {
                return ['success' => false, 'error' => 'Failed to create upload directory.'];
            }

            $variants = $this->generateVariants($file, $imageType, $entityType, $entityId, $uploadDir);
            if (!$variants['success']) {
                return $variants;
            }

            $imageInfo = getimagesize($file['tmp_name']);
            $width = $imageInfo[0] ?? null;
            $height = $imageInfo[1] ?? null;

            $imageId = $this->trackInDatabase(
                uploaderId: $uploaderId,
                imageType: $imageType,
                urls: json_encode($variants['urls']),
                altText: $altText,
                filePath: $variants['paths']['original'] ?? reset($variants['paths']),
                fileSize: $file['size'],
                mimeType: $file['type'],
                width: $width,
                height: $height,
                context: $context
            );

            return [
                'success' => true,
                'image_id' => $imageId,
                'urls' => json_encode($variants['urls']),
                'paths' => $variants['paths'],
            ];
        } catch (\Throwable $e) {
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

    /**
     * Track uploaded image in database with context allocation
     *
     * @param int $uploaderId User who uploaded the image
     * @param string $imageType Type: profile, cover, featured, post, reply
     * @param string $urls JSON string of all size variant URLs
     * @param string $altText Alt text for accessibility
     * @param string $filePath Primary file path
     * @param int $fileSize File size in bytes
     * @param string $mimeType MIME type
     * @param int|null $width Original image width
     * @param int|null $height Original image height
     * @param array $context Context allocation (community_id, event_id, conversation_id, reply_id)
     * @return int Image ID
     */
    private function trackInDatabase(
        int $uploaderId,
        string $imageType,
        string $urls,
        string $altText,
        string $filePath,
        int $fileSize,
        string $mimeType,
        ?int $width,
        ?int $height,
        array $context
    ): int {
        $pdo = $this->db->pdo();

        // Determine which active flag to set based on image type
        $activeFlagColumn = match ($imageType) {
            'featured' => 'is_event_cover',
            'cover' => 'is_community_cover',
            'profile' => 'is_profile_image',
            default => null,
        };

        // If this is a cover/featured image, mark previous ones as inactive
        if ($activeFlagColumn !== null) {
            $this->deactivatePreviousImage($activeFlagColumn, $context);
        }

        // Insert new image record
        $stmt = $pdo->prepare("
            INSERT INTO images (
                uploader_id, image_type, urls, alt_text, file_path,
                file_size, mime_type, width, height,
                community_id, event_id, conversation_id, reply_id,
                is_community_cover, is_event_cover, is_profile_image, is_cover_image,
                created_at, is_active
            ) VALUES (
                :uploader_id, :image_type, :urls, :alt_text, :file_path,
                :file_size, :mime_type, :width, :height,
                :community_id, :event_id, :conversation_id, :reply_id,
                :is_community_cover, :is_event_cover, :is_profile_image, :is_cover_image,
                NOW(), 1
            )
        ");

        $stmt->execute([
            ':uploader_id' => $uploaderId,
            ':image_type' => $imageType,
            ':urls' => $urls,
            ':alt_text' => $altText,
            ':file_path' => $filePath,
            ':file_size' => $fileSize,
            ':mime_type' => $mimeType,
            ':width' => $width,
            ':height' => $height,
            ':community_id' => $context['community_id'] ?? null,
            ':event_id' => $context['event_id'] ?? null,
            ':conversation_id' => $context['conversation_id'] ?? null,
            ':reply_id' => $context['reply_id'] ?? null,
            ':is_community_cover' => ($activeFlagColumn === 'is_community_cover') ? 1 : 0,
            ':is_event_cover' => ($activeFlagColumn === 'is_event_cover') ? 1 : 0,
            ':is_profile_image' => ($activeFlagColumn === 'is_profile_image') ? 1 : 0,
            ':is_cover_image' => ($activeFlagColumn === 'is_cover_image') ? 1 : 0,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Deactivate previous image with same active flag and context
     *
     * @param string $activeFlagColumn Column name (is_event_cover, is_community_cover, etc.)
     * @param array $context Context to match
     */
    private function deactivatePreviousImage(string $activeFlagColumn, array $context): void
    {
        $pdo = $this->db->pdo();

        // Build WHERE clause based on context
        $whereClauses = ["{$activeFlagColumn} = 1"];
        $params = [];

        if (isset($context['community_id'])) {
            $whereClauses[] = 'community_id = :community_id';
            $params[':community_id'] = $context['community_id'];
        }
        if (isset($context['event_id'])) {
            $whereClauses[] = 'event_id = :event_id';
            $params[':event_id'] = $context['event_id'];
        }
        if (isset($context['conversation_id'])) {
            $whereClauses[] = 'conversation_id = :conversation_id';
            $params[':conversation_id'] = $context['conversation_id'];
        }
        if (isset($context['reply_id'])) {
            $whereClauses[] = 'reply_id = :reply_id';
            $params[':reply_id'] = $context['reply_id'];
        }

        $whereClause = implode(' AND ', $whereClauses);

        $stmt = $pdo->prepare("
            UPDATE images
            SET {$activeFlagColumn} = 0
            WHERE {$whereClause}
        ");

        $stmt->execute($params);
    }
}
