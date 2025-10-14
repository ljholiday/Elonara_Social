<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Modern Security Service
 *
 * Provides CSRF protection, token generation, and security utilities
 * for the Elonara Social application.
 */
final class SecurityService
{
    private const NONCE_LIFETIME = 86400; // 24 hours in seconds
    private const NONCE_LENGTH = 12;

    private string $secretKey;
    private array $salts;

    public function __construct()
    {
        $this->initializeSecrets();
    }

    /**
     * Initialize security secrets from configuration
     */
    private function initializeSecrets(): void
    {
        // Load secret key from environment or generate
        $this->secretKey = $_ENV['SECURITY_KEY'] ?? $this->getOrGenerateSecret('security_key');

        // Load salts from config file
        $this->salts = $this->getOrGenerateSalts();
    }

    /**
     * Create a CSRF nonce for a specific action
     *
     * @param string $action Action identifier (e.g., 'create_event', 'delete_community')
     * @param int $userId User ID for user-specific nonces
     * @return string Short nonce token
     */
    public function createNonce(string $action = '', int $userId = 0): string
    {
        // Use time buckets for nonce rotation (12-hour buckets within 24-hour lifetime)
        $timeBucket = (int)floor(time() / (self::NONCE_LIFETIME / 2)) * (self::NONCE_LIFETIME / 2);

        // Create token from user, action, and time
        $token = $userId . '|' . $action . '|' . $timeBucket;

        // Hash with nonce-specific salt
        $hash = $this->hash($token, 'nonce');

        // Return truncated hash as nonce
        return substr($hash, 0, self::NONCE_LENGTH);
    }

    /**
     * Verify a CSRF nonce
     *
     * @param string $nonce Nonce to verify
     * @param string $action Action identifier
     * @param int $userId User ID
     * @return bool True if nonce is valid
     */
    public function verifyNonce(string $nonce, string $action = '', int $userId = 0): bool
    {
        if (empty($nonce)) {
            return false;
        }

        $currentTime = time();

        // Check current and two previous time buckets (covers full 24-hour lifetime)
        for ($i = 0; $i <= 2; $i++) {
            $timeBucket = (int)floor(($currentTime - ($i * (self::NONCE_LIFETIME / 2))) / (self::NONCE_LIFETIME / 2)) * (self::NONCE_LIFETIME / 2);
            $token = $userId . '|' . $action . '|' . $timeBucket;
            $expectedHash = $this->hash($token, 'nonce');
            $expectedNonce = substr($expectedHash, 0, self::NONCE_LENGTH);

            if (hash_equals($expectedNonce, $nonce)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate HTML hidden input field for CSRF nonce
     *
     * @param string $action Action identifier
     * @param string $fieldName Field name (default: '_app_nonce')
     * @param bool $includeReferer Include HTTP referer field
     * @return string HTML input field
     */
    public function nonceField(string $action = '', string $fieldName = '_app_nonce', bool $includeReferer = true): string
    {
        $userId = $_SESSION['user_id'] ?? 0;
        $nonce = $this->createNonce($action, $userId);

        $field = '<input type="hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '" />';

        if ($includeReferer) {
            $referer = $_SERVER['REQUEST_URI'] ?? '';
            $field .= '<input type="hidden" name="_app_http_referer" value="' . htmlspecialchars($referer, ENT_QUOTES, 'UTF-8') . '" />';
        }

        return $field;
    }

    /**
     * Generate a secure random token
     *
     * @param int $length Length in bytes (output will be 2x as hex)
     * @return string Hexadecimal token
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate a secure random password
     *
     * @param int $length Password length
     * @return string Random password
     */
    public function generatePassword(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $maxIndex = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $maxIndex)];
        }

        return $password;
    }

    /**
     * Constant-time string comparison
     *
     * @param string $a First string
     * @param string $b Second string
     * @return bool True if strings match
     */
    public function compareStrings(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /**
     * Generate session-based CSRF token
     *
     * @return string CSRF token
     */
    public function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateToken(32);
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verify session-based CSRF token
     *
     * @param string|null $token Token to verify
     * @return bool True if valid
     */
    public function verifyCSRFToken(?string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || $token === null || $token === '') {
            return false;
        }

        return $this->compareStrings($_SESSION['csrf_token'], $token);
    }

    /**
     * Check rate limiting for a specific key
     *
     * @param string $key Rate limit key (e.g., 'login:user@example.com')
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if within limits
     */
    public function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 3600): bool
    {
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        $attempts = $_SESSION['rate_limit'][$key] ?? [];
        $currentTime = time();

        // Remove old attempts outside time window
        $attempts = array_filter($attempts, static function($timestamp) use ($currentTime, $timeWindow): bool {
            return ($currentTime - $timestamp) < $timeWindow;
        });

        // Check if limit exceeded
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Record this attempt
        $attempts[] = $currentTime;
        $_SESSION['rate_limit'][$key] = $attempts;

        return true;
    }

    /**
     * Get security headers for HTTP responses
     *
     * @return array<string, string> Header name => value pairs
     */
    public function getSecurityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains'
        ];
    }

    /**
     * Hash password using modern algorithm
     *
     * @param string $password Plain text password
     * @return string Password hash
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify password against hash
     *
     * @param string $password Plain text password
     * @param string $hash Password hash
     * @return bool True if password matches
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate secure hash with HMAC
     *
     * @param string $data Data to hash
     * @param string $scheme Salt scheme ('auth', 'nonce', 'session')
     * @return string Hash
     */
    private function hash(string $data, string $scheme = 'auth'): string
    {
        $salt = $this->getSalt($scheme);
        return hash_hmac('sha256', $data, $salt);
    }

    /**
     * Get salt for specific scheme
     *
     * @param string $scheme Salt scheme
     * @return string Salt value
     */
    private function getSalt(string $scheme = 'auth'): string
    {
        if (!isset($this->salts[$scheme])) {
            // Generate new salt if missing
            $this->salts[$scheme] = bin2hex(random_bytes(32));
            $this->storeSalts();
        }

        return $this->salts[$scheme];
    }

    /**
     * Get or generate secret key
     *
     * @param string $keyName Key name
     * @return string Secret key
     */
    private function getOrGenerateSecret(string $keyName): string
    {
        // In production, load from environment
        $key = $_ENV[$keyName] ?? null;

        if ($key === null || $key === '') {
            // Generate random key (should be stored in production)
            $key = bin2hex(random_bytes(32));
        }

        return $key;
    }

    /**
     * Get or generate salts from configuration
     *
     * @return array<string, string> Salt scheme => value pairs
     */
    private function getOrGenerateSalts(): array
    {
        $configPath = dirname(__DIR__, 2) . '/config/security_salts.php';

        if (file_exists($configPath)) {
            $salts = require $configPath;
            if (is_array($salts)) {
                return $salts;
            }
        }

        // Generate and save salts if they don't exist
        $salts = [
            'auth' => bin2hex(random_bytes(32)),
            'nonce' => bin2hex(random_bytes(32)),
            'session' => bin2hex(random_bytes(32))
        ];

        $this->createSaltsFile($configPath, $salts);

        return $salts;
    }

    /**
     * Create salts configuration file
     *
     * @param string $path File path
     * @param array<string, string> $salts Salts to store
     */
    private function createSaltsFile(string $path, array $salts): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Failed to create directory: %s', $directory));
            }
        }

        $content = "<?php\n// Security salts - DO NOT commit to git\n// Add config/security_salts.php to .gitignore\nreturn " . var_export($salts, true) . ";\n";

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Failed to write salts file: %s', $path));
        }
    }

    /**
     * Store updated salts (in-memory only for now)
     */
    private function storeSalts(): void
    {
        // Salts are stored in config file on first generation
        // Future updates would write to file if needed
    }
}
