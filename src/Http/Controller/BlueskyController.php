<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\BlueskyService;
use App\Services\SecurityService;
use App\Services\BlueskyOAuthService;
use App\Services\InvitationService;
use App\Services\PendingInviteSessionStore;

final class BlueskyController
{
    public function __construct(
        private AuthService $auth,
        private BlueskyService $bluesky,
        private SecurityService $security,
        private BlueskyOAuthService $oauth,
        private InvitationService $invitations,
        private PendingInviteSessionStore $pendingInvites
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
            'invite_channel' => (string)$request->query('invite_channel', ''),
        ];

        if ($context['invite_channel'] === '' && $context['event_token'] !== '') {
            $context['invite_channel'] = 'event';
        } elseif ($context['invite_channel'] === '' && $context['invite_token'] !== '') {
            $context['invite_channel'] = 'community';
        }

        if ($context['invite_channel'] !== null && $context['invite_channel'] !== '') {
            $this->rememberPendingInviteContext($context, $redirectTo);
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

        $pending = $this->pendingInvites->pop();
        $redirect = $pending['redirect'] ?? ($result['redirect'] ?? '/profile/edit');
        $userId = (int)($result['user_id'] ?? 0);

        $inviteToken = (string)($pending['invite_token'] ?? ($result['invite_token'] ?? ''));
        $eventToken = (string)($pending['event_token'] ?? ($result['event_token'] ?? ''));
        $inviteChannel = (string)($pending['channel'] ?? ($result['invite_channel'] ?? ''));

        if ($userId > 0 && $inviteChannel !== '') {
            $acceptance = $this->completePendingInvite($inviteChannel, $inviteToken, $eventToken, $userId);
            if ($acceptance !== null) {
                $this->logInviteAcceptanceResult($inviteChannel, $userId, $acceptance);
                if ($acceptance['success']) {
                    $data = $acceptance['data'] ?? [];
                    $redirect = (string)($data['redirect_url'] ?? $redirect);
                    if (isset($data['message'])) {
                        $_SESSION['flash_success'] = (string)$data['message'];
                    }
                } else {
                    $_SESSION['flash_error'] = $acceptance['message'] ?? 'Unable to accept invitation.';
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
        $status = $result['success'] ? 200 : (($result['needs_reauth'] ?? false) ? 409 : 400);

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

    /**
     * @param array<string,mixed> $context
     */
    private function rememberPendingInviteContext(array $context, string $redirect): void
    {
        $channel = (string)($context['invite_channel'] ?? '');
        if ($channel === '') {
            return;
        }

        if ($channel === 'event') {
            $token = (string)($context['event_token'] ?? '');
            if ($token !== '') {
                $this->pendingInvites->captureEvent($token, $redirect, [
                    'channel' => $channel,
                    'metadata' => ['source' => 'guest.rsvp'],
                ]);
            }
            return;
        }

        $token = (string)($context['invite_token'] ?? '');
        if ($token === '') {
            return;
        }

        $this->pendingInvites->captureCommunity($token, $redirect, [
            'channel' => $channel,
            'metadata' => ['source' => 'invitation.accept'],
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}|null
     */
    private function completePendingInvite(string $channel, string $inviteToken, string $eventToken, int $userId): ?array
    {
        if ($channel === 'community' && $inviteToken !== '') {
            $result = $this->invitations->acceptCommunityInvitation($inviteToken, $userId);
            if ($result['success']) {
                if (!isset($result['data']) || !is_array($result['data'])) {
                    $result['data'] = [];
                }
                $result['data']['accepted_via'] = 'oauth';
            }
            return $result;
        }

        if ($channel === 'event_share' && $inviteToken !== '') {
            $result = $this->invitations->acceptEventShareInvitation($inviteToken, $userId);
            if ($result['success']) {
                if (!isset($result['data']) || !is_array($result['data'])) {
                    $result['data'] = [];
                }
                if (!isset($result['data']['redirect_url'])) {
                    $result['data']['redirect_url'] = isset($result['data']['rsvp_token'])
                        ? '/rsvp/' . rawurlencode((string)$result['data']['rsvp_token'])
                        : null;
                }
                $result['data']['accepted_via'] = 'oauth';
            }
            return $result;
        }

        if ($channel === 'event' && $eventToken !== '') {
            return $this->invitations->attachEventInvitation($eventToken, $userId, 'oauth');
        }

        return null;
    }

    /**
     * @param array{success:bool,status:int,message?:string} $result
     */
    private function logInviteAcceptanceResult(string $channel, int $userId, array $result): void
    {
        $status = $result['success'] ? 'success' : 'failure';
        $code = (int)($result['status'] ?? 0);
        $message = (string)($result['message'] ?? '');
        error_log(sprintf(
            '[BlueskyOAuth] invite_accept channel=%s user_id=%d status=%s http=%d message=%s',
            $channel,
            $userId,
            $status,
            $code,
            $message
        ));
    }
}
