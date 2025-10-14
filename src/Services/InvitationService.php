<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Services\EventGuestService;
use App\Services\CommunityMemberService;
use App\Services\BlueskyService;

final class InvitationService
{
    private const TOKEN_LENGTH = 32;
    private const EXPIRY_DAYS = 7;

    public function __construct(
        private Database $database,
        private AuthService $auth,
        private MailService $mail,
        private SanitizerService $sanitizer,
        private EventGuestService $eventGuests,
        private CommunityMemberService $communityMembers,
        private BlueskyService $bluesky
    ) {
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function sendCommunityInvitation(int $communityId, int $viewerId, string $email, string $message): array
    {
        $community = $this->fetchCommunity($communityId);
        if ($community === null) {
            return $this->failure('Community not found.', 404);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to send invitations.', 403);
        }

        $email = $this->sanitizer->email($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('Valid email address required.', 422);
        }

        // Check if already invited
        if ($this->isAlreadyInvited('community', $communityId, $email)) {
            return $this->failure('This email has already been invited.', 400);
        }

        // Create invitation
        $token = $this->generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRY_DAYS . ' days'));
        $sanitizedMessage = $this->sanitizer->textarea($message);

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            INSERT INTO vt_community_invitations
            (community_id, invited_by_member_id, invited_email, invitation_token, message, status, expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ');

        $stmt->execute([
            $communityId,
            $viewerId,
            $email,
            $token,
            $sanitizedMessage,
            'pending',
            $expiresAt
        ]);

        // Send email
        $inviterName = $this->auth->getCurrentUser()->display_name ?? 'A member';
        $communityName = $community['name'] ?? 'a community';
        $this->sendInvitationEmail($email, 'community', $communityName, $token, $inviterName, $sanitizedMessage);

        return $this->success([
            'message' => 'Invitation sent successfully!',
        ], 201);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function listCommunityInvitations(int $communityId, int $viewerId): array
    {
        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to view invitations.', 403);
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, invited_email, invitation_token, status, created_at
             FROM vt_community_invitations
             WHERE community_id = :community_id
             ORDER BY created_at DESC"
        );
        $stmt->execute([':community_id' => $communityId]);
        $invitations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->success([
            'invitations' => $invitations,
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function deleteCommunityInvitation(int $communityId, int $invitationId, int $viewerId): array
    {
        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to cancel invitations.', 403);
        }

        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM vt_community_invitations
             WHERE id = :id AND community_id = :community_id"
        );
        $success = $stmt->execute([
            ':id' => $invitationId,
            ':community_id' => $communityId,
        ]);

        if ($success === false || $stmt->rowCount() === 0) {
            return $this->failure('Failed to cancel invitation.', 400);
        }

        return $this->success([
            'message' => 'Invitation cancelled successfully.',
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function acceptCommunityInvitation(string $token, int $viewerId): array
    {
        $token = trim($token);
        if ($token === '') {
            return $this->failure('Invitation token is required.', 400);
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, community_id, invited_email, invited_user_id, expires_at
             FROM vt_community_invitations
             WHERE invitation_token = :token AND status = 'pending'
             LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($invitation === false) {
            return $this->failure('Invalid or expired invitation.', 404);
        }

        $invitationId = (int)$invitation['id'];
        $communityId = (int)$invitation['community_id'];
        $invitedEmail = strtolower((string)($invitation['invited_email'] ?? ''));
        $expiresAt = $invitation['expires_at'] ?? null;

        if ($expiresAt !== null && $expiresAt !== '' && strtotime((string)$expiresAt) < time()) {
            $this->updateCommunityInvitation($invitationId, 'expired');
            return $this->failure('This invitation has expired.', 410);
        }

        if ($viewerId <= 0) {
            return $this->failure('You must be logged in to accept this invitation.', 401);
        }

        $user = $this->auth->getUserById($viewerId);
        if ($user === null) {
            return $this->failure('User not found.', 404);
        }

        $userEmail = strtolower((string)($user->email ?? ''));
        if ($userEmail === '' || $userEmail !== $invitedEmail) {
            return $this->failure('This invitation was sent to a different email address.', 403);
        }

        if ($this->communityMembers->isMember($communityId, $viewerId)) {
            $this->updateCommunityInvitation($invitationId, 'accepted', $viewerId, true);
            return $this->failure('You are already a member of this community.', 409);
        }

        $displayName = (string)($user->display_name ?? $user->email ?? '');

        try {
            $memberId = $this->communityMembers->addMember(
                $communityId,
                $viewerId,
                $user->email ?? '',
                $displayName,
                'member'
            );
        } catch (\RuntimeException $e) {
            return $this->failure('Failed to add you to the community: ' . $e->getMessage(), 500);
        }

        $this->updateCommunityInvitation($invitationId, 'accepted', $viewerId, true);

        return $this->success([
            'message' => 'You have successfully joined the community!',
            'member_id' => $memberId,
            'community_id' => $communityId,
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function sendEventInvitation(int $eventId, int $viewerId, string $email, string $message): array
    {
        $event = $this->fetchEvent($eventId, includeSlug: true, includeTitle: true);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can send invitations.', 403);
        }

        $email = $this->sanitizer->email($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('Valid email address required.', 422);
        }

        if ($this->eventGuests->guestExists($eventId, $email)) {
            return $this->failure('This email has already been invited.', 400);
        }

        // Create guest entry with token
        $token = $this->generateToken();
        $sanitizedMessage = $this->sanitizer->textarea($message);

        $guestId = $this->eventGuests->createGuest($eventId, $email, $token, $sanitizedMessage);

        // Send email
        $inviterName = $this->auth->getCurrentUser()->display_name ?? 'The host';
        $eventName = $event['title'] ?? 'an event';
        $this->sendInvitationEmail($email, 'event', $eventName, $token, $inviterName, $sanitizedMessage);

        // Get updated guest list
        $guestRecords = $this->eventGuests->listGuests($eventId);

        $invitationUrl = $this->buildInvitationUrl('event', $token);

        return $this->success([
            'message' => 'RSVP invitation created successfully!',
            'invitation_url' => $invitationUrl,
            'temporary_guest_id' => $guestId,
            'guests' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ], 201);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function listEventInvitations(int $eventId, int $viewerId): array
    {
        $event = $this->fetchEvent($eventId);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can view invitations.', 403);
        }

        $guestRecords = $this->eventGuests->listGuests($eventId);

        return $this->success([
            'invitations' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function deleteEventInvitation(int $eventId, int $invitationId, int $viewerId): array
    {
        $event = $this->fetchEvent($eventId);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can remove guests.', 403);
        }

        try {
            $this->eventGuests->deleteGuest($eventId, $invitationId);
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage(), 400);
        }

        $guestRecords = $this->eventGuests->listGuests($eventId);

        return $this->success([
            'message' => 'Invitation cancelled successfully.',
            'guests' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function resendEventInvitation(int $eventId, int $invitationId, int $viewerId): array
    {
        $event = $this->fetchEvent($eventId);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can resend invitations.', 403);
        }

        $guest = $this->eventGuests->findGuestForEvent($eventId, $invitationId);
        if ($guest === null) {
            return $this->failure('Invitation not found for this event.', 404);
        }

        $status = strtolower((string)($guest['status'] ?? ''));
        if (!in_array($status, ['pending', 'maybe'], true)) {
            return $this->failure('This guest has already responded. Remove them before sending a new invitation.', 409);
        }

        $newToken = $this->generateToken();

        try {
            $this->eventGuests->updateGuestToken($eventId, $invitationId, $newToken);
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage(), 400);
        }

        $eventName = $event['title'] ?? 'an event';
        $inviterName = $this->auth->getCurrentUser()->display_name ?? 'The host';
        $emailSent = $this->sendInvitationEmail(
            (string)($guest['email'] ?? ''),
            'event',
            $eventName,
            $newToken,
            $inviterName,
            (string)($guest['notes'] ?? '')
        );

        $messageText = $emailSent
            ? 'Invitation email resent successfully.'
            : 'Invitation resent. Email delivery may have failed.';

        $guestRecords = $this->eventGuests->listGuests($eventId);

        return $this->success([
            'message' => $messageText,
            'email_sent' => $emailSent,
            'guests' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ]);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function getEventGuests(int $eventId): array
    {
        $records = $this->eventGuests->listGuests($eventId);
        return $this->normalizeEventGuests($records);
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail(
        string $email,
        string $type,
        string $entityName,
        string $token,
        string $inviterName,
        string $message = ''
    ): bool {
        $url = $this->buildInvitationUrl($type, $token);

        $subject = $type === 'community'
            ? "You've been invited to join {$entityName} on Elonara Social"
            : "You're invited to {$entityName} on Elonara Social";

        $variables = [
            'inviter_name' => $inviterName,
            'entity_name' => $entityName,
            'entity_type' => $type,
            'invitation_url' => $url,
            'personal_message' => $message,
            'subject' => $subject,
        ];

        return $this->mail->sendTemplate($email, 'invitation', $variables);
    }

    /**
     * Build invitation URL
     */
    private function buildInvitationUrl(string $type, string $token): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;

        if ($type === 'community') {
            return "{$baseUrl}/invitation/accept?token={$token}";
        } else {
            return "{$baseUrl}/rsvp/{$token}";
        }
    }

    /**
     * Check if email has already been invited
     */
    private function isAlreadyInvited(string $type, int $entityId, string $email): bool
    {
        $email = $this->sanitizer->email($email);
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM vt_community_invitations
            WHERE community_id = ? AND invited_email = ? AND status = 'pending'
        ");
        $stmt->execute([$entityId, $email]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function updateCommunityInvitation(int $invitationId, string $status, ?int $userId = null, bool $markAccepted = false): void
    {
        $parts = [
            'status = :status',
            'responded_at = NOW()',
        ];
        $params = [
            ':status' => $status,
            ':id' => $invitationId,
        ];

        if ($userId !== null) {
            $parts[] = 'invited_user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        if ($markAccepted && $status === 'accepted') {
            $parts[] = 'accepted_at = NOW()';
        }

        $sql = 'UPDATE vt_community_invitations SET ' . implode(', ', $parts) . ' WHERE id = :id';
        $stmt = $this->database->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Generate cryptographically secure token
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * @param array<string,mixed> $community
     */
    private function canManageEvent(array $event, int $viewerId): bool
    {
        if ((int)($event['author_id'] ?? 0) === $viewerId) {
            return true;
        }

        return $this->auth->currentUserCan('edit_others_posts');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchCommunity(int $communityId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT id, name FROM vt_communities WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $communityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchEvent(int $eventId, bool $includeSlug = false, bool $includeTitle = false): ?array
    {
        $fields = ['id', 'author_id'];
        if ($includeSlug) {
            $fields[] = 'slug';
        }
        if ($includeTitle) {
            $fields[] = 'title';
        }

        $fieldList = implode(', ', $fields);

        $stmt = $this->database->pdo()->prepare(
            "SELECT {$fieldList} FROM vt_events WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function canManageCommunity(int $communityId, int $viewerId, array $roles): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT role FROM vt_community_members WHERE community_id = :community_id AND user_id = :user_id LIMIT 1"
        );
        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $viewerId,
        ]);
        $role = $stmt->fetchColumn();

        if ($role === false) {
            return $this->auth->currentUserCan('manage_options');
        }

        return in_array($role, $roles, true) || $this->auth->currentUserCan('manage_options');
    }

    /**
     * @param array<int, object|array<string,mixed>> $guestRecords
     * @return array<int, array<string,mixed>>
     */
    private function normalizeEventGuests(array $guestRecords): array
    {
        $normalized = [];

        foreach ($guestRecords as $guest) {
            if ($guest === null) {
                continue;
            }

            $record = is_object($guest) ? $guest : (object)$guest;

            $normalized[] = [
                'id' => (int)($record->id ?? 0),
                'name' => (string)($record->name ?? ''),
                'email' => (string)($record->email ?? ''),
                'status' => (string)($record->status ?? 'pending'),
                'rsvp_date' => $record->rsvp_date ?? null,
                'plus_one' => (int)($record->plus_one ?? 0),
                'plus_one_name' => (string)($record->plus_one_name ?? ''),
                'notes' => (string)($record->notes ?? ''),
                'dietary_restrictions' => (string)($record->dietary_restrictions ?? ''),
                'invitation_source' => (string)($record->invitation_source ?? ''),
                'temporary_guest_id' => (string)($record->temporary_guest_id ?? ''),
                'rsvp_token' => (string)($record->rsvp_token ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * Invite Bluesky followers to an event
     *
     * @param int $eventId
     * @param int $viewerId
     * @param array<string> $followerDids Array of Bluesky DIDs
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function inviteBlueskyFollowersToEvent(int $eventId, int $viewerId, array $followerDids): array
    {
        $event = $this->fetchEvent($eventId, includeSlug: true, includeTitle: true);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can send invitations.', 403);
        }

        if (empty($followerDids)) {
            return $this->failure('No followers selected.', 422);
        }

        // Get cached followers to map DIDs to handles
        $cachedResult = $this->bluesky->getCachedFollowers($viewerId);
        $followersMap = [];

        if ($cachedResult['success'] && !empty($cachedResult['followers'])) {
            foreach ($cachedResult['followers'] as $follower) {
                $did = $follower['did'] ?? '';
                if ($did !== '') {
                    $followersMap[$did] = $follower;
                }
            }
        }

        $invited = 0;
        $skipped = 0;
        $errors = [];
        $posted = 0;

        $eventName = $event['title'] ?? 'an event';

        foreach ($followerDids as $did) {
            $did = trim($did);
            if ($did === '') {
                continue;
            }

            // Use bsky: prefix to indicate DID-based invitation
            $email = 'bsky:' . $did;

            // Check if already invited
            if ($this->eventGuests->guestExists($eventId, $email)) {
                $skipped++;
                continue;
            }

            try {
                $token = $this->generateToken();
                $this->eventGuests->createGuest($eventId, $email, $token, '', 'bluesky');
                $invited++;

                // Post to Bluesky with mention
                $follower = $followersMap[$did] ?? null;
                if ($follower !== null) {
                    $handle = $follower['handle'] ?? '';
                    $displayName = $follower['displayName'] ?? $handle;

                    if ($handle !== '') {
                        $inviteUrl = $this->buildInvitationUrl('event', $token);
                        $postText = "@{$handle} You've been invited to {$eventName}! RSVP: {$inviteUrl}";

                        $postResult = $this->bluesky->createPost($viewerId, $postText, [
                            ['handle' => $handle, 'did' => $did]
                        ]);

                        if ($postResult['success']) {
                            $posted++;
                        }
                    }
                }

            } catch (\Exception $e) {
                $errors[] = 'Failed to invite ' . substr($did, 0, 20) . '...';
            }
        }

        $message = "Invited {$invited} followers";
        if ($posted > 0) {
            $message .= ", posted {$posted} invitations to Bluesky";
        }
        if ($skipped > 0) {
            $message .= ", skipped {$skipped} already invited";
        }

        return $this->success([
            'message' => $message,
            'invited' => $invited,
            'posted' => $posted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * Invite Bluesky followers to a community
     *
     * @param int $communityId
     * @param int $viewerId
     * @param array<string> $followerDids Array of Bluesky DIDs
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function inviteBlueskyFollowersToCommunity(int $communityId, int $viewerId, array $followerDids): array
    {
        $community = $this->fetchCommunity($communityId);
        if ($community === null) {
            return $this->failure('Community not found.', 404);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to send invitations.', 403);
        }

        if (empty($followerDids)) {
            return $this->failure('No followers selected.', 422);
        }

        // Get cached followers to map DIDs to handles
        $cachedResult = $this->bluesky->getCachedFollowers($viewerId);
        $followersMap = [];

        if ($cachedResult['success'] && !empty($cachedResult['followers'])) {
            foreach ($cachedResult['followers'] as $follower) {
                $did = $follower['did'] ?? '';
                if ($did !== '') {
                    $followersMap[$did] = $follower;
                }
            }
        }

        $invited = 0;
        $skipped = 0;
        $errors = [];
        $posted = 0;

        $communityName = $community['name'] ?? 'a community';

        foreach ($followerDids as $did) {
            $did = trim($did);
            if ($did === '') {
                continue;
            }

            // Use bsky: prefix to indicate DID-based invitation
            $email = 'bsky:' . $did;

            // Check if already invited
            if ($this->isAlreadyInvited('community', $communityId, $email)) {
                $skipped++;
                continue;
            }

            try {
                $token = $this->generateToken();
                $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRY_DAYS . ' days'));

                $pdo = $this->database->pdo();
                $stmt = $pdo->prepare('
                    INSERT INTO vt_community_invitations
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
                    $expiresAt
                ]);

                $invited++;

                // Post to Bluesky with mention
                $follower = $followersMap[$did] ?? null;
                if ($follower !== null) {
                    $handle = $follower['handle'] ?? '';
                    $displayName = $follower['displayName'] ?? $handle;

                    if ($handle !== '') {
                        $inviteUrl = $this->buildInvitationUrl('community', $token);
                        $postText = "@{$handle} You've been invited to join {$communityName} on Elonara Social! {$inviteUrl}";

                        $postResult = $this->bluesky->createPost($viewerId, $postText, [
                            ['handle' => $handle, 'did' => $did]
                        ]);

                        if ($postResult['success']) {
                            $posted++;
                        }
                    }
                }

            } catch (\Exception $e) {
                $errors[] = 'Failed to invite ' . substr($did, 0, 20) . '...';
            }
        }

        $message = "Invited {$invited} followers";
        if ($posted > 0) {
            $message .= ", posted {$posted} invitations to Bluesky";
        }
        if ($skipped > 0) {
            $message .= ", skipped {$skipped} already invited";
        }

        return $this->success([
            'message' => $message,
            'invited' => $invited,
            'posted' => $posted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
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
    private function failure(string $message, int $status): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => [],
        ];
    }
}
