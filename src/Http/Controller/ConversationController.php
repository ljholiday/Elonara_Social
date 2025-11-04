<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\CircleService;
use App\Services\ConversationService;
use App\Services\AuthorizationService;
use App\Services\ValidatorService;
use App\Services\SecurityService;
use App\Services\CommunityService;
use App\Services\EventService;
use App\Support\ContextBuilder;
use App\Support\ContextLabel;

final class ConversationController
{
    private const VALID_CIRCLES = ['all', 'inner', 'trusted', 'extended'];

    public function __construct(
        private ConversationService $conversations,
        private CircleService $circles,
        private AuthService $auth,
        private AuthorizationService $authz,
        private ValidatorService $validator,
        private SecurityService $security,
        private CommunityService $communities,
        private EventService $events,
        private \App\Services\FeedService $feed
    ) {
    }

    /**
     * @return array{
     *   conversations: array<int, array<string, mixed>>,
     *   circle: string,
     *   circle_context: array<string, array{communities: array<int>, creators: array<int>}>,
     *   pagination: array{page:int, per_page:int, has_more:bool, next_page:int|null}
     * }
     */
    public function index(): array
    {
        $request = $this->request();
        $circle = $this->normalizeCircle($request->query('circle'));
        $filter = $this->normalizeFilter($request->query('filter'));

        $viewerId = (int)($this->auth->currentUserId() ?? 0);

        $options = [
            'page' => max(1, (int)$request->query('page', 1)),
            'per_page' => 20,
            'filter' => $filter,
        ];

        $feedData = $this->feed->getGlobalFeed($viewerId, $circle, $options);

        $conversations = array_map(function (array $conversation): array {
            $path = ContextBuilder::conversation($conversation, $this->communities, $this->events);
            $plain = ContextLabel::renderPlain($path);
            $html = ContextLabel::render($path);

            $conversation['context_path'] = $path;
            $conversation['context_label'] = $plain !== '' ? $plain : (string)($conversation['title'] ?? '');
            $conversation['context_label_html'] = $html !== '' ? $html : htmlspecialchars((string)($conversation['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            return $conversation;
        }, $feedData['conversations']);

        $context = $this->circles->buildContext($viewerId);

        return [
            'conversations' => $conversations,
            'circle' => $feedData['circle'],
            'circle_context' => $context,
            'filter' => $filter,
            'pagination' => $feedData['pagination'],
        ];
    }

    /**
     * @return array{
     *   conversation: array<string, mixed>|null,
     *   replies: array<int, array<string, mixed>>,
     *   reply_errors: array<string,string>,
     *   reply_input: array<string,string>
     * }
     */
    public function show(string $slugOrId): array
    {
        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        $context = $this->circles->buildContext($viewerId);
        $memberCommunities = $context['inner']['communities'] ?? [];

        if ($conversation === null || !$this->conversations->canViewerAccess($conversation, $viewerId, $memberCommunities)) {
            return [
                'conversation' => null,
                'replies' => [],
                'reply_errors' => [],
                'reply_input' => ['content' => ''],
            ];
        }

        $replies = $this->conversations->listReplies((int)$conversation['id'], $viewerId);
        $contextPath = $this->buildConversationContextPath($conversation);
        $contextLabelPlain = $contextPath !== [] ? ContextLabel::renderPlain($contextPath) : (string)($conversation['title'] ?? '');
        $contextLabelHtml = $contextPath !== [] ? ContextLabel::render($contextPath) : htmlspecialchars((string)($conversation['title'] ?? ''), ENT_QUOTES, 'UTF-8');

        return [
            'conversation' => $conversation,
            'replies' => $replies,
            'reply_errors' => [],
            'reply_input' => ['content' => ''],
            'context_path' => $contextPath,
            'context_label' => $contextLabelPlain,
            'context_label_html' => $contextLabelHtml,
        ];
    }

    /**
     * @return array{
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function create(): array
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        if ($viewerId <= 0) {
            return [
                'errors' => ['auth' => 'You must be logged in to start a conversation.'],
                'input' => ['title' => '', 'content' => ''],
                'context' => ['allowed' => false],
            ];
        }

        $context = $this->resolveConversationContext($this->request(), $viewerId);
        $errors = [];
        if (!empty($context['error'])) {
            $errors['context'] = $context['error'];
        }

        return [
            'errors' => $errors,
            'input' => [
                'title' => '',
                'content' => '',
            ],
            'context' => $context,
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function store(): array
    {
        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return [
                'errors' => ['auth' => 'You must be logged in to create a conversation.'],
                'input' => [],
            ];
        }

        $request = $this->request();

        $titleValidation = $this->validator->required($request->input('title', ''));
        $contentValidation = $this->validator->required($request->input('content', ''));

        $errors = [];
        $input = [
            'title' => $titleValidation['value'],
            'content' => $contentValidation['value'],
        ];

        if (!$titleValidation['is_valid']) {
            $errors['title'] = $titleValidation['errors'][0] ?? 'Title is required.';
        }
        if (!$contentValidation['is_valid']) {
            $errors['content'] = $contentValidation['errors'][0] ?? 'Content is required.';
        }

        if ($errors) {
            return [
                'errors' => $errors,
                'input' => $input,
                'context' => $this->resolveConversationContext($request, $viewerId),
            ];
        }

        $viewer = $this->auth->getCurrentUser();
        $authorId = $viewer?->id ? (int)$viewer->id : 0;
        $authorName = $viewer?->display_name ?? $viewer?->username ?? $viewer?->email ?? 'Member';
        $authorName = is_string($authorName) && $authorName !== '' ? $authorName : 'Member';
        $authorEmail = $viewer?->email ?? '';

        $context = $this->resolveConversationContext($request, $viewerId);
        if (empty($context['allowed'])) {
            $errors['context'] = $context['error'] ?? 'Unable to determine where to start this conversation.';
            return [
                'errors' => $errors,
                'input' => $input,
                'context' => $context,
            ];
        }

        $slug = $this->conversations->create([
            'title' => $input['title'],
            'content' => $input['content'],
            'author_id' => $authorId,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'community_id' => $context['community_id'] ?? null,
            'event_id' => $context['event_id'] ?? null,
            'privacy' => $context['privacy'] ?? 'public',
        ]);

        return [
            'redirect' => '/conversations/' . $slug,
        ];
    }

    /**
     * @return array{
     *   conversation: array<string,mixed>|null,
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function edit(string $slugOrId): array
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        if ($conversation === null) {
            return [
                'conversation' => null,
                'errors' => [],
                'input' => [],
            ];
        }

        if (!$this->authz->canEditConversation($conversation, $viewerId)) {
            return [
                'conversation' => null,
                'errors' => ['auth' => 'You do not have permission to edit this conversation.'],
                'input' => [],
            ];
        }

        return [
            'conversation' => $conversation,
            'errors' => [],
            'input' => [
                'title' => $conversation['title'] ?? '',
                'content' => $conversation['content'] ?? '',
            ],
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   conversation?: array<string,mixed>|null,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function update(string $slugOrId): array
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        if ($conversation === null) {
            return [
                'conversation' => null,
            ];
        }

        if (!$this->authz->canEditConversation($conversation, $viewerId)) {
            return [
                'conversation' => null,
                'errors' => ['auth' => 'You do not have permission to edit this conversation.'],
            ];
        }

        $request = $this->request();

        $titleValidation = $this->validator->required($request->input('title', ''));
        $contentValidation = $this->validator->required($request->input('content', ''));

        $errors = [];
        $input = [
            'title' => $titleValidation['value'],
            'content' => $contentValidation['value'],
        ];

        if (!$titleValidation['is_valid']) {
            $errors['title'] = $titleValidation['errors'][0] ?? 'Title is required.';
        }
        if (!$contentValidation['is_valid']) {
            $errors['content'] = $contentValidation['errors'][0] ?? 'Content is required.';
        }

        if ($errors) {
            return [
                'conversation' => $conversation,
                'errors' => $errors,
                'input' => $input,
            ];
        }

        $this->conversations->update($conversation['slug'], [
            'title' => $input['title'],
            'content' => $input['content'],
        ]);

        return [
            'redirect' => '/conversations/' . $conversation['slug'],
        ];
    }

    /**
     * @return array{
     *   conversation: array<string,mixed>|null,
     *   replies: array<int,array<string,mixed>>,
     *   reply_errors: array<string,string>,
     *   reply_input: array<string,string>,
     *   redirect?: string
     * }
     */
    public function reply(string $slugOrId): array
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        if ($viewerId <= 0) {
            return [
                'conversation' => null,
                'replies' => [],
                'reply_errors' => ['auth' => 'You must be logged in to reply.'],
                'reply_input' => ['content' => ''],
            ];
        }

        $conversation = $this->conversations->getBySlugOrId($slugOrId);
        if ($conversation === null || !isset($conversation['id'])) {
            return [
                'conversation' => null,
                'replies' => [],
                'reply_errors' => ['conversation' => 'Conversation not found.'],
                'reply_input' => ['content' => ''],
            ];
        }

        $context = $this->circles->buildContext($viewerId);
        $memberCommunities = $context['inner']['communities'] ?? [];
        if (!$this->conversations->canViewerAccess($conversation, $viewerId, $memberCommunities)) {
            return [
                'conversation' => null,
                'replies' => [],
                'reply_errors' => ['conversation' => 'Conversation not found.'],
                'reply_input' => ['content' => ''],
            ];
        }

        if (!$this->authz->canReplyToConversation($conversation, $viewerId, $memberCommunities)) {
            return [
                'conversation' => $conversation,
                'replies' => $this->conversations->listReplies((int)$conversation['id'], $viewerId),
                'reply_errors' => ['auth' => 'You cannot reply to this conversation.'],
                'reply_input' => ['content' => ''],
            ];
        }

        $request = $this->request();
        $nonce = (string)$request->input('reply_nonce', '');
        if (!$this->verifyNonce($nonce, 'app_conversation_reply')) {
            return [
                'conversation' => $conversation,
                'replies' => $this->conversations->listReplies((int)$conversation['id'], $viewerId),
                'reply_errors' => ['nonce' => 'Security verification failed. Please refresh and try again.'],
                'reply_input' => ['content' => (string)$request->input('content', '')],
            ];
        }

        $contentValidation = $this->validator->required($request->input('content', ''));

        $errors = [];
        $input = [
            'content' => $contentValidation['value'],
            'image_alt' => (string)$request->input('image_alt', ''),
        ];

        if (!$contentValidation['is_valid']) {
            $errors['content'] = $contentValidation['errors'][0] ?? 'Reply content is required.';
        }

        // Check for image upload
        $hasImage = !empty($_FILES['reply_image']) && !empty($_FILES['reply_image']['tmp_name']);
        if ($hasImage) {
            $imageAlt = trim($input['image_alt']);
            if ($imageAlt === '') {
                $errors['image_alt'] = 'Image alt-text is required for accessibility.';
            }
        }

        if ($errors) {
            return [
                'conversation' => $conversation,
                'replies' => $this->conversations->listReplies((int)$conversation['id'], $viewerId),
                'reply_errors' => $errors,
                'reply_input' => $input,
            ];
        }

        $viewer = $this->auth->getCurrentUser();
        $replyData = [
            'content' => $input['content'],
            'author_id' => isset($viewer->id) ? (int)$viewer->id : 0,
            'author_name' => isset($viewer->display_name) && $viewer->display_name !== ''
                ? (string)$viewer->display_name
                : ((isset($viewer->username) && $viewer->username !== '') ? (string)$viewer->username : 'Anonymous'),
            'author_email' => isset($viewer->email) ? (string)$viewer->email : '',
        ];

        if ($hasImage) {
            $replyData['image'] = $_FILES['reply_image'];
            $replyData['image_alt'] = $input['image_alt'];
        }

        $this->conversations->addReply((int)$conversation['id'], $replyData);

        $redirect = '/conversations/' . $conversation['slug'];
        $circleParam = $request->query('circle');
        if (is_string($circleParam) && $circleParam !== '') {
            $redirect .= '?circle=' . urlencode($circleParam);
        }

        return [
            'redirect' => $redirect,
            'conversation' => $conversation,
            'replies' => [],
            'reply_errors' => [],
            'reply_input' => ['content' => ''],
        ];
    }

    /**
     * @return array{redirect?: string, error?: string}
     */
    public function destroy(string $slugOrId): array
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        $conversation = $this->conversations->getBySlugOrId($slugOrId);

        if ($conversation === null) {
            return [
                'redirect' => '/conversations',
            ];
        }

        if (!$this->authz->canDeleteConversation($conversation, $viewerId)) {
            return [
                'error' => 'You do not have permission to delete this conversation.',
                'redirect' => '/conversations/' . $conversation['slug'],
            ];
        }

        $this->conversations->delete($slugOrId);
        return [
            'redirect' => '/conversations',
        ];
    }

    /**
     * @return array{type:?string,community?:array<string,mixed>|null,event?:array<string,mixed>|null,community_id?:int|null,event_id?:int|null,community_slug?:string|null,event_slug?:string|null,label:string,label_parts:array<int,string>,allowed:bool,error?:string|null,privacy:string}
     */
    private function resolveConversationContext(Request $request, int $viewerId): array
    {
        $context = [
            'type' => null,
            'community' => null,
            'event' => null,
            'community_id' => null,
            'community_slug' => null,
            'event_id' => null,
            'event_slug' => null,
            'label' => '',
            'label_html' => '',
            'label_parts' => [],
            'label_path' => [],
            'allowed' => false,
            'error' => null,
            'privacy' => 'public',
        ];

        $eventId = (int)$request->input('event_id', 0);
        $eventParam = (string)$request->input('event', $request->query('event', ''));
        $communityId = (int)$request->input('community_id', 0);
        $communityParam = (string)$request->input('community', $request->query('community', ''));

        if ($eventId > 0 || $eventParam !== '') {
            $event = $eventId > 0
                ? $this->events->getBySlugOrId((string)$eventId)
                : $this->events->getBySlugOrId($eventParam);

            if ($event === null) {
                $context['error'] = 'Event not found.';
                return $context;
            }

            $context['type'] = 'event';
            $context['event'] = $event;
            $context['event_id'] = (int)($event['id'] ?? 0);
            $context['event_slug'] = $event['slug'] ?? null;
            $context['privacy'] = (string)($event['privacy'] ?? 'public');

            $communityIdFromEvent = (int)($event['community_id'] ?? 0);
            if ($communityIdFromEvent > 0) {
                $community = $this->communities->getBySlugOrId((string)$communityIdFromEvent);
                if ($community !== null) {
                    $context['community'] = $community;
                    $context['community_id'] = (int)($community['id'] ?? 0);
                    $context['community_slug'] = $community['slug'] ?? null;
                    $communityLabel = (string)($community['name'] ?? $community['title'] ?? 'Community');
                    $communitySlug = $community['slug'] ?? null;
                    $context['label_parts'][] = $communityLabel;
                    $context['label_path'][] = [
                        'label' => $communityLabel,
                        'url' => $communitySlug ? '/communities/' . $communitySlug : null,
                    ];
                    $context['privacy'] = (string)($community['privacy'] ?? $context['privacy']);
                }
            }

            $eventLabel = (string)($event['title'] ?? 'Event');
            $eventSlug = $event['slug'] ?? null;
            $context['label_parts'][] = $eventLabel;
            $context['label_path'][] = [
                'label' => $eventLabel,
                'url' => $eventSlug ? '/events/' . $eventSlug : null,
            ];
            if ($context['label_path'] !== []) {
                $context['label'] = ContextLabel::renderPlain($context['label_path']);
                $context['label_html'] = ContextLabel::render($context['label_path']);
            }
            $context['allowed'] = $this->authz->canCreateConversationInEvent($event, $viewerId);
            if (!$context['allowed']) {
                $context['error'] = 'You do not have permission to start a conversation for this event.';
            }

            return $context;
        }

        if ($communityId > 0 || $communityParam !== '') {
            $community = $communityId > 0
                ? $this->communities->getBySlugOrId((string)$communityId)
                : $this->communities->getBySlugOrId($communityParam);

            if ($community === null) {
                $context['error'] = 'Community not found.';
                return $context;
            }

            $context['type'] = 'community';
            $context['community'] = $community;
            $context['community_id'] = (int)($community['id'] ?? 0);
            $context['community_slug'] = $community['slug'] ?? null;
            $communityLabel = (string)($community['name'] ?? $community['title'] ?? 'Community');
            $communitySlug = $community['slug'] ?? null;
            $context['label_parts'][] = $communityLabel;
            $context['label_path'][] = [
                'label' => $communityLabel,
                'url' => $communitySlug ? '/communities/' . $communitySlug : null,
            ];
            if ($context['label_path'] !== []) {
                $context['label'] = ContextLabel::renderPlain($context['label_path']);
                $context['label_html'] = ContextLabel::render($context['label_path']);
            }
            $context['privacy'] = (string)($community['privacy'] ?? 'public');
            $context['allowed'] = $this->authz->canCreateConversationInCommunity((int)$community['id'], $viewerId);
            if (!$context['allowed']) {
                $context['error'] = 'You do not have permission to start a conversation in this community.';
            }

            return $context;
        }

        $context['error'] = 'Choose a community or event before starting a conversation.';
        return $context;
    }

    /**
     * @param array<string,mixed> $conversation
     * @return array<int,array{label:string,url:?string}>
     */
    private function buildConversationContextPath(array $conversation): array
    {
        return ContextBuilder::conversation($conversation, $this->communities, $this->events);
    }

    private function request(): \App\Http\Request
    {
        /** @var \App\Http\Request $request */
        $request = app_service('http.request');
        return $request;
    }

    private function normalizeCircle(?string $circle): string
    {
        $circle = strtolower((string)$circle);
        return in_array($circle, self::VALID_CIRCLES, true) ? $circle : 'all';
    }

    private function normalizeFilter($filter): string
    {
        $filter = strtolower((string)$filter);
        $allowed = ['my-events', 'all-events', 'communities', ''];
        return in_array($filter, $allowed, true) ? $filter : '';
    }

    private function verifyNonce(string $nonce, string $action): bool
    {
        if ($nonce === '') {
            return false;
        }

        $userId = (int)($this->auth->currentUserId() ?? 0);
        return $this->security->verifyNonce($nonce, $action, $userId);
    }
}
