<?php
declare(strict_types=1);

namespace App\Services\BlueskyAgent;

final class BlueskyResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public bool $needsReauth = false,
        public array $payload = []
    ) {
    }

    /**
     * @param array<string,mixed> $response
     */
    public static function fromArray(array $response): self
    {
        return new self(
            (bool)($response['success'] ?? false),
            isset($response['message']) ? (string)$response['message'] : null,
            (bool)($response['needs_reauth'] ?? false),
            $response
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'needs_reauth' => $this->needsReauth,
            'payload' => $this->payload,
        ];
    }
}
