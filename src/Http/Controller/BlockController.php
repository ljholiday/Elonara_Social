<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\BlockService;
use App\Services\SecurityService;

require_once dirname(__DIR__, 3) . '/templates/_helpers.php';

final class BlockController
{
    public function __construct(
        private BlockService $blockService,
        private AuthService $auth,
        private SecurityService $security
    ) {
    }

    /**
     * Block a user (API endpoint)
     *
     * @return array{status:int, body:array<string,mixed>}
     */
    public function block(): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('User not authenticated', 401);
        }

        if (!$this->verifyNonce($nonce, 'app_nonce', $viewerId)) {
            return $this->error('Security verification failed', 403);
        }

        $blockedUserId = (int)$request->input('blocked_user_id', 0);
        if ($blockedUserId <= 0) {
            return $this->error('Invalid user ID', 422);
        }

        try {
            $success = $this->blockService->blockUser($viewerId, $blockedUserId);

            if ($success) {
                return $this->success([
                    'message' => 'User blocked successfully',
                    'blocked_user_id' => $blockedUserId
                ]);
            }

            return $this->error('User is already blocked', 422);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Unblock a user (API endpoint)
     *
     * @return array{status:int, body:array<string,mixed>}
     */
    public function unblock(): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('User not authenticated', 401);
        }

        if (!$this->verifyNonce($nonce, 'app_nonce', $viewerId)) {
            return $this->error('Security verification failed', 403);
        }

        $blockedUserId = (int)$request->input('blocked_user_id', 0);
        if ($blockedUserId <= 0) {
            return $this->error('Invalid user ID', 422);
        }

        $success = $this->blockService->unblockUser($viewerId, $blockedUserId);

        if ($success) {
            return $this->success([
                'message' => 'User unblocked successfully',
                'unblocked_user_id' => $blockedUserId
            ]);
        }

        return $this->error('User was not blocked', 422);
    }

    /**
     * List blocked users (for settings page)
     *
     * @return array{blocked_users:array<int,array<string,mixed>>, user:object|null, viewer:object|null}
     */
    public function listBlocked(): array
    {
        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return [
                'blocked_users' => [],
                'user' => null,
                'viewer' => null
            ];
        }

        $blockedUsers = $this->blockService->getBlockedUsers($viewerId);
        $viewer = $this->auth->getCurrentUser();

        return [
            'blocked_users' => $blockedUsers,
            'user' => $viewer,
            'viewer' => $viewer
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = app_service('http.request');
        return $request;
    }

    private function verifyNonce(string $nonce, string $action, int $userId = 0): bool
    {
        if ($userId === 0) {
            $user = $this->auth->getCurrentUser();
            $userId = isset($user->id) ? (int)$user->id : 0;
        }
        return $this->security->verifyNonce($nonce, $action, $userId);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int, body:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => $data,
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function error(string $message, int $status = 400): array
    {
        return [
            'status' => $status,
            'body' => ['error' => $message],
        ];
    }
}
