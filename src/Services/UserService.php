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
            'SELECT id, username, email, display_name, bio, avatar_url, cover_url, cover_alt, role, created_at, updated_at
             FROM vt_users
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
             FROM vt_users
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
                $params[':avatar_url'] = $uploadResult['url'];
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
                $params[':cover_url'] = $uploadResult['url'];
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

        $sql = 'UPDATE vt_users SET ' . implode(', ', $updates) . ' WHERE id = :id LIMIT 1';
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
             FROM vt_conversations
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
             FROM vt_conversation_replies r
             JOIN vt_conversations c ON r.conversation_id = c.id
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
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vt_conversations WHERE author_id = :id');
        $stmt->execute([':id' => $userId]);
        $conversationCount = (int)$stmt->fetchColumn();

        // Count replies
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vt_conversation_replies WHERE author_id = :id');
        $stmt->execute([':id' => $userId]);
        $replyCount = (int)$stmt->fetchColumn();

        // Count communities (as member)
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vt_community_members WHERE user_id = :id');
        $stmt->execute([':id' => $userId]);
        $communityCount = (int)$stmt->fetchColumn();

        return [
            'conversations' => $conversationCount,
            'replies' => $replyCount,
            'communities' => $communityCount,
        ];
    }
}
