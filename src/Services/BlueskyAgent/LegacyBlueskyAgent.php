<?php
declare(strict_types=1);

namespace App\Services\BlueskyAgent;

use App\Services\BlueskyService;

final class LegacyBlueskyAgent implements BlueskyAgentInterface
{
    public function __construct(private BlueskyService $service)
    {
    }

    public function createPostForMember(int $memberId, array $record): BlueskyResult
    {
        $text = (string)($record['text'] ?? '');
        $mentions = $this->normalizeMentions($record['mentions'] ?? []);
        $links = $this->normalizeLinks($record['links'] ?? []);

        $response = $this->service->createPostLegacy($memberId, $text, $mentions, $links);
        return BlueskyResult::fromArray($response);
    }

    /**
     * @param mixed $mentions
     * @return array<int,array<string,string>>
     */
    private function normalizeMentions(mixed $mentions): array
    {
        if (!is_array($mentions)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($mention): ?array {
            if (!is_array($mention)) {
                return null;
            }

            $handle = isset($mention['handle']) ? (string)$mention['handle'] : '';
            $did = isset($mention['did']) ? (string)$mention['did'] : '';

            if ($handle === '' || $did === '') {
                return null;
            }

            return [
                'handle' => $handle,
                'did' => $did,
            ];
        }, $mentions)));
    }

    /**
     * @param mixed $links
     * @return array<int,array<string,string>>
     */
    private function normalizeLinks(mixed $links): array
    {
        if (!is_array($links)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($link): ?array {
            if (!is_array($link)) {
                return null;
            }

            $url = isset($link['url']) ? (string)$link['url'] : '';
            if ($url === '') {
                return null;
            }

            return ['url' => $url];
        }, $links)));
    }
}
