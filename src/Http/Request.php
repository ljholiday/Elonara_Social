<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, mixed> */
    private array $server;

    private string $method;

    private string $uri;

    private function __construct(array $query, array $body, array $server)
    {
        $this->query = $query;
        $this->body = $body;
        $this->server = $server;

        $this->method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));
        $this->uri = (string)($server['REQUEST_URI'] ?? '/');
    }

    public static function fromGlobals(): self
    {
        $server = $_SERVER;
        $method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));
        $contentType = (string)($server['CONTENT_TYPE'] ?? '');

        $body = $_POST;

        // Parse JSON body for POST requests with application/json content type
        if ($body === [] && $method === 'POST' && stripos($contentType, 'application/json') !== false) {
            $parsedBody = self::parseInputStream($contentType);
            if ($parsedBody !== null) {
                $body = $parsedBody;
            }
        }

        // Parse body for other methods that don't populate $_POST
        if ($body === [] && in_array($method, ['PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)) {
            $parsedBody = self::parseInputStream($contentType);
            if ($parsedBody !== null) {
                $body = $parsedBody;
            }
        }

        return new self($_GET, $body, $server);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        return is_string($path) ? $path : '/';
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allInput(): array
    {
        return $this->body;
    }

    /**
     * Check if this is an AJAX request
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower((string)($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /**
     * Check if client expects JSON response
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        $accept = strtolower((string)($this->server['HTTP_ACCEPT'] ?? ''));
        return $this->isAjax() || str_contains($accept, 'application/json');
    }

    /**
     * Attempt to parse the raw php://input stream when PHP does not populate $_POST.
     *
     * @return array<string, mixed>|null
     */
    private static function parseInputStream(string $contentType): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        if (stripos($contentType, 'application/x-www-form-urlencoded') !== false || $contentType === '') {
            $parsed = [];
            parse_str($raw, $parsed);
            return is_array($parsed) ? $parsed : null;
        }

        return null;
    }
}
