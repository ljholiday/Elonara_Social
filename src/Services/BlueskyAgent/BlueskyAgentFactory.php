<?php
declare(strict_types=1);

namespace App\Services\BlueskyAgent;

use App\Services\BlueskyService;

final class BlueskyAgentFactory
{
    public function __construct(private BlueskyService $service)
    {
    }

    public function make(?string $mode = null): BlueskyAgentInterface
    {
        $mode = strtolower($mode ?? (string)app_config('bluesky.writes.mode', 'legacy'));

        if ($mode === 'oauth') {
            return new OAuthBlueskyAgent($this->service);
        }

        return new LegacyBlueskyAgent($this->service);
    }
}
