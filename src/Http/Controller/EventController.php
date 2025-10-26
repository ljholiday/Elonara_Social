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
use App\Services\ImageService;
use App\Support\ContextBuilder;
use App\Support\ContextLabel;
use App\Support\RecurrenceFormatter;

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
        private CommunityService $communities,
        private ImageService $images
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
            'recurrence_summary' => $event !== null ? RecurrenceFormatter::describe($event) : '',
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
                    'end_date' => '',
                    'location' => '',
                    'recurrence_type' => 'none',
                    'recurrence_interval' => '1',
                    'recurrence_days' => [],
                    'monthly_type' => 'date',
                    'monthly_day_number' => '',
                    'monthly_week' => '',
                    'monthly_weekday' => '',
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
                'end_date' => '',
                'location' => '',
                'recurrence_type' => 'none',
                'recurrence_interval' => '1',
                'recurrence_days' => [],
                'monthly_type' => 'date',
                'monthly_day_number' => '',
                'monthly_week' => '',
                'monthly_weekday' => '',
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
                'input' => [
                    'title' => '',
                    'description' => '',
                    'event_date' => '',
                    'end_date' => '',
                    'location' => '',
                    'recurrence_type' => 'none',
                    'recurrence_interval' => '1',
                    'recurrence_days' => [],
                    'monthly_type' => 'date',
                    'monthly_day_number' => '',
                    'monthly_week' => '',
                    'monthly_weekday' => '',
                ],
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

        // Create event first to get the event ID
        $slug = $this->events->create([
            'title' => $validated['input']['title'],
            'description' => $validated['input']['description'],
            'event_date' => $validated['event_date_db'],
            'end_date' => $validated['end_date_db'],
            'location' => $validated['input']['location'],
            'recurrence_type' => $validated['recurrence']['recurrence_type'],
            'recurrence_interval' => $validated['recurrence']['recurrence_interval'],
            'recurrence_days' => $validated['recurrence']['recurrence_days'],
            'monthly_type' => $validated['recurrence']['monthly_type'],
            'monthly_week' => $validated['recurrence']['monthly_week'],
            'monthly_day' => $validated['recurrence']['monthly_day'],
            'author_id' => $viewerId,
            'created_by' => $viewerId,
            'community_id' => $context['community_id'] ?? 0,
            'privacy' => $context['privacy'] ?? 'public',
        ]);

        // Handle featured image from modal upload or traditional file upload
        $featuredImageUrlUploaded = (string)$request->input('featured_image_url_uploaded', '');
        $imageAlt = trim((string)$request->input('featured_image_alt', ''));

        if ($featuredImageUrlUploaded !== '' && $imageAlt !== '') {
            // Use pre-uploaded image from modal
            $this->events->update($slug, [
                'title' => $validated['input']['title'],
                'description' => $validated['input']['description'],
                'event_date' => $validated['event_date_db'],
                'end_date' => $validated['end_date_db'],
                'location' => $validated['input']['location'],
                'recurrence_type' => $validated['recurrence']['recurrence_type'],
                'recurrence_interval' => $validated['recurrence']['recurrence_interval'],
                'recurrence_days' => $validated['recurrence']['recurrence_days'],
                'monthly_type' => $validated['recurrence']['monthly_type'],
                'monthly_week' => $validated['recurrence']['monthly_week'],
                'monthly_day' => $validated['recurrence']['monthly_day'],
                'featured_image' => $featuredImageUrlUploaded,
                'featured_image_alt' => $imageAlt,
            ]);
        } elseif (!empty($_FILES['featured_image']) && !empty($_FILES['featured_image']['tmp_name'])) {
            // Traditional file upload
            $event = $this->events->getBySlugOrId($slug);
            if ($event !== null) {
                $eventId = (int)$event['id'];
                $imageValidation = $this->validateImageUpload(
                    $_FILES['featured_image'],
                    $imageAlt,
                    $eventId,
                    $context['community_id'] ?? null
                );

                if (empty($imageValidation['error'])) {
                    // Update event with image
                    $this->events->update($slug, [
                        'title' => $validated['input']['title'],
                        'description' => $validated['input']['description'],
                        'event_date' => $validated['event_date_db'],
                        'end_date' => $validated['end_date_db'],
                        'location' => $validated['input']['location'],
                        'featured_image' => $imageValidation['urls'],
                        'featured_image_alt' => $imageAlt,
                    ]);
                }
            }
        }

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

        $allowedRecurrenceTypes = ['none', 'daily', 'weekly', 'monthly'];
        $recurrenceType = strtolower((string)($event['recurrence_type'] ?? 'none'));
        if (!in_array($recurrenceType, $allowedRecurrenceTypes, true)) {
            $recurrenceType = 'none';
        }

        $recurrenceIntervalRaw = (string)($event['recurrence_interval'] ?? '1');
        $recurrenceInterval = (string)((
            filter_var($recurrenceIntervalRaw, FILTER_VALIDATE_INT) !== false
        ) ? $recurrenceIntervalRaw : '1');
        if ((int)$recurrenceInterval <= 0) {
            $recurrenceInterval = '1';
        }

        $weekdayMap = [
            'mon' => 'mon',
            'monday' => 'mon',
            'tue' => 'tue',
            'tuesday' => 'tue',
            'wed' => 'wed',
            'wednesday' => 'wed',
            'thu' => 'thu',
            'thursday' => 'thu',
            'fri' => 'fri',
            'friday' => 'fri',
            'sat' => 'sat',
            'saturday' => 'sat',
            'sun' => 'sun',
            'sunday' => 'sun',
        ];
        $weeklyDays = [];
        $recurrenceDaysRaw = (string)($event['recurrence_days'] ?? '');
        if ($recurrenceDaysRaw !== '') {
            $parts = array_filter(array_map('trim', explode(',', $recurrenceDaysRaw)));
            foreach ($parts as $part) {
                $key = strtolower($part);
                if (isset($weekdayMap[$key])) {
                    $weeklyDays[] = $weekdayMap[$key];
                }
            }
            $weeklyDays = array_values(array_unique($weeklyDays));
        }

        $allowedMonthlyTypes = ['date', 'weekday'];
        $monthlyType = strtolower((string)($event['monthly_type'] ?? 'date'));
        if (!in_array($monthlyType, $allowedMonthlyTypes, true)) {
            $monthlyType = 'date';
        }

        $monthlyDayNumber = '';
        $monthlyWeek = '';
        $monthlyWeekday = '';

        if ($recurrenceType === 'monthly') {
            if ($monthlyType === 'date') {
                $monthlyDayValue = (string)($event['monthly_day'] ?? '');
                if ($monthlyDayValue !== '' && filter_var($monthlyDayValue, FILTER_VALIDATE_INT) !== false) {
                    $monthlyDayNumber = $monthlyDayValue;
                }
            } else {
                $monthlyWeekRaw = strtolower((string)($event['monthly_week'] ?? ''));
                $allowedMonthlyWeeks = ['first', 'second', 'third', 'fourth', 'last'];
                if (in_array($monthlyWeekRaw, $allowedMonthlyWeeks, true)) {
                    $monthlyWeek = $monthlyWeekRaw;
                }

                $monthlyWeekdayRaw = strtolower((string)($event['monthly_day'] ?? ''));
                if (isset($weekdayMap[$monthlyWeekdayRaw])) {
                    $monthlyWeekday = $weekdayMap[$monthlyWeekdayRaw];
                }
            }
        }

        return [
            'event' => $event,
            'errors' => [],
            'input' => [
                'title' => $event['title'] ?? '',
                'description' => $event['description'] ?? '',
                'event_date' => $this->formatForInput($event['event_date'] ?? null),
                'end_date' => $this->formatForInput($event['end_date'] ?? null),
                'location' => $event['location'] ?? '',
                'featured_image_alt' => $event['featured_image_alt'] ?? '',
                'recurrence_type' => $recurrenceType,
                'recurrence_interval' => $recurrenceInterval,
                'recurrence_days' => $weeklyDays,
                'monthly_type' => $monthlyType,
                'monthly_day_number' => $monthlyDayNumber,
                'monthly_week' => $monthlyWeek,
                'monthly_weekday' => $monthlyWeekday,
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

        $request = $this->request();
        $validated = $this->validateEventInput($request);

        if ($validated['errors']) {
            return [
                'event' => $event,
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $updateData = [
            'title' => $validated['input']['title'],
            'description' => $validated['input']['description'],
            'event_date' => $validated['event_date_db'],
            'end_date' => $validated['end_date_db'],
            'location' => $validated['input']['location'],
            'recurrence_type' => $validated['recurrence']['recurrence_type'],
            'recurrence_interval' => $validated['recurrence']['recurrence_interval'],
            'recurrence_days' => $validated['recurrence']['recurrence_days'],
            'monthly_type' => $validated['recurrence']['monthly_type'],
            'monthly_week' => $validated['recurrence']['monthly_week'],
            'monthly_day' => $validated['recurrence']['monthly_day'],
        ];

        // Handle featured image from modal upload or traditional file upload
        $featuredImageUrlUploaded = (string)$request->input('featured_image_url_uploaded', '');
        $imageAlt = trim((string)$request->input('featured_image_alt', ''));

        if ($featuredImageUrlUploaded !== '' && $imageAlt !== '') {
            // Use pre-uploaded image from modal
            $updateData['featured_image'] = $featuredImageUrlUploaded;
            $updateData['featured_image_alt'] = $imageAlt;
        } elseif (!empty($_FILES['featured_image']) && !empty($_FILES['featured_image']['tmp_name'])) {
            // Traditional file upload
            $eventId = (int)$event['id'];
            $communityId = !empty($event['community_id']) ? (int)$event['community_id'] : null;
            $imageValidation = $this->validateImageUpload(
                $_FILES['featured_image'],
                $imageAlt,
                $eventId,
                $communityId
            );

            if (!empty($imageValidation['error'])) {
                return [
                    'event' => $event,
                    'errors' => ['featured_image' => $imageValidation['error']],
                    'input' => $validated['input'],
                ];
            }

            $updateData['featured_image'] = $imageValidation['urls'];
            $updateData['featured_image_alt'] = $imageAlt;
        }

        $this->events->update($event['slug'], $updateData);

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

        $shareLink = '';
        if ($viewerId > 0 && $eventId > 0) {
            $shareResponse = $this->invitations->generateEventShareLink($eventId, $viewerId);
            if (($shareResponse['success'] ?? false) === true) {
                $shareData = $shareResponse['data'] ?? [];
                $shareLink = (string)($shareData['share_url'] ?? '');
            }
        }

        return [
            'status' => 200,
            'event' => $event,
            'tab' => $tab,
            'guest_summary' => $guestSummary,
            'share_link' => $shareLink,
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
        $locationValidation = $this->validator->textField($request->input('location', ''));
        $eventDateRaw = trim((string)$request->input('event_date', ''));
        $endDateRaw = trim((string)$request->input('end_date', ''));

        $errors = [];
        $input = [
            'title' => $titleValidation['value'],
            'description' => $descriptionValidation['value'],
            'location' => $locationValidation['value'],
            'event_date' => $eventDateRaw,
            'end_date' => $endDateRaw,
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

        $endDateDb = null;
        if ($endDateRaw !== '') {
            $timestamp = strtotime($endDateRaw);
            if ($timestamp === false) {
                $errors['end_date'] = 'Provide a valid end date/time.';
            } else {
                $endDateDb = date('Y-m-d H:i:s', $timestamp);

                // Validate that end date is after start date
                if ($eventDateDb !== null && $endDateDb <= $eventDateDb) {
                    $errors['end_date'] = 'End date must be after start date.';
                }
            }
        }

        $allowedRecurrenceTypes = ['none', 'daily', 'weekly', 'monthly'];
        $recurrenceTypeRaw = strtolower(trim((string)$request->input('recurrence_type', 'none')));
        $recurrenceType = in_array($recurrenceTypeRaw, $allowedRecurrenceTypes, true) ? $recurrenceTypeRaw : 'none';

        $recurrenceIntervalRaw = trim((string)$request->input('recurrence_interval', '1'));
        $recurrenceIntervalInput = $recurrenceIntervalRaw !== '' ? $recurrenceIntervalRaw : '1';
        $recurrenceIntervalValue = 1;
        if ($recurrenceType !== 'none') {
            $intervalValidation = filter_var(
                $recurrenceIntervalRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 30]]
            );
            if ($intervalValidation === false) {
                $errors['recurrence_interval'] = 'Recurrence interval must be between 1 and 30.';
            } else {
                $recurrenceIntervalValue = (int)$intervalValidation;
                $recurrenceIntervalInput = (string)$recurrenceIntervalValue;
            }
        } else {
            $recurrenceIntervalInput = '1';
        }

        $weekdayOrder = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $weekdayMap = [
            'mon' => 'mon',
            'monday' => 'mon',
            'tue' => 'tue',
            'tuesday' => 'tue',
            'wed' => 'wed',
            'wednesday' => 'wed',
            'thu' => 'thu',
            'thursday' => 'thu',
            'fri' => 'fri',
            'friday' => 'fri',
            'sat' => 'sat',
            'saturday' => 'sat',
            'sun' => 'sun',
            'sunday' => 'sun',
        ];

        $recurrenceDaysInput = $request->input('recurrence_days', []);
        $selectedWeekdays = [];
        if (is_array($recurrenceDaysInput)) {
            foreach ($recurrenceDaysInput as $day) {
                $normalized = strtolower(trim((string)$day));
                if ($normalized === '') {
                    continue;
                }

                $candidate = $weekdayMap[$normalized] ?? null;
                if ($candidate === null && in_array($normalized, $weekdayOrder, true)) {
                    $candidate = $normalized;
                }

                if ($candidate !== null && !in_array($candidate, $selectedWeekdays, true)) {
                    $selectedWeekdays[] = $candidate;
                }
            }
        }

        if ($selectedWeekdays !== []) {
            $weekdayRank = array_flip($weekdayOrder);
            usort(
                $selectedWeekdays,
                static function (string $a, string $b) use ($weekdayRank): int {
                    return $weekdayRank[$a] <=> $weekdayRank[$b];
                }
            );
        }

        if ($recurrenceType === 'weekly' && $selectedWeekdays === []) {
            $errors['recurrence_days'] = 'Select at least one day of the week.';
        }

        $allowedMonthlyTypes = ['date', 'weekday'];
        $monthlyTypeRaw = strtolower(trim((string)$request->input('monthly_type', 'date')));
        $monthlyType = in_array($monthlyTypeRaw, $allowedMonthlyTypes, true) ? $monthlyTypeRaw : 'date';

        $monthlyDayNumberRaw = trim((string)$request->input('monthly_day_number', ''));
        $monthlyDayNumberValue = null;
        if ($monthlyDayNumberRaw !== '') {
            $dayNumberValidation = filter_var(
                $monthlyDayNumberRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 31]]
            );
            if ($dayNumberValidation !== false) {
                $monthlyDayNumberValue = (int)$dayNumberValidation;
                $monthlyDayNumberRaw = (string)$monthlyDayNumberValue;
            }
        }

        $allowedMonthlyWeeks = ['first', 'second', 'third', 'fourth', 'last'];
        $monthlyWeekRaw = strtolower(trim((string)$request->input('monthly_week', '')));
        $monthlyWeek = in_array($monthlyWeekRaw, $allowedMonthlyWeeks, true) ? $monthlyWeekRaw : '';

        $monthlyWeekdayRaw = strtolower(trim((string)$request->input('monthly_weekday', '')));
        $monthlyWeekday = '';
        if (isset($weekdayMap[$monthlyWeekdayRaw])) {
            $monthlyWeekday = $weekdayMap[$monthlyWeekdayRaw];
        } elseif (in_array($monthlyWeekdayRaw, $weekdayOrder, true)) {
            $monthlyWeekday = $monthlyWeekdayRaw;
        }

        $monthlyTypeForDb = 'date';
        $monthlyWeekForDb = '';
        $monthlyDayForDb = '';

        if ($recurrenceType === 'monthly') {
            $monthlyTypeForDb = $monthlyType;
            if ($monthlyType === 'date') {
                if ($monthlyDayNumberValue === null) {
                    $errors['monthly_day_number'] = 'Choose the day of the month for this recurring event.';
                } else {
                    $monthlyDayForDb = (string)$monthlyDayNumberValue;
                    $monthlyDayNumberRaw = (string)$monthlyDayNumberValue;
                }
                $monthlyWeek = '';
                $monthlyWeekday = '';
            } else {
                if ($monthlyWeek === '') {
                    $errors['monthly_week'] = 'Choose which week of the month this event repeats on.';
                }
                if ($monthlyWeekday === '') {
                    $errors['monthly_weekday'] = 'Choose the weekday for this recurring event.';
                }
                $monthlyWeekForDb = $monthlyWeek;
                $monthlyDayForDb = $monthlyWeekday;
            }
        }

        $input['recurrence_type'] = $recurrenceType;
        $input['recurrence_interval'] = $recurrenceIntervalInput;
        $input['recurrence_days'] = $selectedWeekdays;
        $input['monthly_type'] = $monthlyType;
        $input['monthly_day_number'] = $monthlyDayNumberRaw;
        $input['monthly_week'] = $monthlyWeek;
        $input['monthly_weekday'] = $monthlyWeekday;

        $recurrenceData = [
            'recurrence_type' => $recurrenceType,
            'recurrence_interval' => max(1, $recurrenceIntervalValue),
            'recurrence_days' => $recurrenceType === 'weekly' ? implode(',', $selectedWeekdays) : '',
            'monthly_type' => $recurrenceType === 'monthly' ? $monthlyTypeForDb : 'date',
            'monthly_week' => $recurrenceType === 'monthly' ? $monthlyWeekForDb : '',
            'monthly_day' => $recurrenceType === 'monthly' ? $monthlyDayForDb : '',
        ];

        return [
            'input' => $input,
            'errors' => $errors,
            'event_date_db' => $eventDateDb,
            'end_date_db' => $endDateDb,
            'recurrence' => $recurrenceData,
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

    /**
     * Validate and upload image file
     *
     * @param array $file File from $_FILES
     * @param string $altText Alt text for accessibility
     * @param int $eventId Event ID for context
     * @param int|null $communityId Community ID for context
     * @return array{urls?: string, error?: string}
     */
    private function validateImageUpload(array $file, string $altText, int $eventId, ?int $communityId = null): array
    {
        // Require alt text for accessibility
        if (trim($altText) === '') {
            return ['error' => 'Image description is required for accessibility.'];
        }

        $viewerId = (int)($this->auth->currentUserId() ?? 0);

        $context = ['event_id' => $eventId];
        if ($communityId !== null && $communityId > 0) {
            $context['community_id'] = $communityId;
        }

        $uploadResult = $this->images->upload(
            file: $file,
            uploaderId: $viewerId,
            altText: $altText,
            imageType: 'featured',
            entityType: 'event',
            entityId: $eventId,
            context: $context
        );

        if (!$uploadResult['success']) {
            return ['error' => $uploadResult['error'] ?? 'Image upload failed.'];
        }

        return ['urls' => $uploadResult['urls']];
    }
}
