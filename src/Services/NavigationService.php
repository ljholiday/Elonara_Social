<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AuthorizationService;

/**
 * NavigationService
 *
 * Builds secondary navigation tabs for entities based on current user permissions
 * and current route. Follows doctrine: controllers call this service, templates
 * receive tab arrays with no logic.
 */
class NavigationService
{
    private AuthorizationService $authorization;

    public function __construct(AuthorizationService $authorization)
    {
        $this->authorization = $authorization;
    }

    /**
     * Build Event detail/manage page tabs
     *
     * @param array|null $event Event data with id, slug, host_id
     * @param \stdClass|array|null $currentUser Current user data
     * @param string $currentRoute Current route (e.g., '/events/{slug}')
     * @return array<int, array{title: string, url: string, active: bool}>
     */
    public function buildEventTabs(?array $event, \stdClass|array|null $currentUser, string $currentRoute): array
    {
        if ($event === null) {
            return [];
        }

        $slug = $event['slug'] ?? '';
        $tabs = [];

        // View Event tab (always visible)
        $tabs[] = [
            'title' => 'View Event',
            'url' => '/events/' . $slug,
            'active' => $currentRoute === '/events/' . $slug || str_ends_with($currentRoute, '/events/' . $slug)
        ];

        // Conversations tab (always visible)
        $tabs[] = [
            'title' => 'Conversations',
            'url' => '/events/' . $slug . '/conversations',
            'active' => str_contains($currentRoute, '/events/' . $slug . '/conversations')
        ];

        // Manage tab (only if user can edit)
        $userId = $this->getUserId($currentUser);
        if ($userId > 0 && $this->authorization->canEditEvent($event, $userId)) {
            $tabs[] = [
                'title' => 'Manage',
                'url' => '/events/' . $slug . '/manage?tab=settings',
                'active' => str_contains($currentRoute, '/events/' . $slug . '/manage')
            ];
        }

        return $tabs;
    }

    /**
     * Build Event manage page tabs (Settings, Guests, Invitations)
     *
     * @param array|null $event Event data
     * @param string $currentRoute Current route
     * @return array<int, array{title: string, url: string, active: bool}>
     */
    public function buildEventManageTabs(?array $event, string $currentRoute): array
    {
        if ($event === null) {
            return [];
        }

        $slug = $event['slug'] ?? '';

        // Extract query param from current route
        $tab = '';
        if (str_contains($currentRoute, '?tab=')) {
            parse_str(parse_url($currentRoute, PHP_URL_QUERY) ?? '', $query);
            $tab = $query['tab'] ?? '';
        }

        return [
            [
                'title' => 'View Event',
                'url' => '/events/' . $slug,
                'active' => false
            ],
            [
                'title' => 'Edit',
                'url' => '/events/' . $slug . '/edit',
                'active' => false
            ],
            [
                'title' => 'Guests',
                'url' => '/events/' . $slug . '/manage?tab=guests',
                'active' => $tab === 'guests'
            ],
            [
                'title' => 'Invitations',
                'url' => '/events/' . $slug . '/manage?tab=invites',
                'active' => $tab === 'invites'
            ]
        ];
    }

    /**
     * Build Community detail/manage page tabs
     *
     * @param array|null $community Community data with id, slug
     * @param \stdClass|array|null $currentUser Current user data
     * @param string $currentRoute Current route
     * @return array<int, array{title: string, url: string, active: bool}>
     */
    public function buildCommunityTabs(?array $community, \stdClass|array|null $currentUser, string $currentRoute): array
    {
        if ($community === null) {
            return [];
        }

        $slug = $community['slug'] ?? '';
        $tabs = [];

        // View Community tab (always visible)
        $tabs[] = [
            'title' => 'View Community',
            'url' => '/communities/' . $slug,
            'active' => $currentRoute === '/communities/' . $slug || str_ends_with($currentRoute, '/communities/' . $slug)
        ];

        // Events tab (always visible)
        $tabs[] = [
            'title' => 'Events',
            'url' => '/communities/' . $slug . '/events',
            'active' => str_contains($currentRoute, '/communities/' . $slug . '/events')
        ];

        // Conversations tab (always visible)
        $tabs[] = [
            'title' => 'Conversations',
            'url' => '/communities/' . $slug . '/conversations',
            'active' => str_contains($currentRoute, '/communities/' . $slug . '/conversations')
        ];

        // Members tab (always visible)
        $tabs[] = [
            'title' => 'Members',
            'url' => '/communities/' . $slug . '/members',
            'active' => str_contains($currentRoute, '/communities/' . $slug . '/members')
        ];

        // Manage tab (only if user is admin)
        $userId = $this->getUserId($currentUser);
        if ($userId > 0 && $this->authorization->canEditCommunity($community, $userId)) {
            $tabs[] = [
                'title' => 'Manage',
                'url' => '/communities/' . $slug . '/manage?tab=members',
                'active' => str_contains($currentRoute, '/communities/' . $slug . '/manage')
            ];
        }

        return $tabs;
    }

    /**
     * Build Community manage page tabs
     *
     * @param array|null $community Community data
     * @param string $currentRoute Current route
     * @return array<int, array{title: string, url: string, active: bool}>
     */
    public function buildCommunityManageTabs(?array $community, string $currentRoute): array
    {
        if ($community === null) {
            return [];
        }

        $slug = $community['slug'] ?? '';

        // Extract query param from current route
        $tab = '';
        if (str_contains($currentRoute, '?tab=')) {
            parse_str(parse_url($currentRoute, PHP_URL_QUERY) ?? '', $query);
            $tab = $query['tab'] ?? '';
        }

        return [
            [
                'title' => 'View Community',
                'url' => '/communities/' . $slug,
                'active' => false
            ],
            [
                'title' => 'Edit',
                'url' => '/communities/' . $slug . '/edit',
                'active' => false
            ],
            [
                'title' => 'Members',
                'url' => '/communities/' . $slug . '/manage?tab=members',
                'active' => $tab === 'members'
            ],
            [
                'title' => 'Invitations',
                'url' => '/communities/' . $slug . '/manage?tab=invites',
                'active' => $tab === 'invites'
            ]
        ];
    }

    /**
     * Build Conversation detail/edit page tabs
     *
     * @param array|null $conversation Conversation data
     * @param \stdClass|array|null $currentUser Current user data
     * @param string $currentRoute Current route
     * @return array<int, array{title: string, url: string, active: bool}>
     */
    public function buildConversationTabs(?array $conversation, \stdClass|array|null $currentUser, string $currentRoute): array
    {
        if ($conversation === null) {
            return [];
        }

        $slug = $conversation['slug'] ?? '';
        $tabs = [];

        // Conversation tab (always visible)
        $tabs[] = [
            'title' => 'Conversation',
            'url' => '/conversations/' . $slug,
            'active' => $currentRoute === '/conversations/' . $slug || str_ends_with($currentRoute, '/conversations/' . $slug)
        ];

        // Reply button (always visible on conversation detail page)
        if ($currentRoute === '/conversations/' . $slug || str_ends_with($currentRoute, '/conversations/' . $slug)) {
            $tabs[] = [
                'title' => 'Reply',
                'type' => 'button',
                'data' => ['open-reply-modal' => ''],
                'active' => false
            ];
        }

        // Edit tab (only if user can edit)
        $userId = $this->getUserId($currentUser);
        if ($userId > 0 && $this->authorization->canEditConversation($conversation, $userId)) {
            $tabs[] = [
                'title' => 'Edit',
                'url' => '/conversations/' . $slug . '/edit',
                'active' => str_contains($currentRoute, '/conversations/' . $slug . '/edit')
            ];
        }

        return $tabs;
    }

    /**
     * Build Profile view/edit page tabs
     *
     * @param array|null $user User data
     * @param \stdClass|array|null $currentUser Current user data
     * @param string $currentRoute Current route
     * @return array<int, array{title: string, url: string, active: bool}>
     */
    public function buildProfileTabs(?array $user, \stdClass|array|null $currentUser, string $currentRoute): array
    {
        if ($user === null) {
            return [];
        }

        $username = $user['username'] ?? '';
        $tabs = [];

        // View Profile tab (always visible)
        $tabs[] = [
            'title' => 'View Profile',
            'url' => '/profile/' . $username,
            'active' => $currentRoute === '/profile/' . $username || str_ends_with($currentRoute, '/profile/' . $username)
        ];

        // Edit Profile tab (only if viewing own profile)
        $userId = $this->getUserId($currentUser);
        if ($userId > 0 && $userId === (int)($user['id'] ?? 0)) {
            $tabs[] = [
                'title' => 'Edit Profile',
                'url' => '/profile/edit',
                'active' => str_contains($currentRoute, '/profile/edit')
            ];
        }

        return $tabs;
    }

    /**
     * Extract user ID from stdClass or array
     *
     * @param \stdClass|array|null $user User data
     * @return int User ID or 0 if not found
     */
    private function getUserId(\stdClass|array|null $user): int
    {
        if ($user === null) {
            return 0;
        }

        if (is_object($user)) {
            return (int)($user->id ?? 0);
        }

        return (int)($user['id'] ?? 0);
    }
}
