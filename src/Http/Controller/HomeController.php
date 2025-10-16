<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Services\AuthService;
use App\Services\EventService;
use App\Services\CommunityService;
use App\Services\ConversationService;
use App\Services\CircleService;
use App\Support\ContextBuilder;
use App\Support\ContextLabel;

final class HomeController
{
    public function __construct(
        private AuthService $auth,
        private EventService $events,
        private CommunityService $communities,
        private ConversationService $conversations,
        private CircleService $circles
    ) {
    }

    /**
     * @return array{
     *   viewer: object,
     *   upcoming_events: array<int, array<string,mixed>>,
     *   my_communities: array<int, array<string,mixed>>,
     *   recent_conversations: array<int, array<string,mixed>>
     * }
     */
    public function dashboard(): array
    {
        $viewer = $this->auth->getCurrentUser();
        if ($viewer === null) {
            throw new \RuntimeException('Viewer must be logged in before rendering the dashboard.');
        }

        $viewerId = (int)($viewer->id ?? 0);
        $viewerEmail = $viewer->email ?? null;

        $events = $viewerId > 0
            ? $this->events->listMine($viewerId, $viewerEmail, 5)
            : [];

        $context = $this->circles->buildContext($viewerId);
        $memberCommunities = $this->circles->memberCommunities($context);
        $communities = $memberCommunities !== []
            ? $this->communities->listByIds(array_slice($memberCommunities, 0, 6))
            : [];

        $events = array_map(function (array $event): array {
            $path = ContextBuilder::event($event, $this->communities);
            $plain = ContextLabel::renderPlain($path);
            $html = ContextLabel::render($path);
            $event['context_path'] = $path;
            $event['context_label'] = $plain !== '' ? $plain : (string)($event['title'] ?? '');
            $event['context_label_html'] = $html !== '' ? $html : htmlspecialchars((string)($event['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            return $event;
        }, $events);

        $recentConversations = array_map(function (array $conversation): array {
            $path = ContextBuilder::conversation($conversation, $this->communities, $this->events);
            $plain = ContextLabel::renderPlain($path);
            $html = ContextLabel::render($path);
            $conversation['context_path'] = $path;
            $conversation['context_label'] = $plain !== '' ? $plain : (string)($conversation['title'] ?? '');
            $conversation['context_label_html'] = $html !== '' ? $html : htmlspecialchars((string)($conversation['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            return $conversation;
        }, $this->conversations->listRecent(5));

        return [
            'viewer' => $viewer,
            'upcoming_events' => $events,
            'my_communities' => $communities,
            'recent_conversations' => $recentConversations,
        ];
    }
}
