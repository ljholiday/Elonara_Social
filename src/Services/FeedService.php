<?php
declare(strict_types=1);

namespace App\Services;

final class FeedService
{
    public function __construct(
        private CircleService $circles,
        private ConversationService $conversations
    ) {
    }

    /**
     * Get global feed filtered by author hop distance.
     * Per trust.xml Section 5: "Show a conversation if the author is within N hops,
     * regardless of which community it belongs to."
     *
     * @param array{page?: int, per_page?: int, filter?: string} $options
     * @return array{conversations: array, pagination: array, circle: string}
     */
    public function getGlobalFeed(int $viewerId, string $circle, array $options = []): array
    {
        $circle = strtolower($circle);
        $page = (int)($options['page'] ?? 1);
        $perPage = (int)($options['per_page'] ?? 20);
        $filter = $options['filter'] ?? '';

        $context = $this->circles->buildContext($viewerId);
        $allowedUsers = $this->circles->resolveUsersForCircle($context, $circle);
        $memberCommunities = $this->circles->memberCommunities($viewerId);

        $feed = $this->conversations->listByAuthorHop(
            $viewerId,
            $allowedUsers,
            $memberCommunities,
            [
                'page' => $page,
                'per_page' => $perPage,
                'filter' => $filter,
            ]
        );

        return [
            'conversations' => $feed['conversations'] ?? [],
            'pagination' => $feed['pagination'] ?? [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => false,
                'next_page' => null,
            ],
            'circle' => $circle,
        ];
    }

    /**
     * Get community feed showing all member content.
     * Per trust.xml Section 5: "Within a community, show all conversations and replies by any member,
     * as long as the community is public or the viewer is a member."
     *
     * @param array{page?: int, per_page?: int} $options
     * @return array{conversations: array, pagination: array, community_id: int}
     */
    public function getCommunityFeed(int $viewerId, int $communityId, array $options = []): array
    {
        $page = (int)($options['page'] ?? 1);
        $perPage = (int)($options['per_page'] ?? 20);

        $feed = $this->conversations->listByCommunity(
            $communityId,
            $viewerId,
            [
                'page' => $page,
                'per_page' => $perPage,
            ]
        );

        return [
            'conversations' => $feed['conversations'] ?? [],
            'pagination' => $feed['pagination'] ?? [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => false,
                'next_page' => null,
            ],
            'community_id' => $communityId,
        ];
    }

    /**
     * Get personal feed of user's own content.
     *
     * @param array{page?: int, per_page?: int} $options
     * @return array{conversations: array, pagination: array}
     */
    public function getPersonalFeed(int $userId, array $options = []): array
    {
        $page = (int)($options['page'] ?? 1);
        $perPage = (int)($options['per_page'] ?? 20);

        $memberCommunities = $this->circles->memberCommunities($userId);

        $feed = $this->conversations->listByAuthor(
            $userId,
            $userId,
            $memberCommunities,
            [
                'page' => $page,
                'per_page' => $perPage,
            ]
        );

        return [
            'conversations' => $feed['conversations'] ?? [],
            'pagination' => $feed['pagination'] ?? [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => false,
                'next_page' => null,
            ],
        ];
    }
}
