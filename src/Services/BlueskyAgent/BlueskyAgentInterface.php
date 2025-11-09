<?php
declare(strict_types=1);

namespace App\Services\BlueskyAgent;

interface BlueskyAgentInterface
{
    /**
     * @param array<string,mixed> $record
     */
    public function createPostForMember(int $memberId, array $record): BlueskyResult;
}
