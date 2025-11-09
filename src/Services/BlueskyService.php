<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Support\BlueskyLogger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Bluesky AT Protocol Service
 * Handles authentication, follower retrieval, and API communication with Bluesky
 */
final class BlueskyService
{
    private const PUBLIC_API_BASE = 'https://public.api.bsky.app';
    private const BSKY_SOCIAL_BASE = 'https://bsky.social';

    private Client $client;

    public function __construct(
        private Database $database,
        private ?BlueskyOAuthService $oauth = null
    ) {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Create session with Bluesky using identifier and password
     *
     * @param string $identifier Handle (e.g., 'user.bsky.social') or email
     * @param string $password App password or account password
     * @return array{success: bool, did?: string, handle?: string, accessJwt?: string, refreshJwt?: string, message?: string}
     */
    public function createSession(string $identifier, string $password): array
    {
        try {
            $response = $this->client->post(self::BSKY_SOCIAL_BASE . '/xrpc/com.atproto.server.createSession', [
                'json' => [
                    'identifier' => $identifier,
                    'password' => $password,
                ],
            ]);

            $data = json_decode((string)$response->getBody(), true);

            if (!isset($data['did'], $data['handle'], $data['accessJwt'], $data['refreshJwt'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid response from Bluesky API',
                ];
            }

            return [
                'success' => true,
                'did' => $data['did'],
                'handle' => $data['handle'],
                'accessJwt' => $data['accessJwt'],
                'refreshJwt' => $data['refreshJwt'],
            ];

        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $body = (string)$e->getResponse()->getBody();
                $decoded = json_decode($body, true);
                $message = $decoded['message'] ?? $message;
            }

            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $message,
            ];
        }
    }

    /**
     * Store Bluesky credentials for a user
     */
    public function storeCredentials(int $userId, string $did, string $handle, string $accessJwt, string $refreshJwt): bool
    {
        $pdo = $this->database->pdo();

        // Check if identity exists
        $stmt = $pdo->prepare('SELECT id FROM member_identities WHERE user_id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare('
                UPDATE member_identities
                SET did = ?, handle = ?, access_jwt = ?, refresh_jwt = ?,
                    at_protocol_did = ?, at_protocol_handle = ?, at_protocol_pds = ?,
                    pds_url = ?, is_verified = 1, updated_at = NOW()
                WHERE user_id = ?
            ');
            return $stmt->execute([
                $did,
                $handle,
                $accessJwt,
                $refreshJwt,
                $did,
                $handle,
                self::BSKY_SOCIAL_BASE,
                self::BSKY_SOCIAL_BASE,
                $userId,
            ]);
        } else {
            // Get user email
            $userStmt = $pdo->prepare('SELECT email, display_name FROM users WHERE id = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Insert new
            $stmt = $pdo->prepare('
                INSERT INTO member_identities
                (user_id, email, display_name, did, handle, at_protocol_did, at_protocol_handle, at_protocol_pds, access_jwt, refresh_jwt, pds_url, is_verified, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ');
            return $stmt->execute([
                $userId,
                $user['email'],
                $user['display_name'],
                $did,
                $handle,
                $did,
                $handle,
                self::BSKY_SOCIAL_BASE,
                $accessJwt,
                $refreshJwt,
                self::BSKY_SOCIAL_BASE,
            ]);
        }
    }

    /**
     * Get stored credentials for a user (prefers OAuth tokens, falls back to legacy app-password tokens)
     *
     * @return array{did: string, handle: string, accessJwt: string, refreshJwt?: string, authSource: string}|null
     */
    public function getCredentials(int $userId): ?array
    {
        $resolved = $this->resolveCredentials($userId);
        return $resolved['success'] ? $resolved['credentials'] : null;
    }

    /**
     * Refresh the access token using refresh token
     *
     * @return array{success: bool, accessJwt?: string, refreshJwt?: string, message?: string}
     */
    public function refreshSession(int $userId): array
    {
        $credentials = $this->getCredentials($userId);
        if ($credentials === null) {
            return [
                'success' => false,
                'message' => 'No credentials found',
            ];
        }

        if (($credentials['authSource'] ?? '') === 'oauth') {
            if ($this->oauth === null) {
                return [
                    'success' => false,
                    'message' => 'OAuth service unavailable.',
                ];
            }

            $token = $this->oauth->getAccessToken($userId, true);
            if ($token['success'] ?? false) {
                return [
                    'success' => true,
                    'accessJwt' => (string)$token['access_token'],
                ];
            }

            return [
                'success' => false,
                'message' => $token['message'] ?? 'Unable to refresh OAuth token.',
            ];
        }

        try {
            $response = $this->client->post(self::BSKY_SOCIAL_BASE . '/xrpc/com.atproto.server.refreshSession', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $credentials['refreshJwt'],
                ],
            ]);

            $data = json_decode((string)$response->getBody(), true);

            if (isset($data['accessJwt'], $data['refreshJwt'])) {
                // Update stored credentials
                $this->storeCredentials(
                    $userId,
                    $data['did'] ?? $credentials['did'],
                    $data['handle'] ?? $credentials['handle'],
                    $data['accessJwt'],
                    $data['refreshJwt']
                );

                return [
                    'success' => true,
                    'accessJwt' => $data['accessJwt'],
                    'refreshJwt' => $data['refreshJwt'],
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid response from refresh endpoint',
            ];

        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => 'Failed to refresh session: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect Bluesky account for a user
     */
    public function disconnectAccount(int $userId): bool
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            UPDATE member_identities
            SET did = NULL,
                handle = NULL,
                access_jwt = NULL,
                refresh_jwt = NULL,
                oauth_access_token = NULL,
                oauth_refresh_token = NULL,
                oauth_token_expires_at = NULL,
                oauth_metadata = NULL,
                oauth_connected_at = NULL,
                needs_reauth = 0,
                oauth_last_error = NULL,
                oauth_provider = NULL,
                oauth_scopes = NULL,
                verification_method = :verification_method,
                is_verified = 0,
                updated_at = NOW()
            WHERE user_id = :user_id
        ');
        return $stmt->execute([
            ':verification_method' => 'none',
            ':user_id' => $userId,
        ]);
    }

    /**
     * Check if user has connected Bluesky account
     */
    public function isConnected(int $userId): bool
    {
        return $this->getCredentials($userId) !== null;
    }

    /**
     * Get followers for an actor (DID or handle)
     *
     * @param string $actor DID or handle
     * @param string|null $cursor Pagination cursor
     * @param int $limit Number of results per page (max 100)
     * @return array{success: bool, followers?: array, cursor?: string|null, message?: string}
     */
    public function getFollowers(string $actor, ?string $cursor = null, int $limit = 100): array
    {
        try {
            $params = [
                'actor' => $actor,
                'limit' => min($limit, 100),
            ];

            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $url = self::PUBLIC_API_BASE . '/xrpc/app.bsky.graph.getFollowers?' . http_build_query($params);
            $response = $this->client->get($url);
            $data = json_decode((string)$response->getBody(), true);

            return [
                'success' => true,
                'followers' => $data['followers'] ?? [],
                'cursor' => $data['cursor'] ?? null,
            ];

        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch followers: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get accounts that an actor follows
     *
     * @param string $actor DID or handle
     * @param string|null $cursor Pagination cursor
     * @param int $limit Number of results per page (max 100)
     * @return array{success: bool, follows?: array, cursor?: string|null, message?: string}
     */
    public function getFollows(string $actor, ?string $cursor = null, int $limit = 100): array
    {
        try {
            $params = [
                'actor' => $actor,
                'limit' => min($limit, 100),
            ];

            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $url = self::PUBLIC_API_BASE . '/xrpc/app.bsky.graph.getFollows?' . http_build_query($params);
            $response = $this->client->get($url);
            $data = json_decode((string)$response->getBody(), true);

            return [
                'success' => true,
                'follows' => $data['follows'] ?? [],
                'cursor' => $data['cursor'] ?? null,
            ];

        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch follows: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sync and cache all followers for a user
     */
    public function syncFollowers(int $userId): array
    {
        $credentialResult = $this->resolveCredentials($userId);
        if (!$credentialResult['success']) {
            return [
                'success' => false,
                'message' => $credentialResult['message'] ?? 'Bluesky account not connected',
                'needs_reauth' => $credentialResult['needs_reauth'] ?? false,
            ];
        }

        $credentials = $credentialResult['credentials'];

        $allFollowers = [];
        $cursor = null;
        $maxPages = 10; // Limit to prevent excessive API calls
        $page = 0;

        do {
            $result = $this->getFollowers($credentials['did'], $cursor);

            if (!$result['success']) {
                break;
            }

            $allFollowers = array_merge($allFollowers, $result['followers'] ?? []);
            $cursor = $result['cursor'] ?? null;
            $page++;

        } while ($cursor !== null && $page < $maxPages);

        // Store in social table
        $this->cacheFollowers($userId, $credentials['did'], $credentials['handle'], $allFollowers);

        return [
            'success' => true,
            'count' => count($allFollowers),
            'followers' => $allFollowers,
            'auth_source' => $credentials['authSource'] ?? 'legacy',
        ];
    }

    /**
     * Cache followers in database
     */
    private function cacheFollowers(int $userId, string $did, string $handle, array $followers): void
    {
        $pdo = $this->database->pdo();

        $connectionData = json_encode([
            'followers' => $followers,
            'synced_at' => date('Y-m-d H:i:s'),
            'count' => count($followers),
        ]);

        // Check if record exists
        $stmt = $pdo->prepare('SELECT id FROM social WHERE user_id = ?');
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            // Update
            $stmt = $pdo->prepare('
                UPDATE social
                SET at_protocol_handle = ?, bluesky_did = ?, connection_data = ?,
                    last_sync = NOW(), connection_status = ?
                WHERE user_id = ?
            ');
            $stmt->execute([$handle, $did, $connectionData, 'active', $userId]);
        } else {
            // Insert
            $stmt = $pdo->prepare('
                INSERT INTO social
                (user_id, at_protocol_handle, bluesky_did, connection_data, connection_status, last_sync, created_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ');
            $stmt->execute([$userId, $handle, $did, $connectionData, 'active']);
        }
    }

    /**
     * Get cached followers for a user
     *
     * @return array{success: bool, followers?: array, synced_at?: string, message?: string}
     */
    public function getCachedFollowers(int $userId): array
    {
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            SELECT connection_data, last_sync
            FROM social
            WHERE user_id = ? AND connection_status = ?
        ');
        $stmt->execute([$userId, 'active']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result === false) {
            return [
                'success' => false,
                'message' => 'No cached followers found',
            ];
        }

        $data = json_decode((string)$result['connection_data'], true);

        return [
            'success' => true,
            'followers' => $data['followers'] ?? [],
            'synced_at' => $result['last_sync'],
        ];
    }

    /**
     * Fetch a public profile by handle or DID.
     *
     * @return array<string,mixed>|null
     */
    public function getProfile(string $actor): ?array
    {
        try {
            $response = $this->client->get(self::PUBLIC_API_BASE . '/xrpc/app.bsky.actor.getProfile', [
                'query' => ['actor' => $actor],
            ]);

            $data = json_decode((string)$response->getBody(), true);
            return is_array($data) ? $data : null;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Create a post on Bluesky with mentions
     *
     * @param int $userId User ID to post as
     * @param string $text Post text
     * @param array<array{handle: string, did: string}> $mentions Array of mentions with handle and did
     * @return array{success: bool, uri?: string, cid?: string, message?: string}
     */
    public function createPost(int $userId, string $text, array $mentions = []): array
    {
        $credentialResult = $this->resolveCredentials($userId);
        if (!$credentialResult['success']) {
            return [
                'success' => false,
                'message' => $credentialResult['message'] ?? 'Bluesky account not connected',
                'needs_reauth' => $credentialResult['needs_reauth'] ?? false,
            ];
        }

        $credentials = $credentialResult['credentials'];

        // Try to create post, refresh token if expired
        $result = $this->attemptCreatePost($userId, $credentials, $text, $mentions);

        // If token expired, refresh and retry once
        if (!$result['success'] && str_contains($result['message'] ?? '', 'ExpiredToken')) {
            $authSource = $credentials['authSource'] ?? 'legacy';
            if ($authSource === 'oauth' && $this->oauth !== null) {
                $refreshResult = $this->oauth->getAccessToken($userId, true);
                if ($refreshResult['success'] ?? false) {
                    $credentials['accessJwt'] = (string)$refreshResult['access_token'];
                    $result = $this->attemptCreatePost($userId, $credentials, $text, $mentions);
                } else {
                    return [
                        'success' => false,
                        'message' => $refreshResult['message'] ?? 'Token expired and refresh failed. Please reauthorize your Bluesky account.',
                    ];
                }
            } else {
                $refreshResult = $this->refreshSession($userId);
                if ($refreshResult['success']) {
                    $credentials['accessJwt'] = $refreshResult['accessJwt'];
                    $credentials['refreshJwt'] = $refreshResult['refreshJwt'] ?? $credentials['refreshJwt'] ?? '';
                    $result = $this->attemptCreatePost($userId, $credentials, $text, $mentions);
                } else {
                    return [
                        'success' => false,
                        'message' => 'Token expired and refresh failed. Please reconnect your Bluesky account.',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * @return array{
     *   success:bool,
     *   credentials?:array{
     *     did:string,
     *     handle:string,
     *     accessJwt:string,
     *     refreshJwt?:string,
     *     authSource:string
     *   },
     *   needs_reauth?:bool,
     *   message?:string
     * }
     */
    private function resolveCredentials(int $userId): array
    {
        $oauthEnabled = $this->oauth !== null && $this->oauth->isEnabled();
        $fallbackAllowed = !$oauthEnabled || $this->oauth->allowLegacyFallback();
        $lastError = null;

        if ($oauthEnabled) {
            $tokenResult = $this->oauth->getAccessToken($userId);
            if ($tokenResult['success'] ?? false) {
                return [
                    'success' => true,
                    'credentials' => [
                        'did' => (string)$tokenResult['did'],
                        'handle' => (string)$tokenResult['handle'],
                        'accessJwt' => (string)$tokenResult['access_token'],
                        'authSource' => 'oauth',
                    ],
                ];
            }

            if (($tokenResult['needs_reauth'] ?? false) === true) {
                $lastError = $tokenResult['message'] ?? 'Bluesky authorization expired. Please reauthorize.';
                if (!$fallbackAllowed) {
                    $this->logCredentialIssue('oauth_needs_reauth', $userId, $lastError);
                    return [
                        'success' => false,
                        'needs_reauth' => true,
                        'message' => $lastError,
                    ];
                }
            } else {
                $lastError = $tokenResult['message'] ?? 'Unable to retrieve Bluesky OAuth credentials.';
            }
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            SELECT did, handle, access_jwt as accessJwt, refresh_jwt as refreshJwt
            FROM member_identities
            WHERE user_id = ? AND is_verified = 1
        ');
        $stmt->execute([$userId]);
        $legacy = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($legacy !== false && (string)($legacy['accessJwt'] ?? '') !== '' && $fallbackAllowed) {
            $legacy['authSource'] = 'legacy';
            if ($oauthEnabled) {
                $this->logCredentialIssue('legacy_fallback', $userId, 'Using app-password credentials for Bluesky jobs.');
            }
            return [
                'success' => true,
                'credentials' => $legacy,
            ];
        }

        $message = $lastError ?? 'Bluesky account not connected.';
        return [
            'success' => false,
            'needs_reauth' => $oauthEnabled,
            'message' => $message,
        ];
    }

    private function logCredentialIssue(string $context, int $userId, string $message): void
    {
        BlueskyLogger::log(sprintf('[BlueskyService] %s user_id=%d message=%s', $context, $userId, $message));
    }

    /**
     * Attempt to create a post (internal helper)
     */
    private function attemptCreatePost(int $userId, array $credentials, string $text, array $mentions): array
    {
        try {
            $facets = [];

            // Build facets for mentions
            foreach ($mentions as $mention) {
                $handle = $mention['handle'] ?? '';
                $did = $mention['did'] ?? '';

                if ($handle === '' || $did === '') {
                    continue;
                }

                $mentionText = '@' . $handle;
                $byteStart = mb_strpos($text, $mentionText);

                if ($byteStart === false) {
                    continue;
                }

                // Convert character position to byte position
                $beforeText = mb_substr($text, 0, $byteStart);
                $byteStart = strlen($beforeText);
                $byteEnd = $byteStart + strlen($mentionText);

                $facets[] = [
                    'index' => [
                        'byteStart' => $byteStart,
                        'byteEnd' => $byteEnd,
                    ],
                    'features' => [
                        [
                            '$type' => 'app.bsky.richtext.facet#mention',
                            'did' => $did,
                        ],
                    ],
                ];
            }

            $record = [
                '$type' => 'app.bsky.feed.post',
                'text' => $text,
                'createdAt' => date('c'),
            ];

            if (!empty($facets)) {
                $record['facets'] = $facets;
            }

            $endpoint = self::BSKY_SOCIAL_BASE . '/xrpc/com.atproto.repo.createRecord';
            $nonce = null;

            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $response = $this->client->post($endpoint, [
                        'headers' => $this->buildResourceRequestHeaders($credentials, $endpoint, 'POST', $nonce),
                        'json' => [
                            'repo' => $credentials['did'],
                            'collection' => 'app.bsky.feed.post',
                            'record' => $record,
                        ],
                    ]);

                    $data = json_decode((string)$response->getBody(), true);
                    BlueskyLogger::log(sprintf('[BlueskyService] createPost success user_id=%d uri=%s', $userId, $data['uri'] ?? 'unknown'));

                    return [
                        'success' => true,
                        'uri' => $data['uri'] ?? null,
                        'cid' => $data['cid'] ?? null,
                    ];
                } catch (GuzzleException $e) {
                    $newNonce = $e->hasResponse() ? $e->getResponse()->getHeaderLine('DPoP-Nonce') : '';
                    if ($nonce === null && $newNonce !== '') {
                        $nonce = $newNonce;
                        continue;
                    }

                    $message = $this->formatBlueskyError($e);
                    BlueskyLogger::log(sprintf('[BlueskyService] createPost error user_id=%d message=%s', $userId, $message));

                    if ($this->oauth !== null && $this->isScopeError($message)) {
                        $this->oauth->markNeedsReauth($userId, 'missing_required_scopes');
                        return [
                            'success' => false,
                            'needs_reauth' => true,
                            'message' => 'Bluesky authorization is missing required permissions. Please reauthorize.',
                        ];
                    }

                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }
            }
        } catch (RuntimeException $e) {
            BlueskyLogger::log(sprintf('[BlueskyService] createPost dpop_error user_id=%d message=%s', $userId, $e->getMessage()));
            return [
                'success' => false,
                'message' => 'Unable to sign Bluesky request: ' . $e->getMessage(),
            ];
        }
    }

    private function formatBlueskyError(GuzzleException $e): string
    {
        $message = $e->getMessage();

        if ($e->hasResponse()) {
            $body = (string)$e->getResponse()->getBody();
            $decoded = json_decode($body, true);
            if (isset($decoded['error'])) {
                $message = $decoded['error'] . ': ' . ($decoded['message'] ?? '');
            }
        }

        return $message;
    }

    private function buildResourceRequestHeaders(array $credentials, string $url, string $method, ?string $nonce = null): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $credentials['accessJwt'],
        ];

        if (($credentials['authSource'] ?? '') === 'oauth') {
            if ($this->oauth === null) {
                throw new RuntimeException('OAuth service not available for DPoP signing.');
            }

            $headers['DPoP'] = $this->oauth->buildResourceProof($url, $method, $nonce);
        }

        return $headers;
    }

    private function isScopeError(string $message): bool
    {
        $lower = strtolower($message);
        return str_contains($lower, 'bad token scope') || str_contains($lower, 'invalidtoken');
    }
}
