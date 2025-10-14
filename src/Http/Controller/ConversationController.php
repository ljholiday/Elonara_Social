<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Services\AuthService;
use App\Services\CircleService;
use App\Services\ConversationService;
use App\Services\AuthorizationService;
use App\Services\ValidatorService;
use App\Services\SecurityService;

final class ConversationController
{
    private const VALID_CIRCLES = ['all', 'inner', 'trusted', 'extended'];

    public function __construct(
        private ConversationService $conversations,
        private CircleService $circles,
        private AuthService $auth,
        private AuthorizationService $authz,
        private ValidatorService $validator,
        private SecurityService $security
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

        $viewerId = $this->auth->currentUserId() ?? 0;
        $viewerEmail = $this->auth->currentUserEmail();
        $context = $this->circles->buildContext($viewerId);
        $allowedCommunities = $this->circles->resolveCommunitiesForCircle($context, $circle);
        $memberCommunities = $this->circles->memberCommunities($context);

        $options = [
            'page' => max(1, (int)$request->query('page', 1)),
            'per_page' => 20,
            'filter' => $filter,
            'viewer_email' => $viewerEmail,
        ];

        $feed = $this->conversations->listByCircle(
            $viewerId,
            $circle,
            $allowedCommunities,
            $memberCommunities,
            $options
        );

        return [
            'conversations' => $feed['conversations'],
            'circle' => $circle,
            'circle_context' => $context,
            'filter' => $filter,
            'pagination' => $feed['pagination'],
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
        $viewerId = $this->auth->currentUserId() ?? 0;
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

        $replies = $this->conversations->listReplies((int)$conversation['id']);

        return [
            'conversation' => $conversation,
            'replies' => $replies,
            'reply_errors' => [],
            'reply_input' => ['content' => ''],
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
        return [
            'errors' => [],
            'input' => [
                'title' => '',
                'content' => '',
            ],
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
            ];
        }

        $slug = $this->conversations->create([
            'title' => $input['title'],
            'content' => $input['content'],
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
        $viewerId = $this->auth->currentUserId() ?? 0;
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
        $viewerId = $this->auth->currentUserId() ?? 0;
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
        $viewerId = $this->auth->currentUserId() ?? 0;
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
                'replies' => $this->conversations->listReplies((int)$conversation['id']),
                'reply_errors' => ['auth' => 'You cannot reply to this conversation.'],
                'reply_input' => ['content' => ''],
            ];
        }

        $request = $this->request();
        $nonce = (string)$request->input('reply_nonce', '');
        if (!$this->verifyNonce($nonce, 'vt_conversation_reply')) {
            return [
                'conversation' => $conversation,
                'replies' => $this->conversations->listReplies((int)$conversation['id']),
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
                'replies' => $this->conversations->listReplies((int)$conversation['id']),
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
        $viewerId = $this->auth->currentUserId() ?? 0;
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

    private function request(): \App\Http\Request
    {
        /** @var \App\Http\Request $request */
        $request = vt_service('http.request');
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

        $userId = $this->auth->currentUserId() ?? 0;
        return $this->security->verifyNonce($nonce, $action, $userId);
    }
}
