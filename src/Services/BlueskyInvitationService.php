<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Support\BlueskyLogger;

final class BlueskyInvitationService
{
    private const TOKEN_LENGTH = 32;
    private const COMMUNITY_EXPIRY_DAYS = 7;

    public function __construct(
        private Database $database,
        private AuthService $auth,
        private BlueskyService $bluesky,
        private EventGuestService $eventGuests,
        private CommunityMemberService $communityMembers
    ) {
    }

    /**
     * @param array<int,string> $followerDids
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function inviteFollowersToEvent(int $eventId, int $viewerId, array $followerDids): array
    {
        $event = $this->fetchEvent($eventId);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can send invitations.', 403);
        }

        $followerDids = $this->normalizeFollowerList($followerDids);
        if ($followerDids === []) {
            return $this->failure('No followers selected.', 422);
        }

        $followersMap = $this->loadFollowers($viewerId);
        $invited = 0;
        $skipped = 0;
        $errors = [];
        $posted = 0;
        $processed = [];
        $eventName = $event['title'] ?? 'an event';

        foreach ($followerDids as $did) {
            if (isset($processed[$did])) {
                continue;
            }
            $processed[$did] = true;

            $email = 'bsky:' . $did;
            if ($this->eventGuests->guestExists($eventId, $email)) {
                $skipped++;
                continue;
            }

            try {
                $token = $this->generateToken();
                $this->eventGuests->createGuest($eventId, $email, $token, '', 'bluesky');
                $invited++;

                $follower = $followersMap[$did] ?? null;
                if ($follower !== null) {
                    $handle = $follower['handle'] ?? '';
                    if ($handle !== '') {
                        $inviteUrl = $this->buildInvitationUrl('event', $token);
                        $postText = "@{$handle} You've been invited to {$eventName}! RSVP: {$inviteUrl}";
                        $postResult = $this->bluesky->createPost($viewerId, $postText, [
                            ['handle' => $handle, 'did' => $did],
                        ]);
                        BlueskyLogger::log(sprintf('[BlueskyInvitationService] invite event user=%d follower=%s result=%s', $viewerId, $did, json_encode($postResult)));
                        if ($postResult['success']) {
                            $posted++;
                        } elseif ($postResult['needs_reauth'] ?? false) {
                            return $this->failure(
                                $postResult['message'] ?? 'Bluesky authorization expired. Please reauthorize.',
                                409,
                                ['needs_reauth' => true]
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = 'Failed to invite ' . substr($did, 0, 20) . '...';
            }
        }

        return $this->success([
            'message' => $this->composeResultMessage($invited, $posted, $skipped),
            'invited' => $invited,
            'posted' => $posted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<int,string> $followerDids
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function inviteFollowersToCommunity(int $communityId, int $viewerId, array $followerDids): array
    {
        $community = $this->fetchCommunity($communityId);
        if ($community === null) {
            return $this->failure('Community not found.', 404);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to send invitations.', 403);
        }

        $followerDids = $this->normalizeFollowerList($followerDids);
        if ($followerDids === []) {
            return $this->failure('No followers selected.', 422);
        }

        $followersMap = $this->loadFollowers($viewerId);
        $pdo = $this->database->pdo();

        $invited = 0;
        $skipped = 0;
        $errors = [];
        $posted = 0;
        $processed = [];
        $communityName = $community['name'] ?? 'a community';
        $appName = (string)app_config('app.name', 'our community');

        foreach ($followerDids as $did) {
            if (isset($processed[$did])) {
                continue;
            }
            $processed[$did] = true;

            $email = 'bsky:' . $did;
            if ($this->isAlreadyInvited($communityId, $email)) {
                $skipped++;
                continue;
            }

            try {
                $token = $this->generateToken();
                $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::COMMUNITY_EXPIRY_DAYS . ' days'));
                $stmt = $pdo->prepare('
                    INSERT INTO community_invitations
                    (community_id, invited_by_member_id, invited_email, invitation_token, message, status, expires_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $communityId,
                    $viewerId,
                    $email,
                    $token,
                    '',
                    'pending',
                    $expiresAt,
                ]);
                $invited++;

                $follower = $followersMap[$did] ?? null;
                if ($follower !== null) {
                    $handle = $follower['handle'] ?? '';
                    if ($handle !== '') {
                        $inviteUrl = $this->buildInvitationUrl('community', $token);
                        $postText = "@{$handle} You've been invited to join {$communityName} on {$appName}! {$inviteUrl}";
                        $postResult = $this->bluesky->createPost($viewerId, $postText, [
                            ['handle' => $handle, 'did' => $did],
                        ]);
                        BlueskyLogger::log(sprintf('[BlueskyInvitationService] invite community user=%d follower=%s result=%s', $viewerId, $did, json_encode($postResult)));
                        if ($postResult['success']) {
                            $posted++;
                        } elseif ($postResult['needs_reauth'] ?? false) {
                            return $this->failure(
                                $postResult['message'] ?? 'Bluesky authorization expired. Please reauthorize.',
                                409,
                                ['needs_reauth' => true]
                            );
                        }
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = 'Failed to invite ' . substr($did, 0, 20) . '...';
            }
        }

        return $this->success([
            'message' => $this->composeResultMessage($invited, $posted, $skipped),
            'invited' => $invited,
            'posted' => $posted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function cancelCommunityInvitation(int $communityId, int $invitationId, int $viewerId): array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            'SELECT invited_by_member_id FROM community_invitations WHERE id = :id AND community_id = :community_id LIMIT 1'
        );
        $stmt->execute([
            ':id' => $invitationId,
            ':community_id' => $communityId,
        ]);

        $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($invitation === false) {
            return $this->failure('Invitation not found.', 404);
        }

        $invitedByUserId = (int)($invitation['invited_by_member_id'] ?? 0);
        $canManage = $this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator']);
        $isInviter = $invitedByUserId === $viewerId;

        if (!$canManage && !$isInviter) {
            return $this->failure('You do not have permission to cancel invitations.', 403);
        }

        $delete = $pdo->prepare(
            'DELETE FROM community_invitations WHERE id = :id AND community_id = :community_id LIMIT 1'
        );
        $delete->execute([
            ':id' => $invitationId,
            ':community_id' => $communityId,
        ]);

        if ($delete->rowCount() === 0) {
            return $this->failure('Failed to cancel invitation.', 400);
        }

        return $this->success([
            'message' => 'Invitation cancelled successfully.',
        ]);
    }

    /**
     * @param array<int,string> $followerDids
     * @return array<int,string>
     */
    private function normalizeFollowerList(array $followerDids): array
    {
        $clean = [];
        foreach ($followerDids as $did) {
            $did = strtolower(trim((string)$did));
            if ($did !== '') {
                $clean[] = $did;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function loadFollowers(int $viewerId): array
    {
        $result = $this->bluesky->getCachedFollowers($viewerId);
        $map = [];

        if ($result['success'] && !empty($result['followers'])) {
            foreach ($result['followers'] as $follower) {
                $did = strtolower(trim((string)($follower['did'] ?? '')));
                if ($did !== '') {
                    $map[$did] = $follower;
                }
            }
        }

        return $map;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    private function buildInvitationUrl(string $type, string $token): string
    {
        $baseUrl = $this->getBaseUrl();
        $path = $type === 'event'
            ? '/rsvp/' . urlencode($token)
            : '/invitation/accept?token=' . urlencode($token);

        return $baseUrl . $path;
    }

    private function getBaseUrl(): string
    {
        $baseUrl = rtrim((string)app_config('app.url', ''), '/');
        if ($baseUrl !== '') {
            return $baseUrl;
        }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchEvent(int $eventId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT id, author_id, title FROM events WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchCommunity(int $communityId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT id, name FROM communities WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $communityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string,mixed> $event
     */
    private function canManageEvent(array $event, int $viewerId): bool
    {
        if ((int)($event['author_id'] ?? 0) === $viewerId) {
            return true;
        }

        return $this->auth->currentUserCan('edit_others_posts');
    }

    private function canManageCommunity(int $communityId, int $viewerId, array $roles): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        if ($this->auth->currentUserCan('manage_options')) {
            return true;
        }

        $role = $this->communityMembers->getMemberRole($communityId, $viewerId);
        if ($role === null) {
            return false;
        }

        return in_array($role, $roles, true);
    }

    private function isAlreadyInvited(int $communityId, string $email): bool
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM community_invitations WHERE community_id = :community_id AND invited_email = :email'
        );
        $stmt->execute([
            ':community_id' => $communityId,
            ':email' => $email,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function composeResultMessage(int $invited, int $posted, int $skipped): string
    {
        $message = "Invited {$invited} followers";
        if ($posted > 0) {
            $message .= ", posted {$posted} invitations to Bluesky";
        }
        if ($skipped > 0) {
            $message .= ", skipped {$skipped} already invited";
        }

        return $message;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        return [
            'success' => true,
            'status' => $status,
            'message' => '',
            'data' => $data,
        ];
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    private function failure(string $message, int $status, array $data = []): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }
}
