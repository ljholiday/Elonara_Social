<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Database\Database;
use App\Http\Request;
use App\Services\AuthService;
use App\Services\InvitationService;
use App\Services\ValidatorService;

final class AuthController
{
    public function __construct(
        private AuthService $auth,
        private ValidatorService $validator,
        private Database $database,
        private InvitationService $invitations
    ) {
    }

    public function landing(): array
    {
        $redirect = $this->sanitizeRedirect($this->request()->query('redirect_to'));
        $flashMessage = $this->popFlashMessage();

        $invitation = $this->extractInvitationContext($redirect);

        // Store invitation token in session for resilience against page refreshes/errors
        if ($invitation !== null && str_contains($redirect, '/invitation/accept?token=')) {
            parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);
            $token = $query['token'] ?? '';
            if ($token !== '') {
                $_SESSION['pending_invitation_token'] = $token;
            }
        }

        return $this->buildView(
            loginInput: ['redirect_to' => $redirect],
            registerInput: ['redirect_to' => $redirect],
            flash: $flashMessage !== null ? ['type' => 'success', 'message' => $flashMessage] : [],
            invitation: $invitation
        );
    }

    /**
     * @return array{redirect?: string, active?: string, login?: array<string,mixed>, register?: array<string,mixed>}
     */
    public function login(): array
    {
        $request = $this->request();
        $identifierRaw = (string)$request->input('identifier', '');
        $passwordRaw = (string)$request->input('password', '');
        $redirect = $this->sanitizeRedirect($request->input('redirect_to'));
        $remember = (string)$request->input('remember', '') === '1';
        $flashMessage = $this->popFlashMessage();

        // Validate inputs
        $identifierValidation = $this->validator->required($identifierRaw, 'Email or username');
        $passwordValidation = $this->validator->required($passwordRaw, 'Password');

        $errors = [];
        if (!$identifierValidation['is_valid']) {
            $errors['identifier'] = $identifierValidation['errors'][0] ?? 'Email or username is required.';
        }
        if (!$passwordValidation['is_valid']) {
            $errors['password'] = $passwordValidation['errors'][0] ?? 'Password is required.';
        }

        if ($errors === []) {
            $result = $this->auth->attemptLogin($identifierValidation['value'], $passwordRaw, $remember);
            if ($result['success']) {
                // Check for pending invitation in session and auto-accept
                $invitationRedirect = $this->processPendingInvitation();
                if ($invitationRedirect !== null) {
                    return ['redirect' => $invitationRedirect];
                }

                return [
                    'redirect' => $redirect !== '' ? $redirect : '/',
                ];
            }
            $errors = $result['errors'] ?? ['credentials' => 'Unable to sign in with those details.'];
        }

        return $this->buildView(
            loginInput: [
                'identifier' => $identifierValidation['value'] ?? $identifierRaw,
                'remember' => $remember,
                'redirect_to' => $redirect,
            ],
            loginErrors: $errors,
            registerInput: ['redirect_to' => $redirect],
            active: 'login',
            flash: $flashMessage !== null ? ['type' => 'success', 'message' => $flashMessage] : []
        );
    }

    /**
     * @return array{redirect?: string, active?: string, login?: array<string,mixed>, register?: array<string,mixed>}
     */
    public function register(): array
    {
        $request = $this->request();
        $displayNameRaw = (string)$request->input('display_name', '');
        $usernameRaw = (string)$request->input('username', '');
        $emailRaw = (string)$request->input('email', '');
        $passwordRaw = (string)$request->input('password', '');
        $confirmRaw = (string)$request->input('confirm_password', '');
        $blueskyHandleRaw = (string)$request->input('bluesky_handle', '');
        $redirect = $this->sanitizeRedirect($request->input('redirect_to'));

        // Validate inputs
        $displayNameValidation = $this->validator->textField($displayNameRaw, 1, 100);
        $usernameMinLength = (int)user_config('username_min_length', 2);
        $usernameMaxLength = (int)user_config('username_max_length', 30);
        $usernameValidation = $this->validator->username($usernameRaw, $usernameMinLength, $usernameMaxLength);
        $emailValidation = $this->validator->email($emailRaw);
        $passwordValidation = $this->validator->password($passwordRaw);

        $errors = [];
        if (!$displayNameValidation['is_valid']) {
            $errors['display_name'] = $displayNameValidation['errors'][0] ?? 'Display name is required.';
        }
        if (!$usernameValidation['is_valid']) {
            $errors['username'] = $usernameValidation['errors'][0] ?? 'Username is invalid.';
        }
        if (!$emailValidation['is_valid']) {
            $errors['email'] = $emailValidation['errors'][0] ?? 'Email is invalid.';
        }
        if (!$passwordValidation['is_valid']) {
            $errors['password'] = $passwordValidation['errors'][0] ?? 'Password is required.';
        } elseif ($passwordRaw !== $confirmRaw) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors === []) {
            $result = $this->auth->register([
                'display_name' => $displayNameValidation['value'],
                'username' => $usernameValidation['value'],
                'email' => $emailValidation['value'],
                'password' => $passwordRaw,
            ]);

            if ($result['success']) {
                $userId = $result['user_id'] ?? 0;

                // Store self-reported Bluesky handle if provided
                if ($blueskyHandleRaw !== '') {
                    $this->storeSelfReportedBlueskyHandle($userId, $blueskyHandleRaw);
                }

                // Auto-verify and login when redirect_to is present (e.g., Bluesky invitations)
                // These users are verified via their Bluesky DID
                if ($redirect !== '' && str_contains($redirect, '/invitation/accept')) {
                    $this->autoVerifyUser($userId);
                    $loginResult = $this->auth->attemptLogin($emailValidation['value'], $passwordRaw, false);
                    if ($loginResult['success']) {
                        // Check for pending invitation in session and auto-accept
                        $invitationRedirect = $this->processPendingInvitation();
                        if ($invitationRedirect !== null) {
                            return ['redirect' => $invitationRedirect];
                        }
                        return [
                            'redirect' => $redirect,
                        ];
                    }
                }

                // Standard flow: show success message and login form
                $successFlash = [
                    'type' => 'success',
                    // Plain-language guidance so new members know to verify first.
                    'message' => 'Thanks for registering! Please check your inbox and verify your email before signing in.',
                ];

                return $this->buildView(
                    loginInput: [
                        'identifier' => $emailValidation['value'],
                        'remember' => false,
                        'redirect_to' => $redirect,
                    ],
                    registerInput: [
                        'display_name' => '',
                        'username' => '',
                        'email' => $emailValidation['value'],
                        'redirect_to' => $redirect,
                    ],
                    registerErrors: [],
                    active: 'login',
                    flash: $successFlash
                );
            }

            $errors = $result['errors'];
        }

        if ($errors !== []) {
            $this->debugAuth('register_failed', [
                'input' => [
                    'display_name' => $displayNameValidation['value'] ?? $displayNameRaw,
                    'username' => $usernameValidation['value'] ?? $usernameRaw,
                    'email' => $emailValidation['value'] ?? $emailRaw,
                ],
                'errors' => $errors,
            ]);
        }

        $sessionFlash = $this->popFlashMessage();

        return $this->buildView(
            loginInput: ['redirect_to' => $redirect],
            registerInput: [
                'display_name' => $displayNameValidation['value'] ?? $displayNameRaw,
                'username' => $usernameValidation['value'] ?? $usernameRaw,
                'email' => $emailValidation['value'] ?? $emailRaw,
                'bluesky_handle' => $blueskyHandleRaw,
                'redirect_to' => $redirect,
            ],
            registerErrors: $errors,
            active: 'register',
            flash: $sessionFlash !== null ? ['type' => 'success', 'message' => $sessionFlash] : []
        );
    }

    /**
     * @return array{redirect: string}
     */
    public function logout(): array
    {
        $this->auth->logout();
        return [
            'redirect' => '/auth',
        ];
    }

    /**
     * @return array{errors: array<string,string>, input: array<string,string>}
     */
    public function requestReset(): array
    {
        return [
            'errors' => [],
            'input' => ['email' => ''],
        ];
    }

    /**
     * @return array{errors?: array<string,string>, message?: string, input?: array<string,string>}
     */
    public function sendResetEmail(): array
    {
        $request = $this->request();
        $emailRaw = (string)$request->input('email', '');

        $emailValidation = $this->validator->email($emailRaw);

        if (!$emailValidation['is_valid']) {
            return [
                'errors' => ['email' => $emailValidation['errors'][0] ?? 'Invalid email format.'],
                'input' => ['email' => $emailRaw],
            ];
        }

        $result = $this->auth->requestPasswordReset($emailValidation['value']);

        if ($result['success']) {
            return [
                'message' => $result['message'] ?? 'If that email exists, a reset link has been sent.',
            ];
        }

        return [
            'errors' => $result['errors'] ?? ['email' => 'An error occurred.'],
            'input' => ['email' => $emailValidation['value']],
        ];
    }

    /**
     * @return array{valid: bool, token: string, error?: string}
     */
    public function showResetForm(string $token): array
    {
        $validation = $this->auth->validateResetToken($token);

        return [
            'valid' => $validation['valid'],
            'token' => $token,
            'error' => $validation['error'] ?? null,
        ];
    }

    /**
     * @return array{redirect?: string, errors?: array<string,string>, message?: string, token?: string}
     */
    public function processReset(string $token): array
    {
        $request = $this->request();
        $passwordRaw = (string)$request->input('password', '');
        $confirmRaw = (string)$request->input('confirm_password', '');

        $passwordValidation = $this->validator->password($passwordRaw);

        $errors = [];
        if (!$passwordValidation['is_valid']) {
            $errors['password'] = $passwordValidation['errors'][0] ?? 'Password is required.';
        } elseif ($passwordRaw !== $confirmRaw) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            return [
                'errors' => $errors,
                'token' => $token,
            ];
        }

        $result = $this->auth->resetPasswordWithToken($token, $passwordRaw);

        if ($result['success']) {
            return [
                'redirect' => '/auth',
                'message' => $result['message'] ?? 'Password reset successfully.',
            ];
        }

        return [
            'errors' => $result['errors'] ?? ['token' => 'An error occurred.'],
            'token' => $token,
        ];
    }

    /**
     * @return array{success: bool, message?: string, errors?: array<string,string>, redirect?: string}
     */
    public function verifyEmail(string $token): array
    {
        $result = $this->auth->verifyEmail($token);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Email verified successfully.',
                'redirect' => '/auth',
            ];
        }

        return [
            'success' => false,
            'errors' => $result['errors'] ?? ['token' => 'Verification failed.'],
        ];
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = app_service('http.request');
        return $request;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function debugAuth(string $event, array $context = []): void
    {
        $logFile = dirname(__DIR__, 3) . '/debug.log';
        $line = sprintf(
            "[%s] AuthController:%s %s\n",
            date('Y-m-d H:i:s'),
            $event,
            json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        file_put_contents($logFile, $line, FILE_APPEND);
    }

    private function sanitizeRedirect($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '' || str_starts_with($trimmed, '//')) {
            return '';
        }

        if (preg_match('#^https?://#i', $trimmed)) {
            return '';
        }

        return str_starts_with($trimmed, '/') ? $trimmed : '';
    }

    /**
     * @param array<string,mixed> $loginInput
     * @param array<string,string> $loginErrors
     * @param array<string,mixed> $registerInput
     * @param array<string,string> $registerErrors
     * @return array{
     *   active: string,
     *   login: array{errors: array<string,string>, input: array<string,mixed>},
     *   register: array{errors: array<string,string>, input: array<string,mixed>}
     * }
     */
    private function buildView(
        array $loginInput = [],
        array $loginErrors = [],
        array $registerInput = [],
        array $registerErrors = [],
        string $active = 'login',
        array $flash = [],
        ?array $invitation = null
    ): array {
        return [
            'active' => $active,
            'login' => [
                'errors' => $loginErrors,
                'input' => array_merge([
                    'identifier' => '',
                    'remember' => false,
                    'redirect_to' => '',
                ], $loginInput),
            ],
            'register' => [
                'errors' => $registerErrors,
                'input' => array_merge([
                    'display_name' => '',
                    'username' => '',
                    'email' => '',
                    'bluesky_handle' => '',
                    'redirect_to' => '',
                ], $registerInput),
            ],
            'flash' => $flash,
            'invitation' => $invitation,
        ];
    }

    private function popFlashMessage(): ?string
    {
        if (!isset($_SESSION['flash_message'])) {
            return null;
        }

        $message = (string)$_SESSION['flash_message'];
        unset($_SESSION['flash_message']);

        return $message;
    }

    private function storeSelfReportedBlueskyHandle(int $userId, string $handleRaw): void
    {
        if ($userId <= 0 || $handleRaw === '') {
            return;
        }

        $handle = trim($handleRaw);
        $handle = ltrim($handle, '@');

        if ($handle === '') {
            return;
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            INSERT INTO member_identities (user_id, email, handle, verification_method, is_verified, created_at, updated_at)
            SELECT ?, email, ?, ?, 0, NOW(), NOW()
            FROM users
            WHERE id = ?
            ON DUPLICATE KEY UPDATE
                handle = VALUES(handle),
                verification_method = VALUES(verification_method),
                updated_at = NOW()
        ');

        $stmt->execute([
            $userId,
            $handle,
            'self_reported',
            $userId
        ]);
    }

    private function autoVerifyUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$userId]);
    }

    /**
     * Extract invitation context from redirect_to URL
     * @return array{type:string,name:string,inviter:string}|null
     */
    private function extractInvitationContext(string $redirect): ?array
    {
        if ($redirect === '' || !str_contains($redirect, '/invitation/accept?token=')) {
            return null;
        }

        // Extract token from redirect URL
        parse_str(parse_url($redirect, PHP_URL_QUERY) ?? '', $query);
        $token = $query['token'] ?? '';

        if ($token === '') {
            return null;
        }

        // Check for public community share token (pc_...)
        if (str_starts_with($token, 'pc_')) {
            $communityInfo = $this->invitations->getCommunityInfoFromShareToken($token);
            if ($communityInfo !== null) {
                return [
                    'type' => 'community',
                    'name' => $communityInfo['name'],
                    'inviter' => $communityInfo['creator'],
                ];
            }
        }

        // Check for public event share token (pe_...)
        if (str_starts_with($token, 'pe_')) {
            $eventInfo = $this->invitations->getEventInfoFromShareToken($token);
            if ($eventInfo !== null) {
                // Need to get host name - fetch event details
                $pdo = $this->database->pdo();
                $stmt = $pdo->prepare('
                    SELECT u.display_name
                    FROM events e
                    LEFT JOIN users u ON u.id = e.author_id
                    WHERE e.slug = ?
                    LIMIT 1
                ');
                $stmt->execute([$eventInfo['slug']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $hostName = $row !== false ? (string)($row['display_name'] ?? 'Someone') : 'Someone';

                return [
                    'type' => 'event',
                    'name' => $eventInfo['title'],
                    'inviter' => $hostName,
                ];
            }
        }

        $pdo = $this->database->pdo();

        // Try private community invitation
        $stmt = $pdo->prepare('
            SELECT
                ci.community_id,
                ci.invited_by_member_id,
                c.name AS community_name,
                u.display_name AS inviter_name
            FROM community_invitations ci
            LEFT JOIN communities c ON c.id = ci.community_id
            LEFT JOIN users u ON u.id = ci.invited_by_member_id
            WHERE ci.invitation_token = ?
              AND ci.status = ?
              AND (ci.expires_at IS NULL OR ci.expires_at > NOW())
            LIMIT 1
        ');
        $stmt->execute([$token, 'pending']);
        $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($invitation !== false) {
            return [
                'type' => 'community',
                'name' => (string)($invitation['community_name'] ?? 'a community'),
                'inviter' => (string)($invitation['inviter_name'] ?? 'Someone'),
            ];
        }

        // Try event invitation (guests table uses rsvp_token)
        $stmt = $pdo->prepare('
            SELECT
                g.event_id,
                e.title AS event_name,
                u.display_name AS inviter_name
            FROM guests g
            LEFT JOIN events e ON e.id = g.event_id
            LEFT JOIN users u ON u.id = e.author_id
            WHERE g.rsvp_token = ?
              AND g.status = ?
            LIMIT 1
        ');
        $stmt->execute([$token, 'pending']);
        $eventInvite = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($eventInvite !== false) {
            return [
                'type' => 'event',
                'name' => (string)($eventInvite['event_name'] ?? 'an event'),
                'inviter' => (string)($eventInvite['inviter_name'] ?? 'Someone'),
            ];
        }

        return null;
    }

    /**
     * Process pending invitation stored in session.
     * Returns redirect URL on success, null otherwise.
     */
    private function processPendingInvitation(): ?string
    {
        if (!isset($_SESSION['pending_invitation_token'])) {
            return null;
        }

        $token = (string)$_SESSION['pending_invitation_token'];
        if ($token === '') {
            unset($_SESSION['pending_invitation_token']);
            return null;
        }

        $user = $this->auth->getCurrentUser();
        if ($user === null) {
            return null;
        }

        $viewerId = (int)($user->id ?? 0);
        if ($viewerId <= 0) {
            return null;
        }

        // Try to accept the invitation
        $result = $this->invitations->acceptCommunityInvitation($token, $viewerId);

        // Clear the session token
        unset($_SESSION['pending_invitation_token']);

        if ($result['success']) {
            $redirectUrl = (string)($result['data']['redirect_url'] ?? '');
            return $redirectUrl !== '' ? $redirectUrl : null;
        }

        return null;
    }
}
