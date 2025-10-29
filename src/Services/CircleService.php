<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class CircleService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Compute hop distances via BFS traversal of user_links graph.
     *
     * @return array<int, int> peerId => hopDistance
     */
    public function computeHops(int $userId, int $maxHops = 3): array
    {
        if ($userId <= 0 || $maxHops < 1) {
            return [];
        }

        $visited = [];
        $queue = [[$userId, 0]];
        $visited[$userId] = 0;

        while ($queue !== []) {
            [$currentUserId, $currentHop] = array_shift($queue);

            if ($currentHop >= $maxHops) {
                continue;
            }

            $peers = $this->getDirectPeers($currentUserId);

            foreach ($peers as $peerId) {
                if (!isset($visited[$peerId])) {
                    $visited[$peerId] = $currentHop + 1;
                    $queue[] = [$peerId, $currentHop + 1];
                }
            }
        }

        unset($visited[$userId]);

        return $visited;
    }

    /**
     * Build context with user IDs at each hop level.
     *
     * @return array{
     *   inner: array<int>,
     *   trusted: array<int>,
     *   extended: array<int>
     * }
     */
    public function buildContext(int $viewerId): array
    {
        if ($viewerId <= 0) {
            return [
                'inner' => [],
                'trusted' => [],
                'extended' => [],
            ];
        }

        $cached = $this->getCachedCircles($viewerId);
        if ($cached !== null) {
            return $cached;
        }

        $hops = $this->computeHops($viewerId, 3);

        $inner = [];
        $trusted = [];
        $extended = [];

        foreach ($hops as $peerId => $hopDistance) {
            if ($hopDistance === 1) {
                $inner[] = (int)$peerId;
            } elseif ($hopDistance === 2) {
                $trusted[] = (int)$peerId;
            } elseif ($hopDistance === 3) {
                $extended[] = (int)$peerId;
            }
        }

        $context = [
            'inner' => $this->uniqueInts($inner),
            'trusted' => $this->uniqueInts($trusted),
            'extended' => $this->uniqueInts($extended),
        ];

        $this->cacheCircles($viewerId, $context);

        return $context;
    }

    /**
     * Resolve cumulative user IDs for a given circle.
     *
     * @return array<int>|null
     */
    public function resolveUsersForCircle(array $context, string $circle): ?array
    {
        $circle = strtolower($circle);
        return match ($circle) {
            'inner' => $context['inner'] ?? [],
            'trusted' => $this->uniqueInts(array_merge(
                $context['inner'] ?? [],
                $context['trusted'] ?? []
            )),
            'extended' => $this->uniqueInts(array_merge(
                $context['inner'] ?? [],
                $context['trusted'] ?? [],
                $context['extended'] ?? []
            )),
            'all' => null,
            default => null,
        };
    }

    /**
     * Get community IDs based on creator hop distance and hosting.
     * Inner = created by user OR hosted by user
     * Trusted = created by inner users
     * Extended = created by trusted users
     *
     * @return array<int>|null Returns null for 'all' circle
     */
    public function getCommunityScope(int $viewerId, string $circle): ?array
    {
        if ($viewerId <= 0) {
            return [];
        }

        $circle = strtolower($circle);

        if ($circle === 'all') {
            return null;
        }

        $context = $this->buildContext($viewerId);

        if ($circle === 'inner') {
            $createdByUser = $this->fetchCommunitiesByCreator([$viewerId]);
            $hostedByUser = $this->fetchCommunitiesWhereHost($viewerId);
            return $this->uniqueInts(array_merge($createdByUser, $hostedByUser));
        }

        if ($circle === 'trusted') {
            // Get Inner communities (cumulative)
            $innerCommunities = $this->getCommunityScope($viewerId, 'inner') ?? [];

            // Get communities created by Inner circle users
            $innerUsers = $context['inner'] ?? [];
            $trustedCreated = $innerUsers === [] ? [] : $this->fetchCommunitiesByCreator($innerUsers);

            // Merge and return
            return $this->uniqueInts(array_merge($innerCommunities, $trustedCreated));
        }

        if ($circle === 'extended') {
            // Get Trusted communities (which includes Inner)
            $trustedCommunities = $this->getCommunityScope($viewerId, 'trusted') ?? [];

            // Get communities created by Trusted circle users
            $trustedUsers = $this->uniqueInts(array_merge(
                $context['inner'] ?? [],
                $context['trusted'] ?? []
            ));
            $extendedCreated = $trustedUsers === [] ? [] : $this->fetchCommunitiesByCreator($trustedUsers);

            // Merge and return
            return $this->uniqueInts(array_merge($trustedCommunities, $extendedCreated));
        }

        return [];
    }

    /**
     * Create bidirectional user link and invalidate both caches.
     */
    public function createLink(int $userId, int $peerId): bool
    {
        if ($userId <= 0 || $peerId <= 0 || $userId === $peerId) {
            return false;
        }

        if ($this->linkExists($userId, $peerId)) {
            return true;
        }

        $pdo = $this->db->pdo();

        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO user_links (user_id, peer_id) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);

            $stmt->execute([$userId, $peerId]);
            $stmt->execute([$peerId, $userId]);

            $pdo->commit();

            $this->invalidateCache($userId);
            $this->invalidateCache($peerId);

            return true;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            file_put_contents(__DIR__ . '/../../debug.log', date('Y-m-d H:i:s') . " CircleService::createLink failed: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    /**
     * Remove bidirectional user link and invalidate both caches.
     */
    public function removeLink(int $userId, int $peerId): bool
    {
        if ($userId <= 0 || $peerId <= 0) {
            return false;
        }

        $pdo = $this->db->pdo();

        try {
            $pdo->beginTransaction();

            $sql = "DELETE FROM user_links WHERE (user_id = ? AND peer_id = ?) OR (user_id = ? AND peer_id = ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $peerId, $peerId, $userId]);

            $pdo->commit();

            $this->invalidateCache($userId);
            $this->invalidateCache($peerId);

            return true;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            file_put_contents(__DIR__ . '/../../debug.log', date('Y-m-d H:i:s') . " CircleService::removeLink failed: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    /**
     * Refresh cached circles for a user.
     */
    public function refreshCache(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->invalidateCache($userId);
        $context = $this->buildContext($userId);
        $this->cacheCircles($userId, $context);
    }

    /**
     * Get direct 1-hop peers for a user.
     *
     * @return array<int>
     */
    private function getDirectPeers(int $userId): array
    {
        $sql = "SELECT peer_id FROM user_links WHERE user_id = ?";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId]);

        /** @var array<int> $peers */
        $peers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $peers);
    }

    /**
     * Check if a link already exists.
     */
    private function linkExists(int $userId, int $peerId): bool
    {
        $sql = "SELECT COUNT(*) FROM user_links WHERE user_id = ? AND peer_id = ?";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId, $peerId]);

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Get cached circles from user_circle_cache.
     *
     * @return array{inner: array<int>, trusted: array<int>, extended: array<int>}|null
     */
    private function getCachedCircles(int $userId): ?array
    {
        $sql = "SELECT circle_json FROM user_circle_cache WHERE user_id = ?";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId]);

        $json = $stmt->fetchColumn();

        if ($json === false) {
            return null;
        }

        $data = json_decode((string)$json, true);

        if (!is_array($data)) {
            return null;
        }

        return [
            'inner' => array_map('intval', $data['inner'] ?? []),
            'trusted' => array_map('intval', $data['trusted'] ?? []),
            'extended' => array_map('intval', $data['extended'] ?? []),
        ];
    }

    /**
     * Cache computed circles.
     */
    private function cacheCircles(int $userId, array $context): void
    {
        $json = json_encode([
            'inner' => $context['inner'] ?? [],
            'trusted' => $context['trusted'] ?? [],
            'extended' => $context['extended'] ?? [],
        ]);

        if ($json === false) {
            return;
        }

        $sql = "INSERT INTO user_circle_cache (user_id, circle_json, updated_at) VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE circle_json = VALUES(circle_json), updated_at = NOW()";

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId, $json]);
    }

    /**
     * Invalidate cache for a user.
     */
    private function invalidateCache(int $userId): void
    {
        $sql = "DELETE FROM user_circle_cache WHERE user_id = ?";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId]);
    }

    /**
     * Fetch communities created by specific users.
     *
     * @param array<int> $userIds
     * @return array<int>
     */
    private function fetchCommunitiesByCreator(array $userIds): array
    {
        $userIds = $this->uniqueInts($userIds);
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT DISTINCT id FROM communities WHERE creator_id IN ($placeholders) AND is_active = 1";

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($userIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        /** @var array<int> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $this->uniqueInts($rows);
    }

    /**
     * Fetch communities where user is a host.
     *
     * @return array<int>
     */
    private function fetchCommunitiesWhereHost(int $userId): array
    {
        $sql = "SELECT DISTINCT community_id FROM community_members WHERE user_id = ? AND role = 'host' AND status = 'active'";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId]);

        /** @var array<int> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $this->uniqueInts($rows);
    }

    /**
     * Get member communities for a user (for access control).
     *
     * @return array<int>
     */
    public function memberCommunities(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = "SELECT DISTINCT community_id FROM community_members WHERE user_id = ? AND status = 'active'";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$userId]);

        /** @var array<int> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $this->uniqueInts($rows);
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

        $ints = array_map(static fn($value) => (int) $value, $values);
        $ints = array_values(array_unique($ints));
        sort($ints);

        return $ints;
    }
}
