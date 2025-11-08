<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\BlueskyInvitationService;
use App\Services\SecurityService;

final class BlueskyInvitationController
{
    public function __construct(
        private AuthService $auth,
        private SecurityService $security,
        private BlueskyInvitationService $blueskyInvites
    ) {
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function inviteEvent(int $eventId): array
    {
        $request = $this->request();
        $payload = $this->jsonPayload();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)($payload['nonce'] ?? '');
        }

        if (!$this->verifyNonce($nonce, 'app_bluesky_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $followers = isset($payload['follower_dids']) && is_array($payload['follower_dids'])
            ? $payload['follower_dids']
            : [];

        $result = $this->blueskyInvites->inviteFollowersToEvent($eventId, $viewerId, $followers);
        return $this->fromServiceResult($result, $viewerId);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function inviteCommunity(int $communityId): array
    {
        $request = $this->request();
        $payload = $this->jsonPayload();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)($payload['nonce'] ?? '');
        }

        if (!$this->verifyNonce($nonce, 'app_bluesky_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $followers = isset($payload['follower_dids']) && is_array($payload['follower_dids'])
            ? $payload['follower_dids']
            : [];

        $result = $this->blueskyInvites->inviteFollowersToCommunity($communityId, $viewerId, $followers);
        return $this->fromServiceResult($result, $viewerId);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function cancelCommunity(int $communityId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }

        if (!$this->verifyNonce($nonce, 'app_bluesky_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->blueskyInvites->cancelCommunityInvitation($communityId, $invitationId, $viewerId);
        return $this->fromServiceResult($result, $viewerId);
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

        $viewerId = (int)($this->auth->currentUserId() ?? 0);
        return $this->security->verifyNonce($nonce, $action, $viewerId);
    }

    private function createActionNonce(int $viewerId): string
    {
        return $this->security->createNonce('app_bluesky_action', $viewerId);
    }

    /**
     * @return array<string,mixed>
     */
    private function jsonPayload(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function fromServiceResult(array $result, ?int $viewerId = null): array
    {
        if (!$result['success']) {
            return $this->error($result['message'], $result['status'], $result['data'] ?? []);
        }

        $data = $result['data'];
        if ($viewerId !== null) {
            $data['nonce'] = $this->createActionNonce($viewerId);
        }

        return [
            'status' => $result['status'],
            'body' => [
                'success' => true,
                'message' => $result['message'] ?? '',
                'data' => $data,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function error(string $message, int $status, array $data = []): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
                'data' => $data,
            ],
        ];
    }
}
