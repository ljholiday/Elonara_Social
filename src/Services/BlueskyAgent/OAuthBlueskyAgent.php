<?php
declare(strict_types=1);

namespace App\Services\BlueskyAgent;

use App\Services\BlueskyService;

final class OAuthBlueskyAgent implements BlueskyAgentInterface
{
    public function __construct(private BlueskyService $service)
    {
    }

    public function createPostForMember(int $memberId, array $record): BlueskyResult
    {
        $text = (string)($record['text'] ?? '');
        $mentions = is_array($record['mentions'] ?? null) ? $record['mentions'] : [];

        $links = is_array($record['links'] ?? null) ? $record['links'] : [];

        $response = $this->service->createPostOauth($memberId, $text, $mentions, $links);
        return BlueskyResult::fromArray($response);
    }
}
