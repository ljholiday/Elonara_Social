<?php
declare(strict_types=1);

namespace App\Services\Embed;

/**
 * Secure HTTP Client for External Requests
 *
 * Provides SSRF protection and secure fetching for embed data.
 */
final class HttpClient
{
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB
    private const TIMEOUT = 10; // seconds
    private const MAX_REDIRECTS = 3;

    /**
     * Fetch URL with security checks
     *
     * @param string $url URL to fetch
     * @param array<string, mixed> $options Optional settings (timeout, max_redirects)
     * @return array{success: bool, body?: string, error?: string, status?: int}
     */
    public function get(string $url, array $options = []): array
    {
        // Validate URL
        if (!$this->isValidUrl($url)) {
            return ['success' => false, 'error' => 'Invalid URL'];
        }

        // SSRF protection
        if (!$this->isSafeUrl($url)) {
            return ['success' => false, 'error' => 'URL blocked for security'];
        }

        $timeout = $options['timeout'] ?? self::TIMEOUT;
        $maxRedirects = $options['max_redirects'] ?? self::MAX_REDIRECTS;

        // Try cURL first
        if (function_exists('curl_init')) {
            return $this->fetchWithCurl($url, $timeout, $maxRedirects);
        }

        // Fallback to file_get_contents
        return $this->fetchWithFileGetContents($url, $timeout);
    }

    /**
     * Fetch and decode JSON
     *
     * @param string $url URL to fetch
     * @return array{success: bool, data?: mixed, error?: string}
     */
    public function getJson(string $url): array
    {
        $response = $this->get($url);

        if (!$response['success']) {
            return $response;
        }

        $data = json_decode($response['body'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }

        return ['success' => true, 'data' => $data];
    }

    private function fetchWithCurl(string $url, int $timeout, int $maxRedirects): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['success' => false, 'error' => 'Failed to initialize cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => $maxRedirects,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Elonara Social/1.0',
            CURLOPT_MAXFILESIZE => self::MAX_SIZE,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['success' => false, 'error' => $error ?: 'cURL request failed'];
        }

        if ($status < 200 || $status >= 400) {
            return ['success' => false, 'error' => "HTTP $status", 'status' => $status];
        }

        return ['success' => true, 'body' => $body, 'status' => $status];
    }

    private function fetchWithFileGetContents(string $url, int $timeout): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'Elonara Social/1.0',
                'follow_location' => 1,
                'max_redirects' => self::MAX_REDIRECTS,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return ['success' => false, 'error' => 'Failed to fetch URL'];
        }

        return ['success' => true, 'body' => $body];
    }

    private function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        return in_array($parsed['scheme'], ['http', 'https'], true);
    }

    private function isSafeUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }

        $host = $parsed['host'];

        // Block localhost
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        // Resolve hostname to IP
        $ip = gethostbyname($host);
        if ($ip === $host) {
            // DNS resolution failed, allow it (external DNS will handle)
            return true;
        }

        // Block private IP ranges
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }
}
