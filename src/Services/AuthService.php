<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Services\DefaultCommunityService;
use PDO;

final class AuthService
{
    private const REMEMBER_COOKIE = 'remember_token';
    private const REMEMBER_DURATION = 60 * 60 * 24 * 30; // 30 days

    private ?array $cachedUser = null;
    private bool $rememberChecked = false;

    public function __construct(
        private Database $database,
        private MailService $mail
    ) {
    }

    public function currentUserId(): ?int
    {
        $this->ensureSession();

        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            $id = (int)$_SESSION['user_id'];
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    public function currentUserEmail(): ?string
    {
        $this->ensureSession();

        if (isset($_SESSION['user_email']) && is_string($_SESSION['user_email']) && $_SESSION['user_email'] !== '') {
            return (string)$_SESSION['user_email'];
        }

        $user = $this->getCurrentUser();
        if ($user !== null && isset($user->email) && $user->email !== '') {
            return (string)$user->email;
        }

        return null;
    }

    public function getCurrentUser(): ?object
    {
        $id = $this->currentUserId();
        if ($id === null) {
            $this->cachedUser = null;
            return null;
        }

        if ($this->cachedUser !== null && (int)$this->cachedUser['id'] === $id) {
            return (object)$this->cachedUser;
        }

        $row = $this->loadActiveUserRow($id);
        if ($row === null) {
            $this->logout();
            return null;
        }

        $_SESSION['user_email'] = $row['email'];
        $this->cachedUser = $row;

        return (object)$row;
    }

    public function isLoggedIn(): bool
    {
        return $this->currentUserId() !== null;
    }

    public function currentUserCan(string $capability): bool
    {
        return false;
    }

    /**
     * @return array{
     *   success: bool,
     *   errors?: array<string,string>,
     *   user?: object
     * }
     */
    public function attemptLogin(string $identifier, string $password, bool $remember = false): array
    {
        $this->ensureSession();

        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return [
                'success' => false,
                'errors' => ['credentials' => 'Email and password are required.'],
            ];
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, username, email, password_hash, display_name, status, role, created_at, updated_at
             FROM users
             WHERE (email = :email_identifier OR username = :username_identifier)
             LIMIT 1"
        );
        $stmt->execute([
            ':email_identifier' => $identifier,
            ':username_identifier' => $identifier,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user === false ||
            !isset($user['password_hash']) ||
            !password_verify($password, (string)$user['password_hash'])
        ) {
            return [
                'success' => false,
                'errors' => ['credentials' => 'Invalid email or password.'],
            ];
        }

        if (($user['status'] ?? '') !== 'active') {
            return [
                'success' => false,
                'errors' => ['credentials' => 'Please verify your email before signing in.'],
            ];
        }

        // Cast id to int to prevent type mismatches in strict comparisons
        $user['id'] = (int)$user['id'];

        $this->establishSession($user['id'], (string)$user['email']);
        $this->forgetRememberCookie();
        if ($remember) {
            $this->issueRememberToken($user['id']);
        }
        $this->cachedUser = $user;
        $this->updateLastLogin($user['id']);

        return [
            'success' => true,
            'user' => (object)$user,
        ];
    }

    public function logout(): void
    {
        $this->ensureSession();
        $this->cachedUser = null;

        $this->forgetRememberCookie();

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    /**
     * @param array<string,string>|string $input
     * @return array{
     *   success: bool,
     *   errors: array<string,string>,
     *   user_id?: int
     * }
     */
    public function register($input, ?string $email = null, ?string $password = null, ?string $displayName = null): array
    {
        $this->ensureSession();

        if (is_array($input)) {
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = (string)($input['password'] ?? '');
        $displayName = trim($input['display_name'] ?? '');
        } else {
            $username = trim((string)$input);
            $email = trim((string)$email);
            $password = (string)$password;
            $displayName = trim((string)$displayName);
        }

        $errors = [];

        $usernameMinLength = (int)user_config('username_min_length', 2);
        $usernameMaxLength = (int)user_config('username_max_length', 30);

        if ($displayName === '') {
            $errors['display_name'] = 'Display name is required.';
        }

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($username) < $usernameMinLength) {
            $errors['username'] = sprintf(
                'Username must be at least %d characters long.',
                $usernameMinLength
            );
        } elseif (strlen($username) > $usernameMaxLength) {
            $errors['username'] = sprintf(
                'Username cannot exceed %d characters.',
                $usernameMaxLength
            );
        } elseif (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            $errors['username'] = 'Username may contain only letters, numbers, dots, dashes, or underscores.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($username !== '' && !isset($errors['username']) && $this->usernameExists($username)) {
            $errors['username'] = 'That username is already taken.';
        }

        if ($email !== '' && !isset($errors['email']) && $this->emailExists($email)) {
            $errors['email'] = 'That email is already registered.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $pdo = $this->database->pdo();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO users (
                username,
                email,
                password_hash,
                display_name,
                status,
                created_at,
                updated_at
            ) VALUES (
                :username,
                :email,
                :password_hash,
                :display_name,
                'pending',
                :created_at,
                :updated_at
            )"
        );

        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':display_name' => $displayName,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $userId = (int)$pdo->lastInsertId();
        $this->createUserProfile($userId, $displayName);
        $this->createDefaultCommunitiesForUser($userId, $displayName, $email);

        // Fire off the verification email. If mail transport is down we log the failure
        // but do not block registration so users can retry later.
        try {
            $this->sendVerificationEmail($userId, $email);
        } catch (\Throwable $e) {
            $this->logMailFailure('verification', $userId, $email, $e);
        }

        return [
            'success' => true,
            'errors' => [],
            'user_id' => $userId,
        ];
    }

    public function getUserById(int $userId): ?object
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, username, email, display_name, status, role, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || ($row['status'] ?? '') !== 'active') {
            return null;
        }

        // Cast id to int to prevent type mismatches in strict comparisons
        $row['id'] = (int)$row['id'];

        return (object)$row;
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->rememberChecked && empty($_SESSION['user_id'])) {
            $this->rememberChecked = true;
            $this->restoreRememberedLogin();
        }
    }

    private function establishSession(int $userId, string $email): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
    }

    private function issueRememberToken(int $userId): void
    {
        try {
            $selector = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $this->logError('Failed to generate remember token: ' . $e->getMessage());
            return;
        }

        $hash = hash('sha256', $validator);
        $expiresAtTs = time() + self::REMEMBER_DURATION;
        $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);

        $stmt = $this->database->pdo()->prepare(
            "INSERT INTO remember_tokens (
                user_id, selector, validator_hash, user_agent, ip_address, expires_at, created_at
            ) VALUES (
                :user_id, :selector, :validator_hash, :user_agent, :ip_address, :expires_at, NOW()
            )"
        );

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : null;

        $stmt->execute([
            ':user_id' => $userId,
            ':selector' => $selector,
            ':validator_hash' => $hash,
            ':user_agent' => $userAgent,
            ':ip_address' => $ipAddress,
            ':expires_at' => $expiresAt,
        ]);

        $this->setRememberCookie($selector, $validator, $expiresAtTs);
        $this->pruneExpiredRememberTokens();
    }

    private function restoreRememberedLogin(): void
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if ($cookie === '') {
            return;
        }

        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            $this->forgetRememberCookie();
            return;
        }

        [$selector, $validator] = $parts;

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, user_id, validator_hash, expires_at
             FROM remember_tokens
             WHERE selector = :selector
             LIMIT 1"
        );
        $stmt->execute([':selector' => $selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token === false) {
            $this->forgetRememberCookie(false);
            return;
        }

        $tokenId = (int)$token['id'];
        $userId = (int)$token['user_id'];

        if (strtotime((string)$token['expires_at']) <= time()) {
            $this->deleteRememberToken($tokenId);
            $this->forgetRememberCookie(false);
            return;
        }

        $expected = hash('sha256', $validator);
        if (!hash_equals((string)$token['validator_hash'], $expected)) {
            $this->deleteRememberToken($tokenId);
            $this->forgetRememberCookie(false);
            return;
        }

        $user = $this->loadActiveUserRow($userId);
        if ($user === null) {
            $this->deleteRememberToken($tokenId);
            $this->forgetRememberCookie(false);
            return;
        }

        $this->establishSession($user['id'], (string)$user['email']);
        $this->cachedUser = $user;
        $this->updateLastLogin($user['id']);
        $this->refreshRememberToken($tokenId, $selector);
    }

    private function refreshRememberToken(int $tokenId, string $selector): void
    {
        try {
            $validator = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $this->logError('Failed to rotate remember token: ' . $e->getMessage());
            $this->forgetRememberCookie(false);
            return;
        }

        $hash = hash('sha256', $validator);
        $expiresAtTs = time() + self::REMEMBER_DURATION;
        $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);

        $stmt = $this->database->pdo()->prepare(
            "UPDATE remember_tokens
             SET validator_hash = :validator_hash,
                 expires_at = :expires_at,
                 last_used_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            ':validator_hash' => $hash,
            ':expires_at' => $expiresAt,
            ':id' => $tokenId,
        ]);

        $this->setRememberCookie($selector, $validator, $expiresAtTs);
    }

    private function setRememberCookie(string $selector, string $validator, int $expiresAtTs): void
    {
        $cookieValue = $selector . ':' . $validator;
        $options = [
            'expires' => $expiresAtTs,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        setcookie(self::REMEMBER_COOKIE, $cookieValue, $options);
        $_COOKIE[self::REMEMBER_COOKIE] = $cookieValue;
    }

    private function forgetRememberCookie(bool $deleteRecord = true): void
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if ($cookie !== null) {
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }

        if ($deleteRecord && $cookie) {
            $parts = explode(':', $cookie, 2);
            if (count($parts) === 2 && $parts[0] !== '') {
                $this->deleteRememberTokenBySelector($parts[0]);
            }
        }

        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function deleteRememberToken(int $tokenId): void
    {
        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM remember_tokens WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $tokenId]);
    }

    private function deleteRememberTokenBySelector(string $selector): void
    {
        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM remember_tokens WHERE selector = :selector LIMIT 1"
        );
        $stmt->execute([':selector' => $selector]);
    }

    private function pruneExpiredRememberTokens(): void
    {
        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM remember_tokens WHERE expires_at < NOW()"
        );
        $stmt->execute();
    }

    private function usernameExists(string $username): bool
    {
        if ($username === '') {
            return false;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT 1 FROM users WHERE username = :username LIMIT 1"
        );
        $stmt->execute([':username' => $username]);

        return $stmt->fetchColumn() !== false;
    }

    private function emailExists(string $email): bool
    {
        if ($email === '') {
            return false;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT 1 FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    private function createUserProfile(int $userId, string $displayName): void
    {
        try {
            $stmt = $this->database->pdo()->prepare(
                "INSERT INTO user_profiles (user_id, display_name)
                VALUES (:user_id, :display_name)"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':display_name' => $displayName,
            ]);
        } catch (\Throwable $e) {
            // Profiles are optional for now; ignore failures.
        }
    }

    private function createDefaultCommunitiesForUser(int $userId, string $displayName, string $email): void
    {
        if (!function_exists('app_service')) {
            return;
        }

        try {
            $service = app_service('default.community.service');
            if ($service instanceof DefaultCommunityService) {
                $service->createForUser($userId, $displayName, $email);
            }
        } catch (\Throwable $e) {
            $this->logError('Failed to create default communities for user ' . $userId . ': ' . $e->getMessage());
        }
    }

    /**
     * Load an active user row or return null if unavailable.
     *
     * @return array<string,mixed>|null
     */
    private function loadActiveUserRow(int $userId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT id, username, email, display_name, status, role, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || ($row['status'] ?? '') !== 'active') {
            return null;
        }

        $row['id'] = (int)$row['id'];
        return $row;
    }

    private function logError(string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL);
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    private function updateLastLogin(int $userId): void
    {
        $stmt = $this->database->pdo()->prepare(
            "UPDATE users SET last_login_at = :last_login_at WHERE id = :id"
        );
        $stmt->execute([
            ':last_login_at' => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);
    }

    /**
     * Request password reset
     *
     * @return array{success:bool, errors?:array<string,string>, message?:string}
     */
    public function requestPasswordReset(string $email): array
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'errors' => ['email' => 'Valid email address required.'],
            ];
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND status = 'active' LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return [
                'success' => true,
                'message' => 'If that email exists, a reset link has been sent.',
            ];
        }

        $userId = (int)$user['id'];
        $token = $this->generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $insertStmt = $pdo->prepare(
            "INSERT INTO password_reset_tokens (user_id, token, expires_at)
             VALUES (:user_id, :token, :expires_at)"
        );
        $insertStmt->execute([
            ':user_id' => $userId,
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);

        $this->sendPasswordResetEmail($email, $token);

        return [
            'success' => true,
            'message' => 'If that email exists, a reset link has been sent.',
        ];
    }

    /**
     * Validate password reset token
     *
     * @return array{valid:bool, user_id?:int, error?:string}
     */
    public function validateResetToken(string $token): array
    {
        if ($token === '') {
            return ['valid' => false, 'error' => 'Token is required.'];
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "SELECT id, user_id, expires_at, used_at
             FROM password_reset_tokens
             WHERE token = :token
             LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tokenRecord === false) {
            return ['valid' => false, 'error' => 'Invalid token.'];
        }

        if ($tokenRecord['used_at'] !== null) {
            return ['valid' => false, 'error' => 'Token already used.'];
        }

        if (strtotime($tokenRecord['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'Token expired.'];
        }

        return [
            'valid' => true,
            'user_id' => (int)$tokenRecord['user_id'],
        ];
    }

    /**
     * Reset password with token
     *
     * @return array{success:bool, errors?:array<string,string>, message?:string}
     */
    public function resetPasswordWithToken(string $token, string $newPassword): array
    {
        $validation = $this->validateResetToken($token);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => ['token' => $validation['error'] ?? 'Invalid token.'],
            ];
        }

        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'errors' => ['password' => 'Password must be at least 8 characters.'],
            ];
        }

        $userId = $validation['user_id'];
        $pdo = $this->database->pdo();

        $updateStmt = $pdo->prepare(
            "UPDATE users
             SET password_hash = :password_hash, updated_at = :updated_at
             WHERE id = :id"
        );
        $updateStmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);

        $markUsedStmt = $pdo->prepare(
            "UPDATE password_reset_tokens
             SET used_at = :used_at
             WHERE token = :token"
        );
        $markUsedStmt->execute([
            ':used_at' => date('Y-m-d H:i:s'),
            ':token' => $token,
        ]);

        return [
            'success' => true,
            'message' => 'Password reset successfully.',
        ];
    }

    /**
     * Send email verification
     *
     * @return array{success:bool, message?:string}
     */
    public function sendVerificationEmail(int $userId, string $email): array
    {
        $token = $this->generateSecureToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO email_verification_tokens (user_id, email, token, expires_at)
             VALUES (:user_id, :email, :token, :expires_at)"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':token' => $token,
            ':expires_at' => $expiresAt,
        ]);

        $this->sendEmailVerificationEmail($email, $token);

        return [
            'success' => true,
            'message' => 'Verification email sent.',
        ];
    }

    /**
     * Verify email with token
     *
     * @return array{success:bool, errors?:array<string,string>, message?:string}
     */
    public function verifyEmail(string $token): array
    {
        if ($token === '') {
            return [
                'success' => false,
                'errors' => ['token' => 'Token is required.'],
            ];
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare(
            "SELECT id, user_id, email, expires_at, verified_at
             FROM email_verification_tokens
             WHERE token = :token
             LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tokenRecord === false) {
            return [
                'success' => false,
                'errors' => ['token' => 'Invalid verification token.'],
            ];
        }

        if ($tokenRecord['verified_at'] !== null) {
            return [
                'success' => false,
                'errors' => ['token' => 'Email already verified.'],
            ];
        }

        if (strtotime($tokenRecord['expires_at']) < time()) {
            return [
                'success' => false,
                'errors' => ['token' => 'Verification token expired.'],
            ];
        }

        $markVerifiedStmt = $pdo->prepare(
            "UPDATE email_verification_tokens
             SET verified_at = :verified_at
             WHERE id = :id"
        );
        $markVerifiedStmt->execute([
            ':verified_at' => date('Y-m-d H:i:s'),
            ':id' => $tokenRecord['id'],
        ]);

        $activateStmt = $pdo->prepare(
            "UPDATE users SET status = 'active', updated_at = :updated_at WHERE id = :user_id"
        );
        $activateStmt->execute([
            ':updated_at' => date('Y-m-d H:i:s'),
            ':user_id' => $tokenRecord['user_id'],
        ]);

        return [
            'success' => true,
            'message' => 'Email verified successfully.',
        ];
    }

    /**
     * Force a password reset email for admin actions.
     *
     * @return array{success:bool, message?:string, errors?:array<string,string>}
     */
    public function adminSendPasswordReset(int $userId): array
    {
        try {
            $pdo = $this->database->pdo();
            $stmt = $pdo->prepare(
                "SELECT email FROM users WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user === false || empty($user['email'])) {
                return [
                    'success' => false,
                    'errors' => ['user' => 'User not found.'],
                ];
            }

            $token = $this->generateSecureToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $insertStmt = $pdo->prepare(
                "INSERT INTO password_reset_tokens (user_id, token, expires_at)
                 VALUES (:user_id, :token, :expires_at)"
            );
            $insertStmt->execute([
                ':user_id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt,
            ]);

            $this->sendPasswordResetEmail((string)$user['email'], $token);

            return [
                'success' => true,
                'message' => 'Password reset email sent to ' . $user['email'],
            ];
        } catch (\Throwable $e) {
            $this->logError('Failed admin password reset for user ' . $userId . ': ' . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['user' => 'Unable to send reset email.'],
            ];
        }
    }

    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function sendPasswordResetEmail(string $email, string $token): void
    {
        $resetUrl = $this->getSiteUrl() . '/reset-password/' . $token;

        $this->mail->sendTemplate($email, 'password_reset', [
            'reset_url' => $resetUrl,
            'site_name' => (string)app_config('app_name', 'Our Community'),
            'subject' => 'Reset Your Password',
        ]);
    }

    private function sendEmailVerificationEmail(string $email, string $token): void
    {
        $verifyUrl = $this->getSiteUrl() . '/verify-email/' . $token;

        $this->mail->sendTemplate($email, 'email_verification', [
            'verify_url' => $verifyUrl,
            'site_name' => (string)app_config('app_name', 'Our Community'),
            'subject' => 'Verify Your Email Address',
        ]);
    }

    private function logMailFailure(string $context, int $userId, string $email, \Throwable $e): void
    {
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf(
            '[%s] mail.%s failure for user %d <%s>: %s',
            $timestamp,
            $context,
            $userId,
            $email,
            $e->getMessage()
        );
        file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    }

    private function getSiteUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Set user role
     *
     * @param int $userId User ID
     * @param string $role Role (member, admin, super_admin)
     * @return bool True if role was updated
     */
    public function setUserRole(int $userId, string $role): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $validRoles = ['member', 'admin', 'super_admin'];
        if (!in_array($role, $validRoles, true)) {
            return false;
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get user role
     *
     * @param int $userId User ID
     * @return string|null Role or null if user not found
     */
    public function getUserRole(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        $role = $stmt->fetchColumn();
        return $role !== false ? (string)$role : null;
    }

    /**
     * Check if user is an admin
     *
     * @param int $userId User ID
     * @return bool True if user has admin or super_admin role
     */
    public function isAdmin(int $userId): bool
    {
        $role = $this->getUserRole($userId);
        return in_array($role, ['admin', 'super_admin'], true);
    }

    /**
     * Check if user is a super admin
     *
     * @param int $userId User ID
     * @return bool True if user has super_admin role
     */
    public function isSuperAdmin(int $userId): bool
    {
        $role = $this->getUserRole($userId);
        return $role === 'super_admin';
    }
}
