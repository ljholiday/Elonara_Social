<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\EventService;
use App\Services\AuthService;
use App\Services\ValidatorService;
use App\Services\InvitationService;
use App\Services\ConversationService;

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
        private ConversationService $conversations
    ) {
    }

    /**
     * @return array{events: array<int, array<string, mixed>>, filter: string}
     */
    public function index(): array
    {
        $request = $this->request();
        $filter = $this->normalizeFilter($request->query('filter'));
        $viewerId = $this->auth->currentUserId() ?? 0;
        $viewerEmail = $this->auth->currentUserEmail();

        if ($filter === 'my') {
            $events = $viewerId > 0 ? $this->events->listMine($viewerId, $viewerEmail) : [];
        } else {
            $events = $this->events->listRecent();
        }

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
        return [
            'event' => $this->events->getBySlugOrId($slugOrId),
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
                'description' => '',
                'event_date' => '',
            ],
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
        $validated = $this->validateEventInput($this->request());

        if ($validated['errors']) {
            return [
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $slug = $this->events->create([
            'title' => $validated['input']['title'],
            'description' => $validated['input']['description'],
            'event_date' => $validated['event_date_db'],
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
            ];
        }

        $eventId = (int)($event['id'] ?? 0);
        $conversations = $eventId > 0 ? $this->conversations->listByEvent($eventId) : [];

        return [
            'event' => $event,
            'conversations' => $conversations,
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

        $viewerId = $this->auth->currentUserId() ?? 0;
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

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
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
