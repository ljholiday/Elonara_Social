<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

/**
 * Block Service
 *
 * Handles user blocking and unblocking functionality.
 */
final class BlockService
{
    public function __construct(
        private Database $db
    ) {
    }

    /**
     * Block a user
     *
     * @param int $blockerUserId User ID who is blocking
     * @param int $blockedUserId User ID being blocked
     * @param string|null $reason Optional reason for blocking
     * @return bool True if successfully blocked, false if already blocked
     * @throws \RuntimeException If trying to block oneself or if database error occurs
     */
    public function blockUser(int $blockerUserId, int $blockedUserId, ?string $reason = null): bool
    {
        // Prevent self-blocking
        if ($blockerUserId === $blockedUserId) {
            throw new \RuntimeException('Cannot block yourself.');
        }

        // Check if already blocked
        if ($this->isBlocked($blockerUserId, $blockedUserId)) {
            return false;
        }

        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO user_blocks (blocker_user_id, blocked_user_id, reason, created_at)
             VALUES (:blocker_user_id, :blocked_user_id, :reason, NOW())'
        );

        return $stmt->execute([
            ':blocker_user_id' => $blockerUserId,
            ':blocked_user_id' => $blockedUserId,
            ':reason' => $reason
        ]);
    }

    /**
     * Unblock a user
     *
     * @param int $blockerUserId User ID who is unblocking
     * @param int $blockedUserId User ID being unblocked
     * @return bool True if successfully unblocked, false if not blocked
     */
    public function unblockUser(int $blockerUserId, int $blockedUserId): bool
    {
        $stmt = $this->db->pdo()->prepare(
            'DELETE FROM user_blocks
             WHERE blocker_user_id = :blocker_user_id
             AND blocked_user_id = :blocked_user_id'
        );

        $stmt->execute([
            ':blocker_user_id' => $blockerUserId,
            ':blocked_user_id' => $blockedUserId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a user is blocked by another user
     *
     * @param int $blockerUserId User ID who may have blocked
     * @param int $blockedUserId User ID who may be blocked
     * @return bool True if blocked, false otherwise
     */
    public function isBlocked(int $blockerUserId, int $blockedUserId): bool
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT 1 FROM user_blocks
             WHERE blocker_user_id = :blocker_user_id
             AND blocked_user_id = :blocked_user_id
             LIMIT 1'
        );

        $stmt->execute([
            ':blocker_user_id' => $blockerUserId,
            ':blocked_user_id' => $blockedUserId
        ]);

        return $stmt->fetch(PDO::FETCH_COLUMN) !== false;
    }

    /**
     * Get all users blocked by a specific user
     *
     * @param int $blockerUserId User ID who blocked others
     * @return array<int, array<string, mixed>> Array of blocked user records with user details
     */
    public function getBlockedUsers(int $blockerUserId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT
                ub.id,
                ub.blocked_user_id,
                ub.reason,
                ub.created_at,
                u.username,
                u.display_name,
                u.email,
                u.avatar_url,
                u.avatar_preference
             FROM user_blocks ub
             INNER JOIN users u ON ub.blocked_user_id = u.id
             WHERE ub.blocker_user_id = :blocker_user_id
             ORDER BY ub.created_at DESC'
        );

        $stmt->execute([':blocker_user_id' => $blockerUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all users who have blocked a specific user (for analytics/debugging)
     *
     * @param int $blockedUserId User ID who is blocked
     * @return array<int, array<string, mixed>> Array of blocker user records
     */
    public function getBlockedByUsers(int $blockedUserId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT
                ub.id,
                ub.blocker_user_id,
                ub.created_at,
                u.username,
                u.display_name
             FROM user_blocks ub
             INNER JOIN users u ON ub.blocker_user_id = u.id
             WHERE ub.blocked_user_id = :blocked_user_id
             ORDER BY ub.created_at DESC'
        );

        $stmt->execute([':blocked_user_id' => $blockedUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get count of users blocked by a specific user
     *
     * @param int $blockerUserId User ID who blocked others
     * @return int Number of blocked users
     */
    public function getBlockedUserCount(int $blockerUserId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM user_blocks
             WHERE blocker_user_id = :blocker_user_id'
        );

        $stmt->execute([':blocker_user_id' => $blockerUserId]);

        return (int)$stmt->fetchColumn();
    }
}
