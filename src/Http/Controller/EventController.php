<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\EventService;
use App\Services\AuthService;
use App\Services\ValidatorService;
use App\Services\InvitationService;
use App\Services\ConversationService;
use App\Services\AuthorizationService;
use App\Services\CommunityService;
use App\Support\ContextBuilder;
use App\Support\ContextLabel;

/**
 * Thin HTTP controller for event listings and detail views.
 * Controllers return view data arrays that templates consume directly.
 */
final class EventController
{
    private const VALID_FILTERS = ['all', 'my'];

    public function __construct(
        private EventService $events,
        private AuthService $auth,
        private ValidatorService $validator,
        private InvitationService $invitations,
        private ConversationService $conversations,
        private AuthorizationService $authz,
        private CommunityService $communities
    ) {
    }

    /**
     * @return array{events: array<int, array<string, mixed>>, filter: string}
     */
    public function index(): array
    {
        $request = $this->request();
        $filter = $this->normalizeFilter($request->query('filter'));
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        $viewerEmail = $this->auth->currentUserEmail();

        if ($filter === 'my') {
            $events = $viewerId > 0 ? $this->events->listMine($viewerId, $viewerEmail) : [];
        } else {
            $events = $this->events->listRecent();
        }

        $events = array_map(function (array $event): array {
            $path = ContextBuilder::event($event, $this->communities);
            $plain = ContextLabel::renderPlain($path);
            $html = ContextLabel::render($path);
            $event['context_path'] = $path;
            $event['context_label'] = $plain !== '' ? $plain : (string)($event['title'] ?? '');
            $event['context_label_html'] = $html !== '' ? $html : htmlspecialchars((string)($event['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            return $event;
        }, $events);

        return [
            'events' => $events,
            'filter' => $filter,
        ];
    }

    /**
     * @return array{event: array<string, mixed>|null}
     */
    public function show(string $slugOrId): array
    {
        $event = $this->events->getBySlugOrId($slugOrId);
        $contextPath = $event !== null ? ContextBuilder::event($event, $this->communities) : [];

        return [
            'event' => $event,
            'context_path' => $contextPath,
            'context_label' => $contextPath !== [] ? ContextLabel::renderPlain($contextPath) : '',
            'context_label_html' => $contextPath !== [] ? ContextLabel::render($contextPath) : '',
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
                'errors' => ['auth' => 'You must be logged in to create an event.'],
                'input' => [
                    'title' => '',
                    'description' => '',
                    'event_date' => '',
                ],
                'context' => ['allowed' => false],
            ];
        }

        $context = $this->resolveCommunityContext($this->request(), $viewerId);
        $errors = [];
        if (!empty($context['error'])) {
            $errors['context'] = $context['error'];
        }

        return [
            'errors' => $errors,
            'input' => [
                'title' => '',
                'description' => '',
                'event_date' => '',
            ],
            'context' => $context,
        ];
    }

    /**
     * @return array{
     *   errors?: array<string,string>,
     *   input?: array<string,string>,
     *   event_date_db?: ?string,
     *   redirect?: string
     * }
     */
    public function store(): array
    {
        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return [
                'errors' => ['auth' => 'You must be logged in to create an event.'],
                'input' => ['title' => '', 'description' => '', 'event_date' => ''],
                'context' => ['allowed' => false],
            ];
        }

        $request = $this->request();
        $validated = $this->validateEventInput($request);

        if ($validated['errors']) {
            return [
                'errors' => $validated['errors'],
                'input' => $validated['input'],
                'context' => $this->resolveCommunityContext($request, $viewerId),
            ];
        }

        $context = $this->resolveCommunityContext($request, $viewerId);
        if (!empty($context['error'])) {
            return [
                'errors' => ['context' => $context['error']],
                'input' => $validated['input'],
                'context' => $context,
            ];
        }

        $slug = $this->events->create([
            'title' => $validated['input']['title'],
            'description' => $validated['input']['description'],
            'event_date' => $validated['event_date_db'],
            'author_id' => $viewerId,
            'created_by' => $viewerId,
            'community_id' => $context['community_id'] ?? 0,
            'privacy' => $context['privacy'] ?? 'public',
        ]);

        return [
            'redirect' => '/events/' . $slug,
        ];
    }

    /**
     * @return array{
     *   event: array<string,mixed>|null,
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function edit(string $slugOrId): array
    {
        $event = $this->events->getBySlugOrId($slugOrId);
        if ($event === null) {
            return [
                'event' => null,
                'errors' => [],
                'input' => [],
            ];
        }

        return [
            'event' => $event,
            'errors' => [],
            'input' => [
                'title' => $event['title'] ?? '',
                'description' => $event['description'] ?? '',
                'event_date' => $this->formatForInput($event['event_date'] ?? null),
            ],
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   event?: array<string,mixed>|null,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function update(string $slugOrId): array
    {
        $event = $this->events->getBySlugOrId($slugOrId);
        if ($event === null) {
            return [
                'event' => null,
            ];
        }

        $validated = $this->validateEventInput($this->request());

        if ($validated['errors']) {
            return [
                'event' => $event,
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $this->events->update($event['slug'], [
            'title' => $validated['input']['title'],
            'description' => $validated['input']['description'],
            'event_date' => $validated['event_date_db'],
        ]);

        return [
            'redirect' => '/events/' . $event['slug'],
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
        // Events currently do not support replies; redirect to detail.
        return [
            'redirect' => '/events/' . $slugOrId,
            'conversation' => null,
            'replies' => [],
            'reply_errors' => [],
            'reply_input' => ['content' => ''],
        ];
    }

    /**
     * @return array{redirect: string}
     */
    public function destroy(string $slugOrId): array
    {
        $this->events->delete($slugOrId);
        return [
            'redirect' => '/events',
        ];
    }

    /**
     * @return array{
     *   event: array<string,mixed>|null,
     *   conversations: array<int,array<string,mixed>>
     * }
     */
    public function conversations(string $slugOrId): array
    {
        $event = $this->events->getBySlugOrId($slugOrId);
        if ($event === null) {
            return [
                'event' => null,
                'conversations' => [],
                'canCreateConversation' => false,
            ];
        }

        $eventId = (int)($event['id'] ?? 0);
        $conversations = $eventId > 0 ? $this->conversations->listByEvent($eventId) : [];
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        $canCreate = $this->authz->canCreateConversationInEvent($event, $viewerId);

        $conversations = array_map(function (array $conversation): array {
            $path = ContextBuilder::conversation($conversation, $this->communities, $this->events);
            $plain = ContextLabel::renderPlain($path);
            $html = ContextLabel::render($path);
            $conversation['context_path'] = $path;
            $conversation['context_label'] = $plain !== '' ? $plain : (string)($conversation['title'] ?? '');
            $conversation['context_label_html'] = $html !== '' ? $html : htmlspecialchars((string)($conversation['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            return $conversation;
        }, $conversations);

        return [
            'event' => $event,
            'conversations' => $conversations,
            'canCreateConversation' => $canCreate,
        ];
    }

    /**
     * @return array{
     *   status:int,
     *   event?: array<string,mixed>|null,
     *   tab?: string,
     *   guest_summary?: array<string,int>
     * }
     */
    public function manage(string $slugOrId): array
    {
        $event = $this->events->getBySlugOrId($slugOrId);
        if ($event === null) {
            return [
                'status' => 404,
                'event' => null,
            ];
        }

        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        if (!$this->canManageEvent($event, $viewerId)) {
            return [
                'status' => 403,
                'event' => null,
            ];
        }

        $tab = $this->normalizeManageTab($this->request()->query('tab'));

        $guestSummary = [
            'total' => 0,
            'confirmed' => 0,
        ];

        $eventId = (int)($event['id'] ?? 0);
        if ($eventId > 0) {
            $guests = $this->invitations->getEventGuests($eventId);
            $guestSummary['total'] = count($guests);
            $guestSummary['confirmed'] = count(array_filter(
                $guests,
                static function (array $guest): bool {
                    $status = strtolower((string)($guest['status'] ?? ''));
                    return in_array($status, ['confirmed', 'yes'], true);
                }
            ));
        }

        return [
            'status' => 200,
            'event' => $event,
            'tab' => $tab,
            'guest_summary' => $guestSummary,
        ];
    }

    /**
     * @return array{community?:array<string,mixed>|null,community_id?:int|null,community_slug?:string|null,label:string,allowed:bool,error?:string|null,privacy:string}
     */
    private function resolveCommunityContext(Request $request, int $viewerId): array
    {
        $context = [
            'community' => null,
            'community_id' => null,
            'community_slug' => null,
            'label' => '',
            'allowed' => true,
            'error' => null,
            'privacy' => 'public',
        ];

        $communityId = (int)$request->input('community_id', 0);
        $communityParam = (string)$request->input('community', $request->query('community', ''));

        if ($communityId > 0 || $communityParam !== '') {
            $community = $communityId > 0
                ? $this->communities->getBySlugOrId((string)$communityId)
                : $this->communities->getBySlugOrId($communityParam);

            if ($community === null) {
                $context['allowed'] = false;
                $context['error'] = 'Community not found.';
                return $context;
            }

            $context['community'] = $community;
            $context['community_id'] = (int)($community['id'] ?? 0);
            $context['community_slug'] = $community['slug'] ?? null;
            $context['label'] = (string)($community['name'] ?? $community['title'] ?? 'Community');
            $context['privacy'] = (string)($community['privacy'] ?? 'public');
            $context['allowed'] = $this->authz->canCreateEventInCommunity((int)$community['id'], $viewerId);
            if (!$context['allowed']) {
                $context['error'] = 'You do not have permission to create an event in this community.';
            }
        }
        return $context;
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = app_service('http.request');
        return $request;
    }

    private function normalizeFilter(?string $filter): string
    {
        $filter = strtolower((string) $filter);
        return in_array($filter, self::VALID_FILTERS, true) ? $filter : 'all';
    }

    private function formatForInput(?string $dbDate): string
    {
        if (!$dbDate) {
            return '';
        }
        $timestamp = strtotime($dbDate);
        return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
    }

    private function validateEventInput(Request $request): array
    {
        $titleValidation = $this->validator->required($request->input('title', ''));
        $descriptionValidation = $this->validator->textField($request->input('description', ''));
        $eventDateRaw = trim((string)$request->input('event_date', ''));

        $errors = [];
        $input = [
            'title' => $titleValidation['value'],
            'description' => $descriptionValidation['value'],
            'event_date' => $eventDateRaw,
        ];

        if (!$titleValidation['is_valid']) {
            $errors['title'] = $titleValidation['errors'][0] ?? 'Title is required.';
        }

        $eventDateDb = null;
        if ($eventDateRaw !== '') {
            $timestamp = strtotime($eventDateRaw);
            if ($timestamp === false) {
                $errors['event_date'] = 'Provide a valid date/time.';
            } else {
                $eventDateDb = date('Y-m-d H:i:s', $timestamp);
            }
        }

        return [
            'input' => $input,
            'errors' => $errors,
            'event_date_db' => $eventDateDb,
        ];
    }

    private function normalizeManageTab(?string $tab): string
    {
        $tab = strtolower((string)$tab);
        return in_array($tab, ['settings', 'guests', 'invites'], true) ? $tab : 'settings';
    }

    /**
     * @param array<string,mixed> $event
     */
    private function canManageEvent(array $event, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        if ((int)($event['author_id'] ?? 0) === $viewerId) {
            return true;
        }

        return $this->auth->currentUserCan('edit_others_posts');
    }
}
