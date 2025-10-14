<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Modern Image Service
 *
 * Handles image upload, validation, resizing with alt-text enforcement.
 */
final class ImageService
{
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    private const DIMENSIONS = [
        'profile' => ['width' => 400, 'height' => 400],
        'cover' => ['width' => 1200, 'height' => 400],
        'post' => ['width' => 800, 'height' => 600],
        'featured' => ['width' => 1200, 'height' => 630],
    ];

    private string $uploadBasePath;
    private string $uploadBaseUrl;

    public function __construct(string $uploadBasePath, string $uploadBaseUrl = '/uploads')
    {
        $this->uploadBasePath = rtrim($uploadBasePath, '/');
        $this->uploadBaseUrl = rtrim($uploadBaseUrl, '/');
    }

    /**
     * Upload image with required alt-text
     *
     * @param array $file Uploaded file array from $_FILES
     * @param string $altText Required alt-text for accessibility
     * @param string $imageType Type: profile, cover, post, featured
     * @param string $entityType Entity: user, event, conversation, community
     * @param int $entityId Entity ID
     * @return array{success: bool, url?: string, path?: string, error?: string}
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

            // Generate filename
            $filename = $this->generateFilename($file, $imageType, $entityId, $entityType);
            $filePath = $uploadDir . '/' . $filename;
            $fileUrl = $this->uploadBaseUrl . '/' . $this->getRelativePath($entityType, $entityId) . '/' . $filename;

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Filename: {$filename}, Path: {$filePath}\n", FILE_APPEND);

            // Process and save
            $result = $this->processAndSave($file, $filePath, $imageType);
            if (!$result['success']) {
                file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "processAndSave failed\n", FILE_APPEND);
                return $result;
            }

            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Upload successful: {$fileUrl}\n", FILE_APPEND);

            return [
                'success' => true,
                'url' => $fileUrl,
                'path' => $filePath,
                'filename' => $filename,
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

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['is_valid' => false, 'error' => 'File must be less than 10MB.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
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
     * Process and save image with resizing
     */
    private function processAndSave(array $file, string $filePath, string $imageType): array
    {
        $dimensions = self::DIMENSIONS[$imageType] ?? self::DIMENSIONS['post'];

        $image = $this->loadImage($file['tmp_name']);
        if ($image === false) {
            return ['success' => false, 'error' => 'Failed to load image.'];
        }

        $resized = $this->resize($image, $dimensions['width'], $dimensions['height']);
        $saved = $this->saveImage($resized, $filePath);

        imagedestroy($image);
        if ($resized !== $image) {
            imagedestroy($resized);
        }

        if (!$saved) {
            return ['success' => false, 'error' => 'Failed to save image.'];
        }

        return ['success' => true];
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
     * Save image to file
     */
    private function saveImage($image, string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path, 8),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path, 90),
            default => false,
        };
    }

    private function getUploadDirectory(string $entityType, int $entityId): string
    {
        return $this->uploadBasePath . '/' . $this->getRelativePath($entityType, $entityId);
    }

    private function getRelativePath(string $entityType, int $entityId): string
    {
        return match ($entityType) {
            'event' => "events/{$entityId}",
            'conversation' => "conversations/{$entityId}",
            'community' => "communities/{$entityId}",
            'user' => "users/{$entityId}",
            default => "{$entityType}s/{$entityId}",
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
}
