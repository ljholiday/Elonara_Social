<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

final class UserInvitationService
{
    private const TOKEN_LENGTH = 32;
    private const EXPIRY_DAYS = 30;

    public function __construct(
        private Database $db,
        private AuthService $auth,
        private MailService $mail,
        private CircleService $circles,
        private SanitizerService $sanitizer
    ) {
    }

    /**
     * Send a user-to-user connection invitation.
     *
     * @return array{success: bool, message: string, invitation_id?: int}
     */
    public function sendInvitation(int $inviterId, string $inviteeEmail, string $message = ''): array
    {
        if ($inviterId <= 0) {
            return ['success' => false, 'message' => 'Invalid inviter.'];
        }

        $inviter = $this->auth->getUserById($inviterId);
        if ($inviter === null) {
            return ['success' => false, 'message' => 'Inviter not found.'];
        }

        $inviteeEmail = $this->sanitizer->email($inviteeEmail);
        if ($inviteeEmail === '') {
            return ['success' => false, 'message' => 'Valid email address required.'];
        }

        $inviteeUser = $this->auth->getUserByEmail($inviteeEmail);
        $inviteeId = $inviteeUser?->id ?? null;

        if ($inviteeId === $inviterId) {
            return ['success' => false, 'message' => 'You cannot invite yourself.'];
        }

        $message = trim($this->sanitizer->textField($message));

        if ($this->hasActivePendingInvitation($inviterId, $inviteeEmail)) {
            return ['success' => false, 'message' => 'You already have a pending invitation to this user.'];
        }

        if ($inviteeId !== null && $this->areAlreadyConnected($inviterId, $inviteeId)) {
            return ['success' => false, 'message' => 'You are already connected to this user.'];
        }

        $token = $this->generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRY_DAYS . ' days'));

        $sql = "INSERT INTO user_invitations (inviter_id, invitee_email, invitee_id, token, message, status, expires_at)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)";

        try {
            $stmt = $this->db->pdo()->prepare($sql);
            $stmt->execute([
                $inviterId,
                $inviteeEmail,
                $inviteeId,
                $token,
                $message,
                $expiresAt,
            ]);

            $invitationId = (int)$this->db->pdo()->lastInsertId();

            $this->sendInvitationEmail($inviter, $inviteeEmail, $token, $message);

            return [
                'success' => true,
                'message' => 'Invitation sent successfully.',
                'invitation_id' => $invitationId,
            ];
        } catch (\PDOException $e) {
            file_put_contents(__DIR__ . '/../../debug.log', date('Y-m-d H:i:s') . " UserInvitationService::sendInvitation failed: " . $e->getMessage() . "\n", FILE_APPEND);
            return ['success' => false, 'message' => 'Failed to send invitation.'];
        }
    }

    /**
     * Accept a user invitation and create bidirectional user_link.
     *
     * @return array{success: bool, message: string, redirect_url?: string}
     */
    public function acceptInvitation(string $token, int $userId): array
    {
        $invitation = $this->getInvitationByToken($token);

        if ($invitation === null) {
            return ['success' => false, 'message' => 'Invalid or expired invitation.'];
        }

        $inviterId = (int)$invitation['inviter_id'];
        $inviteeEmail = strtolower((string)$invitation['invitee_email']);
        $expiresAt = $invitation['expires_at'];

        if (strtotime((string)$expiresAt) < time()) {
            $this->updateInvitationStatus((int)$invitation['id'], 'expired');
            return ['success' => false, 'message' => 'This invitation has expired.'];
        }

        $user = $this->auth->getUserById($userId);
        if ($user === null) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $userEmail = strtolower((string)$user->email);

        if ($userEmail !== $inviteeEmail) {
            return ['success' => false, 'message' => 'This invitation was sent to a different email address.'];
        }

        if ($this->areAlreadyConnected($inviterId, $userId)) {
            $this->updateInvitationStatus((int)$invitation['id'], 'accepted', $userId);
            return [
                'success' => true,
                'message' => 'You are already connected to this user.',
                'redirect_url' => '/connections',
            ];
        }

        $linkCreated = $this->circles->createLink($inviterId, $userId);

        if (!$linkCreated) {
            return ['success' => false, 'message' => 'Failed to create connection.'];
        }

        $this->updateInvitationStatus((int)$invitation['id'], 'accepted', $userId);

        return [
            'success' => true,
            'message' => 'Connection accepted successfully!',
            'redirect_url' => '/connections',
        ];
    }

    /**
     * Reject a user invitation.
     *
     * @return array{success: bool, message: string}
     */
    public function rejectInvitation(string $token, int $userId): array
    {
        $invitation = $this->getInvitationByToken($token);

        if ($invitation === null) {
            return ['success' => false, 'message' => 'Invalid invitation.'];
        }

        $inviteeEmail = strtolower((string)$invitation['invitee_email']);
        $user = $this->auth->getUserById($userId);

        if ($user === null || strtolower((string)$user->email) !== $inviteeEmail) {
            return ['success' => false, 'message' => 'This invitation was sent to a different email address.'];
        }

        $this->updateInvitationStatus((int)$invitation['id'], 'rejected', $userId);

        return ['success' => true, 'message' => 'Invitation rejected.'];
    }

    /**
     * List pending invitations for a user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPendingForUser(int $userId): array
    {
        $user = $this->auth->getUserById($userId);
        if ($user === null) {
            return [];
        }

        $userEmail = strtolower((string)$user->email);

        $sql = "SELECT
                    ui.id,
                    ui.inviter_id,
                    ui.message,
                    ui.created_at,
                    ui.expires_at,
                    u.display_name AS inviter_name,
                    u.email AS inviter_email
                FROM user_invitations ui
                LEFT JOIN users u ON ui.inviter_id = u.id
                WHERE LOWER(ui.invitee_email) = ?
                  AND ui.status = 'pending'
                  AND ui.expires_at > NOW()
                ORDER BY ui.created_at DESC";

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userEmail]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get invitation by token.
     *
     * @return array<string, mixed>|null
     */
    private function getInvitationByToken(string $token): ?array
    {
        $sql = "SELECT id, inviter_id, invitee_email, invitee_id, message, status, expires_at
                FROM user_invitations
                WHERE token = ? AND status = 'pending'
                LIMIT 1";

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$token]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Update invitation status.
     */
    private function updateInvitationStatus(int $invitationId, string $status, ?int $userId = null): void
    {
        $sql = "UPDATE user_invitations
                SET status = ?, responded_at = NOW()";

        $params = [$status];

        if ($userId !== null) {
            $sql .= ", invitee_id = ?";
            $params[] = $userId;
        }

        $sql .= " WHERE id = ?";
        $params[] = $invitationId;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Check if there's an active pending invitation.
     */
    private function hasActivePendingInvitation(int $inviterId, string $inviteeEmail): bool
    {
        $sql = "SELECT COUNT(*) FROM user_invitations
                WHERE inviter_id = ? AND LOWER(invitee_email) = ? AND status = 'pending' AND expires_at > NOW()";

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$inviterId, strtolower($inviteeEmail)]);

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Check if two users are already connected.
     */
    private function areAlreadyConnected(int $userId1, int $userId2): bool
    {
        $sql = "SELECT COUNT(*) FROM user_links WHERE user_id = ? AND peer_id = ?";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId1, $userId2]);

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Generate cryptographically secure token.
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Send invitation email.
     */
    private function sendInvitationEmail(object $inviter, string $inviteeEmail, string $token, string $message): void
    {
        $inviterName = (string)($inviter->display_name ?? $inviter->email ?? 'Someone');
        $acceptUrl = $_ENV['APP_URL'] . '/invitations/user/' . $token;

        $subject = $inviterName . ' wants to connect with you on Elonara Social';

        $body = "Hello,\n\n";
        $body .= $inviterName . " has invited you to connect on Elonara Social.\n\n";

        if ($message !== '') {
            $body .= "Message: \"" . $message . "\"\n\n";
        }

        $body .= "Click here to accept: " . $acceptUrl . "\n\n";
        $body .= "This invitation will expire in " . self::EXPIRY_DAYS . " days.\n\n";
        $body .= "Best regards,\nElonara Social";

        $this->mail->send($inviteeEmail, $subject, $body);
    }
}
