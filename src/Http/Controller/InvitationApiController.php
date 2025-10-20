<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Database\Database;
use App\Http\Request;
use App\Services\AuthService;
use App\Services\InvitationService;
use App\Services\CommunityMemberService;
use App\Services\SecurityService;

require_once dirname(__DIR__, 3) . '/templates/_helpers.php';

final class InvitationApiController
{
    public function __construct(
        private Database $database,
        private AuthService $auth,
        private InvitationService $invitations,
        private SecurityService $security,
        private CommunityMemberService $communityMembers
    ) {
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function sendCommunity(int $communityId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'app_community_action')) {
            $this->logNonceFailure('community-send', $communityId, 0, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $email = trim((string)$request->input('email', ''));
        $message = trim((string)$request->input('message', ''));
        $result = $this->invitations->sendCommunityInvitation($communityId, $viewerId, $email, $message);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $data['nonce'] = $this->createActionNonce('app_community_action');

        return $this->success($data, $result['status']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function listCommunity(int $communityId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }

        if (!$this->verifyNonce($nonce, 'app_community_action')) {
            $this->logNonceFailure('community-list', $communityId, 0, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->listCommunityInvitations($communityId, $viewerId);

        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $data['nonce'] = $this->createActionNonce('app_community_action');

        return $this->success($data, $result['status']);
    }

    public function listCommunityMembers(int $communityId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }

        if (!$this->verifyNonce($nonce, 'app_community_action')) {
            $this->logNonceFailure('community-members', $communityId, 0, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->error('You do not have permission to view members.', 403);
        }

        return $this->success([
            'html' => $this->renderCommunityMembers($communityId, $viewerId),
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function deleteCommunity(int $communityId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'app_community_action')) {
            $this->logNonceFailure('community-delete', $communityId, $invitationId, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->deleteCommunityInvitation($communityId, $invitationId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $data['nonce'] = $this->createActionNonce('app_community_action');

        return $this->success($data, $result['status']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function resendCommunity(int $communityId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'app_community_action')) {
            $this->logNonceFailure('community-resend', $communityId, $invitationId, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->resendCommunityInvitation($communityId, $invitationId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $data['nonce'] = $this->createActionNonce('app_community_action');

        return $this->success($data, $result['status']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function sendEvent(int $eventId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'app_event_action')) {
            $this->logNonceFailure('event-send', $eventId, 0, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $email = trim((string)$request->input('email', ''));
        $message = trim((string)$request->input('message', ''));
        $result = $this->invitations->sendEventInvitation($eventId, $viewerId, $email, $message);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $guestRecords = $data['guest_records'] ?? null;
        unset($data['guest_records']);
        $data['html'] = $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null);
        $data['nonce'] = $this->createActionNonce('app_event_action');

        return $this->success($data, $result['status']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function listEvent(int $eventId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'app_event_action')) {
            $this->logNonceFailure('event-list', $eventId, 0, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->listEventInvitations($eventId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $guestRecords = $result['data']['guest_records'] ?? [];

        return $this->success([
            'invitations' => $result['data']['invitations'],
            'html' => $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null),
            'nonce' => $this->createActionNonce('app_event_action'),
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function deleteEvent(int $eventId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'app_event_action')) {
            $this->logNonceFailure('event-delete', $eventId, $invitationId, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->deleteEventInvitation($eventId, $invitationId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $guestRecords = $data['guest_records'] ?? null;
        unset($data['guest_records']);
        $data['html'] = $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null);
        $data['nonce'] = $this->createActionNonce('app_event_action');

        return $this->success($data, $result['status']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function resendEvent(int $eventId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }

        if (!$this->verifyNonce($nonce, 'app_event_action')) {
            $this->logNonceFailure('event-resend', $eventId, $invitationId, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->resendEventInvitation($eventId, $invitationId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $guestRecords = $data['guest_records'] ?? null;
        unset($data['guest_records']);
        $data['html'] = $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null);
        $data['nonce'] = $this->createActionNonce('app_event_action');

        return $this->success($data, $result['status']);
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = app_service('http.request');
        return $request;
    }

    private function verifyNonce(string $nonce, string $action): bool
    {
        if ($nonce === '') {
            return false;
        }

        $userId = (int)($this->auth->currentUserId() ?? 0);
        return $this->security->verifyNonce($nonce, $action, $userId);
    }

    private function canManageCommunity(int $communityId, int $viewerId, array $roles): bool
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT role FROM community_members WHERE community_id = :community_id AND user_id = :user_id LIMIT 1"
        );
        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $viewerId,
        ]);
        $role = $stmt->fetchColumn();

        if ($role === false) {
            return false;
        }

        if (in_array($role, $roles, true)) {
            return true;
        }

        return $this->auth->currentUserCan('manage_options');
    }

    public function updateCommunityMemberRole(int $communityId, int $memberId): array
    {
        $payload = $this->jsonBody();
        $nonce = (string)($payload['nonce'] ?? '');
        if (!$this->verifyNonce($nonce, 'app_community_action')) {
            $this->logNonceFailure('community-role', $communityId, $memberId, $nonce);
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $role = strtolower((string)($payload['role'] ?? ''));
        if (!in_array($role, ['member', 'moderator', 'admin'], true)) {
            return $this->error('Invalid role.', 422);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin'])) {
            return $this->error('You do not have permission to change roles.', 403);
        }

        try {
            $this->communityMembers->updateMemberRole($communityId, $memberId, $role);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->success([
            'message' => 'Member role updated successfully.',
            'html' => $this->renderCommunityMembers($communityId, $viewerId),
            'nonce' => $this->createActionNonce('app_community_action'),
        ]);
    }

    public function removeCommunityMember(int $communityId, int $memberId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }

        if (!$this->verifyNonce($nonce, 'app_community_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin'])) {
            return $this->error('You do not have permission to remove members.', 403);
        }

        try {
            $this->communityMembers->removeMember($communityId, $memberId);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }

        return $this->success([
            'message' => 'Member removed successfully.',
            'html' => $this->renderCommunityMembers($communityId, $viewerId),
            'nonce' => $this->createActionNonce('app_community_action'),
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function accept(): array
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        if ($viewerId <= 0) {
            return $this->error('You must be logged in to accept invitations', 401);
        }

        $request = $this->request();
        $token = trim((string)$request->input('token', ''));

        if ($token === '') {
            return $this->error('Invitation token is required', 400);
        }

        return $this->completeCommunityAcceptance($token, $viewerId);
    }

    /**
     * Handle community invitation acceptance from a direct link.
     *
     * @return array{status:int, body:array<string,mixed>}|array{status:int,redirect:string}
     */
    public function acceptToken(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return $this->error('Invitation token is required.', 400);
        }

        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        if ($viewerId <= 0) {
            $redirect = '/auth/login?redirect_to=' . rawurlencode('/invitation/accept?token=' . rawurlencode($token));
            return [
                'status' => 302,
                'redirect' => $redirect,
                'body' => [
                    'success' => false,
                    'message' => 'Please sign in to accept the invitation.',
                ],
            ];
        }

        return $this->completeCommunityAcceptance($token, $viewerId);
    }

    /**
     * Finalize acceptance logic used by both API and direct link flows.
     */
    private function completeCommunityAcceptance(string $token, int $viewerId): array
    {
        $result = $this->invitations->acceptCommunityInvitation($token, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $data['nonce'] = $this->createActionNonce('app_community_action');

        return $this->success($data, $result['status']);
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function renderCommunityMembers(int $communityId, int $viewerId): string
    {
        $members = $this->communityMembers->listMembers($communityId);
        $viewerRole = $viewerId > 0 ? $this->communityMembers->getMemberRole($communityId, $viewerId) : null;

        if (!$members) {
            return '<tr><td colspan="4" class="app-text-center app-text-muted">No members yet.</td></tr>';
        }

        ob_start();
        foreach ($members as $member) {
            $memberId = (int)($member['id'] ?? 0);
            $userId = (int)($member['user_id'] ?? 0);
            $role = (string)($member['role'] ?? 'member');
            $displayName = (string)($member['display_name'] ?? $member['email'] ?? 'Member');
            $email = (string)($member['email'] ?? '');
            $joinedAt = (string)($member['joined_at'] ?? '');
            $isSelf = $userId === $viewerId;

            echo '<tr id="member-row-' . htmlspecialchars((string)$memberId) . '">';
            echo '<td>';
            echo '<div class="app-flex app-gap-2"><div class="app-avatar"></div>';
            echo '<div><strong>' . htmlspecialchars($displayName) . '</strong></div></div>';
            echo '</td>';

            echo '<td>' . htmlspecialchars($email) . '</td>';
            echo '<td>' . ($joinedAt !== '' ? htmlspecialchars(date('M j, Y', strtotime($joinedAt))) : '-') . '</td>';

            echo '<td><div class="app-flex app-gap-2">';
            if ($isSelf) {
                echo '<span class="app-text-muted app-text-sm">You</span>';
            } else {
                if ($viewerRole === 'admin' || $this->auth->currentUserCan('manage_options')) {
                    echo '<select class="app-form-input app-form-input-sm" onchange="changeMemberRole(' . htmlspecialchars((string)$memberId) . ', this.value, ' . htmlspecialchars((string)$communityId) . ')">';
                    echo '<option value="member"' . ($role === 'member' ? ' selected' : '') . '>Member</option>';
                    echo '<option value="moderator"' . ($role === 'moderator' ? ' selected' : '') . '>Moderator</option>';
                    echo '<option value="admin"' . ($role === 'admin' ? ' selected' : '') . '>Admin</option>';
                    echo '</select>';
                    $jsName = json_encode($displayName);
                    echo '<button class="app-btn app-btn-sm app-btn-danger" onclick="removeMember(' . htmlspecialchars((string)$memberId) . ', ' . $jsName . ', ' . htmlspecialchars((string)$communityId) . ')">Remove</button>';
                } else {
                    echo '<span class="app-badge app-badge-' . ($role === 'admin' ? 'primary' : 'secondary') . '">' . htmlspecialchars(ucfirst($role)) . '</span>';
                }
            }
            echo '</div></td>';
            echo '</tr>';
        }

        return (string)ob_get_clean();
    }

    private function createActionNonce(string $action): string
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        return $this->security->createNonce($action, $viewerId);
    }

    private function logNonceFailure(string $context, int $entityId, int $invitationId, string $nonce): void
    {
        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        $log = sprintf(
            '[%s] nonce failure: context=%s entity=%d invitation=%d viewer=%d nonce=%s',
            date('Y-m-d H:i:s'),
            $context,
            $entityId,
            $invitationId,
            $viewerId,
            $nonce
        );
        file_put_contents(dirname(__DIR__, 3) . '/debug.log', $log . PHP_EOL, FILE_APPEND);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        $message = (string)($data['message'] ?? '');
        return [
            'status' => $status,
            'body' => [
                'success' => true,
                'message' => $message,
                'data' => $data,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }

    private function renderEventGuests(int $eventId, ?array $preloadedGuests = null): string
    {
        $guests = $preloadedGuests ?? $this->invitations->getEventGuests($eventId);

        if ($guests === []) {
            return '<div class="app-text-center app-text-muted">No RSVP invitations sent yet.</div>';
        }

        ob_start();
        foreach ($guests as $guest) {
            $status = (string)($guest['status'] ?? 'pending');
            $statusClass = match ($status) {
                'confirmed' => 'success',
                'declined' => 'danger',
                'maybe' => 'warning',
                default => 'secondary',
            };
            $statusLabel = match ($status) {
                'confirmed' => 'Confirmed',
                'declined' => 'Declined',
                'maybe' => 'Maybe',
                default => 'Pending',
            };
            $invitationUrl = $this->buildEventInvitationUrl((string)($guest['rsvp_token'] ?? ''));

            echo '<div class="app-invitation-item" id="guest-' . htmlspecialchars((string)($guest['id'] ?? '')) . '">';
            echo '<div class="app-invitation-badges">';
            echo '<span class="app-badge app-badge-' . $statusClass . '">' . htmlspecialchars($statusLabel) . '</span>';
            $source = (string)($guest['invitation_source'] ?? 'direct');
            $sourceLabel = ucfirst($source);
            echo '<span class="app-badge app-badge-secondary">' . htmlspecialchars($sourceLabel) . '</span>';
            echo '</div>';

            echo '<div class="app-invitation-details">';
            echo '<h4>' . htmlspecialchars((string)($guest['email'] ?? '')) . '</h4>';
            if (!empty($guest['name'])) {
                echo '<div class="app-text-muted">' . htmlspecialchars((string)$guest['name']) . '</div>';
            }
            if (!empty($guest['rsvp_date'])) {
                echo '<div class="app-text-muted">Invited on ' . htmlspecialchars(date('M j, Y', strtotime((string)$guest['rsvp_date']))) . '</div>';
            }
            if (!empty($guest['dietary_restrictions'])) {
                echo '<div class="app-text-muted"><strong>Dietary:</strong> ' . htmlspecialchars((string)$guest['dietary_restrictions']) . '</div>';
            }
            if (!empty($guest['notes'])) {
                echo '<div class="app-text-muted"><em>' . htmlspecialchars((string)$guest['notes']) . '</em></div>';
            }
            echo '</div>';

            echo '<div class="app-invitation-actions">';
            echo '<button type="button" class="app-btn app-btn-sm app-btn-secondary" onclick="copyInvitationUrl(' . json_encode($invitationUrl) . ')">Copy Link</button>';
            if (in_array($status, ['pending', 'maybe'], true)) {
                echo '<button type="button" class="app-btn app-btn-sm app-btn-secondary resend-event-invitation" data-invitation-id="' . htmlspecialchars((string)($guest['id'] ?? '')) . '" data-invitation-action="resend">Resend Email</button>';
            }
            if ($status === 'pending') {
                echo '<button type="button" class="app-btn app-btn-sm app-btn-danger cancel-event-invitation" data-invitation-id="' . htmlspecialchars((string)($guest['id'] ?? '')) . '" data-invitation-action="cancel">Remove</button>';
            }
            echo '</div>';
            echo '</div>';
        }

        return (string)ob_get_clean();
    }

    private function buildEventInvitationUrl(string $token): string
    {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return sprintf('%s://%s/rsvp/%s', $scheme, $host, $token);
    }

}
