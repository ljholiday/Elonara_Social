<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\CircleService;
use App\Services\ConversationService;
use App\Services\SecurityService;

require_once dirname(__DIR__, 3) . '/templates/_helpers.php';

final class ConversationApiController
{
    private const VALID_CIRCLES = ['inner', 'trusted', 'extended', 'all'];
    private const VALID_FILTERS = ['', 'my-events', 'all-events', 'communities'];

    public function __construct(
        private ConversationService $conversations,
        private CircleService $circles,
        private AuthService $auth,
        private SecurityService $security
    ) {
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function list(): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('User not authenticated', 401);
        }

        if (!$this->verifyNonce($nonce, 'vt_nonce', $viewerId)) {
            return $this->error('Security verification failed', 403);
        }

        $circle = $this->normalizeCircle($request->input('circle'));
        $filter = $this->normalizeFilter($request->input('filter'));
        $page = max(1, (int)$request->input('page', 1));

        $context = $this->circles->buildContext($viewerId);
        $allowedCommunities = $this->circles->resolveCommunitiesForCircle($context, $circle);
        $memberCommunities = $this->circles->memberCommunities($context);

        $feed = $this->conversations->listByCircle(
            $viewerId,
            $circle,
            $allowedCommunities,
            $memberCommunities,
            [
                'page' => $page,
                'per_page' => 20,
                'filter' => $filter,
                'viewer_email' => $this->auth->currentUserEmail(),
            ]
        );

        $html = $this->renderConversationCards($feed['conversations']);
        $pagination = $feed['pagination'];

        return $this->success([
            'html' => $html,
            'meta' => [
                'count' => $pagination['total'] ?? count($feed['conversations']),
                'page' => $pagination['page'],
                'has_more' => $pagination['has_more'],
                'circle' => $circle,
                'filter' => $filter,
            ],
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function reply(string $slugOrId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('User not authenticated', 401);
        }

        if (!$this->verifyNonce($nonce, 'vt_conversation_reply', $viewerId)) {
            return $this->error('Security verification failed', 403);
        }

        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        if ($conversation === null || !isset($conversation['id'])) {
            return $this->error('Conversation not found', 404);
        }

        $context = $this->circles->buildContext($viewerId);
        $memberCommunities = $context['inner']['communities'] ?? [];
        if (!$this->conversations->canViewerAccess($conversation, $viewerId, $memberCommunities)) {
            return $this->error('Conversation not found', 404);
        }

        $content = trim((string)$request->input('content', ''));
        if ($content === '') {
            return $this->error('Reply content is required.', 422);
        }

        $viewer = $this->auth->getCurrentUser();
        $replyId = $this->conversations->addReply((int)$conversation['id'], [
            'content' => $content,
            'author_id' => isset($viewer->id) ? (int)$viewer->id : 0,
            'author_name' => isset($viewer->display_name) && $viewer->display_name !== ''
                ? (string)$viewer->display_name
                : ((isset($viewer->username) && $viewer->username !== '') ? (string)$viewer->username : 'Anonymous'),
            'author_email' => isset($viewer->email) ? (string)$viewer->email : '',
        ]);

        $replies = $this->conversations->listReplies((int)$conversation['id']);
        $html = $this->renderReplyCards($replies);

        return $this->success([
            'reply_id' => $replyId,
            'html' => $html,
        ], 201);
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function normalizeCircle($circle): string
    {
        $circle = strtolower((string)$circle);
        return in_array($circle, self::VALID_CIRCLES, true) ? $circle : 'inner';
    }

    private function normalizeFilter($filter): string
    {
        $filter = strtolower((string)$filter);
        return in_array($filter, self::VALID_FILTERS, true) ? $filter : '';
    }

    private function verifyNonce(string $nonce, string $action, int $userId = 0): bool
    {
        if ($nonce === '') {
            return false;
        }

        return $this->security->verifyNonce($nonce, $action, $userId);
    }

    private function renderConversationCards(array $rows): string
    {
        if ($rows === []) {
            return '<div class="vt-text-center vt-p-4"><h3 class="vt-heading vt-heading-sm vt-mb-4">No Conversations Found</h3><p class="vt-text-muted">There are no conversations in this circle.</p></div>';
        }

        $partial = dirname(__DIR__, 3) . '/templates/partials/entity-card.php';

        ob_start();
        foreach ($rows as $row) {
            $entity = (object)$row;
            $entity_type = 'conversation';

            $conversationType = 'General Discussion';
            if (!empty($entity->event_id)) {
                $conversationType = 'Event Discussion';
            } elseif (!empty($entity->community_id)) {
                $conversationType = 'Community Discussion';
            }

            $badges = [
                ['label' => $conversationType, 'class' => 'vt-badge-secondary'],
                ['label' => ucfirst((string)($entity->privacy ?? 'public')), 'class' => (($entity->privacy ?? 'public') === 'private') ? 'vt-badge-secondary' : 'vt-badge-success'],
            ];

            $stats = [
                ['value' => (int)($entity->reply_count ?? 0), 'label' => 'Replies'],
            ];

            $actions = [
                ['label' => 'View', 'url' => '/conversations/' . ($entity->slug ?? '')],
            ];

            $description = $entity->content ?? '';

            if (is_file($partial)) {
                include $partial;
            } else {
                echo '<article class="vt-card">';
                echo '<h3 class="vt-card-title"><a class="vt-link" href="/conversations/' . htmlspecialchars($entity->slug ?? '') . '">' . htmlspecialchars($entity->title ?? '') . '</a></h3>';
                echo '<p class="vt-text-muted">' . htmlspecialchars(substr(strip_tags($description), 0, 160)) . '</p>';
                echo '</article>';
            }
        }

        return (string)ob_get_clean();
    }

    private function renderReplyCards(array $rows): string
    {
        if ($rows === []) {
            return '<p class="vt-text-muted">No replies yet.</p>';
        }

        ob_start();
        echo '<div class="vt-stack">';
        foreach ($rows as $reply) {
            $r = (object)$reply;
            echo '<article class="vt-card" id="reply-' . htmlspecialchars((string)($r->id ?? '')) . '">';
            echo '<div class="vt-card-sub">' . htmlspecialchars($r->author_name ?? 'Unknown');
            if (!empty($r->created_at)) {
                echo ' · ' . htmlspecialchars(date_fmt($r->created_at));
            }
            echo '</div>';
            echo '<p class="vt-card-desc">' . nl2br(htmlspecialchars($r->content ?? '')) . '</p>';
            echo '</article>';
        }
        echo '</div>';

        return (string)ob_get_clean();
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => true,
                'data' => $data,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }
}
