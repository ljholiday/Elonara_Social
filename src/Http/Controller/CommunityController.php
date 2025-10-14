<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\CommunityService;
use App\Services\CircleService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ValidatorService;
use App\Services\CommunityMemberService;
use App\Services\EventService;
use App\Services\ConversationService;

final class CommunityController
{
    private const VALID_CIRCLES = ['all', 'inner', 'trusted', 'extended'];

    public function __construct(
        private CommunityService $communities,
        private CircleService $circles,
        private AuthService $auth,
        private AuthorizationService $authz,
        private ValidatorService $validator,
        private CommunityMemberService $members,
        private EventService $events,
        private ConversationService $conversations
    ) {
    }

    /**
     * @return array{communities: array<int, array<string, mixed>>}
     */
    public function index(): array
    {
        $request = $this->request();
        $circle = $this->normalizeCircle($request->query('circle'));
        $viewerId = $this->auth->currentUserId() ?? 0;
        $context = $this->circles->buildContext($viewerId);
        $allowed = $this->circles->resolveCommunitiesForCircle($context, $circle);
        $memberCommunities = $this->circles->memberCommunities($context);

        $communities = $this->communities->listByCircle($allowed, $memberCommunities);

        return [
            'communities' => $communities,
            'circle' => $circle,
            'circle_context' => $context,
        ];
    }

    /**
     * @return array{
     *   community: array<string, mixed>|null,
     *   status: int,
     *   viewer?: array<string, mixed>,
     *   circle_context?: array<string, array{communities: array<int>, creators: array<int>}>
     * }
     */
    public function show(string $slugOrId): array
    {
        $viewerId = $this->auth->currentUserId() ?? 0;
        $context = $this->circles->buildContext($viewerId);
        $memberCommunities = $this->circles->memberCommunities($context);

        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
                'status' => 404,
                'circle_context' => $context,
            ];
        }

        $communityId = (int)($community['id'] ?? 0);
        $privacy = strtolower((string)($community['privacy'] ?? 'public'));
        $isMember = $communityId > 0 && in_array($communityId, $memberCommunities, true);
        $isCreator = $viewerId > 0 && isset($community['creator_id']) && (int)$community['creator_id'] === $viewerId;

        if ($privacy === 'private' && !$isMember && !$isCreator) {
            return [
                'community' => null,
                'status' => 404,
                'circle_context' => $context,
            ];
        }

        return [
            'community' => $community,
            'status' => 200,
            'viewer' => [
                'id' => $viewerId,
                'is_member' => $isMember,
                'is_creator' => $isCreator,
            ],
            'circle_context' => $context,
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
                'name' => '',
                'description' => '',
                'privacy' => 'public',
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
                'errors' => ['auth' => 'You must be logged in to create a community.'],
                'input' => [],
            ];
        }

        $validated = $this->validateCommunityInput($this->request());

        if ($validated['errors']) {
            return [
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $slug = $this->communities->create([
            'name' => $validated['input']['name'],
            'description' => $validated['input']['description'],
            'privacy' => $validated['input']['privacy'],
        ]);

        return [
            'redirect' => '/communities/' . $slug,
        ];
    }

    /**
     * @return array{
     *   community: array<string,mixed>|null,
     *   errors: array<string,string>,
     *   input: array<string,string>
     * }
     */
    public function edit(string $slugOrId): array
    {
        $viewerId = $this->auth->currentUserId() ?? 0;
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
                'errors' => [],
                'input' => [],
            ];
        }

        if (!$this->authz->canEditCommunity($community, $viewerId)) {
            return [
                'community' => null,
                'errors' => ['auth' => 'You do not have permission to edit this community.'],
                'input' => [],
            ];
        }

        return [
            'community' => $community,
            'errors' => [],
            'input' => [
                'name' => $community['title'] ?? '',
                'description' => $community['description'] ?? '',
                'privacy' => strtolower((string)($community['privacy'] ?? 'public')),
            ],
        ];
    }

    /**
     * @return array{
     *   redirect?: string,
     *   community?: array<string,mixed>|null,
     *   errors?: array<string,string>,
     *   input?: array<string,string>
     * }
     */
    public function update(string $slugOrId): array
    {
        $viewerId = $this->auth->currentUserId() ?? 0;
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
            ];
        }

        if (!$this->authz->canEditCommunity($community, $viewerId)) {
            return [
                'community' => null,
                'errors' => ['auth' => 'You do not have permission to edit this community.'],
            ];
        }

        $validated = $this->validateCommunityInput($this->request());
        if ($validated['errors']) {
            return [
                'community' => $community,
                'errors' => $validated['errors'],
                'input' => $validated['input'],
            ];
        }

        $this->communities->update($community['slug'], [
            'name' => $validated['input']['name'],
            'description' => $validated['input']['description'],
            'privacy' => $validated['input']['privacy'],
        ]);

        return [
            'redirect' => '/communities/' . $community['slug'],
        ];
    }

    /**
     * @return array{
     *   status:int,
     *   community?: array<string,mixed>|null,
     *   tab?: string,
     *   members?: array<int,array<string,mixed>>,
     *   viewer_role?: ?string
     * }
     */
    public function manage(string $slugOrId): array
    {
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'status' => 404,
                'community' => null,
            ];
        }

        $communityId = (int)($community['id'] ?? 0);
        if ($communityId <= 0) {
            return [
                'status' => 404,
                'community' => null,
            ];
        }

        $viewerId = $this->auth->currentUserId() ?? 0;
        $viewerRole = null;
        if ($viewerId > 0) {
            $viewerRole = $this->members->getMemberRole($communityId, $viewerId);
        }

        $canManage = $this->canManageCommunity($viewerRole);
        if (!$canManage) {
            return [
                'status' => 403,
                'community' => null,
            ];
        }

        $tab = $this->normalizeManageTab($this->request()->query('tab'));
        $members = $this->members->listMembers($communityId);

        return [
            'status' => 200,
            'community' => $community,
            'tab' => $tab,
            'members' => $members,
            'viewer_role' => $viewerRole,
            'viewer_id' => $viewerId,
            'can_manage_members' => $this->canEditMembers($viewerRole),
        ];
    }

    /**
     * @return array{
     *   input: array<string,string>,
     *   errors: array<string,string>
     * }
     */
    /**
     * @return array{redirect?: string, error?: string}
     */
    public function destroy(string $slugOrId): array
    {
        $viewerId = $this->auth->currentUserId() ?? 0;
        $community = $this->communities->getBySlugOrId($slugOrId);

        if ($community === null) {
            return [
                'redirect' => '/communities',
            ];
        }

        if (!$this->authz->canDeleteCommunity($community, $viewerId)) {
            return [
                'error' => 'You do not have permission to delete this community.',
                'redirect' => '/communities/' . $community['slug'],
            ];
        }

        $this->communities->delete($slugOrId);
        return [
            'redirect' => '/communities',
        ];
    }

    /**
     * @return array{
     *   community: array<string,mixed>|null,
     *   events: array<int,array<string,mixed>>
     * }
     */
    public function events(string $slugOrId): array
    {
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
                'events' => [],
            ];
        }

        $communityId = (int)($community['id'] ?? 0);
        $events = $communityId > 0 ? $this->events->listByCommunity($communityId) : [];

        return [
            'community' => $community,
            'events' => $events,
        ];
    }

    /**
     * @return array{
     *   community: array<string,mixed>|null,
     *   conversations: array<int,array<string,mixed>>
     * }
     */
    public function conversations(string $slugOrId): array
    {
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
                'conversations' => [],
            ];
        }

        $communityId = (int)($community['id'] ?? 0);
        $conversations = $communityId > 0 ? $this->conversations->listByCommunity($communityId) : [];

        return [
            'community' => $community,
            'conversations' => $conversations,
        ];
    }

    /**
     * @return array{
     *   community: array<string,mixed>|null,
     *   members: array<int,array<string,mixed>>
     * }
     */
    public function members(string $slugOrId): array
    {
        $community = $this->communities->getBySlugOrId($slugOrId);
        if ($community === null) {
            return [
                'community' => null,
                'members' => [],
            ];
        }

        $communityId = (int)($community['id'] ?? 0);
        $members = $communityId > 0 ? $this->members->listMembers($communityId) : [];

        return [
            'community' => $community,
            'members' => $members,
        ];
    }

    private function validateCommunityInput(Request $request): array
    {
        $nameValidation = $this->validator->required($request->input('name', ''));
        $descriptionValidation = $this->validator->textField($request->input('description', ''));
        $privacyRaw = strtolower(trim((string)$request->input('privacy', 'public')));

        $errors = [];
        $input = [
            'name' => $nameValidation['value'],
            'description' => $descriptionValidation['value'],
            'privacy' => $privacyRaw,
        ];

        if (!$nameValidation['is_valid']) {
            $errors['name'] = $nameValidation['errors'][0] ?? 'Name is required.';
        }

        if (!in_array($privacyRaw, ['public', 'private'], true)) {
            $errors['privacy'] = 'Privacy must be public or private.';
        }

        return [
            'input' => $input,
            'errors' => $errors,
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function normalizeManageTab(?string $tab): string
    {
        $tab = strtolower((string)$tab);
        return in_array($tab, ['members', 'invites'], true) ? $tab : 'members';
    }

    private function canManageCommunity(?string $viewerRole): bool
    {
        if ($this->auth->currentUserCan('manage_options')) {
            return true;
        }

        if ($viewerRole === null) {
            return false;
        }

        return in_array($viewerRole, ['admin', 'moderator'], true);
    }

    private function canEditMembers(?string $viewerRole): bool
    {
        if ($this->auth->currentUserCan('manage_options')) {
            return true;
        }

        return $viewerRole === 'admin';
    }

    private function normalizeCircle(?string $circle): string
    {
        $circle = strtolower((string) $circle);
        return in_array($circle, self::VALID_CIRCLES, true) ? $circle : 'all';
    }
}
