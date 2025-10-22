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
    private const EVENT_TOKEN_LENGTH = 64;

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
            INSERT INTO community_invitations
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
        $appName = (string)app_config('app.name', 'our community');
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
            "SELECT ci.id,
                    ci.invited_email,
                    ci.invitation_token,
                    ci.status,
                    ci.created_at,
                    ci.invited_user_id,
                    u.display_name AS member_name,
                    u.username AS member_username
             FROM community_invitations ci
             LEFT JOIN users u ON u.id = ci.invited_user_id
             WHERE ci.community_id = :community_id
             ORDER BY ci.created_at DESC"
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
    public function resendCommunityInvitation(int $communityId, int $invitationId, int $viewerId): array
    {
        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to resend invitations.', 403);
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "SELECT id, invited_email, message, status
             FROM community_invitations
             WHERE id = :id AND community_id = :community_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $invitationId,
            ':community_id' => $communityId,
        ]);

        $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($invitation === false) {
            return $this->failure('Invitation not found.', 404);
        }

        $status = strtolower((string)($invitation['status'] ?? ''));
        if ($status !== 'pending') {
            return $this->failure('This invitation cannot be resent.', 409);
        }

        $community = $this->fetchCommunity($communityId);
        if ($community === null) {
            return $this->failure('Community not found.', 404);
        }

        $invitedEmail = strtolower((string)($invitation['invited_email'] ?? ''));
        if (\str_starts_with($invitedEmail, 'bsky:')) {
            return $this->resendBlueskyCommunityInvitation($communityId, $invitationId, $viewerId, $community, $invitation);
        }

        $newToken = $this->generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRY_DAYS . ' days'));

        $update = $pdo->prepare(
            "UPDATE community_invitations
             SET invitation_token = :token,
                 expires_at = :expires_at,
                 responded_at = NULL,
                 status = 'pending'
             WHERE id = :id AND community_id = :community_id
             LIMIT 1"
        );
        $update->execute([
            ':token' => $newToken,
            ':expires_at' => $expiresAt,
            ':id' => $invitationId,
            ':community_id' => $communityId,
        ]);

        $inviterName = $this->auth->getCurrentUser()->display_name ?? 'A member';
        $communityName = $community['name'] ?? 'a community';
        $email = (string)($invitation['invited_email'] ?? '');
        $message = (string)($invitation['message'] ?? '');

        $this->sendInvitationEmail(
            $email,
            'community',
            $communityName,
            $newToken,
            $inviterName,
            $message,
            ['community_id' => $communityId]
        );

        $list = $this->listCommunityInvitations($communityId, $viewerId);
        if (!$list['success']) {
            return $list;
        }

        $data = $list['data'] ?? [];
        $data['message'] = 'Invitation email resent successfully.';

        return $this->success($data);
    }

    /**
     * @param array<string,mixed> $community
     * @param array<string,mixed> $invitation
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    private function resendBlueskyCommunityInvitation(int $communityId, int $invitationId, int $viewerId, array $community, array $invitation): array
    {
        $did = substr((string)$invitation['invited_email'], 5);
        $did = strtolower(trim($did));
        if ($did === '') {
            return $this->failure('Invalid Bluesky invitation.', 400);
        }

        $token = $this->generateToken();
        $inviteUrl = $this->buildInvitationUrl('community', $token);

        $handle = $this->resolveBlueskyHandle($did);
        $appName = (string)app_config('app.name', 'Elonara Social');
        $communityName = (string)($community['name'] ?? 'a community');

        $message = $handle !== null
            ? '@' . $handle . ' You\'ve been invited to join ' . $communityName . ' on ' . $appName . '! ' . $inviteUrl
            : 'You\'ve been invited to join ' . $communityName . ' on ' . $appName . '! ' . $inviteUrl;

        $mentions = [];
        if ($handle !== null) {
            $mentions[] = ['handle' => $handle, 'did' => $did];
        }

        $postResult = $this->bluesky->createPost($viewerId, $message, $mentions);
        if (!$postResult['success']) {
            return $this->failure($postResult['message'] ?? 'Unable to resend invitation via Bluesky.', 400);
        }

        $pdo = $this->database->pdo();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRY_DAYS . ' days'));
        $update = $pdo->prepare(
            "UPDATE community_invitations
             SET invitation_token = :token,
                 expires_at = :expires_at,
                 responded_at = NULL,
                 status = 'pending'
             WHERE id = :id AND community_id = :community_id
             LIMIT 1"
        );
        $update->execute([
            ':token' => $token,
            ':expires_at' => $expiresAt,
            ':id' => $invitationId,
            ':community_id' => $communityId,
        ]);

        $list = $this->listCommunityInvitations($communityId, $viewerId);
        if (!$list['success']) {
            return $list;
        }

        $data = $list['data'] ?? [];
        $data['message'] = 'Bluesky invitation resent successfully.';

        return $this->success($data);
    }

    private function resolveBlueskyHandle(string $did): ?string
    {
        $profile = $this->bluesky->getProfile($did);
        if (!is_array($profile)) {
            return null;
        }

        $handle = (string)($profile['handle'] ?? '');
        $handle = ltrim($handle, '@');

        return $handle !== '' ? $handle : null;
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
            "DELETE FROM community_invitations
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
             FROM community_invitations
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
        $invitedEmailRaw = (string)($invitation['invited_email'] ?? '');
        $invitedEmail = strtolower(trim($invitedEmailRaw));
        $isBlueskyInvite = \str_starts_with($invitedEmail, 'bsky:');
        $invitedDid = $isBlueskyInvite ? strtolower(trim(substr($invitedEmail, 5))) : '';
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

        $blueskyVerified = false;
        $blueskyConnectUrl = null;

        if ($isBlueskyInvite) {
            if ($invitedDid === '') {
                return $this->failure('Invalid Bluesky invitation.', 400);
            }

            $credentials = $this->bluesky->getCredentials($viewerId);
            if ($credentials !== null) {
                $userDid = strtolower((string)($credentials['did'] ?? ''));
                if ($userDid === '' || $userDid !== $invitedDid) {
                    return $this->failure('This invitation was sent to a different Bluesky account.', 403);
                }
                $blueskyVerified = true;
            } else {
                $redirectBack = '/invitation/accept?token=' . rawurlencode($token);
                $blueskyConnectUrl = '/profile/edit?connect=bluesky&redirect=' . rawurlencode($redirectBack);
            }
        } else {
            if ($userEmail === '' || $userEmail !== $invitedEmail) {
                return $this->failure('This invitation was sent to a different email address.', 403);
            }
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

        if ($isBlueskyInvite && $invitedDid !== '') {
            $stmt = $this->database->pdo()->prepare(
                'UPDATE community_members SET at_protocol_did = :did WHERE id = :id'
            );
            $stmt->execute([
                ':did' => $invitedDid,
                ':id' => $memberId,
            ]);

            // Store invited DID in member_identities if not already verified
            if (!$blueskyVerified) {
                $this->storePendingBlueskyDid($viewerId, $invitedDid);
            }
        }

        $community = $this->fetchCommunity($communityId);
        $communitySlug = (string)($community['slug'] ?? '');
        $redirectUrl = $communitySlug !== ''
            ? '/communities/' . $communitySlug
            : '/communities/' . $communityId;

        $message = $blueskyVerified
            ? 'You have successfully joined the community!'
            : ($isBlueskyInvite
                ? 'Invitation accepted! Connect your Bluesky account to unlock Bluesky-powered features.'
                : 'You have successfully joined the community!');

        return $this->success([
            'message' => $message,
            'member_id' => $memberId,
            'community_id' => $communityId,
            'community_slug' => $communitySlug,
            'redirect_url' => $redirectUrl,
            'bluesky_verified' => $blueskyVerified,
            'needs_bluesky_link' => $isBlueskyInvite && !$blueskyVerified,
            'bluesky_connect_url' => $blueskyConnectUrl,
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
        $this->sendInvitationEmail(
            $email,
            'event',
            $eventName,
            $token,
            $inviterName,
            $sanitizedMessage,
            ['event_id' => $eventId]
        );

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
            (string)($guest['notes'] ?? ''),
            ['event_id' => $eventId]
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
        string $message = '',
        array $options = []
    ): bool {
        $url = $this->buildInvitationUrl($type, $token);
        $appName = (string)app_config('app.name', 'Our Community');
        $siteUrl = (string)app_config('app.url', '/');
        $fromName = $options['from_name'] ?? ($inviterName !== '' ? $inviterName : $appName);

        $eventTitle = $entityName;
        $eventDate = null;
        $eventTime = null;
        $venueInfo = null;
        $eventDescription = '';

        if ($type === 'event') {
            $eventId = $options['event_id'] ?? null;
            if (is_int($eventId) && $eventId > 0) {
                $details = $this->getEventDetailsForEmail($eventId);
                if ($details !== null) {
                    $eventTitle = $details['title'] ?? $eventTitle;
                    $eventDate = $details['event_date'] ?? null;
                    $eventTime = $details['event_time'] ?? null;
                    $venueInfo = $details['venue_info'] ?? null;
                    $eventDescription = $details['description'] ?? '';
                    if (!empty($details['host_name'])) {
                        $fromName = (string)$details['host_name'];
                    }
                }
            }
        }

        $subject = $type === 'community'
            ? "You've been invited to join {$entityName} on {$appName}"
            : "You're invited to {$entityName} on {$appName}";

        $variables = [
            'inviter_name' => $inviterName,
            'entity_name' => $entityName,
            'entity_type' => $type,
            'invitation_url' => $url,
            'personal_message' => $message,
            'subject' => $subject,
            'site_name' => $appName,
            'site_url' => $siteUrl,
            'from_name' => $fromName,
            'event_title' => $eventTitle,
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'venue_info' => $venueInfo,
            'event_description' => $eventDescription,
        ];

        return $this->mail->sendTemplate($email, 'invitation', $variables);
    }

    /**
     * Build invitation URL
     */
    private function buildInvitationUrl(string $type, string $token): string
    {
        $baseUrl = rtrim((string)app_config('app.url', ''), '/');
        if ($baseUrl === '') {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host;
        }

        return $type === 'community'
            ? "{$baseUrl}/invitation/accept?token={$token}"
            : "{$baseUrl}/rsvp/{$token}";
    }

    private function getEventDetailsForEmail(int $eventId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT e.id, e.title, e.event_date, e.event_time, e.venue_info, e.description,
                    u.display_name AS host_name
             FROM events e
             LEFT JOIN users u ON u.id = e.author_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Check if email has already been invited
     */
    private function isAlreadyInvited(string $type, int $entityId, string $email): bool
    {
        $normalizedEmail = strtolower(trim($email));
        if ($normalizedEmail === '') {
            return false;
        }

        if (!\str_starts_with($normalizedEmail, 'bsky:')) {
            $normalizedEmail = strtolower($this->sanitizer->email($email));
        }

        if ($normalizedEmail === '') {
            return false;
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM community_invitations
            WHERE community_id = ? AND LOWER(invited_email) = ? AND status = 'pending'
        ");
        $stmt->execute([$entityId, $normalizedEmail]);

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

        $sql = 'UPDATE community_invitations SET ' . implode(', ', $parts) . ' WHERE id = :id';
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
            'SELECT id, name, slug FROM communities WHERE id = :id LIMIT 1'
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
            "SELECT {$fieldList} FROM events WHERE id = :id LIMIT 1"
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
            "SELECT role FROM community_members WHERE community_id = :community_id AND user_id = :user_id LIMIT 1"
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
     * @param array<string,mixed> $data
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        $message = (string)($data['message'] ?? '');
        return [
            'success' => true,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * @param array<string,mixed> $data
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

    /**
     * Fetch event invitation context by guest RSVP token.
     *
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function getEventInvitationByToken(string $token): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) !== self::EVENT_TOKEN_LENGTH) {
            return $this->failure('Invalid RSVP token.', 400);
        }

        $guest = $this->eventGuests->findGuestByToken($token);
        if ($guest === null) {
            return $this->failure('RSVP invitation not found.', 404);
        }

        $event = [
            'id' => (int)$guest['event_id'],
            'title' => (string)($guest['event_title'] ?? ''),
            'slug' => (string)($guest['event_slug'] ?? ''),
            'event_date' => (string)($guest['event_date'] ?? ''),
            'event_time' => (string)($guest['event_time'] ?? ''),
            'venue_info' => (string)($guest['venue_info'] ?? ''),
            'description' => (string)($guest['event_description'] ?? ''),
            'featured_image' => (string)($guest['featured_image'] ?? ''),
            'allow_plus_ones' => (bool)((int)($guest['allow_plus_ones'] ?? 1) === 1),
            'max_guests' => (int)($guest['max_guests'] ?? 0),
            'guest_limit' => (int)($guest['guest_limit'] ?? 0),
        ];

        $guestData = [
            'id' => (int)$guest['id'],
            'email' => (string)$guest['email'],
            'name' => (string)$guest['name'],
            'phone' => (string)$guest['phone'],
            'status' => strtolower((string)$guest['status'] ?? 'pending'),
            'dietary_restrictions' => (string)($guest['dietary_restrictions'] ?? ''),
            'plus_one' => (int)($guest['plus_one'] ?? 0),
            'plus_one_name' => (string)($guest['plus_one_name'] ?? ''),
            'notes' => (string)($guest['notes'] ?? ''),
            'rsvp_date' => (string)($guest['rsvp_date'] ?? ''),
            'invitation_source' => (string)($guest['invitation_source'] ?? ''),
        ];

        return $this->success([
            'guest' => $guestData,
            'event' => $event,
            'token' => $token,
            'is_bluesky' => \str_starts_with(strtolower($guestData['email']), 'bsky:'),
        ]);
    }

    /**
     * Respond to an event invitation via RSVP token.
     *
     * @param array<string,mixed> $input
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function respondToEventInvitation(string $token, string $response, array $input): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) !== self::EVENT_TOKEN_LENGTH) {
            return $this->failure('Invalid RSVP token.', 400);
        }

        $response = strtolower(trim($response));
        $allowedResponses = ['yes', 'no', 'maybe'];
        if (!in_array($response, $allowedResponses, true)) {
            return $this->failure('Please choose a valid RSVP option.', 422);
        }

        $guestRecord = $this->eventGuests->findGuestByToken($token);
        if ($guestRecord === null) {
            return $this->failure('RSVP invitation not found.', 404);
        }

        $eventId = (int)$guestRecord['event_id'];
        $currentStatus = strtolower((string)($guestRecord['status'] ?? 'pending'));

        $statusMap = [
            'yes' => 'confirmed',
            'maybe' => 'maybe',
            'no' => 'declined',
        ];
        $targetStatus = $statusMap[$response];

        $maxGuests = (int)($guestRecord['max_guests'] ?? 0);
        if ($maxGuests <= 0) {
            $maxGuests = (int)($guestRecord['guest_limit'] ?? 0);
        }

        if ($targetStatus === 'confirmed' && $maxGuests > 0 && $currentStatus !== 'confirmed') {
            $confirmedCount = $this->eventGuests->countGuestsByStatus($eventId, 'confirmed');
            if ($confirmedCount >= $maxGuests) {
                return $this->failure('This event has reached its guest limit.', 409);
            }
        }

        $allowPlusOnes = (bool)((int)($guestRecord['allow_plus_ones'] ?? 1) === 1);
        $sanitizedName = $this->sanitizer->textField((string)($input['guest_name'] ?? $guestRecord['name'] ?? ''));
        $sanitizedPhone = $this->sanitizer->phoneNumber((string)($input['guest_phone'] ?? $guestRecord['phone'] ?? ''));
        $dietary = $this->sanitizer->textField((string)($input['dietary_restrictions'] ?? $guestRecord['dietary_restrictions'] ?? ''));
        $notes = $this->sanitizer->textarea((string)($input['guest_notes'] ?? $guestRecord['notes'] ?? ''));
        $plusOne = (int)($input['plus_one'] ?? $guestRecord['plus_one'] ?? 0);
        $plusOne = $allowPlusOnes ? max(0, min(1, $plusOne)) : 0;
        $plusOneName = $plusOne === 1
            ? $this->sanitizer->textField((string)($input['plus_one_name'] ?? $guestRecord['plus_one_name'] ?? ''))
            : '';

        if ($targetStatus !== 'declined' && $sanitizedName === '') {
            return $this->failure('Please provide your name so the host knows who is attending.', 422);
        }

        if ($plusOne === 1 && $plusOneName === '' && $targetStatus === 'confirmed') {
            return $this->failure('Please share your guest\'s name or remove the plus one.', 422);
        }

        $update = [
            'status' => $targetStatus,
            'name' => $sanitizedName,
            'phone' => $sanitizedPhone,
            'dietary_restrictions' => $dietary,
            'plus_one' => $plusOne,
            'plus_one_name' => $plusOneName,
            'notes' => $notes,
            'rsvp_date' => date('Y-m-d H:i:s'),
        ];

        $updated = $this->eventGuests->updateGuestByToken($token, $update);
        if (!$updated && $targetStatus !== $currentStatus) {
            return $this->failure('Unable to save your RSVP at this time.', 500);
        }

        $refreshed = $this->eventGuests->findGuestByToken($token);
        if ($refreshed === null) {
            return $this->failure('Unable to retrieve updated RSVP details.', 500);
        }

        $guestData = [
            'id' => (int)$refreshed['id'],
            'email' => (string)$refreshed['email'],
            'name' => (string)$refreshed['name'],
            'phone' => (string)$refreshed['phone'],
            'status' => strtolower((string)$refreshed['status'] ?? 'pending'),
            'dietary_restrictions' => (string)($refreshed['dietary_restrictions'] ?? ''),
            'plus_one' => (int)($refreshed['plus_one'] ?? 0),
            'plus_one_name' => (string)($refreshed['plus_one_name'] ?? ''),
            'notes' => (string)($refreshed['notes'] ?? ''),
            'rsvp_date' => (string)($refreshed['rsvp_date'] ?? ''),
            'invitation_source' => (string)($refreshed['invitation_source'] ?? ''),
        ];

        $event = [
            'id' => (int)$refreshed['event_id'],
            'title' => (string)($refreshed['event_title'] ?? ''),
            'slug' => (string)($refreshed['event_slug'] ?? ''),
            'event_date' => (string)($refreshed['event_date'] ?? ''),
            'event_time' => (string)($refreshed['event_time'] ?? ''),
            'venue_info' => (string)($refreshed['venue_info'] ?? ''),
            'description' => (string)($refreshed['event_description'] ?? ''),
            'featured_image' => (string)($refreshed['featured_image'] ?? ''),
            'allow_plus_ones' => (bool)((int)($refreshed['allow_plus_ones'] ?? 1) === 1),
        ];

        $statusMessage = match ($targetStatus) {
            'confirmed' => 'RSVP confirmed! We\'re excited to see you there.',
            'declined' => 'Thanks for letting the host know you can\'t make it.',
            'maybe' => 'We\'ve saved your “maybe” response. Feel free to update it anytime.',
            default => 'RSVP updated.',
        };

        return $this->success([
            'guest' => $guestData,
            'event' => $event,
            'token' => $token,
            'status' => $targetStatus,
            'message' => $statusMessage,
        ]);
    }

    /**
     * Store Bluesky DID from invitation for users without full OAuth verification.
     * This links the user to their invited Bluesky identity even with self-reported handles.
     */
    private function storePendingBlueskyDid(int $userId, string $did): void
    {
        if ($userId <= 0 || $did === '') {
            return;
        }

        $pdo = $this->database->pdo();

        // Check if user already has member_identities record
        $stmt = $pdo->prepare('
            SELECT id, verification_method, did
            FROM member_identities
            WHERE user_id = ?
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing !== false) {
            // Update existing record only if not already verified via OAuth
            $currentMethod = (string)($existing['verification_method'] ?? 'none');
            if ($currentMethod !== 'oauth') {
                $updateStmt = $pdo->prepare('
                    UPDATE member_identities
                    SET did = ?,
                        at_protocol_did = ?,
                        verification_method = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ');
                $newMethod = $currentMethod === 'self_reported' ? 'self_reported' : 'invitation_linked';
                $updateStmt->execute([$did, $did, $newMethod, $userId]);
            }
        } else {
            // Create new record for invitation-linked identity
            $insertStmt = $pdo->prepare('
                INSERT INTO member_identities
                (user_id, email, did, at_protocol_did, verification_method, is_verified, created_at, updated_at)
                SELECT ?, email, ?, ?, ?, 0, NOW(), NOW()
                FROM users
                WHERE id = ?
            ');
            $insertStmt->execute([$userId, $did, $did, 'invitation_linked', $userId]);
        }
    }
}
