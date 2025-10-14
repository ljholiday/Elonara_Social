<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

final class CommunityService
{
    public function __construct(private Database $db) {}

    /** @return array<int,array<string,mixed>> */
    public function listByIds(array $communityIds): array
    {
        $ids = $this->uniqueInts($communityIds);
        if ($ids === []) {
            return [];
        }

        $sql = "SELECT
                    id,
                    name AS title,
                    slug,
                    description,
                    created_at,
                    privacy,
                    member_count,
                    event_count
                FROM vt_communities
                WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string> $values
     * @return array<int>
     */
    private function uniqueInts(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $ints = array_map(static fn($value) => (int)$value, $values);
        $ints = array_values(array_unique($ints));
        sort($ints);

        return $ints;
    }

    public function listRecent(int $limit = 20): array
    {
        $sql = "SELECT
                    id,
                    name AS title,
                    slug,
                    description,
                    created_at,
                    privacy,
                    member_count,
                    event_count
                FROM vt_communities
                ORDER BY COALESCE(created_at, id) DESC
                LIMIT :lim";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByCircle(?array $allowedCommunities, array $memberCommunities, int $limit = 20): array
    {
        $allowedCommunities = $allowedCommunities === null ? null : $this->uniqueInts($allowedCommunities);
        $memberCommunities = $this->uniqueInts($memberCommunities);

        if ($allowedCommunities !== null && $allowedCommunities === []) {
            return [];
        }

        $conditions = [];

        if ($allowedCommunities === null) {
            $privacyParts = ["privacy = 'public'"];
            if ($memberCommunities !== []) {
                $privacyParts[] = 'id IN (' . implode(',', array_fill(0, count($memberCommunities), '?')) . ')';
            }
            $conditions[] = '(' . implode(' OR ', $privacyParts) . ')';
        } else {
            $conditions[] = 'id IN (' . implode(',', array_fill(0, count($allowedCommunities), '?')) . ')';
            $privacyParts = ["privacy = 'public'"];
            if ($memberCommunities !== []) {
                $privacyParts[] = 'id IN (' . implode(',', array_fill(0, count($memberCommunities), '?')) . ')';
            }
            $conditions[] = '(' . implode(' OR ', $privacyParts) . ')';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                    id,
                    name AS title,
                    slug,
                    description,
                    created_at,
                    privacy,
                    member_count,
                    event_count
                FROM vt_communities
                $where
                ORDER BY COALESCE(created_at, id) DESC
                LIMIT $limit";

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare($sql);

        $bindValues = [];
        if ($allowedCommunities === null) {
            foreach ($memberCommunities as $id) {
                $bindValues[] = $id;
            }
        } else {
            foreach ($allowedCommunities as $id) {
                $bindValues[] = $id;
            }
            foreach ($memberCommunities as $id) {
                $bindValues[] = $id;
            }
        }

        foreach ($bindValues as $index => $value) {
            $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function getBySlugOrId(string $slugOrId): ?array
    {
        $pdo = $this->db->pdo();

        if (ctype_digit($slugOrId)) {
            $stmt = $pdo->prepare(
                "SELECT id, name AS title, slug, description, created_at, privacy, member_count, event_count, creator_id
                 FROM vt_communities
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => (int)$slugOrId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, name AS title, slug, description, created_at, privacy, member_count, event_count, creator_id
                 FROM vt_communities
                 WHERE slug = :slug
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slugOrId]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @param array{name:string,description:string,privacy:string} $data
     */
    public function create(array $data): string
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }

        $privacy = $data['privacy'] !== '' ? $data['privacy'] : 'public';
        if (!in_array($privacy, ['public', 'private'], true)) {
            throw new RuntimeException('Invalid privacy value.');
        }

        $pdo = $this->db->pdo();
        $slug = $this->ensureUniqueSlug($pdo, $this->slugify($name));
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO vt_communities (
                name,
                slug,
                description,
                privacy,
                creator_id,
                creator_email,
                created_by,
                created_at,
                updated_at,
                member_count,
                event_count,
                is_active
            ) VALUES (
                :name,
                :slug,
                :description,
                :privacy,
                :creator_id,
                :creator_email,
                :created_by,
                :created_at,
                :updated_at,
                :member_count,
                :event_count,
                :is_active
            )"
        );

        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $data['description'],
            ':privacy' => $privacy,
            ':creator_id' => 1,
            ':creator_email' => 'demo@example.com',
            ':created_by' => 1,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':member_count' => 1,
            ':event_count' => 0,
            ':is_active' => 1,
        ]);

    // ------------------------------------------------------
    // Automatically add creator as admin of new community
    // ------------------------------------------------------
    $communityId = (int)$pdo->lastInsertId();

    $stmtMember = $pdo->prepare("
        INSERT INTO vt_community_members (
            community_id,
            user_id,
            role,
            status,
            joined_at
        ) VALUES (
            :community_id,
            :user_id,
            'admin',
            'active',
            NOW()
        )
    ");

    $stmtMember->execute([
        ':community_id' => $communityId,
        ':user_id' => 1, // same creator as above
    ]);
    // ------------------------------------------------------

        return $slug;
    }

    /**
     * @param array{name:string,description:string,privacy:string} $data
     */
    public function update(string $slugOrId, array $data): string
    {
        $community = $this->getBySlugOrId($slugOrId);
        if ($community === null) {
            throw new RuntimeException('Community not found.');
        }

        $name = trim($data['name']);
        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }

        $privacy = $data['privacy'] !== '' ? $data['privacy'] : 'public';
        if (!in_array($privacy, ['public', 'private'], true)) {
            throw new RuntimeException('Invalid privacy value.');
        }

        $slug = (string)($community['slug'] ?? $slugOrId);
        $pdo = $this->db->pdo();
        $updatedAt = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "UPDATE vt_communities
             SET name = :name,
                 description = :description,
                 privacy = :privacy,
                 updated_at = :updated_at
             WHERE slug = :slug
             LIMIT 1"
        );

        $stmt->execute([
            ':name' => $name,
            ':description' => $data['description'],
            ':privacy' => $privacy,
            ':updated_at' => $updatedAt,
            ':slug' => $slug,
        ]);

        return $slug;
    }

    public function delete(string $slugOrId): bool
    {
        $community = $this->getBySlugOrId($slugOrId);
        if ($community === null) {
            return false;
        }

        $slug = (string)($community['slug'] ?? $slugOrId);
        $pdo = $this->db->pdo();

        $stmt = $pdo->prepare('DELETE FROM vt_communities WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        return $stmt->rowCount() === 1;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'community';
    }

    private function ensureUniqueSlug(PDO $pdo, string $slug): string
    {
        $base = $slug;
        $i = 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vt_communities WHERE slug = :slug');

        while (true) {
            $stmt->execute([':slug' => $slug]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . ++$i;
        }
    }

    public function isMember(int $communityId, int $userId): bool
    {
        if ($communityId <= 0 || $userId <= 0) {
            return false;
        }

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare(
            "SELECT id FROM vt_community_members
             WHERE community_id = :community_id
               AND user_id = :user_id
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $userId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * @param array{user_id:int,email:string,display_name:string,role?:string,status?:string} $memberData
     */
    public function addMember(int $communityId, array $memberData): int
    {
        if ($communityId <= 0) {
            throw new RuntimeException('Invalid community ID.');
        }

        if (!isset($memberData['user_id']) || $memberData['user_id'] <= 0) {
            throw new RuntimeException('User ID is required.');
        }

        if (!isset($memberData['email']) || trim($memberData['email']) === '') {
            throw new RuntimeException('Email is required.');
        }

        if ($this->isMember($communityId, $memberData['user_id'])) {
            throw new RuntimeException('User is already a member.');
        }

        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s');

        $role = $memberData['role'] ?? 'member';
        if (!in_array($role, ['admin', 'member'], true)) {
            $role = 'member';
        }

        $status = $memberData['status'] ?? 'active';
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        $stmt = $pdo->prepare(
            "INSERT INTO vt_community_members (
                community_id,
                user_id,
                email,
                display_name,
                role,
                status,
                joined_at
            ) VALUES (
                :community_id,
                :user_id,
                :email,
                :display_name,
                :role,
                :status,
                :joined_at
            )"
        );

        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $memberData['user_id'],
            ':email' => $memberData['email'],
            ':display_name' => $memberData['display_name'] ?? '',
            ':role' => $role,
            ':status' => $status,
            ':joined_at' => $now,
        ]);

        $memberId = (int)$pdo->lastInsertId();

        $updateStmt = $pdo->prepare(
            "UPDATE vt_communities
             SET member_count = (
                 SELECT COUNT(*) FROM vt_community_members
                 WHERE community_id = :count_community_id AND status = 'active'
             )
             WHERE id = :where_community_id"
        );
        $updateStmt->execute([
            ':count_community_id' => $communityId,
            ':where_community_id' => $communityId,
        ]);

        return $memberId;
    }
}
