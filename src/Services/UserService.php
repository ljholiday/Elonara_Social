<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

/**
 * User Service
 *
 * Handles user profile data and updates.
 */
final class UserService
{
    public function __construct(
        private Database $db,
        private ?ImageService $imageService = null
    ) {
    }

    /**
     * Get user by ID
     *
     * @return array<string, mixed>|null
     */
    public function getById(int $userId): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT id, username, email, display_name, bio, avatar_url, cover_url, cover_alt, role, status, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Get user by username
     *
     * @return array<string, mixed>|null
     */
    public function getByUsername(string $username): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT id, username, email, display_name, bio, avatar_url, cover_url, cover_alt, role, created_at, updated_at
             FROM users
             WHERE username = :username
             LIMIT 1'
        );
        $stmt->execute([':username' => $username]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Update user profile
     *
     * @param int $userId User ID
     * @param array{display_name?:string, bio?:string, avatar?:array, avatar_alt?:string, cover?:array, cover_alt?:string} $data
     * @return bool
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $user = $this->getById($userId);
        if ($user === null) {
            throw new \RuntimeException('User not found.');
        }

        $updates = [];
        $params = [':id' => $userId];

        // Update display name
        if (isset($data['display_name'])) {
            $displayName = trim($data['display_name']);
            if ($displayName !== '') {
                $updates[] = 'display_name = :display_name';
                $params[':display_name'] = $displayName;
            }
        }

        // Update bio
        if (isset($data['bio'])) {
            $bio = trim($data['bio']);
            $updates[] = 'bio = :bio';
            $params[':bio'] = $bio !== '' ? $bio : null;
        }

        // Handle avatar upload
        if ($this->imageService && !empty($data['avatar']) && !empty($data['avatar']['tmp_name'])) {
            $avatarAlt = trim((string)($data['avatar_alt'] ?? ''));
            if ($avatarAlt === '') {
                throw new \RuntimeException('Avatar alt-text is required for accessibility.');
            }

            try {
                // Delete old avatar variants if exists
                if (!empty($user['avatar_url'])) {
                    $this->imageService->deleteAllSizes($user['avatar_url']);
                }

                $uploadResult = $this->imageService->upload(
                    $data['avatar'],
                    $avatarAlt,
                    'profile',
                    'user',
                    $userId
                );

                if (!$uploadResult['success']) {
                    $errorMsg = $uploadResult['error'] ?? 'Failed to upload avatar.';
                    file_put_contents(dirname(__DIR__, 2) . '/debug.log', date('[Y-m-d H:i:s] ') . "Avatar upload failed: {$errorMsg}\n", FILE_APPEND);
                    throw new \RuntimeException($errorMsg);
                }

                $updates[] = 'avatar_url = :avatar_url';
                $params[':avatar_url'] = $uploadResult['urls'];
            } catch (\Throwable $e) {
                file_put_contents(dirname(__DIR__, 2) . '/debug.log', date('[Y-m-d H:i:s] ') . "Avatar upload exception: " . $e->getMessage() . "\n", FILE_APPEND);
                throw new \RuntimeException('Failed to upload avatar: ' . $e->getMessage());
            }
        }

        // Handle cover image upload
        if ($this->imageService && !empty($data['cover']) && !empty($data['cover']['tmp_name'])) {
            $coverAlt = trim((string)($data['cover_alt'] ?? ''));
            if ($coverAlt === '') {
                throw new \RuntimeException('Cover image alt-text is required for accessibility.');
            }

            try {
                // Delete old cover variants if exists
                if (!empty($user['cover_url'])) {
                    $this->imageService->deleteAllSizes($user['cover_url']);
                }

                $uploadResult = $this->imageService->upload(
                    $data['cover'],
                    $coverAlt,
                    'cover',
                    'user',
                    $userId
                );

                if (!$uploadResult['success']) {
                    $errorMsg = $uploadResult['error'] ?? 'Failed to upload cover image.';
                    file_put_contents(dirname(__DIR__, 2) . '/debug.log', date('[Y-m-d H:i:s] ') . "Cover upload failed: {$errorMsg}\n", FILE_APPEND);
                    throw new \RuntimeException($errorMsg);
                }

                $updates[] = 'cover_url = :cover_url';
                $params[':cover_url'] = $uploadResult['urls'];
                $updates[] = 'cover_alt = :cover_alt';
                $params[':cover_alt'] = $coverAlt;
            } catch (\Throwable $e) {
                file_put_contents(dirname(__DIR__, 2) . '/debug.log', date('[Y-m-d H:i:s] ') . "Cover upload exception: " . $e->getMessage() . "\n", FILE_APPEND);
                throw new \RuntimeException('Failed to upload cover image: ' . $e->getMessage());
            }
        }

        if (empty($updates)) {
            return true; // Nothing to update
        }

        $updates[] = 'updated_at = :updated_at';
        $params[':updated_at'] = date('Y-m-d H:i:s');

        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id LIMIT 1';
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get user's recent activity
     *
     * @return array<string, mixed>
     */
    public function getRecentActivity(int $userId, int $limit = 10): array
    {
        $activities = [];

        // Recent conversations
        $stmt = $this->db->pdo()->prepare(
            'SELECT id, title, slug, created_at, "conversation" as type
             FROM conversations
             WHERE author_id = :user_id
             ORDER BY created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent replies
        $stmt = $this->db->pdo()->prepare(
            'SELECT r.id, r.created_at, c.title, c.slug as conversation_slug, "reply" as type
             FROM conversation_replies r
             JOIN conversations c ON r.conversation_id = c.id
             WHERE r.author_id = :user_id
             ORDER BY r.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Merge and sort
        $activities = array_merge($conversations, $replies);
        usort($activities, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

        return array_slice($activities, 0, $limit);
    }

    /**
     * Get user stats
     *
     * @return array{conversations: int, replies: int, communities: int}
     */
    public function getStats(int $userId): array
    {
        $pdo = $this->db->pdo();

        // Count conversations
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE author_id = :id');
        $stmt->execute([':id' => $userId]);
        $conversationCount = (int)$stmt->fetchColumn();

        // Count replies
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM conversation_replies WHERE author_id = :id');
        $stmt->execute([':id' => $userId]);
        $replyCount = (int)$stmt->fetchColumn();

        // Count communities (as member)
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM community_members WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $communityCount = (int)$stmt->fetchColumn();

        return [
            'conversations' => $conversationCount,
            'replies' => $replyCount,
            'communities' => $communityCount,
        ];
    }

    /**
     * Retrieve a paginated collection for admin dashboards.
     *
     * @return array{users: array<int, array<string,mixed>>, total: int}
     */
    public function listForAdmin(string $search, int $limit, int $offset): array
    {
        $search = trim($search);
        $pdo = $this->db->pdo();

        $baseFrom = 'FROM users u
            LEFT JOIN member_identities mi ON mi.user_id = u.id
            LEFT JOIN user_profiles up ON up.user_id = u.id';

        $params = [];
        $where = '';

        if ($search !== '') {
            $likeValue = '%' . $search . '%';
            $searchFields = [
                'u.display_name',
                'u.username',
                'u.email',
                'mi.did',
                'mi.at_protocol_did',
                'mi.handle',
            ];

            $searchConditions = [];
            foreach ($searchFields as $field) {
                $placeholder = ':p' . count($params);
                $searchConditions[] = $field . ' LIKE ' . $placeholder;
                $params[$placeholder] = ['value' => $likeValue, 'type' => PDO::PARAM_STR];
            }

            if (ctype_digit($search)) {
                $placeholder = ':p' . count($params);
                $searchConditions[] = 'u.id = ' . $placeholder;
                $params[$placeholder] = ['value' => (int)$search, 'type' => PDO::PARAM_INT];
            }

            $where = ' WHERE (' . implode(' OR ', $searchConditions) . ')';
        }

        $countSql = 'SELECT COUNT(*) ' . $baseFrom . $where;
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $placeholder => $binding) {
            $countStmt->bindValue($placeholder, $binding['value'], $binding['type']);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $dataSql = 'SELECT
                u.id,
                u.display_name,
                u.username,
                u.email,
                u.role,
                u.status,
                u.created_at,
                COALESCE(NULLIF(mi.did, \'\'), NULLIF(mi.at_protocol_did, \'\')) AS did,
                CASE
                    WHEN mi.is_verified = 1 THEN 1
                    WHEN up.is_verified = 1 THEN 1
                    WHEN u.status = \'active\' THEN 1
                    ELSE 0
                END AS is_verified
            ' . $baseFrom . $where . '
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset';

        $dataStmt = $pdo->prepare($dataSql);
        foreach ($params as $placeholder => $binding) {
            $dataStmt->bindValue($placeholder, $binding['value'], $binding['type']);
        }
        $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $users = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'users' => $users !== false ? $users : [],
            'total' => $total,
        ];
    }

    /**
     * Fetch essential account details for admin operations.
     *
     * @return array<string,mixed>|null
     */
    public function getAdminUser(int $userId): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT
                u.id,
                u.email,
                u.display_name,
                u.username,
                u.status,
                u.role,
                COALESCE(NULLIF(mi.did, \'\'), NULLIF(mi.at_protocol_did, \'\')) AS did
             FROM users u
             LEFT JOIN member_identities mi ON mi.user_id = u.id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Activate a pending account.
     */
    public function activateUser(int $userId): bool
    {
        $stmt = $this->db->pdo()->prepare(
            "UPDATE users
             SET status = 'active', updated_at = :updated_at
             WHERE id = :id"
        );

        $stmt->execute([
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft delete while removing sensitive data.
     */
    public function deleteUser(int $userId): bool
    {
        $pdo = $this->db->pdo();

        try {
            $pdo->beginTransaction();

            $dependentTables = [
                'member_identities',
                'user_profiles',
                'sessions',
                'social',
                'password_reset_tokens',
                'email_verification_tokens',
            ];

            foreach ($dependentTables as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE user_id = :id");
                $stmt->execute([':id' => $userId]);
            }

            $placeholderEmail = sprintf('deleted+%d@users.social.elonara.invalid', $userId);
            $deletedName = sprintf('Deleted User #%d', $userId);

            $updateStmt = $pdo->prepare(
                "UPDATE users
                 SET status = 'deleted',
                     email = :email,
                     username = NULL,
                     display_name = :display_name,
                     bio = NULL,
                     avatar_url = NULL,
                     cover_url = NULL,
                     cover_alt = NULL,
                     updated_at = :updated_at
                 WHERE id = :id"
            );

            $updateStmt->execute([
                ':email' => $placeholderEmail,
                ':display_name' => $deletedName,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id' => $userId,
            ]);

            $pdo->commit();

            return $updateStmt->rowCount() > 0;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->logAdminError('delete_user', $userId, $e->getMessage());
            return false;
        }
    }

    private function logAdminError(string $context, int $userId, string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        $line = sprintf(
            '[%s] admin.users.%s user %d: %s%s',
            date('Y-m-d H:i:s'),
            $context,
            $userId,
            $message,
            PHP_EOL
        );
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}
