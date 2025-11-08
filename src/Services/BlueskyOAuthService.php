<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Security\TokenEncryptor;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class BlueskyOAuthService
{
    private const CONTEXT_SESSION_KEY = 'bluesky_oauth';
    private const PROVIDER_NAME = 'bluesky-oauth';
    private const DEFAULT_SCOPE = 'atproto transition:generic';
    private const DEFAULT_API_BASE = 'https://bsky.social';

    private Database $database;
    private ?TokenEncryptor $encryptor;
    private AuthService $auth;
    private SecurityService $security;
    private Client $http;
    private array $config;
    private ?array $metadata = null;
    private ?array $publicJwk = null;

    public function __construct(
        Database $database,
        ?TokenEncryptor $encryptor,
        AuthService $auth,
        SecurityService $security,
        ?Client $client = null
    ) {
        $this->database = $database;
        $this->encryptor = $encryptor;
        $this->auth = $auth;
        $this->security = $security;
        $this->http = $client ?? new Client([
            'timeout' => 15,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'ElonaraSocial/1.0 (+https://social.elonara.com)',
            ],
        ]);
        $this->config = (array)app_config('bluesky.oauth', []);
    }

    public function isEnabled(): bool
    {
        return $this->encryptor !== null && (bool)($this->config['enabled'] ?? false);
    }

    public function forceForInvites(): bool
    {
        return $this->isEnabled() && (bool)($this->config['force_for_invites'] ?? false);
    }

    public function allowLegacyFallback(): bool
    {
        return (bool)($this->config['allow_legacy_fallback'] ?? true);
    }

    /**
     * @param array<string,mixed> $context
     * @return array{success:bool,redirect?:string,message?:string}
     */
    public function beginAuthorization(array $context = []): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Bluesky OAuth is not enabled.',
            ];
        }

        try {
            $state = bin2hex(random_bytes(16));
            $codeVerifier = $this->generateCodeVerifier();
            $codeChallenge = $this->codeChallenge($codeVerifier);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Unable to initialize OAuth: ' . $e->getMessage(),
            ];
        }

        $this->storeContext([
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'redirect_to' => $context['redirect_to'] ?? null,
            'invite_token' => $context['invite_token'] ?? null,
            'event_token' => $context['event_token'] ?? null,
            'invite_channel' => $context['invite_channel'] ?? null,
            'reauthorize' => (bool)($context['reauthorize'] ?? false),
            'initiated_at' => time(),
        ]);

        try {
            $metadata = $this->metadata();
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $requestParams = [
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'scope' => $this->config['scopes'] ?? self::DEFAULT_SCOPE,
            'state' => $state,
            'code_challenge_method' => 'S256',
            'code_challenge' => $codeChallenge,
        ];

        if (!empty($context['login_hint'])) {
            $requestParams['login_hint'] = (string)$context['login_hint'];
        }

        $authorizationResult = $this->buildAuthorizationRequestUrl($metadata, $requestParams);
        if (!$authorizationResult['success']) {
            return [
                'success' => false,
                'message' => $authorizationResult['message'] ?? 'Unable to initialize Bluesky OAuth.',
            ];
        }

        $authorizationUrl = $authorizationResult['url'];

        return [
            'success' => true,
            'redirect' => $authorizationUrl,
        ];
    }

    /**
     * @param array<string,string> $query
     * @return array{success:bool,redirect?:string,message?:string,user_id?:int,invite_token?:string|null,event_token?:string|null}
     */
    public function handleCallback(array $query): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Bluesky OAuth is not enabled.',
            ];
        }

        $context = $this->getContext();
        if ($context === null || !isset($context['state'])) {
            return [
                'success' => false,
                'message' => 'OAuth session expired. Please try again.',
            ];
        }

        $incomingState = (string)($query['state'] ?? '');
        if ($incomingState === '' || !hash_equals((string)$context['state'], $incomingState)) {
            $this->clearContext();
            return [
                'success' => false,
                'message' => 'State verification failed. Please restart authorization.',
            ];
        }

        $code = (string)($query['code'] ?? '');
        if ($code === '') {
            return [
                'success' => false,
                'message' => 'Authorization code missing.',
            ];
        }

        $tokenResult = $this->exchangeCode($code, (string)$context['code_verifier']);
        if (!$tokenResult['success']) {
            return $tokenResult;
        }

        $identity = $this->fetchIdentity($tokenResult['access_token'], $tokenResult['raw']);
        $did = (string)($identity['did'] ?? $tokenResult['raw']['did'] ?? $tokenResult['raw']['sub'] ?? '');
        if ($did === '') {
            return [
                'success' => false,
                'message' => 'Bluesky did was not returned by the provider.',
            ];
        }

        $handle = (string)($identity['handle'] ?? $tokenResult['raw']['handle'] ?? '');
        $displayName = (string)($identity['displayName'] ?? $identity['display_name'] ?? $handle);

        try {
            $userId = $this->resolveUserId($did, $handle, $displayName, (bool)$context['reauthorize']);
        } catch (RuntimeException $e) {
            $this->clearContext();
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $this->upsertIdentity(
            $userId,
            $did,
            $handle,
            $tokenResult['access_token'],
            $tokenResult['refresh_token'],
            (int)($tokenResult['expires_in'] ?? 0),
            $tokenResult['scope'] ?? ($this->config['scopes'] ?? self::DEFAULT_SCOPE),
            $identity,
            $tokenResult['raw']
        );

        $this->auth->loginUserById($userId);

        $redirect = $context['redirect_to'] ?? null;
        if ($redirect === null && !empty($context['invite_token'])) {
            $redirect = '/invitation/accept?token=' . rawurlencode((string)$context['invite_token']);
        }
        if ($redirect === null && !empty($context['event_token'])) {
            $redirect = '/rsvp/' . rawurlencode((string)$context['event_token']);
        }
        if ($redirect === null) {
            $redirect = '/profile/edit';
        }

        $response = [
            'success' => true,
            'user_id' => $userId,
            'redirect' => $redirect,
            'invite_token' => $context['invite_token'] ?? null,
            'event_token' => $context['event_token'] ?? null,
            'invite_channel' => $context['invite_channel'] ?? null,
        ];

        $this->clearContext();

        return $response;
    }

    /**
     * @param array<string,mixed> $metadata
     * @param array<string,string> $requestParams
     * @return array{success:bool,url?:string,message?:string}
     */
    private function buildAuthorizationRequestUrl(array $metadata, array $requestParams): array
    {
        $parEndpoint = $metadata['par_endpoint'] ?? null;
        if ($parEndpoint !== null) {
            $parResult = $this->pushAuthorizationRequest($metadata, $requestParams);
            if (!$parResult['success']) {
                return [
                    'success' => false,
                    'message' => $parResult['message'] ?? 'Unable to push authorization request.',
                ];
            }

            $query = [
                'client_id' => $this->clientId(),
                'request_uri' => (string)$parResult['request_uri'],
            ];

            return [
                'success' => true,
                'url' => $metadata['authorization_endpoint'] . '?' . http_build_query($query),
            ];
        }

        if (!empty($metadata['require_par'])) {
            return [
                'success' => false,
                'message' => 'Authorization server requires pushed authorization requests.',
            ];
        }

        return [
            'success' => true,
            'url' => $metadata['authorization_endpoint'] . '?' . http_build_query($requestParams),
        ];
    }

    /**
     * @param array<string,string> $requestParams
     * @return array{success:bool,request_uri?:string,message?:string}
     */
    private function pushAuthorizationRequest(array $metadata, array $requestParams): array
    {
        $endpoint = (string)($metadata['par_endpoint'] ?? '');
        $form = $requestParams;
        $options = [
            'headers' => ['Accept' => 'application/json'],
        ];

        try {
            $this->applyClientAuthentication($options, $form, $this->audienceFromUrl($endpoint));
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        try {
            $response = $this->http->post($endpoint, $options);
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => 'Unable to push authorization request: ' . $e->getMessage(),
            ];
        }

        $data = json_decode((string)$response->getBody(), true);
        if ($response->getStatusCode() >= 400) {
            $error = is_array($data)
                ? ($data['error_description'] ?? $data['error'] ?? 'OAuth error')
                : 'OAuth error';

            return [
                'success' => false,
                'message' => 'Authorization request rejected: ' . $error,
            ];
        }

        if (!is_array($data) || !isset($data['request_uri'])) {
            return [
                'success' => false,
                'message' => 'Authorization server response missing request_uri.',
            ];
        }

        return [
            'success' => true,
            'request_uri' => (string)$data['request_uri'],
        ];
    }

    /**
     * Retrieve an access token, refreshing as necessary.
     *
     * @return array{success:bool,access_token?:string,did?:string,handle?:string,message?:string,needs_reauth?:bool}
     */
    public function getAccessToken(int $userId, bool $forceRefresh = false): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Bluesky OAuth is not enabled.',
            ];
        }

        $identity = $this->fetchIdentityRow($userId);
        if ($identity === null) {
            return [
                'success' => false,
                'message' => 'No Bluesky OAuth identity found.',
            ];
        }

        $needsReauth = (bool)($identity['needs_reauth'] ?? false);
        if ($needsReauth) {
            return [
                'success' => false,
                'needs_reauth' => true,
                'message' => 'Bluesky authorization expired. Please reauthorize.',
            ];
        }

        $accessToken = $this->decrypt($identity['oauth_access_token'] ?? null);
        $refreshToken = $this->decrypt($identity['oauth_refresh_token'] ?? null);
        $expiresAt = $identity['oauth_token_expires_at'] ?? null;

        $shouldRefresh = $forceRefresh;
        if (!$shouldRefresh && $expiresAt !== null && $expiresAt !== '') {
            $shouldRefresh = strtotime((string)$expiresAt) <= time() + 60;
        }

        if ($accessToken === null || $accessToken === '' || $shouldRefresh) {
            if ($refreshToken === null || $refreshToken === '') {
                $this->markNeedsReauth($userId, 'Missing refresh token');
                return [
                    'success' => false,
                    'needs_reauth' => true,
                    'message' => 'Bluesky authorization expired. Please reauthorize.',
                ];
            }

            $refreshResult = $this->refreshAccessTokenInternal($userId, $identity, $refreshToken);
            if (!$refreshResult['success']) {
                return $refreshResult;
            }

            $accessToken = $refreshResult['access_token'];
        }

        return [
            'success' => true,
            'access_token' => $accessToken,
            'did' => (string)($identity['did'] ?? $identity['at_protocol_did'] ?? ''),
            'handle' => (string)($identity['handle'] ?? $identity['at_protocol_handle'] ?? ''),
        ];
    }

    /**
     * Mark the identity as requiring reauthorization.
     */
    public function markNeedsReauth(int $userId, string $message = ''): void
    {
        $stmt = $this->database->pdo()->prepare('
            UPDATE member_identities
            SET needs_reauth = 1,
                oauth_last_error = :error,
                updated_at = NOW()
            WHERE user_id = :user_id
        ');
        $stmt->execute([
            ':error' => $message !== '' ? substr($message, 0, 250) : null,
            ':user_id' => $userId,
        ]);
    }

    /**
     * @return array{connected:bool,needs_reauth:bool,handle?:string,did?:string,expires_at?:string}
     */
    public function getIdentityStatus(int $userId): array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT did, handle, oauth_token_expires_at, needs_reauth
             FROM member_identities
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return [
                'connected' => false,
                'needs_reauth' => false,
            ];
        }

        $expiresAt = $row['oauth_token_expires_at'] ?? null;
        $needsReauth = (bool)($row['needs_reauth'] ?? false);

        if (!$needsReauth && $expiresAt !== null && $expiresAt !== '') {
            $needsReauth = strtotime((string)$expiresAt) <= time();
        }

        return [
            'connected' => true,
            'needs_reauth' => $needsReauth,
            'handle' => $row['handle'] ?? null,
            'did' => $row['did'] ?? null,
            'expires_at' => $expiresAt,
        ];
    }

    public function shouldForceOAuthForInvite(): bool
    {
        return $this->forceForInvites();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getContext(): ?array
    {
        return $_SESSION[self::CONTEXT_SESSION_KEY] ?? null;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function storeContext(array $context): void
    {
        $_SESSION[self::CONTEXT_SESSION_KEY] = $context;
    }

    private function clearContext(): void
    {
        unset($_SESSION[self::CONTEXT_SESSION_KEY]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchIdentityRow(int $userId): ?array
    {
        $stmt = $this->database->pdo()->prepare('
            SELECT *
            FROM member_identities
            WHERE user_id = :user_id
              AND (oauth_provider = :provider OR verification_method = :method)
            LIMIT 1
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':provider' => self::PROVIDER_NAME,
            ':method' => 'oauth',
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function decrypt(?string $value): ?string
    {
        if ($this->encryptor === null || $value === null || $value === '') {
            return null;
        }

        return $this->encryptor->decrypt($value);
    }

    /**
     * @return array{authorization_endpoint:string,token_endpoint:string}
     */
    private function metadata(): array
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }

        $metadataUrl = (string)($this->config['metadata_url'] ?? '');
        if ($metadataUrl === '') {
            throw new RuntimeException('Bluesky OAuth metadata_url is not configured.');
        }

        try {
            $response = $this->http->get($metadataUrl, [
                'headers' => ['Accept' => 'application/json'],
            ]);
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('Unable to load OAuth metadata (HTTP ' . $response->getStatusCode() . ').');
            }

            $data = json_decode((string)$response->getBody(), true);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Unable to load OAuth metadata: ' . $e->getMessage());
        }

        if (
            !is_array($data) ||
            !isset($data['authorization_endpoint'], $data['token_endpoint'])
        ) {
            throw new RuntimeException('OAuth metadata is missing required endpoints.');
        }

        $this->metadata = [
            'authorization_endpoint' => (string)$data['authorization_endpoint'],
            'token_endpoint' => (string)$data['token_endpoint'],
            'par_endpoint' => isset($data['pushed_authorization_request_endpoint'])
                ? (string)$data['pushed_authorization_request_endpoint']
                : null,
            'require_par' => (bool)($data['require_pushed_authorization_requests'] ?? false),
        ];

        return $this->metadata;
    }

    /**
     * @return array{success:bool,access_token?:string,refresh_token?:string|null,expires_in?:int|null,scope?:string|null,raw?:array<string,mixed>,message?:string}
     */
    private function exchangeCode(string $code, string $codeVerifier): array
    {
        try {
            $metadata = $this->metadata();
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $codeVerifier,
            'client_id' => $this->clientId(),
        ];

        $tokenResult = $this->performTokenRequest($metadata['token_endpoint'], $form);
        if (isset($tokenResult['error'])) {
            return [
                'success' => false,
                'message' => 'Token exchange failed: ' . $tokenResult['error'],
            ];
        }

        $status = (int)($tokenResult['status'] ?? 500);
        $data = $tokenResult['data'];
        if ($status >= 400) {
            $error = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'OAuth error') : 'OAuth error';
            return [
                'success' => false,
                'message' => 'Token exchange failed: ' . $error,
            ];
        }

        if (!is_array($data) || !isset($data['access_token'])) {
            return [
                'success' => false,
                'message' => 'Token response missing access_token.',
            ];
        }

        return [
            'success' => true,
            'access_token' => (string)$data['access_token'],
            'refresh_token' => isset($data['refresh_token']) ? (string)$data['refresh_token'] : null,
            'expires_in' => isset($data['expires_in']) ? (int)$data['expires_in'] : null,
            'scope' => isset($data['scope']) ? (string)$data['scope'] : null,
            'raw' => $data,
        ];
    }

    /**
     * @param array<string,mixed> $tokenResponse
     * @return array<string,mixed>
     */
    private function fetchIdentity(string $accessToken, array $tokenResponse): array
    {
        $apiBase = rtrim((string)($this->config['api_base'] ?? self::DEFAULT_API_BASE), '/');
        $endpoint = $apiBase . '/xrpc/com.atproto.server.getSession';

        try {
            $response = $this->http->get($endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                return $tokenResponse;
            }

            $data = json_decode((string)$response->getBody(), true);
            return is_array($data) ? $data : $tokenResponse;
        } catch (GuzzleException $e) {
            return $tokenResponse;
        }
    }

    /**
     * @throws RuntimeException
     */
    private function resolveUserId(string $did, string $handle, string $displayName, bool $reauthorize): int
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('SELECT user_id FROM member_identities WHERE at_protocol_did = :did OR did = :did LIMIT 1');
        $stmt->execute([':did' => $did]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $currentUserId = (int)($this->auth->currentUserId() ?? 0);

        if ($row !== false) {
            $ownerId = (int)$row['user_id'];
            if ($currentUserId > 0 && $ownerId !== $currentUserId) {
                throw new RuntimeException('This Bluesky account is already connected to another member.');
            }

            return $ownerId;
        }

        if ($currentUserId > 0) {
            return $currentUserId;
        }

        if ($reauthorize) {
            throw new RuntimeException('No existing account was found for reauthorization.');
        }

        return $this->createUserFromIdentity($did, $handle, $displayName);
    }

    private function createUserFromIdentity(string $did, string $handle, string $displayName): int
    {
        $normalizedHandle = $this->normalizeHandle($handle);
        $usernameBase = $normalizedHandle !== '' ? $normalizedHandle : $this->generateUsernameFromDid($did);
        $username = $usernameBase;
        $email = $this->buildSyntheticEmail($did);
        $password = bin2hex(random_bytes(12));

        $attempts = 0;
        do {
            $attempts++;
            $result = $this->auth->register([
                'display_name' => $displayName !== '' ? $displayName : $username,
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ]);

            if ($result['success']) {
                $userId = (int)($result['user_id'] ?? 0);
                $this->activateUser($userId);
                return $userId;
            }

            $username = $usernameBase . '-' . $attempts . random_int(10, 99);
        } while ($attempts < 5);

        throw new RuntimeException('Unable to create a new member account for this Bluesky identity.');
    }

    private function activateUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $stmt = $this->database->pdo()->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => 'active',
            ':id' => $userId,
        ]);
    }

    private function upsertIdentity(
        int $userId,
        string $did,
        string $handle,
        string $accessToken,
        ?string $refreshToken,
        int $expiresIn,
        string $scope,
        array $profile,
        array $tokenResponse
    ): void {
        if ($this->encryptor === null) {
            throw new RuntimeException('Bluesky token encryption is not configured.');
        }

        $encryptor = $this->encryptor;
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('SELECT id FROM member_identities WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $exists = $stmt->fetch(\PDO::FETCH_ASSOC) !== false;

        $expiresAt = $expiresIn > 0 ? date('Y-m-d H:i:s', time() + $expiresIn - 30) : null;
        $encryptedAccess = $encryptor->encrypt($accessToken);
        $encryptedRefresh = $refreshToken !== null ? $encryptor->encrypt($refreshToken) : null;

        try {
            $metadata = json_encode([
                'profile' => $profile,
                'token' => $tokenResponse,
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $metadata = json_encode([
                'profile' => $profile,
                'token' => $tokenResponse,
            ]);
        }

        if ($exists) {
            $stmt = $pdo->prepare('
                UPDATE member_identities
                SET did = :did,
                    handle = :handle,
                    at_protocol_did = :did,
                    at_protocol_handle = :handle,
                    verification_method = :verification_method,
                    oauth_provider = :provider,
                    oauth_scopes = :scopes,
                    oauth_access_token = :access_token,
                    oauth_refresh_token = :refresh_token,
                    oauth_token_expires_at = :expires_at,
                    oauth_metadata = :metadata,
                    oauth_connected_at = NOW(),
                    needs_reauth = 0,
                    oauth_last_error = NULL,
                    is_verified = 1,
                    updated_at = NOW()
                WHERE user_id = :user_id
            ');
        } else {
            $userStmt = $pdo->prepare('SELECT email, display_name FROM users WHERE id = :id LIMIT 1');
            $userStmt->execute([':id' => $userId]);
            $userRow = $userStmt->fetch(\PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare('
                INSERT INTO member_identities (
                    user_id,
                    email,
                    display_name,
                    did,
                    handle,
                    at_protocol_did,
                    at_protocol_handle,
                    verification_method,
                    oauth_provider,
                    oauth_scopes,
                    oauth_access_token,
                    oauth_refresh_token,
                    oauth_token_expires_at,
                    oauth_metadata,
                    oauth_connected_at,
                    needs_reauth,
                    is_verified,
                    created_at,
                    updated_at
                ) VALUES (
                    :user_id,
                    :email,
                    :display_name,
                    :did,
                    :handle,
                    :did,
                    :handle,
                    :verification_method,
                    :provider,
                    :scopes,
                    :access_token,
                    :refresh_token,
                    :expires_at,
                    :metadata,
                    NOW(),
                    0,
                    1,
                    NOW(),
                    NOW()
                )
            ');

            $stmt->bindValue(':email', $userRow['email'] ?? $this->buildSyntheticEmail($did));
            $stmt->bindValue(':display_name', $userRow['display_name'] ?? $handle);
        }

        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':did', $did);
        $stmt->bindValue(':handle', $handle);
        $stmt->bindValue(':verification_method', 'oauth');
        $stmt->bindValue(':provider', self::PROVIDER_NAME);
        $stmt->bindValue(':scopes', $scope);
        $stmt->bindValue(':access_token', $encryptedAccess);
        $stmt->bindValue(':refresh_token', $encryptedRefresh);
        $stmt->bindValue(':expires_at', $expiresAt);
        $stmt->bindValue(':metadata', $metadata);

        $stmt->execute();
    }

    /**
     * Refresh access tokens using the stored refresh token.
     *
     * @return array{success:bool,access_token?:string,message?:string,needs_reauth?:bool}
     */
    private function refreshAccessTokenInternal(int $userId, array $identity, string $refreshToken): array
    {
        try {
            $metadata = $this->metadata();
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $form = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
        ];

        $tokenResult = $this->performTokenRequest($metadata['token_endpoint'], $form);
        if (isset($tokenResult['error'])) {
            $this->markNeedsReauth($userId, $tokenResult['error']);
            return [
                'success' => false,
                'needs_reauth' => true,
                'message' => 'Failed to refresh Bluesky authorization. Please reauthorize.',
            ];
        }

        $refreshStatus = (int)($tokenResult['status'] ?? 500);
        $data = $tokenResult['data'];
        if ($refreshStatus >= 400) {
            $error = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'OAuth error') : 'OAuth error';
            $this->markNeedsReauth($userId, $error);
            return [
                'success' => false,
                'needs_reauth' => true,
                'message' => 'Bluesky authorization expired. Please reauthorize.',
            ];
        }

        if (!is_array($data) || !isset($data['access_token'])) {
            $this->markNeedsReauth($userId, 'refresh_missing_access_token');
            return [
                'success' => false,
                'needs_reauth' => true,
                'message' => 'Bluesky authorization expired. Please reauthorize.',
            ];
        }

        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : 0;
        $expiresAt = $expiresIn > 0 ? date('Y-m-d H:i:s', time() + $expiresIn - 30) : null;

        $encryptedAccess = $this->encryptor?->encrypt((string)$data['access_token']) ?? null;
        $encryptedRefresh = isset($data['refresh_token'])
            ? ($this->encryptor?->encrypt((string)$data['refresh_token']) ?? null)
            : ($identity['oauth_refresh_token'] ?? null);

        $stmt = $this->database->pdo()->prepare('
            UPDATE member_identities
            SET oauth_access_token = :access,
                oauth_refresh_token = :refresh,
                oauth_token_expires_at = :expires_at,
                needs_reauth = 0,
                oauth_last_error = NULL,
                updated_at = NOW()
            WHERE user_id = :user_id
        ');
        $stmt->execute([
            ':access' => $encryptedAccess,
            ':refresh' => $encryptedRefresh,
            ':expires_at' => $expiresAt,
            ':user_id' => $userId,
        ]);

        return [
            'success' => true,
            'access_token' => (string)$data['access_token'],
        ];
    }

    private function buildClientAssertion(string $audience): string
    {
        $privateKey = (string)($this->config['client_private_key'] ?? '');
        if ($privateKey === '') {
            throw new RuntimeException('client_private_key is required for private_key_jwt authentication.');
        }

        $now = time();
        $payload = [
            'iss' => $this->clientId(),
            'sub' => $this->clientId(),
            'aud' => $audience,
            'iat' => $now,
            'exp' => $now + 300,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $kid = $this->config['client_key_id'] ?? null;

        return JWT::encode($payload, $privateKey, 'RS256', $kid ?: null);
    }

    private function performTokenRequest(string $endpoint, array $form): array
    {
        $initial = $this->sendTokenRequest($endpoint, $form, null);
        if (isset($initial['error'])) {
            return $initial;
        }

        $status = (int)($initial['status'] ?? 500);
        $nonce = (string)($initial['nonce'] ?? '');

        if ($status >= 400 && $nonce !== '') {
            $retry = $this->sendTokenRequest($endpoint, $form, $nonce);
            return $retry;
        }

        return $initial;
    }

    private function sendTokenRequest(string $endpoint, array $form, ?string $nonce): array
    {
        $options = [
            'form_params' => $form,
            'headers' => [
                'Accept' => 'application/json',
                'DPoP' => $this->buildDpopProof($endpoint, 'POST', $nonce),
            ],
        ];

        $this->applyClientAuthentication($options, $form, $this->audienceFromUrl($endpoint));

        try {
            $response = $this->http->post($endpoint, $options);
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }

        $data = json_decode((string)$response->getBody(), true);

        return [
            'status' => $response->getStatusCode(),
            'data' => $data,
            'nonce' => $response->getHeaderLine('DPoP-Nonce'),
        ];
    }

    private function buildDpopProof(string $url, string $method, ?string $nonce = null): string
    {
        $privateKey = (string)($this->config['client_private_key'] ?? '');
        if ($privateKey === '') {
            throw new RuntimeException('client_private_key is required for DPoP authentication.');
        }

        $payload = [
            'htu' => $url,
            'htm' => strtoupper($method),
            'iat' => time(),
            'jti' => bin2hex(random_bytes(16)),
        ];

        if ($nonce !== null) {
            $payload['nonce'] = $nonce;
        }

        $header = [
            'typ' => 'dpop+jwt',
            'alg' => 'RS256',
            'jwk' => $this->publicJwk(),
        ];

        return JWT::encode($payload, $privateKey, 'RS256', null, $header);
    }

    private function applyClientAuthentication(array &$options, array &$formParams, string $audience): void
    {
        $authMethod = strtolower((string)($this->config['token_endpoint_auth_method'] ?? 'private_key_jwt'));

        switch ($authMethod) {
            case 'client_secret_basic':
                $options['auth'] = [$this->clientId(), $this->clientSecret()];
                break;
            case 'client_secret_post':
                $formParams['client_secret'] = $this->clientSecret();
                break;
            case 'private_key_jwt':
            default:
                $formParams['client_assertion_type'] = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
                $formParams['client_assertion'] = $this->buildClientAssertion($audience);
                break;
        }

        $options['form_params'] = $formParams;
    }

    private function audienceFromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        return $parts['scheme'] . '://' . $parts['host'];
    }

    /**
     * @return array<string,string>
     */
    private function publicJwk(): array
    {
        if ($this->publicJwk !== null) {
            return $this->publicJwk;
        }

        $privateKey = (string)($this->config['client_private_key'] ?? '');
        if ($privateKey === '') {
            throw new RuntimeException('client_private_key is required to derive JWKS.');
        }

        $resource = openssl_pkey_get_private($privateKey);
        if ($resource === false) {
            throw new RuntimeException('Unable to parse client private key.');
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Invalid RSA private key.');
        }

        $kid = (string)($this->config['client_key_id'] ?? '');

        $jwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];

        if ($kid !== '') {
            $jwk['kid'] = $kid;
        }

        $this->publicJwk = $jwk;

        return $jwk;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function clientId(): string
    {
        $clientId = (string)($this->config['client_id'] ?? '');
        if ($clientId === '') {
            throw new RuntimeException('Bluesky OAuth client_id is not configured.');
        }

        return $clientId;
    }

    private function clientSecret(): string
    {
        $secret = (string)($this->config['client_secret'] ?? '');
        if ($secret === '') {
            throw new RuntimeException('Bluesky OAuth client_secret is not configured.');
        }

        return $secret;
    }

    private function redirectUri(): string
    {
        $redirect = (string)($this->config['redirect_uri'] ?? '');
        if ($redirect === '') {
            $redirect = rtrim((string)app_config('app.url', 'https://social.elonara.com'), '/') . '/auth/bluesky/callback';
        }

        return $redirect;
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function normalizeHandle(string $handle): string
    {
        $handle = ltrim($handle, '@');
        $handle = strtolower($handle);
        $handle = preg_replace('/[^a-z0-9_.-]+/', '', $handle ?? '') ?? '';

        return trim($handle);
    }

    private function generateUsernameFromDid(string $did): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $did) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'member';
        }

        return substr('bsky-' . $slug, 0, 24);
    }

    private function buildSyntheticEmail(string $did): string
    {
        $local = str_replace([':', '.'], '-', $did);
        return $local . '@identity.bsky';
    }
}
