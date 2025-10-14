<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * CommunityMemberService
 *
 * Provides read/write operations for community membership rolls without
 * depending on legacy WP-style managers.
 */
final class CommunityMemberService
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Fetch active members for a community ordered by display name/email.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listMembers(int $communityId): array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT
                id,
                community_id,
                user_id,
                email,
                display_name,
                role,
                status,
                joined_at
             FROM vt_community_members
             WHERE community_id = :community_id AND status = 'active'
             ORDER BY
                CASE WHEN display_name IS NULL OR display_name = '' THEN 1 ELSE 0 END,
                display_name ASC,
                email ASC"
        );

        $stmt->execute([
            ':community_id' => $communityId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Determine a member's role inside a community.
     */
    public function getMemberRole(int $communityId, int $userId): ?string
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT role
             FROM vt_community_members
             WHERE community_id = :community_id
               AND user_id = :user_id
               AND status = 'active'
             LIMIT 1"
        );

        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $userId,
        ]);

        $role = $stmt->fetchColumn();

        if ($role === false || $role === null) {
            return null;
        }

        return (string)$role;
    }

    public function isMember(int $communityId, int $userId): bool
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT 1
             FROM vt_community_members
             WHERE community_id = :community_id
               AND user_id = :user_id
               AND status = 'active'
             LIMIT 1"
        );

        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $userId,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * @throws RuntimeException
     */
    public function addMember(int $communityId, int $userId, string $email, string $displayName, string $role = 'member'): int
    {
        $role = strtolower($role);
        if (!in_array($role, ['member', 'moderator', 'admin'], true)) {
            throw new RuntimeException('Invalid role.');
        }

        $email = trim($email);
        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $email;
        }

        $pdo = $this->database->pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM vt_community_members
                 WHERE community_id = :community_id AND user_id = :user_id
                 LIMIT 1"
            );
            $stmt->execute([
                ':community_id' => $communityId,
                ':user_id' => $userId,
            ]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $update = $pdo->prepare(
                    "UPDATE vt_community_members
                     SET email = :email,
                         display_name = :display_name,
                         role = :role,
                         status = 'active',
                         joined_at = COALESCE(joined_at, NOW())
                     WHERE id = :id"
                );
                $update->execute([
                    ':email' => $email,
                    ':display_name' => $displayName,
                    ':role' => $role,
                    ':id' => $existingId,
                ]);
                $memberId = (int)$existingId;
            } else {
                $insert = $pdo->prepare(
                    "INSERT INTO vt_community_members
                        (community_id, user_id, email, display_name, role, status, joined_at)
                     VALUES
                        (:community_id, :user_id, :email, :display_name, :role, 'active', NOW())"
                );
                $insert->execute([
                    ':community_id' => $communityId,
                    ':user_id' => $userId,
                    ':email' => $email,
                    ':display_name' => $displayName,
                    ':role' => $role,
                ]);
                $memberId = (int)$pdo->lastInsertId();
            }

            $this->refreshMemberCount($communityId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw new RuntimeException('Failed to add member: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }

        return $memberId;
    }

    /**
     * Promote/demote a member to a new role.
     *
     * @throws RuntimeException
     */
    public function updateMemberRole(int $communityId, int $memberId, string $role): void
    {
        $role = strtolower($role);

        if (!in_array($role, ['member', 'moderator', 'admin'], true)) {
            throw new RuntimeException('Invalid role.');
        }

        $member = $this->getMemberRecord($communityId, $memberId);

        if ($member === null) {
            throw new RuntimeException('Member not found.');
        }

        if ($member['role'] === 'admin' && $role !== 'admin' && $this->countAdmins($communityId) <= 1) {
            throw new RuntimeException('Cannot demote the last admin. Promote another member first.');
        }

        $stmt = $this->database->pdo()->prepare(
            "UPDATE vt_community_members
             SET role = :role
             WHERE id = :id AND community_id = :community_id"
        );

        $success = $stmt->execute([
            ':role' => $role,
            ':id' => $memberId,
            ':community_id' => $communityId,
        ]);

        if ($success === false || $stmt->rowCount() === 0) {
            throw new RuntimeException('Failed to update member role.');
        }
    }

    /**
     * Remove a member from the community.
     *
     * @throws RuntimeException
     */
    public function removeMember(int $communityId, int $memberId): void
    {
        $member = $this->getMemberRecord($communityId, $memberId);

        if ($member === null) {
            throw new RuntimeException('Member not found.');
        }

        if ($member['role'] === 'admin' && $this->countAdmins($communityId) <= 1) {
            throw new RuntimeException('Cannot remove the only admin. Promote another member first.');
        }

        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM vt_community_members WHERE id = :id AND community_id = :community_id"
        );

        $success = $stmt->execute([
            ':id' => $memberId,
            ':community_id' => $communityId,
        ]);

        if ($success === false || $stmt->rowCount() === 0) {
            throw new RuntimeException('Failed to remove member.');
        }

        $this->refreshMemberCount($communityId);
    }

    /**
     * Retrieve a specific member row.
     *
     * @return array<string, mixed>|null
     */
    private function getMemberRecord(int $communityId, int $memberId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT id, community_id, user_id, email, display_name, role, status
             FROM vt_community_members
             WHERE community_id = :community_id AND id = :id
             LIMIT 1"
        );

        $stmt->execute([
            ':community_id' => $communityId,
            ':id' => $memberId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function countAdmins(int $communityId): int
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT COUNT(*) FROM vt_community_members
             WHERE community_id = :community_id
               AND role = 'admin'
               AND status = 'active'"
        );

        $stmt->execute([
            ':community_id' => $communityId,
        ]);

        return (int)$stmt->fetchColumn();
    }

    private function refreshMemberCount(int $communityId): void
    {
        $stmt = $this->database->pdo()->prepare(
            "UPDATE vt_communities SET member_count = (
                SELECT COUNT(*) FROM vt_community_members
                WHERE community_id = :community_id AND status = 'active'
            )
            WHERE id = :community_id"
        );

        $stmt->execute([':community_id' => $communityId]);
    }
}
