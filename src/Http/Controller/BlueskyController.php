<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\BlueskyService;
use App\Services\SecurityService;
use App\Services\BlueskyOAuthService;
use App\Services\InvitationService;

final class BlueskyController
{
    public function __construct(
        private AuthService $auth,
        private BlueskyService $bluesky,
        private SecurityService $security,
        private BlueskyOAuthService $oauth,
        private InvitationService $invitations
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
     * Redirect the user to Bluesky OAuth.
     *
     * @return array{redirect:string}
     */
    public function startOAuth(): array
    {
        if (!$this->oauth->isEnabled()) {
            $_SESSION['flash_error'] = 'Bluesky OAuth is not enabled yet.';
            return ['redirect' => '/profile/edit'];
        }

        $request = $this->request();
        $redirectTo = $this->sanitizeRedirect((string)$request->query('redirect', '')) ?: '/profile/edit';
        $context = [
            'redirect_to' => $redirectTo,
            'invite_token' => (string)$request->query('invite_token', ''),
            'event_token' => (string)$request->query('event_token', ''),
            'reauthorize' => $request->query('reauthorize', '') === '1',
        ];

        if ($context['invite_token'] !== '') {
            $_SESSION['pending_invitation_token'] = $context['invite_token'];
        }

        $result = $this->oauth->beginAuthorization($context);

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'] ?? 'Unable to contact Bluesky.';
            return ['redirect' => $redirectTo];
        }

        return ['redirect' => $result['redirect']];
    }

    /**
     * Handle Bluesky OAuth callback response.
     *
     * @return array{redirect:string}
     */
    public function handleOAuthCallback(): array
    {
        $request = $this->request();
        $result = $this->oauth->handleCallback($request->allQuery());

        if (!$result['success']) {
            $_SESSION['flash_error'] = $result['message'] ?? 'Unable to complete Bluesky authorization.';
            return ['redirect' => '/profile/edit'];
        }

        $redirect = $result['redirect'] ?? '/profile/edit';
        $userId = (int)($result['user_id'] ?? 0);

        $inviteToken = (string)($result['invite_token'] ?? '');
        if ($inviteToken !== '' && $userId > 0) {
            $acceptance = $this->invitations->acceptCommunityInvitation($inviteToken, $userId);
            if ($acceptance['success']) {
                $data = $acceptance['data'];
                $redirect = (string)($data['redirect_url'] ?? $redirect);
                if (isset($data['message'])) {
                    $_SESSION['flash_success'] = $data['message'];
                }
            }
        }

        return ['redirect' => $redirect];
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

    private function sanitizeRedirect(string $redirect): string
    {
        $redirect = trim($redirect);
        if ($redirect === '') {
            return '';
        }

        if (str_starts_with($redirect, 'http')) {
            $appUrl = (string)app_config('app.url', '');
            if ($appUrl === '' || !str_starts_with($redirect, $appUrl)) {
                return '';
            }
            $redirectPath = parse_url($redirect, PHP_URL_PATH) ?? '';
            $query = parse_url($redirect, PHP_URL_QUERY);
            return $redirectPath . ($query ? '?' . $query : '');
        }

        if (!str_starts_with($redirect, '/')) {
            $redirect = '/' . $redirect;
        }

        return $redirect;
    }
}
