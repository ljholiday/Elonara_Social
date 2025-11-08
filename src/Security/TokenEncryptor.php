<?php
declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Encrypts and decrypts OAuth tokens at rest.
 */
final class TokenEncryptor
{
    private const DEFAULT_CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $cipher;
    private string $key;

    public function __construct(?string $key = null, ?string $cipher = null)
    {
        $resolvedKey = $key ?? (string)(app_config('bluesky.encryption.key') ?? '');
        if ($resolvedKey === '') {
            $resolvedKey = $_ENV['BLUESKY_TOKEN_KEY'] ?? (string)($_ENV['SECURITY_KEY'] ?? '');
        }

        if ($resolvedKey === '') {
            throw new RuntimeException('Bluesky token encryption key is not configured.');
        }

        $this->key = hash('sha256', $resolvedKey, true);
        $this->cipher = $cipher ?: (string)(app_config('bluesky.encryption.cipher', self::DEFAULT_CIPHER));

        $available = array_map('strtolower', openssl_get_cipher_methods(true));
        if (!in_array(strtolower($this->cipher), $available, true)) {
            throw new RuntimeException(sprintf('Cipher "%s" is not supported on this host.', $this->cipher));
        }
    }

    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $value,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt Bluesky token.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = base64_decode($value, true);
        if ($decoded === false || strlen($decoded) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($decoded, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt($ciphertext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plaintext === false ? null : $plaintext;
    }
}
