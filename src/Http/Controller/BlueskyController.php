<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\BlueskyService;
use App\Services\SecurityService;

final class BlueskyController
{
    public function __construct(
        private AuthService $auth,
        private BlueskyService $bluesky,
        private SecurityService $security
    ) {
    }

    /**
     * Handle connecting a Bluesky account for the current user.
     *
     * @return array{redirect: string}
     */
    public function connect(): array
    {
        $request = $this->request();
        $currentUser = $this->auth->getCurrentUser();

        if ($currentUser === null) {
            $_SESSION['flash_error'] = 'You must be logged in to connect Bluesky';
            return ['redirect' => '/auth'];
        }

        $userId = (int)$currentUser->id;
        $nonce = (string)$request->input('nonce', '');
        if (!$this->security->verifyNonce($nonce, 'app_nonce', $userId)) {
            $_SESSION['flash_error'] = 'Security verification failed';
            return ['redirect' => '/profile/edit'];
        }

        $identifier = trim((string)$request->input('identifier', ''));
        $password = trim((string)$request->input('password', ''));

        if ($identifier === '' || $password === '') {
            $_SESSION['flash_error'] = 'Bluesky handle and app password are required';
            return ['redirect' => '/profile/edit'];
        }

        $sessionResult = $this->bluesky->createSession($identifier, $password);
        if (!$sessionResult['success']) {
            $_SESSION['flash_error'] = $sessionResult['message'];
            return ['redirect' => '/profile/edit'];
        }

        $stored = $this->bluesky->storeCredentials(
            $userId,
            $sessionResult['did'],
            $sessionResult['handle'],
            $sessionResult['accessJwt'],
            $sessionResult['refreshJwt']
        );

        if (!$stored) {
            $_SESSION['flash_error'] = 'Failed to store Bluesky credentials';
            return ['redirect' => '/profile/edit'];
        }

        $this->bluesky->syncFollowers($userId);

        $_SESSION['flash_success'] = 'Bluesky account connected successfully!';
        $logFile = dirname(__DIR__, 3) . '/debug.log';
        @file_put_contents(
            $logFile,
            date('[Y-m-d H:i:s] ') . "Bluesky connected - flash_success set\n",
            FILE_APPEND
        );

        return ['redirect' => '/profile/edit'];
    }

    /**
     * Disconnect the current user's Bluesky account.
     *
     * @return array{redirect: string}
     */
    public function disconnect(): array
    {
        $request = $this->request();
        $currentUser = $this->auth->getCurrentUser();

        if ($currentUser === null) {
            $_SESSION['flash_error'] = 'You must be logged in';
            return ['redirect' => '/auth'];
        }

        $userId = (int)$currentUser->id;
        $nonce = (string)$request->input('nonce', '');
        if (!$this->security->verifyNonce($nonce, 'app_nonce', $userId)) {
            $_SESSION['flash_error'] = 'Security verification failed';
            return ['redirect' => '/profile/edit'];
        }

        $this->bluesky->disconnectAccount($userId);

        $_SESSION['flash_success'] = 'Bluesky account disconnected';
        return ['redirect' => '/profile/edit'];
    }

    /**
     * Trigger a follower sync for the current user.
     *
     * @return array{status:int, body:array<string,mixed>}
     */
    public function syncFollowers(): array
    {
        $request = $this->request();
        $currentUser = $this->auth->getCurrentUser();

        if ($currentUser === null) {
            return $this->jsonError('Not authenticated', 401);
        }

        $userId = (int)$currentUser->id;
        $nonce = (string)$request->input('nonce', '');

        if ($nonce === '') {
            $payload = $this->jsonPayload();
            $nonce = (string)($payload['nonce'] ?? '');
        }

        if (!$this->security->verifyNonce($nonce, 'app_bluesky_action', $userId)) {
            return $this->jsonError('Security verification failed', 403);
        }

        $result = $this->bluesky->syncFollowers($userId);
        $status = $result['success'] ? 200 : 400;

        return [
            'status' => $status,
            'body' => array_merge($result, [
                'nonce' => $this->createActionNonce($userId),
            ]),
        ];
    }

    /**
     * Return cached Bluesky followers for the current user.
     *
     * @return array{status:int, body:array<string,mixed>}
     */
    public function followers(): array
    {
        $currentUser = $this->auth->getCurrentUser();

        if ($currentUser === null) {
            return $this->jsonError('Not authenticated', 401);
        }

        $result = $this->bluesky->getCachedFollowers((int)$currentUser->id);
        if (!$result['success']) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to load followers',
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => array_merge($result, [
                'nonce' => $this->createActionNonce((int)$currentUser->id),
            ]),
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = app_service('http.request');
        return $request;
    }

    private function createActionNonce(int $userId): string
    {
        return $this->security->createNonce('app_bluesky_action', $userId);
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
     * @param string $message
     * @param int $status
     * @return array{status:int, body:array<string,mixed>}
     */
    private function jsonError(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }
}
