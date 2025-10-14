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
        return new self($_GET, $_POST, $_SERVER);
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
}
